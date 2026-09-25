<?php
/**
 * API operations: voice agents (Retell), text agents and email agents (admin)
 */

require_once __DIR__ . '/../../services/ai_agents.php';

$aiVariables = ['type' => 'array', 'description' => 'Prompt variables; replaces the whole list',
    'items' => ['type' => 'object', 'properties' => ['name' => ['type' => 'string'], 'value' => ['type' => 'string']]]];

/* ---------- Voice agents ---------- */

api_op('voice_agents_list', [
    'description' => "List the restaurant's voice agents (local copy of the Retell configuration) with their prompt variables.",
    'role' => 'admin', 'method' => 'GET', 'path' => 'voice-agents',
    'handler' => fn($in, $ctx) => ['voice_agents' => svc_voice_agent_list($ctx['restaurant_id'])],
]);

api_op('voice_agents_get', [
    'description' => 'Get one voice agent with its prompt variables.',
    'role' => 'admin', 'method' => 'GET', 'path' => 'voice-agents/{id}',
    'handler' => fn($in, $ctx) => ['voice_agent' => svc_voice_agent_get($ctx['restaurant_id'], $in['id'])],
]);

api_op('voice_agents_create', [
    'description' => 'Create a new voice agent in Retell (LLM, agent, call webhook, and the reservation MCP tools) and store it locally. Then edit it with voice_agents_update and push with voice_agents_sync.',
    'role' => 'admin', 'method' => 'POST', 'path' => 'voice-agents',
    'input' => ['agent_name' => ['type' => 'string']],
    'required' => ['agent_name'],
    'handler' => fn($in, $ctx) => svc_voice_agent_create($ctx['restaurant_id'], $in['agent_name']),
]);

api_op('voice_agents_update', [
    'description' => "Edit a voice agent's local configuration. Only the fields sent are changed. Does not call Retell — use voice_agents_sync afterwards.",
    'role' => 'admin', 'method' => 'PATCH', 'path' => 'voice-agents/{id}',
    'input' => [
        'description'          => ['type' => 'string', 'description' => 'Agent name'],
        'phone_number'         => ['type' => 'string'],
        'voice_agent_greeting' => ['type' => 'string'],
        'language_code'        => ['type' => 'string', 'description' => 'e.g. en, es'],
        'language_name'        => ['type' => 'string'],
        'is_primary_language'  => ['type' => 'boolean'],
        'model'                => ['type' => 'string', 'description' => 'LLM model, e.g. gpt-4.1'],
        'voice_id'             => ['type' => 'string'],
        'voice_model'          => ['type' => 'string'],
        'agent_prompt'         => ['type' => 'string', 'description' => 'System prompt of the voice agent'],
        'begin_message'        => ['type' => 'string'],
        'transfer_number'      => ['type' => 'string', 'description' => 'Number to transfer calls to'],
        'agent_swap_id'        => ['type' => 'string'],
        'webhook_url'          => ['type' => 'string'],
        'inbound_webhook_url'  => ['type' => 'string'],
        'mcp_url'              => ['type' => 'string'],
        'mcp_headers'          => ['type' => 'string', 'description' => 'JSON object of headers'],
        'variables'            => $aiVariables,
    ],
    'secret' => ['mcp_headers'],
    'handler' => function ($in, $ctx) {
        $id = $in['id'];
        unset($in['id']);
        return ['voice_agent' => svc_voice_agent_save($ctx['restaurant_id'], $id, $in)];
    },
]);

api_op('voice_agents_delete', [
    'description' => 'Delete the local voice agent record. The agent in Retell is not deleted.',
    'role' => 'admin', 'method' => 'DELETE', 'path' => 'voice-agents/{id}',
    'handler' => function ($in, $ctx) {
        svc_voice_agent_delete($ctx['restaurant_id'], $in['id']);
        return ['deleted' => true, 'id' => $in['id']];
    },
]);

api_op('voice_agents_sync', [
    'description' => 'Push the stored configuration to Retell, creating or updating the LLM and the agent.',
    'role' => 'admin', 'method' => 'POST', 'path' => 'voice-agents/{id}/sync',
    'handler' => function ($in, $ctx) {
        $r = svc_voice_agent_sync($ctx['restaurant_id'], $in['id']);
        if (!$r['success']) svc_ai_retell_fail('Sync had issues: ' . implode(' ', $r['messages']));
        return $r;
    },
]);

api_op('voice_agents_fetch', [
    'description' => "Pull the agent's current configuration from Retell and overwrite the local copy with it.",
    'role' => 'admin', 'method' => 'POST', 'path' => 'voice-agents/{id}/fetch',
    'handler' => fn($in, $ctx) => ['fetched' => svc_voice_agent_fetch($ctx['restaurant_id'], $in['id'])],
]);

api_op('voice_agents_update_prompt', [
    'description' => "Update the agent prompt (and optionally the begin message) directly in Retell, then locally.",
    'role' => 'admin', 'method' => 'POST', 'path' => 'voice-agents/{id}/prompt',
    'input' => ['agent_prompt' => ['type' => 'string'], 'begin_message' => ['type' => 'string']],
    'required' => ['agent_prompt'],
    'handler' => fn($in, $ctx) => ['voice_agent' => svc_voice_agent_update_prompt($ctx['restaurant_id'], $in['id'], $in['agent_prompt'], $in['begin_message'] ?? '')],
]);

api_op('voice_agents_assign_phone', [
    'description' => 'Route inbound calls on a Retell phone number to this agent (see retell_unassigned_phones).',
    'role' => 'admin', 'method' => 'POST', 'path' => 'voice-agents/{id}/phone',
    'input' => ['phone_number' => ['type' => 'string', 'description' => 'Retell phone number, E.164']],
    'required' => ['phone_number'],
    'handler' => fn($in, $ctx) => ['voice_agent' => svc_voice_agent_assign_phone($ctx['restaurant_id'], $in['id'], $in['phone_number'])],
]);

api_op('retell_unassigned_phones', [
    'description' => 'Phone numbers in the Retell account that no agent answers yet.',
    'role' => 'admin', 'method' => 'GET', 'path' => 'retell/unassigned-phones',
    'handler' => fn($in, $ctx) => ['phone_numbers' => svc_retell_unassigned_phones()],
]);

api_op('retell_prompt_templates', [
    'description' => 'Ready-made voice agent prompts to start from.',
    'role' => 'staff', 'method' => 'GET', 'path' => 'retell/prompt-templates',
    'handler' => fn($in, $ctx) => ['templates' => svc_prompt_templates()],
]);

/* ---------- Text (SMS) and email agents ---------- */

$msgAgentOps = [
    'text' => ['plural' => 'text_agents', 'path' => 'text-agents', 'what' => 'text (SMS) agent', 'fields' => [
        'phone_number'   => ['type' => 'string', 'description' => 'Number the agent texts from; also its agent ID'],
        'description'    => ['type' => 'string'],
        'system_prompt'  => ['type' => 'string'],
        'custom_webhook' => ['type' => 'string', 'description' => 'Optional URL that receives inbound messages instead'],
    ], 'required' => ['phone_number']],
    'email' => ['plural' => 'email_agents', 'path' => 'email-agents', 'what' => 'email agent', 'fields' => [
        'agent_id'             => ['type' => 'string', 'description' => 'Unique agent identifier'],
        'email_address'        => ['type' => 'string'],
        'email_agent_greeting' => ['type' => 'string'],
        'description'          => ['type' => 'string'],
    ], 'required' => ['agent_id']],
];

foreach ($msgAgentOps as $kind => $m) {
    $fields = $m['fields'] + ['variables' => $aiVariables];

    api_op("{$m['plural']}_list", [
        'description' => "List the restaurant's {$m['what']}s with their prompt variables.",
        'role' => 'admin', 'method' => 'GET', 'path' => $m['path'],
        'handler' => fn($in, $ctx) => [$m['plural'] => svc_msg_agent_list($kind, $ctx['restaurant_id'])],
    ]);
    api_op("{$m['plural']}_get", [
        'description' => "Get one {$m['what']}.",
        'role' => 'admin', 'method' => 'GET', 'path' => "{$m['path']}/{id}",
        'handler' => fn($in, $ctx) => ['agent' => svc_msg_agent_get($kind, $ctx['restaurant_id'], $in['id'])],
    ]);
    api_op("{$m['plural']}_create", [
        'description' => "Create a {$m['what']}.",
        'role' => 'admin', 'method' => 'POST', 'path' => $m['path'],
        'input' => $fields, 'required' => $m['required'],
        'handler' => fn($in, $ctx) => ['agent' => svc_msg_agent_save($kind, $ctx['restaurant_id'], 0, $in)],
    ]);
    api_op("{$m['plural']}_update", [
        'description' => "Edit a {$m['what']}. Only the fields sent are changed.",
        'role' => 'admin', 'method' => 'PATCH', 'path' => "{$m['path']}/{id}",
        'input' => $fields,
        'handler' => function ($in, $ctx) use ($kind) {
            $id = $in['id'];
            unset($in['id']);
            return ['agent' => svc_msg_agent_save($kind, $ctx['restaurant_id'], $id, $in)];
        },
    ]);
    api_op("{$m['plural']}_delete", [
        'description' => "Delete a {$m['what']}.",
        'role' => 'admin', 'method' => 'DELETE', 'path' => "{$m['path']}/{id}",
        'handler' => function ($in, $ctx) use ($kind) {
            svc_msg_agent_delete($kind, $ctx['restaurant_id'], $in['id']);
            return ['deleted' => true, 'id' => $in['id']];
        },
    ]);
}
