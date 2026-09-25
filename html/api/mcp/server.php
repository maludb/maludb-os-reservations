<?php
/**
 * MCP (Model Context Protocol) Server — Streamable HTTP
 *
 * JSON-RPC 2.0 over HTTP. Exposes reservation tools for Retell AI,
 * Claude, or any MCP-compatible client.
 *
 * Auth: Bearer token in Authorization header; must equal a restaurant's 'mcp_api_key'
 * setting, and the caller may then only act on that restaurant.
 */

require_once __DIR__ . '/../../../helpers/db.php';
require_once __DIR__ . '/../../../helpers/availability.php';
require_once __DIR__ . '/../../../helpers/api/auth.php';
require_once __DIR__ . '/../../../helpers/services/_base.php';
require_once __DIR__ . '/server-tools.php';

header('Content-Type: application/json');

// --- Logging ---
define('MCP_LOG_FILE', __DIR__ . '/../../../logs/mcp-server.log');

function mcpLog(string $message): void
{
    $ts = date('Y-m-d H:i:s');
    file_put_contents(MCP_LOG_FILE, "[{$ts}] {$message}\n", FILE_APPEND);
}

function mcpLogJson(string $label, $data): void
{
    mcpLog("{$label}: " . json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
}

// --- Auth ---
/**
 * The bearer token must equal one restaurant's 'mcp_api_key' setting.
 * The key binds the caller to that restaurant. No key configured = no access.
 * Returns the restaurant's slug.
 */
function verifyMcpAuth(): string
{
    $token = api_bearer_token();

    if ($token !== '') {
        $stmt = db()->prepare(
            "SELECT r.slug, s.setting_value FROM settings s JOIN restaurants r ON r.id = s.restaurant_id
             WHERE s.setting_key = 'mcp_api_key' AND s.setting_value != '' AND r.is_active = 1"
        );
        $stmt->execute();
        foreach ($stmt->fetchAll() as $row) {
            if (hash_equals($row['setting_value'], $token)) {
                return $row['slug'];
            }
        }
    }

    mcpLog("AUTH FAILED: " . ($token === '' ? 'no token' : 'token matches no restaurant'));
    http_response_code(401);
    header('WWW-Authenticate: Bearer');
    emitMcpJson([
        'jsonrpc' => '2.0',
        'error' => ['code' => -32000, 'message' => 'Unauthorized'],
        'id' => null,
    ]);
    exit;
}

// --- JSON-RPC Handler ---
function jsonRpcError(int $code, string $message, $id = null): array
{
    return [
        'jsonrpc' => '2.0',
        'error' => ['code' => $code, 'message' => $message],
        'id' => $id,
    ];
}

function jsonRpcResult($result, $id): array
{
    return [
        'jsonrpc' => '2.0',
        'result' => $result,
        'id' => $id,
    ];
}

function emitMcpJson(array $payload): void
{
    $json = json_encode(
        $payload,
        JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE
    );

    if ($json === false) {
        http_response_code(500);
        echo '{"jsonrpc":"2.0","error":{"code":-32603,"message":"Internal JSON encoding error."},"id":null}';
        return;
    }

    echo $json;
}

// --- Main ---
mcpLog("=== REQUEST: {$_SERVER['REQUEST_METHOD']} {$_SERVER['REQUEST_URI']} ===");

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    mcpLog("ERROR: Method not allowed — {$_SERVER['REQUEST_METHOD']}");
    http_response_code(405);
    emitMcpJson(jsonRpcError(-32600, 'Only POST requests are accepted.'));
    exit;
}

$boundSlug = verifyMcpAuth();

$rawBody = file_get_contents('php://input');
mcpLog("RAW BODY: " . ($rawBody ?: '(empty)'));

$request = json_decode($rawBody, true);

if (!is_array($request) || !isset($request['method'])) {
    mcpLog("ERROR: Invalid JSON-RPC request");
    emitMcpJson(jsonRpcError(-32700, 'Parse error: invalid JSON-RPC request.'));
    exit;
}

$method = $request['method'];
$params = $request['params'] ?? [];
$id = $request['id'] ?? null;

mcpLog("METHOD: {$method} | ID: " . json_encode($id));

switch ($method) {
    case 'initialize':
        $response = jsonRpcResult([
            'protocolVersion' => '2024-11-05',
            'capabilities' => [
                'tools' => ['listChanged' => false],
            ],
            'serverInfo' => [
                'name' => 'ZozoCal Restaurant Reservations',
                'version' => '1.0.0',
            ],
        ], $id);
        mcpLog("RESPONSE: initialize OK");
        emitMcpJson($response);
        break;

    case 'notifications/initialized':
        mcpLog("RESPONSE: notifications/initialized acknowledged");
        // Notification — no response needed, but send empty result if id present
        if ($id !== null) {
            emitMcpJson(jsonRpcResult([], $id));
        }
        break;

    case 'tools/list':
        mcpLog("RESPONSE: tools/list — returning " . count(getToolDefinitions()) . " tools");
        emitMcpJson(jsonRpcResult([
            'tools' => getToolDefinitions(),
        ], $id));
        break;

    case 'tools/call':
        $toolName = $params['name'] ?? '';
        $toolArgs = $params['arguments'] ?? [];

        mcpLog("TOOL CALL: {$toolName}");
        mcpLogJson("TOOL ARGS", $toolArgs);

        if ($toolName === '') {
            mcpLog("ERROR: Missing tool name");
            emitMcpJson(jsonRpcError(-32602, 'Missing tool name.', $id));
            break;
        }

        // The key belongs to one restaurant; default the slug to it and refuse any other
        $askedSlug = $toolArgs['restaurant_slug'] ?? '';
        if ($askedSlug !== '' && $askedSlug !== $boundSlug) {
            mcpLog("REFUSED: key for {$boundSlug} used for {$askedSlug}");
            emitMcpJson(jsonRpcResult(['isError' => true, 'content' => [['type' => 'text',
                'text' => "This API key belongs to restaurant \"{$boundSlug}\" and cannot be used for \"{$askedSlug}\"."]]], $id));
            break;
        }
        $toolArgs['restaurant_slug'] = $boundSlug;

        try {
            $toolResult = executeTool($toolName, $toolArgs);
            if (empty($toolResult['isError']) && in_array($toolName, ['make_reservation', 'cancel_reservation', 'confirm_reservation', 'modify_reservation', 'update_contact_preferences'], true)) {
                $rid = (int)(getRestaurantBySlug($boundSlug)['id'] ?? 0);
                svc_log($rid, 0, 'mcp_guest_call', 'reservation', null, "Guest MCP {$toolName}", null, $toolArgs);
            }
            mcpLogJson("TOOL RESULT", $toolResult);
            emitMcpJson(jsonRpcResult($toolResult, $id));
        } catch (Throwable $e) {
            mcpLog("EXCEPTION: " . $e->getMessage());
            mcpLog($e->getTraceAsString());
            emitMcpJson(jsonRpcError(-32603, 'Internal server error.', $id));
        }
        break;

    default:
        mcpLog("ERROR: Unknown method — {$method}");
        emitMcpJson(jsonRpcError(-32601, "Method not found: {$method}", $id));
        break;
}

mcpLog("=== END ===");
