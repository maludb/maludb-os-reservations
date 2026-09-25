<?php
require_once '../../../helpers/auth.php';
require_once '../../../helpers/csrf.php';

requireSuperAdmin();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    exit;
}

if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
    http_response_code(403);
    echo '<div class="alert alert-danger" id="platform-toggle-product-csrf-error">Invalid security token.</div>';
    exit;
}

require_once '../../../helpers/services/platform.php';

$productId = (int)($_POST['product_id'] ?? 0);
if ($productId <= 0) {
    echo '<div class="alert alert-danger" id="platform-toggle-product-id-error">Invalid product.</div>';
    exit;
}

try {
    svc_platform_product_toggle($productId, currentUserId());
} catch (ServiceError $e) {
    echo '<div class="alert alert-danger" id="platform-toggle-product-notfound-error">Product not found.</div>';
    exit;
}

include __DIR__ . '/products.php';
