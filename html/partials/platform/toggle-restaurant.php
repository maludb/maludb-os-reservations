<?php
require_once '../../../helpers/auth.php';
require_once '../../../helpers/csrf.php';

requireSuperAdmin();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo '<div class="alert alert-warning" id="platform-toggle-method-error">Invalid request method.</div>';
    exit;
}

if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
    http_response_code(403);
    echo '<div class="alert alert-danger" id="platform-toggle-csrf-error">Invalid security token. Please refresh and try again.</div>';
    exit;
}

require_once '../../../helpers/services/platform.php';

$restaurantId = (int)($_POST['restaurant_id'] ?? 0);
if ($restaurantId <= 0) {
    echo '<div class="alert alert-danger" id="platform-toggle-id-error">Invalid restaurant ID.</div>';
    exit;
}

try {
    $restaurant = svc_platform_restaurant_get($restaurantId);
    svc_platform_restaurant_set_active($restaurantId, !$restaurant['is_active'], currentUserId());
} catch (ServiceError $e) {
    echo '<div class="alert alert-danger" id="platform-toggle-notfound-error">Restaurant not found.</div>';
    exit;
}

$source = $_POST['source'] ?? '';
if ($source === 'dashboard') {
    $_GET['id'] = $restaurantId;
    include __DIR__ . '/restaurant-dashboard.php';
} else {
    include __DIR__ . '/restaurants.php';
}
