<?php
/**
 * Sync a voice agent prompt to Retell API.
 * Creates or updates the Retell LLM + Agent from stored config.
 */
require_once __DIR__ . '/../../../helpers/auth.php';
require_once __DIR__ . '/../../../helpers/csrf.php';
require_once __DIR__ . '/../../../helpers/retell-api.php';

requireAuth();
if (!isAdmin() && !isSuperAdmin()) {
    http_response_code(403);
    exit('Forbidden');
}
if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
    http_response_code(403);
    echo '<div class="alert alert-danger">Invalid CSRF token.</div>';
    exit;
}

$restaurantId = currentRestaurantId();
$promptId = (int)($_POST['id'] ?? 0);

if ($promptId <= 0) {
    echo '<div class="alert alert-danger">Invalid prompt ID.</div>';
    exit;
}

require_once __DIR__ . '/../../../helpers/services/ai_agents.php';

try {
    $result = svc_voice_agent_sync($restaurantId, $promptId);
} catch (ServiceError $e) {
    echo '<div class="alert alert-danger">' . htmlspecialchars($e->getMessage()) . '</div>';
    exit;
}

$detail = implode(' ', $result['messages']);
if ($result['success']) {
    echo '<div class="alert alert-success">Synced to Retell successfully. ' . htmlspecialchars($detail) . '</div>';
} else {
    echo '<div class="alert alert-warning">Sync had issues: ' . htmlspecialchars($detail) . '</div>';
}
