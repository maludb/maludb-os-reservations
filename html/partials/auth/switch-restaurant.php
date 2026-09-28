<?php
require_once '../../../helpers/auth.php';
require_once '../../../helpers/csrf.php';

init_session();
requireAuth();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
        http_response_code(403);
        echo '<div class="alert alert-danger" id="switch-csrf-error">Invalid security token. Please refresh and try again.</div>';
        exit;
    }
    $restaurantId = (int)($_POST['restaurant_id'] ?? 0);

    if ($restaurantId && switchRestaurant($restaurantId)) {
        // Full page reload so sidenav re-renders with the new restaurant's permissions
        header('HX-Redirect: /app.php');
        exit;
    } else {
        http_response_code(403);
        echo '<div class="alert alert-danger" id="switch-error">You do not have access to that restaurant.</div>';
    }
    exit;
}

http_response_code(405);
echo '<div class="alert alert-warning" id="switch-method-error">Invalid request.</div>';
