<?php
require_once '../../../helpers/auth.php';
require_once '../../../helpers/csrf.php';

requireSuperAdmin();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo '<div class="alert alert-warning" id="platform-save-method-error">Invalid request method.</div>';
    exit;
}

if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
    http_response_code(403);
    echo '<div class="alert alert-danger" id="platform-save-csrf-error">Invalid security token. Please refresh and try again.</div>';
    exit;
}

require_once '../../../helpers/services/platform.php';

$id = (int)($_POST['id'] ?? 0);

try {
    $restaurant = svc_platform_restaurant_save($id, $_POST, currentUserId());
} catch (ServiceError $e) {
    // Keep the per-error alert ids the page used before
    $suffix = $e->status === 404 ? 'notfound' : ($e->field ?: 'validation');
    echo '<div class="alert alert-danger" id="platform-save-' . htmlspecialchars($suffix) . '-error">' . htmlspecialchars($e->getMessage()) . '</div>';
    exit;
} catch (Throwable $e) {
    echo '<div class="alert alert-danger" id="platform-save-error">Error saving restaurant: ' . htmlspecialchars($e->getMessage()) . '</div>';
    exit;
}

header('HX-Trigger: closeModal');

if ($id > 0) {
    // Update session if editing current restaurant
    if ($id === (int)($_SESSION['current_restaurant_id'] ?? 0)) {
        $_SESSION['current_restaurant_name'] = $restaurant['name'];
    }
    // Reload the restaurant dashboard
    $_GET['id'] = $id;
    include __DIR__ . '/restaurant-dashboard.php';
    exit;
}

// Reload the restaurants list
include __DIR__ . '/restaurants.php';
