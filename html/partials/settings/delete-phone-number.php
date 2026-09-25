<?php
/**
 * Delete a restaurant phone number.
 */
require_once __DIR__ . '/../../../helpers/auth.php';
require_once __DIR__ . '/../../../helpers/csrf.php';

requireAuth();
if (!isAdmin() && !isSuperAdmin()) {
    http_response_code(403);
    exit('Forbidden');
}
if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
    http_response_code(403);
    echo '<div class="alert alert-danger">Invalid CSRF token.</div>';
    exit;
}

$restaurantId = currentRestaurantId();
$id = (int)($_POST['id'] ?? 0);

if ($id <= 0) {
    echo '<div class="alert alert-danger">Invalid phone number ID.</div>';
    exit;
}

require_once __DIR__ . '/../../../helpers/services/team.php';

try {
    svc_phone_delete($restaurantId, $id);
    echo '<div class="alert alert-success">Phone number deleted.</div>';
} catch (ServiceError $e) {
    echo '<div class="alert alert-warning">Phone number not found.</div>';
} catch (PDOException $e) {
    echo '<div class="alert alert-danger">Error deleting phone number: ' . htmlspecialchars($e->getMessage()) . '</div>';
}
