#!/usr/bin/env php
<?php
/**
 * Process Voice Messages — Outbound Delivery Cron Worker
 *
 * Picks up pending voice_messages rows and launches outbound Retell calls
 * using the target language agent to deliver the message.
 *
 * Usage:
 *   php scripts/process-voice-messages.php              # Process all pending
 *   php scripts/process-voice-messages.php --limit=5    # Limit batch size
 *   php scripts/process-voice-messages.php --dry-run    # Preview without calling
 *
 * Cron example (every 60 seconds):
 *   * * * * * php /var/www/scripts/process-voice-messages.php --limit=10 >> /var/www/logs/voice-messages-cron.log 2>&1
 */

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../helpers/retell-auth.php';

$API_URL = 'https://api.retellai.com/v2/create-phone-call';
$MAX_RETRIES = 3;

// --- Logging ---
$logFile = __DIR__ . '/../logs/voice-messages-cron.log';
function vmlog(string $msg) {
    global $logFile;
    $ts = date('Y-m-d H:i:s');
    file_put_contents($logFile, "[{$ts}] {$msg}\n", FILE_APPEND);
}

// --- Parse CLI arguments ---
$opts = getopt('', ['limit:', 'dry-run', 'help']);

if (isset($opts['help'])) {
    echo "Usage: php scripts/process-voice-messages.php [options]\n";
    echo "  --limit=N    Max number of messages to process\n";
    echo "  --dry-run    Preview without making calls\n";
    echo "  --help       Show this help\n";
    exit(0);
}

$limit  = isset($opts['limit']) ? (int)$opts['limit'] : 10;
$dryRun = isset($opts['dry-run']);

// --- Get Retell API key ---
$apiKey = getRetellApiKey();
if (empty($apiKey) && !$dryRun) {
    vmlog("ERROR: No Retell API key configured. Exiting.");
    echo "No Retell API key configured.\n";
    exit(1);
}

// --- Connect to database ---
$pdo = Database::getInstance()->getConnection();

// --- Fetch pending messages ---
$stmt = $pdo->prepare(
    "SELECT vm.*, rp.agent_id AS target_agent_id, rp.phone_number AS target_from_number,
            rp.language_code AS target_lang, rp.language_name AS target_lang_name,
            sp.language_name AS source_lang_name,
            r.name AS business_name
     FROM voice_messages vm
     JOIN restaurant_prompts rp ON rp.id = vm.target_prompt_id
     LEFT JOIN restaurant_prompts sp ON sp.id = vm.source_prompt_id
     LEFT JOIN restaurants r ON r.id = vm.restaurant_id
     WHERE vm.status = 'pending' AND vm.retry_count < ?
     ORDER BY vm.created_at ASC
     LIMIT ?"
);
$stmt->execute([$MAX_RETRIES, $limit]);
$messages = $stmt->fetchAll(PDO::FETCH_ASSOC);

if (empty($messages)) {
    vmlog("No pending voice messages to process.");
    exit(0);
}

vmlog("=== PROCESSING " . count($messages) . " voice message(s) ===");
if ($dryRun) vmlog("*** DRY RUN — no calls will be made ***");

$success = 0;
$failed  = 0;

foreach ($messages as $vm) {
    $vmId = $vm['id'];
    vmlog("--- Message #{$vmId}: {$vm['source_lang_name']} → {$vm['target_lang_name']}, to: {$vm['recipient_phone']}");

    if ($dryRun) {
        vmlog("  [SKIP - dry run] message: " . mb_substr($vm['message_text'], 0, 100) . '...');
        continue;
    }

    // Ensure target agent has a from_number
    if (empty($vm['target_from_number'])) {
        vmlog("  [SKIP] Target agent has no phone number configured");
        $pdo->prepare("UPDATE voice_messages SET retry_count = retry_count + 1, error_message = ? WHERE id = ?")
            ->execute(['Target agent has no phone number', $vmId]);
        $failed++;
        continue;
    }

    // Ensure recipient phone has country code
    $recipientPhone = preg_replace('/[^0-9+]/', '', $vm['recipient_phone']);
    if (!str_starts_with($recipientPhone, '+')) {
        $recipientPhone = '+1' . $recipientPhone;
    }

    // Build Retell API request
    $body = [
        'agent_id'    => $vm['target_agent_id'],
        'from_number' => $vm['target_from_number'],
        'to_number'   => $recipientPhone,
        'retell_llm_dynamic_variables' => [
            'message_text'       => $vm['message_text'],
            'source_language'    => $vm['source_lang_name'] ?? $vm['from_language'],
            'business_name'      => $vm['business_name'] ?? '',
            'is_message_relay'   => 'true',
        ],
    ];

    vmlog("  Calling Retell API: agent={$vm['target_agent_id']}, from={$vm['target_from_number']}, to={$recipientPhone}");

    // Mark as in_progress
    $pdo->prepare("UPDATE voice_messages SET status = 'in_progress' WHERE id = ?")
        ->execute([$vmId]);

    // Call Retell API
    $ch = curl_init();
    curl_setopt_array($ch, [
        CURLOPT_URL            => $API_URL,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST           => true,
        CURLOPT_POSTFIELDS     => json_encode($body),
        CURLOPT_HTTPHEADER     => [
            'Authorization: Bearer ' . $apiKey,
            'Content-Type: application/json',
        ],
        CURLOPT_TIMEOUT => 30,
    ]);

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curlError = curl_error($ch);
    curl_close($ch);

    if ($curlError) {
        vmlog("  [FAILED] Connection error: {$curlError}");
        $pdo->prepare("UPDATE voice_messages SET status = 'pending', retry_count = retry_count + 1, error_message = ? WHERE id = ?")
            ->execute(["Connection error: {$curlError}", $vmId]);
        $failed++;
        continue;
    }

    $data = json_decode($response, true);

    if ($httpCode >= 200 && $httpCode < 300) {
        $deliveryCallId = $data['call_id'] ?? 'unknown';
        vmlog("  [OK] Delivery call launched: call_id={$deliveryCallId}");

        $pdo->prepare("UPDATE voice_messages SET status = 'in_progress', delivery_call_id = ? WHERE id = ?")
            ->execute([$deliveryCallId, $vmId]);

        // Activity log
        try {
            $pdo->prepare(
                "INSERT INTO activity_log (restaurant_id, user_id, action, entity_type, description, ip_address, created_at)
                 VALUES (?, NULL, 'voice_relay_sent', 'voice_message', ?, '127.0.0.1', NOW())"
            )->execute([
                $vm['restaurant_id'],
                "Voice relay outbound call launched: {$vm['source_lang_name']} → {$vm['target_lang_name']}, call_id: {$deliveryCallId}",
            ]);
        } catch (Exception $e) {
            vmlog("  Activity log error: " . $e->getMessage());
        }

        $success++;
    } else {
        $error = $data['message'] ?? $data['error'] ?? "HTTP {$httpCode}";
        vmlog("  [FAILED] API error: {$error}");
        $pdo->prepare("UPDATE voice_messages SET status = 'pending', retry_count = retry_count + 1, error_message = ? WHERE id = ?")
            ->execute([$error, $vmId]);
        $failed++;
    }

    // Brief pause between calls
    usleep(500000);
}

vmlog("=== DONE. Success: {$success}, Failed: {$failed} ===\n");
echo "Done. Success: {$success}, Failed: {$failed}\n";
