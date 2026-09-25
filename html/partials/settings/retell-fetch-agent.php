<?php
/**
 * Retell API: Fetch current agent + LLM config from Retell.
 * Returns JSON with all fields so the edit form can be populated.
 * Also updates the local database record with the fetched values.
 */
require_once __DIR__ . '/../../../helpers/auth.php';
require_once __DIR__ . '/../../../helpers/csrf.php';
require_once __DIR__ . '/../../../helpers/retell-api.php';

requireAuth();
if (!isAdmin() && !isSuperAdmin()) {
    http_response_code(403);
    echo json_encode(['error' => 'Forbidden']);
    exit;
}

header('Content-Type: application/json');

$restaurantId = currentRestaurantId();
$promptId = (int)($_GET['id'] ?? 0);

if ($promptId <= 0) {
    echo json_encode(['error' => 'Missing prompt ID']);
    exit;
}

require_once __DIR__ . '/../../../helpers/services/ai_agents.php';

try {
    echo json_encode(['success' => true, 'data' => svc_voice_agent_fetch($restaurantId, $promptId)]);
} catch (ServiceError $e) {
    echo json_encode(['error' => $e->errorCode === 'NOT_FOUND' ? 'Agent not found' : $e->getMessage()]);
}
