<?php
/**
 * ZozoCal inside the MaluDB Business OS (docs/os-adoption.md).
 *
 * While OS_ENABLED is on, the kernel signs people in (/sso), owns who they are and which restaurants they
 * hold, and turns each of its sites into a restaurant here. ZozoCal keeps its own tables and links them:
 *   users.os_member_id        — the kernel's member
 *   restaurants.os_scope_id   — the kernel's scope (a site)
 *   user_restaurants.source   — 'os' rows are the kernel's grants, replaced whole on every sign-on and sync
 * With OS_ENABLED off nothing here runs and ZozoCal is the standalone application it always was.
 *
 * The token formats are the kernel's (maludb-os-integration, php-sign-on-kit.md); the verifiers are copied
 * from it unchanged but for names.
 */

require_once __DIR__ . '/db.php';
require_once __DIR__ . '/restaurant.php';
require_once __DIR__ . '/services/_base.php';
require_once __DIR__ . '/../config/app.php';

const OS_ROLES = ['admin', 'manager', 'user'];     // ZozoCal's restaurant roles, highest first
const OS_MANAGED = 'Managed in the operating system.';

function os_enabled(): bool
{
    return in_array(strtolower((string)app_config('OS_ENABLED', '')), ['1', 'true', 'on', 'yes'], true);
}

function os_app_key(): string
{
    return (string)app_config('APP_KEY', 'reservations');
}

/** Where a visitor with no session goes: the kernel's launcher, which can send them straight back. */
function os_launcher_url(): string
{
    return rtrim((string)app_config('OS_LAUNCHER_URL', '/'), '/') . '/?app=' . rawurlencode(os_app_key());
}

/* ---------- Tokens (the kernel mints; ZozoCal only verifies) ---------- */

/** The tenant's ACTION_TOKEN_KEY — the same value as the kernel's. */
function os_action_token_key(): string
{
    $k = (string)app_config('ACTION_TOKEN_KEY', '');
    if (strlen($k) < 32) throw new RuntimeException('ACTION_TOKEN_KEY is not configured.');
    return $k;
}

function os_base64url_decode(string $text): string|false
{
    return base64_decode(strtr($text, '-_', '+/') . str_repeat('=', (4 - strlen($text) % 4) % 4), true);
}

/** [member_id, nonce, expires] for a valid hand-off token bound to this application, else null. */
function os_verify_sso_token(string $token): ?array
{
    $parts = explode('.', $token);
    if (count($parts) !== 5) return null;
    [$mid, $exp, $app, $nonce, $mac] = $parts;
    if (!ctype_digit($mid) || !ctype_digit($exp) || (int)$exp < time() || $app !== os_app_key()
        || !preg_match('/^[a-f0-9]{32}$/', $nonce)) {
        return null;
    }
    $expected = hash_hmac('sha256', 'sso:' . $mid . '.' . $exp . '.' . $app . '.' . $nonce, os_action_token_key());
    return hash_equals($expected, $mac) ? [(int)$mid, $nonce, (int)$exp] : null;
}

/** The signed claims beside the token: base64url(json) . '.' . hmac(base64url text). */
function os_verify_sso_claims(string $signed): ?array
{
    $dot = strrpos($signed, '.');
    if ($dot === false) return null;
    $text = substr($signed, 0, $dot);
    if (!hash_equals(hash_hmac('sha256', $text, os_action_token_key()), substr($signed, $dot + 1))) return null;
    $json = os_base64url_decode($text);
    $claims = $json === false ? null : json_decode($json, true);
    return is_array($claims) ? $claims : null;
}

/** The kernel's sign-out notice: {member}.{issued}.{app}.{hmac}, 120 s. Member id or null. */
function os_verify_logout_notice(string $notice): ?int
{
    $parts = explode('.', $notice);
    if (count($parts) !== 4) return null;
    [$mid, $issued, $app, $mac] = $parts;
    if (!ctype_digit($mid) || !ctype_digit($issued) || $app !== os_app_key() || abs(time() - (int)$issued) > 120) return null;
    $expected = hash_hmac('sha256', 'sso-logout:' . $mid . '.' . $issued . '.' . $app, os_action_token_key());
    return hash_equals($expected, $mac) ? (int)$mid : null;
}

/** The kernel calling as itself (app_roles only): kernel.{exp}.{app}.{nonce}.{hmac over "kernel:exp.app.nonce"}. */
function os_verify_kernel_token(string $token): bool
{
    $parts = explode('.', $token);
    if (count($parts) !== 5 || $parts[0] !== 'kernel' || strlen((string)app_config('ACTION_TOKEN_KEY', '')) < 32) return false;
    [, $exp, $app, $nonce, $mac] = $parts;
    if (!ctype_digit($exp) || (int)$exp < time() || $app !== os_app_key() || !preg_match('/^[a-f0-9]{32}$/', $nonce)) return false;
    return hash_equals(hash_hmac('sha256', 'kernel:' . $exp . '.' . $app . '.' . $nonce, os_action_token_key()), $mac);
}

/* ---------- The session list (a PHP file session cannot be found by member, so every one is listed) ---------- */

function os_session_open(int $memberId, string $sessionId): void
{
    db()->prepare("INSERT INTO member_sessions (session_hash, member_id) VALUES (?, ?)
                   ON CONFLICT (session_hash) DO UPDATE SET member_id = EXCLUDED.member_id, ended_at = NULL, ended_by = NULL, last_seen_at = now()")
        ->execute([hash('sha256', $sessionId), $memberId]);
}

function os_session_end(string $sessionId, string $by): void
{
    db()->prepare("UPDATE member_sessions SET ended_at = now(), ended_by = ? WHERE session_hash = ? AND ended_at IS NULL")
        ->execute([$by, hash('sha256', $sessionId)]);
}

function os_end_member_sessions(int $memberId, string $by): int
{
    $st = db()->prepare("UPDATE member_sessions SET ended_at = now(), ended_by = ? WHERE member_id = ? AND ended_at IS NULL");
    $st->execute([$by, $memberId]);
    return $st->rowCount();
}

/** Out to the launcher: HX-Redirect for an HTMX request, Location otherwise. */
function os_to_launcher(): never
{
    if (session_status() === PHP_SESSION_ACTIVE) $_SESSION = [];
    if (isset($_SERVER['HTTP_HX_REQUEST'])) {
        http_response_code(401);
        header('HX-Redirect: ' . os_launcher_url());
    } else {
        header('Location: ' . os_launcher_url());
    }
    exit;
}

/**
 * Password sign-in, registration, Google, invitations and password reset are closed while the kernel signs
 * people in: each of their pages and handlers calls this first and the visitor goes to the launcher.
 */
function os_close_local_signin(): void
{
    if (!os_enabled()) return;
    if (isset($_SERVER['HTTP_HX_REQUEST'])) {
        http_response_code(401);
        header('HX-Redirect: ' . os_launcher_url());
    } else {
        header('Location: ' . os_launcher_url());
    }
    exit;
}

/**
 * The guard's check under OS_ENABLED, run by requireAuth() on every request: the session is listed, its user is
 * linked to the member who signed on and active, and the current restaurant is still held (else the next one held
 * is chosen). One query on the common path.
 */
function os_guard(): void
{
    $user = $_SESSION['user'] ?? null;
    $memberId = (int)($_SESSION['os_member_id'] ?? 0);
    if ($user === null || $memberId === 0) os_to_launcher();
    $st = db()->prepare(
        "SELECT EXISTS (SELECT 1 FROM member_sessions WHERE session_hash = ? AND ended_at IS NULL) AS listed,
                u.is_active,
                EXISTS (SELECT 1 FROM user_restaurants ur JOIN restaurants r ON r.id = ur.restaurant_id
                        WHERE ur.user_id = u.id AND ur.restaurant_id = ? AND ur.source = 'os' AND ur.is_active = 1
                          AND r.is_active = 1 AND r.os_scope_id IS NOT NULL) AS holds_current
         FROM users u WHERE u.id = ? AND u.os_member_id = ?"
    );
    $st->execute([hash('sha256', session_id()), (int)($_SESSION['current_restaurant_id'] ?? 0), (int)$user['id'], $memberId]);
    $row = $st->fetch(PDO::FETCH_ASSOC);
    if (!$row || !$row['listed'] || !$row['is_active']) {
        os_end_member_sessions($memberId, 'directory');
        os_to_launcher();
    }
    if (!$row['holds_current']) {
        $held = os_held_restaurants((int)$user['id']);
        if ($held === [] || !switchRestaurant((int)$held[0]['id'])) {
            os_end_member_sessions($memberId, 'directory');
            os_to_launcher();
        }
    }
}

/** The restaurants a user holds through the kernel, by name. */
function os_held_restaurants(int $userId): array
{
    $st = db()->prepare(
        "SELECT r.*, ur.role FROM restaurants r
         JOIN user_restaurants ur ON ur.restaurant_id = r.id
         WHERE ur.user_id = ? AND ur.source = 'os' AND ur.is_active = 1 AND r.is_active = 1 AND r.os_scope_id IS NOT NULL
         ORDER BY r.name"
    );
    $st->execute([$userId]);
    return $st->fetchAll(PDO::FETCH_ASSOC);
}

/** The note a people or restaurant screen shows instead of its buttons while the kernel owns them. */
function os_managed_notice(string $id): string
{
    return '<div class="alert alert-info d-flex align-items-center mb-3" id="' . htmlspecialchars($id) . '">'
        . '<i class="feather-info me-2"></i><span>' . OS_MANAGED . ' People, their roles here and the restaurants are '
        . 'granted there. <a class="alert-link" href="' . htmlspecialchars(os_launcher_url()) . '">Open it</a></span></div>';
}

/** Stop a people- or restaurant-changing service while the kernel owns them (screens, REST and MCP alike). */
function os_refuse_if_managed(): void
{
    if (os_enabled()) {
        require_once __DIR__ . '/services/_base.php';
        throw new ServiceError(OS_MANAGED, 'MANAGED_BY_OS', 403);
    }
}

/** What app_roles answers: ZozoCal's roles and rights, from app_roles / app_rights (docs/sql/os_adoption.sql). */
function app_roles_document(): array
{
    $pdo = db();
    $rights = $pdo->query("SELECT right_key, description FROM app_rights ORDER BY sort_order")->fetchAll(PDO::FETCH_ASSOC);
    $roles = $pdo->query("SELECT role_key, name, description, capability, is_admin, array_to_json(rights) AS rights FROM app_roles ORDER BY sort_order")
        ->fetchAll(PDO::FETCH_ASSOC);
    return [
        'schema' => 'os.app-roles/1',
        'rights' => array_map(fn($r) => ['key' => $r['right_key'], 'description' => $r['description']], $rights),
        'roles' => array_map(fn($r) => ['key' => $r['role_key'], 'name' => $r['name'], 'description' => $r['description'],
            'capability' => $r['capability'], 'is_admin' => (bool)$r['is_admin'], 'rights' => json_decode($r['rights'], true)], $roles),
    ];
}

/* ---------- Claims and the change feed → ZozoCal's own tables ---------- */

/** The highest ZozoCal role in a claims or feed scope entry (roles, else role), or null. */
function os_role_of(array $scope): ?string
{
    $roles = is_array($scope['roles'] ?? null) ? $scope['roles'] : [$scope['role'] ?? null];
    foreach (OS_ROLES as $r) {
        if (in_array($r, $roles, true)) return $r;
    }
    return null;
}

/** first and last name from the kernel's display name. */
function os_split_name(string $display): array
{
    $display = trim(preg_replace('/\s+/', ' ', $display));
    $sp = strpos($display, ' ');
    return $sp === false ? [$display, ''] : [substr($display, 0, $sp), substr($display, $sp + 1)];
}

/**
 * The restaurant for a kernel scope, created the first time it is seen — seeded exactly as a new restaurant always
 * is (svc_platform_restaurant_seed). With $full (a feed scopes[] row) its name, time zone, address and open/closed
 * follow the kernel's; a claims entry only names it. A removed scope closes the restaurant and keeps its data.
 */
function os_restaurant_for_scope(PDO $pdo, array $s, bool $full): int
{
    require_once __DIR__ . '/services/platform.php';
    $scopeId = (int)$s['scope_id'];
    $name = trim((string)($s['name'] ?? '')) ?: ('Site ' . $scopeId);
    $tz = (string)($s['timezone'] ?? '');
    if (!in_array($tz, DateTimeZone::listIdentifiers(), true)) $tz = '';
    $active = empty($s['removed_at']) ? 1 : 0;

    $st = $pdo->prepare("SELECT id FROM restaurants WHERE os_scope_id = ?");
    $st->execute([$scopeId]);
    $rid = (int)($st->fetchColumn() ?: 0);
    if ($rid > 0) {
        if ($full) {
            $pdo->prepare("UPDATE restaurants SET name = ?, timezone = COALESCE(NULLIF(?, ''), timezone),
                                  address_line1 = COALESCE(?, address_line1), is_active = ?, updated_at = NOW()
                           WHERE id = ?")
                ->execute([$name, $tz, ($s['address'] ?? '') !== '' ? (string)$s['address'] : null, $active, $rid]);
        }
        return $rid;
    }

    $slug = strtolower(trim(preg_replace('/[^a-zA-Z0-9]+/', '-', $name), '-')) ?: 'site';
    $base = $slug;
    for ($n = 1; ; $n++) {
        $chk = $pdo->prepare("SELECT 1 FROM restaurants WHERE slug = ?");
        $chk->execute([$slug]);
        if (!$chk->fetchColumn()) break;
        $slug = $base . '-' . $n;
    }
    $st = $pdo->prepare(
        "INSERT INTO restaurants (name, slug, timezone, address_line1, is_active, os_scope_id, created_at)
         VALUES (?, ?, ?, ?, ?, ?, NOW()) RETURNING id"
    );
    $st->execute([$name, $slug, $tz ?: 'America/Chicago', ($s['address'] ?? '') !== '' ? (string)$s['address'] : null, $full ? $active : 1, $scopeId]);
    $rid = (int)$st->fetchColumn();
    svc_platform_restaurant_seed($pdo, $rid);
    svc_log($rid, 0, 'create', 'restaurant', $rid, "Operating system: created restaurant {$name} for site scope {$scopeId}");
    return $rid;
}

/** Replace a user's kernel grants with $scopes ([{scope_id, role|roles, …}]); scopes without a ZozoCal role are skipped. */
function os_apply_holding(PDO $pdo, int $userId, array $scopes, bool $createMissing): void
{
    $pdo->prepare("DELETE FROM user_restaurants WHERE user_id = ? AND source = 'os'")->execute([$userId]);
    $ins = $pdo->prepare(
        "INSERT INTO user_restaurants (user_id, restaurant_id, role, is_active, source) VALUES (?, ?, ?, 1, 'os')
         ON CONFLICT (user_id, restaurant_id) DO UPDATE SET role = EXCLUDED.role, is_active = 1, source = 'os'"
    );
    foreach ($scopes as $s) {
        $role = os_role_of($s);
        if ($role === null || empty($s['scope_id'])) continue;
        if ($createMissing) {
            $rid = os_restaurant_for_scope($pdo, $s, false);
        } else {
            $st = $pdo->prepare("SELECT id FROM restaurants WHERE os_scope_id = ?");
            $st->execute([(int)$s['scope_id']]);
            $rid = (int)($st->fetchColumn() ?: 0);
            if ($rid === 0) continue;
        }
        $ins->execute([$userId, $rid, $role]);
    }
}

/**
 * The ZozoCal user for a verified hand-off: found by os_member_id; else an existing user with the claims' email
 * and no link yet is linked, once; else created with an unusable password. Name, email and the platform role
 * follow the claims (the kernel's super-admin is ZozoCal's super-admin; everyone else is a user). Throws
 * RuntimeException('email-in-use') when the email belongs to a user linked to someone else.
 */
function os_link_user(PDO $pdo, array $claims): int
{
    $memberId = (int)$claims['member_id'];
    $email = strtolower(trim((string)($claims['email'] ?? '')));
    [$first, $last] = os_split_name((string)($claims['display_name'] ?? ''));
    if ($first === '') $first = 'Member';
    $role = ($claims['business_role'] ?? '') === 'super_admin' ? 'super-admin' : 'user';

    $st = $pdo->prepare("SELECT id FROM users WHERE os_member_id = ?");
    $st->execute([$memberId]);
    $userId = (int)($st->fetchColumn() ?: 0);

    if ($userId === 0 && $email !== '') {
        $st = $pdo->prepare("SELECT id, os_member_id FROM users WHERE lower(email) = ?");
        $st->execute([$email]);
        $existing = $st->fetch(PDO::FETCH_ASSOC);
        if ($existing && $existing['os_member_id'] !== null) throw new RuntimeException('email-in-use');
        if ($existing) {
            $userId = (int)$existing['id'];
            $pdo->prepare("UPDATE users SET os_member_id = ? WHERE id = ?")->execute([$memberId, $userId]);
            svc_log(0, $userId, 'link', 'user', $userId, "Operating system: linked this user to member {$memberId} by email");
        }
    }
    if ($userId === 0) {
        if ($email === '') $email = 'member-' . $memberId . '@os.invalid';
        $st = $pdo->prepare(
            "INSERT INTO users (first_name, last_name, email, password_hash, auth_provider, role, is_platform_admin, is_active, os_member_id)
             VALUES (?, ?, ?, '!OS', 'os', ?, 0, 1, ?) RETURNING id"
        );
        $st->execute([$first, $last, $email, $role, $memberId]);
        $userId = (int)$st->fetchColumn();
        svc_log(0, $userId, 'create', 'user', $userId, "Operating system: created this user for member {$memberId}");
    }

    // The email follows the kernel's unless another user here already has it.
    $pdo->prepare(
        "UPDATE users SET first_name = ?, last_name = ?, role = ?, is_active = 1,
                email = CASE WHEN ? <> '' AND NOT EXISTS (SELECT 1 FROM users o WHERE lower(o.email) = ? AND o.id <> users.id) THEN ? ELSE email END,
                updated_at = NOW()
         WHERE id = ?"
    )->execute([$first, $last, $role, $email, $email, $email, $userId]);
    return $userId;
}

/** A GET against the kernel's internal port with the application token. Null when unreachable or refused. */
function os_kernel_get(string $path): ?array
{
    $ch = curl_init(rtrim((string)app_config('OS_INTERNAL_URL', 'http://127.0.0.1:8080'), '/') . $path);
    curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER => true, CURLOPT_CONNECTTIMEOUT => 3, CURLOPT_TIMEOUT => 15,
        CURLOPT_HTTPHEADER => ['Authorization: Bearer ' . app_config('OS_APPLICATION_TOKEN', ''), 'Accept: application/json']]);
    $body = curl_exec($ch);
    $code = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    return $body !== false && $code === 200 ? (json_decode((string)$body, true) ?: null) : null;
}

/**
 * Apply one change-feed document (os.directory-changes/1), or a scopes document (os.directory-scopes/1), in the
 * kernel's order: members[], then scopes[], then access[]. Answers counts. Members unknown here are never created
 * from the feed (the first sign-on makes them); departments are not used by ZozoCal.
 */
function os_apply_feed(PDO $pdo, array $doc): array
{
    $n = ['members' => 0, 'scopes' => 0, 'access' => 0, 'sessions_ended' => 0];
    $linked = $pdo->prepare("SELECT id FROM users WHERE os_member_id = ?");

    foreach (is_array($doc['members'] ?? null) ? $doc['members'] : [] as $m) {
        $linked->execute([(int)$m['id']]);
        $userId = (int)($linked->fetchColumn() ?: 0);
        if ($userId === 0) continue;
        [$first, $last] = os_split_name((string)($m['display_name'] ?? ''));
        $active = ($m['status'] ?? 'active') === 'active' ? 1 : 0;
        $email = strtolower(trim((string)($m['email'] ?? '')));
        $pdo->prepare(
            "UPDATE users SET first_name = COALESCE(NULLIF(?, ''), first_name), last_name = ?, is_active = ?,
                    role = CASE WHEN ? = 'super_admin' THEN 'super-admin' ELSE 'user' END,
                    email = CASE WHEN ? <> '' AND NOT EXISTS (SELECT 1 FROM users o WHERE lower(o.email) = ? AND o.id <> users.id) THEN ? ELSE email END,
                    updated_at = NOW()
             WHERE id = ?"
        )->execute([$first, $last, $active, (string)($m['business_role'] ?? 'user'), $email, $email, $email, $userId]);
        if (!$active) $n['sessions_ended'] += os_end_member_sessions((int)$m['id'], 'directory');
        $n['members']++;
    }

    foreach (is_array($doc['scopes'] ?? null) ? $doc['scopes'] : [] as $s) {
        if (($s['kind'] ?? 'location') !== 'location' || empty($s['scope_id'])) continue;
        $rid = os_restaurant_for_scope($pdo, $s, true);
        if (!empty($s['removed_at'])) {
            $pdo->prepare("DELETE FROM user_restaurants WHERE restaurant_id = ? AND source = 'os'")->execute([$rid]);
        }
        $n['scopes']++;
    }

    foreach (is_array($doc['access'] ?? null) ? $doc['access'] : [] as $a) {
        $linked->execute([(int)$a['member_id']]);
        $userId = (int)($linked->fetchColumn() ?: 0);
        if ($userId === 0) continue;
        $holds = ($a['capability'] ?? null) !== null;
        os_apply_holding($pdo, $userId, $holds ? (array)($a['scopes'] ?? []) : [], false);
        if (!$holds) $n['sessions_ended'] += os_end_member_sessions((int)$a['member_id'], 'directory');
        $n['access']++;
    }
    return $n;
}
