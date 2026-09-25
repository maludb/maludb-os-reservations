<?php
/**
 * API operations: tables, live floor status, walk-ins and meal progress
 */

require_once __DIR__ . '/../../services/tables.php';

$tableFields = [
    'table_name' => ['type' => 'string', 'description' => 'Unique table name, e.g. "T12"'],
    'section_id' => ['type' => 'integer', 'description' => 'Section the table belongs to (see sections_list)'],
    'min_seats'  => ['type' => 'integer'],
    'max_seats'  => ['type' => 'integer'],
    'status'     => ['type' => 'string', 'enum' => TABLE_STATUSES, 'description' => 'blocked keeps the table off the floor'],
    'sort_order' => ['type' => 'integer'],
    'notes'      => ['type' => 'string'],
    'is_active'  => ['type' => 'boolean'],
];

/** Seated reservation id for a table: the one given, else the one seated there today. */
function api_tables_seated_id(int $rid, int $tableId, ?int $resId): int
{
    if ($resId) return $resId;
    $res = svc_table_seated_reservation($rid, $tableId);
    if (!$res) throw new ServiceError('No seated reservation found for this table.', 'NOT_FOUND', 404);
    return (int)$res['id'];
}

api_op('tables_list', [
    'description' => 'List tables with their section. Filter by section_id; inactive tables hidden unless include_inactive=true.',
    'role' => 'manager', 'method' => 'GET', 'path' => 'tables',
    'input' => ['section_id' => ['type' => 'integer'], 'include_inactive' => ['type' => 'boolean']],
    'handler' => fn($in, $ctx) => ['tables' => svc_table_list($ctx['restaurant_id'], $in)],
]);

api_op('tables_get', [
    'description' => 'Get one table, plus the reservation seated at it right now (if any).',
    'method' => 'GET', 'path' => 'tables/{id}',
    'handler' => fn($in, $ctx) => [
        'table' => svc_table_get($ctx['restaurant_id'], $in['id']),
        'seated_reservation' => svc_table_seated_reservation($ctx['restaurant_id'], $in['id']),
    ],
]);

api_op('tables_floor_status', [
    'description' => "Live floor plan for today: each active table's effective status (available, reserved, occupied, blocked), its reservation, meal progress and estimated time it frees up.",
    'method' => 'GET', 'path' => 'floor',
    'handler' => fn($in, $ctx) => ['tables' => svc_table_floor_status($ctx['restaurant_id'])],
]);

api_op('tables_create', [
    'description' => 'Create a table. Active unless is_active=false.',
    'role' => 'manager', 'method' => 'POST', 'path' => 'tables',
    'input' => $tableFields, 'required' => ['table_name', 'section_id', 'max_seats'],
    'handler' => fn($in, $ctx) => ['table' => svc_table_save($ctx['restaurant_id'], 0, $in + ['is_active' => true])],
]);

api_op('tables_update', [
    'description' => 'Change a table. Only the fields sent are changed.',
    'role' => 'manager', 'method' => 'PATCH', 'path' => 'tables/{id}',
    'input' => $tableFields,
    'handler' => fn($in, $ctx) => ['table' => svc_table_save($ctx['restaurant_id'], $in['id'],
        array_merge(svc_table_get($ctx['restaurant_id'], $in['id']), $in))],
]);

api_op('tables_set_active', [
    'description' => 'Activate or deactivate a table.',
    'role' => 'manager', 'method' => 'POST', 'path' => 'tables/{id}/active',
    'input' => ['is_active' => ['type' => 'boolean']], 'required' => ['is_active'],
    'handler' => fn($in, $ctx) => ['table' => svc_table_set_active($ctx['restaurant_id'], $in['id'], $in['is_active'])],
]);

api_op('tables_seat_walkin', [
    'description' => 'Seat a walk-in party at this table now (creates a seated walk-in reservation). A name is optional; unnamed walk-ins are not added to the guest list.',
    'method' => 'POST', 'path' => 'tables/{id}/walk-in',
    'input' => [
        'party_size' => ['type' => 'integer'],
        'first_name' => ['type' => 'string'],
        'last_name'  => ['type' => 'string'],
    ],
    'required' => ['party_size'],
    'handler' => fn($in, $ctx) => ['walk_in' => svc_table_seat_walkin($ctx['restaurant_id'], $ctx['user_id'], $in['id'], $in)],
]);

api_op('tables_set_meal_status', [
    'description' => 'Record meal progress for the party seated at this table (drives the estimated time the table frees up). reservation_id defaults to the party seated there now.',
    'method' => 'POST', 'path' => 'tables/{id}/meal-status',
    'input' => [
        'meal_status'    => ['type' => 'string', 'enum' => TABLE_MEAL_STATUSES],
        'reservation_id' => ['type' => 'integer'],
    ],
    'required' => ['meal_status'],
    'handler' => fn($in, $ctx) => svc_table_set_meal_status($ctx['restaurant_id'],
        api_tables_seated_id($ctx['restaurant_id'], $in['id'], $in['reservation_id'] ?? null), $in['meal_status']),
]);

api_op('tables_complete', [
    'description' => 'The party at this table has left: complete their reservation and open the table. reservation_id defaults to the party seated there now.',
    'method' => 'POST', 'path' => 'tables/{id}/complete',
    'input' => ['reservation_id' => ['type' => 'integer']],
    'handler' => fn($in, $ctx) => svc_table_complete($ctx['restaurant_id'], $ctx['user_id'],
        api_tables_seated_id($ctx['restaurant_id'], $in['id'], $in['reservation_id'] ?? null)),
]);
