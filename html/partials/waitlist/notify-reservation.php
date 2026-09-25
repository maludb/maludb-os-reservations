<?php
/**
 * POST handler: "Table Ready" notification for a reservation from the waitlist screen
 * Sends SMS if phone is on file, otherwise just shows a confirmation.
 */
require_once __DIR__ . '/../../../helpers/auth.php';
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
    $r = svc_waitlist_notify_reservation($restaurantId, currentUserId(), (int)($_POST['reservation_id'] ?? 0));
} catch (ServiceError $e) {
    echo '<div class="alert alert-warning" id="notify-res-missing">' . htmlspecialchars($e->getMessage()) . '</div>';
    exit;
}

header('HX-Trigger: refreshWaitlist');

$name = htmlspecialchars($r['name']);
if ($r['phone'] !== '' && $r['sms_sent']) {
    echo '<div class="alert alert-success alert-dismissible fade show" id="notify-res-sent">
            <i class="feather-check-circle me-1"></i> SMS sent to ' . $name . ' at ' . htmlspecialchars($r['phone']) . '.
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
          </div>';
} elseif ($r['phone'] !== '') {
    echo '<div class="alert alert-warning alert-dismissible fade show" id="notify-res-failed">
            <i class="feather-alert-circle me-1"></i> Marked as called for ' . $name . '. SMS could not be sent.
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
          </div>';
} else {
    echo '<div class="alert alert-info alert-dismissible fade show" id="notify-res-nophone">
            <i class="feather-info me-1"></i> Call guest ' . $name . ' — no phone on file.
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
          </div>';
}
