<?php
/**
 * API operation registry
 *
 * Every agent-callable action is declared once with api_op() in helpers/api/ops/*.php.
 * The REST router (/api/v1/index.php) and the admin MCP server (/api/mcp/admin.php)
 * are both generated from this registry, so the two surfaces always match.
 *
 * Operation definition keys:
 *   description  What the operation does (shown to agents)
 *   role         Minimum role: staff | manager | admin | super-admin
 *   method       GET | POST | PATCH | PUT | DELETE
 *   path         REST path under /api/v1/index.php, e.g. 'reservations/{id}/status'
 *   input        JSON-schema properties (path params are added automatically)
 *   required     Required input names
 *   handler      function(array $input, array $ctx): array
 *   secret       Input names never written to the audit log (optional)
 */

require_once __DIR__ . '/../services/_base.php';

$GLOBALS['API_OPS'] = [];

const API_ROLE_RANK = ['staff' => 1, 'user' => 1, 'manager' => 2, 'admin' => 3];

function api_op(string $name, array $def): void
{
    $def['name'] = $name;
    $def['method'] = strtoupper($def['method'] ?? 'GET');
    $def['input'] = $def['input'] ?? [];
    $def['required'] = $def['required'] ?? [];
    $def['secret'] = $def['secret'] ?? [];
    $def['role'] = $def['role'] ?? 'staff';

    // Path params become required integer/string inputs
    if (preg_match_all('/\{(\w+)\}/', $def['path'], $m)) {
        foreach ($m[1] as $p) {
            $def['input'][$p] = $def['input'][$p] ?? ['type' => 'integer', 'description' => "ID of the " . rtrim(explode('/', $def['path'])[0], 's')];
            if (!in_array($p, $def['required'], true)) $def['required'][] = $p;
        }
    }
    $GLOBALS['API_OPS'][$name] = $def;
}

/** Load every ops file once. */
function api_ops(): array
{
    static $loaded = false;
    if (!$loaded) {
        foreach (glob(__DIR__ . '/ops/*.php') as $file) {
            require_once $file;
        }
        $loaded = true;
    }
    return $GLOBALS['API_OPS'];
}

/** Can this caller use this operation? */
function api_allowed(array $op, array $ctx): bool
{
    if (($ctx['platform_role'] ?? '') === 'super-admin') return true;
    if ($op['role'] === 'super-admin') return false;
    return (API_ROLE_RANK[$ctx['role'] ?? ''] ?? 0) >= (API_ROLE_RANK[$op['role']] ?? 99);
}

/** Operations visible to this caller. */
function api_ops_for(array $ctx): array
{
    return array_filter(api_ops(), fn($op) => api_allowed($op, $ctx));
}

/** Find the operation for a REST method + path. Returns [op, pathParams] or null. */
function api_match_route(string $method, string $path): ?array
{
    $path = trim($path, '/');
    foreach (api_ops() as $op) {
        if ($op['method'] !== $method) continue;
        $regex = '#^' . preg_replace('/\\\{(\w+)\\\}/', '(?P<$1>[^/]+)', preg_quote($op['path'], '#')) . '$#';
        if (preg_match($regex, $path, $m)) {
            return [$op, array_filter($m, 'is_string', ARRAY_FILTER_USE_KEY)];
        }
    }
    return null;
}

/** Coerce and check input against the operation's schema. */
function api_validate_input(array $op, array $input): array
{
    $clean = [];
    foreach ($op['input'] as $key => $schema) {
        if (!array_key_exists($key, $input) || $input[$key] === null) continue;
        $v = $input[$key];
        switch ($schema['type'] ?? 'string') {
            case 'integer':
                if (!is_numeric($v) || (int)$v != $v) svc_fail("{$key} must be an integer.", $key);
                $v = (int)$v;
                break;
            case 'number':
                if (!is_numeric($v)) svc_fail("{$key} must be a number.", $key);
                $v = (float)$v;
                break;
            case 'boolean':
                if (is_string($v)) $v = in_array(strtolower($v), ['1', 'true', 'yes', 'on'], true);
                $v = (bool)$v;
                break;
            case 'array':
            case 'object':
                if (!is_array($v)) svc_fail("{$key} must be an {$schema['type']}.", $key);
                break;
            default:
                if (!is_scalar($v)) svc_fail("{$key} must be a string.", $key);
                $v = (string)$v;
        }
        if (isset($schema['enum']) && !in_array($v, $schema['enum'], true)) {
            svc_fail("{$key} must be one of: " . implode(', ', $schema['enum']) . '.', $key);
        }
        $clean[$key] = $v;
    }
    foreach ($op['required'] as $key) {
        if (!array_key_exists($key, $clean) || $clean[$key] === '') svc_fail("{$key} is required.", $key);
    }
    return $clean;
}

/**
 * Run an operation for an authenticated caller.
 * $channel is 'rest' or 'mcp'. Throws ServiceError on any failure.
 */
function api_run(string $name, array $input, array $ctx, string $channel): array
{
    $op = api_ops()[$name] ?? null;
    if (!$op) throw new ServiceError("Unknown operation: {$name}", 'NOT_FOUND', 404);
    if (!api_allowed($op, $ctx)) {
        throw new ServiceError("This operation requires the {$op['role']} role.", 'FORBIDDEN', 403);
    }
    $clean = api_validate_input($op, $input);
    $result = ($op['handler'])($clean, $ctx);

    if ($op['method'] !== 'GET') {
        $logged = array_diff_key($clean, array_flip($op['secret']));
        svc_log(
            (int)($ctx['restaurant_id'] ?? 0), (int)$ctx['user_id'], 'api_call', 'api', null,
            strtoupper($channel) . " {$name} via key \"" . ($ctx['token_name'] ?? 'login token') . "\"",
            null, $logged
        );
    }
    return $result;
}

/** MCP tool definitions for this caller. */
function api_mcp_tools(array $ctx): array
{
    $tools = [];
    foreach (api_ops_for($ctx) as $op) {
        $schema = ['type' => 'object', 'properties' => (object)$op['input']];
        if ($op['required']) $schema['required'] = $op['required'];
        $tools[] = [
            'name' => $op['name'],
            'description' => $op['description'] . " (role: {$op['role']})",
            'inputSchema' => $schema,
        ];
    }
    return $tools;
}

/** OpenAPI 3 document for the whole registry. */
function api_openapi(string $serverUrl): array
{
    $paths = [];
    foreach (api_ops() as $op) {
        $params = [];
        preg_match_all('/\{(\w+)\}/', $op['path'], $m);
        $pathParams = $m[1];
        $body = [];
        foreach ($op['input'] as $key => $schema) {
            if (in_array($key, $pathParams, true)) {
                $params[] = ['name' => $key, 'in' => 'path', 'required' => true, 'schema' => $schema];
            } elseif ($op['method'] === 'GET' || $op['method'] === 'DELETE') {
                $params[] = ['name' => $key, 'in' => 'query', 'required' => in_array($key, $op['required'], true), 'schema' => $schema];
            } else {
                $body[$key] = $schema;
            }
        }
        $entry = [
            'operationId' => $op['name'],
            'summary' => $op['description'],
            'tags' => [explode('/', $op['path'])[0]],
            'x-required-role' => $op['role'],
            'responses' => ['200' => ['description' => 'Success: {"success": true, "data": {...}}'],
                            '4XX' => ['description' => 'Error: {"success": false, "error": "...", "code": "..."}']],
        ];
        if ($params) $entry['parameters'] = $params;
        if ($body) {
            $bodySchema = ['type' => 'object', 'properties' => $body];
            $req = array_values(array_intersect($op['required'], array_keys($body)));
            if ($req) $bodySchema['required'] = $req;
            $entry['requestBody'] = ['required' => true, 'content' => ['application/json' => ['schema' => $bodySchema]]];
        }
        $paths['/' . $op['path']][strtolower($op['method'])] = $entry;
    }
    ksort($paths);
    return [
        'openapi' => '3.0.3',
        'info' => ['title' => 'ZozoCal Restaurant API', 'version' => '1.0.0',
                   'description' => 'Every operation is also available as a tool on the admin MCP server (/api/mcp/admin.php).'],
        'servers' => [['url' => $serverUrl]],
        'components' => ['securitySchemes' => ['bearer' => ['type' => 'http', 'scheme' => 'bearer']]],
        'security' => [['bearer' => []]],
        'paths' => $paths,
    ];
}
