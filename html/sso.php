<?php
/**
 * /sso — the Business OS kernel's hand-off (docs/os-adoption.md; Apache maps /sso here).
 *
 * The kernel's launcher sends ?token=&claims=. In order: the token (signature, expiry, this application),
 * the claims (signature, same member, active, a capability), the nonce (single use); then, in one transaction,
 * the user is linked or created and their restaurants replaced from the claims; then ZozoCal's own login_user()
 * fills the session exactly as a password sign-in does, the chosen restaurant is opened and the session listed.
 * Any failure answers the same page, which never says why; the reason goes to the activity log.
 */
require_once __DIR__ . '/../helpers/auth.php';

header('Cache-Control: no-store');

function sso_refuse(string $reason, ?int $memberId = null): never
{
    try {
        svc_log(0, 0, 'sign_on_refused', 'member', $memberId, "Operating system sign-on refused: {$reason}");
    } catch (Throwable $e) {
        error_log('sso refuse log: ' . $e->getMessage());
    }
    http_response_code(403);
    $launcher = htmlspecialchars(os_launcher_url());
    echo <<<HTML
<!DOCTYPE html>
<html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
<title>Sign-on link expired</title><link rel="stylesheet" href="/assets/css/bootstrap.min.css"></head>
<body class="bg-light" id="sso-refused-body"><div class="container py-5" id="sso-refused-container" style="max-width:32rem">
<div class="card shadow-sm" id="sso-refused-card"><div class="card-body p-4" id="sso-refused-card-body">
<h1 class="h4 mb-3" id="sso-refused-title">This sign-on link has expired</h1>
<p class="text-muted" id="sso-refused-text">Open ZozoCal from the launcher again.</p>
<a class="btn btn-primary" href="{$launcher}" id="sso-refused-launcher">Go to the launcher</a>
</div></div></div></body></html>
HTML;
    exit;
}

if (!os_enabled()) {
    http_response_code(404);
    exit;
}

$token = (string)($_GET['token'] ?? '');
$verified = $token === '' ? null : os_verify_sso_token($token);
if ($verified === null) sso_refuse('token');
[$memberId, $nonce, $expires] = $verified;

$claimsRaw = (string)($_GET['claims'] ?? '');
$claims = $claimsRaw === '' ? null : os_verify_sso_claims($claimsRaw);
if ($claims === null || (int)($claims['member_id'] ?? 0) !== $memberId) sso_refuse('claims', $memberId);
if (($claims['status'] ?? 'active') !== 'active') sso_refuse('status', $memberId);
if (!in_array($claims['capability'] ?? null, ['read', 'write', 'admin'], true)) sso_refuse('capability', $memberId);
$scopes = array_values(array_filter(is_array($claims['scopes'] ?? null) ? $claims['scopes'] : [],
    fn($s) => is_array($s) && !empty($s['scope_id']) && os_role_of($s) !== null));
if ($scopes === []) sso_refuse('no-restaurant', $memberId);

$pdo = db();
$claimed = $pdo->prepare("INSERT INTO sso_nonces (nonce, member_id, expires_at) VALUES (?, ?, to_timestamp(?)) ON CONFLICT (nonce) DO NOTHING");
$claimed->execute([$nonce, $memberId, $expires]);
if ($claimed->rowCount() !== 1) sso_refuse('replay', $memberId);
$pdo->exec("DELETE FROM sso_nonces WHERE expires_at < now() - interval '1 hour'");

$pdo->beginTransaction();
try {
    $userId = os_link_user($pdo, $claims + ['member_id' => $memberId]);
    os_apply_holding($pdo, $userId, $scopes, true);
    $pdo->commit();
} catch (Throwable $e) {
    $pdo->rollBack();
    error_log('sso link: ' . $e->getMessage());
    sso_refuse($e->getMessage() === 'email-in-use' ? 'email-in-use' : 'link', $memberId);
}

// ZozoCal's own sign-in, then the restaurant chosen on the launcher (or the first one held)
$st = $pdo->prepare("SELECT * FROM users WHERE id = ?");
$st->execute([$userId]);
$user = $st->fetch(PDO::FETCH_ASSOC);
login_user($user, false);
$_SESSION['os_member_id'] = $memberId;

$held = os_held_restaurants($userId);
$chosen = null;
if (isset($claims['scope'])) {
    $st = $pdo->prepare("SELECT id FROM restaurants WHERE os_scope_id = ?");
    $st->execute([(int)$claims['scope']]);
    $chosen = (int)($st->fetchColumn() ?: 0);
}
$ids = array_map(fn($r) => (int)$r['id'], $held);
if (!in_array($chosen, $ids, true)) $chosen = $ids[0] ?? null;
if ($chosen === null || !switchRestaurant($chosen)) sso_refuse('no-restaurant', $memberId);

os_session_open($memberId, session_id());
svc_log($chosen, $userId, 'sign_on', 'user', $userId,
    "Signed on through the operating system (member {$memberId}, capability {$claims['capability']})");
header('Location: /app.php', true, 302);
exit;
