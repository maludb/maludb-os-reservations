<?php
/**
 * Development only: print a /sso URL signed exactly as the Business OS kernel signs one, from the claims in
 * scripts/os-dev-directory.json — for proving the sign-on without a kernel (maludb-os-integration,
 * testing-without-a-kernel.md). Needs a development ACTION_TOKEN_KEY (openssl rand -hex 32); the installer
 * replaces it with the kernel's, so a development token never opens a real installation.
 *
 *   php scripts/os-dev-handoff.php <member_id> [scope_id] [--app=<key>] [--notice]
 * --notice prints a sign-out notice for the member instead (POST it as notice= to /sso/logout).
 */
if (PHP_SAPI !== 'cli') exit(1);
require_once __DIR__ . '/../helpers/os.php';

$args = array_values(array_filter(array_slice($argv, 1), fn($a) => !str_starts_with($a, '--')));
$opts = getopt('', ['app:', 'notice']);
$member = (int)($args[0] ?? 1);
$scope = isset($args[1]) ? (int)$args[1] : null;
$app = (string)($opts['app'] ?? os_app_key());
$key = os_action_token_key();

if (isset($opts['notice'])) {
    $payload = $member . '.' . time() . '.' . $app;
    echo $payload . '.' . hash_hmac('sha256', 'sso-logout:' . $payload, $key) . "\n";
    exit;
}
$payload = $member . '.' . (time() + 60) . '.' . $app . '.' . bin2hex(random_bytes(16));
$token = $payload . '.' . hash_hmac('sha256', 'sso:' . $payload, $key);
$fixture = json_decode((string)file_get_contents(__DIR__ . '/os-dev-directory.json'), true);
$claims = ($fixture['claims'][(string)$member] ?? []) + ['member_id' => $member, 'scope' => $scope];
$claims['scope'] = $scope;
$text = rtrim(strtr(base64_encode(json_encode($claims)), '+/', '-_'), '=');
echo rtrim(app_url(), '/') . '/sso?' . http_build_query(['token' => $token, 'claims' => $text . '.' . hash_hmac('sha256', $text, $key)]) . "\n";
