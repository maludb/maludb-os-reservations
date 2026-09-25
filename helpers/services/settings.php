<?php
/**
 * Restaurant configuration service — business profile, operating hours,
 * special dates, turn times, meal progress percentages, notification settings,
 * email templates and integration keys.
 *
 * Used by html/partials/settings/* and the API (helpers/api/ops/settings.php,
 * helpers/api/ops/hours.php).
 *
 * ServiceError::$field holds a short code that the partials turn into the
 * alert id they used before (e.g. field 'dup' → id "save-hours-dup-error").
 * Secret settings (API keys) are write-only: read functions return them masked.
 */

require_once __DIR__ . '/_base.php';
require_once __DIR__ . '/../meal-status.php';

const SETTINGS_DAY_NAMES = ['Sunday', 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday'];
const SETTINGS_SPECIAL_DATE_TYPES = ['holiday', 'blackout', 'event', 'modified_hours'];
const SETTINGS_EMAIL_TEMPLATE_KEYS = ['confirmation', 'reminder', 'cancellation', 'waitlist_ready', 'modification', 'thank_you'];
const SETTINGS_PROFILE_FIELDS = ['name', 'phone', 'email', 'address_line1', 'city', 'state', 'postal_code', 'website', 'timezone'];

/** Notification settings: API field => [setting key, kind] (kind: bool | int | text | secret) */
const SETTINGS_NOTIFICATION_KEYS = [
    'confirmation_email'    => ['notification_confirmation_email', 'bool'],
    'confirmation_sms'      => ['notification_confirmation_sms', 'bool'],
    'reminder_email'        => ['notification_reminder_email', 'bool'],
    'cancellation_email'    => ['notification_cancellation_email', 'bool'],
    'waitlist_sms'          => ['notification_waitlist_sms', 'bool'],
    'from_email'            => ['notification_from_email', 'text'],
    'reminder_hours_before' => ['reminder_hours_before', 'int'],
    'mailersend_api_key'    => ['mailersend_api_key', 'secret'],
    'sms_api_key'           => ['sms_api_key', 'secret'],
    'sms_api_secret'        => ['sms_api_secret', 'secret'],
    'sms_from_number'       => ['sms_from_number', 'text'],
];

/** Defaults the Notifications page shows when a setting is missing. */
const SETTINGS_NOTIFICATION_DEFAULTS = [
    'confirmation_email' => '1', 'confirmation_sms' => '0', 'reminder_email' => '1',
    'cancellation_email' => '1', 'waitlist_sms' => '1', 'reminder_hours_before' => '24',
];

/** Integration settings: setting key => kind (secret | text) */
const SETTINGS_INTEGRATION_KEYS = [
    'retell_api_key'          => 'secret',
    'retell_agent_id'         => 'text',
    'mcp_api_key'             => 'secret',
    'openai_api_key'          => 'secret',
    'voice_agent_greeting'    => 'text',
    'voice_agent_specials'    => 'text',
    'voice_agent_custom_info' => 'text',
];

// ── Shared helpers ─────────────────────────────────────────

/** Insert or update one restaurant setting. */
function svc_settings_put(int $rid, string $key, string $value, string $category = 'general'): void
{
    db()->prepare(
        "INSERT INTO settings (restaurant_id, setting_key, setting_value, category)
         VALUES (?, ?, ?, ?)
         ON CONFLICT (restaurant_id, setting_key)
         DO UPDATE SET setting_value = EXCLUDED.setting_value, updated_at = NOW()"
    )->execute([$rid, $key, $value, $category]);
}

/** Setting values for the given keys ([key => value|null]). */
function svc_settings_get_many(int $rid, array $keys): array
{
    $values = array_fill_keys($keys, null);
    if (!$keys) return $values;
    $stmt = db()->prepare(
        "SELECT setting_key, setting_value FROM settings
         WHERE restaurant_id = ? AND setting_key IN (" . implode(',', array_fill(0, count($keys), '?')) . ")"
    );
    $stmt->execute([$rid, ...$keys]);
    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
        $values[$row['setting_key']] = $row['setting_value'];
    }
    return $values;
}

/** Masked form of a secret: '' when not set, otherwise '••••' + last 4 characters. */
function svc_settings_mask(?string $value): string
{
    $value = (string)$value;
    if ($value === '') return '';
    return '••••' . (strlen($value) > 8 ? substr($value, -4) : '');
}

/** Check a required time field and normalise it to HH:MM:SS. */
function svc_settings_time(?string $value, string $field): ?string
{
    $value = trim((string)$value);
    if ($value === '') return null;
    return svc_time($value, $field);
}

/** Boolean-ish input ('1', 'on', true …) as '1' / '0'. */
function svc_settings_flag($value): string
{
    if (is_bool($value)) return $value ? '1' : '0';
    return in_array(strtolower(trim((string)$value)), ['1', 'true', 'yes', 'on'], true) ? '1' : '0';
}

// ── Business profile ───────────────────────────────────────

function svc_settings_profile_get(int $rid): array
{
    $stmt = db()->prepare("SELECT id, slug, " . implode(', ', SETTINGS_PROFILE_FIELDS) . " FROM restaurants WHERE id = ?");
    $stmt->execute([$rid]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$row) svc_not_found('Restaurant');
    return $row;
}

/** Update profile fields; only keys present in $changes are changed. */
function svc_settings_profile_update(int $rid, int $uid, array $changes): array
{
    $old = svc_settings_profile_get($rid);
    $p = [];
    foreach (SETTINGS_PROFILE_FIELDS as $f) {
        $p[$f] = array_key_exists($f, $changes) ? trim((string)$changes[$f]) : (string)$old[$f];
    }

    if ($p['name'] === '') svc_fail('Restaurant name is required.', 'name');
    if ($p['timezone'] === '') svc_fail('Timezone is required.', 'tz');
    if (!in_array($p['timezone'], timezone_identifiers_list(), true)) svc_fail('Timezone must be a valid time zone such as America/Chicago.', 'tz');
    if ($p['email'] !== '' && !filter_var($p['email'], FILTER_VALIDATE_EMAIL)) svc_fail('Please enter a valid email address.', 'email');
    if ($p['website'] !== '' && !filter_var($p['website'], FILTER_VALIDATE_URL)) svc_fail('Please enter a valid website URL.', 'url');

    db()->prepare(
        "UPDATE restaurants SET name = ?, phone = ?, email = ?, address_line1 = ?,
                city = ?, state = ?, postal_code = ?, website = ?, timezone = ?, updated_at = NOW()
         WHERE id = ?"
    )->execute([
        $p['name'], $p['phone'] ?: null, $p['email'] ?: null, $p['address_line1'] ?: null,
        $p['city'] ?: null, $p['state'] ?: null, $p['postal_code'] ?: null, $p['website'] ?: null, $p['timezone'],
        $rid,
    ]);
    return svc_settings_profile_get($rid);
}

// ── Operating hours ────────────────────────────────────────

function svc_hours_list(int $rid): array
{
    $stmt = db()->prepare("SELECT * FROM operating_hours WHERE restaurant_id = ? ORDER BY day_of_week ASC, open_time ASC");
    $stmt->execute([$rid]);
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
    foreach ($rows as &$r) $r['day_name'] = SETTINGS_DAY_NAMES[(int)$r['day_of_week']] ?? '';
    return $rows;
}

function svc_hours_get(int $rid, int $id): array
{
    $row = svc_row('operating_hours', $id, $rid, 'Service period');
    $row['day_name'] = SETTINGS_DAY_NAMES[(int)$row['day_of_week']] ?? '';
    return $row;
}

/**
 * Create ($id = 0) or update a service period.
 * Input: day_of_week (0=Sunday…6), service_name, open_time, close_time, first_seating, last_seating, is_active
 */
function svc_hours_save(int $rid, int $id, array $in): array
{
    if ($id > 0) svc_row('operating_hours', $id, $rid, 'Service period');

    $day = isset($in['day_of_week']) && $in['day_of_week'] !== '' ? (int)$in['day_of_week'] : -1;
    $serviceName = trim($in['service_name'] ?? '');
    if ($day < 0 || $day > 6) svc_fail('Please select a valid day.', 'day');
    if ($serviceName === '') svc_fail('Service name is required.', 'service');

    foreach (['open_time', 'close_time', 'first_seating', 'last_seating'] as $f) {
        if (trim((string)($in[$f] ?? '')) === '') svc_fail('All time fields are required.', 'times');
    }
    $open = svc_settings_time($in['open_time'], 'open_time');
    $close = svc_settings_time($in['close_time'], 'close_time');
    $first = svc_settings_time($in['first_seating'], 'first_seating');
    $last = svc_settings_time($in['last_seating'], 'last_seating');

    if ($open >= $close) svc_fail('Open time must be before close time.', 'oc');
    if ($first < $open) svc_fail('First seating cannot be before open time.', 'fs');
    if ($last > $close) svc_fail('Last seating cannot be after close time.', 'ls');
    if ($first >= $last) svc_fail('First seating must be before last seating.', 'fl');

    $stmt = db()->prepare("SELECT id FROM operating_hours WHERE restaurant_id = ? AND day_of_week = ? AND service_name = ? AND id != ?");
    $stmt->execute([$rid, $day, $serviceName, $id]);
    if ($stmt->fetch()) svc_fail('This day already has a service period with that name.', 'dup');

    $active = svc_settings_flag($in['is_active'] ?? '0');
    if ($id > 0) {
        db()->prepare(
            "UPDATE operating_hours SET day_of_week = ?, service_name = ?, open_time = ?, close_time = ?,
                    first_seating = ?, last_seating = ?, is_active = ?, updated_at = NOW()
             WHERE id = ? AND restaurant_id = ?"
        )->execute([$day, $serviceName, $open, $close, $first, $last, $active, $id, $rid]);
    } else {
        $stmt = db()->prepare(
            "INSERT INTO operating_hours (restaurant_id, day_of_week, service_name, open_time, close_time, first_seating, last_seating, is_active)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?) RETURNING id"
        );
        $stmt->execute([$rid, $day, $serviceName, $open, $close, $first, $last, $active]);
        $id = (int)$stmt->fetchColumn();
    }
    return svc_hours_get($rid, $id);
}

/** Activate/deactivate a service period; $active null = flip. */
function svc_hours_set_active(int $rid, int $id, ?bool $active = null): array
{
    $row = svc_row('operating_hours', $id, $rid, 'Service period');
    $new = $active === null ? ($row['is_active'] ? 0 : 1) : ($active ? 1 : 0);
    db()->prepare("UPDATE operating_hours SET is_active = ?, updated_at = NOW() WHERE id = ? AND restaurant_id = ?")
        ->execute([$new, $id, $rid]);
    return svc_hours_get($rid, $id);
}

function svc_hours_delete(int $rid, int $id): void
{
    svc_row('operating_hours', $id, $rid, 'Service period');
    db()->prepare("DELETE FROM operating_hours WHERE id = ? AND restaurant_id = ?")->execute([$id, $rid]);
}

// ── Special dates ──────────────────────────────────────────

function svc_special_dates_list(int $rid, array $in = []): array
{
    $where = ['restaurant_id = ?'];
    $params = [$rid];
    if ($d = svc_date($in['date_from'] ?? '', 'date_from', false)) { $where[] = 'special_date >= ?'; $params[] = $d; }
    if ($d = svc_date($in['date_to'] ?? '', 'date_to', false)) { $where[] = 'special_date <= ?'; $params[] = $d; }
    $stmt = db()->prepare("SELECT * FROM special_dates WHERE " . implode(' AND ', $where) . " ORDER BY special_date ASC");
    $stmt->execute($params);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

/**
 * Create ($id = 0) or update a special date.
 * Input: special_date, date_type, label, is_closed, custom_open_time, custom_close_time,
 *        custom_first_seating, custom_last_seating, max_covers, outbound_voice_agent, notes
 */
function svc_special_dates_save(int $rid, int $id, array $in): array
{
    if ($id > 0) svc_row('special_dates', $id, $rid, 'Special date');

    $date = trim($in['special_date'] ?? '');
    if ($date === '') svc_fail('Date is required.', 'date');
    svc_date($date, 'date');
    $type = trim($in['date_type'] ?? '');
    if (!in_array($type, SETTINGS_SPECIAL_DATE_TYPES, true)) svc_fail('Please select a valid type.', 'type');
    $label = trim($in['label'] ?? '');
    if ($label === '') svc_fail('Label is required.', 'label');

    $open = svc_settings_time($in['custom_open_time'] ?? '', 'custom_open_time');
    $close = svc_settings_time($in['custom_close_time'] ?? '', 'custom_close_time');
    $first = svc_settings_time($in['custom_first_seating'] ?? '', 'custom_first_seating');
    $last = svc_settings_time($in['custom_last_seating'] ?? '', 'custom_last_seating');
    $set = count(array_filter([$open, $close, $first, $last]));
    if ($set > 0 && $set < 4) svc_fail('If setting custom hours, all four time fields are required.', 'hours');
    if ($set === 4) {
        if ($open >= $close) svc_fail('Open time must be before close time.', 'oc');
        if ($first < $open || $last > $close) svc_fail('Seating times must be within open/close times.', 'seating');
    }

    $maxCovers = trim((string)($in['max_covers'] ?? '')) !== '' ? (int)$in['max_covers'] : null;
    $agent = trim((string)($in['outbound_voice_agent'] ?? '')) ?: null;
    $notes = trim((string)($in['notes'] ?? '')) ?: null;
    $closed = svc_settings_flag($in['is_closed'] ?? '0');

    $stmt = db()->prepare("SELECT id FROM special_dates WHERE restaurant_id = ? AND special_date = ? AND date_type = ? AND id != ?");
    $stmt->execute([$rid, $date, $type, $id]);
    if ($stmt->fetch()) svc_fail('A special date with this date and type already exists.', 'dup');

    $values = [$date, $type, $label, $closed, $open, $close, $first, $last, $maxCovers, $agent, $notes];
    if ($id > 0) {
        db()->prepare(
            "UPDATE special_dates SET special_date = ?, date_type = ?, label = ?, is_closed = ?,
                    custom_open_time = ?, custom_close_time = ?, custom_first_seating = ?, custom_last_seating = ?,
                    max_covers = ?, outbound_voice_agent = ?, notes = ?, updated_at = NOW()
             WHERE id = ? AND restaurant_id = ?"
        )->execute([...$values, $id, $rid]);
    } else {
        $stmt = db()->prepare(
            "INSERT INTO special_dates (special_date, date_type, label, is_closed, custom_open_time, custom_close_time,
                    custom_first_seating, custom_last_seating, max_covers, outbound_voice_agent, notes, restaurant_id)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?) RETURNING id"
        );
        $stmt->execute([...$values, $rid]);
        $id = (int)$stmt->fetchColumn();
    }
    return svc_row('special_dates', $id, $rid, 'Special date');
}

function svc_special_dates_delete(int $rid, int $id): void
{
    svc_row('special_dates', $id, $rid, 'Special date');
    db()->prepare("DELETE FROM special_dates WHERE id = ? AND restaurant_id = ?")->execute([$id, $rid]);
}

/** Non-cancelled reservations on a date (e.g. to call guests before a holiday). */
function svc_special_dates_reservations(int $rid, string $date): array
{
    svc_date($date, 'date');
    $stmt = db()->prepare(
        "SELECT r.*, g.first_name, g.last_name, g.phone, g.email, t.table_name
         FROM reservations r
         JOIN guests g ON r.guest_id = g.id
         LEFT JOIN tables t ON r.table_id = t.id
         WHERE r.restaurant_id = ? AND r.reservation_date = ? AND r.status NOT IN ('cancelled')
         ORDER BY r.reservation_time ASC"
    );
    $stmt->execute([$rid, $date]);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

// ── Turn times ─────────────────────────────────────────────

function svc_turn_times_list(int $rid): array
{
    $stmt = db()->prepare("SELECT * FROM turn_times WHERE restaurant_id = ? ORDER BY min_party_size ASC, service_period ASC");
    $stmt->execute([$rid]);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

/**
 * Create ($id = 0) or update a turn-time rule.
 * Input: min_party_size, max_party_size, service_period ('all' or a service name), duration_minutes, buffer_minutes
 */
function svc_turn_times_save(int $rid, int $id, array $in): array
{
    if ($id > 0) svc_row('turn_times', $id, $rid, 'Turn time');

    $min = (int)($in['min_party_size'] ?? 0);
    $max = (int)($in['max_party_size'] ?? 0);
    $period = trim($in['service_period'] ?? '');
    $duration = (int)($in['duration_minutes'] ?? 0);
    $buffer = (int)($in['buffer_minutes'] ?? 0);

    if ($min < 1 || $max < 1) svc_fail('Party sizes must be at least 1.', 'size');
    if ($min > $max) svc_fail('Min party size cannot exceed max party size.', 'range');
    if ($period === '') svc_fail('Service period is required.', 'service');
    if ($duration < 15) svc_fail('Duration must be at least 15 minutes.', 'duration');
    if ($buffer < 0) svc_fail('Buffer cannot be negative.', 'buffer');

    $stmt = db()->prepare(
        "SELECT id FROM turn_times WHERE restaurant_id = ? AND min_party_size = ? AND max_party_size = ? AND service_period = ? AND id != ?"
    );
    $stmt->execute([$rid, $min, $max, $period, $id]);
    if ($stmt->fetch()) svc_fail('A turn time rule with this party size range and service period already exists.', 'dup');

    if ($id > 0) {
        db()->prepare(
            "UPDATE turn_times SET min_party_size = ?, max_party_size = ?, service_period = ?, duration_minutes = ?,
                    buffer_minutes = ?, updated_at = NOW()
             WHERE id = ? AND restaurant_id = ?"
        )->execute([$min, $max, $period, $duration, $buffer, $id, $rid]);
    } else {
        $stmt = db()->prepare(
            "INSERT INTO turn_times (restaurant_id, min_party_size, max_party_size, service_period, duration_minutes, buffer_minutes)
             VALUES (?, ?, ?, ?, ?, ?) RETURNING id"
        );
        $stmt->execute([$rid, $min, $max, $period, $duration, $buffer]);
        $id = (int)$stmt->fetchColumn();
    }
    return svc_row('turn_times', $id, $rid, 'Turn time');
}

function svc_turn_times_delete(int $rid, int $id): void
{
    svc_row('turn_times', $id, $rid, 'Turn time');
    db()->prepare("DELETE FROM turn_times WHERE id = ? AND restaurant_id = ?")->execute([$id, $rid]);
}

// ── Meal progress percentages ──────────────────────────────

/** [status => percent of the meal completed] */
function svc_meal_percentages_get(int $rid): array
{
    return getMealPercentages($rid);
}

/** Set percentages for the statuses given ([status => 0..100]); others are unchanged. */
function svc_meal_percentages_update(int $rid, array $values): array
{
    $meta = getMealStatusMeta();
    foreach (array_keys(getDefaultMealPercentages()) as $status) {
        if (!array_key_exists($status, $values)) continue;
        $val = max(0, min(100, (int)$values[$status]));
        db()->prepare(
            "INSERT INTO settings (restaurant_id, setting_key, setting_value, category, description)
             VALUES (?, ?, ?, 'operations', ?)
             ON CONFLICT (restaurant_id, setting_key)
             DO UPDATE SET setting_value = EXCLUDED.setting_value, updated_at = NOW()"
        )->execute([$rid, 'meal_pct_' . $status, (string)$val, 'Meal progress % for ' . ($meta[$status]['label'] ?? $status) . ' status']);
    }
    return svc_meal_percentages_get($rid);
}

// ── Notification settings ──────────────────────────────────

/** Notification settings with secrets masked. */
function svc_notifications_get(int $rid): array
{
    $keys = array_column(SETTINGS_NOTIFICATION_KEYS, 0);
    $stored = svc_settings_get_many($rid, $keys);
    $out = [];
    foreach (SETTINGS_NOTIFICATION_KEYS as $field => [$key, $kind]) {
        $v = $stored[$key] ?? (SETTINGS_NOTIFICATION_DEFAULTS[$field] ?? '');
        $out[$field] = match ($kind) {
            'bool'   => $v === '1',
            'int'    => (int)$v,
            'secret' => svc_settings_mask($v),
            default  => (string)$v,
        };
    }
    return $out;
}

/** Update the notification settings present in $changes (API field names). */
function svc_notifications_update(int $rid, array $changes): array
{
    foreach (SETTINGS_NOTIFICATION_KEYS as $field => [$key, $kind]) {
        if (!array_key_exists($field, $changes)) continue;
        $v = $changes[$field];
        if ($kind === 'bool') {
            $v = svc_settings_flag($v);
        } elseif ($kind === 'int') {
            $v = (string)max(1, (int)$v);
        } else {
            $v = trim((string)$v);
            if ($field === 'from_email' && $v !== '' && !filter_var($v, FILTER_VALIDATE_EMAIL)) {
                svc_fail('from_email must be a valid email address.', 'from_email');
            }
        }
        svc_settings_put($rid, $key, $v);
    }
    return svc_notifications_get($rid);
}

/** Send a test SMS through the restaurant's SMS settings. */
function svc_notifications_test_sms(int $rid, string $phone): array
{
    $phone = trim($phone);
    if ($phone === '') svc_fail('Enter a phone number to test.', 'test_phone');
    require_once __DIR__ . '/../twilio.php';
    $result = twilioSend($rid, $phone, 'Test SMS from your restaurant reservation system.');
    if (empty($result['success'])) {
        throw new ServiceError('SMS failed: ' . ($result['error'] ?? 'unknown error'), 'SMS_FAILED', 502);
    }
    return ['sent' => true, 'sid' => $result['sid'] ?? null];
}

// ── Email templates ────────────────────────────────────────

function svc_email_templates_list(int $rid): array
{
    $stmt = db()->prepare("SELECT * FROM email_templates WHERE restaurant_id = ? ORDER BY template_key");
    $stmt->execute([$rid]);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

/**
 * Create ($id = 0; template_key required) or update an email template.
 * Input: template_key, subject, body_html, body_text, is_active
 */
function svc_email_templates_save(int $rid, int $id, array $in): array
{
    $subject = trim($in['subject'] ?? '');
    $bodyHtml = trim($in['body_html'] ?? '');
    $bodyText = trim($in['body_text'] ?? '') ?: null;
    $active = svc_settings_flag($in['is_active'] ?? '0');
    if ($subject === '' || $bodyHtml === '') svc_fail('Subject and HTML body are required.', 'body');

    if ($id > 0) {
        svc_row('email_templates', $id, $rid, 'Template');
        db()->prepare(
            "UPDATE email_templates SET subject = ?, body_html = ?, body_text = ?, is_active = ?, updated_at = NOW()
             WHERE id = ? AND restaurant_id = ?"
        )->execute([$subject, $bodyHtml, $bodyText, $active, $id, $rid]);
        return svc_row('email_templates', $id, $rid, 'Template');
    }

    $key = trim($in['template_key'] ?? '');
    if (!in_array($key, SETTINGS_EMAIL_TEMPLATE_KEYS, true)) svc_fail('Invalid template type.', 'template_key');
    $stmt = db()->prepare("SELECT id FROM email_templates WHERE restaurant_id = ? AND template_key = ?");
    $stmt->execute([$rid, $key]);
    if ($stmt->fetch()) svc_fail('Template for this type already exists.', 'dup');

    $placeholders = json_encode(['guest_name', 'guest_first_name', 'guest_last_name', 'restaurant_name',
        'restaurant_phone', 'date', 'time', 'party_size', 'confirmation_code', 'status']);
    $stmt = db()->prepare(
        "INSERT INTO email_templates (restaurant_id, template_key, subject, body_html, body_text, placeholders, is_active)
         VALUES (?, ?, ?, ?, ?, ?, ?) RETURNING id"
    );
    $stmt->execute([$rid, $key, $subject, $bodyHtml, $bodyText, $placeholders, $active]);
    return svc_row('email_templates', (int)$stmt->fetchColumn(), $rid, 'Template');
}

function svc_email_templates_delete(int $rid, int $id): void
{
    svc_row('email_templates', $id, $rid, 'Template');
    db()->prepare("DELETE FROM email_templates WHERE id = ? AND restaurant_id = ?")->execute([$id, $rid]);
}

// ── Integrations (Retell voice agent, MCP key, prompt sections) ──

/** Integration settings with secrets masked. */
function svc_integrations_get(int $rid): array
{
    $stored = svc_settings_get_many($rid, array_keys(SETTINGS_INTEGRATION_KEYS));
    $out = [];
    foreach (SETTINGS_INTEGRATION_KEYS as $key => $kind) {
        $out[$key] = $kind === 'secret' ? svc_settings_mask($stored[$key]) : (string)$stored[$key];
    }
    return $out;
}

/** Update the integration settings present in $changes. An empty value clears a setting. */
function svc_integrations_update(int $rid, array $changes): array
{
    foreach (SETTINGS_INTEGRATION_KEYS as $key => $kind) {
        if (!array_key_exists($key, $changes)) continue;
        $category = str_starts_with($key, 'voice_agent_') ? 'voice_agent' : 'general';
        svc_settings_put($rid, $key, trim((string)$changes[$key]), $category);
    }
    return svc_integrations_get($rid);
}
