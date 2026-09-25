<?php
/**
 * Cron-compatible Reminder Script
 * Finds reservations needing reminders across ALL restaurants and sends them.
 *
 * Usage: php /var/www/helpers/send-reminders.php
 * Cron:  0 * * * * php /var/www/helpers/send-reminders.php >> /var/log/reservation-reminders.log 2>&1
 */
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/restaurant.php';
require_once __DIR__ . '/availability.php';
require_once __DIR__ . '/notifications.php';

$pdo = db();

// Get all active restaurants
$stmt = $pdo->query("SELECT id, name, timezone FROM restaurants WHERE is_active = 1");
$restaurants = $stmt->fetchAll();

$totalSent = 0;
$totalFailed = 0;

foreach ($restaurants as $rest) {
    $restaurantId = (int)$rest['id'];
    applyRestaurantTimezone($restaurantId);

    // Check if reminders are enabled
    $enabled = getRestaurantSetting($restaurantId, 'notification_reminder_email', '1');
    if ($enabled !== '1') continue;

    $hoursBefore = (int)getRestaurantSetting($restaurantId, 'reminder_hours_before', '24');

    // Find reservations that:
    // 1. Are confirmed or pending
    // 2. Are within the reminder window (reservation datetime is X hours from now)
    // 3. Haven't already been sent a reminder
    $stmt = $pdo->prepare(
        "SELECT r.id, r.confirmation_code, r.reservation_date, r.reservation_time
         FROM reservations r
         WHERE r.restaurant_id = ?
           AND r.status IN ('pending', 'confirmed')
           AND FLOOR(EXTRACT(EPOCH FROM ((r.reservation_date + r.reservation_time) - NOW())) / 3600) BETWEEN 0 AND ?
           AND r.id NOT IN (
               SELECT COALESCE(reservation_id, 0) FROM notifications_log
               WHERE restaurant_id = ? AND notification_type = 'reminder' AND status = 'sent'
           )
         ORDER BY r.reservation_date, r.reservation_time"
    );
    $stmt->execute([$restaurantId, $hoursBefore, $restaurantId]);
    $reservations = $stmt->fetchAll();

    foreach ($reservations as $res) {
        $sent = sendReminderEmail($res['id']);
        if ($sent) {
            $totalSent++;
            echo date('Y-m-d H:i:s') . " [OK] Reminder sent for reservation #{$res['confirmation_code']} at {$rest['name']}\n";
        } else {
            $totalFailed++;
            echo date('Y-m-d H:i:s') . " [FAIL] Could not send reminder for reservation #{$res['confirmation_code']} at {$rest['name']}\n";
        }
    }
}

echo date('Y-m-d H:i:s') . " Reminder run complete. Sent: {$totalSent}, Failed: {$totalFailed}\n";
