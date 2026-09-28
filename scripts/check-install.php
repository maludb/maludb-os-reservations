<?php
/**
 * Install check — run after installing or moving the app. Safe to run any time
 * (read-only apart from one HTTP request per check).
 *
 *   php scripts/check-install.php [--url=https://example.com] [--web-user=www-data]
 *
 * Prints PASS / WARN / FAIL per check. Exit code 1 if anything FAILs.
 */

$opts = getopt('', ['url::', 'web-user::']);
$root = realpath(__DIR__ . '/..');
$webUser = $opts['web-user'] ?? 'www-data';
$fails = 0;
$warns = 0;

function report(string $level, string $label, string $hint = ''): void
{
    global $fails, $warns;
    if ($level === 'FAIL') $fails++;
    if ($level === 'WARN') $warns++;
    echo str_pad($level, 5) . ' ' . $label . ($hint !== '' ? "\n      → {$hint}" : '') . "\n";
}

echo "ZozoCal install check ({$root})\n\n";

// ── PHP ──────────────────────────────────────────────────
version_compare(PHP_VERSION, '8.1.0', '>=')
    ? report('PASS', 'PHP ' . PHP_VERSION)
    : report('FAIL', 'PHP ' . PHP_VERSION . ' is too old', 'Install PHP 8.3');
foreach (['pdo_pgsql', 'curl', 'mbstring', 'json', 'xml', 'openssl'] as $ext) {
    extension_loaded($ext)
        ? report('PASS', "PHP extension {$ext}")
        : report('FAIL', "PHP extension {$ext} missing", "sudo apt install php8.3-" . ($ext === 'pdo_pgsql' ? 'pgsql' : $ext));
}
is_file("{$root}/vendor/autoload.php")
    ? report('PASS', 'Composer dependencies installed')
    : report('FAIL', 'vendor/ missing', "cd {$root} && composer install --no-dev");

// ── Config ───────────────────────────────────────────────
require_once "{$root}/config/app.php";
$local = "{$root}/config/local.php";
if (is_file($local)) {
    report('PASS', 'config/local.php present');
    if (fileperms($local) & 0004) report('WARN', 'config/local.php is world-readable', "chmod 640 {$local} && sudo chgrp {$webUser} {$local}");
} elseif (getenv('DB_PASS') !== false) {
    report('PASS', 'Configuration from environment variables');
} else {
    report('FAIL', 'config/local.php missing', "cp config/local.example.php config/local.php and fill it in");
}
$appUrl = (string)app_config('APP_URL', '');
$appUrl !== '' && $appUrl !== 'https://example.com'
    ? report('PASS', "APP_URL = {$appUrl}")
    : report('WARN', 'APP_URL not set', 'Set APP_URL in config/local.php (used for Retell voice agent URLs)');
app_config('GOOGLE_CLIENT_ID', '') !== ''
    ? report('PASS', 'Google sign-in configured')
    : report('WARN', 'Google sign-in not configured (optional)');

// ── Database ─────────────────────────────────────────────
$pdo = null;
try {
    require_once "{$root}/config/database.php";
    $pdo = Database::getInstance()->getConnection();
    report('PASS', 'Database connection (' . app_config('DB_USER') . '@' . app_config('DB_HOST') . '/' . app_config('DB_NAME') . ')');
} catch (Throwable $e) {
    report('FAIL', 'Database connection failed', 'Check DB_* in config/local.php and that PostgreSQL is running');
}
if ($pdo) {
    $tables = (int)$pdo->query("SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = 'public' AND table_type = 'BASE TABLE'")->fetchColumn();
    $tables >= 51
        ? report('PASS', "Schema loaded ({$tables} tables)")
        : report('FAIL', "Schema incomplete ({$tables} tables, expected 51)", 'psql -f docs/sql/pg_schema.sql');
    $hasKeyCols = (int)$pdo->query("SELECT COUNT(*) FROM information_schema.columns WHERE table_name = 'api_tokens' AND column_name IN ('name', 'revoked_at', 'last_used_at')")->fetchColumn();
    $hasKeyCols === 3
        ? report('PASS', 'Schema is current (api_tokens has key columns)')
        : report('FAIL', 'Schema is out of date (api_tokens key columns missing)', 'Compare against docs/sql/pg_schema.sql');
    $hasInvite = (int)$pdo->query("SELECT COUNT(*) FROM information_schema.columns WHERE table_name = 'users' AND column_name = 'invite_code_hash'")->fetchColumn();
    $hasInvite === 1
        ? report('PASS', 'Adoption changes loaded (docs/sql/os_adoption.sql)')
        : report('FAIL', 'docs/sql/os_adoption.sql not loaded', 'psql -f docs/sql/os_adoption.sql');
    try {
        $nav = (int)$pdo->query("SELECT COUNT(*) FROM nav_permissions")->fetchColumn();
        $nav > 0
            ? report('PASS', "Sidebar permissions seeded ({$nav} rows)")
            : report('FAIL', 'nav_permissions is empty — the sidebar will be blank', 'psql -f docs/sql/nav_permissions.sql');
        $admins = (int)$pdo->query("SELECT COUNT(*) FROM users WHERE role = 'super-admin' AND is_active = 1")->fetchColumn();
        $admins > 0
            ? report('PASS', "Platform super-admin exists ({$admins})")
            : report('WARN', 'No platform super-admin yet', 'php scripts/create-super-admin.php --email=... --first=... --last=...');
    } catch (Throwable $e) {
        // Missing tables were already reported above
    }
}

// ── Files ────────────────────────────────────────────────
$logs = "{$root}/logs";
$pw = function_exists('posix_getpwnam') ? posix_getpwnam($webUser) : false;
if (!is_dir($logs)) {
    report('FAIL', 'logs/ directory missing', "mkdir {$logs} && sudo chown {$webUser}:{$webUser} {$logs}");
} elseif ($pw) {
    $st = stat($logs);
    $ok = ($st['uid'] === $pw['uid'] && ($st['mode'] & 0200))
       || ($st['gid'] === $pw['gid'] && ($st['mode'] & 0020))
       || ($st['mode'] & 0002);
    $ok ? report('PASS', "logs/ writable by {$webUser}")
        : report('FAIL', "logs/ not writable by {$webUser}", "sudo chgrp -R {$webUser} {$logs} && sudo chmod -R g+w {$logs}");
} else {
    report('WARN', "Web user {$webUser} not found; pass --web-user=");
}
$exposed = array_filter(['info.php', 'test-db.php', 'test-email.php'], fn($f) => is_file("{$root}/html/{$f}"));
$exposed
    ? report('WARN', 'Diagnostic pages are web-accessible: html/' . implode(', html/', $exposed), 'Remove them on a public server (phpinfo and DB details are exposed)')
    : report('PASS', 'No diagnostic pages in the web root');

// ── Cron ─────────────────────────────────────────────────
$cron = (string)@shell_exec('crontab -l 2>/dev/null; cat /etc/cron.d/* 2>/dev/null');
$jobs = ['generate-invoices.php', 'mark-overdue.php', 'send-reminders.php', 'process-voice-messages.php'];
$missing = array_filter($jobs, fn($j) => !str_contains($cron, $j));
$missing
    ? report('WARN', 'Cron jobs not found: ' . implode(', ', $missing), 'Install docs/deploy/crontab (see the install skill)')
    : report('PASS', 'Cron jobs installed');

// ── HTTP ─────────────────────────────────────────────────
$base = rtrim($opts['url'] ?? ($appUrl !== '' && $appUrl !== 'https://example.com' ? $appUrl : 'http://localhost'), '/');
$get = function (string $method, string $path, ?string $body = null) use ($base): array {
    $ch = curl_init($base . $path);
    curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER => true, CURLOPT_TIMEOUT => 15, CURLOPT_CUSTOMREQUEST => $method,
                            CURLOPT_HTTPHEADER => ['Content-Type: application/json']]);
    if ($body !== null) curl_setopt($ch, CURLOPT_POSTFIELDS, $body);
    $out = curl_exec($ch);
    $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    return [$code, (string)$out];
};
[$c] = $get('GET', '/login.php');
$c === 200 ? report('PASS', "{$base}/login.php → 200") : report('FAIL', "{$base}/login.php → {$c}", 'Check the Apache DocumentRoot points at html/ and PHP is enabled');
[$c, $out] = $get('GET', '/api/v1/index.php/openapi.json');
($c === 200 && str_contains($out, '"openapi"'))
    ? report('PASS', 'REST API reachable (openapi.json)')
    : report('FAIL', "REST API openapi.json → {$c}", 'PATH_INFO must be allowed (Apache default for PHP)');
[$c] = $get('POST', '/api/mcp/admin.php', '{"jsonrpc":"2.0","id":1,"method":"tools/list"}');
$c === 401 ? report('PASS', 'Admin MCP server reachable (401 without key)') : report('FAIL', "Admin MCP server → {$c}");

echo "\n" . ($fails ? "{$fails} FAIL, {$warns} WARN" : ($warns ? "OK with {$warns} warning(s)" : 'All checks passed')) . "\n";
exit($fails ? 1 : 0);
