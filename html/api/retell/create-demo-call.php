<?php
/**
 * Retell Create Web Call — Demo Endpoint (no auth required)
 *
 * Creates a web call for the demo/call pages.
 * Auto-populates restaurant info as dynamic variables.
 * Uses the Retell API key from the first restaurant's settings.
 */

require_once __DIR__ . '/../../../helpers/db.php';
require_once __DIR__ . '/../../../helpers/retell-auth.php';

header('Content-Type: application/json');

// --- Logging helper ---
$logFile = __DIR__ . '/../../../logs/create-demo-call.log';
function dclog(string $msg) {
    global $logFile;
    $ts = date('Y-m-d H:i:s');
    file_put_contents($logFile, "[{$ts}] {$msg}\n", FILE_APPEND);
}
dclog("=== CREATE DEMO CALL ===");

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
    exit;
}

$rawBody = file_get_contents('php://input');
dclog("RAW BODY: " . ($rawBody ?: '(empty)'));
$input = json_decode($rawBody, true);
$agentId = $input['agent_id'] ?? '';

if (empty($agentId) || !preg_match('/^agent_[a-zA-Z0-9]+$/', $agentId)) {
    dclog("INVALID agent_id: " . ($agentId ?: '(empty)'));
    http_response_code(400);
    echo json_encode(['error' => 'Invalid or missing agent_id']);
    exit;
}

$apiKey = getRetellApiKey();
if (empty($apiKey)) {
    dclog("NO API KEY");
    http_response_code(400);
    echo json_encode(['error' => 'No Retell API key configured. Set it in Settings > Integrations.']);
    exit;
}

$pdo = db();

// --- Auto-populate restaurant info ---
$stmt = $pdo->query("SELECT id, name, slug, phone FROM restaurants ORDER BY id ASC LIMIT 1");
$restaurant = $stmt->fetch();
dclog("RESTAURANT: " . ($restaurant ? json_encode($restaurant) : 'none'));

$dynamicVars = [
    'restaurant_name'  => $restaurant['name'] ?? '',
    'restaurant_slug'  => $restaurant['slug'] ?? '',
    'restaurant_phone' => $restaurant['phone'] ?? '',
    'caller_known'     => 'false',
];

// Load custom prompt sections
if ($restaurant) {
    $rid = (int)$restaurant['id'];
    $promptKeys = ['voice_agent_greeting', 'voice_agent_specials', 'voice_agent_custom_info'];
    $placeholders = implode(',', array_fill(0, count($promptKeys), '?'));
    $stmt = $pdo->prepare(
        "SELECT setting_key, setting_value FROM settings
         WHERE restaurant_id = ? AND setting_key IN ({$placeholders})"
    );
    $stmt->execute(array_merge([$rid], $promptKeys));
    foreach ($stmt->fetchAll() as $ps) {
        $val = trim($ps['setting_value'] ?? '');
        if ($val !== '') {
            $dynamicVars[$ps['setting_key']] = $val;
        }
    }
}

// Merge any extra metadata from the caller
$metadata = $input['metadata'] ?? [];
if (is_array($metadata) && !empty($metadata)) {
    $dynamicVars = array_merge($dynamicVars, $metadata);
}

dclog("DYNAMIC VARIABLES: " . json_encode($dynamicVars, JSON_PRETTY_PRINT));

$postBody = [
    'agent_id' => $agentId,
    'retell_llm_dynamic_variables' => $dynamicVars,
];

dclog("POST BODY TO RETELL: " . json_encode($postBody, JSON_PRETTY_PRINT));

$ch = curl_init();
curl_setopt_array($ch, [
    CURLOPT_URL => 'https://api.retellai.com/v2/create-web-call',
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_POST => true,
    CURLOPT_POSTFIELDS => json_encode($postBody),
    CURLOPT_HTTPHEADER => [
        'Authorization: Bearer ' . $apiKey,
        'Content-Type: application/json',
    ],
    CURLOPT_TIMEOUT => 30,
]);

$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$curlError = curl_error($ch);
curl_close($ch);

dclog("RETELL RESPONSE — HTTP {$httpCode}");
dclog("RETELL RESPONSE BODY: " . ($response ?: '(empty)'));
if ($curlError) dclog("CURL ERROR: " . $curlError);
dclog("=== END ===\n");

if ($curlError) {
    http_response_code(500);
    echo json_encode(['error' => 'Failed to connect to Retell API']);
    exit;
}

if ($httpCode >= 200 && $httpCode < 300) {
    echo $response;
} else {
    http_response_code($httpCode >= 400 && $httpCode < 600 ? $httpCode : 500);
    $errorResponse = json_decode($response, true);
    echo json_encode(['error' => $errorResponse['message'] ?? 'Failed to create web call']);
}
