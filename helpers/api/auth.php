<?php
/**
 * API key authentication shared by the REST API and the admin MCP server.
 *
 * A key (api_tokens row) acts as its owning user in one restaurant with that
 * user's restaurant role. Super-admin keys may act on another restaurant by
 * sending an X-Restaurant-Id header.
 */

require_once __DIR__ . '/../db.php';
require_once __DIR__ . '/../restaurant.php';
require_once __DIR__ . '/../os.php';

/** Bearer token from the Authorization header, or ''. */
function api_bearer_token(): string
{
    // mod_php does not put a Bearer Authorization header into $_SERVER
    $header = $_SERVER['HTTP_AUTHORIZATION'] ?? $_SERVER['REDIRECT_HTTP_AUTHORIZATION'] ?? '';
    if ($header === '' && function_exists('getallheaders')) {
        $headers = array_change_key_case(getallheaders(), CASE_LOWER);
        $header = $headers['authorization'] ?? '';
    }
    return preg_match('/^Bearer\s+(.+)$/i', $header, $m) ? trim($m[1]) : '';
}

/**
 * Resolve a raw token to a caller context, or null if invalid/expired/revoked.
 * Context keys: user_id, email, first_name, last_name, platform_role,
 *               restaurant_id, role, token_id, token_name
 */
function api_token_context(string $token, ?int $restaurantOverride = null): ?array
{
    if ($token === '') return null;

    // Under the operating system a key works only for a user linked to the kernel, in a restaurant the kernel
    // still grants them (a super-admin included) — so a deactivation or revocation there ends the key here.
    $os = os_enabled();
    $stmt = db()->prepare(
        "SELECT t.id AS token_id, t.name AS token_name, t.device_name, t.user_id, t.restaurant_id,
                u.email, u.first_name, u.last_name, u.role AS platform_role, u.is_active,
                ur.role AS restaurant_role
         FROM api_tokens t
         JOIN users u ON u.id = t.user_id
         LEFT JOIN user_restaurants ur ON ur.user_id = t.user_id AND ur.restaurant_id = t.restaurant_id AND ur.is_active = 1"
        . ($os ? " AND ur.source = 'os' AND EXISTS (SELECT 1 FROM restaurants r WHERE r.id = ur.restaurant_id AND r.is_active = 1 AND r.os_scope_id IS NOT NULL)" : "") . "
         WHERE t.token_hash = ? AND t.expires_at > NOW() AND t.revoked_at IS NULL"
        . ($os ? " AND u.os_member_id IS NOT NULL" : "") . "
         LIMIT 1"
    );
    $stmt->execute([hash('sha256', $token)]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$row || !$row['is_active']) return null;

    $isSuper = $row['platform_role'] === 'super-admin';
    $ctx = [
        'user_id'       => (int)$row['user_id'],
        'email'         => $row['email'],
        'first_name'    => $row['first_name'],
        'last_name'     => $row['last_name'],
        'platform_role' => $row['platform_role'],
        'restaurant_id' => (int)$row['restaurant_id'],
        'role'          => $row['restaurant_role'] ?? ($isSuper && !$os ? 'admin' : null),
        'token_id'      => (int)$row['token_id'],
        'token_name'    => $row['token_name'] ?? $row['device_name'] ?? 'login token',
    ];

    // A user removed from (or deactivated in) the restaurant loses access with it
    if ($ctx['role'] === null) return null;

    if ($restaurantOverride && $restaurantOverride !== $ctx['restaurant_id']) {
        if (!$isSuper || !getRestaurant($restaurantOverride)) return null;
        $overrideRole = $os ? getUserRole($ctx['user_id'], $restaurantOverride) : 'admin';
        if ($overrideRole === null) return null;
        $ctx['restaurant_id'] = $restaurantOverride;
        $ctx['role'] = $overrideRole;
    }

    applyRestaurantTimezone($ctx['restaurant_id']);
    db()->prepare("UPDATE api_tokens SET last_used_at = NOW() WHERE id = ?")->execute([$ctx['token_id']]);
    return $ctx;
}

/** Restaurant override from the X-Restaurant-Id header. */
function api_restaurant_header(): ?int
{
    $v = (int)($_SERVER['HTTP_X_RESTAURANT_ID'] ?? 0);
    return $v > 0 ? $v : null;
}

/** Create a key; returns the raw token (shown once). */
function api_create_key(int $userId, int $restaurantId, string $name, int $days = 365): string
{
    $raw = 'zc_' . bin2hex(random_bytes(32));
    db()->prepare(
        "INSERT INTO api_tokens (user_id, restaurant_id, token_hash, name, device_name, expires_at)
         VALUES (?, ?, ?, ?, 'API key', NOW() + (? || ' days')::interval)"
    )->execute([$userId, $restaurantId, hash('sha256', $raw), $name, (string)$days]);
    return $raw;
}
