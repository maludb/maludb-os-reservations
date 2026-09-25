<?php
/**
 * Save (create or update) an email agent prompt.
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

// The form always sends every field
$input = array_intersect_key($_POST, array_flip(AI_MSG_AGENTS['email']['fields']));
foreach (AI_MSG_AGENTS['email']['fields'] as $f) $input[$f] = $input[$f] ?? '';
$input['var_names'] = $_POST['var_names'] ?? [];
$input['var_values'] = $_POST['var_values'] ?? [];

try {
    svc_msg_agent_save('email', $restaurantId, $id, $input);
    echo '<div class="alert alert-success">Email agent ' . ($id > 0 ? 'updated' : 'created') . '.</div>';
} catch (ServiceError $e) {
    echo '<div class="alert alert-danger">' . htmlspecialchars($e->getMessage()) . '</div>';
} catch (PDOException $e) {
    echo '<div class="alert alert-danger">Error saving email agent: ' . htmlspecialchars($e->getMessage()) . '</div>';
}
