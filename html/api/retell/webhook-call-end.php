<?php
/**
 * Retell Webhook: Call End
 *
 * Fired when a call ends. Saves transcript, recording URL, and call
 * metadata to the call_logs table. Also writes a short audit entry
 * to activity_log.
 */
require_once __DIR__ . '/../../../helpers/retell-auth.php';
require_once __DIR__ . '/../../../helpers/voice-api.php';

header('Content-Type: application/json');

// --- Logging helper ---
$logFile = __DIR__ . '/../../../logs/webhook-call-end.log';

function elog(string $msg) {
    global $logFile;
    $ts = date('Y-m-d H:i:s');
    file_put_contents($logFile, "[{$ts}] {$msg}\n", FILE_APPEND);
}

elog("=== WEBHOOK CALL END ===");

// Read raw body
$rawBody = file_get_contents('php://input');
elog("RAW BODY: " . ($rawBody !== false && $rawBody !== '' ? $rawBody : '(empty)'));

if ($rawBody === false || $rawBody === '') {
    http_response_code(400);
    echo json_encode(['error' => 'Empty request body']);
    exit;
}

$data = json_decode($rawBody, true);
if (!is_array($data)) {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid JSON']);
    exit;
}

$call = $data['call'] ?? $data;

// --- Extract fields ---
$callId              = $call['call_id'] ?? '';
$fromNumber          = $call['from_number'] ?? '';
$toNumber            = $call['to_number'] ?? '';
$direction           = $call['direction'] ?? 'inbound';
$status              = $call['call_status'] ?? ($call['status'] ?? 'ended');
$durationMs          = $call['duration_ms'] ?? null;
$disconnectionReason = $call['disconnection_reason'] ?? '';
$transcript          = $call['transcript'] ?? '';
$recordingUrl        = $call['recording_url'] ?? '';
$publicLogUrl        = $call['public_log_url'] ?? '';
$callSummary         = $call['call_analysis']['call_summary'] ?? ($call['call_summary'] ?? '');
$startTimestamp      = $call['start_timestamp'] ?? null;
$endTimestamp        = $call['end_timestamp'] ?? null;

if ($toNumber === '') {
    $toNumber = '+18473930142';
}

elog("PARSED — call_id: {$callId}, from: {$fromNumber}, to: {$toNumber}, duration_ms: {$durationMs}, status: {$status}");
elog("recording_url: " . ($recordingUrl ?: '(none)'));
elog("transcript length: " . strlen($transcript));

// --- Look up restaurant ---
$restaurant = null;
if ($toNumber !== '') {
    $restaurant = getRestaurantByPhone($toNumber);
}
$restaurantId = $restaurant ? (int)$restaurant['id'] : null;
if ($restaurantId) {
    applyRestaurantTimezone($restaurantId);
}
$pdo = db();

elog("restaurant_id: " . ($restaurantId ?? 'NULL'));

// --- Match guest by from_number ---
$guestId = null;
if ($fromNumber !== '' && $restaurantId) {
    $fromDigits = normalizePhone($fromNumber);
    $from10 = strlen($fromDigits) > 10 ? substr($fromDigits, -10) : $fromDigits;

    $stmt = $pdo->prepare(
        "SELECT id, phone FROM guests
         WHERE restaurant_id = ? AND phone IS NOT NULL AND phone != '' AND is_active = 1"
    );
    $stmt->execute([$restaurantId]);
    foreach ($stmt->fetchAll() as $g) {
        $gDigits = normalizePhone($g['phone']);
        $g10 = strlen($gDigits) > 10 ? substr($gDigits, -10) : $gDigits;
        if ($gDigits === $fromDigits || $g10 === $from10) {
            $guestId = (int)$g['id'];
            elog("GUEST MATCHED — id: {$guestId}");
            break;
        }
    }
}

// --- Build extra metadata ---
$metadata = [];
if ($publicLogUrl !== '') {
    $metadata['public_log_url'] = $publicLogUrl;
}
if ($startTimestamp !== null) {
    $metadata['start_timestamp'] = $startTimestamp;
}
if ($endTimestamp !== null) {
    $metadata['end_timestamp'] = $endTimestamp;
}
// Capture call_analysis if present
if (isset($call['call_analysis']) && is_array($call['call_analysis'])) {
    $metadata['call_analysis'] = $call['call_analysis'];
}

// --- Timestamps ---
$createdAt = $startTimestamp ? date('Y-m-d H:i:s', $startTimestamp / 1000) : date('Y-m-d H:i:s');
$endedAt   = $endTimestamp   ? date('Y-m-d H:i:s', $endTimestamp / 1000)   : date('Y-m-d H:i:s');

// --- Insert into call_logs ---
try {
    $stmt = $pdo->prepare(
        "INSERT INTO call_logs
         (restaurant_id, guest_id, retell_call_id, direction, from_number, to_number,
          status, duration_ms, disconnection_reason, transcript, recording_url,
          call_summary, metadata, created_at, ended_at)
         VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)"
    );
    $stmt->execute([
        $restaurantId,
        $guestId,
        $callId ?: null,
        $direction,
        $fromNumber,
        $toNumber,
        $status,
        $durationMs,
        $disconnectionReason ?: null,
        $transcript ?: null,
        $recordingUrl ?: null,
        $callSummary ?: null,
        !empty($metadata) ? json_encode($metadata) : null,
        $createdAt,
        $endedAt,
    ]);
    elog("INSERTED call_logs row id: " . $pdo->lastInsertId());
} catch (Exception $e) {
    elog("ERROR inserting call_logs: " . $e->getMessage());
}

// --- Short audit entry in activity_log ---
$durationSec = $durationMs !== null ? round($durationMs / 1000) : null;
$durationDisplay = $durationSec !== null ? gmdate('H:i:s', $durationSec) : 'unknown';
$desc = "Call ended — from: {$fromNumber}, to: {$toNumber}, duration: {$durationDisplay}, status: {$status}";
if ($disconnectionReason !== '') {
    $desc .= ", reason: {$disconnectionReason}";
}

try {
    $stmt = $pdo->prepare(
        "INSERT INTO activity_log (restaurant_id, user_id, action, entity_type, description, ip_address, created_at)
         VALUES (?, NULL, 'voice_call_ended', 'voice_call', ?, ?, NOW())"
    );
    $stmt->execute([
        $restaurantId,
        $desc,
        $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0'
    ]);
} catch (Exception $e) {
    elog("ERROR inserting activity_log: " . $e->getMessage());
}

elog("=== END ===\n");

http_response_code(200);
echo json_encode(['success' => true]);
exit;
