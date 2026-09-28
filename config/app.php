<?php
/**
 * Per-server configuration loader
 *
 * Values come from, in order:
 *   1. an environment variable of the same name (e.g. set in the Apache vhost with SetEnv)
 *   2. config/.env (KEY=value lines; written by the Business OS installer; git-ignored)
 *   3. config/local.php (git-ignored; copy config/local.example.php to create it)
 *   4. the default passed by the caller
 * DB_PASSWORD (the installer's name) stands in for DB_PASS.
 */

function app_config(string $key, $default = null)
{
    static $local = null, $dotenv = null;
    if ($local === null) {
        $file = __DIR__ . '/local.php';
        $local = is_file($file) ? (require $file) : [];
        if (!is_array($local)) $local = [];
        $dotenv = [];
        foreach (is_readable(__DIR__ . '/.env') ? file(__DIR__ . '/.env', FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) : [] as $line) {
            if (preg_match('/^([A-Z][A-Z0-9_]*)=(.*)$/', $line, $m)) $dotenv[$m[1]] = trim($m[2], '"');
        }
    }

    $env = getenv($key);
    if ($env !== false && $env !== '') return $env;
    if (isset($dotenv[$key]) && $dotenv[$key] !== '') return $dotenv[$key];
    if (array_key_exists($key, $local) && $local[$key] !== '') return $local[$key];
    if ($key === 'DB_PASS') return app_config('DB_PASSWORD', $default);
    return $default;
}

/** Public base URL of this install, without a trailing slash (e.g. https://zozocal.com). */
function app_url(): string
{
    $url = (string)app_config('APP_URL', '');
    if ($url === '' && !empty($_SERVER['HTTP_HOST'])) {
        $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
        $url = "{$scheme}://{$_SERVER['HTTP_HOST']}";
    }
    return rtrim($url, '/');
}
