<?php
/**
 * Retell Restaurant Webhook
 *
 * Single webhook endpoint handling all Retell events:
 *   - call_inbound: Look up restaurant/guest, return dynamic_variables
 *   - call_started: Log only
 *   - call_ended:   Save transcript, recording, summary to call_logs
 *   - call_analyzed: Save call analysis to call_logs
 */
require_once __DIR__ . '/../../../helpers/retell-auth.php';
require_once __DIR__ . '/../../../helpers/voice-api.php';

header('Content-Type: application/json');

// --- Logging helper ---
$logFile = __DIR__ . '/../../../logs/restaurant-webhook.log';

function wlog(string $msg) {
    global $logFile;
    $ts = date('Y-m-d H:i:s');
    file_put_contents($logFile, "[{$ts}] {$msg}\n", FILE_APPEND);
}

// Agent ID from query string (e.g. ?id=agent_xxxx)
$queryAgentId = $_GET['id'] ?? '';

// Read raw body
$rawBody = file_get_contents('php://input');

$data = ($rawBody !== false && $rawBody !== '') ? json_decode($rawBody, true) : [];
if (!is_array($data)) {
    $data = [];
}

// Determine the event type
$event = $data['event'] ?? 'call_inbound';
wlog("=== WEBHOOK EVENT: {$event} ===");
wlog("QUERY agent_id: " . ($queryAgentId ?: '(none)'));

// Extract call data — Retell nests differently per event type
$call = $data['call'] ?? $data['call_inbound'] ?? $data;
$fromNumber = $call['from_number'] ?? '';
$toNumber   = $call['to_number'] ?? '';
$callId     = $call['call_id'] ?? '';

wlog("PARSED — call_id: {$callId}, from_number: {$fromNumber}, to_number: {$toNumber}");

if ($toNumber === '') {
    $toNumber = '+18473930142';
    wlog("to_number empty — using default: {$toNumber}");
}

// --- Look up restaurant: prefer agent_id resolution, fall back to phone ---
$restaurant = null;

// If agent_id provided, resolve restaurant from restaurant_prompts first
if ($queryAgentId !== '') {
    $agentStmt = db()->prepare(
        "SELECT rp.restaurant_id FROM restaurant_prompts rp
         JOIN restaurants r ON r.id = rp.restaurant_id AND r.is_active = 1
         WHERE rp.agent_id = ?
         LIMIT 1"
    );
    $agentStmt->execute([$queryAgentId]);
    $agentRow = $agentStmt->fetch();
    if ($agentRow) {
        $restaurant = getRestaurant((int)$agentRow['restaurant_id']);
        wlog("RESTAURANT RESOLVED via agent_id — restaurant_id: {$agentRow['restaurant_id']}");
    }
}

// Fall back to phone lookup only if no agent_id was provided
if (!$restaurant && $queryAgentId === '' && $toNumber !== '') {
    $restaurant = getRestaurantByPhone($toNumber);
    if ($restaurant) {
        wlog("RESTAURANT RESOLVED via phone — to_number: {$toNumber}");
    }
}

if (!$restaurant && $queryAgentId !== '') {
    wlog("INVALID agent_id: {$queryAgentId} — no matching restaurant_prompts record");
}

$restaurantId = $restaurant ? (int)$restaurant['id'] : null;
if ($restaurantId) {
    applyRestaurantTimezone($restaurantId);
}

// =====================================================================
// EVENT: call_inbound — Return dynamic variables for the agent
// =====================================================================
if ($event === 'call_inbound') {

    if (!$restaurant) {
        wlog("RESTAURANT NOT FOUND for to_number: {$toNumber}");
        $response = [
            'call_inbound' => [
                'dynamic_variables' => [
                    'restaurant_name' => '',
                    'restaurant_slug' => '',
                    'caller_known'    => 'false',
                ],
                'metadata' => [
                    'source' => 'phone',
                ],
            ],
        ];
        echo json_encode($response);
        exit;
    }

    wlog("RESTAURANT FOUND — id: {$restaurantId}, name: {$restaurant['name']}");
    $pdo = db();

    // --- Build dynamic variables ---
    $vars = [
        'restaurant_name'  => $restaurant['name'],
        'restaurant_slug'  => $restaurant['slug'],
        'restaurant_phone' => $restaurant['phone'] ?? '',
        'time'             => date('g:i A'),
        'today'            => date('Y-m-d'),
        'tomorrow'         => date('Y-m-d', strtotime('+1 day')),
        'dow'              => date('l'),
        'monday'           => date('Y-m-d', strtotime(strtolower(date('l')) === 'monday' ? 'today' : 'next monday')),
        'tuesday'          => date('Y-m-d', strtotime(strtolower(date('l')) === 'tuesday' ? 'today' : 'next tuesday')),
        'wednesday'        => date('Y-m-d', strtotime(strtolower(date('l')) === 'wednesday' ? 'today' : 'next wednesday')),
        'thursday'         => date('Y-m-d', strtotime(strtolower(date('l')) === 'thursday' ? 'today' : 'next thursday')),
        'friday'           => date('Y-m-d', strtotime(strtolower(date('l')) === 'friday' ? 'today' : 'next friday')),
        'saturday'         => date('Y-m-d', strtotime(strtolower(date('l')) === 'saturday' ? 'today' : 'next saturday')),
        'sunday'           => date('Y-m-d', strtotime(strtolower(date('l')) === 'sunday' ? 'today' : 'next sunday')),
        'caller_known'     => 'false',
    ];

    // --- Build metadata ---
    $meta = [
        'restaurant_id' => (string)$restaurantId,
        'source'        => 'phone',
        'from_number'   => $fromNumber,
        'to_number'     => $toNumber,
    ];

    // --- Check if caller is a known guest ---
    if ($fromNumber !== '') {
        $fromDigits = normalizePhone($fromNumber);
        $from10 = strlen($fromDigits) > 10 ? substr($fromDigits, -10) : $fromDigits;
        $fromE164 = '+1' . $from10;
        wlog("GUEST LOOKUP — from_number normalized to E.164: {$fromE164}");

        $stmt = $pdo->prepare(
            "SELECT * FROM guests
             WHERE restaurant_id = ? AND phone IS NOT NULL AND phone != '' AND is_active = 1
             ORDER BY last_visit_at DESC"
        );
        $stmt->execute([$restaurantId]);
        $guests = $stmt->fetchAll();
        wlog("GUEST LOOKUP — found " . count($guests) . " active guests with phone numbers");

        $matchedGuest = null;
        foreach ($guests as $g) {
            $gDigits = normalizePhone($g['phone']);
            $g10 = strlen($gDigits) > 10 ? substr($gDigits, -10) : $gDigits;
            $gE164 = '+1' . $g10;
            if ($fromE164 === $gE164) {
                $matchedGuest = $g;
                wlog("GUEST MATCHED — id: {$g['id']}, name: {$g['first_name']} {$g['last_name']}, phone: {$g['phone']} (normalized: {$gE164})");
                break;
            }
        }

        if (!$matchedGuest) {
            wlog("GUEST NOT MATCHED — no guest found for phone {$fromNumber}");
            $vars['guest_phone'] = $fromE164;
        }

        if ($matchedGuest) {
            $vars['caller_known']          = 'true';
            $vars['guest_name']            = trim($matchedGuest['first_name'] . ' ' . $matchedGuest['last_name']);
            $vars['guest_first_name']      = $matchedGuest['first_name'];
            $vars['guest_last_name']       = $matchedGuest['last_name'];
            $vars['guest_phone']           = $matchedGuest['phone'] ?? '';
            $vars['guest_email']           = $matchedGuest['email'] ?? '';
            $vars['guest_tags']            = $matchedGuest['tags'] ?? '';
            $vars['guest_dietary']         = $matchedGuest['dietary_restrictions'] ?? '';
            $vars['guest_allergies']       = $matchedGuest['allergies'] ?? '';
            $vars['guest_seating_pref']    = $matchedGuest['seating_preference'] ?? '';
            $vars['guest_notes']           = $matchedGuest['notes'] ?? '';
            $vars['guest_visit_count']     = (string)($matchedGuest['visit_count'] ?? '0');
            $vars['guest_noshow_count']    = (string)($matchedGuest['noshow_count'] ?? '0');
            $vars['guest_favorite_server'] = $matchedGuest['favorite_server'] ?? '';

            $meta['guest_id'] = (string)$matchedGuest['id'];
        }
    }

    // --- Load custom prompt sections from settings ---
    $promptKeys = ['voice_agent_greeting', 'voice_agent_specials', 'voice_agent_custom_info'];
    $placeholders = implode(',', array_fill(0, count($promptKeys), '?'));
    $stmt = $pdo->prepare(
        "SELECT setting_key, setting_value FROM settings
         WHERE restaurant_id = ? AND setting_key IN ({$placeholders})"
    );
    $stmt->execute(array_merge([$restaurantId], $promptKeys));
    $promptSettings = $stmt->fetchAll();

    foreach ($promptSettings as $ps) {
        $val = trim($ps['setting_value'] ?? '');
        if ($val !== '') {
            $vars[$ps['setting_key']] = $val;
        }
    }

    // --- Override with agent-specific prompt from restaurant_prompts ---
    $agentPrompt = null;
    if ($queryAgentId !== '') {
        $stmt = $pdo->prepare(
            "SELECT id, agent_id, phone_number, voice_agent_greeting, description
             FROM restaurant_prompts
             WHERE restaurant_id = ? AND agent_id = ?"
        );
        $stmt->execute([$restaurantId, $queryAgentId]);
        $agentPrompt = $stmt->fetch();
    }

    if (!$agentPrompt && $toNumber !== '') {
        $stmt = $pdo->prepare(
            "SELECT id, agent_id, phone_number, voice_agent_greeting, description
             FROM restaurant_prompts
             WHERE restaurant_id = ? AND phone_number = ?"
        );
        $stmt->execute([$restaurantId, $toNumber]);
        $agentPrompt = $stmt->fetch();
    }

    if ($agentPrompt) {
        if (!empty(trim($agentPrompt['voice_agent_greeting'] ?? ''))) {
            $vars['voice_agent_greeting'] = trim($agentPrompt['voice_agent_greeting']);
        }
        if (!empty($agentPrompt['phone_number'])) {
            $vars['agent_phone_number'] = $agentPrompt['phone_number'];
        }
        if (!empty($agentPrompt['description'])) {
            $vars['agent_description'] = $agentPrompt['description'];
        }
        $meta['agent_id'] = $agentPrompt['agent_id'];

        $detailStmt = $pdo->prepare(
            "SELECT variable_name, variable_value FROM restaurant_prompt_details
             WHERE restaurant_prompt_id = ? AND is_active = 1"
        );
        $detailStmt->execute([$agentPrompt['id']]);
        foreach ($detailStmt->fetchAll() as $d) {
            $vars[$d['variable_name']] = $d['variable_value'];
        }
    }

    // --- Log the inbound call start ---
    try {
        $desc = "Inbound call started from {$fromNumber} to {$toNumber}";
        if (isset($vars['guest_name']) && $vars['guest_name'] !== '') {
            $desc .= " (known guest: {$vars['guest_name']})";
        }
        $stmt = $pdo->prepare(
            "INSERT INTO activity_log (restaurant_id, user_id, action, entity_type, description, ip_address, created_at)
             VALUES (?, NULL, 'voice_call_started', 'voice_call', ?, ?, NOW())"
        );
        $stmt->execute([$restaurantId, $desc, $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0']);
    } catch (Exception $e) {
        wlog("ACTIVITY LOG ERROR: " . $e->getMessage());
    }

    // --- Return Retell call_inbound response ---
    $response = [
        'call_inbound' => [
            'dynamic_variables' => $vars,
            'metadata'          => $meta,
        ],
    ];

    wlog("DYNAMIC VARIABLES: " . json_encode($vars, JSON_PRETTY_PRINT));
    wlog("=== END ===\n");

    echo json_encode($response);
    exit;
}

// =====================================================================
// EVENT: call_ended — Save call data to call_logs and activity_log
// =====================================================================
if ($event === 'call_ended') {

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

    wlog("duration_ms: {$durationMs}, status: {$status}, recording: " . ($recordingUrl ?: '(none)'));

    $pdo = db();

    // --- Match guest by from_number ---
    $guestId = null;
    if ($fromNumber !== '' && $restaurantId) {
        $fromDigits = normalizePhone($fromNumber);
        $from10 = strlen($fromDigits) > 10 ? substr($fromDigits, -10) : $fromDigits;
        $fromE164 = '+1' . $from10;

        $stmt = $pdo->prepare(
            "SELECT id, phone FROM guests
             WHERE restaurant_id = ? AND phone IS NOT NULL AND phone != '' AND is_active = 1"
        );
        $stmt->execute([$restaurantId]);
        foreach ($stmt->fetchAll() as $g) {
            $gDigits = normalizePhone($g['phone']);
            $g10 = strlen($gDigits) > 10 ? substr($gDigits, -10) : $gDigits;
            $gE164 = '+1' . $g10;
            if ($fromE164 === $gE164) {
                $guestId = (int)$g['id'];
                wlog("GUEST MATCHED — id: {$guestId}");
                break;
            }
        }
    }

    // --- Build extra metadata ---
    $callMeta = [];
    if ($publicLogUrl !== '') {
        $callMeta['public_log_url'] = $publicLogUrl;
    }
    if ($startTimestamp !== null) {
        $callMeta['start_timestamp'] = $startTimestamp;
    }
    if ($endTimestamp !== null) {
        $callMeta['end_timestamp'] = $endTimestamp;
    }
    if (isset($call['call_analysis']) && is_array($call['call_analysis'])) {
        $callMeta['call_analysis'] = $call['call_analysis'];
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
            !empty($callMeta) ? json_encode($callMeta) : null,
            $createdAt,
            $endedAt,
        ]);
        wlog("INSERTED call_logs row id: " . $pdo->lastInsertId());
    } catch (Exception $e) {
        wlog("ERROR inserting call_logs: " . $e->getMessage());
    }

    // --- Audit entry in activity_log ---
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
        $stmt->execute([$restaurantId, $desc, $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0']);
    } catch (Exception $e) {
        wlog("ERROR inserting activity_log: " . $e->getMessage());
    }

    wlog("=== END ===\n");
    echo json_encode(['success' => true]);
    exit;
}

// =====================================================================
// EVENT: call_started, call_analyzed, or unknown — acknowledge only
// =====================================================================
wlog("Event '{$event}' acknowledged — no action taken");
wlog("=== END ===\n");
echo json_encode(['success' => true]);
exit;
