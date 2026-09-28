<?php
/**
 * AI agent service — voice agents (Retell), text (SMS) agents and email agents.
 * Used by the html/partials/settings/ voice/text/email agent and retell-* handlers
 * and the API (helpers/api/ops/ai_agents.php).
 *
 * Retell calls go to the external Retell API; failures throw ServiceError
 * with code RETELL_ERROR (HTTP 502).
 */

require_once __DIR__ . '/_base.php';
require_once __DIR__ . '/../retell-api.php';
require_once __DIR__ . '/../../config/app.php';


/** Fields of a voice agent that can be edited locally. */
const AI_VOICE_FIELDS = [
    'phone_number', 'voice_agent_greeting', 'description', 'language_code', 'language_name',
    'is_primary_language', 'model', 'voice_id', 'voice_model', 'agent_prompt', 'begin_message',
    'transfer_number', 'agent_swap_id', 'webhook_url', 'inbound_webhook_url', 'mcp_url', 'mcp_headers',
];

/** Text and email agents share one shape; this maps each kind to its tables and fields. */
const AI_MSG_AGENTS = [
    'text' => [
        'label' => 'Text agent', 'table' => 'text_agent_prompts', 'details' => 'text_agent_prompt_details',
        'fk' => 'text_agent_prompt_id', 'fields' => ['phone_number', 'description', 'system_prompt', 'custom_webhook'],
        'key' => 'phone_number', 'key_error' => 'Phone number is required.',
    ],
    'email' => [
        'label' => 'Email agent', 'table' => 'email_agent_prompts', 'details' => 'email_agent_prompt_details',
        'fk' => 'email_agent_prompt_id', 'fields' => ['agent_id', 'email_address', 'email_agent_greeting', 'description'],
        'key' => 'agent_id', 'key_error' => 'Agent ID is required.',
    ],
];

function svc_ai_retell_fail(string $message): never
{
    throw new ServiceError($message, 'RETELL_ERROR', 502);
}

/** Variables list from [{name, value}] or the form's parallel var_names/var_values arrays. */
function svc_ai_variables(array $in): array
{
    if (isset($in['variables']) && is_array($in['variables'])) {
        $vars = $in['variables'];
    } else {
        $vars = [];
        foreach (($in['var_names'] ?? []) as $i => $name) {
            $vars[] = ['name' => $name, 'value' => $in['var_values'][$i] ?? ''];
        }
    }
    $clean = [];
    foreach ($vars as $v) {
        $name = trim((string)($v['name'] ?? ''));
        if ($name !== '') $clean[] = ['name' => $name, 'value' => trim((string)($v['value'] ?? ''))];
    }
    return $clean;
}

/** Replace the variable rows of a prompt. */
function svc_ai_save_variables(string $table, string $fk, int $promptId, array $vars): void
{
    db()->prepare("DELETE FROM {$table} WHERE {$fk} = ?")->execute([$promptId]);
    $ins = db()->prepare("INSERT INTO {$table} ({$fk}, variable_name, variable_value, is_active) VALUES (?, ?, ?, 1)");
    foreach ($vars as $v) $ins->execute([$promptId, $v['name'], $v['value']]);
}

function svc_ai_load_variables(string $table, string $fk, int $promptId): array
{
    $stmt = db()->prepare("SELECT variable_name AS name, variable_value AS value, is_active FROM {$table} WHERE {$fk} = ? ORDER BY id");
    $stmt->execute([$promptId]);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

/* ---------- Voice agents ---------- */

function svc_voice_agent_get(int $rid, int $id): array
{
    $row = svc_row('restaurant_prompts', $id, $rid, 'Voice agent');
    $row['variables'] = svc_ai_load_variables('restaurant_prompt_details', 'restaurant_prompt_id', $id);
    return $row;
}

function svc_voice_agent_list(int $rid): array
{
    $stmt = db()->prepare("SELECT id FROM restaurant_prompts WHERE restaurant_id = ? ORDER BY created_at ASC");
    $stmt->execute([$rid]);
    return array_map(fn($id) => svc_voice_agent_get($rid, (int)$id), $stmt->fetchAll(PDO::FETCH_COLUMN));
}

/**
 * Save a voice agent's local configuration (Retell is not called; use sync).
 * Only keys present in $in are changed; 'variables' replaces the variable list when present.
 * New agents are made with svc_voice_agent_create.
 */
function svc_voice_agent_save(int $rid, int $id, array $in): array
{
    if ($id <= 0) svc_fail('Use the "Create Agent" button to create a new agent.');
    $current = svc_row('restaurant_prompts', $id, $rid, 'Voice agent');
    $fields = [];
    foreach (AI_VOICE_FIELDS as $f) {
        if (!array_key_exists($f, $in)) continue;
        $v = $in[$f];
        if ($f === 'is_primary_language') {
            $fields[$f] = $v ? 1 : 0;
        } elseif (in_array($f, ['language_code', 'language_name', 'model'], true)) {
            $fields[$f] = trim((string)$v) ?: ['language_code' => 'en', 'language_name' => 'English', 'model' => 'gpt-4.1'][$f];
        } else {
            $fields[$f] = trim((string)$v) ?: null;
        }
    }

    $pdo = db();
    $pdo->beginTransaction();
    try {
        if (!empty($fields['is_primary_language'])) {
            $pdo->prepare("UPDATE restaurant_prompts SET is_primary_language = 0 WHERE restaurant_id = ? AND id != ?")->execute([$rid, $id]);
        }
        if ($fields) {
            $sets = implode(', ', array_map(fn($f) => "{$f} = ?", array_keys($fields)));
            $pdo->prepare("UPDATE restaurant_prompts SET {$sets} WHERE id = ? AND restaurant_id = ?")
                ->execute([...array_values($fields), $id, $rid]);
        }
        if (array_key_exists('variables', $in) || array_key_exists('var_names', $in)) {
            svc_ai_save_variables('restaurant_prompt_details', 'restaurant_prompt_id', $id, svc_ai_variables($in));
        }
        $pdo->commit();
    } catch (Throwable $e) {
        $pdo->rollBack();
        throw $e;
    }
    return svc_voice_agent_get($rid, $id);
}

/** Delete the local voice agent record (the Retell agent itself is left alone). */
function svc_voice_agent_delete(int $rid, int $id): void
{
    svc_row('restaurant_prompts', $id, $rid, 'Voice agent');
    db()->prepare("DELETE FROM restaurant_prompt_details WHERE restaurant_prompt_id = ?")->execute([$id]);
    db()->prepare("DELETE FROM restaurant_prompts WHERE id = ? AND restaurant_id = ?")->execute([$id, $rid]);
}

/**
 * Push the stored configuration to Retell (create or update the LLM and agent).
 * Returns ['success' => bool, 'messages' => [...], 'agent' => row].
 */
function svc_voice_agent_sync(int $rid, int $id): array
{
    retellUseRestaurant($rid);
    $prompt = svc_row('restaurant_prompts', $id, $rid, 'Voice agent');
    if (empty($prompt['agent_prompt'])) svc_fail('Cannot sync — agent prompt is empty. Edit the agent first.', 'agent_prompt');

    $stmt = db()->prepare("SELECT * FROM restaurant_prompt_details WHERE restaurant_prompt_id = ? AND is_active = 1");
    $stmt->execute([$id]);
    $details = $stmt->fetchAll();

    $messages = [];
    $success = true;

    $llmPayload = retellBuildLlmPayload($prompt, $details);
    $llmId = $prompt['retell_llm_id'] ?? '';
    if ($llmId !== '' && $llmId !== null) {
        $r = retellUpdateLlm($llmId, $llmPayload);
        if ($r['success']) {
            $messages[] = 'LLM updated.';
        } else {
            $success = false;
            $messages[] = 'LLM update failed: ' . $r['error'];
        }
    } else {
        $r = retellCreateLlm($llmPayload);
        if ($r['success'] && !empty($r['data']['llm_id'])) {
            $llmId = $r['data']['llm_id'];
            db()->prepare("UPDATE restaurant_prompts SET retell_llm_id = ? WHERE id = ?")->execute([$llmId, $id]);
            $messages[] = 'LLM created.';
        } else {
            $success = false;
            $messages[] = 'LLM creation failed: ' . ($r['error'] ?? 'Unknown error');
        }
    }

    if ($llmId !== '' && $llmId !== null) {
        $agentPayload = retellBuildAgentPayload($prompt, $llmId);
        $agentId = $prompt['agent_id'] ?? '';
        if ($agentId !== '' && $agentId !== null) {
            $r = retellUpdateAgent($agentId, $agentPayload);
            if ($r['success']) {
                $messages[] = 'Agent updated.';
            } else {
                $success = false;
                $messages[] = 'Agent update failed: ' . $r['error'];
            }
        } else {
            $r = retellCreateAgent($agentPayload);
            if ($r['success'] && !empty($r['data']['agent_id'])) {
                db()->prepare("UPDATE restaurant_prompts SET agent_id = ? WHERE id = ?")->execute([$r['data']['agent_id'], $id]);
                $messages[] = 'Agent created (' . $r['data']['agent_id'] . ').';
            } else {
                $success = false;
                $messages[] = 'Agent creation failed: ' . ($r['error'] ?? 'Unknown error');
            }
        }
    }

    if ($success) db()->prepare("UPDATE restaurant_prompts SET is_synced = 1 WHERE id = ?")->execute([$id]);
    return ['success' => $success, 'messages' => $messages, 'agent' => svc_voice_agent_get($rid, $id)];
}

/**
 * Create a new Retell agent (LLM + agent + webhook + reservation MCP server and tools)
 * and the local record. Returns ['agent' => row, 'messages' => [...]].
 */
function svc_voice_agent_create(int $rid, string $agentName): array
{
    retellUseRestaurant($rid);
    $agentName = trim($agentName);
    if ($agentName === '') svc_fail('Agent name is required.', 'agent_name');
    $pdo = db();

    $logFile = __DIR__ . '/../../logs/retell-create-agent.log';
    $clog = function (string $msg) use ($logFile) {
        @file_put_contents($logFile, '[' . date('Y-m-d H:i:s') . '] ' . $msg . "\n", FILE_APPEND);
    };
    $clog('=== CREATE AGENT START === name: ' . $agentName);

    // Step 1: minimal LLM
    $llmResult = retellCreateLlm([
        'model' => 'gpt-4.1',
        'general_prompt' => 'You are a helpful voice assistant.',
        'start_speaker' => 'agent',
        'general_tools' => [[
            'type' => 'end_call',
            'name' => 'end_call',
            'description' => 'End the call when the conversation is complete or the caller says goodbye.',
        ]],
    ]);
    $clog('LLM result: ' . json_encode($llmResult));
    if (!$llmResult['success']) svc_ai_retell_fail('Failed to create Retell LLM: ' . $llmResult['error']);
    $llmId = $llmResult['data']['llm_id'] ?? '';
    if ($llmId === '') svc_ai_retell_fail('Retell returned success but no llm_id.');

    // Step 2: agent linked to the LLM
    $agentResult = retellCreateAgent([
        'response_engine' => ['type' => 'retell-llm', 'llm_id' => $llmId],
        'voice_id' => 'retell-Cimo',
        'agent_name' => $agentName,
        'language' => 'en-US',
    ]);
    $clog('Agent result: ' . json_encode($agentResult));
    if (!$agentResult['success']) {
        retellDeleteLlm($llmId);
        svc_ai_retell_fail('Failed to create Retell Agent: ' . $agentResult['error']);
    }
    $agentId = $agentResult['data']['agent_id'] ?? '';
    if ($agentId === '') {
        retellDeleteLlm($llmId);
        svc_ai_retell_fail('Retell returned success but no agent_id.');
    }

    // Step 3: webhook
    $messages = [];
    $webhookUrl = app_url() . '/api/retell/restaurant-webhook.php?id=' . $agentId;
    $r = retellUpdateAgent($agentId, [
        'webhook_url' => $webhookUrl,
        'webhook_events' => ['call_started', 'call_ended', 'call_analyzed'],
    ]);
    $messages[] = $r['success'] ? 'Webhook set.' : 'Webhook failed: ' . $r['error'];

    // Step 4: reservation MCP server — it only accepts the restaurant's own key; create one if missing
    $mcpUrl = app_url() . '/api/mcp/server.php';
    $stmt = $pdo->prepare("SELECT setting_value FROM settings WHERE restaurant_id = ? AND setting_key = 'mcp_api_key'");
    $stmt->execute([$rid]);
    $mcpApiKey = (string)($stmt->fetchColumn() ?: '');
    if ($mcpApiKey === '') {
        $mcpApiKey = bin2hex(random_bytes(24));
        $pdo->prepare("DELETE FROM settings WHERE restaurant_id = ? AND setting_key = 'mcp_api_key'")->execute([$rid]);
        $pdo->prepare("INSERT INTO settings (restaurant_id, setting_key, setting_value) VALUES (?, 'mcp_api_key', ?)")->execute([$rid, $mcpApiKey]);
    }
    $mcpResult = retellUpdateLlm($llmId, ['mcps' => [[
        'id' => 'restaurant_mcp', // Retell requires an id; step 5 looks the tools up by it
        'name' => 'restaurant_mcp',
        'url' => $mcpUrl,
        'headers' => ['Authorization' => "Bearer {$mcpApiKey}"],
    ]]]);
    $clog('MCP result: ' . json_encode($mcpResult));

    if ($mcpResult['success']) {
        $messages[] = 'MCP server set.';
        // Step 5: enable the reservation tools
        $toolsResult = retellApiCall('GET', '/get-mcp-tools/' . urlencode($llmId) . '?mcp_id=restaurant_mcp');
        $enabled = ['check_availability', 'make_reservation', 'lookup_reservation', 'confirm_reservation',
                    'cancel_reservation', 'modify_reservation', 'update_contact_preferences'];
        if ($toolsResult['success'] && is_array($toolsResult['data'])) {
            $mcpTools = [];
            foreach ($toolsResult['data'] as $tool) {
                $name = $tool['name'] ?? '';
                if (in_array($name, $enabled, true)) {
                    $mcpTools[] = [
                        'type' => 'mcp', 'mcp_id' => 'restaurant_mcp', 'name' => $name,
                        'description' => $tool['description'] ?? $name,
                        'input_schema' => $tool['inputSchema'] ?? $tool['input_schema'] ?? new \stdClass(),
                    ];
                }
            }
            if ($mcpTools) {
                $existing = retellGetLlm($llmId);
                $existingTools = $existing['success'] ? ($existing['data']['general_tools'] ?? []) : [];
                $r = retellUpdateLlm($llmId, ['general_tools' => array_merge($existingTools, $mcpTools)]);
                $messages[] = $r['success'] ? count($mcpTools) . ' MCP tools enabled.' : 'MCP tools enable failed: ' . $r['error'];
            } else {
                $messages[] = 'No matching MCP tools found to enable.';
            }
        } else {
            $messages[] = 'Could not fetch MCP tools: ' . ($toolsResult['error'] ?? 'unknown');
        }
    } else {
        $messages[] = 'MCP failed: ' . $mcpResult['error'];
    }

    // Step 6: local record
    try {
        $stmt = $pdo->prepare(
            "INSERT INTO restaurant_prompts (restaurant_id, agent_id, retell_llm_id, description, language_code, language_name, model, webhook_url, mcp_url)
             VALUES (?, ?, ?, ?, 'en', 'English', 'gpt-4.1', ?, ?) RETURNING id"
        );
        $stmt->execute([$rid, $agentId, $llmId, $agentName, $webhookUrl, $mcpUrl]);
        $id = (int)$stmt->fetchColumn();
    } catch (PDOException $e) {
        throw new ServiceError("Agent created in Retell but failed to save locally: {$e->getMessage()} "
            . "(Agent ID {$agentId}, LLM ID {$llmId})", 'SERVER_ERROR', 500);
    }
    return ['agent' => svc_voice_agent_get($rid, $id), 'messages' => $messages];
}

/**
 * Pull the agent and LLM configuration from Retell and store it locally.
 * Returns the fetched fields.
 */
function svc_voice_agent_fetch(int $rid, int $id): array
{
    retellUseRestaurant($rid);
    $prompt = svc_row('restaurant_prompts', $id, $rid, 'Voice agent');
    $agentId = $prompt['agent_id'] ?? '';
    $llmId = $prompt['retell_llm_id'] ?? '';
    if ($agentId === '') svc_fail('No Retell agent ID on this record');

    $agentResult = retellGetAgent($agentId);
    if (!$agentResult['success']) svc_ai_retell_fail('Failed to fetch agent: ' . $agentResult['error']);
    $agent = $agentResult['data'];

    $langMap = [
        'en-US' => 'en', 'es-ES' => 'es', 'fr-FR' => 'fr', 'pt-BR' => 'pt', 'de-DE' => 'de', 'it-IT' => 'it',
        'zh-CN' => 'zh', 'ja-JP' => 'ja', 'ko-KR' => 'ko', 'pl-PL' => 'pl', 'ro-RO' => 'ro', 'ar-SA' => 'ar', 'hi-IN' => 'hi',
    ];
    $fetched = [
        'agent_id' => $agent['agent_id'] ?? $agentId,
        'description' => $agent['agent_name'] ?? '',
        'voice_id' => $agent['voice_id'] ?? '',
        'voice_model' => $agent['voice_model'] ?? '',
        'language_code' => $langMap[$agent['language'] ?? ''] ?? 'en',
        'webhook_url' => $agent['webhook_url'] ?? '',
    ];
    $responseLlmId = $agent['response_engine']['llm_id'] ?? '';
    if ($llmId === '' && $responseLlmId !== '') $llmId = $responseLlmId;
    $fetched['retell_llm_id'] = $llmId;

    if ($llmId !== '') {
        $llmResult = retellGetLlm($llmId);
        if ($llmResult['success']) {
            $llm = $llmResult['data'];
            $fetched['model'] = $llm['model'] ?? 'gpt-4.1';
            $fetched['agent_prompt'] = $llm['general_prompt'] ?? '';
            $fetched['begin_message'] = $llm['begin_message'] ?? '';
            foreach ($llm['general_tools'] ?? [] as $tool) {
                if (($tool['type'] ?? '') === 'transfer_call') $fetched['transfer_number'] = $tool['transfer_destination']['number'] ?? '';
                if (($tool['type'] ?? '') === 'agent_swap') $fetched['agent_swap_id'] = $tool['agent_id'] ?? '';
            }
            $mcps = $llm['mcps'] ?? [];
            if (!empty($mcps[0])) {
                $fetched['mcp_url'] = $mcps[0]['url'] ?? '';
                $fetched['mcp_headers'] = !empty($mcps[0]['headers']) ? json_encode($mcps[0]['headers']) : '';
            }
            $fetched['dynamic_variables'] = $llm['default_dynamic_variables'] ?? [];
        }
    }

    $langNameMap = [
        'en' => 'English', 'es' => 'Spanish', 'fr' => 'French', 'pt' => 'Portuguese', 'de' => 'German', 'it' => 'Italian',
        'zh' => 'Chinese', 'ja' => 'Japanese', 'ko' => 'Korean', 'pl' => 'Polish', 'ro' => 'Romanian', 'ar' => 'Arabic', 'hi' => 'Hindi',
    ];
    db()->prepare(
        "UPDATE restaurant_prompts SET
            retell_llm_id = ?, description = ?, voice_id = ?, voice_model = ?,
            language_code = ?, language_name = ?, model = ?, agent_prompt = ?, begin_message = ?,
            transfer_number = ?, agent_swap_id = ?, webhook_url = ?, mcp_url = ?, mcp_headers = ?
         WHERE id = ? AND restaurant_id = ?"
    )->execute([
        $fetched['retell_llm_id'] ?: null, $fetched['description'] ?: null, $fetched['voice_id'] ?: null,
        $fetched['voice_model'] ?: null, $fetched['language_code'] ?: 'en', $langNameMap[$fetched['language_code']] ?? 'English',
        $fetched['model'] ?? 'gpt-4.1', $fetched['agent_prompt'] ?? null, $fetched['begin_message'] ?? null,
        $fetched['transfer_number'] ?? null, $fetched['agent_swap_id'] ?? null, $fetched['webhook_url'] ?? null,
        $fetched['mcp_url'] ?? null, $fetched['mcp_headers'] ?? null, $id, $rid,
    ]);
    return $fetched;
}

/** Update the prompt (and optional begin message) on the Retell LLM, then locally. */
function svc_voice_agent_update_prompt(int $rid, int $id, string $agentPrompt, string $beginMessage = ''): array
{
    retellUseRestaurant($rid);
    $agentPrompt = trim($agentPrompt);
    $beginMessage = trim($beginMessage);
    if ($agentPrompt === '') svc_fail('Agent prompt cannot be empty.', 'agent_prompt');
    $prompt = svc_row('restaurant_prompts', $id, $rid, 'Voice agent');
    $llmId = $prompt['retell_llm_id'] ?? '';
    if ($llmId === '') svc_fail('No Retell LLM ID. Create the agent first.');

    $payload = ['general_prompt' => $agentPrompt];
    if ($beginMessage !== '') $payload['begin_message'] = $beginMessage;
    $result = retellUpdateLlm($llmId, $payload);
    if (!$result['success']) svc_ai_retell_fail('Failed to update prompt in Retell: ' . $result['error']);

    db()->prepare("UPDATE restaurant_prompts SET agent_prompt = ?, begin_message = ? WHERE id = ?")
        ->execute([$agentPrompt, $beginMessage ?: null, $id]);
    return svc_voice_agent_get($rid, $id);
}

/** Route inbound calls on a Retell phone number to this agent. */
function svc_voice_agent_assign_phone(int $rid, int $id, string $phoneNumber): array
{
    retellUseRestaurant($rid);
    $phoneNumber = trim($phoneNumber);
    if ($phoneNumber === '') svc_fail('Missing prompt ID or phone number.', 'phone_number');
    $prompt = svc_row('restaurant_prompts', $id, $rid, 'Voice agent');
    $agentId = $prompt['agent_id'] ?? '';
    if ($agentId === '') svc_fail('This agent has no Retell agent ID. Create the agent first.');

    $webhookUrl = $prompt['webhook_url'] ?: app_url() . '/api/retell/restaurant-webhook.php?id=' . $agentId;
    $result = retellUpdatePhoneNumber($phoneNumber, [
        'inbound_agents' => [['agent_id' => $agentId, 'weight' => 1]],
        'inbound_webhook_url' => $webhookUrl,
    ]);
    if (!$result['success']) svc_ai_retell_fail('Failed to assign phone number in Retell: ' . $result['error']);

    db()->prepare("UPDATE restaurant_prompts SET phone_number = ?, inbound_webhook_url = ? WHERE id = ?")
        ->execute([$phoneNumber, $webhookUrl, $id]);
    return svc_voice_agent_get($rid, $id);
}

/** Retell phone numbers with no inbound or outbound agent. */
function svc_retell_unassigned_phones(int $rid): array
{
    retellUseRestaurant($rid);
    $result = retellListPhoneNumbers();
    if (!$result['success']) svc_ai_retell_fail($result['error'] ?? 'Failed to fetch phone numbers');
    $unassigned = [];
    foreach ($result['data'] ?? [] as $phone) {
        $inbound = $phone['inbound_agents'] ?? $phone['inbound_agent'] ?? [];
        $outbound = $phone['outbound_agents'] ?? $phone['outbound_agent'] ?? [];
        if (is_string($inbound)) $inbound = $inbound !== '' ? [$inbound] : [];
        if (is_string($outbound)) $outbound = $outbound !== '' ? [$outbound] : [];
        if (empty($inbound) && empty($outbound)) {
            $unassigned[] = ['phone_number' => $phone['phone_number'] ?? '', 'nickname' => $phone['nickname'] ?? ''];
        }
    }
    return $unassigned;
}

/** Active prompt templates for new voice agents. */
function svc_prompt_templates(): array
{
    return db()->query(
        "SELECT id, name, description, agent_prompt, begin_message FROM prompt_templates WHERE is_active = 1 ORDER BY sort_order ASC, name ASC"
    )->fetchAll(PDO::FETCH_ASSOC);
}

/* ---------- Text and email agents ($kind = 'text' | 'email') ---------- */

function svc_msg_agent_get(string $kind, int $rid, int $id): array
{
    $c = AI_MSG_AGENTS[$kind];
    $row = svc_row($c['table'], $id, $rid, $c['label']);
    $row['variables'] = svc_ai_load_variables($c['details'], $c['fk'], $id);
    return $row;
}

function svc_msg_agent_list(string $kind, int $rid): array
{
    $c = AI_MSG_AGENTS[$kind];
    $stmt = db()->prepare("SELECT id FROM {$c['table']} WHERE restaurant_id = ? ORDER BY created_at ASC");
    $stmt->execute([$rid]);
    return array_map(fn($id) => svc_msg_agent_get($kind, $rid, (int)$id), $stmt->fetchAll(PDO::FETCH_COLUMN));
}

/**
 * Create ($id = 0) or update a text/email agent. Fields not in $in keep their value
 * on update; 'variables' ([{name, value}] or var_names/var_values) replaces the list when present.
 * A text agent's agent_id is its phone number.
 */
function svc_msg_agent_save(string $kind, int $rid, int $id, array $in): array
{
    $c = AI_MSG_AGENTS[$kind];
    $current = $id > 0 ? svc_row($c['table'], $id, $rid, $c['label']) : [];

    $values = [];
    foreach ($c['fields'] as $f) {
        $v = array_key_exists($f, $in) ? $in[$f] : ($current[$f] ?? null);
        $values[$f] = trim((string)$v) ?: null;
    }
    if ($values[$c['key']] === null) svc_fail($c['key_error'], $c['key']);
    if ($kind === 'text') $values['agent_id'] = $values['phone_number'];

    $pdo = db();
    $pdo->beginTransaction();
    try {
        if ($id > 0) {
            $sets = implode(', ', array_map(fn($f) => "{$f} = ?", array_keys($values)));
            $pdo->prepare("UPDATE {$c['table']} SET {$sets} WHERE id = ? AND restaurant_id = ?")
                ->execute([...array_values($values), $id, $rid]);
        } else {
            $cols = implode(', ', array_keys($values));
            $marks = implode(', ', array_fill(0, count($values), '?'));
            $stmt = $pdo->prepare("INSERT INTO {$c['table']} (restaurant_id, {$cols}) VALUES (?, {$marks}) RETURNING id");
            $stmt->execute([$rid, ...array_values($values)]);
            $id = (int)$stmt->fetchColumn();
        }
        if (!$current || array_key_exists('variables', $in) || array_key_exists('var_names', $in)) {
            svc_ai_save_variables($c['details'], $c['fk'], $id, svc_ai_variables($in));
        }
        $pdo->commit();
    } catch (PDOException $e) {
        $pdo->rollBack();
        if ($e->getCode() === '23505') {
            throw new ServiceError('A ' . strtolower($c['label']) . ' with this ID already exists for this restaurant.', 'CONFLICT', 409, 'duplicate');
        }
        throw $e;
    }
    return svc_msg_agent_get($kind, $rid, $id);
}

function svc_msg_agent_delete(string $kind, int $rid, int $id): void
{
    $c = AI_MSG_AGENTS[$kind];
    svc_row($c['table'], $id, $rid, $c['label']);
    db()->prepare("DELETE FROM {$c['details']} WHERE {$c['fk']} = ?")->execute([$id]);
    db()->prepare("DELETE FROM {$c['table']} WHERE id = ? AND restaurant_id = ?")->execute([$id, $rid]);
}
