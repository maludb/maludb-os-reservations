<?php
/**
 * API operations: waitlist and host-stand actions on today's reservations
 */

require_once __DIR__ . '/../../services/waitlist.php';

api_op('waitlist_list', [
    'description' => "Host-stand view: active waitlist (waiting/notified) in queue order, entries finished in the last 2 hours, and today's pending/confirmed reservations.",
    'method' => 'GET', 'path' => 'waitlist',
    'handler' => fn($in, $ctx) => svc_waitlist_list($ctx['restaurant_id']),
]);

api_op('waitlist_open_tables', [
    'description' => 'Tables free right now that fit a party (not held by a confirmed or seated reservation today). If none fit, all free tables are returned with fits_party=false.',
    'method' => 'GET', 'path' => 'waitlist/open-tables',
    'input' => ['party_size' => ['type' => 'integer']], 'required' => ['party_size'],
    'handler' => fn($in, $ctx) => svc_waitlist_open_tables($ctx['restaurant_id'], $in['party_size']),
]);

api_op('waitlist_add', [
    'description' => 'Add a walk-in party to the end of the waitlist.',
    'method' => 'POST', 'path' => 'waitlist',
    'input' => [
        'guest_name'          => ['type' => 'string'],
        'party_size'          => ['type' => 'integer'],
        'quoted_wait_minutes' => ['type' => 'integer', 'description' => 'Wait time quoted to the guest'],
        'phone'               => ['type' => 'string', 'description' => 'For the "table ready" text'],
        'guest_id'            => ['type' => 'integer', 'description' => 'Existing guest (optional)'],
        'seating_preference'  => ['type' => 'string'],
        'notes'               => ['type' => 'string'],
    ],
    'required' => ['guest_name', 'party_size', 'quoted_wait_minutes'],
    'handler' => fn($in, $ctx) => ['entry' => svc_waitlist_add($ctx['restaurant_id'], $ctx['user_id'], $in)],
]);

api_op('waitlist_set_status', [
    'description' => 'Finish a waitlist entry: seated (requires table_id; creates a seated walk-in reservation), left, or no_response.',
    'method' => 'POST', 'path' => 'waitlist/{id}/status',
    'input' => [
        'status'   => ['type' => 'string', 'enum' => WAITLIST_FINAL_STATUSES],
        'table_id' => ['type' => 'integer', 'description' => 'Required when status is seated (see waitlist_open_tables)'],
    ],
    'required' => ['status'],
    'handler' => fn($in, $ctx) => ['entry' => svc_waitlist_set_status($ctx['restaurant_id'], $ctx['user_id'], $in['id'], $in['status'], $in['table_id'] ?? 0)],
]);

api_op('waitlist_notify', [
    'description' => '"Table ready" for a waitlist party: marks it notified, counts the call, and texts the guest when a phone is on file. Returns whether the SMS was sent.',
    'method' => 'POST', 'path' => 'waitlist/{id}/notify',
    'handler' => fn($in, $ctx) => svc_waitlist_notify($ctx['restaurant_id'], $ctx['user_id'], $in['id']),
]);

api_op('waitlist_clear', [
    'description' => 'Clear the waitlist: marks every waiting/notified entry as left.',
    'method' => 'POST', 'path' => 'waitlist/clear',
    'handler' => fn($in, $ctx) => ['cleared' => svc_waitlist_clear($ctx['restaurant_id'], $ctx['user_id'])],
]);

api_op('waitlist_checkin_reservation', [
    'description' => 'Guest with a reservation has arrived: record check-in (a pending reservation is confirmed).',
    'method' => 'POST', 'path' => 'reservations/{id}/check-in',
    'handler' => fn($in, $ctx) => svc_waitlist_checkin_reservation($ctx['restaurant_id'], $ctx['user_id'], $in['id']),
]);

api_op('waitlist_seat_reservation', [
    'description' => 'Seat a pending/confirmed reservation at a table now (see waitlist_open_tables).',
    'method' => 'POST', 'path' => 'reservations/{id}/seat',
    'input' => ['table_id' => ['type' => 'integer']], 'required' => ['table_id'],
    'handler' => fn($in, $ctx) => svc_waitlist_seat_reservation($ctx['restaurant_id'], $ctx['user_id'], $in['id'], $in['table_id']),
]);

api_op('waitlist_notify_reservation', [
    'description' => '"Table ready" for a reservation: texts the guest when a phone is on file. Returns whether the SMS was sent.',
    'method' => 'POST', 'path' => 'reservations/{id}/notify-ready',
    'handler' => fn($in, $ctx) => svc_waitlist_notify_reservation($ctx['restaurant_id'], $ctx['user_id'], $in['id']),
]);
