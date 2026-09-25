<?php
/**
 * Section service — floor sections that group tables.
 * Used by html/partials/sections/* and the API (helpers/api/ops/sections.php).
 */

require_once __DIR__ . '/_base.php';

/** All sections, with their table counts. */
function svc_section_list(int $rid, array $in = []): array
{
    $where = 's.restaurant_id = ?';
    if (empty($in['include_inactive'])) $where .= ' AND s.is_active = 1';
    $stmt = db()->prepare(
        "SELECT s.*, (SELECT COUNT(*) FROM tables t WHERE t.section_id = s.id AND t.is_active = 1) AS active_tables
         FROM sections s WHERE {$where} ORDER BY s.display_order ASC, s.name ASC"
    );
    $stmt->execute([$rid]);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

function svc_section_get(int $rid, int $id): array
{
    return svc_row('sections', $id, $rid, 'Section');
}

/**
 * Create ($id = 0) or update a section. All fields are written.
 * Input: name, description, display_order, open_time, close_time, is_active
 */
function svc_section_save(int $rid, int $id, array $in): array
{
    $name = trim($in['name'] ?? '');
    $description = trim($in['description'] ?? '') ?: null;
    $displayOrder = (int)($in['display_order'] ?? 0);
    $openTime = trim((string)($in['open_time'] ?? '')) ?: null;
    $closeTime = trim((string)($in['close_time'] ?? '')) ?: null;
    $isActive = !empty($in['is_active']) ? 1 : 0;

    if ($name === '') svc_fail('Section name is required.', 'name');
    if (($openTime && !$closeTime) || (!$openTime && $closeTime)) {
        svc_fail('Both open and close times must be set, or leave both blank.', 'hours');
    }
    if ($openTime) $openTime = svc_time($openTime, 'open_time');
    if ($closeTime) $closeTime = svc_time($closeTime, 'close_time');
    if ($openTime && $closeTime && $openTime >= $closeTime) svc_fail('Open time must be before close time.', 'hours-order');

    $stmt = db()->prepare("SELECT id FROM sections WHERE restaurant_id = ? AND name = ? AND id != ?");
    $stmt->execute([$rid, $name, $id]);
    if ($stmt->fetch()) svc_fail('A section with this name already exists.', 'dup');

    if ($id > 0) {
        svc_section_get($rid, $id);
        db()->prepare(
            "UPDATE sections SET name = ?, description = ?, display_order = ?, open_time = ?, close_time = ?, is_active = ?
             WHERE id = ? AND restaurant_id = ?"
        )->execute([$name, $description, $displayOrder, $openTime, $closeTime, $isActive, $id, $rid]);
    } else {
        $stmt = db()->prepare(
            "INSERT INTO sections (restaurant_id, name, description, display_order, open_time, close_time, is_active)
             VALUES (?, ?, ?, ?, ?, ?, ?) RETURNING id"
        );
        $stmt->execute([$rid, $name, $description, $displayOrder, $openTime, $closeTime, $isActive]);
        $id = (int)$stmt->fetchColumn();
    }
    return svc_section_get($rid, $id);
}

/** Activate or deactivate a section; $active = null toggles. */
function svc_section_set_active(int $rid, int $id, ?bool $active = null): array
{
    $section = svc_section_get($rid, $id);
    $new = $active === null ? ($section['is_active'] ? 0 : 1) : ($active ? 1 : 0);
    db()->prepare("UPDATE sections SET is_active = ? WHERE id = ? AND restaurant_id = ?")->execute([$new, $id, $rid]);
    return svc_section_get($rid, $id);
}
