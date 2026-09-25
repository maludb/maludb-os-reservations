<?php
/**
 * Waitlist service — walk-in queue and the host-stand actions on today's reservations
 * (check in, seat at a table, "table ready" notification).
 * Used by html/partials/waitlist/* and the API (helpers/api/ops/waitlist.php).
 */

require_once __DIR__ . '/_base.php';
require_once __DIR__ . '/../restaurant.php';

const WAITLIST_STATUSES = ['waiting', 'notified', 'seated', 'left', 'no_response'];
const WAITLIST_FINAL_STATUSES = ['seated', 'left', 'no_response'];

/** Display name of a waitlist row joined with guests. */
function svc_waitlist_name(array $entry): string
{
    return $entry['guest_id'] ? ($entry['first_name'] . ' ' . $entry['last_name']) : ($entry['guest_name'] ?: 'Unknown');
}

/**
 * Host-stand view: active queue (waiting/notified) in queue order, entries finished
 * in the last 2 hours, and today's pending/confirmed reservations.
 */
function svc_waitlist_list(int $rid): array
{
    $pdo = db();
    $stmt = $pdo->prepare(
        "SELECT w.*, g.first_name, g.last_name, g.phone AS guest_phone
         FROM waitlist w LEFT JOIN guests g ON w.guest_id = g.id
         WHERE w.restaurant_id = ? AND w.status IN ('waiting', 'notified')
         ORDER BY w.queue_position ASC"
    );
    $stmt->execute([$rid]);
    $active = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $stmt = $pdo->prepare(
        "SELECT w.*, g.first_name, g.last_name
         FROM waitlist w LEFT JOIN guests g ON w.guest_id = g.id
         WHERE w.restaurant_id = ? AND w.status IN ('seated', 'left', 'no_response')
           AND w.updated_at >= NOW() - INTERVAL '2 hours'
         ORDER BY w.updated_at DESC LIMIT 20"
    );
    $stmt->execute([$rid]);
    $recent = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $stmt = $pdo->prepare(
        "SELECT r.id, r.confirmation_code, r.reservation_time, r.party_size, r.status, r.checked_in_at,
                r.special_requests, g.first_name, g.last_name, g.phone AS guest_phone
         FROM reservations r JOIN guests g ON r.guest_id = g.id
         WHERE r.restaurant_id = ? AND r.reservation_date = CURRENT_DATE
           AND r.status IN ('pending', 'confirmed') AND r.source != 'walk_in'
         ORDER BY r.reservation_time ASC"
    );
    $stmt->execute([$rid]);

    return ['waiting' => $active, 'recent' => $recent, 'todays_reservations' => $stmt->fetchAll(PDO::FETCH_ASSOC)];
}

/** Active (waiting/notified) entry or throw. */
function svc_waitlist_active_entry(int $rid, int $id): array
{
    $stmt = db()->prepare(
        "SELECT w.*, g.first_name, g.last_name, g.email, g.phone AS guest_phone
         FROM waitlist w LEFT JOIN guests g ON w.guest_id = g.id
         WHERE w.id = ? AND w.restaurant_id = ? AND w.status IN ('waiting', 'notified')"
    );
    $stmt->execute([$id, $rid]);
    $entry = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$entry) throw new ServiceError('Waitlist entry not found or already processed.', 'NOT_FOUND', 404);
    return $entry;
}

/**
 * Active tables not held by a confirmed or seated reservation today that fit the party.
 * When none fit, returns every open table (the host may still choose one).
 */
function svc_waitlist_open_tables(int $rid, int $partySize): array
{
    $sql = "SELECT t.id, t.table_name, t.min_seats, t.max_seats, s.name AS section_name
            FROM tables t JOIN sections s ON t.section_id = s.id
            WHERE t.restaurant_id = ? AND t.is_active = 1 %s
              AND t.id NOT IN (
                  SELECT table_id FROM reservations
                  WHERE restaurant_id = ? AND reservation_date = CURRENT_DATE
                    AND status IN ('confirmed', 'seated') AND table_id IS NOT NULL
              )
            ORDER BY t.min_seats ASC";
    $stmt = db()->prepare(sprintf($sql, 'AND t.max_seats >= ?'));
    $stmt->execute([$rid, $partySize, $rid]);
    $tables = $stmt->fetchAll(PDO::FETCH_ASSOC);
    if ($tables) return ['fits_party' => true, 'tables' => $tables];

    $stmt = db()->prepare(sprintf($sql, ''));
    $stmt->execute([$rid, $rid]);
    return ['fits_party' => false, 'tables' => $stmt->fetchAll(PDO::FETCH_ASSOC)];
}

/**
 * Add a party to the end of the queue.
 * Input: guest_name, party_size, quoted_wait_minutes, phone, guest_id, seating_preference, notes
 */
function svc_waitlist_add(int $rid, int $uid, array $in): array
{
    $guestName = trim($in['guest_name'] ?? '');
    $partySize = (int)($in['party_size'] ?? 0);
    $quoted = isset($in['quoted_wait_minutes']) && $in['quoted_wait_minutes'] !== '' ? (int)$in['quoted_wait_minutes'] : null;
    if ($guestName === '' || $partySize < 1) svc_fail('Guest name and party size are required.', 'guest_name');
    if ($quoted === null || $quoted < 0) svc_fail('Please enter the quoted wait time.', 'quoted_wait_minutes');

    $guestId = (int)($in['guest_id'] ?? 0) ?: null;
    if ($guestId) {
        $stmt = db()->prepare("SELECT id FROM guests WHERE id = ? AND restaurant_id = ?");
        $stmt->execute([$guestId, $rid]);
        if (!$stmt->fetch()) svc_fail('Invalid guest.', 'guest_id');
    }

    $pdo = db();
    $stmt = $pdo->prepare(
        "SELECT COALESCE(MAX(queue_position), 0) + 1 FROM waitlist WHERE restaurant_id = ? AND status IN ('waiting', 'notified')"
    );
    $stmt->execute([$rid]);
    $pos = (int)$stmt->fetchColumn();

    $stmt = $pdo->prepare(
        "INSERT INTO waitlist (restaurant_id, guest_id, guest_name, party_size, phone,
         estimated_wait_minutes, status, queue_position, seating_preference, notes, added_by)
         VALUES (?, ?, ?, ?, ?, ?, 'waiting', ?, ?, ?, ?) RETURNING id"
    );
    $stmt->execute([
        $rid, $guestId, $guestName, $partySize, trim($in['phone'] ?? '') ?: null, $quoted, $pos,
        trim($in['seating_preference'] ?? '') ?: null, trim($in['notes'] ?? '') ?: null, $uid ?: null,
    ]);
    $id = (int)$stmt->fetchColumn();
    svc_log($rid, $uid, 'create', 'waitlist', $id, "Added {$guestName} (party of {$partySize}) to waitlist at position #{$pos}");

    return ['id' => $id, 'queue_position' => $pos, 'estimated_wait_minutes' => $quoted];
}

/**
 * Finish a queue entry: 'left', 'no_response', or 'seated' (needs $tableId; creates the
 * guest if needed and a seated walk_in reservation).
 */
function svc_waitlist_set_status(int $rid, int $uid, int $id, string $status, int $tableId = 0): array
{
    if (!in_array($status, WAITLIST_FINAL_STATUSES, true)) svc_fail('Invalid status.', 'status');
    $entry = svc_waitlist_active_entry($rid, $id);
    $name = svc_waitlist_name($entry);
    $result = ['id' => $id, 'status' => $status, 'name' => $name];

    $pdo = db();
    $pdo->beginTransaction();
    try {
        if ($status === 'seated') {
            if ($tableId < 1) svc_fail('Please select a table.', 'table_id');
            $stmt = $pdo->prepare("SELECT id, table_name FROM tables WHERE id = ? AND restaurant_id = ? AND is_active = 1");
            $stmt->execute([$tableId, $rid]);
            $table = $stmt->fetch(PDO::FETCH_ASSOC);
            if (!$table) svc_fail('Table not found or unavailable.', 'table_id');

            $guestId = $entry['guest_id'];
            if (!$guestId) {
                $parts = explode(' ', $entry['guest_name'], 2);
                $stmt = $pdo->prepare(
                    "INSERT INTO guests (restaurant_id, first_name, last_name, phone, visit_count, noshow_count, is_active)
                     VALUES (?, ?, ?, ?, 0, 0, 1) RETURNING id"
                );
                $stmt->execute([$rid, $parts[0] ?: 'Walk', $parts[1] ?? 'In', $entry['phone'] ?: null]);
                $guestId = (int)$stmt->fetchColumn();
            }

            $code = generateConfirmationCode($rid, date('Y-m-d'));
            $stmt = $pdo->prepare(
                "INSERT INTO reservations (restaurant_id, guest_id, table_id, reservation_date, reservation_time,
                 party_size, status, confirmation_code, source, created_by, seated_at)
                 VALUES (?, ?, ?, CURRENT_DATE, CURRENT_TIME, ?, 'seated', ?, 'walk_in', ?, NOW()) RETURNING id"
            );
            $stmt->execute([$rid, $guestId, $tableId, $entry['party_size'], $code, $uid ?: null]);
            $resId = (int)$stmt->fetchColumn();

            $pdo->prepare("UPDATE tables SET status = 'occupied' WHERE id = ?")->execute([$tableId]);
            $pdo->prepare("UPDATE waitlist SET status = 'seated', seated_at = NOW(), reservation_id = ? WHERE id = ?")
                ->execute([$resId, $id]);

            $desc = "Seated {$name} (party of {$entry['party_size']}) from waitlist at {$table['table_name']}";
            $result += ['reservation_id' => $resId, 'confirmation_code' => $code, 'table_name' => $table['table_name']];
        } else {
            $pdo->prepare("UPDATE waitlist SET status = ? WHERE id = ?")->execute([$status, $id]);
            $desc = "Waitlist: {$name} marked as " . str_replace('_', ' ', $status);
        }
        svc_log($rid, $uid, 'status_change', 'waitlist', $id, $desc);
        $pdo->commit();
    } catch (Throwable $e) {
        $pdo->rollBack();
        throw $e;
    }
    return $result;
}

/** "Table ready": mark notified, count the call, text the guest if a phone is on file. */
function svc_waitlist_notify(int $rid, int $uid, int $id): array
{
    $entry = svc_waitlist_active_entry($rid, $id);
    $phone = $entry['phone'] ?: ($entry['guest_phone'] ?? '');
    $name = svc_waitlist_name($entry);
    $count = (int)($entry['notify_count'] ?? 0) + 1;

    db()->prepare("UPDATE waitlist SET status = 'notified', notified_at = NOW(), notify_count = ? WHERE id = ?")
        ->execute([$count, $id]);

    $smsSent = false;
    if ($phone !== '') {
        require_once __DIR__ . '/../notifications.php';
        $smsSent = sendWaitlistSMS($id);
    }
    $method = $phone !== '' ? ($smsSent ? 'SMS sent' : 'SMS failed') : 'called/announced';
    svc_log($rid, $uid, 'notify', 'waitlist', $id, "Table ready for {$name} — {$method} (call #{$count})");

    return ['id' => $id, 'name' => $name, 'phone' => $phone, 'sms_sent' => $smsSent, 'notify_count' => $count];
}

/** Mark every active entry as left. Returns how many were cleared. */
function svc_waitlist_clear(int $rid, int $uid): int
{
    $stmt = db()->prepare("UPDATE waitlist SET status = 'left' WHERE restaurant_id = ? AND status IN ('waiting', 'notified')");
    $stmt->execute([$rid]);
    $cleared = $stmt->rowCount();
    svc_log($rid, $uid, 'waitlist_cleared', 'waitlist', null, "Cleared {$cleared} waitlist entries");
    return $cleared;
}

/** Pending/confirmed reservation with guest, or throw. */
function svc_waitlist_open_reservation(int $rid, int $resId): array
{
    $stmt = db()->prepare(
        "SELECT r.*, g.first_name, g.last_name, g.phone AS guest_phone
         FROM reservations r JOIN guests g ON r.guest_id = g.id
         WHERE r.id = ? AND r.restaurant_id = ? AND r.status IN ('pending', 'confirmed')"
    );
    $stmt->execute([$resId, $rid]);
    $res = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$res) throw new ServiceError('Reservation not found or already seated.', 'NOT_FOUND', 404);
    return $res;
}

/** Guest with a reservation has arrived: set checked_in_at, confirm if pending. */
function svc_waitlist_checkin_reservation(int $rid, int $uid, int $resId): array
{
    $res = svc_row('reservations', $resId, $rid, 'Reservation');
    if (!in_array($res['status'], ['pending', 'confirmed'], true)) {
        throw new ServiceError("Reservation cannot be checked in (status: {$res['status']}).", 'INVALID_TRANSITION', 409);
    }
    $sets = ['checked_in_at = NOW()'];
    if ($res['status'] === 'pending') $sets[] = "status = 'confirmed'";
    db()->prepare("UPDATE reservations SET " . implode(', ', $sets) . " WHERE id = ? AND restaurant_id = ?")->execute([$resId, $rid]);
    svc_log($rid, $uid, 'check_in', 'reservation', $resId, 'Guest checked in');
    return ['reservation_id' => $resId, 'status' => 'confirmed', 'checked_in' => true];
}

/** Seat a pending/confirmed reservation at a table now. */
function svc_waitlist_seat_reservation(int $rid, int $uid, int $resId, int $tableId): array
{
    if (!$tableId) svc_fail('Please select a table.', 'table_id');
    $res = svc_waitlist_open_reservation($rid, $resId);
    $stmt = db()->prepare("SELECT id, table_name FROM tables WHERE id = ? AND restaurant_id = ? AND is_active = 1");
    $stmt->execute([$tableId, $rid]);
    $table = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$table) svc_fail('Table not found or unavailable.', 'table_id');

    $name = $res['first_name'] . ' ' . $res['last_name'];
    $pdo = db();
    $pdo->beginTransaction();
    try {
        $pdo->prepare(
            "UPDATE reservations SET table_id = ?, status = 'seated', seated_at = NOW(), meal_status = 'seated'
             WHERE id = ? AND restaurant_id = ?"
        )->execute([$tableId, $resId, $rid]);
        $pdo->prepare("UPDATE tables SET status = 'occupied' WHERE id = ?")->execute([$tableId]);
        svc_log($rid, $uid, 'status_change', 'reservation', $resId,
            "Seated reservation for {$name} (party of {$res['party_size']}) at {$table['table_name']}");
        $pdo->commit();
    } catch (Throwable $e) {
        $pdo->rollBack();
        throw $e;
    }
    return ['reservation_id' => $resId, 'status' => 'seated', 'name' => $name, 'table_id' => $tableId, 'table_name' => $table['table_name']];
}

/** "Table ready" for a reservation: text the guest if a phone is on file. */
function svc_waitlist_notify_reservation(int $rid, int $uid, int $resId): array
{
    $res = svc_waitlist_open_reservation($rid, $resId);
    $phone = $res['guest_phone'] ?? '';
    $name = $res['first_name'] . ' ' . $res['last_name'];

    $smsSent = false;
    if ($phone !== '') {
        require_once __DIR__ . '/../notifications.php';
        $message = "Hi {$res['first_name']}, your table is ready! Please check in with the host.";
        $result = twilioSend($rid, $phone, $message);
        $smsSent = !empty($result['success']);
        logNotification($rid, $resId, null, (int)$res['guest_id'], 'waitlist_ready', 'sms', $phone, 'Table Ready', $message,
            $smsSent ? 'sent' : 'failed', $result['response'] ?? $result['error'] ?? null);
    }
    $method = $phone !== '' ? ($smsSent ? 'SMS sent' : 'SMS failed') : 'called/announced';
    svc_log($rid, $uid, 'notify', 'reservation', $resId, "Table ready for {$name} — {$method}");

    return ['reservation_id' => $resId, 'name' => $name, 'phone' => $phone, 'sms_sent' => $smsSent];
}
