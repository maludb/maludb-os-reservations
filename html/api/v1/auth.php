<?php
/**
 * POST /api/v1/auth.php?action=login   — Authenticate, return bearer token (body: email, password, optional restaurant_id)
 * POST /api/v1/auth.php?action=logout  — Revoke current token
 */

require_once __DIR__ . '/_bootstrap.php';

api_require_method('POST');
$action = api_query('action', '');

if ($action === 'login') {
    require_once __DIR__ . '/../../../helpers/os.php';
    if (os_enabled()) {
        api_error('Password sign-in is closed: people sign in through the operating system. Use an API key (Settings → API Keys).', 'FORBIDDEN', 403);
    }
    api_rate_limit('auth', 10);
    handleLogin();
} elseif ($action === 'logout') {
    handleLogout();
} else {
    api_error('Unknown action. Use ?action=login or ?action=logout.', 'VALIDATION_ERROR', 400);
}

// --- Login ---
function handleLogin(): void {
    $body = api_json_body();
    $email    = trim($body['email'] ?? '');
    $password = $body['password'] ?? '';
    $device   = trim($body['device_name'] ?? 'Mobile App');

    if ($email === '' || $password === '') {
        api_error('Email and password are required.', 'VALIDATION_ERROR', 400);
    }

    $pdo = db();

    // Look up user
    $stmt = $pdo->prepare("SELECT * FROM users WHERE email = ? AND is_active = 1 LIMIT 1");
    $stmt->execute([$email]);
    $user = $stmt->fetch();

    if (!$user || !password_verify($password, $user['password_hash'])) {
        api_error('Invalid email or password.', 'AUTH_REQUIRED', 401);
    }

    // Get their restaurant assignment (optionally a specific one)
    $wantRestaurant = (int)($body['restaurant_id'] ?? 0);
    if ($wantRestaurant > 0) {
        $stmt = $pdo->prepare("SELECT restaurant_id, role FROM user_restaurants WHERE user_id = ? AND restaurant_id = ?");
        $stmt->execute([$user['id'], $wantRestaurant]);
    } else {
        $stmt = $pdo->prepare("SELECT restaurant_id, role FROM user_restaurants WHERE user_id = ? ORDER BY restaurant_id LIMIT 1");
        $stmt->execute([$user['id']]);
    }
    $ur = $stmt->fetch();
    if (!$ur && $wantRestaurant > 0 && $user['role'] === 'super-admin' && getRestaurant($wantRestaurant)) {
        $ur = ['restaurant_id' => $wantRestaurant, 'role' => 'admin'];
    }

    if (!$ur) {
        api_error('No restaurant assigned to this account.', 'FORBIDDEN', 403);
    }

    // Generate token
    $rawToken = bin2hex(random_bytes(32)); // 64 hex chars
    $tokenHash = hash('sha256', $rawToken);
    $expiresAt = date('Y-m-d H:i:s', strtotime('+90 days'));

    $stmt = $pdo->prepare(
        "INSERT INTO api_tokens (user_id, restaurant_id, token_hash, device_name, expires_at)
         VALUES (?, ?, ?, ?, ?)"
    );
    $stmt->execute([$user['id'], $ur['restaurant_id'], $tokenHash, $device, $expiresAt]);

    api_success([
        'token' => $rawToken,
        'expires_at' => $expiresAt,
        'user' => [
            'id'    => (int)$user['id'],
            'name'  => trim($user['first_name'] . ' ' . $user['last_name']),
            'email' => $user['email'],
            'role'  => $user['role'],
        ],
        'restaurant_id'   => (int)$ur['restaurant_id'],
        'restaurant_role'  => $ur['role'],
    ]);
}

// --- Logout ---
function handleLogout(): void {
    $token = api_bearer_token();
    if ($token === '') {
        api_error('Missing Authorization header.', 'AUTH_REQUIRED', 401);
    }

    $pdo = db();
    $stmt = $pdo->prepare("DELETE FROM api_tokens WHERE token_hash = ?");
    $stmt->execute([hash('sha256', $token)]);

    api_success(['message' => 'Logged out.']);
}
