<?php
/**
 * API operations: guests (guest book)
 */

require_once __DIR__ . '/../../services/guests.php';

$guestFields = [
    'first_name'           => ['type' => 'string'],
    'last_name'            => ['type' => 'string'],
    'email'                => ['type' => 'string'],
    'phone'                => ['type' => 'string'],
    'address_line1'        => ['type' => 'string'],
    'address_line2'        => ['type' => 'string'],
    'city'                 => ['type' => 'string'],
    'state'                => ['type' => 'string'],
    'postal_code'          => ['type' => 'string'],
    'tags'                 => ['type' => 'string', 'description' => 'Comma-separated tags, e.g. "vip,regular" (stored lower-case)'],
    'dietary_restrictions' => ['type' => 'string'],
    'allergies'            => ['type' => 'string'],
    'seating_preference'   => ['type' => 'string'],
    'favorite_server'      => ['type' => 'string'],
    'notes'                => ['type' => 'string', 'description' => 'Staff notes about the guest'],
    'do_not_call'          => ['type' => 'boolean', 'description' => 'Guest opted out of calls'],
    'do_not_email'         => ['type' => 'boolean', 'description' => 'Guest opted out of email'],
    'do_not_text'          => ['type' => 'boolean', 'description' => 'Guest opted out of SMS'],
];

api_op('guests_list', [
    'description' => 'List active guests alphabetically, with optional search (name, email, phone) and tag filter. Returns total and one page.',
    'method' => 'GET', 'path' => 'guests',
    'input' => [
        'search' => ['type' => 'string'],
        'tag'    => ['type' => 'string', 'description' => 'Only guests with this tag'],
        'limit'  => ['type' => 'integer'], 'offset' => ['type' => 'integer'],
    ],
    'handler' => fn($in, $ctx) => svc_guest_list($ctx['restaurant_id'], $in),
]);

api_op('guests_search', [
    'description' => 'Quick guest lookup by name, email or phone (at least 2 characters); returns up to 10 matches. Use before creating a reservation to find an existing guest_id.',
    'method' => 'GET', 'path' => 'guests/search',
    'input' => ['q' => ['type' => 'string', 'description' => 'Search text']],
    'required' => ['q'],
    'handler' => fn($in, $ctx) => ['guests' => svc_guest_search($ctx['restaurant_id'], $in['q'])],
]);

api_op('guests_get', [
    'description' => 'Get one guest with preferences, opt-outs, visit/no-show counts and reservation history.',
    'method' => 'GET', 'path' => 'guests/{id}',
    'handler' => fn($in, $ctx) => ['guest' => svc_guest_get($ctx['restaurant_id'], $in['id'])],
]);

api_op('guests_create', [
    'description' => 'Add a guest to the guest book.',
    'method' => 'POST', 'path' => 'guests',
    'input' => $guestFields,
    'required' => ['first_name', 'last_name'],
    'handler' => fn($in, $ctx) => ['guest' => svc_guest_create($ctx['restaurant_id'], $ctx['user_id'], $in)],
]);

api_op('guests_update', [
    'description' => 'Change a guest. Only the fields sent are changed; send an empty string to clear a text field. To add or remove a single tag use guests_add_tag / guests_remove_tag.',
    'method' => 'PATCH', 'path' => 'guests/{id}',
    'input' => $guestFields,
    'handler' => function ($in, $ctx) {
        $id = $in['id'];
        unset($in['id']);
        return ['guest' => svc_guest_update($ctx['restaurant_id'], $ctx['user_id'], $id, $in)];
    },
]);

api_op('guests_add_tag', [
    'description' => 'Add one tag to a guest (e.g. vip, regular).',
    'method' => 'POST', 'path' => 'guests/{id}/tags',
    'input' => ['tag' => ['type' => 'string']],
    'required' => ['tag'],
    'handler' => fn($in, $ctx) => ['guest' => svc_guest_tag($ctx['restaurant_id'], $ctx['user_id'], $in['id'], $in['tag'], true)],
]);

api_op('guests_remove_tag', [
    'description' => 'Remove one tag from a guest.',
    'method' => 'DELETE', 'path' => 'guests/{id}/tags',
    'input' => ['tag' => ['type' => 'string']],
    'required' => ['tag'],
    'handler' => fn($in, $ctx) => ['guest' => svc_guest_tag($ctx['restaurant_id'], $ctx['user_id'], $in['id'], $in['tag'], false)],
]);
