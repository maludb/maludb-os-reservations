<?php
/**
 * Retell API: Update the agent prompt and begin message on the Retell LLM.
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
    echo '<div class="alert alert-danger">Invalid CSRF token.</div>';
    exit;
}

$restaurantId = currentRestaurantId();
$promptId = (int)($_POST['id'] ?? 0);

if ($promptId <= 0) {
    echo '<div class="alert alert-danger">No agent selected.</div>';
    exit;
}

require_once __DIR__ . '/../../../helpers/services/ai_agents.php';

try {
    svc_voice_agent_update_prompt($restaurantId, $promptId, $_POST['agent_prompt'] ?? '', $_POST['begin_message'] ?? '');
    echo '<div class="alert alert-success">Prompt updated in Retell.</div>';
} catch (ServiceError $e) {
    echo '<div class="alert alert-danger">' . htmlspecialchars($e->errorCode === 'NOT_FOUND' ? 'Agent not found.' : $e->getMessage()) . '</div>';
}
