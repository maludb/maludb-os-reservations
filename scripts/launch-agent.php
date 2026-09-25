#!/usr/bin/env php
<?php
/**
 * Launch Retell AI Voice Agent — Outbound Calls to Customers
 *
 * Reads guests with phone numbers from the database and launches
 * a Retell AI outbound phone call to each one.
 *
 * Usage:
 *   php scripts/launch-agent.php                         # Call all guests with phone numbers
 *   php scripts/launch-agent.php --restaurant=1          # Only guests for restaurant ID 1
 *   php scripts/launch-agent.php --limit=10              # Limit to 10 calls
 *   php scripts/launch-agent.php --dry-run               # Preview without calling
 *   php scripts/launch-agent.php --from=+12025551234     # Specify caller ID number
 */

require_once __DIR__ . '/../config/database.php';

// --- Configuration ---
$AGENT_ID = (string)app_config('RETELL_LAUNCH_AGENT_ID', '');
$API_KEY  = (string)app_config('RETELL_LAUNCH_API_KEY', '');
$API_URL  = 'https://api.retellai.com/v2/create-phone-call';

// --- Parse CLI arguments ---
$opts = getopt('', ['restaurant:', 'limit:', 'from:', 'dry-run', 'help']);

if (isset($opts['help'])) {
    echo "Usage: php scripts/launch-agent.php [options]\n";
    echo "  --restaurant=ID   Only guests for this restaurant ID\n";
    echo "  --limit=N         Max number of calls to make\n";
    echo "  --from=NUMBER     Retell phone number to call from (e.g. +12025551234)\n";
    echo "  --dry-run         Preview customers without making calls\n";
    echo "  --help            Show this help\n";
    exit(0);
}

$restaurantId = isset($opts['restaurant']) ? (int)$opts['restaurant'] : null;
$limit        = isset($opts['limit']) ? (int)$opts['limit'] : null;
$fromNumber   = $opts['from'] ?? null;
$dryRun       = isset($opts['dry-run']);

// --- Connect to database ---
$pdo = Database::getInstance()->getConnection();

// --- Build query ---
$where = ["phone IS NOT NULL", "phone != ''"];
$params = [];

if ($restaurantId) {
    $where[] = "restaurant_id = ?";
    $params[] = $restaurantId;
}

$sql = "SELECT id, restaurant_id, first_name, last_name, phone, email
        FROM guests
        WHERE " . implode(' AND ', $where) . "
        ORDER BY last_name, first_name";

if ($limit) {
    $sql .= " LIMIT " . $limit;
}

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$guests = $stmt->fetchAll(PDO::FETCH_ASSOC);

if (empty($guests)) {
    echo "No guests with phone numbers found.\n";
    exit(0);
}

echo "Found " . count($guests) . " guest(s) with phone numbers.\n";
echo "Agent: {$AGENT_ID}\n";
if ($dryRun) echo "*** DRY RUN — no calls will be made ***\n";
echo str_repeat('-', 60) . "\n";

// --- Process each guest ---
$success = 0;
$failed  = 0;

foreach ($guests as $guest) {
    $name  = trim($guest['first_name'] . ' ' . $guest['last_name']);
    $phone = preg_replace('/[^0-9+]/', '', $guest['phone']);

    // Ensure phone has country code
    if (!str_starts_with($phone, '+')) {
        $phone = '+1' . $phone; // Default to US
    }

    echo sprintf("  %-30s %s", $name, $guest['phone']);

    if ($dryRun) {
        echo " [SKIP - dry run]\n";
        continue;
    }

    // Build API request
    $body = [
        'agent_id' => $AGENT_ID,
        'to_number' => $phone,
        'retell_llm_dynamic_variables' => [
            'customer_name' => $name,
            'customer_phone' => $guest['phone'],
            'customer_email' => $guest['email'] ?? '',
        ],
    ];

    if ($fromNumber) {
        $body['from_number'] = $fromNumber;
    }

    // Call Retell API
    $ch = curl_init();
    curl_setopt_array($ch, [
        CURLOPT_URL            => $API_URL,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST           => true,
        CURLOPT_POSTFIELDS     => json_encode($body),
        CURLOPT_HTTPHEADER     => [
            'Authorization: Bearer ' . $API_KEY,
            'Content-Type: application/json',
        ],
        CURLOPT_TIMEOUT => 30,
    ]);

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curlError = curl_error($ch);
    curl_close($ch);

    if ($curlError) {
        echo " [FAILED - connection error]\n";
        $failed++;
        continue;
    }

    $data = json_decode($response, true);

    if ($httpCode >= 200 && $httpCode < 300) {
        $callId = $data['call_id'] ?? 'unknown';
        echo " [OK - call_id: {$callId}]\n";
        $success++;
    } else {
        $error = $data['message'] ?? $data['error'] ?? "HTTP {$httpCode}";
        echo " [FAILED - {$error}]\n";
        $failed++;
    }

    // Brief pause between calls
    usleep(500000); // 0.5 seconds
}

echo str_repeat('-', 60) . "\n";
echo "Done. Success: {$success}, Failed: {$failed}\n";
