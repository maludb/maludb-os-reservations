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
    echo '<div class="alert alert-danger">Invalid security token.</div>';
    exit;
}

require_once '../../../helpers/services/platform.php';

$input = $_POST;
$input['is_active'] = isset($_POST['is_active']);

try {
    svc_platform_nav_permission_save((int)($_POST['id'] ?? 0), $input, currentUserId());
} catch (ServiceError $e) {
    echo '<div class="alert alert-danger" id="platform-nav-permission-save-error">' . htmlspecialchars($e->getMessage()) . '</div>';
    exit;
}

header('HX-Trigger: closeModal');
include __DIR__ . '/nav-permissions.php';
