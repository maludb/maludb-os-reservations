<?php
/**
 * Proofs of the OS adoption and its security fixes — FOR A SCRATCH COPY ONLY: they write rows (users,
 * restaurants, settings, keys), set Retell/Twilio keys and flip OS_ENABLED in config/local.php.
 *
 * A scratch copy: an empty database loaded from docs/sql/pg_schema.sql, nav_permissions.sql, os_adoption.sql;
 * config/local.php pointing at it with APP_URL http://127.0.0.1:8190, EMAIL_WEBHOOK_SECRET 'proof-email-secret',
 * 'OS_ENABLED'=>'0', APP_KEY 'reservations', a development ACTION_TOKEN_KEY (openssl rand -hex 32) and
 * OS_LAUNCHER_URL https://app.example.invalid/; the owner from
 *   echo 'Test1234Aa' | php scripts/create-super-admin.php --email=owner@example.invalid --first=Test --last=Owner \
 *        --restaurant="Test Bistro" --slug=test-bistro --timezone=America/Chicago --password-stdin
 * and the site served with  php -S 127.0.0.1:8190 -t html. Then  php tests/os-adoption/<file>.php  — each ends
 * "all passed" or with the count failed. os-sign-on.php expects no OS rows yet (a fresh copy).
 */
chdir(dirname(__DIR__, 2));
require_once 'helpers/api/auth.php';
const BASE = 'http://127.0.0.1:8190';
$GLOBALS['fails'] = 0;
function ok(bool $c, string $l): void { if (!$c) $GLOBALS['fails']++; echo ($c ? "  ok   " : "  FAIL ") . "$l\n"; }
function req(string $method, string $path, array $opt = []): array {
    $ch = curl_init(BASE . $path);
    $h = $opt['headers'] ?? [];
    curl_setopt_array($ch, [CURLOPT_CUSTOMREQUEST => $method, CURLOPT_RETURNTRANSFER => true, CURLOPT_HEADER => true,
        CURLOPT_HTTPHEADER => $h, CURLOPT_COOKIEJAR => $opt['jar'] ?? '/dev/null', CURLOPT_COOKIEFILE => $opt['jar'] ?? '/dev/null']);
    if (isset($opt['form'])) curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($opt['form']));
    if (isset($opt['json'])) { curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($opt['json'])); $h[] = 'Content-Type: application/json'; curl_setopt($ch, CURLOPT_HTTPHEADER, $h); }
    if (isset($opt['raw'])) curl_setopt($ch, CURLOPT_POSTFIELDS, $opt['raw']);
    $r = curl_exec($ch); $code = curl_getinfo($ch, CURLINFO_HTTP_CODE); $hs = curl_getinfo($ch, CURLINFO_HEADER_SIZE);
    return ['code' => $code, 'headers' => substr($r, 0, $hs), 'body' => substr($r, $hs)];
}
function csrf_from(string $html): string { preg_match('/name="csrf_token" value="([^"]+)"/', $html, $m); return $m[1] ?? ''; }
function jar(): string { return tempnam(sys_get_temp_dir(), 'zjar'); }
function finish(): void { echo $GLOBALS['fails'] ? "{$GLOBALS['fails']} FAILED\n" : "all passed\n"; exit($GLOBALS['fails'] ? 1 : 0); }
function login_as(string $email, string $pw): string {
    $j = jar(); $p = req('GET', '/login.php', ['jar' => $j]);
    req('POST', '/partials/auth/login.php', ['jar' => $j, 'form' => ['csrf_token' => csrf_from($p['body']), 'email' => $email, 'password' => $pw]]);
    return $j;
}
function page_csrf(string $j): string { preg_match('/name="csrf-token" content="([^"]+)"/', req('GET', '/app.php', ['jar' => $j])['body'], $m); return $m[1] ?? ''; }
function set_os(bool $on): void {
    $p = dirname(__DIR__, 2) . '/config/local.php';
    $s = file_get_contents($p);
    $s = preg_replace("/'OS_ENABLED'=>'[01]'/", "'OS_ENABLED'=>'" . ($on ? '1' : '0') . "'", $s);
    file_put_contents($p, $s);
}
function devkey(): string { return (require dirname(__DIR__, 2) . '/config/local.php')['ACTION_TOKEN_KEY']; }
function mint_handoff(int $member, array $claims, ?int $scope = null, string $app = 'reservations', int $ttl = 60, ?string $key = null): string {
    $key ??= devkey();
    $payload = $member . '.' . (time() + $ttl) . '.' . $app . '.' . bin2hex(random_bytes(16));
    $token = $payload . '.' . hash_hmac('sha256', 'sso:' . $payload, $key);
    $claims = $claims + ['member_id' => $member]; $claims['scope'] = $scope;
    $text = rtrim(strtr(base64_encode(json_encode($claims)), '+/', '-_'), '=');
    return '/sso.php?' . http_build_query(['token' => $token, 'claims' => $text . '.' . hash_hmac('sha256', $text, $key)]);
}
function fixture(): array { return json_decode(file_get_contents(dirname(__DIR__, 2) . '/scripts/os-dev-directory.json'), true); }
function kernel_token(string $app = 'reservations'): string {
    $payload = (time() + 60) . '.' . $app . '.' . bin2hex(random_bytes(16));
    return 'kernel.' . $payload . '.' . hash_hmac('sha256', 'kernel:' . $payload, devkey());
}
function logout_notice(int $member, string $app = 'reservations'): string {
    $payload = $member . '.' . time() . '.' . $app;
    return $payload . '.' . hash_hmac('sha256', 'sso-logout:' . $payload, devkey());
}
function sync_with(array $doc): string {
    $f = tempnam(sys_get_temp_dir(), 'fx'); file_put_contents($f, json_encode($doc));
    $out = shell_exec('php ' . dirname(__DIR__, 2) . '/scripts/os-directory-sync.php --from-file ' . escapeshellarg($f) . ' 2>&1');
    unlink($f); return trim((string)$out);
}
function location(array $r): string { return preg_match('/^Location: (.+)$/mi', $r['headers'], $m) ? trim($m[1]) : ''; }
