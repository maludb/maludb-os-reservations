<?php
require_once '../../../helpers/auth.php';
require_once '../../../helpers/csrf.php';

requireSuperAdmin();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo '<div class="alert alert-warning" id="platform-save-product-method-error">Invalid request method.</div>';
    exit;
}

if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
    http_response_code(403);
    echo '<div class="alert alert-danger" id="platform-save-product-csrf-error">Invalid security token. Please refresh and try again.</div>';
    exit;
}

require_once '../../../helpers/services/platform.php';

$id = (int)($_POST['id'] ?? 0);
$input = $_POST;
$input['is_active'] = isset($_POST['is_active']);

try {
    svc_platform_product_save($id, $input, currentUserId());
} catch (ServiceError $e) {
    // Keep the per-error alert ids the page used before
    $suffix = $e->status === 404 ? 'notfound' : ($e->field ?: 'validation');
    echo '<div class="alert alert-danger" id="platform-save-product-' . htmlspecialchars($suffix) . '-error">' . htmlspecialchars($e->getMessage()) . '</div>';
    exit;
}

header('HX-Trigger: closeModal');
include __DIR__ . '/products.php';
