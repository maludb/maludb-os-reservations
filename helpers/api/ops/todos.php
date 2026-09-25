<?php
/**
 * API operations: todos (the caller's own task list)
 */

require_once __DIR__ . '/../../services/todos.php';

$todoFields = [
    'title'       => ['type' => 'string', 'description' => 'Short task title'],
    'description' => ['type' => 'string', 'description' => 'Details (optional)'],
    'due_date'    => ['type' => 'string', 'description' => 'Due date YYYY-MM-DD (optional)'],
    'priority'    => ['type' => 'string', 'enum' => TODO_PRIORITIES],
];

api_op('todos_list', [
    'description' => "List the caller's own todos, open ones first, by due date.",
    'method' => 'GET', 'path' => 'todos',
    'input' => [
        'status'   => ['type' => 'string', 'enum' => TODO_STATUSES],
        'priority' => ['type' => 'string', 'enum' => TODO_PRIORITIES],
        'limit'    => ['type' => 'integer'], 'offset' => ['type' => 'integer'],
    ],
    'handler' => fn($in, $ctx) => ['todos' => svc_todo_list($ctx['restaurant_id'], $ctx['user_id'], $in)],
]);

api_op('todos_create', [
    'description' => 'Create a todo for the caller.',
    'method' => 'POST', 'path' => 'todos',
    'input' => $todoFields, 'required' => ['title'],
    'handler' => fn($in, $ctx) => ['todo' => svc_todo_save($ctx['restaurant_id'], $ctx['user_id'], 0, $in)],
]);

api_op('todos_update', [
    'description' => 'Edit a todo. Only the fields sent are changed.',
    'method' => 'PATCH', 'path' => 'todos/{id}',
    'input' => $todoFields,
    'handler' => fn($in, $ctx) => ['todo' => svc_todo_save($ctx['restaurant_id'], $ctx['user_id'], $in['id'],
        array_merge(svc_todo_get($ctx['restaurant_id'], $ctx['user_id'], $in['id']), $in))],
]);

api_op('todos_set_status', [
    'description' => 'Set a todo to pending, in_progress or completed.',
    'method' => 'POST', 'path' => 'todos/{id}/status',
    'input' => ['status' => ['type' => 'string', 'enum' => TODO_STATUSES]], 'required' => ['status'],
    'handler' => fn($in, $ctx) => ['todo' => svc_todo_set_status($ctx['restaurant_id'], $ctx['user_id'], $in['id'], $in['status'])],
]);

api_op('todos_delete', [
    'description' => 'Delete a todo.',
    'method' => 'DELETE', 'path' => 'todos/{id}',
    'handler' => function ($in, $ctx) {
        svc_todo_delete($ctx['restaurant_id'], $ctx['user_id'], $in['id']);
        return ['deleted' => true, 'id' => $in['id']];
    },
]);
