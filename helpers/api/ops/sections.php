<?php
/**
 * API operations: sections (floor areas that group tables)
 */

require_once __DIR__ . '/../../services/sections.php';

$sectionFields = [
    'name'          => ['type' => 'string', 'description' => 'Unique section name, e.g. "Patio"'],
    'description'   => ['type' => 'string'],
    'display_order' => ['type' => 'integer', 'description' => 'Lower numbers are listed first'],
    'open_time'     => ['type' => 'string', 'description' => 'HH:MM; set together with close_time, or leave both empty for restaurant hours'],
    'close_time'    => ['type' => 'string', 'description' => 'HH:MM'],
    'is_active'     => ['type' => 'boolean', 'description' => 'Inactive sections and their tables are not bookable'],
];

api_op('sections_list', [
    'description' => 'List floor sections with their number of active tables.',
    'role' => 'manager', 'method' => 'GET', 'path' => 'sections',
    'input' => ['include_inactive' => ['type' => 'boolean']],
    'handler' => fn($in, $ctx) => ['sections' => svc_section_list($ctx['restaurant_id'], $in)],
]);

api_op('sections_get', [
    'description' => 'Get one floor section.',
    'role' => 'manager', 'method' => 'GET', 'path' => 'sections/{id}',
    'handler' => fn($in, $ctx) => ['section' => svc_section_get($ctx['restaurant_id'], $in['id'])],
]);

api_op('sections_create', [
    'description' => 'Create a floor section. Active unless is_active=false.',
    'role' => 'manager', 'method' => 'POST', 'path' => 'sections',
    'input' => $sectionFields, 'required' => ['name'],
    'handler' => fn($in, $ctx) => ['section' => svc_section_save($ctx['restaurant_id'], 0, $in + ['is_active' => true])],
]);

api_op('sections_update', [
    'description' => 'Change a floor section. Only the fields sent are changed.',
    'role' => 'manager', 'method' => 'PATCH', 'path' => 'sections/{id}',
    'input' => $sectionFields,
    'handler' => fn($in, $ctx) => ['section' => svc_section_save($ctx['restaurant_id'], $in['id'],
        array_merge(svc_section_get($ctx['restaurant_id'], $in['id']), $in))],
]);

api_op('sections_set_active', [
    'description' => 'Activate or deactivate a floor section.',
    'role' => 'manager', 'method' => 'POST', 'path' => 'sections/{id}/active',
    'input' => ['is_active' => ['type' => 'boolean']], 'required' => ['is_active'],
    'handler' => fn($in, $ctx) => ['section' => svc_section_set_active($ctx['restaurant_id'], $in['id'], $in['is_active'])],
]);
