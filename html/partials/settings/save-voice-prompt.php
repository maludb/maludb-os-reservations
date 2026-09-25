<?php
/**
 * Save (create or update) a voice agent prompt locally.
 * Does NOT call the Retell API — use the individual action buttons for that.
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

require_once __DIR__ . '/../../../helpers/services/ai_agents.php';

// The form always sends every field; an unchecked checkbox means "not primary"
$input = array_intersect_key($_POST, array_flip(AI_VOICE_FIELDS));
$input['is_primary_language'] = isset($_POST['is_primary_language']);
$input['var_names'] = $_POST['var_names'] ?? [];
$input['var_values'] = $_POST['var_values'] ?? [];

try {
    svc_voice_agent_save($restaurantId, $id, $input);
    echo '<div class="alert alert-success">Voice agent saved. Use the Retell API action buttons on the card to push changes to Retell.</div>';
} catch (ServiceError $e) {
    echo '<div class="alert alert-danger">' . htmlspecialchars($e->getMessage()) . '</div>';
} catch (PDOException $e) {
    echo '<div class="alert alert-danger">Error saving: ' . htmlspecialchars($e->getMessage()) . '</div>';
}
