<?php
require_once __DIR__ . '/../../../helpers/auth.php';
require_once __DIR__ . '/../../../helpers/csrf.php';

requireAuth();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    exit('Method not allowed');
}

if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
    http_response_code(403);
    exit('<div class="alert alert-danger">Invalid CSRF token. Please refresh and try again.</div>');
}

$restaurantId = currentRestaurantId();
$userId = $_SESSION['user_id'] ?? 0;

if (!$restaurantId) {
    echo '<div class="alert alert-danger">No account selected.</div>';
    exit;
}

$id = (int)($_POST['id'] ?? 0);

require_once __DIR__ . '/../../../helpers/services/todos.php';
try {
    svc_todo_save($restaurantId, (int)$userId, $id, $_POST);
} catch (ServiceError $e) {
    echo '<div class="alert alert-danger" id="todo-save-error">' . htmlspecialchars($e->getMessage()) . '</div>';
    exit;
}

// Return the updated list
header('HX-Trigger: todoSaved');
require __DIR__ . '/list.php';
