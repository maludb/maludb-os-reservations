<?php
/**
 * Guest service — the restaurant's guest book.
 * Used by html/partials/guests/* and the API (helpers/api/ops/guests.php).
 */

require_once __DIR__ . '/_base.php';

/** Guest fields an update may set (text fields; '' clears). */
const GUEST_TEXT_FIELDS = ['email', 'phone', 'address_line1', 'address_line2', 'city', 'state', 'postal_code',
                           'tags', 'dietary_restrictions', 'allergies', 'seating_preference', 'favorite_server', 'notes'];
const GUEST_FLAG_FIELDS = ['do_not_call', 'do_not_email', 'do_not_text'];

/** Paged guest list. Filters: search (name/email/phone), tag, limit, offset. */
function svc_guest_list(int $rid, array $in): array
{
    $where = ['restaurant_id = ?', 'is_active = 1'];
    $params = [$rid];
    $search = trim($in['search'] ?? '');
    if ($search !== '') {
        $where[] = "(first_name ILIKE ? OR last_name ILIKE ? OR email ILIKE ? OR phone ILIKE ? OR CONCAT(first_name, ' ', last_name) ILIKE ?)";
        array_push($params, ...array_fill(0, 5, '%' . $search . '%'));
    }
    $tag = strtolower(trim($in['tag'] ?? ''));
    if ($tag !== '') {
        $where[] = "? = ANY(regexp_split_to_array(tags, '\\s*,\\s*'))";
        $params[] = $tag;
    }
    $whereClause = implode(' AND ', $where);

    $stmt = db()->prepare("SELECT COUNT(*) FROM guests WHERE {$whereClause}");
    $stmt->execute($params);
    $total = (int)$stmt->fetchColumn();

    [$limit, $offset] = svc_page($in);
    $stmt = db()->prepare("SELECT * FROM guests WHERE {$whereClause} ORDER BY last_name, first_name LIMIT {$limit} OFFSET {$offset}");
    $stmt->execute($params);
    return ['total' => $total, 'guests' => $stmt->fetchAll(PDO::FETCH_ASSOC)];
}

/** Quick name/email/phone lookup, max 10 results (query must be 2+ characters). */
function svc_guest_search(int $rid, string $q): array
{
    $q = trim($q);
    if (strlen($q) < 2) svc_fail('Search text must be at least 2 characters.', 'q');
    $s = '%' . $q . '%';
    $stmt = db()->prepare(
        "SELECT id, first_name, last_name, email, phone, tags, visit_count, noshow_count
         FROM guests
         WHERE restaurant_id = ?
           AND (first_name ILIKE ? OR last_name ILIKE ? OR email ILIKE ? OR phone ILIKE ? OR CONCAT(first_name, ' ', last_name) ILIKE ?)
         ORDER BY last_name, first_name LIMIT 10"
    );
    $stmt->execute([$rid, $s, $s, $s, $s, $s]);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

/** One guest with their reservation history (newest first, max 100). */
function svc_guest_get(int $rid, int $id): array
{
    $guest = svc_row('guests', $id, $rid, 'Guest');
    $stmt = db()->prepare(
        "SELECT r.id, r.reservation_date, r.reservation_time, r.party_size, r.status, r.confirmation_code,
                r.source, r.special_requests, r.event_id, t.table_name
         FROM reservations r LEFT JOIN tables t ON r.table_id = t.id
         WHERE r.guest_id = ? AND r.restaurant_id = ?
         ORDER BY r.reservation_date DESC, r.reservation_time DESC LIMIT 100"
    );
    $stmt->execute([$id, $rid]);
    $guest['reservations'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
    return $guest;
}

/** Create a guest. Requires first_name, last_name. */
function svc_guest_create(int $rid, int $uid, array $in): array
{
    $values = svc_guest_values($in, true);
    $cols = array_keys($values);
    $stmt = db()->prepare(
        "INSERT INTO guests (restaurant_id, " . implode(', ', $cols) . ", visit_count, noshow_count, is_active)
         VALUES (?" . str_repeat(', ?', count($cols)) . ", 0, 0, 1) RETURNING id"
    );
    $stmt->execute([$rid, ...array_values($values)]);
    $id = (int)$stmt->fetchColumn();
    svc_log($rid, $uid, 'create', 'guest', $id, "Created guest {$values['first_name']} {$values['last_name']}");
    return svc_guest_get($rid, $id);
}

/** Update a guest. Only keys present in $changes are changed. */
function svc_guest_update(int $rid, int $uid, int $id, array $changes): array
{
    $old = svc_row('guests', $id, $rid, 'Guest');
    $values = svc_guest_values($changes, false);
    if (!$values) svc_fail('Nothing to update.');
    $sets = implode(', ', array_map(fn($c) => "{$c} = ?", array_keys($values)));
    db()->prepare("UPDATE guests SET {$sets}, updated_at = NOW() WHERE id = ? AND restaurant_id = ?")
        ->execute([...array_values($values), $id, $rid]);
    svc_log($rid, $uid, 'update', 'guest', $id, "Updated guest {$old['first_name']} {$old['last_name']}: " . implode(', ', array_keys($values)),
        array_intersect_key($old, $values), $values);
    return svc_guest_get($rid, $id);
}

/** Add or remove one tag (tags are a lower-case comma list). */
function svc_guest_tag(int $rid, int $uid, int $id, string $tag, bool $add): array
{
    $guest = svc_row('guests', $id, $rid, 'Guest');
    $tag = strtolower(trim($tag));
    if ($tag === '' || str_contains($tag, ',')) svc_fail('Select a tag to add.', 'tag');
    $tags = $guest['tags'] ? array_filter(array_map('trim', explode(',', $guest['tags']))) : [];
    $tags = $add ? array_unique([...$tags, $tag]) : array_filter($tags, fn($t) => $t !== $tag);
    return svc_guest_update($rid, $uid, $id, ['tags' => implode(',', $tags)]);
}

/** Validate and normalise guest input. $create requires names. */
function svc_guest_values(array $in, bool $create): array
{
    $v = [];
    foreach (['first_name', 'last_name'] as $f) {
        if ($create || array_key_exists($f, $in)) {
            $v[$f] = trim((string)($in[$f] ?? ''));
            if ($v[$f] === '') svc_fail('First and last name are required.', $f);
        }
    }
    foreach (GUEST_TEXT_FIELDS as $f) {
        if (array_key_exists($f, $in)) $v[$f] = trim((string)$in[$f]) ?: null;
    }
    if (isset($v['tags'])) {
        $v['tags'] = implode(',', array_unique(array_filter(array_map(fn($t) => strtolower(trim($t)), explode(',', $v['tags']))))) ?: null;
    }
    if (!empty($v['email']) && !filter_var($v['email'], FILTER_VALIDATE_EMAIL)) {
        svc_fail('Please enter a valid email address.', 'email');
    }
    foreach (GUEST_FLAG_FIELDS as $f) {
        if (array_key_exists($f, $in)) $v[$f] = $in[$f] ? 1 : 0;
    }
    return $v;
}
