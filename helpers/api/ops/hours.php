<?php
/**
 * API operations: operating hours, special dates and turn times (manager)
 */

require_once __DIR__ . '/../../services/settings.php';

// ── Operating hours ────────────────────────────────────────

$hoursInput = [
    'day_of_week'   => ['type' => 'integer', 'description' => '0 = Sunday … 6 = Saturday'],
    'service_name'  => ['type' => 'string', 'description' => 'e.g. Lunch, Dinner'],
    'open_time'     => ['type' => 'string', 'description' => 'HH:MM, 24-hour'],
    'close_time'    => ['type' => 'string', 'description' => 'HH:MM, 24-hour'],
    'first_seating' => ['type' => 'string', 'description' => 'HH:MM; not before open_time'],
    'last_seating'  => ['type' => 'string', 'description' => 'HH:MM; not after close_time'],
    'is_active'     => ['type' => 'boolean', 'description' => 'Default true'],
];

api_op('hours_list', [
    'description' => 'List weekly service periods (e.g. Monday Lunch 11:00–14:00) with seating windows. A day can have several periods.',
    'role' => 'manager', 'method' => 'GET', 'path' => 'hours',
    'handler' => fn($in, $ctx) => ['hours' => svc_hours_list($ctx['restaurant_id'])],
]);

api_op('hours_create', [
    'description' => 'Add a service period to a day of the week. Service names must be unique per day.',
    'role' => 'manager', 'method' => 'POST', 'path' => 'hours',
    'input' => $hoursInput,
    'required' => ['day_of_week', 'service_name', 'open_time', 'close_time', 'first_seating', 'last_seating'],
    'handler' => fn($in, $ctx) => ['service_period' => svc_hours_save($ctx['restaurant_id'], 0, $in + ['is_active' => true])],
]);

api_op('hours_update', [
    'description' => 'Change a service period. Only the fields sent are changed; send is_active=false to close it without deleting.',
    'role' => 'manager', 'method' => 'PATCH', 'path' => 'hours/{id}',
    'input' => $hoursInput,
    'handler' => fn($in, $ctx) => ['service_period' => svc_hours_save($ctx['restaurant_id'], $in['id'],
        array_merge(svc_hours_get($ctx['restaurant_id'], $in['id']), $in))],
]);

api_op('hours_delete', [
    'description' => 'Delete a service period.',
    'role' => 'manager', 'method' => 'DELETE', 'path' => 'hours/{id}',
    'handler' => function ($in, $ctx) {
        svc_hours_delete($ctx['restaurant_id'], $in['id']);
        return ['deleted' => true, 'id' => $in['id']];
    },
]);

// ── Special dates ──────────────────────────────────────────

$specialDateInput = [
    'special_date'         => ['type' => 'string', 'description' => 'YYYY-MM-DD'],
    'date_type'            => ['type' => 'string', 'enum' => SETTINGS_SPECIAL_DATE_TYPES],
    'label'                => ['type' => 'string', 'description' => 'e.g. Christmas Day'],
    'is_closed'            => ['type' => 'boolean', 'description' => 'Restaurant closed all day'],
    'custom_open_time'     => ['type' => 'string', 'description' => 'HH:MM; custom hours need all four times'],
    'custom_close_time'    => ['type' => 'string', 'description' => 'HH:MM'],
    'custom_first_seating' => ['type' => 'string', 'description' => 'HH:MM'],
    'custom_last_seating'  => ['type' => 'string', 'description' => 'HH:MM'],
    'max_covers'           => ['type' => 'integer', 'description' => 'Cap on total guests that day (optional)'],
    'outbound_voice_agent' => ['type' => 'string', 'description' => 'Retell agent ID used to call booked guests (optional)'],
    'notes'                => ['type' => 'string'],
];

api_op('special_dates_list', [
    'description' => 'List special dates (holidays, blackouts, events, modified hours) that override the weekly hours.',
    'role' => 'manager', 'method' => 'GET', 'path' => 'special-dates',
    'input' => [
        'date_from' => ['type' => 'string', 'description' => 'YYYY-MM-DD'],
        'date_to'   => ['type' => 'string', 'description' => 'YYYY-MM-DD'],
    ],
    'handler' => fn($in, $ctx) => ['special_dates' => svc_special_dates_list($ctx['restaurant_id'], $in)],
]);

api_op('special_dates_create', [
    'description' => 'Add a special date. One entry per date and type.',
    'role' => 'manager', 'method' => 'POST', 'path' => 'special-dates',
    'input' => $specialDateInput,
    'required' => ['special_date', 'date_type', 'label'],
    'handler' => fn($in, $ctx) => ['special_date' => svc_special_dates_save($ctx['restaurant_id'], 0, $in)],
]);

api_op('special_dates_update', [
    'description' => 'Change a special date. Only the fields sent are changed; send empty strings for all four custom times to remove custom hours.',
    'role' => 'manager', 'method' => 'PATCH', 'path' => 'special-dates/{id}',
    'input' => $specialDateInput,
    'handler' => fn($in, $ctx) => ['special_date' => svc_special_dates_save($ctx['restaurant_id'], $in['id'],
        array_merge(svc_row('special_dates', $in['id'], $ctx['restaurant_id'], 'Special date'), $in))],
]);

api_op('special_dates_delete', [
    'description' => 'Delete a special date.',
    'role' => 'manager', 'method' => 'DELETE', 'path' => 'special-dates/{id}',
    'handler' => function ($in, $ctx) {
        svc_special_dates_delete($ctx['restaurant_id'], $in['id']);
        return ['deleted' => true, 'id' => $in['id']];
    },
]);

api_op('special_dates_reservations', [
    'description' => 'Non-cancelled reservations on a date, with guest contact details — e.g. guests to contact before a closure.',
    'role' => 'manager', 'method' => 'GET', 'path' => 'special-dates/reservations',
    'input' => ['date' => ['type' => 'string', 'description' => 'YYYY-MM-DD']],
    'required' => ['date'],
    'handler' => fn($in, $ctx) => ['reservations' => svc_special_dates_reservations($ctx['restaurant_id'], $in['date'])],
]);

// ── Turn times ─────────────────────────────────────────────

$turnTimeInput = [
    'min_party_size'   => ['type' => 'integer'],
    'max_party_size'   => ['type' => 'integer'],
    'service_period'   => ['type' => 'string', 'description' => '"all" or a service name such as Dinner'],
    'duration_minutes' => ['type' => 'integer', 'description' => 'How long the party holds the table (min 15)'],
    'buffer_minutes'   => ['type' => 'integer', 'description' => 'Cleanup time before the next seating'],
];

api_op('turn_times_list', [
    'description' => 'List turn-time rules: how long a table is occupied by party size and service period.',
    'role' => 'manager', 'method' => 'GET', 'path' => 'turn-times',
    'handler' => fn($in, $ctx) => ['turn_times' => svc_turn_times_list($ctx['restaurant_id'])],
]);

api_op('turn_times_create', [
    'description' => 'Add a turn-time rule. Party-size range + service period must be unique.',
    'role' => 'manager', 'method' => 'POST', 'path' => 'turn-times',
    'input' => $turnTimeInput,
    'required' => ['min_party_size', 'max_party_size', 'duration_minutes'],
    'handler' => fn($in, $ctx) => ['turn_time' => svc_turn_times_save($ctx['restaurant_id'], 0,
        $in + ['service_period' => 'all', 'buffer_minutes' => 15])],
]);

api_op('turn_times_update', [
    'description' => 'Change a turn-time rule. Only the fields sent are changed.',
    'role' => 'manager', 'method' => 'PATCH', 'path' => 'turn-times/{id}',
    'input' => $turnTimeInput,
    'handler' => fn($in, $ctx) => ['turn_time' => svc_turn_times_save($ctx['restaurant_id'], $in['id'],
        array_merge(svc_row('turn_times', $in['id'], $ctx['restaurant_id'], 'Turn time'), $in))],
]);

api_op('turn_times_delete', [
    'description' => 'Delete a turn-time rule.',
    'role' => 'manager', 'method' => 'DELETE', 'path' => 'turn-times/{id}',
    'handler' => function ($in, $ctx) {
        svc_turn_times_delete($ctx['restaurant_id'], $in['id']);
        return ['deleted' => true, 'id' => $in['id']];
    },
]);
