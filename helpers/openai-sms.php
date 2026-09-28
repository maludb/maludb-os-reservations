<?php
/**
 * OpenAI SMS Helper — Tool-calling agent for SMS conversations
 *
 * Converts MCP tool definitions to OpenAI function format,
 * sends conversation history + system prompt to GPT-4.1,
 * and handles the tool-call loop until a final text response.
 */

require_once __DIR__ . '/db.php';
require_once __DIR__ . '/availability.php';       // getRestaurantSetting()
require_once __DIR__ . '/voice-api.php';           // restaurant tool executors
require_once __DIR__ . '/../html/api/mcp/server-tools.php'; // restaurant MCP tool definitions/executors

define('SMS_LOG_FILE', __DIR__ . '/../logs/sms-agent.log');

function smsLog(string $message): void
{
    $ts = date('Y-m-d H:i:s');
    file_put_contents(SMS_LOG_FILE, "[{$ts}] {$message}\n", FILE_APPEND);
}

// -------------------------------------------------------
// Convert MCP tool definitions to OpenAI function format
// -------------------------------------------------------
function mcpToolsToOpenAI(array $mcpTools): array
{
    $functions = [];
    foreach ($mcpTools as $tool) {
        $params = $tool['inputSchema'] ?? ['type' => 'object', 'properties' => []];
        // OpenAI requires additionalProperties to be set
        if (!isset($params['additionalProperties'])) {
            $params['additionalProperties'] = false;
        }
        $functions[] = [
            'type' => 'function',
            'function' => [
                'name'        => $tool['name'],
                'description' => $tool['description'],
                'parameters'  => $params,
            ],
        ];
    }
    return $functions;
}

// -------------------------------------------------------
// Execute a tool call using the restaurant MCP executors
// -------------------------------------------------------
function executeSmsTool(string $toolName, array $args): array
{
    return executeTool($toolName, $args);
}

// -------------------------------------------------------
// Call OpenAI Chat Completions API
// -------------------------------------------------------
function callOpenAI(string $apiKey, array $messages, array $tools, string $model, float $temperature, int $maxTokens): ?array
{
    $payload = [
        'model'       => $model,
        'messages'    => $messages,
        'temperature' => $temperature,
        'max_tokens'  => $maxTokens,
    ];

    if (!empty($tools)) {
        $payload['tools'] = $tools;
        $payload['tool_choice'] = 'auto';
    }

    $ch = curl_init('https://api.openai.com/v1/chat/completions');
    curl_setopt_array($ch, [
        CURLOPT_POST           => true,
        CURLOPT_POSTFIELDS     => json_encode($payload),
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT        => 60,
        CURLOPT_HTTPHEADER     => [
            'Content-Type: application/json',
            "Authorization: Bearer {$apiKey}",
        ],
    ]);

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curlError = curl_error($ch);
    curl_close($ch);

    if ($curlError) {
        smsLog("OPENAI CURL ERROR: {$curlError}");
        return null;
    }

    if ($httpCode < 200 || $httpCode >= 300) {
        smsLog("OPENAI HTTP ERROR {$httpCode}: {$response}");
        return null;
    }

    return json_decode($response, true);
}

// -------------------------------------------------------
// Main: Process an inbound SMS through OpenAI with tools
// Returns the assistant's final text response
// -------------------------------------------------------
function processInboundSMS(
    int    $restaurantId,
    string $fromNumber,
    string $toNumber,
    string $inboundMessage
): string {
    $pdo = db();

    // --- Load OpenAI API key ---
    $apiKey = getRestaurantSetting($restaurantId, 'openai_api_key', '');
    if ($apiKey === '') {
        smsLog("ERROR: No OpenAI API key for restaurant {$restaurantId}");
        return '';
    }

    // --- Find or create conversation ---
    $stmt = $pdo->prepare(
        "SELECT * FROM sms_conversations
         WHERE restaurant_id = ? AND from_number = ? AND to_number = ? AND status = 'active'
         ORDER BY last_message_at DESC LIMIT 1"
    );
    $stmt->execute([$restaurantId, $fromNumber, $toNumber]);
    $conversation = $stmt->fetch();

    // --- Load sms_prompt (system prompt + LLM config) ---
    // Try to find a prompt linked to a text_agent_prompt matching this phone number
    $smsPrompt = null;
    $stmt = $pdo->prepare(
        "SELECT sp.* FROM sms_prompts sp
         JOIN text_agent_prompts tap ON sp.text_agent_prompt_id = tap.id
         WHERE sp.restaurant_id = ? AND tap.phone_number = ? AND sp.is_active = 1
         LIMIT 1"
    );
    $stmt->execute([$restaurantId, $toNumber]);
    $smsPrompt = $stmt->fetch();

    // Fallback: any active prompt for this restaurant
    if (!$smsPrompt) {
        $stmt = $pdo->prepare(
            "SELECT * FROM sms_prompts WHERE restaurant_id = ? AND is_active = 1 ORDER BY id ASC LIMIT 1"
        );
        $stmt->execute([$restaurantId]);
        $smsPrompt = $stmt->fetch();
    }

    if (!$smsPrompt) {
        smsLog("ERROR: No active SMS prompt for restaurant {$restaurantId}");
        return '';
    }

    $model       = $smsPrompt['model'] ?: 'gpt-4.1';
    $temperature = (float)($smsPrompt['temperature'] ?: 0.7);
    $maxTokens   = (int)($smsPrompt['max_tokens'] ?: 1024);
    $systemPrompt = $smsPrompt['system_prompt'];

    // --- Load text agent custom variables and inject into system prompt ---
    if ($smsPrompt['text_agent_prompt_id']) {
        $stmt = $pdo->prepare(
            "SELECT variable_name, variable_value FROM text_agent_prompt_details
             WHERE text_agent_prompt_id = ? AND is_active = 1"
        );
        $stmt->execute([$smsPrompt['text_agent_prompt_id']]);
        $vars = $stmt->fetchAll();
        foreach ($vars as $v) {
            $systemPrompt = str_replace('{{' . $v['variable_name'] . '}}', $v['variable_value'], $systemPrompt);
        }
    }

    // --- Inject dynamic context variables ---
    $stmt = $pdo->prepare("SELECT * FROM restaurants WHERE id = ? LIMIT 1");
    $stmt->execute([$restaurantId]);
    $restaurant = $stmt->fetch();

    $slug = $restaurant['slug'] ?? $restaurant['booking_slug'] ?? '';
    $contextVars = [
        'restaurant_name'  => $restaurant['name'] ?? '',
        'restaurant_slug'  => $slug,
        'restaurant_phone' => $restaurant['phone'] ?? '',
        'caller_phone'     => $fromNumber,
        'today'            => date('Y-m-d'),
        'time'             => date('H:i'),
        'day_of_week'      => strtolower(date('l')),
    ];
    foreach ($contextVars as $key => $val) {
        $systemPrompt = str_replace('{{' . $key . '}}', $val, $systemPrompt);
    }

    // --- Create or reuse conversation ---
    if (!$conversation) {
        $stmt = $pdo->prepare(
            "INSERT INTO sms_conversations (restaurant_id, sms_prompt_id, from_number, to_number, status, last_message_at)
             VALUES (?, ?, ?, ?, 'active', NOW())"
        );
        $stmt->execute([$restaurantId, $smsPrompt['id'], $fromNumber, $toNumber]);
        $conversationId = (int)$pdo->lastInsertId();
    } else {
        $conversationId = (int)$conversation['id'];
        $pdo->prepare("UPDATE sms_conversations SET last_message_at = NOW() WHERE id = ?")->execute([$conversationId]);
    }

    // --- Save inbound user message ---
    $stmt = $pdo->prepare(
        "INSERT INTO sms_messages (sms_conversation_id, role, content) VALUES (?, 'user', ?)"
    );
    $stmt->execute([$conversationId, $inboundMessage]);

    // --- Load conversation history ---
    $stmt = $pdo->prepare(
        "SELECT role, content, tool_call_id, tool_name, tool_args
         FROM sms_messages
         WHERE sms_conversation_id = ?
         ORDER BY created_at ASC"
    );
    $stmt->execute([$conversationId]);
    $history = $stmt->fetchAll();

    // Build OpenAI messages array
    $messages = [
        ['role' => 'system', 'content' => $systemPrompt],
    ];
    foreach ($history as $msg) {
        if ($msg['role'] === 'tool') {
            $messages[] = [
                'role'         => 'tool',
                'tool_call_id' => $msg['tool_call_id'],
                'content'      => $msg['content'] ?? '',
            ];
        } elseif ($msg['role'] === 'assistant' && $msg['tool_name']) {
            // This was a tool-call assistant message
            $messages[] = [
                'role'       => 'assistant',
                'content'    => null,
                'tool_calls' => [[
                    'id'       => $msg['tool_call_id'],
                    'type'     => 'function',
                    'function' => [
                        'name'      => $msg['tool_name'],
                        'arguments' => $msg['tool_args'] ?? '{}',
                    ],
                ]],
            ];
        } else {
            $messages[] = [
                'role'    => $msg['role'],
                'content' => $msg['content'],
            ];
        }
    }

    // --- Get tools for this location type ---
    $mcpTools = getToolDefinitions();
    $openaiTools = mcpToolsToOpenAI($mcpTools);

    // --- Tool-calling loop (max 5 iterations to prevent runaway) ---
    $maxIterations = 5;
    $finalResponse = '';

    for ($i = 0; $i < $maxIterations; $i++) {
        smsLog("OPENAI CALL #{$i} — conversation:{$conversationId} messages:" . count($messages));

        $result = callOpenAI($apiKey, $messages, $openaiTools, $model, $temperature, $maxTokens);

        if (!$result || !isset($result['choices'][0]['message'])) {
            smsLog("ERROR: No valid response from OpenAI");
            $finalResponse = 'Sorry, I encountered an error. Please try again.';
            break;
        }

        $choice = $result['choices'][0];
        $assistantMsg = $choice['message'];
        $tokensUsed = $result['usage']['total_tokens'] ?? null;

        // Check for tool calls
        if (isset($assistantMsg['tool_calls']) && !empty($assistantMsg['tool_calls'])) {
            // Process each tool call
            foreach ($assistantMsg['tool_calls'] as $toolCall) {
                $tcId   = $toolCall['id'];
                $tcName = $toolCall['function']['name'];
                $tcArgs = $toolCall['function']['arguments'] ?? '{}';
                $args   = json_decode($tcArgs, true) ?: [];

                smsLog("TOOL CALL: {$tcName}");

                // Save assistant tool-call message
                $stmt = $pdo->prepare(
                    "INSERT INTO sms_messages (sms_conversation_id, role, content, tool_call_id, tool_name, tool_args, tokens_used)
                     VALUES (?, 'assistant', NULL, ?, ?, ?, ?)"
                );
                $stmt->execute([$conversationId, $tcId, $tcName, $tcArgs, $tokensUsed]);

                // Execute the tool
                $toolResult = executeSmsTool($tcName, $args);
                $toolContent = '';
                if (isset($toolResult['content'])) {
                    foreach ($toolResult['content'] as $c) {
                        $toolContent .= ($c['text'] ?? '');
                    }
                }

                smsLog("TOOL RESULT: " . strlen($toolContent) . " bytes");

                // Save tool result message
                $stmt = $pdo->prepare(
                    "INSERT INTO sms_messages (sms_conversation_id, role, content, tool_call_id, tool_name)
                     VALUES (?, 'tool', ?, ?, ?)"
                );
                $stmt->execute([$conversationId, $toolContent, $tcId, $tcName]);

                // Add to messages for next iteration
                $messages[] = [
                    'role'       => 'assistant',
                    'content'    => null,
                    'tool_calls' => [[ 'id' => $tcId, 'type' => 'function', 'function' => ['name' => $tcName, 'arguments' => $tcArgs] ]],
                ];
                $messages[] = [
                    'role'         => 'tool',
                    'tool_call_id' => $tcId,
                    'content'      => $toolContent,
                ];
            }
            // Continue loop to get next response after tool results
            continue;
        }

        // No tool calls — this is the final text response
        $finalResponse = $assistantMsg['content'] ?? '';

        // Save assistant response
        $stmt = $pdo->prepare(
            "INSERT INTO sms_messages (sms_conversation_id, role, content, tokens_used) VALUES (?, 'assistant', ?, ?)"
        );
        $stmt->execute([$conversationId, $finalResponse, $tokensUsed]);

        smsLog("FINAL RESPONSE: " . strlen($finalResponse) . " characters");
        break;
    }

    return $finalResponse;
}
