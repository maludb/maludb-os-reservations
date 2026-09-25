<?php
/**
 * Notification Service — Email & SMS for reservations and waitlist
 * All functions include restaurant_id context
 */
require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/availability.php'; // getRestaurantSetting()
require_once __DIR__ . '/twilio.php';

use MailerSend\MailerSend;
use MailerSend\Helpers\Builder\Recipient;
use MailerSend\Helpers\Builder\EmailParams;

/**
 * Load an email template and replace placeholders
 */
function loadTemplate(int $restaurantId, string $templateKey, array $vars): ?array
{
    $pdo = db();
    $stmt = $pdo->prepare(
        "SELECT * FROM email_templates WHERE restaurant_id = ? AND template_key = ? AND is_active = 1"
    );
    $stmt->execute([$restaurantId, $templateKey]);
    $tpl = $stmt->fetch();

    if (!$tpl) return null;

    $subject  = $tpl['subject'];
    $bodyHtml = $tpl['body_html'];
    $bodyText = $tpl['body_text'] ?? '';

    foreach ($vars as $key => $val) {
        $placeholder = '{{' . $key . '}}';
        $subject  = str_replace($placeholder, $val, $subject);
        $bodyHtml = str_replace($placeholder, htmlspecialchars($val), $bodyHtml);
        $bodyText = str_replace($placeholder, $val, $bodyText);
    }

    return [
        'subject'   => $subject,
        'body_html' => $bodyHtml,
        'body_text' => $bodyText,
    ];
}

/**
 * Get standard reservation placeholder variables
 */
function getReservationVars(int $reservationId): ?array
{
    $pdo = db();
    $stmt = $pdo->prepare(
        "SELECT r.*, g.first_name, g.last_name, g.email, g.phone,
                t.table_name, rest.name AS restaurant_name, rest.phone AS restaurant_phone,
                rest.email AS restaurant_email, rest.address_line1, rest.city, rest.state
         FROM reservations r
         JOIN guests g ON r.guest_id = g.id
         JOIN restaurants rest ON r.restaurant_id = rest.id
         LEFT JOIN tables t ON r.table_id = t.id
         WHERE r.id = ?"
    );
    $stmt->execute([$reservationId]);
    $row = $stmt->fetch();

    if (!$row) return null;

    return [
        '_row'              => $row,
        'guest_first_name'  => $row['first_name'],
        'guest_last_name'   => $row['last_name'],
        'guest_name'        => $row['first_name'] . ' ' . $row['last_name'],
        'guest_email'       => $row['email'] ?? '',
        'guest_phone'       => $row['phone'] ?? '',
        'restaurant_name'   => $row['restaurant_name'],
        'restaurant_phone'  => $row['restaurant_phone'] ?? '',
        'restaurant_email'  => $row['restaurant_email'] ?? '',
        'restaurant_address'=> trim(($row['address_line1'] ?? '') . ', ' . ($row['city'] ?? '') . ', ' . ($row['state'] ?? ''), ', '),
        'date'              => date('l, F j, Y', strtotime($row['reservation_date'])),
        'time'              => date('g:ia', strtotime($row['reservation_time'])),
        'party_size'        => (string)$row['party_size'],
        'confirmation_code' => $row['confirmation_code'],
        'table_name'        => $row['table_name'] ?? 'TBD',
        'status'            => ucfirst(str_replace('_', ' ', $row['status'])),
        'special_requests'  => $row['special_requests'] ?? '',
    ];
}

/**
 * Send an email via MailerSend API
 */
function sendEmail(int $restaurantId, string $to, string $subject, string $htmlBody, string $textBody = ''): bool
{
    // Get restaurant info
    $stmt = db()->prepare("SELECT name, email FROM restaurants WHERE id = ?");
    $stmt->execute([$restaurantId]);
    $rest = $stmt->fetch();

    $fromEmail = getRestaurantSetting($restaurantId, 'notification_from_email', $rest['email'] ?? 'noreply@example.com');
    $fromName  = $rest['name'] ?? 'Restaurant';

    // Get MailerSend API key
    $apiKey = getRestaurantSetting($restaurantId, 'mailersend_api_key', '');

    // Fallback to PHP mail() if no API key configured
    if ($apiKey === '') {
        $boundary = md5(time());
        $headers = [
            "From: {$fromName} <{$fromEmail}>",
            "Reply-To: {$fromEmail}",
            "MIME-Version: 1.0",
            "Content-Type: multipart/alternative; boundary=\"{$boundary}\"",
        ];
        $body = "--{$boundary}\r\n";
        $body .= "Content-Type: text/plain; charset=UTF-8\r\n\r\n";
        $body .= ($textBody ?: strip_tags($htmlBody)) . "\r\n\r\n";
        $body .= "--{$boundary}\r\n";
        $body .= "Content-Type: text/html; charset=UTF-8\r\n\r\n";
        $body .= $htmlBody . "\r\n\r\n";
        $body .= "--{$boundary}--";
        return @mail($to, $subject, $body, implode("\r\n", $headers));
    }

    try {
        $mailersend = new MailerSend(['api_key' => $apiKey]);

        $recipients = [new Recipient($to, '')];

        $emailParams = (new EmailParams())
            ->setFrom($fromEmail)
            ->setFromName($fromName)
            ->setReplyTo($fromEmail)
            ->setRecipients($recipients)
            ->setSubject($subject)
            ->setHtml($htmlBody)
            ->setText($textBody ?: strip_tags($htmlBody));

        $mailersend->email->send($emailParams);
        return true;
    } catch (\Exception $e) {
        error_log("MailerSend error for restaurant {$restaurantId}: " . $e->getMessage());
        return false;
    }
}

/**
 * Log notification to notifications_log
 */
function logNotification(int $restaurantId, ?int $reservationId, ?int $waitlistId, ?int $guestId,
                         string $type, string $channel, string $recipient, string $subject,
                         string $body, string $status, ?string $providerResponse = null): void
{
    try {
        $pdo = db();
        $stmt = $pdo->prepare(
            "INSERT INTO notifications_log
             (restaurant_id, reservation_id, waitlist_id, guest_id, notification_type, channel, recipient, subject, body, status, provider_response, sent_at)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)"
        );
        $stmt->execute([
            $restaurantId, $reservationId, $waitlistId, $guestId,
            $type, $channel, $recipient, $subject, $body, $status,
            $providerResponse,
            $status === 'sent' ? date('Y-m-d H:i:s') : null
        ]);
    } catch (Exception $e) {
        // Silently fail — don't break the main flow for logging
    }
}

/**
 * Send reservation confirmation email
 */
function sendConfirmationEmail(int $reservationId): bool
{
    $vars = getReservationVars($reservationId);
    if (!$vars) return false;

    $row = $vars['_row'];
    $restaurantId = (int)$row['restaurant_id'];
    unset($vars['_row']);

    $enabled = getRestaurantSetting($restaurantId, 'notification_confirmation_email', '1');
    if ($enabled !== '1') return false;

    $email = $row['email'] ?? '';
    if ($email === '') return false;

    // Try template, fall back to default
    $tpl = loadTemplate($restaurantId, 'confirmation', $vars);

    if ($tpl) {
        $subject  = $tpl['subject'];
        $htmlBody = $tpl['body_html'];
        $textBody = $tpl['body_text'];
    } else {
        $subject = "Reservation Confirmed — {$vars['restaurant_name']}";
        $htmlBody = "<h2>Your reservation is confirmed!</h2>
            <p>Hi {$vars['guest_first_name']},</p>
            <p>Your reservation at <strong>{$vars['restaurant_name']}</strong> has been received.</p>
            <ul>
                <li><strong>Date:</strong> {$vars['date']}</li>
                <li><strong>Time:</strong> {$vars['time']}</li>
                <li><strong>Party Size:</strong> {$vars['party_size']}</li>
                <li><strong>Confirmation Code:</strong> {$vars['confirmation_code']}</li>
            </ul>
            <p>If you need to modify or cancel, use your confirmation code.</p>
            <p>Thank you!<br>{$vars['restaurant_name']}</p>";
        $textBody = '';
    }

    $sent = sendEmail($restaurantId, $email, $subject, $htmlBody, $textBody);

    logNotification($restaurantId, $reservationId, null, (int)$row['guest_id'],
        'confirmation', 'email', $email, $subject, $htmlBody,
        $sent ? 'sent' : 'failed');

    return $sent;
}

/**
 * Send reservation confirmation SMS
 */
function sendConfirmationSMS(int $reservationId): bool
{
    $vars = getReservationVars($reservationId);
    if (!$vars) return false;

    $row = $vars['_row'];
    $restaurantId = (int)$row['restaurant_id'];
    unset($vars['_row']);

    $enabled = getRestaurantSetting($restaurantId, 'notification_confirmation_sms', '0');
    if ($enabled !== '1') return false;

    $phone = $row['phone'] ?? '';
    if ($phone === '') return false;

    $message = "Hi {$vars['guest_first_name']}, your reservation at {$vars['restaurant_name']} is confirmed for {$vars['date']} at {$vars['time']} (party of {$vars['party_size']}). Confirmation code: {$vars['confirmation_code']}";

    $result = twilioSend($restaurantId, $phone, $message);

    logNotification($restaurantId, $reservationId, null, (int)$row['guest_id'],
        'confirmation', 'sms', $phone, 'Reservation Confirmed', $message,
        $result['success'] ? 'sent' : 'failed',
        $result['response'] ?? $result['error'] ?? null);

    return $result['success'];
}

/**
 * Send reservation reminder email
 */
function sendReminderEmail(int $reservationId): bool
{
    $vars = getReservationVars($reservationId);
    if (!$vars) return false;

    $row = $vars['_row'];
    $restaurantId = (int)$row['restaurant_id'];
    unset($vars['_row']);

    $enabled = getRestaurantSetting($restaurantId, 'notification_reminder_email', '1');
    if ($enabled !== '1') return false;

    $email = $row['email'] ?? '';
    if ($email === '') return false;

    $tpl = loadTemplate($restaurantId, 'reminder', $vars);

    if ($tpl) {
        $subject  = $tpl['subject'];
        $htmlBody = $tpl['body_html'];
        $textBody = $tpl['body_text'];
    } else {
        $subject = "Reminder: Your reservation at {$vars['restaurant_name']}";
        $htmlBody = "<h2>Reservation Reminder</h2>
            <p>Hi {$vars['guest_first_name']},</p>
            <p>This is a reminder of your upcoming reservation at <strong>{$vars['restaurant_name']}</strong>.</p>
            <ul>
                <li><strong>Date:</strong> {$vars['date']}</li>
                <li><strong>Time:</strong> {$vars['time']}</li>
                <li><strong>Party Size:</strong> {$vars['party_size']}</li>
                <li><strong>Confirmation Code:</strong> {$vars['confirmation_code']}</li>
            </ul>
            <p>We look forward to seeing you!</p>
            <p>{$vars['restaurant_name']}<br>{$vars['restaurant_phone']}</p>";
        $textBody = '';
    }

    $sent = sendEmail($restaurantId, $email, $subject, $htmlBody, $textBody);

    logNotification($restaurantId, $reservationId, null, (int)$row['guest_id'],
        'reminder', 'email', $email, $subject, $htmlBody,
        $sent ? 'sent' : 'failed');

    return $sent;
}

/**
 * Send cancellation email
 */
function sendCancellationEmail(int $reservationId): bool
{
    $vars = getReservationVars($reservationId);
    if (!$vars) return false;

    $row = $vars['_row'];
    $restaurantId = (int)$row['restaurant_id'];
    unset($vars['_row']);

    $enabled = getRestaurantSetting($restaurantId, 'notification_cancellation_email', '1');
    if ($enabled !== '1') return false;

    $email = $row['email'] ?? '';
    if ($email === '') return false;

    $tpl = loadTemplate($restaurantId, 'cancellation', $vars);

    if ($tpl) {
        $subject  = $tpl['subject'];
        $htmlBody = $tpl['body_html'];
        $textBody = $tpl['body_text'];
    } else {
        $subject = "Reservation Cancelled — {$vars['restaurant_name']}";
        $htmlBody = "<h2>Reservation Cancelled</h2>
            <p>Hi {$vars['guest_first_name']},</p>
            <p>Your reservation at <strong>{$vars['restaurant_name']}</strong> has been cancelled.</p>
            <ul>
                <li><strong>Date:</strong> {$vars['date']}</li>
                <li><strong>Time:</strong> {$vars['time']}</li>
                <li><strong>Confirmation Code:</strong> {$vars['confirmation_code']}</li>
            </ul>
            <p>If this was a mistake, please contact us at {$vars['restaurant_phone']}.</p>
            <p>{$vars['restaurant_name']}</p>";
        $textBody = '';
    }

    $sent = sendEmail($restaurantId, $email, $subject, $htmlBody, $textBody);

    logNotification($restaurantId, $reservationId, null, (int)$row['guest_id'],
        'cancellation', 'email', $email, $subject, $htmlBody,
        $sent ? 'sent' : 'failed');

    return $sent;
}

/**
 * Send waitlist SMS notification
 */
function sendWaitlistSMS(int $waitlistId): bool
{
    $pdo = db();
    $stmt = $pdo->prepare(
        "SELECT w.*, g.first_name, g.last_name, g.phone AS guest_phone,
                rest.name AS restaurant_name
         FROM waitlist w
         LEFT JOIN guests g ON w.guest_id = g.id
         JOIN restaurants rest ON w.restaurant_id = rest.id
         WHERE w.id = ?"
    );
    $stmt->execute([$waitlistId]);
    $entry = $stmt->fetch();

    if (!$entry) return false;

    $restaurantId = (int)$entry['restaurant_id'];
    $phone = $entry['phone'] ?: ($entry['guest_phone'] ?? '');
    $name  = $entry['guest_id'] ? ($entry['first_name'] . ' ' . $entry['last_name']) : ($entry['guest_name'] ?: 'Guest');

    if ($phone === '') return false;

    $message = "{$name}, your table at {$entry['restaurant_name']} is ready! Please check in with the host.";

    $result = twilioSend($restaurantId, $phone, $message);

    logNotification($restaurantId, null, $waitlistId, $entry['guest_id'] ? (int)$entry['guest_id'] : null,
        'waitlist_ready', 'sms', $phone, 'Table Ready', $message,
        $result['success'] ? 'sent' : 'failed',
        $result['response'] ?? $result['error'] ?? null);

    return $result['success'];
}
