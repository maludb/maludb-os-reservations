<?php
/**
 * POST handler: Check in a reservation from the waitlist screen
 */
require_once __DIR__ . '/../../../helpers/auth.php';
require_once __DIR__ . '/../../../helpers/csrf.php';

requireAuth();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo '<div class="alert alert-warning">Invalid request method.</div>';
    exit;
}

if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
    http_response_code(403);
    echo '<div class="alert alert-danger">Invalid security token.</div>';
    exit;
}

$restaurantId = currentRestaurantId();
$resId = (int)($_POST['reservation_id'] ?? 0);

if (!$resId || !$restaurantId) {
    echo '<div class="alert alert-danger">Invalid request.</div>';
    exit;
}

require_once __DIR__ . '/../../../helpers/services/waitlist.php';

try {
    svc_waitlist_checkin_reservation($restaurantId, currentUserId(), $resId);
} catch (ServiceError $e) {
    $cls = $e->status === 404 ? 'danger' : 'warning';
    echo '<div class="alert alert-' . $cls . '" id="checkin-error">' . htmlspecialchars($e->getMessage()) . '</div>';
    exit;
}

// Refresh waitlist
header('HX-Trigger: refreshWaitlist');
echo '<div class="alert alert-success alert-dismissible fade show" id="checkin-success">
        <i class="feather-check-circle me-1"></i> Guest checked in.
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
      </div>';
