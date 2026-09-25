<?php
/**
 * Reservation service — staff-side reservation management.
 * Used by html/partials/reservations/* and the API (helpers/api/ops/reservations.php).
 */

require_once __DIR__ . '/_base.php';
require_once __DIR__ . '/../restaurant.php';
require_once __DIR__ . '/../availability.php';
require_once __DIR__ . '/tables.php';

const RESERVATION_STATUSES = ['pending', 'confirmed', 'seated', 'completed', 'cancelled', 'no_show'];
const RESERVATION_SOURCES = ['online', 'phone', 'walk_in', 'staff'];

/** Allowed status changes: from => [to, ...] */
const RESERVATION_TRANSITIONS = [
    'pending'   => ['confirmed', 'cancelled'],
    'confirmed' => ['seated', 'cancelled', 'no_show'],
    'seated'    => ['completed'],
];

/** Search reservations. Filters: date_from, date_to, status, search, include_walk_ins, limit, offset. */
function svc_reservation_list(int $rid, array $in): array
{
    $where = ['r.restaurant_id = ?'];
    $params = [$rid];

    if (empty($in['include_walk_ins'])) $where[] = "r.source != 'walk_in'";

    $search = trim($in['search'] ?? '');
    if ($search !== '') {
        $where[] = "(g.first_name ILIKE ? OR g.last_name ILIKE ? OR g.phone ILIKE ? OR g.email ILIKE ?
                     OR r.confirmation_code ILIKE ? OR CONCAT(g.first_name, ' ', g.last_name) ILIKE ?)";
        array_push($params, ...array_fill(0, 6, '%' . $search . '%'));
    }
    if ($d = svc_date($in['date_from'] ?? '', 'date_from', false)) { $where[] = 'r.reservation_date >= ?'; $params[] = $d; }
    if ($d = svc_date($in['date_to'] ?? '', 'date_to', false)) { $where[] = 'r.reservation_date <= ?'; $params[] = $d; }
    if (!empty($in['status'])) {
        $where[] = 'r.status = ?';
        $params[] = $in['status'];
    } else {
        $where[] = "r.status != 'cancelled'";
    }

    $whereClause = implode(' AND ', $where);
    $stmt = db()->prepare("SELECT COUNT(*) FROM reservations r JOIN guests g ON r.guest_id = g.id WHERE {$whereClause}");
    $stmt->execute($params);
    $total = (int)$stmt->fetchColumn();

    [$limit, $offset] = svc_page($in);
    $stmt = db()->prepare(
        "SELECT r.*, g.first_name, g.last_name, g.phone AS guest_phone, g.email AS guest_email, t.table_name
         FROM reservations r
         JOIN guests g ON r.guest_id = g.id
         LEFT JOIN tables t ON r.table_id = t.id
         WHERE {$whereClause}
         ORDER BY r.reservation_date DESC, r.reservation_time DESC
         LIMIT {$limit} OFFSET {$offset}"
    );
    $stmt->execute($params);
    return ['total' => $total, 'reservations' => $stmt->fetchAll(PDO::FETCH_ASSOC)];
}

/** One reservation with guest, table, section, creator and recent history. */
function svc_reservation_get(int $rid, int $id): array
{
    $stmt = db()->prepare(
        "SELECT r.*, g.first_name, g.last_name, g.phone AS guest_phone, g.email AS guest_email,
                g.tags AS guest_tags, g.visit_count, g.noshow_count,
                t.table_name, s.name AS section_name,
                u.first_name AS created_by_first, u.last_name AS created_by_last
         FROM reservations r
         JOIN guests g ON r.guest_id = g.id
         LEFT JOIN tables t ON r.table_id = t.id
         LEFT JOIN sections s ON t.section_id = s.id
         LEFT JOIN users u ON r.created_by = u.id
         WHERE r.id = ? AND r.restaurant_id = ?"
    );
    $stmt->execute([$id, $rid]);
    $res = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$res) svc_not_found('Reservation');

    $stmt = db()->prepare(
        "SELECT al.action, al.description, al.old_value, al.new_value, al.created_at,
                u.first_name AS user_first, u.last_name AS user_last
         FROM activity_log al LEFT JOIN users u ON al.user_id = u.id
         WHERE al.restaurant_id = ? AND al.entity_type = 'reservation' AND al.entity_id = ?
         ORDER BY al.created_at DESC LIMIT 20"
    );
    $stmt->execute([$rid, $id]);
    $res['history'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
    $res['allowed_status_changes'] = RESERVATION_TRANSITIONS[$res['status']] ?? [];
    return $res;
}

/** Time slots for a date and party size (staff view: includes slots staff may overbook). */
function svc_reservation_availability(int $rid, string $date, int $partySize): array
{
    svc_date($date, 'date');
    if ($partySize < 1) svc_fail('Party size must be at least 1.', 'party_size');
    if ($date < date('Y-m-d')) svc_fail('Cannot check availability for past dates.', 'date');
    return getAvailableSlots($rid, $date, $partySize, true);
}

/** Returns $tableId if it belongs to the restaurant, else throws. */
function svc_reservation_check_table(int $rid, ?int $tableId): ?int
{
    if (!$tableId) return null;
    $stmt = db()->prepare("SELECT id FROM tables WHERE id = ? AND restaurant_id = ?");
    $stmt->execute([$tableId, $rid]);
    if (!$stmt->fetch()) svc_fail('Invalid table.', 'table_id');
    return $tableId;
}

/**
 * Staff-created reservation (auto-confirmed). Finds or creates the guest by
 * guest_id, email or phone; auto-assigns a table when none is given.
 * Input: reservation_date, reservation_time, party_size, first_name, last_name,
 *        phone, email, guest_id, table_id, source, special_requests, internal_notes, turn_time
 */
function svc_reservation_create(int $rid, int $uid, array $in): array
{
    $date = svc_date($in['reservation_date'] ?? '', 'reservation_date');
    $time = trim($in['reservation_time'] ?? '');
    if ($time === '') svc_fail('Please select a time slot.', 'reservation_time');
    $time = svc_time($time, 'reservation_time');
    $partySize = (int)($in['party_size'] ?? 0);
    if ($partySize < 1) svc_fail('Party size must be at least 1.', 'party_size');
    $firstName = trim($in['first_name'] ?? '');
    $lastName = trim($in['last_name'] ?? '');
    if ($firstName === '' || $lastName === '') svc_fail('Guest first and last name are required.', 'name');
    $phone = trim($in['phone'] ?? '');
    $email = trim($in['email'] ?? '');
    $guestId = (int)($in['guest_id'] ?? 0);
    $tableId = (int)($in['table_id'] ?? 0) ?: null;
    $source = in_array($in['source'] ?? '', RESERVATION_SOURCES, true) ? $in['source'] : 'phone';
    $specialRequests = trim($in['special_requests'] ?? '') ?: null;
    $internalNotes = trim($in['internal_notes'] ?? '') ?: null;
    $turnTime = (int)($in['turn_time'] ?? 0) ?: null;

    $pdo = db();
    $pdo->beginTransaction();
    try {
        if ($guestId > 0) {
            $stmt = $pdo->prepare("SELECT id FROM guests WHERE id = ? AND restaurant_id = ?");
            $stmt->execute([$guestId, $rid]);
            if (!$stmt->fetch()) $guestId = 0;
        }
        if ($guestId <= 0) {
            foreach (['email' => $email, 'phone' => $phone] as $col => $val) {
                if ($guestId > 0 || $val === '') continue;
                $stmt = $pdo->prepare("SELECT id FROM guests WHERE restaurant_id = ? AND {$col} = ? LIMIT 1");
                $stmt->execute([$rid, $val]);
                $guestId = (int)($stmt->fetchColumn() ?: 0);
            }
            if ($guestId <= 0) {
                $stmt = $pdo->prepare(
                    "INSERT INTO guests (restaurant_id, first_name, last_name, email, phone, visit_count, noshow_count, is_active)
                     VALUES (?, ?, ?, ?, ?, 0, 0, 1) RETURNING id"
                );
                $stmt->execute([$rid, $firstName, $lastName, $email ?: null, $phone ?: null]);
                $guestId = (int)$stmt->fetchColumn();
            } else {
                $pdo->prepare("UPDATE guests SET first_name = ?, last_name = ?, phone = COALESCE(?, phone), email = COALESCE(?, email) WHERE id = ?")
                    ->execute([$firstName, $lastName, $phone ?: null, $email ?: null, $guestId]);
            }
        }

        if ($tableId) {
            $stmt = $pdo->prepare("SELECT id FROM tables WHERE id = ? AND restaurant_id = ?");
            $stmt->execute([$tableId, $rid]);
            if (!$stmt->fetch()) $tableId = null;
        }
        if (!$tableId) $tableId = findAvailableTable($rid, $date, $time, $partySize);

        $code = generateConfirmationCode($rid, $date);
        $stmt = $pdo->prepare(
            "INSERT INTO reservations (restaurant_id, guest_id, table_id, reservation_date, reservation_time,
                    party_size, status, confirmation_code, source, special_requests, turn_time_minutes,
                    internal_notes, created_by)
             VALUES (?, ?, ?, ?, ?, ?, 'confirmed', ?, ?, ?, ?, ?, ?) RETURNING id"
        );
        $stmt->execute([$rid, $guestId, $tableId, $date, $time, $partySize, $code, $source,
                        $specialRequests, $turnTime, $internalNotes, $uid ?: null]);
        $id = (int)$stmt->fetchColumn();

        svc_log($rid, $uid, 'create', 'reservation', $id,
            "Created reservation #{$code} for {$firstName} {$lastName}, party of {$partySize} on {$date} at " . date('g:ia', strtotime($time)));
        $pdo->commit();
    } catch (Throwable $e) {
        $pdo->rollBack();
        throw $e;
    }
    return svc_reservation_get($rid, $id);
}

/**
 * Change any of: reservation_date, reservation_time, party_size, table_id,
 * turn_time_minutes, special_requests, internal_notes. Only keys present are changed.
 */
function svc_reservation_update(int $rid, int $uid, int $id, array $changes): array
{
    $res = svc_row('reservations', $id, $rid, 'Reservation');
    $fields = [];

    if (array_key_exists('reservation_date', $changes)) $fields['reservation_date'] = svc_date($changes['reservation_date'], 'reservation_date');
    if (array_key_exists('reservation_time', $changes)) $fields['reservation_time'] = svc_time($changes['reservation_time'], 'reservation_time');
    if (array_key_exists('party_size', $changes)) {
        if ((int)$changes['party_size'] < 1) svc_fail('Party size must be at least 1.', 'party_size');
        $fields['party_size'] = (int)$changes['party_size'];
    }
    if (array_key_exists('table_id', $changes)) $fields['table_id'] = svc_reservation_check_table($rid, (int)$changes['table_id'] ?: null);
    if (array_key_exists('turn_time_minutes', $changes)) {
        $fields['turn_time_minutes'] = ($changes['turn_time_minutes'] === '' || $changes['turn_time_minutes'] === null) ? null : (int)$changes['turn_time_minutes'];
    }
    foreach (['special_requests', 'internal_notes'] as $f) {
        if (array_key_exists($f, $changes)) $fields[$f] = trim((string)$changes[$f]) ?: null;
    }
    if (!$fields) svc_fail('Nothing to update.');

    $sets = implode(', ', array_map(fn($f) => "{$f} = ?", array_keys($fields)));
    db()->prepare("UPDATE reservations SET {$sets} WHERE id = ? AND restaurant_id = ?")
        ->execute([...array_values($fields), $id, $rid]);

    $old = array_intersect_key($res, $fields);
    svc_log($rid, $uid, 'update', 'reservation', $id,
        "Edited reservation #{$res['confirmation_code']}: " . implode(', ', array_keys($fields)), $old, $fields);
    return svc_reservation_get($rid, $id);
}

/** Move a reservation through its lifecycle; sends guest notifications. */
function svc_reservation_set_status(int $rid, int $uid, int $id, string $newStatus): array
{
    $res = svc_row('reservations', $id, $rid, 'Reservation');
    $allowed = RESERVATION_TRANSITIONS[$res['status']] ?? [];
    if (!in_array($newStatus, $allowed, true)) {
        throw new ServiceError("Invalid status transition from {$res['status']} to {$newStatus}."
            . ($allowed ? ' Allowed: ' . implode(', ', $allowed) . '.' : ''), 'INVALID_TRANSITION', 409, 'status');
    }

    $sets = ['status = ?'];
    if ($newStatus === 'seated') {
        $sets[] = 'seated_at = NOW()';
        $sets[] = "meal_status = 'seated'";
    } elseif ($newStatus === 'completed') {
        $sets[] = 'completed_at = NOW()';
        $sets[] = 'meal_status = NULL';
    } elseif ($newStatus === 'cancelled') {
        $sets[] = 'cancelled_at = NOW()';
        $sets[] = 'meal_status = NULL';
    }

    $pdo = db();
    $pdo->beginTransaction();
    try {
        $pdo->prepare("UPDATE reservations SET " . implode(', ', $sets) . " WHERE id = ? AND restaurant_id = ?")
            ->execute([$newStatus, $id, $rid]);
        if ($newStatus === 'no_show') {
            $pdo->prepare("UPDATE guests SET noshow_count = noshow_count + 1 WHERE id = ?")->execute([$res['guest_id']]);
        } elseif ($newStatus === 'completed') {
            $pdo->prepare("UPDATE guests SET visit_count = visit_count + 1, last_visit_at = ? WHERE id = ?")
                ->execute([$res['reservation_date'], $res['guest_id']]);
            svc_table_release($res['table_id'] ? (int)$res['table_id'] : null);
        }
        svc_log($rid, $uid, 'status_change', 'reservation', $id,
            "Status changed from {$res['status']} to {$newStatus} for reservation #{$res['confirmation_code']}",
            ['status' => $res['status']], ['status' => $newStatus]);
        $pdo->commit();
    } catch (Throwable $e) {
        $pdo->rollBack();
        throw $e;
    }

    require_once __DIR__ . '/../notifications.php';
    if ($newStatus === 'cancelled') {
        sendCancellationEmail($id);
    } elseif ($newStatus === 'confirmed') {
        sendConfirmationEmail($id);
        sendConfirmationSMS($id);
    }
    return svc_reservation_get($rid, $id);
}
