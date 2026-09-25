<?php
require_once '../../../helpers/auth.php';
require_once '../../../helpers/csrf.php';

requireAuth();
requireManager();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo '<div class="alert alert-warning" id="toggle-table-method-error">Invalid request method.</div>';
    exit;
}

if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
    http_response_code(403);
    echo '<div class="alert alert-danger" id="toggle-table-csrf-error">Invalid security token. Please refresh and try again.</div>';
    exit;
}

$restaurantId = currentRestaurantId();
$tableId = (int)($_POST['table_id'] ?? 0);

if (!$restaurantId || !$tableId) {
    http_response_code(400);
    echo '<div class="alert alert-danger" id="toggle-table-invalid">Invalid request.</div>';
    exit;
}

require_once '../../../helpers/services/tables.php';

try {
    $table = svc_table_set_active($restaurantId, $tableId);
} catch (ServiceError $e) {
    echo '<div class="alert alert-danger" id="toggle-table-notfound">Table not found.</div>';
    exit;
}

$statusLabel = $table['is_active'] ? 'activated' : 'deactivated';

header('HX-Trigger: refreshTableList');

echo '<div class="alert alert-success alert-dismissible fade show" id="toggle-table-success">
        <i class="feather-check-circle me-1"></i> Table ' . $statusLabel . ' successfully.
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
      </div>';
