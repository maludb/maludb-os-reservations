<?php
/**
 * REST API v1 — Todos
 *
 * GET    /api/v1/todos.php              List todos (query: status, priority, due_before, due_after, sort_by, sort_dir)
 * POST   /api/v1/todos.php              Create a todo
 * PUT    /api/v1/todos.php?id={id}      Update a todo
 * DELETE /api/v1/todos.php?id={id}      Delete a todo
 */

require_once __DIR__ . '/_bootstrap.php';

$auth   = api_authenticate();
$uid    = $auth['user_id'];
$rid    = $auth['restaurant_id'];
$method = api_method();

api_require_method('GET', 'POST', 'PUT', 'DELETE');

// ── GET: List ──────────────────────────────────────────────
if ($method === 'GET') {
    $where  = ['restaurant_id = ?', 'user_id = ?'];
    $params = [$rid, $uid];

    $status = api_query('status');
    if ($status && in_array($status, ['pending', 'in_progress', 'completed'])) {
        $where[]  = 'status = ?';
        $params[] = $status;
    }

    $priority = api_query('priority');
    if ($priority && in_array($priority, ['low', 'medium', 'high'])) {
        $where[]  = 'priority = ?';
        $params[] = $priority;
    }

    $dueBefore = api_query('due_before');
    if ($dueBefore && preg_match('/^\d{4}-\d{2}-\d{2}$/', $dueBefore)) {
        $where[]  = 'due_date <= ?';
        $params[] = $dueBefore;
    }

    $dueAfter = api_query('due_after');
    if ($dueAfter && preg_match('/^\d{4}-\d{2}-\d{2}$/', $dueAfter)) {
        $where[]  = 'due_date >= ?';
        $params[] = $dueAfter;
    }

    $sortBy  = api_query('sort_by', 'due_date');
    $sortDir = strtoupper(api_query('sort_dir', 'ASC')) === 'DESC' ? 'DESC' : 'ASC';

    $orderClause = match ($sortBy) {
        'priority'   => "CASE priority WHEN 'high' THEN 1 WHEN 'medium' THEN 2 ELSE 3 END {$sortDir}",
        'created_at' => "created_at {$sortDir}",
        'title'      => "title {$sortDir}",
        default      => "COALESCE(due_date, '9999-12-31') {$sortDir}",
    };

    $whereClause = implode(' AND ', $where);
    $stmt = db()->prepare("SELECT * FROM todos WHERE {$whereClause} ORDER BY status != 'completed', {$orderClause}");
    $stmt->execute($params);
    $todos = $stmt->fetchAll(PDO::FETCH_ASSOC);

    api_success(['data' => ['todos' => $todos]]);
}

// ── POST: Create ───────────────────────────────────────────
if ($method === 'POST') {
    $body  = api_json_body();
    $title = trim($body['title'] ?? '');

    if ($title === '') {
        api_error('Title is required.', 'VALIDATION_ERROR', 422);
    }

    $description = trim($body['description'] ?? '') ?: null;
    $dueDate     = ($body['due_date'] ?? null) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $body['due_date']) ? $body['due_date'] : null;
    $priority    = in_array($body['priority'] ?? '', ['low', 'medium', 'high']) ? $body['priority'] : 'medium';

    $stmt = db()->prepare(
        "INSERT INTO todos (restaurant_id, user_id, title, description, due_date, priority) VALUES (?, ?, ?, ?, ?, ?)"
    );
    $stmt->execute([$rid, $uid, $title, $description, $dueDate, $priority]);

    $newId = (int)db()->lastInsertId();
    $fetch = db()->prepare("SELECT * FROM todos WHERE id = ?");
    $fetch->execute([$newId]);

    api_success(['data' => ['todo' => $fetch->fetch(PDO::FETCH_ASSOC), 'message' => 'Todo created']], 201);
}

// ── PUT: Update ────────────────────────────────────────────
if ($method === 'PUT') {
    $id = (int)api_query('id', 0);
    if ($id <= 0) {
        api_error('Missing id parameter.', 'VALIDATION_ERROR', 422);
    }

    $check = db()->prepare("SELECT * FROM todos WHERE id = ? AND restaurant_id = ? AND user_id = ?");
    $check->execute([$id, $rid, $uid]);
    $existing = $check->fetch(PDO::FETCH_ASSOC);

    if (!$existing) {
        api_error('Todo not found.', 'NOT_FOUND', 404);
    }

    $body        = api_json_body();
    $title       = isset($body['title']) ? trim($body['title']) : $existing['title'];
    $description = array_key_exists('description', $body) ? (trim($body['description']) ?: null) : $existing['description'];
    $dueDate     = array_key_exists('due_date', $body)
        ? (($body['due_date'] && preg_match('/^\d{4}-\d{2}-\d{2}$/', $body['due_date'])) ? $body['due_date'] : null)
        : $existing['due_date'];
    $priority    = isset($body['priority']) && in_array($body['priority'], ['low', 'medium', 'high']) ? $body['priority'] : $existing['priority'];
    $status      = isset($body['status']) && in_array($body['status'], ['pending', 'in_progress', 'completed']) ? $body['status'] : $existing['status'];

    $completedAt = $existing['completed_at'];
    if ($status === 'completed' && $existing['status'] !== 'completed') {
        $completedAt = date('Y-m-d H:i:s');
    } elseif ($status !== 'completed') {
        $completedAt = null;
    }

    if ($title === '') {
        api_error('Title cannot be empty.', 'VALIDATION_ERROR', 422);
    }

    $stmt = db()->prepare(
        "UPDATE todos SET title = ?, description = ?, due_date = ?, priority = ?, status = ?, completed_at = ?, updated_at = NOW() WHERE id = ?"
    );
    $stmt->execute([$title, $description, $dueDate, $priority, $status, $completedAt, $id]);

    $fetch = db()->prepare("SELECT * FROM todos WHERE id = ?");
    $fetch->execute([$id]);

    api_success(['data' => ['todo' => $fetch->fetch(PDO::FETCH_ASSOC), 'message' => 'Todo updated']]);
}

// ── DELETE ──────────────────────────────────────────────────
if ($method === 'DELETE') {
    $id = (int)api_query('id', 0);
    if ($id <= 0) {
        api_error('Missing id parameter.', 'VALIDATION_ERROR', 422);
    }

    $stmt = db()->prepare("DELETE FROM todos WHERE id = ? AND restaurant_id = ? AND user_id = ?");
    $stmt->execute([$id, $rid, $uid]);

    if ($stmt->rowCount() === 0) {
        api_error('Todo not found.', 'NOT_FOUND', 404);
    }

    api_success(['data' => ['message' => 'Todo deleted']]);
}
