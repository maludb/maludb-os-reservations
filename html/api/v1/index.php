<?php
/**
 * REST API v1 router — every operation in the registry (helpers/api/ops/*.php)
 *
 *   GET  /api/v1/index.php/openapi.json         OpenAPI description (no auth)
 *   GET  /api/v1/index.php/operations           Operations this key may call
 *   ANY  /api/v1/index.php/<path>               Run the matching operation
 *
 * Auth: Authorization: Bearer <api key>. Super-admin keys may add X-Restaurant-Id.
 * Input: path params, query string, and a JSON body (POST/PATCH/PUT).
 */

require_once __DIR__ . '/_bootstrap.php';
require_once __DIR__ . '/../../../helpers/api/registry.php';

$path   = trim($_SERVER['PATH_INFO'] ?? '', '/');
$method = api_method();

if ($path === 'openapi.json' && $method === 'GET') {
    $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
    echo json_encode(api_openapi("{$scheme}://{$_SERVER['HTTP_HOST']}/api/v1/index.php"), JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    exit;
}

$ctx = api_authenticate();
api_rate_limit('v1', 300);

if ($path === 'operations' && $method === 'GET') {
    $ops = array_map(fn($op) => [
        'name' => $op['name'], 'method' => $op['method'], 'path' => $op['path'],
        'role' => $op['role'], 'description' => $op['description'],
    ], array_values(api_ops_for($ctx)));
    api_success(['data' => ['restaurant_id' => $ctx['restaurant_id'], 'role' => $ctx['role'], 'operations' => $ops]]);
}

$match = api_match_route($method, $path);
if (!$match) {
    api_error("No operation for {$method} /{$path}. See /api/v1/index.php/openapi.json.", 'NOT_FOUND', 404);
}
[$op, $pathParams] = $match;

$input = array_merge($_GET, in_array($method, ['POST', 'PATCH', 'PUT', 'DELETE'], true) ? api_json_body() : [], $pathParams);

try {
    $result = api_run($op['name'], $input, $ctx, 'rest');
    api_success(['data' => $result], $method === 'POST' ? 201 : 200);
} catch (ServiceError $e) {
    api_error($e->getMessage(), $e->errorCode, $e->status);
} catch (Throwable $e) {
    error_log("API {$op['name']} failed: " . $e->getMessage());
    api_error('Internal server error.', 'SERVER_ERROR', 500);
}
