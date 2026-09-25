<?php
/**
 * Save (create or update) a restaurant phone number.
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

require_once __DIR__ . '/../../../helpers/services/team.php';

try {
    svc_phone_save($restaurantId, $id, [
        'phone_number'  => $_POST['phone_number'] ?? '',
        'label'         => $_POST['label'] ?? '',
        'language_code' => $_POST['language_code'] ?? 'en',
        'language_name' => $_POST['language_name'] ?? 'English',
        'is_primary'    => isset($_POST['is_primary']),
    ]);
    echo '<div class="alert alert-success">Phone number ' . ($id > 0 ? 'updated' : 'added') . '.</div>';
} catch (ServiceError $e) {
    echo '<div class="alert alert-danger">' . htmlspecialchars($e->getMessage()) . '</div>';
} catch (PDOException $e) {
    echo '<div class="alert alert-danger">Error saving phone number: ' . htmlspecialchars($e->getMessage()) . '</div>';
}
