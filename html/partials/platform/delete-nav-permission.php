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

$permissionId = (int)($_POST['permission_id'] ?? 0);
if ($permissionId <= 0) {
    echo '<div class="alert alert-danger">Invalid permission.</div>';
    exit;
}

try {
    svc_platform_nav_permission_delete($permissionId, currentUserId());
} catch (ServiceError $e) {
    // Already gone — just show the list
}

include __DIR__ . '/nav-permissions.php';
