<?php
/**
 * POST handler: Update reservation status
 * Validates transitions, sets timestamps, updates guest stats
 */
require_once '../../../helpers/auth.php';
require_once '../../../helpers/csrf.php';

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
$newStatus = trim($_POST['new_status'] ?? '');

if (!$resId || !$restaurantId) {
    echo '<div class="alert alert-danger">Invalid request.</div>';
    exit;
}

require_once '../../../helpers/services/reservations.php';

try {
    svc_reservation_set_status($restaurantId, currentUserId(), $resId, $newStatus);
} catch (ServiceError $e) {
    echo '<div class="alert alert-danger" id="status-update-error">' . htmlspecialchars($e->getMessage()) . '</div>';
    exit;
} catch (Throwable $e) {
    echo '<div class="alert alert-danger" id="status-update-error">An error occurred while updating status.</div>';
    exit;
}

// If called from waitlist, show success message and trigger refresh
$fromWaitlist = ($_POST['from_waitlist'] ?? '') === '1';
if ($fromWaitlist) {
    header('HX-Trigger: refreshWaitlist');
    echo '<div class="alert alert-success alert-dismissible fade show">
            <i class="feather-check-circle me-1"></i> Reservation seated.
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
          </div>';
    exit;
}

// Reload the detail page via HTMX retarget (stay in #page-content)
$_GET['id'] = $resId;
include __DIR__ . '/detail.php';
exit;
