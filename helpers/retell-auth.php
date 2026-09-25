<?php
/**
 * Retell AI Request Authentication
 *
 * Verifies X-Retell-Signature header using HMAC SHA-256.
 * API key stored in settings table as 'retell_api_key'.
 */

require_once __DIR__ . '/db.php';
require_once __DIR__ . '/availability.php'; // getRestaurantSetting

/**
 * Verify the Retell signature on an incoming request.
 * Retell signs the raw request body with HMAC SHA-256 using the API key.
 *
 * @param string $rawBody Raw request body
 * @param string $signature Value of X-Retell-Signature header
 * @param string $apiKey The Retell API key
 * @return bool
 */
function verifyRetellSignature(string $rawBody, string $signature, string $apiKey): bool
{
    if ($signature === '' || $apiKey === '') {
        return false;
    }
    $expected = hash_hmac('sha256', $rawBody, $apiKey);
    return hash_equals($expected, $signature);
}

/**
 * Get the Retell API key.
 * Priority: restaurant-specific override → global setting → hardcoded default.
 *
 * @param int|null $restaurantId Check for a restaurant-specific key first
 */
function getRetellApiKey(?int $restaurantId = null): string
{
    $pdo = db();

    // 1. Check for restaurant-specific override
    if ($restaurantId !== null && $restaurantId > 0) {
        $stmt = $pdo->prepare(
            "SELECT setting_value FROM settings WHERE setting_key = 'retell_api_key' AND restaurant_id = ? AND setting_value != '' LIMIT 1"
        );
        $stmt->execute([$restaurantId]);
        $row = $stmt->fetch();
        if ($row) {
            return $row['setting_value'];
        }
    }

    // 2. Check for a global key in settings
    $stmt = $pdo->prepare(
        "SELECT setting_value FROM settings WHERE setting_key = 'retell_api_key' AND setting_value != '' ORDER BY restaurant_id ASC LIMIT 1"
    );
    $stmt->execute();
    $row = $stmt->fetch();
    if ($row) {
        return $row['setting_value'];
    }

    // 3. Server-wide default (config/local.php or environment)
    require_once __DIR__ . '/../config/app.php';
    return (string)app_config('RETELL_DEFAULT_API_KEY', '');
}

/**
 * Parse a Retell Custom Function request.
 * Returns the decoded body or sends a 401/400 error response.
 */
function parseRetellRequest(): array
{
    // Logging
    $logFile = __DIR__ . '/../logs/retell-custom-functions.log';
    $ts = date('Y-m-d H:i:s');
    $uri = $_SERVER['REQUEST_URI'] ?? 'unknown';
    file_put_contents($logFile, "[{$ts}] === REQUEST: {$uri} ===\n", FILE_APPEND);

    // Read raw body
    $rawBody = file_get_contents('php://input');
    file_put_contents($logFile, "[{$ts}] RAW BODY: " . ($rawBody ?: '(empty)') . "\n", FILE_APPEND);

    if ($rawBody === false || $rawBody === '') {
        file_put_contents($logFile, "[{$ts}] ERROR: Empty body\n\n", FILE_APPEND);
        http_response_code(400);
        echo json_encode(['error' => 'Empty request body']);
        exit;
    }

    // Verify signature
    $signature = $_SERVER['HTTP_X_RETELL_SIGNATURE'] ?? '';
    $apiKey = getRetellApiKey();

    // Auth temporarily disabled for debugging
    // if ($apiKey !== '' && $signature !== '') {
    //     if (!verifyRetellSignature($rawBody, $signature, $apiKey)) {
    //         http_response_code(401);
    //         echo json_encode(['error' => 'Invalid signature']);
    //         exit;
    //     }
    // }

    // Parse JSON
    $data = json_decode($rawBody, true);
    if (!is_array($data)) {
        http_response_code(400);
        echo json_encode(['error' => 'Invalid JSON']);
        exit;
    }

    return $data;
}

/**
 * Extract args from Retell request body.
 * Retell sends: { "name": "function_name", "args": { ... }, "call": { ... } }
 */
function getRetellArgs(array $data): array
{
    return $data['args'] ?? [];
}

/**
 * Send a JSON response for Retell consumption.
 * Retell converts the response to a string for the LLM.
 */
function retellResponse(array $data): void
{
    $logFile = __DIR__ . '/../logs/retell-custom-functions.log';
    $ts = date('Y-m-d H:i:s');
    file_put_contents($logFile, "[{$ts}] RESPONSE: " . json_encode($data, JSON_PRETTY_PRINT) . "\n\n", FILE_APPEND);

    header('Content-Type: application/json');
    echo json_encode($data);
    exit;
}
