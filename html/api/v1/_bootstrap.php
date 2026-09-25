<?php
/**
 * REST API v1 Bootstrap
 * Shared by all /api/v1/ endpoints.
 * Provides: JSON helpers, token auth, error responses, request parsing.
 */

require_once __DIR__ . '/../../../helpers/db.php';
require_once __DIR__ . '/../../../helpers/restaurant.php';
require_once __DIR__ . '/../../../helpers/api/auth.php';

header('Content-Type: application/json; charset=utf-8');
header('X-Content-Type-Options: nosniff');

// --- JSON helpers ---

function api_success(array $data = [], int $status = 200): never {
    http_response_code($status);
    echo json_encode(array_merge(['success' => true], $data), JSON_UNESCAPED_UNICODE);
    exit;
}

function api_error(string $message, string $code, int $status = 400): never {
    http_response_code($status);
    echo json_encode([
        'success' => false,
        'error'   => $message,
        'code'    => $code,
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

// --- Request helpers ---

function api_method(): string {
    return $_SERVER['REQUEST_METHOD'];
}

function api_require_method(string ...$allowed): void {
    if (!in_array(api_method(), $allowed, true)) {
        api_error('Method not allowed.', 'METHOD_NOT_ALLOWED', 405);
    }
}

function api_json_body(): array {
    $raw = file_get_contents('php://input');
    if ($raw === '' || $raw === false) return [];
    $data = json_decode($raw, true);
    return is_array($data) ? $data : [];
}

function api_query(string $key, $default = null) {
    return $_GET[$key] ?? $default;
}

function api_path_segments(): array {
    $path = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
    // Strip /api/v1/ prefix
    $path = preg_replace('#^/api/v1/?#', '', $path);
    $path = trim($path, '/');
    return $path !== '' ? explode('/', $path) : [];
}

// --- Token auth ---

function api_authenticate(): array {
    $token = api_bearer_token();
    if ($token === '') {
        api_error('Missing or invalid Authorization header.', 'AUTH_REQUIRED', 401);
    }
    $ctx = api_token_context($token, api_restaurant_header());
    if (!$ctx) {
        api_error('Invalid, expired or revoked token, or no access to that restaurant.', 'AUTH_REQUIRED', 401);
    }
    // Keys kept for endpoints written before the registry (todos.php)
    $ctx['restaurant_role'] = $ctx['role'];
    return $ctx;
}

// --- Rate limiting (simple, per-token) ---

function api_rate_limit(string $scope, int $maxPerMinute = 60): void {
    // Lightweight: uses APCu if available, otherwise skip
    if (!function_exists('apcu_fetch')) return;

    $ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
    $key = "api_rl:{$scope}:{$ip}:" . floor(time() / 60);
    $count = apcu_fetch($key);
    if ($count === false) {
        apcu_store($key, 1, 120);
    } elseif ($count >= $maxPerMinute) {
        api_error('Rate limit exceeded. Try again shortly.', 'RATE_LIMITED', 429);
    } else {
        apcu_inc($key);
    }
}
