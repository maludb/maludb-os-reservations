<?php
/**
 * API operations: events (special events with limited capacity)
 */

require_once __DIR__ . '/../../services/events.php';

$eventFields = [
    'name'             => ['type' => 'string'],
    'event_date'       => ['type' => 'string', 'description' => 'YYYY-MM-DD'],
    'start_time'       => ['type' => 'string', 'description' => 'HH:MM, 24-hour'],
    'end_time'         => ['type' => 'string', 'description' => 'HH:MM, 24-hour (optional)'],
    'max_capacity'     => ['type' => 'integer', 'description' => 'Maximum covers (default 50)'],
    'price_per_person' => ['type' => 'number'],
    'status'           => ['type' => 'string', 'enum' => EVENT_STATUSES, 'description' => 'Default draft; only draft and published events accept bookings'],
    'is_public'        => ['type' => 'boolean', 'description' => 'Shown to guests (default true)'],
    'description'      => ['type' => 'string'],
    'notes'            => ['type' => 'string', 'description' => 'Staff-only notes'],
];

api_op('events_list', [
    'description' => 'List events with booked covers and booking counts. filter: upcoming (default: today onward, draft or published), draft, past (earlier or completed/cancelled), all.',
    'method' => 'GET', 'path' => 'events',
    'input' => [
        'filter' => ['type' => 'string', 'enum' => EVENT_FILTERS],
        'limit' => ['type' => 'integer'], 'offset' => ['type' => 'integer'],
    ],
    'handler' => fn($in, $ctx) => ['events' => svc_event_list($ctx['restaurant_id'], $in)],
]);

api_op('events_get', [
    'description' => 'Get one event with its bookings, booked covers and spots left.',
    'method' => 'GET', 'path' => 'events/{id}',
    'handler' => fn($in, $ctx) => ['event' => svc_event_get($ctx['restaurant_id'], $in['id'])],
]);

api_op('events_create', [
    'description' => 'Create an event.',
    'method' => 'POST', 'path' => 'events',
    'input' => $eventFields,
    'required' => ['name', 'event_date', 'start_time'],
    'handler' => fn($in, $ctx) => ['event' => svc_event_save($ctx['restaurant_id'], $ctx['user_id'], 0, $in)],
]);

api_op('events_update', [
    'description' => 'Change an event. Only the fields sent are changed. Set status=cancelled to cancel it.',
    'method' => 'PATCH', 'path' => 'events/{id}',
    'input' => $eventFields,
    'handler' => function ($in, $ctx) {
        $id = $in['id'];
        unset($in['id']);
        return ['event' => svc_event_save($ctx['restaurant_id'], $ctx['user_id'], $id, $in)];
    },
]);

api_op('events_book', [
    'description' => "Book a party onto an event: creates a confirmed reservation at the event's date and start time. Matches an existing guest by guest_id, email or phone, or creates one. Fails with CAPACITY_EXCEEDED when not enough spots remain.",
    'method' => 'POST', 'path' => 'events/{id}/bookings',
    'input' => [
        'party_size'       => ['type' => 'integer'],
        'first_name'       => ['type' => 'string'],
        'last_name'        => ['type' => 'string'],
        'phone'            => ['type' => 'string'],
        'email'            => ['type' => 'string'],
        'guest_id'         => ['type' => 'integer', 'description' => 'Existing guest (optional)'],
        'special_requests' => ['type' => 'string'],
    ],
    'required' => ['party_size', 'first_name', 'last_name'],
    'handler' => fn($in, $ctx) => ['reservation' => svc_event_book($ctx['restaurant_id'], $ctx['user_id'], $in['id'], $in)],
]);
