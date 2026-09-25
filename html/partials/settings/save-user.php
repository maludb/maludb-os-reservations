<?php
require_once '../../../helpers/auth.php';
require_once '../../../helpers/csrf.php';

requireAuth();
requireAdmin();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo '<div class="alert alert-warning" id="save-user-method-error">Invalid request method.</div>';
    exit;
}

if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
    http_response_code(403);
    echo '<div class="alert alert-danger" id="save-user-csrf-error">Invalid security token. Please refresh and try again.</div>';
    exit;
}

$restaurantId = currentRestaurantId();
if (!$restaurantId) {
    http_response_code(400);
    echo '<div class="alert alert-danger" id="save-user-no-restaurant">No restaurant selected.</div>';
    exit;
}

$userId = (int)($_POST['user_id'] ?? 0);
$isEdit = $userId > 0;

require_once '../../../helpers/services/team.php';

try {
    svc_team_save($restaurantId, $userId, $_POST);
} catch (ServiceError $e) {
    // Keep the per-error alert ids the form used before
    $suffix = $e->field !== '' ? $e->field . '-error' : 'error';
    if ($e->field === 'notfound') $suffix = 'notfound';
    echo '<div class="alert alert-danger" id="save-user-' . $suffix . '">' . htmlspecialchars($e->getMessage()) . '</div>';
    exit;
} catch (Throwable $e) {
    echo '<div class="alert alert-danger" id="save-user-error">An error occurred while saving. Please try again.</div>';
    exit;
}

// Trigger a refresh of the user list (must be before output)
header('HX-Trigger: refreshUserList');

echo '<div class="alert alert-success alert-dismissible fade show" id="save-user-success">
        <i class="feather-check-circle me-1"></i> Staff member ' . ($isEdit ? 'updated' : 'added') . ' successfully.
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
      </div>';
echo '<script>document.getElementById("users-modal-container").innerHTML="";</script>';
