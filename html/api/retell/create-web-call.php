<?php
/**
 * Retell Create Web Call API Endpoint
 *
 * Creates a web call session with Retell AI and returns the access token
 * for the frontend to initiate the voice call via browser audio.
 * Auto-populates dynamic variables with restaurant info and guest CRM data.
 */

require_once __DIR__ . '/../../../helpers/auth.php';
require_once __DIR__ . '/../../../helpers/retell-auth.php';

header('Content-Type: application/json');

// --- Logging helper ---
$logFile = __DIR__ . '/../../../logs/create-web-call.log';
function wclog(string $msg) {
    global $logFile;
    $ts = date('Y-m-d H:i:s');
    file_put_contents($logFile, "[{$ts}] {$msg}\n", FILE_APPEND);
}
wclog("=== CREATE WEB CALL ===");
wclog("REQUEST_METHOD: " . ($_SERVER['REQUEST_METHOD'] ?? 'unknown'));

// Verify user is authenticated
if (!get_user_id()) {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

// Only accept POST requests
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
    exit;
}

// Get request body
$rawBody = file_get_contents('php://input');
wclog("RAW BODY: " . ($rawBody !== false && $rawBody !== '' ? $rawBody : '(empty)'));
$input = json_decode($rawBody, true);
if (!is_array($input)) {
    $input = [];
}
$agentId = $input['agent_id'] ?? '';
wclog("PARSED agent_id: " . ($agentId ?: '(empty)'));

$restaurantId = currentRestaurantId();
$pdo = db();

// If no agent_id provided, try to load from settings
if (empty($agentId)) {
    $stmt = $pdo->prepare("SELECT setting_value FROM settings WHERE restaurant_id = ? AND setting_key = 'retell_agent_id'");
    $stmt->execute([$restaurantId]);
    $row = $stmt->fetch();
    $agentId = $row ? $row['setting_value'] : '';
}

if (empty($agentId)) {
    http_response_code(400);
    echo json_encode(['error' => 'No Retell agent configured. Set the Agent ID in Settings > Integrations.']);
    exit;
}

// Validate agent_id format
if (!preg_match('/^agent_[a-zA-Z0-9]+$/', $agentId)) {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid agent_id format']);
    exit;
}

// Get API key from settings
$apiKey = getRetellApiKey();
if (empty($apiKey)) {
    http_response_code(400);
    echo json_encode(['error' => 'No Retell API key configured. Set it in Settings > Integrations.']);
    exit;
}

// --- Auto-populate dynamic variables with restaurant + guest info ---
$stmt = $pdo->prepare("SELECT name, slug, phone FROM restaurants WHERE id = ?");
$stmt->execute([$restaurantId]);
$restaurant = $stmt->fetch();

$dynamicVars = [
    'restaurant_name'  => $restaurant['name'] ?? '',
    'restaurant_slug'  => $restaurant['slug'] ?? '',
    'restaurant_phone' => $restaurant['phone'] ?? '',
    'caller_known'     => 'false',
];

// Load custom prompt sections from settings
$promptKeys = ['voice_agent_greeting', 'voice_agent_specials', 'voice_agent_custom_info'];
$placeholders = implode(',', array_fill(0, count($promptKeys), '?'));
$stmt = $pdo->prepare(
    "SELECT setting_key, setting_value FROM settings
     WHERE restaurant_id = ? AND setting_key IN ({$placeholders})"
);
$stmt->execute(array_merge([$restaurantId], $promptKeys));
foreach ($stmt->fetchAll() as $ps) {
    $val = trim($ps['setting_value'] ?? '');
    if ($val !== '') {
        $dynamicVars[$ps['setting_key']] = $val;
    }
}

// If a guest_id or guest_phone was passed, look up CRM data
// Check both top-level and inside metadata (retell-integration.js sends them in metadata)
$extraVars = $input['metadata'] ?? $input['dynamic_variables'] ?? [];
$guestId = $input['guest_id'] ?? ($extraVars['guest_id'] ?? null);
$guestPhone = $input['guest_phone'] ?? ($extraVars['guest_phone'] ?? null);
$matchedGuest = null;

if ($guestId) {
    $stmt = $pdo->prepare("SELECT * FROM guests WHERE id = ? AND restaurant_id = ? AND is_active = 1");
    $stmt->execute([$guestId, $restaurantId]);
    $matchedGuest = $stmt->fetch();
} elseif ($guestPhone) {
    require_once __DIR__ . '/../../../helpers/restaurant.php';
    $phoneDigits = normalizePhone($guestPhone);
    $phone10 = strlen($phoneDigits) > 10 ? substr($phoneDigits, -10) : $phoneDigits;

    $stmt = $pdo->prepare(
        "SELECT * FROM guests
         WHERE restaurant_id = ? AND phone IS NOT NULL AND phone != '' AND is_active = 1
         ORDER BY last_visit_at DESC"
    );
    $stmt->execute([$restaurantId]);
    foreach ($stmt->fetchAll() as $g) {
        $gDigits = normalizePhone($g['phone']);
        $g10 = strlen($gDigits) > 10 ? substr($gDigits, -10) : $gDigits;
        if ($gDigits === $phoneDigits || $g10 === $phone10) {
            $matchedGuest = $g;
            break;
        }
    }
}

if ($matchedGuest) {
    $dynamicVars['caller_known']          = 'true';
    $dynamicVars['guest_name']            = trim($matchedGuest['first_name'] . ' ' . $matchedGuest['last_name']);
    $dynamicVars['guest_first_name']      = $matchedGuest['first_name'];
    $dynamicVars['guest_last_name']       = $matchedGuest['last_name'];
    $dynamicVars['guest_phone']           = $matchedGuest['phone'] ?? '';
    $dynamicVars['guest_email']           = $matchedGuest['email'] ?? '';
    $dynamicVars['guest_tags']            = $matchedGuest['tags'] ?? '';
    $dynamicVars['guest_dietary']         = $matchedGuest['dietary_restrictions'] ?? '';
    $dynamicVars['guest_allergies']       = $matchedGuest['allergies'] ?? '';
    $dynamicVars['guest_seating_pref']    = $matchedGuest['seating_preference'] ?? '';
    $dynamicVars['guest_notes']           = $matchedGuest['notes'] ?? '';
    $dynamicVars['guest_visit_count']     = (string)($matchedGuest['visit_count'] ?? '0');
    $dynamicVars['guest_noshow_count']    = (string)($matchedGuest['noshow_count'] ?? '0');
    $dynamicVars['guest_favorite_server'] = $matchedGuest['favorite_server'] ?? '';
}

// Allow caller to override/add extra dynamic variables
$extraVars = $input['metadata'] ?? $input['dynamic_variables'] ?? [];
if (is_array($extraVars)) {
    $dynamicVars = array_merge($dynamicVars, $extraVars);
}

wclog("RESTAURANT — id: {$restaurantId}, name: " . ($restaurant['name'] ?? 'null') . ", slug: " . ($restaurant['slug'] ?? 'null'));
wclog("GUEST LOOKUP — guest_id: " . ($guestId ?: 'null') . ", guest_phone: " . ($guestPhone ?: 'null'));
wclog("MATCHED GUEST: " . ($matchedGuest ? "id={$matchedGuest['id']} name={$matchedGuest['first_name']} {$matchedGuest['last_name']}" : 'none'));
wclog("DYNAMIC VARIABLES: " . json_encode($dynamicVars, JSON_PRETTY_PRINT));

// Build the web call request
$postBody = [
    'agent_id' => $agentId,
    'retell_llm_dynamic_variables' => $dynamicVars,
];

wclog("POST BODY TO RETELL: " . json_encode($postBody, JSON_PRETTY_PRINT));

// Make cURL request to Retell API
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

wclog("RETELL RESPONSE — HTTP {$httpCode}");
wclog("RETELL RESPONSE BODY: " . ($response ?: '(empty)'));
if ($curlError) {
    wclog("CURL ERROR: " . $curlError);
}
wclog("=== END ===\n");

if ($curlError) {
    error_log("Retell API cURL error: " . $curlError);
    http_response_code(500);
    echo json_encode(['error' => 'Failed to connect to Retell API']);
    exit;
}

if ($httpCode >= 200 && $httpCode < 300) {
    echo $response;
} else {
    error_log("Retell API error (HTTP $httpCode): " . $response);
    http_response_code($httpCode >= 400 && $httpCode < 600 ? $httpCode : 500);
    $errorResponse = json_decode($response, true);
    $errorMessage = $errorResponse['message'] ?? 'Failed to create web call';
    echo json_encode(['error' => $errorMessage]);
}
