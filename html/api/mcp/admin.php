<?php
/**
 * Admin MCP server — Streamable HTTP, JSON-RPC 2.0
 *
 * Exposes every operation in the API registry (helpers/api/ops/*.php) as an MCP
 * tool, filtered by the caller's role. Same operations as the REST API.
 *
 * Auth: Authorization: Bearer <api key> (Settings → API Keys).
 * Super-admin keys may add X-Restaurant-Id to act on another restaurant.
 *
 * The guest-facing reservation tools used by voice/SMS/email agents stay on
 * /api/mcp/server.php.
 */

require_once __DIR__ . '/../../../helpers/api/auth.php';
require_once __DIR__ . '/../../../helpers/api/registry.php';

header('Content-Type: application/json');

function adminMcpEmit(array $payload): void
{
    echo json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE);
}

function adminMcpError(int $code, string $message, $id = null): void
{
    adminMcpEmit(['jsonrpc' => '2.0', 'error' => ['code' => $code, 'message' => $message], 'id' => $id]);
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    header('Allow: POST');
    adminMcpError(-32600, 'Only POST requests are accepted.');
    exit;
}

$ctx = api_token_context(api_bearer_token(), api_restaurant_header());
if (!$ctx) {
    http_response_code(401);
    header('WWW-Authenticate: Bearer');
    adminMcpError(-32000, 'Unauthorized: send a valid API key as a Bearer token.');
    exit;
}

$request = json_decode(file_get_contents('php://input'), true);
if (!is_array($request) || !isset($request['method'])) {
    adminMcpError(-32700, 'Parse error: invalid JSON-RPC request.');
    exit;
}

$method = $request['method'];
$params = $request['params'] ?? [];
$id     = $request['id'] ?? null;

// Notifications get no response body
if (!array_key_exists('id', $request)) {
    http_response_code(202);
    exit;
}

switch ($method) {
    case 'initialize':
        $supported = ['2025-06-18', '2025-03-26', '2024-11-05'];
        $asked = $params['protocolVersion'] ?? '';
        adminMcpEmit(['jsonrpc' => '2.0', 'id' => $id, 'result' => [
            'protocolVersion' => in_array($asked, $supported, true) ? $asked : $supported[0],
            'capabilities' => ['tools' => ['listChanged' => false]],
            'serverInfo' => ['name' => 'ZozoCal Restaurant Admin', 'version' => '1.0.0'],
            'instructions' => "You are administering restaurant #{$ctx['restaurant_id']} as {$ctx['email']} "
                . "(role: {$ctx['role']}" . ($ctx['platform_role'] === 'super-admin' ? ', platform super-admin' : '') . "). "
                . 'Dates are YYYY-MM-DD and times HH:MM (24-hour) in the restaurant\'s time zone. '
                . 'List tools accept limit (max 200) and offset.',
        ]]);
        break;

    case 'ping':
        adminMcpEmit(['jsonrpc' => '2.0', 'id' => $id, 'result' => new stdClass()]);
        break;

    case 'tools/list':
        adminMcpEmit(['jsonrpc' => '2.0', 'id' => $id, 'result' => ['tools' => api_mcp_tools($ctx)]]);
        break;

    case 'tools/call':
        $name = $params['name'] ?? '';
        $args = $params['arguments'] ?? [];
        if (!is_array($args)) $args = [];
        try {
            $result = api_run($name, $args, $ctx, 'mcp');
            $isError = false;
            $payload = $result;
        } catch (ServiceError $e) {
            $isError = true;
            $payload = ['error' => $e->getMessage(), 'code' => $e->errorCode];
        } catch (Throwable $e) {
            error_log("Admin MCP {$name} failed: " . $e->getMessage());
            $isError = true;
            $payload = ['error' => 'Internal server error.', 'code' => 'SERVER_ERROR'];
        }
        adminMcpEmit(['jsonrpc' => '2.0', 'id' => $id, 'result' => [
            'isError' => $isError,
            'content' => [['type' => 'text', 'text' => json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE)]],
        ]]);
        break;

    default:
        adminMcpError(-32601, "Method not found: {$method}", $id);
}
