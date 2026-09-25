<?php
/**
 * Retell AI API Helper
 *
 * Functions for creating, updating, and retrieving Retell LLMs and Agents.
 * Uses the API key stored in settings table via getRetellApiKey().
 */

require_once __DIR__ . '/retell-auth.php';

define('RETELL_API_BASE', 'https://api.retellai.com');

/**
 * Make an authenticated request to the Retell API.
 *
 * @param string $method  HTTP method (GET, POST, PATCH, DELETE)
 * @param string $endpoint API path (e.g. /create-agent)
 * @param array|null $data  Request body (JSON-encoded for POST/PATCH)
 * @return array ['success' => bool, 'status' => int, 'data' => array|null, 'error' => string|null]
 */
function retellApiCall(string $method, string $endpoint, ?array $data = null): array
{
    $apiKey = getRetellApiKey();
    if ($apiKey === '') {
        return ['success' => false, 'status' => 0, 'data' => null, 'error' => 'Retell API key not configured. Add it in Settings > Integrations.'];
    }

    $url = RETELL_API_BASE . $endpoint;
    $ch = curl_init($url);

    $headers = [
        'Authorization: Bearer ' . $apiKey,
        'Content-Type: application/json',
    ];

    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER     => $headers,
        CURLOPT_TIMEOUT        => 30,
        CURLOPT_CUSTOMREQUEST  => strtoupper($method),
    ]);

    if ($data !== null && in_array(strtoupper($method), ['POST', 'PATCH'])) {
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
    }

    $response = curl_exec($ch);
    $httpCode = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curlError = curl_error($ch);
    curl_close($ch);

    if ($curlError !== '') {
        return ['success' => false, 'status' => 0, 'data' => null, 'error' => 'cURL error: ' . $curlError];
    }

    $decoded = json_decode($response, true);

    if ($httpCode >= 200 && $httpCode < 300) {
        return ['success' => true, 'status' => $httpCode, 'data' => $decoded, 'error' => null];
    }

    $errorMsg = $decoded['message'] ?? $decoded['error'] ?? "HTTP {$httpCode}";
    return ['success' => false, 'status' => $httpCode, 'data' => $decoded, 'error' => $errorMsg];
}

// ---------------------------------------------------------------------------
// Retell LLM (Brain) endpoints
// ---------------------------------------------------------------------------

/**
 * Create a new Retell LLM.
 * @param array $config LLM configuration (model, general_prompt, general_tools, mcps, etc.)
 * @return array API response with llm_id on success
 */
function retellCreateLlm(array $config): array
{
    return retellApiCall('POST', '/create-retell-llm', $config);
}

/**
 * Update an existing Retell LLM (partial update).
 * @param string $llmId The LLM ID to update
 * @param array $config Fields to update
 * @return array API response
 */
function retellUpdateLlm(string $llmId, array $config): array
{
    return retellApiCall('PATCH', '/update-retell-llm/' . urlencode($llmId), $config);
}

/**
 * Get a Retell LLM by ID.
 * @param string $llmId The LLM ID
 * @return array API response with full LLM config
 */
function retellGetLlm(string $llmId): array
{
    return retellApiCall('GET', '/get-retell-llm/' . urlencode($llmId));
}

// ---------------------------------------------------------------------------
// Agent (Voice) endpoints
// ---------------------------------------------------------------------------

/**
 * Create a new Retell Agent.
 * @param array $config Agent configuration (response_engine, voice_id, webhook_url, etc.)
 * @return array API response with agent_id on success
 */
function retellCreateAgent(array $config): array
{
    return retellApiCall('POST', '/create-agent', $config);
}

/**
 * Update an existing Retell Agent (partial update).
 * @param string $agentId The Agent ID to update
 * @param array $config Fields to update
 * @return array API response
 */
function retellUpdateAgent(string $agentId, array $config): array
{
    return retellApiCall('PATCH', '/update-agent/' . urlencode($agentId), $config);
}

/**
 * Get a Retell Agent by ID.
 * @param string $agentId The Agent ID
 * @return array API response with full agent config
 */
function retellGetAgent(string $agentId): array
{
    return retellApiCall('GET', '/get-agent/' . urlencode($agentId));
}

/**
 * List all Retell Agents.
 * @return array API response with array of agents
 */
function retellListAgents(): array
{
    return retellApiCall('GET', '/list-agents');
}

/**
 * Delete a Retell Agent.
 * @param string $agentId The Agent ID to delete
 * @return array API response
 */
function retellDeleteAgent(string $agentId): array
{
    return retellApiCall('DELETE', '/delete-agent/' . urlencode($agentId));
}

/**
 * Delete a Retell LLM.
 * @param string $llmId The LLM ID to delete
 * @return array API response
 */
function retellDeleteLlm(string $llmId): array
{
    return retellApiCall('DELETE', '/delete-retell-llm/' . urlencode($llmId));
}

// ---------------------------------------------------------------------------
// Phone Number endpoints
// ---------------------------------------------------------------------------

/**
 * List all phone numbers in the Retell account.
 * @return array API response with array of phone number objects
 */
function retellListPhoneNumbers(): array
{
    return retellApiCall('GET', '/list-phone-numbers');
}

/**
 * Update a phone number (assign/unassign agents).
 * @param string $phoneNumber E.164 format (e.g. +18005551234)
 * @param array $config Fields to update (inbound_agents, outbound_agents, etc.)
 * @return array API response
 */
function retellUpdatePhoneNumber(string $phoneNumber, array $config): array
{
    return retellApiCall('PATCH', '/update-phone-number/' . urlencode($phoneNumber), $config);
}

// ---------------------------------------------------------------------------
// Payload builders
// ---------------------------------------------------------------------------

/**
 * Map short language code to Retell's full locale code.
 */
function retellLanguageCode(string $shortCode): string
{
    $map = [
        'en' => 'en-US', 'es' => 'es-ES', 'fr' => 'fr-FR', 'pt' => 'pt-BR',
        'de' => 'de-DE', 'it' => 'it-IT', 'zh' => 'zh-CN', 'ja' => 'ja-JP',
        'ko' => 'ko-KR', 'pl' => 'pl-PL', 'ro' => 'ro-RO', 'ar' => 'ar-SA',
        'hi' => 'hi-IN',
    ];
    return $map[$shortCode] ?? 'en-US';
}

/**
 * Build the general_tools array for a Retell LLM.
 *
 * Always includes end_call. Conditionally includes transfer_call and agent_swap.
 *
 * @param string|null $transferNumber  Phone number for transfer_call (omit if empty)
 * @param string|null $agentSwapId     Agent ID for agent_swap (omit if empty)
 * @return array Tools array for the Retell LLM payload
 */
function retellBuildTools(?string $transferNumber = null, ?string $agentSwapId = null): array
{
    $tools = [];

    // end_call — always present
    $tools[] = [
        'type' => 'end_call',
        'name' => 'end_call',
        'description' => 'End the call when the conversation is complete or the caller says goodbye.',
    ];

    // transfer_call — only if a transfer number is provided
    if ($transferNumber !== null && $transferNumber !== '') {
        $tools[] = [
            'type' => 'transfer_call',
            'name' => 'transfer_to_staff',
            'description' => 'Transfer the caller to a staff member when they request to speak with a person or when you cannot help them further.',
            'transfer_destination' => [
                'type' => 'predefined',
                'number' => $transferNumber,
            ],
            'transfer_option' => [
                'type' => 'cold_transfer',
            ],
        ];
    }

    // agent_swap — only if an agent swap ID is provided
    if ($agentSwapId !== null && $agentSwapId !== '') {
        $tools[] = [
            'type' => 'agent_swap',
            'name' => 'switch_agent',
            'description' => 'Switch to another specialized agent when the caller needs a different type of assistance.',
            'agent_id' => $agentSwapId,
        ];
    }

    return $tools;
}

/**
 * Build the Retell LLM payload from stored prompt config.
 *
 * @param array $prompt  Row from restaurant_prompts table
 * @param array $details Rows from restaurant_prompt_details table
 * @return array Payload for create/update Retell LLM
 */
function retellBuildLlmPayload(array $prompt, array $details = []): array
{
    $payload = [
        'model' => $prompt['model'] ?: 'gpt-4.1',
        'model_temperature' => 0,
        'start_speaker' => 'agent',
    ];

    if (!empty($prompt['agent_prompt'])) {
        $payload['general_prompt'] = $prompt['agent_prompt'];
    }

    if (!empty($prompt['begin_message'])) {
        $payload['begin_message'] = $prompt['begin_message'];
    }

    // Tools
    $payload['general_tools'] = retellBuildTools(
        $prompt['transfer_number'] ?? null,
        $prompt['agent_swap_id'] ?? null
    );

    // MCP
    if (!empty($prompt['mcp_url'])) {
        $mcpConfig = [
            'name' => 'restaurant_mcp',
            'url' => $prompt['mcp_url'],
        ];
        if (!empty($prompt['mcp_headers'])) {
            $headers = json_decode($prompt['mcp_headers'], true);
            if (is_array($headers)) {
                $mcpConfig['headers'] = $headers;
            }
        }
        $payload['mcps'] = [$mcpConfig];
    }

    // Default dynamic variables from custom variable pairs
    $dynamicVars = [];
    foreach ($details as $d) {
        if (!empty($d['is_active']) && !empty($d['variable_name'])) {
            $dynamicVars[$d['variable_name']] = $d['variable_value'] ?? '';
        }
    }
    if (!empty($dynamicVars)) {
        $payload['default_dynamic_variables'] = $dynamicVars;
    }

    return $payload;
}

/**
 * Build the Retell Agent payload from stored prompt config.
 *
 * @param array $prompt  Row from restaurant_prompts table
 * @param string $llmId  The Retell LLM ID to link
 * @return array Payload for create/update Retell Agent
 */
function retellBuildAgentPayload(array $prompt, string $llmId): array
{
    $payload = [
        'response_engine' => [
            'type' => 'retell-llm',
            'llm_id' => $llmId,
        ],
    ];

    if (!empty($prompt['voice_id'])) {
        $payload['voice_id'] = $prompt['voice_id'];
    }

    if (!empty($prompt['voice_model'])) {
        $payload['voice_model'] = $prompt['voice_model'];
    }

    if (!empty($prompt['description'])) {
        $payload['agent_name'] = $prompt['description'];
    }

    if (!empty($prompt['language_code'])) {
        $payload['language'] = retellLanguageCode($prompt['language_code']);
    }

    if (!empty($prompt['webhook_url'])) {
        $payload['webhook_url'] = $prompt['webhook_url'];
        $payload['webhook_events'] = ['call_started', 'call_ended', 'call_analyzed'];
    }

    return $payload;
}
