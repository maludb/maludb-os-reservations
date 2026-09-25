<?php
/**
 * API operations: reservations
 */

require_once __DIR__ . '/../../services/reservations.php';

api_op('reservations_list', [
    'description' => 'Search reservations, newest first. Cancelled ones are hidden unless status=cancelled; walk-ins are hidden unless include_walk_ins=true.',
    'method' => 'GET', 'path' => 'reservations',
    'input' => [
        'date_from' => ['type' => 'string', 'description' => 'YYYY-MM-DD'],
        'date_to'   => ['type' => 'string', 'description' => 'YYYY-MM-DD'],
        'status'    => ['type' => 'string', 'enum' => RESERVATION_STATUSES],
        'search'    => ['type' => 'string', 'description' => 'Guest name, phone, email or confirmation code'],
        'include_walk_ins' => ['type' => 'boolean'],
        'limit' => ['type' => 'integer'], 'offset' => ['type' => 'integer'],
    ],
    'handler' => fn($in, $ctx) => svc_reservation_list($ctx['restaurant_id'], $in),
]);

api_op('reservations_get', [
    'description' => 'Get one reservation with guest, table, recent history and the status changes allowed next.',
    'method' => 'GET', 'path' => 'reservations/{id}',
    'handler' => fn($in, $ctx) => ['reservation' => svc_reservation_get($ctx['restaurant_id'], $in['id'])],
]);

api_op('reservations_availability', [
    'description' => 'Available time slots for a date and party size, grouped by service period, with the number of free tables per slot.',
    'method' => 'GET', 'path' => 'availability',
    'input' => [
        'date' => ['type' => 'string', 'description' => 'YYYY-MM-DD'],
        'party_size' => ['type' => 'integer'],
    ],
    'required' => ['date', 'party_size'],
    'handler' => fn($in, $ctx) => ['slots' => svc_reservation_availability($ctx['restaurant_id'], $in['date'], $in['party_size'])],
]);

api_op('reservations_create', [
    'description' => 'Create a confirmed reservation. Matches an existing guest by guest_id, email or phone, or creates one. A table is auto-assigned when table_id is omitted.',
    'method' => 'POST', 'path' => 'reservations',
    'input' => [
        'reservation_date' => ['type' => 'string', 'description' => 'YYYY-MM-DD'],
        'reservation_time' => ['type' => 'string', 'description' => 'HH:MM, 24-hour'],
        'party_size'       => ['type' => 'integer'],
        'first_name'       => ['type' => 'string'],
        'last_name'        => ['type' => 'string'],
        'phone'            => ['type' => 'string'],
        'email'            => ['type' => 'string'],
        'guest_id'         => ['type' => 'integer', 'description' => 'Existing guest (optional)'],
        'table_id'         => ['type' => 'integer', 'description' => 'Specific table (optional)'],
        'source'           => ['type' => 'string', 'enum' => RESERVATION_SOURCES, 'description' => 'Default phone'],
        'special_requests' => ['type' => 'string'],
        'internal_notes'   => ['type' => 'string', 'description' => 'Staff-only notes'],
        'turn_time'        => ['type' => 'integer', 'description' => 'Override turn time in minutes'],
    ],
    'required' => ['reservation_date', 'reservation_time', 'party_size', 'first_name', 'last_name'],
    'handler' => fn($in, $ctx) => ['reservation' => svc_reservation_create($ctx['restaurant_id'], $ctx['user_id'], $in)],
]);

api_op('reservations_update', [
    'description' => 'Change a reservation. Only the fields sent are changed. Send table_id 0 to unassign the table.',
    'method' => 'PATCH', 'path' => 'reservations/{id}',
    'input' => [
        'reservation_date'  => ['type' => 'string', 'description' => 'YYYY-MM-DD'],
        'reservation_time'  => ['type' => 'string', 'description' => 'HH:MM, 24-hour'],
        'party_size'        => ['type' => 'integer'],
        'table_id'          => ['type' => 'integer'],
        'turn_time_minutes' => ['type' => 'integer'],
        'special_requests'  => ['type' => 'string'],
        'internal_notes'    => ['type' => 'string'],
    ],
    'handler' => function ($in, $ctx) {
        $id = $in['id'];
        unset($in['id']);
        return ['reservation' => svc_reservation_update($ctx['restaurant_id'], $ctx['user_id'], $id, $in)];
    },
]);

api_op('reservations_set_status', [
    'description' => 'Change reservation status: pending→confirmed|cancelled, confirmed→seated|cancelled|no_show, seated→completed. Confirming or cancelling notifies the guest.',
    'method' => 'POST', 'path' => 'reservations/{id}/status',
    'input' => ['status' => ['type' => 'string', 'enum' => ['confirmed', 'seated', 'completed', 'cancelled', 'no_show']]],
    'required' => ['status'],
    'handler' => fn($in, $ctx) => ['reservation' => svc_reservation_set_status($ctx['restaurant_id'], $ctx['user_id'], $in['id'], $in['status'])],
]);
