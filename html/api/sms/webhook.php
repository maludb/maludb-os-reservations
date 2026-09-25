<?php
/**
 * Twilio Inbound SMS Webhook
 *
 * Receives incoming SMS from Twilio, processes through OpenAI GPT-4.1
 * with tool calling (using the restaurant MCP tools from server-tools.php),
 * and sends the response back via Twilio SMS.
 *
 * Matches company by recipient phone number (restaurant_phone_numbers first,
 * then text_agent_prompts, then settings fallback).
 * Identifies the guest by from_number (guests).
 * Logs inbound and outbound messages to sms_message_log.
 *
 * Twilio webhook URL: https://yourdomain.com/api/sms/webhook.php
 * HTTP Method: POST
 */

require_once __DIR__ . '/../../../helpers/db.php';
require_once __DIR__ . '/../../../helpers/availability.php';
require_once __DIR__ . '/../../../helpers/twilio.php';
require_once __DIR__ . '/../../../helpers/openai-sms.php';
require_once __DIR__ . '/../../../helpers/restaurant.php';

// --- Logging ---
define('SMS_WEBHOOK_LOG', __DIR__ . '/../../../logs/sms-webhook.log');

function webhookLog(string $message): void
{
    $ts = date('Y-m-d H:i:s');
    file_put_contents(SMS_WEBHOOK_LOG, "[{$ts}] {$message}\n", FILE_APPEND);
}

// --- Main ---
webhookLog("=== INBOUND SMS ===");

// Twilio sends POST data
$fromNumber = $_POST['From'] ?? '';
$toNumber   = $_POST['To'] ?? '';
$body       = trim($_POST['Body'] ?? '');
$messageSid = $_POST['MessageSid'] ?? '';

webhookLog("FROM: {$fromNumber} | TO: {$toNumber} | SID: {$messageSid}");
webhookLog("BODY: {$body}");

if ($fromNumber === '' || $toNumber === '' || $body === '') {
    webhookLog("ERROR: Missing required fields");
    header('Content-Type: text/xml');
    echo '<Response></Response>';
    exit;
}

// --- Look up restaurant by the "To" phone number ---
$pdo = db();
$restaurantId = null;
$phoneNumberId = null;

// 1. Check restaurant_phone_numbers table first
$toDigits = normalizePhone($toNumber);
$rpnStmt = $pdo->prepare(
    "SELECT rpn.id as phone_number_id, rpn.restaurant_id, r.name as restaurant_name
     FROM restaurant_phone_numbers rpn
     JOIN restaurants r ON rpn.restaurant_id = r.id
     WHERE rpn.is_active = 1
     ORDER BY rpn.id"
);
$rpnStmt->execute();
$rpnRows = $rpnStmt->fetchAll();

foreach ($rpnRows as $rpn) {
    $rpnDigits = normalizePhone($rpn['phone_number'] ?? '');
    $rpnDigits10 = strlen($rpnDigits) > 10 ? substr($rpnDigits, -10) : $rpnDigits;
    $toDigits10 = strlen($toDigits) > 10 ? substr($toDigits, -10) : $toDigits;
    if ($rpnDigits === $toDigits || $rpnDigits10 === $toDigits10) {
        $restaurantId = (int)$rpn['restaurant_id'];
        $phoneNumberId = (int)$rpn['phone_number_id'];
        webhookLog("MATCHED via restaurant_phone_numbers id={$phoneNumberId}");
        break;
    }
}

// 2. Fallback: check text_agent_prompts for phone match
if (!$restaurantId) {
    $stmt = $pdo->prepare(
        "SELECT tap.*, r.id as restaurant_id, r.slug, r.name as restaurant_name
         FROM text_agent_prompts tap
         JOIN restaurants r ON tap.restaurant_id = r.id
         WHERE tap.phone_number = ?
         LIMIT 1"
    );
    $stmt->execute([$toNumber]);
    $agent = $stmt->fetch();
    if ($agent) {
        $restaurantId = (int)$agent['restaurant_id'];
        webhookLog("MATCHED via text_agent_prompts");
    }
}

// 3. Fallback: check sms_from_number in settings
if (!$restaurantId) {
    $stmt = $pdo->prepare(
        "SELECT s.restaurant_id, r.slug, r.name as restaurant_name
         FROM settings s
         JOIN restaurants r ON s.restaurant_id = r.id
         WHERE s.setting_key = 'sms_from_number' AND s.setting_value = ?
         LIMIT 1"
    );
    $stmt->execute([$toNumber]);
    $agent = $stmt->fetch();
    if ($agent) {
        $restaurantId = (int)$agent['restaurant_id'];
        webhookLog("MATCHED via settings sms_from_number");
    }
}

if (!$restaurantId) {
    webhookLog("ERROR: No restaurant found for number {$toNumber}");
    header('Content-Type: text/xml');
    echo '<Response></Response>';
    exit;
}

webhookLog("RESTAURANT: {$restaurantId}");

// --- Identify guest by from_number ---
$guestId = null;
$fromDigits = normalizePhone($fromNumber);
$fromDigits10 = strlen($fromDigits) > 10 ? substr($fromDigits, -10) : $fromDigits;

$guestStmt = $pdo->prepare(
    "SELECT id, phone FROM guests WHERE restaurant_id = ? AND phone IS NOT NULL AND phone != ''"
);
$guestStmt->execute([$restaurantId]);
$guests = $guestStmt->fetchAll();

foreach ($guests as $g) {
    $gDigits = normalizePhone($g['phone']);
    $gDigits10 = strlen($gDigits) > 10 ? substr($gDigits, -10) : $gDigits;
    if ($gDigits === $fromDigits || $gDigits10 === $fromDigits10) {
        $guestId = (int)$g['id'];
        webhookLog("GUEST MATCHED: id={$guestId}");
        break;
    }
}

if (!$guestId) {
    webhookLog("GUEST: No matching guest found for {$fromNumber}");
}

// --- Log inbound message to sms_message_log ---
$logStmt = $pdo->prepare(
    "INSERT INTO sms_message_log (restaurant_id, guest_id, phone_number_id, direction, from_number, to_number, body, status, external_id)
     VALUES (?, ?, ?, 'inbound', ?, ?, ?, 'received', ?)"
);
$logStmt->execute([$restaurantId, $guestId, $phoneNumberId, $fromNumber, $toNumber, $body, $messageSid]);
webhookLog("LOGGED inbound message id=" . $pdo->lastInsertId());

// --- Process through OpenAI ---
$response = processInboundSMS($restaurantId, $fromNumber, $toNumber, $body);

if ($response === '') {
    webhookLog("ERROR: Empty response from OpenAI");
    header('Content-Type: text/xml');
    echo '<Response></Response>';
    exit;
}

// --- Send reply via Twilio ---
webhookLog("SENDING REPLY: " . mb_substr($response, 0, 200));

$sendResult = twilioSend($restaurantId, $fromNumber, $response);

$replyStatus = $sendResult['success'] ? 'sent' : 'failed';
$replySid = $sendResult['sid'] ?? null;

// --- Log outbound reply to sms_message_log ---
$logStmt = $pdo->prepare(
    "INSERT INTO sms_message_log (restaurant_id, guest_id, phone_number_id, direction, from_number, to_number, body, status, external_id)
     VALUES (?, ?, ?, 'outbound', ?, ?, ?, ?, ?)"
);
$logStmt->execute([$restaurantId, $guestId, $phoneNumberId, $toNumber, $fromNumber, $response, $replyStatus, $replySid]);
webhookLog("LOGGED outbound reply id=" . $pdo->lastInsertId());

if ($sendResult['success']) {
    webhookLog("REPLY SENT OK — SID: " . $sendResult['sid']);
} else {
    webhookLog("REPLY FAILED: " . ($sendResult['error'] ?? 'unknown'));
}

// Return empty TwiML (we're sending via REST API, not TwiML response)
header('Content-Type: text/xml');
echo '<Response></Response>';

webhookLog("=== END ===");
