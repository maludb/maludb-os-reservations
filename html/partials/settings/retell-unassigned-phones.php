<?php
/**
 * Retell API: Fetch phone numbers not assigned to any agent.
 * Returns JSON array for use in a dropdown.
 */
require_once __DIR__ . '/../../../helpers/auth.php';
require_once __DIR__ . '/../../../helpers/retell-api.php';

requireAuth();
if (!isAdmin() && !isSuperAdmin()) {
    http_response_code(403);
    echo json_encode(['error' => 'Forbidden']);
    exit;
}

header('Content-Type: application/json');

require_once __DIR__ . '/../../../helpers/services/ai_agents.php';

try {
    echo json_encode(svc_retell_unassigned_phones(currentRestaurantId()));
} catch (ServiceError $e) {
    echo json_encode(['error' => $e->getMessage()]);
}
