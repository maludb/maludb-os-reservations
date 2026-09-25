<?php
/**
 * Fetch active prompt templates for dropdown.
 * Returns JSON array.
 */
require_once __DIR__ . '/../../../helpers/auth.php';

requireAuth();
header('Content-Type: application/json');

require_once __DIR__ . '/../../../helpers/services/ai_agents.php';

echo json_encode(svc_prompt_templates());
