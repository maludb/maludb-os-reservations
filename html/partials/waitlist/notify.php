<?php
/**
 * Waitlist Notify — "Table Ready" button handler
 * Sends SMS if phone is on file, otherwise just marks as called.
 * Tracks notify_count and notified_at timestamp.
 * Allows up to 2 calls before suggesting no_response.
 */
require_once __DIR__ . '/../../../helpers/auth.php';
require_once __DIR__ . '/../../../helpers/db.php';
require_once __DIR__ . '/../../../helpers/csrf.php';

requireAuth();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo '<div class="alert alert-danger">Invalid request.</div>';
    exit;
}

if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
    echo '<div class="alert alert-danger">Invalid security token.</div>';
    exit;
}

$restaurantId = currentRestaurantId();

require_once __DIR__ . '/../../../helpers/services/waitlist.php';

try {
    $r = svc_waitlist_notify($restaurantId, currentUserId(), (int)($_POST['id'] ?? 0));
} catch (ServiceError $e) {
    echo '<div class="alert alert-warning" id="waitlist-notify-missing">' . htmlspecialchars($e->getMessage()) . '</div>';
    exit;
}

header('HX-Trigger: refreshWaitlist');

$name = htmlspecialchars($r['name']);
if ($r['phone'] !== '' && $r['sms_sent']) {
    echo '<div class="alert alert-success" id="waitlist-notify-sent">SMS sent to ' . $name . ' at ' . htmlspecialchars($r['phone']) . ' (call #' . $r['notify_count'] . ').</div>';
} elseif ($r['phone'] !== '') {
    echo '<div class="alert alert-warning" id="waitlist-notify-failed">Marked as called (call #' . $r['notify_count'] . '). SMS could not be sent — check Twilio settings.</div>';
} else {
    echo '<div class="alert alert-info" id="waitlist-notify-nophone">Marked as called for ' . $name . ' (call #' . $r['notify_count'] . '). No phone on file.</div>';
}
