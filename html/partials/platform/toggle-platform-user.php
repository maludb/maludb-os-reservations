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

$userId = (int)($_POST['user_id'] ?? 0);
if ($userId <= 0) {
    echo '<div class="alert alert-danger">Invalid user.</div>';
    exit;
}

try {
    svc_platform_user_toggle($userId, currentUserId());
} catch (ServiceError $e) {
    echo '<div class="alert alert-danger" id="platform-user-toggle-error">' . htmlspecialchars($e->getMessage()) . '</div>';
    exit;
}

include __DIR__ . '/users.php';
