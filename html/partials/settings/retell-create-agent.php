<?php
/**
 * Retell API: Create a new agent.
 * 1. Creates a minimal LLM in Retell
 * 2. Creates an Agent in Retell linked to that LLM
 * 3. Creates the local restaurant_prompts record with the returned IDs
 * (logic in helpers/services/ai_agents.php svc_voice_agent_create)
 */
require_once __DIR__ . '/../../../helpers/auth.php';
require_once __DIR__ . '/../../../helpers/csrf.php';

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

require_once __DIR__ . '/../../../helpers/services/ai_agents.php';

try {
    $created = svc_voice_agent_create($restaurantId, $_POST['agent_name'] ?? '');
} catch (ServiceError $e) {
    echo '<div class="alert alert-' . ($e->errorCode === 'SERVER_ERROR' ? 'warning' : 'danger') . '">' . htmlspecialchars($e->getMessage()) . '</div>';
    exit;
}

$agentId = $created['agent']['agent_id'];
$llmId = $created['agent']['retell_llm_id'];
$postMessages = $created['messages'];
$postDetail = !empty($postMessages) ? '<br><small>' . htmlspecialchars(implode(' ', $postMessages)) . '</small>' : '';
echo '<div class="alert alert-success">Agent created.<br>'
    . 'Agent ID: <code>' . htmlspecialchars($agentId) . '</code><br>'
    . 'LLM ID: <code>' . htmlspecialchars($llmId) . '</code>'
    . $postDetail . '</div>';
