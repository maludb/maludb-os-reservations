<?php
require_once '../../../helpers/auth.php';
require_once '../../../helpers/csrf.php';

requireAuth();
requireAdmin();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo '<div class="alert alert-warning" id="toggle-user-method-error">Invalid request method.</div>';
    exit;
}

if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
    http_response_code(403);
    echo '<div class="alert alert-danger" id="toggle-user-csrf-error">Invalid security token. Please refresh and try again.</div>';
    exit;
}

$restaurantId = currentRestaurantId();
$userId = (int)($_POST['user_id'] ?? 0);

if (!$restaurantId || !$userId) {
    http_response_code(400);
    echo '<div class="alert alert-danger" id="toggle-user-invalid">Invalid request.</div>';
    exit;
}

require_once '../../../helpers/services/team.php';

try {
    $member = svc_team_set_active($restaurantId, currentUserId(), $userId);
} catch (ServiceError $e) {
    $ids = ['self' => 'toggle-user-self-error', 'notfound' => 'toggle-user-notfound'];
    echo '<div class="alert alert-danger" id="' . ($ids[$e->field] ?? 'toggle-user-error') . '">' . htmlspecialchars($e->getMessage()) . '</div>';
    exit;
}

$statusLabel = $member['membership_active'] ? 'activated' : 'deactivated';

// Trigger refresh (must be before output)
header('HX-Trigger: refreshUserList');

echo '<div class="alert alert-success alert-dismissible fade show" id="toggle-user-success">
        <i class="feather-check-circle me-1"></i> Staff member ' . $statusLabel . ' successfully.
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
      </div>';
