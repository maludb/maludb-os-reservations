<?php
/**
 * OpenAI Email Helper — Tool-calling agent for email conversations
 *
 * Converts MCP tool definitions to OpenAI function format,
 * sends conversation history + system prompt to GPT-4.1,
 * and handles the tool-call loop until a final text response.
 */

require_once __DIR__ . '/db.php';
require_once __DIR__ . '/availability.php';
require_once __DIR__ . '/voice-api.php';
require_once __DIR__ . '/../html/api/mcp/server-tools.php';

if (!defined('EMAIL_AGENT_LOG_FILE')) {
    define('EMAIL_AGENT_LOG_FILE', __DIR__ . '/../logs/email-agent.log');
}

function emailAgentLog(string $message): void
{
    $ts = date('Y-m-d H:i:s');
    file_put_contents(EMAIL_AGENT_LOG_FILE, "[{$ts}] {$message}\n", FILE_APPEND);
}

// -------------------------------------------------------
// Convert MCP tool definitions to OpenAI function format
// -------------------------------------------------------
function emailMcpToolsToOpenAI(array $mcpTools): array
{
    $functions = [];
    foreach ($mcpTools as $tool) {
        $params = $tool['inputSchema'] ?? ['type' => 'object', 'properties' => []];
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
function executeEmailTool(string $toolName, array $args): array
{
    return executeTool($toolName, $args);
}

// -------------------------------------------------------
// Call OpenAI Chat Completions API
// -------------------------------------------------------
function callOpenAIForEmail(string $apiKey, array $messages, array $tools, string $model, float $temperature, int $maxTokens): ?array
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
        emailAgentLog("OPENAI CURL ERROR: {$curlError}");
        return null;
    }

    if ($httpCode < 200 || $httpCode >= 300) {
        emailAgentLog("OPENAI HTTP ERROR {$httpCode}: {$response}");
        return null;
    }

    return json_decode($response, true);
}

// -------------------------------------------------------
// Main: Process an inbound email through OpenAI with tools
// Returns the assistant's final text response
// -------------------------------------------------------
function processInboundEmail(
    int    $restaurantId,
    string $fromEmail,
    string $toEmail,
    string $subject,
    string $inboundMessage
): string {
    $pdo = db();

    // --- Load OpenAI API key ---
    $apiKey = getRestaurantSetting($restaurantId, 'openai_api_key', '');
    if ($apiKey === '') {
        emailAgentLog("ERROR: No OpenAI API key for restaurant {$restaurantId}");
        return '';
    }

    // --- Find or create conversation ---
    $stmt = $pdo->prepare(
        "SELECT * FROM email_conversations
         WHERE restaurant_id = ? AND from_email = ? AND to_email = ? AND status = 'active'
         ORDER BY last_message_at DESC LIMIT 1"
    );
    $stmt->execute([$restaurantId, $fromEmail, $toEmail]);
    $conversation = $stmt->fetch();

    // --- Load email_prompt (system prompt + LLM config) ---
    $emailPrompt = null;
    $stmt = $pdo->prepare(
        "SELECT ep.* FROM email_prompts ep
         JOIN email_agent_prompts eap ON ep.email_agent_prompt_id = eap.id
         WHERE ep.restaurant_id = ? AND eap.email_address = ? AND ep.is_active = 1
         LIMIT 1"
    );
    $stmt->execute([$restaurantId, $toEmail]);
    $emailPrompt = $stmt->fetch();

    // Fallback: any active prompt for this restaurant
    if (!$emailPrompt) {
        $stmt = $pdo->prepare(
            "SELECT * FROM email_prompts WHERE restaurant_id = ? AND is_active = 1 ORDER BY id ASC LIMIT 1"
        );
        $stmt->execute([$restaurantId]);
        $emailPrompt = $stmt->fetch();
    }

    if (!$emailPrompt) {
        emailAgentLog("ERROR: No active email prompt for restaurant {$restaurantId}");
        return '';
    }

    $model       = $emailPrompt['model'] ?: 'gpt-4.1';
    $temperature = (float)($emailPrompt['temperature'] ?: 0.7);
    $maxTokens   = (int)($emailPrompt['max_tokens'] ?: 2048);
    $systemPrompt = $emailPrompt['system_prompt'];

    // --- Load email agent custom variables and inject into system prompt ---
    if ($emailPrompt['email_agent_prompt_id']) {
        $stmt = $pdo->prepare(
            "SELECT variable_name, variable_value FROM email_agent_prompt_details
             WHERE email_agent_prompt_id = ? AND is_active = 1"
        );
        $stmt->execute([$emailPrompt['email_agent_prompt_id']]);
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
        'sender_email'     => $fromEmail,
        'email_subject'    => $subject,
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
            "INSERT INTO email_conversations (restaurant_id, email_prompt_id, from_email, to_email, subject, status, last_message_at)
             VALUES (?, ?, ?, ?, ?, 'active', NOW())"
        );
        $stmt->execute([$restaurantId, $emailPrompt['id'], $fromEmail, $toEmail, $subject]);
        $conversationId = (int)$pdo->lastInsertId();
    } else {
        $conversationId = (int)$conversation['id'];
        $pdo->prepare("UPDATE email_conversations SET last_message_at = NOW() WHERE id = ?")->execute([$conversationId]);
    }

    // --- Save inbound user message ---
    $stmt = $pdo->prepare(
        "INSERT INTO email_messages (email_conversation_id, role, content) VALUES (?, 'user', ?)"
    );
    $stmt->execute([$conversationId, $inboundMessage]);

    // --- Load conversation history ---
    $stmt = $pdo->prepare(
        "SELECT role, content, tool_call_id, tool_name, tool_args
         FROM email_messages
         WHERE email_conversation_id = ?
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
    $openaiTools = emailMcpToolsToOpenAI($mcpTools);

    // --- Tool-calling loop (max 5 iterations) ---
    $maxIterations = 5;
    $finalResponse = '';

    for ($i = 0; $i < $maxIterations; $i++) {
        emailAgentLog("OPENAI CALL #{$i} — conversation:{$conversationId} messages:" . count($messages));

        $result = callOpenAIForEmail($apiKey, $messages, $openaiTools, $model, $temperature, $maxTokens);

        if (!$result || !isset($result['choices'][0]['message'])) {
            emailAgentLog("ERROR: No valid response from OpenAI");
            $finalResponse = 'Sorry, I encountered an error processing your email. Please try again.';
            break;
        }

        $choice = $result['choices'][0];
        $assistantMsg = $choice['message'];
        $tokensUsed = $result['usage']['total_tokens'] ?? null;

        // Check for tool calls
        if (isset($assistantMsg['tool_calls']) && !empty($assistantMsg['tool_calls'])) {
            foreach ($assistantMsg['tool_calls'] as $toolCall) {
                $tcId   = $toolCall['id'];
                $tcName = $toolCall['function']['name'];
                $tcArgs = $toolCall['function']['arguments'] ?? '{}';
                $args   = json_decode($tcArgs, true) ?: [];

                emailAgentLog("TOOL CALL: {$tcName}");

                // Save assistant tool-call message
                $stmt = $pdo->prepare(
                    "INSERT INTO email_messages (email_conversation_id, role, content, tool_call_id, tool_name, tool_args, tokens_used)
                     VALUES (?, 'assistant', NULL, ?, ?, ?, ?)"
                );
                $stmt->execute([$conversationId, $tcId, $tcName, $tcArgs, $tokensUsed]);

                // Execute the tool
                $toolResult = executeEmailTool($tcName, $args);
                $toolContent = '';
                if (isset($toolResult['content'])) {
                    foreach ($toolResult['content'] as $c) {
                        $toolContent .= ($c['text'] ?? '');
                    }
                }

                emailAgentLog("TOOL RESULT: " . strlen($toolContent) . " bytes");

                // Save tool result message
                $stmt = $pdo->prepare(
                    "INSERT INTO email_messages (email_conversation_id, role, content, tool_call_id, tool_name)
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
            continue;
        }

        // No tool calls — final text response
        $finalResponse = $assistantMsg['content'] ?? '';

        // Save assistant response
        $stmt = $pdo->prepare(
            "INSERT INTO email_messages (email_conversation_id, role, content, tokens_used) VALUES (?, 'assistant', ?, ?)"
        );
        $stmt->execute([$conversationId, $finalResponse, $tokensUsed]);

        emailAgentLog("FINAL RESPONSE: " . strlen($finalResponse) . " characters");
        break;
    }

    return $finalResponse;
}
