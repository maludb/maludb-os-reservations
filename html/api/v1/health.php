<?php
/**
 * GET /api/v1/health — unauthenticated liveness for the Business OS kernel and its installer:
 * {"ok": true, "application": "<APP_KEY>", "version": "…", "database": "ok"}. Says nothing else.
 */
require_once __DIR__ . '/../../../helpers/os.php';

header('Content-Type: application/json');
header('Cache-Control: no-store');
$database = 'down';
try {
    $database = db()->query('SELECT 1')->fetchColumn() == 1 ? 'ok' : 'down';
} catch (Throwable $e) {
    error_log('health: ' . $e->getMessage());
}
$manifest = json_decode((string)@file_get_contents(__DIR__ . '/../../../maludb-os.json'), true);
http_response_code($database === 'ok' ? 200 : 503);
echo json_encode(['ok' => $database === 'ok', 'application' => os_app_key(),
    'version' => (string)($manifest['version'] ?? ''), 'database' => $database]);
