<?php
/**
 * Waitlist Save — Add guest to waitlist
 */
require_once __DIR__ . '/../../../helpers/auth.php';
require_once __DIR__ . '/../../../helpers/db.php';
require_once __DIR__ . '/../../../helpers/csrf.php';
require_once __DIR__ . '/../../../helpers/availability.php';

requireAuth();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo '<div class="alert alert-danger">Invalid request.</div>';
    exit;
}

if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
    echo '<div class="alert alert-danger">Invalid security token. Please refresh and try again.</div>';
    exit;
}

$restaurantId = currentRestaurantId();

require_once __DIR__ . '/../../../helpers/services/waitlist.php';

try {
    $entry = svc_waitlist_add($restaurantId, currentUserId(), $_POST);
} catch (ServiceError $e) {
    echo '<div class="alert alert-danger" id="waitlist-save-error">' . htmlspecialchars($e->getMessage()) . '</div>';
    exit;
}

header('HX-Trigger: refreshWaitlist');
echo '<div class="alert alert-success" id="waitlist-save-success">Added to waitlist at position #' . $entry['queue_position'] . ' (~' . $entry['estimated_wait_minutes'] . ' min wait).</div>';
echo '<script>document.getElementById("waitlist-modal-container").innerHTML="";</script>';
