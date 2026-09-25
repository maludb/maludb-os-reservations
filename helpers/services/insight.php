<?php
/**
 * Insight service — read-only dashboard numbers, reports, message logs and the activity log.
 * Used by html/partials/reports/data.php and the API (helpers/api/ops/insight.php).
 */

require_once __DIR__ . '/_base.php';

const INSIGHT_DIRECTIONS = ['inbound', 'outbound'];

/** Today's key numbers: reservations, covers, occupancy, waitlist, next arrivals, alerts. */
function svc_insight_dashboard(int $rid): array
{
    $pdo = db();
    $today = date('Y-m-d');
    $now = date('H:i:s');
    $one = function (string $sql, array $params) use ($pdo) {
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        return (int)$stmt->fetchColumn();
    };

    $totalTables = $one("SELECT COUNT(*) FROM tables WHERE restaurant_id = ? AND is_active = 1", [$rid]);
    $occupied = $one("SELECT COUNT(*) FROM tables WHERE restaurant_id = ? AND is_active = 1 AND status = 'occupied'", [$rid]);

    $stmt = $pdo->prepare(
        "SELECT r.id, r.reservation_time, r.party_size, r.status, r.confirmation_code,
                g.first_name, g.last_name, t.table_name
         FROM reservations r JOIN guests g ON r.guest_id = g.id LEFT JOIN tables t ON r.table_id = t.id
         WHERE r.restaurant_id = ? AND r.reservation_date = ? AND r.reservation_time BETWEEN ? AND ?
           AND r.status IN ('pending', 'confirmed')
         ORDER BY r.reservation_time LIMIT 15"
    );
    $stmt->execute([$rid, $today, $now, date('H:i:s', strtotime('+2 hours'))]);
    $upcoming = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $stmt = $pdo->prepare(
        "SELECT r.id, r.reservation_date, r.reservation_time, r.party_size, r.confirmation_code, g.first_name, g.last_name
         FROM reservations r JOIN guests g ON r.guest_id = g.id
         WHERE r.restaurant_id = ? AND r.reservation_date IN (?, ?) AND r.status = 'pending'
         ORDER BY r.reservation_date, r.reservation_time LIMIT 10"
    );
    $stmt->execute([$rid, $today, date('Y-m-d', strtotime('+1 day'))]);
    $unconfirmed = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $stmt = $pdo->prepare(
        "SELECT id, name, event_date, start_time, max_capacity, status FROM events
         WHERE restaurant_id = ? AND event_date BETWEEN ? AND ? AND status IN ('draft', 'published')
         ORDER BY event_date, start_time LIMIT 3"
    );
    $stmt->execute([$rid, $today, date('Y-m-d', strtotime('+30 days'))]);
    $events = $stmt->fetchAll(PDO::FETCH_ASSOC);

    return [
        'date' => $today,
        'time' => $now,
        'reservations_today' => $one("SELECT COUNT(*) FROM reservations WHERE restaurant_id = ? AND reservation_date = ? AND status != 'cancelled'", [$rid, $today]),
        'covers_today' => $one("SELECT COALESCE(SUM(party_size), 0) FROM reservations WHERE restaurant_id = ? AND reservation_date = ? AND status != 'cancelled'", [$rid, $today]),
        'no_shows_today' => $one("SELECT COUNT(*) FROM reservations WHERE restaurant_id = ? AND reservation_date = ? AND status = 'no_show'", [$rid, $today]),
        'tables_total' => $totalTables,
        'tables_occupied' => $occupied,
        'occupancy_pct' => $totalTables > 0 ? (int)round($occupied / $totalTables * 100) : 0,
        'waitlist_waiting' => $one("SELECT COUNT(*) FROM waitlist WHERE restaurant_id = ? AND status IN ('waiting', 'notified')", [$rid]),
        'arrivals_next_2_hours' => $upcoming,
        'unconfirmed_today_tomorrow' => $unconfirmed,
        'upcoming_events' => $events,
    ];
}

/**
 * Reports for a date range (default last 30 days). Keys match the variables
 * html/partials/reports/data.php renders.
 */
function svc_insight_report(int $rid, array $in): array
{
    $start = svc_date($in['start_date'] ?? '', 'start_date', false) ?? date('Y-m-d', strtotime('-30 days'));
    $end = svc_date($in['end_date'] ?? '', 'end_date', false) ?? date('Y-m-d');
    if ($start > $end) svc_fail('start_date must be on or before end_date.', 'start_date');

    $pdo = db();
    $base = "FROM reservations WHERE restaurant_id = ? AND reservation_date BETWEEN ? AND ?";
    $p = [$rid, $start, $end];
    $val = function (string $sql) use ($pdo, $p) {
        $stmt = $pdo->prepare($sql);
        $stmt->execute($p);
        return $stmt->fetchColumn();
    };
    $rows = function (string $sql) use ($pdo, $p) {
        $stmt = $pdo->prepare($sql);
        $stmt->execute($p);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    };

    $total = (int)$val("SELECT COUNT(*) {$base} AND status != 'cancelled'");
    $noshowCount = (int)$val("SELECT COUNT(*) {$base} AND status = 'no_show'");
    $outcomeTotal = max(1, (int)$val("SELECT COUNT(*) {$base} AND status IN ('completed', 'no_show', 'seated')"));
    $cancelCount = (int)$val("SELECT COUNT(*) {$base} AND status = 'cancelled'");
    $allTotal = max(1, (int)$val("SELECT COUNT(*) {$base}"));

    $byDay = $rows("SELECT reservation_date AS d, COUNT(*) AS cnt {$base} AND status != 'cancelled' GROUP BY reservation_date ORDER BY reservation_date");
    $byHour = $rows("SELECT EXTRACT(HOUR FROM reservation_time)::int AS h, SUM(party_size) AS covers {$base} AND status != 'cancelled' GROUP BY 1 ORDER BY 1");

    $stmt = $pdo->prepare(
        "SELECT s.name AS section_name, COUNT(r.id) AS res_count
         FROM reservations r JOIN tables t ON r.table_id = t.id JOIN sections s ON t.section_id = s.id
         WHERE r.restaurant_id = ? AND r.reservation_date BETWEEN ? AND ? AND r.status != 'cancelled'
         GROUP BY s.id, s.name ORDER BY res_count DESC"
    );
    $stmt->execute($p);
    $bySection = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // PostgreSQL DOW: 0 = Sunday
    $heatRaw = $rows("SELECT EXTRACT(DOW FROM reservation_date)::int AS dow, EXTRACT(HOUR FROM reservation_time)::int AS h, COUNT(*) AS cnt
                      {$base} AND status != 'cancelled' GROUP BY 1, 2 ORDER BY 1, 2");
    $heatmap = [];
    foreach (['Sun', 'Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat'] as $i => $dayName) {
        $data = [];
        foreach ($heatRaw as $row) {
            if ((int)$row['dow'] === $i) $data[] = ['x' => date('ga', mktime((int)$row['h'], 0)), 'y' => (int)$row['cnt']];
        }
        if ($data) $heatmap[] = ['name' => $dayName, 'data' => $data];
    }

    $bySource = $rows("SELECT source, COUNT(*) AS cnt {$base} AND status != 'cancelled' GROUP BY source");

    $stmt = $pdo->prepare(
        "SELECT SUM(CASE WHEN g.visit_count <= 1 THEN 1 ELSE 0 END) AS new_guests,
                SUM(CASE WHEN g.visit_count > 1 THEN 1 ELSE 0 END) AS returning_guests
         FROM reservations r JOIN guests g ON r.guest_id = g.id
         WHERE r.restaurant_id = ? AND r.reservation_date BETWEEN ? AND ? AND r.status != 'cancelled'"
    );
    $stmt->execute($p);
    $freq = $stmt->fetch(PDO::FETCH_ASSOC);

    return [
        'startDate' => $start,
        'endDate' => $end,
        'totalReservations' => $total,
        'avgPartySize' => $val("SELECT ROUND(AVG(party_size), 1) {$base} AND status != 'cancelled'") ?: '0',
        'noshowCount' => $noshowCount,
        'noshowRate' => round($noshowCount / $outcomeTotal * 100, 1),
        'cancelCount' => $cancelCount,
        'cancelRate' => round($cancelCount / $allTotal * 100, 1),
        'dayLabels' => array_column($byDay, 'd'),
        'dayCounts' => array_map('intval', array_column($byDay, 'cnt')),
        'hourLabels' => array_map(fn($r) => date('ga', mktime((int)$r['h'], 0)), $byHour),
        'hourCovers' => array_map(fn($r) => (int)$r['covers'], $byHour),
        'sectionLabels' => array_column($bySection, 'section_name'),
        'sectionCounts' => array_map('intval', array_column($bySection, 'res_count')),
        'heatmapSeries' => $heatmap,
        'sourceLabels' => array_map(fn($r) => ucfirst(str_replace('_', ' ', $r['source'])), $bySource),
        'sourceCounts' => array_map(fn($r) => (int)$r['cnt'], $bySource),
        'newGuests' => (int)($freq['new_guests'] ?? 0),
        'returningGuests' => (int)($freq['returning_guests'] ?? 0),
    ];
}

/** Report in agent-friendly snake_case shape. */
function svc_insight_report_data(int $rid, array $in): array
{
    $r = svc_insight_report($rid, $in);
    return [
        'start_date' => $r['startDate'],
        'end_date' => $r['endDate'],
        'metrics' => [
            'reservations' => $r['totalReservations'],
            'avg_party_size' => (float)$r['avgPartySize'],
            'no_shows' => $r['noshowCount'],
            'no_show_rate_pct' => $r['noshowRate'],
            'cancellations' => $r['cancelCount'],
            'cancellation_rate_pct' => $r['cancelRate'],
            'new_guests' => $r['newGuests'],
            'returning_guests' => $r['returningGuests'],
        ],
        'reservations_by_day' => array_map(fn($d, $c) => ['date' => $d, 'count' => $c], $r['dayLabels'], $r['dayCounts']),
        'covers_by_hour' => array_map(fn($h, $c) => ['hour' => $h, 'covers' => $c], $r['hourLabels'], $r['hourCovers']),
        'reservations_by_section' => array_map(fn($s, $c) => ['section' => $s, 'count' => $c], $r['sectionLabels'], $r['sectionCounts']),
        'reservations_by_source' => array_map(fn($s, $c) => ['source' => $s, 'count' => $c], $r['sourceLabels'], $r['sourceCounts']),
        'peak_heatmap' => $r['heatmapSeries'],
    ];
}

/**
 * Paged message log. $kind: email | sms | voice.
 * Filters: direction, search, date_from, date_to, limit, offset.
 */
function svc_insight_messages(int $rid, string $kind, array $in): array
{
    [$table, $searchCols] = match ($kind) {
        'email' => ['email_message_log', ['from_address', 'to_address', 'subject', 'body_text']],
        'sms'   => ['sms_message_log', ['from_number', 'to_number', 'body']],
        'voice' => ['call_logs', ['from_number', 'to_number', 'call_summary']],
    };
    $where = ['restaurant_id = ?'];
    $params = [$rid];
    if (in_array($in['direction'] ?? '', INSIGHT_DIRECTIONS, true)) {
        $where[] = 'direction = ?';
        $params[] = $in['direction'];
    }
    $search = trim($in['search'] ?? '');
    if ($search !== '') {
        $where[] = '(' . implode(' OR ', array_map(fn($c) => "{$c} ILIKE ?", $searchCols)) . ')';
        array_push($params, ...array_fill(0, count($searchCols), '%' . $search . '%'));
    }
    if ($d = svc_date($in['date_from'] ?? '', 'date_from', false)) { $where[] = 'created_at >= ?'; $params[] = $d; }
    if ($d = svc_date($in['date_to'] ?? '', 'date_to', false)) { $where[] = "created_at < (?::date + 1)"; $params[] = $d; }
    $whereClause = implode(' AND ', $where);

    $stmt = db()->prepare("SELECT COUNT(*) FROM {$table} WHERE {$whereClause}");
    $stmt->execute($params);
    $total = (int)$stmt->fetchColumn();

    [$limit, $offset] = svc_page($in);
    $stmt = db()->prepare("SELECT * FROM {$table} WHERE {$whereClause} ORDER BY created_at DESC LIMIT {$limit} OFFSET {$offset}");
    $stmt->execute($params);
    return ['total' => $total, 'messages' => $stmt->fetchAll(PDO::FETCH_ASSOC)];
}

/** Paged activity log. Filters: entity_type, entity_id, action, user_id, date_from, date_to. */
function svc_insight_activity(int $rid, array $in): array
{
    $where = ['a.restaurant_id = ?'];
    $params = [$rid];
    foreach (['entity_type', 'action'] as $f) {
        if (!empty($in[$f])) { $where[] = "a.{$f} = ?"; $params[] = $in[$f]; }
    }
    foreach (['entity_id', 'user_id'] as $f) {
        if (!empty($in[$f])) { $where[] = "a.{$f} = ?"; $params[] = (int)$in[$f]; }
    }
    if ($d = svc_date($in['date_from'] ?? '', 'date_from', false)) { $where[] = 'a.created_at >= ?'; $params[] = $d; }
    if ($d = svc_date($in['date_to'] ?? '', 'date_to', false)) { $where[] = "a.created_at < (?::date + 1)"; $params[] = $d; }
    $whereClause = implode(' AND ', $where);

    $stmt = db()->prepare("SELECT COUNT(*) FROM activity_log a WHERE {$whereClause}");
    $stmt->execute($params);
    $total = (int)$stmt->fetchColumn();

    [$limit, $offset] = svc_page($in);
    $stmt = db()->prepare(
        "SELECT a.id, a.action, a.entity_type, a.entity_id, a.description, a.old_value, a.new_value,
                a.user_id, u.first_name AS user_first, u.last_name AS user_last, a.ip_address, a.created_at
         FROM activity_log a LEFT JOIN users u ON a.user_id = u.id
         WHERE {$whereClause} ORDER BY a.created_at DESC, a.id DESC LIMIT {$limit} OFFSET {$offset}"
    );
    $stmt->execute($params);
    return ['total' => $total, 'entries' => $stmt->fetchAll(PDO::FETCH_ASSOC)];
}
