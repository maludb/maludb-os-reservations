<?php
/**
 * Todo service — personal task list of a user within a restaurant.
 */

require_once __DIR__ . '/_base.php';

const TODO_STATUSES = ['pending', 'in_progress', 'completed'];
const TODO_PRIORITIES = ['low', 'medium', 'high'];

function svc_todo_get(int $rid, int $uid, int $id): array
{
    $stmt = db()->prepare("SELECT * FROM todos WHERE id = ? AND restaurant_id = ? AND user_id = ?");
    $stmt->execute([$id, $rid, $uid]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$row) svc_not_found('Todo');
    return $row;
}

function svc_todo_list(int $rid, int $uid, array $in): array
{
    $where = ['restaurant_id = ?', 'user_id = ?'];
    $params = [$rid, $uid];
    if (!empty($in['status'])) { $where[] = 'status = ?'; $params[] = $in['status']; }
    if (!empty($in['priority'])) { $where[] = 'priority = ?'; $params[] = $in['priority']; }
    [$limit, $offset] = svc_page($in);
    $stmt = db()->prepare(
        "SELECT * FROM todos WHERE " . implode(' AND ', $where) . "
         ORDER BY status = 'completed', COALESCE(due_date, '9999-12-31'), id LIMIT {$limit} OFFSET {$offset}"
    );
    $stmt->execute($params);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

/** Create ($id = 0) or update a todo. Returns the saved row. */
function svc_todo_save(int $rid, int $uid, int $id, array $in): array
{
    $title = trim($in['title'] ?? '');
    if ($title === '') svc_fail('Title is required.', 'title');
    $priority = in_array($in['priority'] ?? '', TODO_PRIORITIES, true) ? $in['priority'] : 'medium';
    $dueDate = trim($in['due_date'] ?? '');
    $dueDate = preg_match('/^\d{4}-\d{2}-\d{2}$/', $dueDate) ? $dueDate : null;
    $description = trim($in['description'] ?? '') ?: null;

    if ($id > 0) {
        svc_todo_get($rid, $uid, $id);
        db()->prepare("UPDATE todos SET title = ?, description = ?, due_date = ?, priority = ?, updated_at = NOW() WHERE id = ?")
            ->execute([$title, $description, $dueDate, $priority, $id]);
    } else {
        $stmt = db()->prepare(
            "INSERT INTO todos (restaurant_id, user_id, title, description, due_date, priority) VALUES (?, ?, ?, ?, ?, ?) RETURNING id"
        );
        $stmt->execute([$rid, $uid, $title, $description, $dueDate, $priority]);
        $id = (int)$stmt->fetchColumn();
    }
    return svc_todo_get($rid, $uid, $id);
}

function svc_todo_set_status(int $rid, int $uid, int $id, string $status): array
{
    if (!in_array($status, TODO_STATUSES, true)) svc_fail('Invalid status.', 'status');
    svc_todo_get($rid, $uid, $id);
    db()->prepare("UPDATE todos SET status = ?, completed_at = ?, updated_at = NOW() WHERE id = ?")
        ->execute([$status, $status === 'completed' ? date('Y-m-d H:i:s') : null, $id]);
    return svc_todo_get($rid, $uid, $id);
}

function svc_todo_delete(int $rid, int $uid, int $id): void
{
    $stmt = db()->prepare("DELETE FROM todos WHERE id = ? AND restaurant_id = ? AND user_id = ?");
    $stmt->execute([$id, $rid, $uid]);
    if ($stmt->rowCount() === 0) svc_not_found('Todo');
}
