<?php
/**
 * POST handler: Update meal status or complete & open table
 */
require_once '../../../helpers/auth.php';
require_once '../../../helpers/csrf.php';
require_once '../../../helpers/meal-status.php';

requireAuth();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    exit;
}

if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
    http_response_code(403);
    echo '<div class="alert alert-danger">Invalid security token.</div>';
    exit;
}

$restaurantId = currentRestaurantId();
$resId = (int)($_POST['reservation_id'] ?? 0);
$tableId = (int)($_POST['table_id'] ?? 0);
$action = trim($_POST['action'] ?? '');
$newMealStatus = trim($_POST['meal_status'] ?? '');

if (!$resId || !$tableId) {
    echo '<div class="alert alert-danger">Invalid request.</div>';
    exit;
}

require_once __DIR__ . '/../../../helpers/services/tables.php';

try {
    if ($action === 'complete') {
        svc_table_complete($restaurantId, currentUserId(), $resId);
        // Reload floor plan
        include __DIR__ . '/floor-plan.php';
        exit;
    }
    svc_table_set_meal_status($restaurantId, $resId, $newMealStatus);
} catch (ServiceError $e) {
    echo '<div class="alert alert-danger" id="meal-status-error">' . htmlspecialchars($e->getMessage()) . '</div>';
    exit;
} catch (Throwable $e) {
    echo '<div class="alert alert-danger" id="meal-status-error">An error occurred.</div>';
    exit;
}

// Re-render the modal with updated status
$_GET['table_id'] = $tableId;
include __DIR__ . '/table-status.php';
