<?php
require_once '../../../helpers/auth.php';
require_once '../../../helpers/csrf.php';

requireAuth();
requireManager();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo '<div class="alert alert-warning" id="save-table-method-error">Invalid request method.</div>';
    exit;
}

if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
    http_response_code(403);
    echo '<div class="alert alert-danger" id="save-table-csrf-error">Invalid security token. Please refresh and try again.</div>';
    exit;
}

$restaurantId = currentRestaurantId();
if (!$restaurantId) {
    http_response_code(400);
    echo '<div class="alert alert-danger" id="save-table-no-restaurant">No restaurant selected.</div>';
    exit;
}

$tableId = (int)($_POST['table_id'] ?? 0);
$isEdit = $tableId > 0;

require_once '../../../helpers/services/tables.php';

try {
    svc_table_save($restaurantId, $tableId, ['is_active' => isset($_POST['is_active'])] + $_POST);
} catch (ServiceError $e) {
    // Keep the per-field alert ids the page used before
    $id = $e->status === 404 ? 'save-table-notfound' : 'save-table-' . ($e->field ?: 'validation') . '-error';
    if ($e->field === 'section-invalid') $id = 'save-table-section-invalid';
    echo '<div class="alert alert-danger" id="' . $id . '">' . htmlspecialchars($e->getMessage()) . '</div>';
    exit;
} catch (Throwable $e) {
    echo '<div class="alert alert-danger" id="save-table-error">An error occurred while saving. Please try again.</div>';
    exit;
}

header('HX-Trigger: refreshTableList');

echo '<div class="alert alert-success alert-dismissible fade show" id="save-table-success">
        <i class="feather-check-circle me-1"></i> Table ' . ($isEdit ? 'updated' : 'added') . ' successfully.
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
      </div>';
echo '<script>document.getElementById("tables-modal-container").innerHTML="";</script>';
