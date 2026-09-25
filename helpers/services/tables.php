<?php
/**
 * Table service — tables, live floor status, walk-ins and meal progress.
 * Used by html/partials/tables/* and the API (helpers/api/ops/tables.php).
 */

require_once __DIR__ . '/_base.php';
require_once __DIR__ . '/../restaurant.php';
require_once __DIR__ . '/../meal-status.php';

const TABLE_STATUSES = ['available', 'occupied', 'reserved', 'blocked'];
const TABLE_MEAL_STATUSES = ['seated', 'ordering', 'eating', 'check_dropped', 'paid'];

/** Tables with section names. Filters: section_id, include_inactive. */
function svc_table_list(int $rid, array $in = []): array
{
    $where = ['t.restaurant_id = ?'];
    $params = [$rid];
    if (!empty($in['section_id'])) { $where[] = 't.section_id = ?'; $params[] = (int)$in['section_id']; }
    if (empty($in['include_inactive'])) $where[] = 't.is_active = 1';
    $stmt = db()->prepare(
        "SELECT t.*, s.name AS section_name FROM tables t JOIN sections s ON t.section_id = s.id
         WHERE " . implode(' AND ', $where) . " ORDER BY s.display_order ASC, t.sort_order ASC, t.table_name ASC"
    );
    $stmt->execute($params);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

function svc_table_get(int $rid, int $id): array
{
    $stmt = db()->prepare(
        "SELECT t.*, s.name AS section_name FROM tables t JOIN sections s ON t.section_id = s.id
         WHERE t.id = ? AND t.restaurant_id = ?"
    );
    $stmt->execute([$id, $rid]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$row) svc_not_found('Table');
    return $row;
}

/**
 * Create ($id = 0) or update a table. All fields are written.
 * Input: table_name, section_id, min_seats, max_seats, status, sort_order, notes, is_active
 */
function svc_table_save(int $rid, int $id, array $in): array
{
    $name = trim($in['table_name'] ?? '');
    $sectionId = (int)($in['section_id'] ?? 0);
    $minSeats = (int)($in['min_seats'] ?? 1);
    $maxSeats = (int)($in['max_seats'] ?? 2);
    $status = trim($in['status'] ?? 'available') ?: 'available';
    $sortOrder = (int)($in['sort_order'] ?? 0);
    $notes = trim($in['notes'] ?? '') ?: null;
    $isActive = !empty($in['is_active']) ? 1 : 0;

    if ($name === '') svc_fail('Table name is required.', 'name');
    if ($sectionId <= 0) svc_fail('Please select a section.', 'section');
    if ($minSeats < 1 || $maxSeats < 1) svc_fail('Seats must be at least 1.', 'seats-min');
    if ($minSeats > $maxSeats) svc_fail('Min seats cannot exceed max seats.', 'seats-range');
    if (!in_array($status, TABLE_STATUSES, true)) svc_fail('Invalid status.', 'status');

    $stmt = db()->prepare("SELECT id FROM sections WHERE id = ? AND restaurant_id = ?");
    $stmt->execute([$sectionId, $rid]);
    if (!$stmt->fetch()) svc_fail('Invalid section.', 'section-invalid');

    $stmt = db()->prepare("SELECT id FROM tables WHERE restaurant_id = ? AND table_name = ? AND id != ?");
    $stmt->execute([$rid, $name, $id]);
    if ($stmt->fetch()) svc_fail('A table with this name already exists.', 'dup');

    if ($id > 0) {
        svc_table_get($rid, $id);
        db()->prepare(
            "UPDATE tables SET table_name = ?, section_id = ?, min_seats = ?, max_seats = ?,
                    status = ?, sort_order = ?, notes = ?, is_active = ?
             WHERE id = ? AND restaurant_id = ?"
        )->execute([$name, $sectionId, $minSeats, $maxSeats, $status, $sortOrder, $notes, $isActive, $id, $rid]);
    } else {
        $stmt = db()->prepare(
            "INSERT INTO tables (restaurant_id, table_name, section_id, min_seats, max_seats, status, sort_order, notes, is_active)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?) RETURNING id"
        );
        $stmt->execute([$rid, $name, $sectionId, $minSeats, $maxSeats, $status, $sortOrder, $notes, $isActive]);
        $id = (int)$stmt->fetchColumn();
    }
    return svc_table_get($rid, $id);
}

/** Activate or deactivate a table; $active = null toggles. */
function svc_table_set_active(int $rid, int $id, ?bool $active = null): array
{
    $table = svc_table_get($rid, $id);
    $new = $active === null ? ($table['is_active'] ? 0 : 1) : ($active ? 1 : 0);
    db()->prepare("UPDATE tables SET is_active = ? WHERE id = ? AND restaurant_id = ?")->execute([$new, $id, $rid]);
    return svc_table_get($rid, $id);
}

/**
 * Live floor status for today: every active table in every active section with its
 * effective status (available / reserved / occupied / blocked), the reservation on it,
 * meal progress and estimated time the table frees up.
 */
function svc_table_floor_status(int $rid): array
{
    $stmt = db()->prepare(
        "SELECT t.id, t.table_name, t.min_seats, t.max_seats, t.status, t.notes, s.id AS section_id, s.name AS section_name
         FROM tables t JOIN sections s ON t.section_id = s.id
         WHERE t.restaurant_id = ? AND t.is_active = 1 AND s.is_active = 1
         ORDER BY s.display_order ASC, t.sort_order ASC, t.table_name ASC"
    );
    $stmt->execute([$rid]);
    $tables = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $stmt = db()->prepare(
        "SELECT r.id, r.table_id, r.confirmation_code, r.reservation_time, r.party_size, r.status,
                r.turn_time_minutes, r.seated_at, r.meal_status, g.first_name, g.last_name
         FROM reservations r JOIN guests g ON r.guest_id = g.id
         WHERE r.restaurant_id = ? AND r.reservation_date = CURRENT_DATE
           AND r.status IN ('confirmed', 'seated') AND r.table_id IS NOT NULL
         ORDER BY r.reservation_time ASC"
    );
    $stmt->execute([$rid]);
    $byTable = [];
    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $r) {
        $tid = (int)$r['table_id'];
        // Prefer seated over confirmed, otherwise keep the earliest
        if (!isset($byTable[$tid]) || $r['status'] === 'seated') $byTable[$tid] = $r;
    }

    foreach ($tables as &$t) {
        $res = $byTable[(int)$t['id']] ?? null;
        $t['effective_status'] = $t['status'] === 'blocked' ? 'blocked'
            : ($res && $res['status'] === 'seated' ? 'occupied' : ($res ? 'reserved' : 'available'));
        $t['reservation'] = $res;
        if ($t['effective_status'] === 'occupied') {
            $meal = $res['meal_status'] ?: 'seated';
            $turn = (int)($res['turn_time_minutes'] ?: 90);
            $t['meal_status'] = $meal;
            $t['minutes_remaining'] = estimatedMinutesRemaining($meal, $turn, $rid);
            $t['estimated_free_at'] = estimatedAvailableTime($meal, $turn, $res['seated_at'], $rid);
        }
    }
    return $tables;
}

/** The reservation seated at a table today, or null. */
function svc_table_seated_reservation(int $rid, int $tableId): ?array
{
    $stmt = db()->prepare(
        "SELECT r.id, r.party_size, r.reservation_time, r.seated_at, r.turn_time_minutes, r.meal_status,
                r.confirmation_code, r.special_requests, r.ordering_at, r.eating_at, r.check_dropped_at, r.paid_at,
                g.first_name, g.last_name
         FROM reservations r JOIN guests g ON r.guest_id = g.id
         WHERE r.restaurant_id = ? AND r.table_id = ? AND r.reservation_date = CURRENT_DATE AND r.status = 'seated'
         ORDER BY r.seated_at DESC LIMIT 1"
    );
    $stmt->execute([$rid, $tableId]);
    return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
}

/**
 * Seat a walk-in at a table now: creates a guest (a CRM guest only when named)
 * and a seated walk_in reservation. Input: party_size, first_name, last_name.
 */
function svc_table_seat_walkin(int $rid, int $uid, int $tableId, array $in): array
{
    $firstName = trim($in['first_name'] ?? '');
    $lastName = trim($in['last_name'] ?? '');
    $partySize = (int)($in['party_size'] ?? 1);
    if ($partySize < 1 || !$tableId) svc_fail('Party size and table are required.', 'party_size');

    $stmt = db()->prepare("SELECT id FROM tables WHERE id = ? AND restaurant_id = ?");
    $stmt->execute([$tableId, $rid]);
    if (!$stmt->fetch()) svc_fail('Invalid table.', 'table_id');

    $pdo = db();
    $pdo->beginTransaction();
    try {
        $isNamed = $firstName !== '';
        $stmt = $pdo->prepare(
            "INSERT INTO guests (restaurant_id, first_name, last_name, visit_count, noshow_count, is_active)
             VALUES (?, ?, ?, 0, 0, ?) RETURNING id"
        );
        $stmt->execute([$rid, $isNamed ? $firstName : 'Walk-in', $isNamed ? ($lastName ?: '') : 'Guest', $isNamed ? 1 : 0]);
        $guestId = (int)$stmt->fetchColumn();

        $today = date('Y-m-d');
        $code = generateConfirmationCode($rid, $today);
        $stmt = $pdo->prepare(
            "INSERT INTO reservations (restaurant_id, guest_id, table_id, reservation_date, reservation_time,
                    party_size, status, meal_status, confirmation_code, source, seated_at, created_by)
             VALUES (?, ?, ?, ?, ?, ?, 'seated', 'seated', ?, 'walk_in', NOW(), ?) RETURNING id"
        );
        $stmt->execute([$rid, $guestId, $tableId, $today, date('H:i:s'), $partySize, $code, $uid ?: null]);
        $resId = (int)$stmt->fetchColumn();

        $label = $isNamed ? "{$firstName} " . ($lastName ?: 'Walk-in') : 'Anonymous';
        svc_log($rid, $uid, 'create', 'reservation', $resId, "Walk-in seated: {$label}, party of {$partySize}");
        $pdo->commit();
    } catch (Throwable $e) {
        $pdo->rollBack();
        throw $e;
    }
    return ['reservation_id' => $resId, 'confirmation_code' => $code, 'table_id' => $tableId, 'party_size' => $partySize];
}

/** Seated reservation by id, or throw. */
function svc_table_require_seated(int $rid, int $resId): array
{
    $stmt = db()->prepare("SELECT id, status, meal_status, table_id FROM reservations WHERE id = ? AND restaurant_id = ? AND status = 'seated'");
    $stmt->execute([$resId, $rid]);
    $res = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$res) throw new ServiceError('Reservation not found or not seated.', 'NOT_FOUND', 404);
    return $res;
}

/** Record meal progress for a seated reservation (seated, ordering, eating, check_dropped, paid). */
function svc_table_set_meal_status(int $rid, int $resId, string $mealStatus): array
{
    svc_table_require_seated($rid, $resId);
    if (!in_array($mealStatus, TABLE_MEAL_STATUSES, true)) svc_fail('Invalid meal status.', 'meal_status');

    $stampColumns = ['ordering' => 'ordering_at', 'eating' => 'eating_at', 'check_dropped' => 'check_dropped_at', 'paid' => 'paid_at'];
    $sql = "UPDATE reservations SET meal_status = ?" . (isset($stampColumns[$mealStatus]) ? ", {$stampColumns[$mealStatus]} = NOW()" : '') . " WHERE id = ?";
    db()->prepare($sql)->execute([$mealStatus, $resId]);
    return ['reservation_id' => $resId, 'meal_status' => $mealStatus];
}

/**
 * Set a table back to 'available' after its party leaves. Only an 'occupied'
 * table is changed (a 'blocked' one stays blocked), and only if no other
 * party is still seated there.
 */
function svc_table_release(?int $tableId): void
{
    if (!$tableId) return;
    db()->prepare(
        "UPDATE tables SET status = 'available'
         WHERE id = ? AND status = 'occupied'
           AND NOT EXISTS (SELECT 1 FROM reservations WHERE table_id = ? AND status = 'seated')"
    )->execute([$tableId, $tableId]);
}

/** Finish a seated reservation and free its table. */
function svc_table_complete(int $rid, int $uid, int $resId): array
{
    $res = svc_table_require_seated($rid, $resId);
    db()->prepare("UPDATE reservations SET status = 'completed', meal_status = NULL, completed_at = NOW() WHERE id = ?")
        ->execute([$resId]);
    svc_table_release($res['table_id'] ? (int)$res['table_id'] : null);
    svc_log($rid, $uid, 'update', 'reservation', $resId, 'Table completed and opened');
    return ['reservation_id' => $resId, 'status' => 'completed'];
}
