<?php
/**
 * Per-server configuration loader
 *
 * Values come from, in order:
 *   1. an environment variable of the same name (e.g. set in the Apache vhost with SetEnv)
 *   2. config/local.php (git-ignored; copy config/local.example.php to create it)
 *   3. the default passed by the caller
 */

function app_config(string $key, $default = null)
{
    static $local = null;
    if ($local === null) {
        $file = __DIR__ . '/local.php';
        $local = is_file($file) ? (require $file) : [];
        if (!is_array($local)) $local = [];
    }

    $env = getenv($key);
    if ($env !== false && $env !== '') return $env;
    if (array_key_exists($key, $local) && $local[$key] !== '') return $local[$key];
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
