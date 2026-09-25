<?php
/**
 * Inbound Email Webhook
 *
 * Receives incoming emails (e.g. from SendGrid Inbound Parse, Mailgun, etc.),
 * processes through OpenAI GPT-4.1 with tool calling (using MCP tools),
 * and sends the response back via the configured email provider.
 *
 * Webhook URL: https://yourdomain.com/api/email/webhook.php
 * HTTP Method: POST
 */

require_once __DIR__ . '/../../../helpers/db.php';
require_once __DIR__ . '/../../../helpers/availability.php';
require_once __DIR__ . '/../../../helpers/openai-email.php';

// --- Logging ---
define('EMAIL_WEBHOOK_LOG', __DIR__ . '/../../../logs/email-webhook.log');

function emailWebhookLog(string $message): void
{
    $ts = date('Y-m-d H:i:s');
    file_put_contents(EMAIL_WEBHOOK_LOG, "[{$ts}] {$message}\n", FILE_APPEND);
}

// --- Main ---
emailWebhookLog("=== INBOUND EMAIL ===");

// Parse inbound email fields (SendGrid Inbound Parse format)
$fromEmail = $_POST['from'] ?? '';
$toEmail   = $_POST['to'] ?? '';
$subject   = trim($_POST['subject'] ?? '');
$body      = trim($_POST['text'] ?? $_POST['html'] ?? '');

// Extract just the email address from "Name <email>" format
if (preg_match('/<([^>]+)>/', $fromEmail, $m)) {
    $fromEmail = $m[1];
}
if (preg_match('/<([^>]+)>/', $toEmail, $m)) {
    $toEmail = $m[1];
}

$fromEmail = strtolower(trim($fromEmail));
$toEmail   = strtolower(trim($toEmail));

emailWebhookLog("FROM: {$fromEmail} | TO: {$toEmail} | SUBJECT: {$subject}");
emailWebhookLog("BODY: " . mb_substr($body, 0, 500));

if ($fromEmail === '' || $toEmail === '' || $body === '') {
    emailWebhookLog("ERROR: Missing required fields");
    http_response_code(200);
    echo json_encode(['status' => 'ignored', 'reason' => 'missing fields']);
    exit;
}

// --- Look up restaurant by the "To" email address ---
$pdo = db();

// Check email_agent_prompts for email match
$stmt = $pdo->prepare(
    "SELECT eap.*, r.id as restaurant_id, r.slug, r.name as restaurant_name
     FROM email_agent_prompts eap
     JOIN restaurants r ON eap.restaurant_id = r.id
     WHERE LOWER(eap.email_address) = ?
     LIMIT 1"
);
$stmt->execute([$toEmail]);
$agent = $stmt->fetch();

if (!$agent) {
    emailWebhookLog("ERROR: No email agent found for address {$toEmail}");
    http_response_code(200);
    echo json_encode(['status' => 'ignored', 'reason' => 'no agent for address']);
    exit;
}

$restaurantId = (int)$agent['restaurant_id'];
emailWebhookLog("RESTAURANT: {$restaurantId} ({$agent['restaurant_name']})");

// --- Process through OpenAI ---
$response = processInboundEmail($restaurantId, $fromEmail, $toEmail, $subject, $body);

if ($response === '') {
    emailWebhookLog("ERROR: Empty response from OpenAI");
    http_response_code(200);
    echo json_encode(['status' => 'error', 'reason' => 'empty response']);
    exit;
}

// --- Send reply email ---
// Uses the existing MailerSend integration from notifications helper
emailWebhookLog("SENDING REPLY: " . mb_substr($response, 0, 200));

require_once __DIR__ . '/../../../helpers/notifications.php';

$replySubject = 'Re: ' . $subject;
$htmlBody = '<div style="font-family: Arial, sans-serif; line-height: 1.6;">'
    . nl2br(htmlspecialchars($response))
    . '</div>';

$fromEmailAddr = getRestaurantSetting($restaurantId, 'notification_from_email', $toEmail);
$mailApiKey    = getRestaurantSetting($restaurantId, 'mailersend_api_key', '');

if ($mailApiKey === '') {
    emailWebhookLog("ERROR: No MailerSend API key configured");
    http_response_code(200);
    echo json_encode(['status' => 'error', 'reason' => 'no mail api key']);
    exit;
}

// Send via MailerSend
$mailPayload = [
    'from' => ['email' => $fromEmailAddr],
    'to'   => [['email' => $fromEmail]],
    'subject' => $replySubject,
    'html'    => $htmlBody,
    'text'    => $response,
];

$ch = curl_init('https://api.mailersend.com/v1/email');
curl_setopt_array($ch, [
    CURLOPT_POST           => true,
    CURLOPT_POSTFIELDS     => json_encode($mailPayload),
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_TIMEOUT        => 15,
    CURLOPT_HTTPHEADER     => [
        'Content-Type: application/json',
        "Authorization: Bearer {$mailApiKey}",
    ],
]);

$mailResponse = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

if ($httpCode >= 200 && $httpCode < 300) {
    emailWebhookLog("REPLY SENT OK — HTTP {$httpCode}");
} else {
    emailWebhookLog("REPLY FAILED — HTTP {$httpCode}: {$mailResponse}");
}

http_response_code(200);
echo json_encode(['status' => 'ok']);

emailWebhookLog("=== END ===");
