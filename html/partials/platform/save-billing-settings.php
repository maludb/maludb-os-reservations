<?php
/**
 * Platform Admin — Save Billing Settings
 *
 * Saves billing configuration for a restaurant.
 */
require_once __DIR__ . '/../../../helpers/auth.php';
require_once __DIR__ . '/../../../helpers/db.php';
require_once __DIR__ . '/../../../helpers/csrf.php';

requireSuperAdmin();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    exit;
}

if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
    http_response_code(403);
    echo '<div class="alert alert-danger m-4">Invalid security token. Please refresh and try again.</div>';
    exit;
}

require_once '../../../helpers/services/platform.php';

try {
    svc_platform_billing_settings_save((int)($_POST['restaurant_id'] ?? 0), $_POST, currentUserId());
} catch (ServiceError $e) {
    echo '<div class="alert alert-danger m-4" id="platform-billing-save-error">' . htmlspecialchars($e->getMessage()) . '</div>';
    exit;
} catch (Throwable $e) {
    echo '<div class="alert alert-danger m-4" id="platform-billing-save-error">An error occurred. Please try again.</div>';
    exit;
}

// Reload the billing settings list
include __DIR__ . '/billing-settings.php';
