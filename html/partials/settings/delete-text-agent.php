<?php
/**
 * Delete a text agent prompt.
 */
require_once __DIR__ . '/../../../helpers/auth.php';
require_once __DIR__ . '/../../../helpers/csrf.php';

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
$id = (int)($_POST['id'] ?? 0);

if ($id <= 0) {
    echo '<div class="alert alert-danger">Invalid text agent ID.</div>';
    exit;
}

require_once __DIR__ . '/../../../helpers/services/ai_agents.php';

try {
    svc_msg_agent_delete('text', $restaurantId, $id);
    echo '<div class="alert alert-success">Text agent deleted.</div>';
} catch (ServiceError $e) {
    echo '<div class="alert alert-warning">Text agent not found.</div>';
}
