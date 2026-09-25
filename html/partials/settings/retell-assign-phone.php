<?php
/**
 * Retell API: Assign a phone number to an agent.
 * Updates the phone number in Retell to set inbound_agents and outbound_agents.
 * Also updates the local restaurant_prompts record.
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
$phoneNumber = trim($_POST['phone_number'] ?? '');

if ($promptId <= 0 || $phoneNumber === '') {
    echo '<div class="alert alert-danger">Missing prompt ID or phone number.</div>';
    exit;
}

require_once __DIR__ . '/../../../helpers/services/ai_agents.php';

try {
    $agent = svc_voice_agent_assign_phone($restaurantId, $promptId, $phoneNumber);
    echo '<div class="alert alert-success">Phone number <code>' . htmlspecialchars($phoneNumber) . '</code> assigned to agent <code>' . htmlspecialchars($agent['agent_id']) . '</code> with inbound webhook.</div>';
} catch (ServiceError $e) {
    echo '<div class="alert alert-danger">' . htmlspecialchars($e->errorCode === 'NOT_FOUND' ? 'Agent not found.' : $e->getMessage()) . '</div>';
}
