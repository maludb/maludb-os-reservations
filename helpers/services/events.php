<?php
/**
 * Event service — special events (tasting menus, holidays) with limited capacity.
 * Used by html/partials/events/* and the API (helpers/api/ops/events.php).
 */

require_once __DIR__ . '/_base.php';
require_once __DIR__ . '/../restaurant.php';

const EVENT_STATUSES = ['draft', 'published', 'cancelled', 'completed'];
const EVENT_FILTERS = ['upcoming', 'draft', 'past', 'all'];

/** Covers booked on an event (excludes cancelled and no-shows). */
function svc_event_booked(int $rid, int $eventId): int
{
    $stmt = db()->prepare(
        "SELECT COALESCE(SUM(party_size), 0) FROM reservations
         WHERE event_id = ? AND restaurant_id = ? AND status NOT IN ('cancelled', 'no_show')"
    );
    $stmt->execute([$eventId, $rid]);
    return (int)$stmt->fetchColumn();
}

/** Events with booked covers. filter: upcoming (default) | draft | past | all. */
function svc_event_list(int $rid, array $in): array
{
    $today = date('Y-m-d');
    $where = ['e.restaurant_id = ?'];
    $params = [$rid];
    $filter = $in['filter'] ?? 'upcoming';
    $order = 'e.event_date DESC, e.start_time DESC';
    if ($filter === 'upcoming') {
        $where[] = "e.event_date >= ? AND e.status IN ('draft', 'published')";
        $params[] = $today;
        $order = 'e.event_date ASC, e.start_time ASC';
    } elseif ($filter === 'draft') {
        $where[] = "e.status = 'draft'";
        $order = 'e.event_date ASC, e.start_time ASC';
    } elseif ($filter === 'past') {
        $where[] = "(e.event_date < ? OR e.status IN ('completed', 'cancelled'))";
        $params[] = $today;
    }
    [$limit, $offset] = svc_page($in);
    $stmt = db()->prepare(
        "SELECT e.*,
                (SELECT COALESCE(SUM(r.party_size), 0) FROM reservations r
                 WHERE r.event_id = e.id AND r.status NOT IN ('cancelled', 'no_show')) AS booked_covers,
                (SELECT COUNT(*) FROM reservations r
                 WHERE r.event_id = e.id AND r.status NOT IN ('cancelled', 'no_show')) AS booking_count
         FROM events e WHERE " . implode(' AND ', $where) . "
         ORDER BY {$order} LIMIT {$limit} OFFSET {$offset}"
    );
    $stmt->execute($params);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

/** One event with its bookings and remaining capacity. */
function svc_event_get(int $rid, int $id): array
{
    $event = svc_row('events', $id, $rid, 'Event');
    $stmt = db()->prepare(
        "SELECT r.id, r.party_size, r.status, r.confirmation_code, r.special_requests, r.created_at,
                r.guest_id, g.first_name, g.last_name, g.phone, g.email
         FROM reservations r JOIN guests g ON r.guest_id = g.id
         WHERE r.event_id = ? AND r.restaurant_id = ?
         ORDER BY r.created_at DESC"
    );
    $stmt->execute([$id, $rid]);
    $event['bookings'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
    $event['booked_covers'] = svc_event_booked($rid, $id);
    $event['spots_left'] = max(0, (int)$event['max_capacity'] - $event['booked_covers']);
    return $event;
}

/**
 * Create ($id = 0) or update an event. On update only keys present change.
 * Fields: name, event_date, start_time, end_time, max_capacity, price_per_person,
 *         status, is_public, description, notes
 */
function svc_event_save(int $rid, int $uid, int $id, array $in): array
{
    $old = $id > 0 ? svc_row('events', $id, $rid, 'Event') : null;
    $in = $old ? array_merge($old, $in) : $in;

    $name = trim((string)($in['name'] ?? ''));
    $eventDate = trim((string)($in['event_date'] ?? ''));
    $startTime = trim((string)($in['start_time'] ?? ''));
    if ($name === '' || $eventDate === '' || $startTime === '') svc_fail('Name, date, and start time are required.');
    svc_date($eventDate, 'event_date');
    $startTime = svc_time($startTime, 'start_time');
    $endTime = svc_time((string)($in['end_time'] ?? ''), 'end_time', false);
    $maxCapacity = (int)($in['max_capacity'] ?? ($old ? 0 : 50));
    if ($maxCapacity < 1) svc_fail('Max capacity must be at least 1.', 'max_capacity');
    $price = trim((string)($in['price_per_person'] ?? ''));
    $price = $price !== '' ? (float)$price : null;
    $status = in_array($in['status'] ?? '', EVENT_STATUSES, true) ? $in['status'] : 'draft';
    $isPublic = array_key_exists('is_public', $in) ? ($in['is_public'] ? 1 : 0) : 1;
    $description = trim((string)($in['description'] ?? '')) ?: null;
    $notes = trim((string)($in['notes'] ?? '')) ?: null;

    $values = [$name, $description, $eventDate, $startTime, $endTime, $maxCapacity, $price, $status, $isPublic, $notes];
    if ($old) {
        db()->prepare(
            "UPDATE events SET name = ?, description = ?, event_date = ?, start_time = ?, end_time = ?,
                    max_capacity = ?, price_per_person = ?, status = ?, is_public = ?, notes = ?, updated_at = NOW()
             WHERE id = ? AND restaurant_id = ?"
        )->execute([...$values, $id, $rid]);
    } else {
        $stmt = db()->prepare(
            "INSERT INTO events (name, description, event_date, start_time, end_time, max_capacity,
                    price_per_person, status, is_public, notes, restaurant_id)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?) RETURNING id"
        );
        $stmt->execute([...$values, $rid]);
        $id = (int)$stmt->fetchColumn();
    }
    svc_log($rid, $uid, 'update', 'event', $id, "Event saved: {$name} on {$eventDate}");
    return svc_event_get($rid, $id);
}

function svc_event_cancel(int $rid, int $uid, int $id): array
{
    $event = svc_row('events', $id, $rid, 'Event');
    db()->prepare("UPDATE events SET status = 'cancelled', updated_at = NOW() WHERE id = ? AND restaurant_id = ?")->execute([$id, $rid]);
    svc_log($rid, $uid, 'update', 'event', $id, "Event cancelled: {$event['name']} on {$event['event_date']}");
    return svc_event_get($rid, $id);
}

/**
 * Book a party onto an event (confirmed reservation at the event's date/start time).
 * Input: party_size, first_name, last_name, phone, email, guest_id, special_requests
 * Returns the new reservation row.
 */
function svc_event_book(int $rid, int $uid, int $eventId, array $in): array
{
    $event = svc_row('events', $eventId, $rid, 'Event');
    if (!in_array($event['status'], ['draft', 'published'], true)) {
        throw new ServiceError('This event is not accepting bookings.', 'EVENT_CLOSED', 409);
    }
    $partySize = (int)($in['party_size'] ?? 0);
    if ($partySize < 1) svc_fail('Party size must be at least 1.', 'party_size');
    $firstName = trim((string)($in['first_name'] ?? ''));
    $lastName = trim((string)($in['last_name'] ?? ''));
    if ($firstName === '' || $lastName === '') svc_fail('Guest first and last name are required.', 'name');
    $phone = trim((string)($in['phone'] ?? '')) ?: null;
    $email = trim((string)($in['email'] ?? '')) ?: null;
    $guestId = (int)($in['guest_id'] ?? 0);
    $specialRequests = trim((string)($in['special_requests'] ?? '')) ?: null;

    $spotsLeft = max(0, (int)$event['max_capacity'] - svc_event_booked($rid, $eventId));
    if ($partySize > $spotsLeft) {
        throw new ServiceError("Not enough spots available. Only {$spotsLeft} remaining.", 'CAPACITY_EXCEEDED', 409, 'party_size');
    }

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
                if ($guestId > 0 || $val === null) continue;
                $stmt = $pdo->prepare("SELECT id FROM guests WHERE restaurant_id = ? AND {$col} = ? LIMIT 1");
                $stmt->execute([$rid, $val]);
                $guestId = (int)($stmt->fetchColumn() ?: 0);
            }
            if ($guestId <= 0) {
                $stmt = $pdo->prepare(
                    "INSERT INTO guests (restaurant_id, first_name, last_name, email, phone, visit_count, noshow_count, is_active)
                     VALUES (?, ?, ?, ?, ?, 0, 0, 1) RETURNING id"
                );
                $stmt->execute([$rid, $firstName, $lastName, $email, $phone]);
                $guestId = (int)$stmt->fetchColumn();
            } else {
                $pdo->prepare("UPDATE guests SET first_name = ?, last_name = ?, phone = COALESCE(?, phone), email = COALESCE(?, email) WHERE id = ?")
                    ->execute([$firstName, $lastName, $phone, $email, $guestId]);
            }
        }

        $code = generateConfirmationCode($rid, $event['event_date']);
        $stmt = $pdo->prepare(
            "INSERT INTO reservations (restaurant_id, guest_id, event_id, reservation_date, reservation_time,
                    party_size, status, confirmation_code, source, special_requests, created_by)
             VALUES (?, ?, ?, ?, ?, ?, 'confirmed', ?, 'staff', ?, ?) RETURNING id"
        );
        $stmt->execute([$rid, $guestId, $eventId, $event['event_date'], $event['start_time'],
                        $partySize, $code, $specialRequests, $uid ?: null]);
        $resId = (int)$stmt->fetchColumn();

        svc_log($rid, $uid, 'create', 'reservation', $resId,
            "Event booking: {$firstName} {$lastName} (party of {$partySize}) for {$event['name']} on {$event['event_date']}");
        $pdo->commit();
    } catch (Throwable $e) {
        $pdo->rollBack();
        throw $e;
    }

    $stmt = db()->prepare("SELECT * FROM reservations WHERE id = ?");
    $stmt->execute([$resId]);
    return $stmt->fetch(PDO::FETCH_ASSOC);
}
