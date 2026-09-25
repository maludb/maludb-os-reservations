<?php
/**
 * Agent API smoke test and parity check
 *
 *   php scripts/api-smoke.php [base_url]      (default http://localhost)
 *
 * 1. Parity: every POST handler under html/partials/ must be mapped to API
 *    operations (or exempted with a reason) in scripts/api-parity.php, and
 *    every mapped operation must exist.
 * 2. Surfaces: OpenAPI, /operations and MCP tools/list list the same operations.
 * 3. Roles: a temporary staff user's key sees fewer operations and gets 403
 *    on a manager operation.
 * 4. Reads: every GET operation without path params is called through REST
 *    and MCP and must succeed.
 *
 * Creates a temporary staff user and two temporary keys; removes them at the end.
 * Exit code 0 = all passed.
 */

require_once __DIR__ . '/../helpers/api/auth.php';
require_once __DIR__ . '/../helpers/api/registry.php';

$base = rtrim($argv[1] ?? 'http://localhost', '/');
$failures = 0;

function check(bool $ok, string $label): void
{
    global $failures;
    if (!$ok) $failures++;
    echo ($ok ? "  ok   " : "  FAIL ") . $label . "\n";
}

function http(string $method, string $url, string $key = '', $body = null): array
{
    $ch = curl_init($url);
    $headers = ['Content-Type: application/json'];
    if ($key !== '') $headers[] = "Authorization: Bearer {$key}";
    curl_setopt_array($ch, [
        CURLOPT_CUSTOMREQUEST => $method,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER => $headers,
        CURLOPT_TIMEOUT => 30,
    ]);
    if ($body !== null) curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($body));
    $raw = curl_exec($ch);
    $status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    return [$status, json_decode((string)$raw, true)];
}

function mcp(string $key, string $method, array $params = []): ?array
{
    global $base;
    [, $json] = http('POST', "{$base}/api/mcp/admin.php", $key,
        ['jsonrpc' => '2.0', 'id' => 1, 'method' => $method, 'params' => (object)$params]);
    return $json;
}

// Sample values for required inputs of read operations
function sample_input(array $op): array
{
    $samples = ['date' => date('Y-m-d', strtotime('+1 day')), 'party_size' => 2, 'q' => 'an',
                'date_from' => date('Y-m-d', strtotime('-30 days')), 'date_to' => date('Y-m-d')];
    $in = [];
    foreach ($op['required'] as $r) {
        if (isset($samples[$r])) $in[$r] = $samples[$r];
    }
    return $in;
}

// ── 1. Parity ─────────────────────────────────────────────
echo "Parity (UI handlers ↔ API operations)\n";
$parity = require __DIR__ . '/api-parity.php';
$ops = api_ops();
$handlers = [];
foreach (glob(__DIR__ . '/../html/partials/*/*.php') as $file) {
    $src = file_get_contents($file);
    if (preg_match('/\$_POST\[|REQUEST_METHOD\'\]\s*!?==\s*\'POST\'/', $src)) {
        $handlers[] = basename(dirname($file)) . '/' . basename($file);
    }
}
$unmapped = array_diff($handlers, array_keys($parity));
check(!$unmapped, count($handlers) . ' POST handlers mapped' . ($unmapped ? ' — missing: ' . implode(', ', $unmapped) : ''));
$missingOps = [];
foreach ($parity as $handler => $names) {
    if (is_string($names)) continue; // exemption reason
    foreach ($names as $n) if (!isset($ops[$n])) $missingOps[] = "{$handler} → {$n}";
}
check(!$missingOps, 'mapped operations exist' . ($missingOps ? ' — unknown: ' . implode(', ', $missingOps) : ''));

// ── Temporary caller keys ────────────────────────────────
$pdo = db();
$restaurantId = (int)$pdo->query("SELECT id FROM restaurants WHERE is_active = 1 ORDER BY id LIMIT 1")->fetchColumn();
$adminId = (int)$pdo->query("SELECT user_id FROM user_restaurants WHERE role = 'admin' AND restaurant_id = {$restaurantId} ORDER BY user_id LIMIT 1")->fetchColumn();
$email = 'api-smoke-' . bin2hex(random_bytes(4)) . '@example.invalid';
$stmt = $pdo->prepare("INSERT INTO users (email, password_hash, first_name, last_name, role, is_active) VALUES (?, ?, 'API', 'Smoke', 'user', 1) RETURNING id");
$stmt->execute([$email, password_hash(bin2hex(random_bytes(16)), PASSWORD_DEFAULT)]);
$staffId = (int)$stmt->fetchColumn();
$pdo->prepare("INSERT INTO user_restaurants (user_id, restaurant_id, role) VALUES (?, ?, 'user')")->execute([$staffId, $restaurantId]);
$adminKey = api_create_key($adminId, $restaurantId, 'api-smoke admin', 1);
$staffKey = api_create_key($staffId, $restaurantId, 'api-smoke staff', 1);

try {
    // ── 2. Surfaces agree ────────────────────────────────
    echo "Surfaces\n";
    [$s, $openapi] = http('GET', "{$base}/api/v1/index.php/openapi.json");
    $openapiOps = 0;
    foreach ($openapi['paths'] ?? [] as $methods) $openapiOps += count($methods);
    check($s === 200 && $openapiOps === count($ops), "OpenAPI lists all {$openapiOps}/" . count($ops) . ' operations');

    [$s, $list] = http('GET', "{$base}/api/v1/index.php/operations", $adminKey);
    $adminVisible = count($list['data']['operations'] ?? []);
    $expected = count(api_ops_for(api_token_context($adminKey)));
    check($s === 200 && $adminVisible === $expected, "admin key sees {$adminVisible} operations over REST");
    $tools = mcp($adminKey, 'tools/list')['result']['tools'] ?? [];
    check(count($tools) === $adminVisible, 'MCP tools/list matches REST (' . count($tools) . ')');
    $badNames = array_filter(array_column($tools, 'name'), fn($n) => !preg_match('/^[a-zA-Z0-9_-]{1,64}$/', $n));
    check(!$badNames, 'tool names are valid for Claude (^[a-zA-Z0-9_-]{1,64}$)');

    // ── 3. Roles ────────────────────────────────────────
    echo "Roles\n";
    [, $staffList] = http('GET', "{$base}/api/v1/index.php/operations", $staffKey);
    $staffVisible = count($staffList['data']['operations'] ?? []);
    check($staffVisible > 0 && $staffVisible < $adminVisible, "staff key sees {$staffVisible} operations");
    $managerOp = null;
    foreach ($ops as $op) {
        if ($op['role'] === 'manager' && $op['method'] === 'GET' && !str_contains($op['path'], '{')) { $managerOp = $op; break; }
    }
    if ($managerOp) {
        [$s] = http('GET', "{$base}/api/v1/index.php/{$managerOp['path']}", $staffKey);
        check($s === 403, "staff key gets 403 on {$managerOp['name']}");
    }
    [$s] = http('GET', "{$base}/api/v1/index.php/operations", 'zc_not_a_real_key');
    check($s === 401, 'bad key gets 401');

    // ── 4. Every read operation ─────────────────────────
    echo "Read operations (REST + MCP)\n";
    foreach ($ops as $op) {
        if ($op['method'] !== 'GET' || str_contains($op['path'], '{')) continue;
        $in = sample_input($op);
        [$s, $json] = http('GET', "{$base}/api/v1/index.php/{$op['path']}?" . http_build_query($in), $adminKey);
        $mcpRes = mcp($adminKey, 'tools/call', ['name' => $op['name'], 'arguments' => (object)$in]);
        $mcpOk = isset($mcpRes['result']) && empty($mcpRes['result']['isError']);
        check($s === 200 && !empty($json['success']) && $mcpOk,
            "{$op['name']}" . ($s === 200 && $mcpOk ? '' : " — REST {$s} " . ($json['error'] ?? '') . ' / MCP ' . substr(json_encode($mcpRes['result']['content'][0]['text'] ?? $mcpRes), 0, 120)));
    }
} finally {
    $pdo->prepare("DELETE FROM api_tokens WHERE user_id = ? OR (user_id = ? AND name = 'api-smoke admin')")->execute([$staffId, $adminId]);
    $pdo->prepare("DELETE FROM activity_log WHERE user_id = ?")->execute([$staffId]);
    $pdo->prepare("DELETE FROM user_restaurants WHERE user_id = ?")->execute([$staffId]);
    $pdo->prepare("DELETE FROM users WHERE id = ?")->execute([$staffId]);
}

echo $failures ? "\n{$failures} check(s) FAILED\n" : "\nAll checks passed\n";
exit($failures ? 1 : 0);
