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
$input['enabled'] = isset($_POST['enabled']);

try {
    svc_platform_account_option_save((int)($_POST['id'] ?? 0), $input, currentUserId());
} catch (ServiceError $e) {
    echo '<div class="alert alert-danger" id="platform-account-option-save-error">' . htmlspecialchars($e->getMessage()) . '</div>';
    exit;
}

header('HX-Trigger: closeModal');
include __DIR__ . '/account-options.php';
