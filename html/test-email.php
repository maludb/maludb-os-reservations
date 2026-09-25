<?php
/**
 * Test Email — Send a test email via MailerSend
 */
require_once __DIR__ . '/../helpers/db.php';
require_once __DIR__ . '/../helpers/availability.php';
require_once __DIR__ . '/../helpers/notifications.php';

$restaurantId = 1;

// Override from email to support@zozocal.com
$pdo = db();
$stmt = $pdo->prepare(
    "INSERT INTO settings (restaurant_id, setting_key, setting_value)
     VALUES (?, 'notification_from_email', ?) ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)"
);
$stmt->execute([$restaurantId, 'support@zozocal.com']);

$to      = 'edward.honour@gmail.com';
$subject = 'ZozoCal Test Email — MailerSend Integration';
$html    = '<h2>MailerSend Integration Test</h2>
<p>This is a test email sent via MailerSend from ZozoCal.</p>
<p>If you received this, email delivery is working correctly.</p>
<p>Sent at: ' . date('Y-m-d H:i:s T') . '</p>';

$result = sendEmail($restaurantId, $to, $subject, $html);

if ($result) {
    echo "Email sent successfully to {$to}\n";
} else {
    echo "Failed to send email. Check error log for details.\n";
}
