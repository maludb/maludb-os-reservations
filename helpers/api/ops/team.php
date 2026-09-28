<?php
/**
 * API operations: staff users and restaurant phone numbers (admin)
 */

require_once __DIR__ . '/../../services/team.php';

$teamUserFields = [
    'first_name' => ['type' => 'string'],
    'last_name'  => ['type' => 'string'],
    'email'      => ['type' => 'string'],
    'phone'      => ['type' => 'string'],
    'role'       => ['type' => 'string', 'enum' => TEAM_ROLES, 'description' => 'admin: full setup; manager: setup except team/agents; user: floor staff'],
    'password'   => ['type' => 'string', 'description' => 'Optional. Without one, a new user is marked invited and sets a password via Accept Invitation.'],
];

api_op('users_list', [
    'description' => "List the restaurant's staff with role, membership status and whether an invite is pending.",
    'role' => 'admin', 'method' => 'GET', 'path' => 'users',
    'handler' => fn($in, $ctx) => ['users' => svc_team_list($ctx['restaurant_id'])],
]);

api_op('users_get', [
    'description' => 'Get one staff member.',
    'role' => 'admin', 'method' => 'GET', 'path' => 'users/{id}',
    'handler' => fn($in, $ctx) => ['user' => svc_team_get($ctx['restaurant_id'], $in['id'])],
]);

api_op('users_create', [
    'description' => 'Add a staff member. An existing account with the same email is linked to this restaurant instead of creating a new one. With no password the person is invited: the answer carries invite_code, a one-time code (14 days) they enter on /register.php?invite.',
    'role' => 'admin', 'method' => 'POST', 'path' => 'users',
    'input' => $teamUserFields,
    'required' => ['first_name', 'last_name', 'email', 'role'],
    'secret' => ['password'],
    'handler' => fn($in, $ctx) => ['user' => svc_team_save($ctx['restaurant_id'], 0, $in)],
]);

api_op('users_update', [
    'description' => "Edit a staff member's name, phone, role or password. Only the fields sent are changed. The email cannot be changed. The last active admin cannot be demoted.",
    'role' => 'admin', 'method' => 'PATCH', 'path' => 'users/{id}',
    'input' => array_diff_key($teamUserFields, ['email' => 1]),
    'secret' => ['password'],
    'handler' => function ($in, $ctx) {
        $current = svc_team_get($ctx['restaurant_id'], $in['id']);
        $merged = array_merge(
            ['first_name' => $current['first_name'], 'last_name' => $current['last_name'], 'email' => $current['email'],
             'phone' => $current['phone'], 'role' => $current['role']],
            array_diff_key($in, ['id' => 1])
        );
        return ['user' => svc_team_save($ctx['restaurant_id'], $in['id'], $merged)];
    },
]);

api_op('users_set_active', [
    'description' => "Activate or deactivate a staff member's access to this restaurant. You cannot deactivate yourself or the last active admin.",
    'role' => 'admin', 'method' => 'POST', 'path' => 'users/{id}/active',
    'input' => ['is_active' => ['type' => 'boolean']],
    'required' => ['is_active'],
    'handler' => fn($in, $ctx) => ['user' => svc_team_set_active($ctx['restaurant_id'], $ctx['user_id'], $in['id'], $in['is_active'])],
]);

$phoneFields = [
    'phone_number'  => ['type' => 'string', 'description' => 'E.164 preferred, e.g. +15551234567'],
    'label'         => ['type' => 'string', 'description' => 'e.g. Main line'],
    'language_code' => ['type' => 'string', 'description' => 'e.g. en, es (default en)'],
    'language_name' => ['type' => 'string', 'description' => 'e.g. English (default)'],
    'is_primary'    => ['type' => 'boolean', 'description' => 'Making one number primary clears the others'],
];

api_op('phone_numbers_list', [
    'description' => "List the restaurant's phone numbers, primary first.",
    'role' => 'admin', 'method' => 'GET', 'path' => 'phone-numbers',
    'handler' => fn($in, $ctx) => ['phone_numbers' => svc_phone_list($ctx['restaurant_id'])],
]);

api_op('phone_numbers_create', [
    'description' => 'Add a restaurant phone number.',
    'role' => 'admin', 'method' => 'POST', 'path' => 'phone-numbers',
    'input' => $phoneFields, 'required' => ['phone_number'],
    'handler' => fn($in, $ctx) => ['phone_number' => svc_phone_save($ctx['restaurant_id'], 0, $in)],
]);

api_op('phone_numbers_update', [
    'description' => 'Edit a restaurant phone number. Only the fields sent are changed.',
    'role' => 'admin', 'method' => 'PATCH', 'path' => 'phone-numbers/{id}',
    'input' => $phoneFields,
    'handler' => fn($in, $ctx) => ['phone_number' => svc_phone_save($ctx['restaurant_id'], $in['id'],
        array_merge(svc_row('restaurant_phone_numbers', $in['id'], $ctx['restaurant_id'], 'Phone number'), $in))],
]);

api_op('phone_numbers_delete', [
    'description' => 'Delete a restaurant phone number.',
    'role' => 'admin', 'method' => 'DELETE', 'path' => 'phone-numbers/{id}',
    'handler' => function ($in, $ctx) {
        svc_phone_delete($ctx['restaurant_id'], $in['id']);
        return ['deleted' => true, 'id' => $in['id']];
    },
]);
