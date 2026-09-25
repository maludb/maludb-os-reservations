<?php
require_once __DIR__ . '/../../../helpers/auth.php';
require_once __DIR__ . '/../../../helpers/csrf.php';

requireAuth();

$restaurantId = currentRestaurantId();
$userId = $_SESSION['user_id'] ?? 0;

if (!$restaurantId) {
    echo '<div class="alert alert-danger" id="todos-no-restaurant">No account is currently selected.</div>';
    exit;
}

$statusFilter = trim($_GET['status'] ?? '');
$sortBy = trim($_GET['sort_by'] ?? 'due_date');

// Build query
$where = ['restaurant_id = ?', 'user_id = ?'];
$params = [$restaurantId, $userId];

if ($statusFilter !== '' && in_array($statusFilter, ['pending', 'in_progress', 'completed'])) {
    $where[] = 'status = ?';
    $params[] = $statusFilter;
}

$whereClause = implode(' AND ', $where);

// Sort
$orderClause = match ($sortBy) {
    'priority' => "CASE priority WHEN 'high' THEN 1 WHEN 'medium' THEN 2 ELSE 3 END, COALESCE(due_date, '9999-12-31') ASC",
    'created' => "created_at DESC",
    default => "COALESCE(due_date, '9999-12-31') ASC, CASE priority WHEN 'high' THEN 1 WHEN 'medium' THEN 2 ELSE 3 END",
};

// Counts for tabs
$countStmt = db()->prepare(
    "SELECT
        COUNT(*) AS total,
        SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END) AS pending,
        SUM(CASE WHEN status = 'in_progress' THEN 1 ELSE 0 END) AS in_progress,
        SUM(CASE WHEN status = 'completed' THEN 1 ELSE 0 END) AS completed
     FROM todos
     WHERE restaurant_id = ? AND user_id = ?"
);
$countStmt->execute([$restaurantId, $userId]);
$counts = $countStmt->fetch(PDO::FETCH_ASSOC);

// Fetch todos
$stmt = db()->prepare("SELECT * FROM todos WHERE {$whereClause} ORDER BY status != 'completed', {$orderClause}");
$stmt->execute($params);
$todos = $stmt->fetchAll(PDO::FETCH_ASSOC);

$today = date('Y-m-d');

$priorityBadge = function($p) {
    return match($p) {
        'high' => '<span class="badge bg-danger">High</span>',
        'low' => '<span class="badge bg-info">Low</span>',
        default => '<span class="badge bg-warning text-dark">Medium</span>',
    };
};

$statusBadge = function($s) {
    return match($s) {
        'in_progress' => '<span class="badge bg-primary">In Progress</span>',
        'completed' => '<span class="badge bg-success">Completed</span>',
        default => '<span class="badge bg-secondary">Pending</span>',
    };
};
?>

<div class="main-content" id="todos-page">
  <div class="row" id="todos-header-row">
    <div class="col-12 d-flex align-items-center justify-content-between mb-4">
      <h2 class="mb-0" id="todos-title">Todo List</h2>
      <button class="btn btn-primary"
              hx-get="/partials/todos/form.php"
              hx-target="#todo-form-container"
              hx-swap="innerHTML"
              id="todos-add-btn">
        <i class="feather-plus me-1"></i> Add Todo
      </button>
    </div>
  </div>

  <!-- Inline form container -->
  <div id="todo-form-container" class="mb-3"></div>

  <!-- Filter Tabs -->
  <div class="row mb-3" id="todos-filter-row">
    <div class="col-12">
      <ul class="nav nav-tabs" id="todos-filter-tabs">
        <li class="nav-item" id="todos-tab-all">
          <a class="nav-link <?php echo $statusFilter === '' ? 'active' : ''; ?>" href="#"
             hx-get="/partials/todos/list.php?sort_by=<?php echo urlencode($sortBy); ?>"
             hx-target="#page-content">
            All <span class="badge bg-light text-dark ms-1"><?php echo (int)$counts['total']; ?></span>
          </a>
        </li>
        <li class="nav-item" id="todos-tab-pending">
          <a class="nav-link <?php echo $statusFilter === 'pending' ? 'active' : ''; ?>" href="#"
             hx-get="/partials/todos/list.php?status=pending&sort_by=<?php echo urlencode($sortBy); ?>"
             hx-target="#page-content">
            Pending <span class="badge bg-secondary ms-1"><?php echo (int)$counts['pending']; ?></span>
          </a>
        </li>
        <li class="nav-item" id="todos-tab-inprogress">
          <a class="nav-link <?php echo $statusFilter === 'in_progress' ? 'active' : ''; ?>" href="#"
             hx-get="/partials/todos/list.php?status=in_progress&sort_by=<?php echo urlencode($sortBy); ?>"
             hx-target="#page-content">
            In Progress <span class="badge bg-primary ms-1"><?php echo (int)$counts['in_progress']; ?></span>
          </a>
        </li>
        <li class="nav-item" id="todos-tab-completed">
          <a class="nav-link <?php echo $statusFilter === 'completed' ? 'active' : ''; ?>" href="#"
             hx-get="/partials/todos/list.php?status=completed&sort_by=<?php echo urlencode($sortBy); ?>"
             hx-target="#page-content">
            Completed <span class="badge bg-success ms-1"><?php echo (int)$counts['completed']; ?></span>
          </a>
        </li>
      </ul>
    </div>
  </div>

  <!-- Sort Controls -->
  <div class="row mb-3" id="todos-sort-row">
    <div class="col-auto">
      <small class="text-muted">Sort by:</small>
      <div class="btn-group btn-group-sm ms-2" id="todos-sort-group">
        <a class="btn btn-outline-secondary <?php echo $sortBy === 'due_date' ? 'active' : ''; ?>" href="#"
           hx-get="/partials/todos/list.php?status=<?php echo urlencode($statusFilter); ?>&sort_by=due_date"
           hx-target="#page-content">Due Date</a>
        <a class="btn btn-outline-secondary <?php echo $sortBy === 'priority' ? 'active' : ''; ?>" href="#"
           hx-get="/partials/todos/list.php?status=<?php echo urlencode($statusFilter); ?>&sort_by=priority"
           hx-target="#page-content">Priority</a>
        <a class="btn btn-outline-secondary <?php echo $sortBy === 'created' ? 'active' : ''; ?>" href="#"
           hx-get="/partials/todos/list.php?status=<?php echo urlencode($statusFilter); ?>&sort_by=created"
           hx-target="#page-content">Newest</a>
      </div>
    </div>
  </div>

  <!-- Todo List -->
  <div class="card" id="todos-list-card">
    <div class="card-body p-0" id="todos-list-body">
      <?php if (empty($todos)): ?>
        <div class="text-center text-muted py-5" id="todos-empty">
          <i class="feather-check-square" style="font-size: 2rem;"></i>
          <p class="mt-2 mb-0">No todos found. Click "Add Todo" to create one.</p>
        </div>
      <?php else: ?>
        <div class="table-responsive" id="todos-table-wrapper">
          <table class="table table-hover align-middle mb-0" id="todos-table">
            <thead>
              <tr id="todos-table-header">
                <th style="width: 40px;" id="todos-th-check"></th>
                <th id="todos-th-title">Title</th>
                <th style="width: 100px;" id="todos-th-priority">Priority</th>
                <th style="width: 120px;" id="todos-th-due">Due Date</th>
                <th style="width: 120px;" id="todos-th-status">Status</th>
                <th style="width: 100px;" id="todos-th-actions">Actions</th>
              </tr>
            </thead>
            <tbody id="todos-table-body">
              <?php foreach ($todos as $todo): ?>
                <?php
                  $isOverdue = $todo['due_date'] && $todo['due_date'] < $today && $todo['status'] !== 'completed';
                  $rowClass = $isOverdue ? 'table-danger' : ($todo['status'] === 'completed' ? 'text-muted' : '');
                  $nextStatus = match($todo['status']) {
                      'pending' => 'in_progress',
                      'in_progress' => 'completed',
                      'completed' => 'pending',
                      default => 'pending',
                  };
                  $checkIcon = $todo['status'] === 'completed' ? 'feather-check-circle' : 'feather-circle';
                ?>
                <tr class="<?php echo $rowClass; ?>" id="todo-row-<?php echo $todo['id']; ?>">
                  <td id="todo-check-<?php echo $todo['id']; ?>">
                    <a href="#" class="text-decoration-none"
                       hx-post="/partials/todos/update-status.php"
                       hx-vals='<?php echo json_encode(["id" => $todo["id"], "status" => $nextStatus, "csrf_token" => $_SESSION["csrf_token"] ?? ""]); ?>'
                       hx-target="#page-content"
                       title="Mark as <?php echo htmlspecialchars(str_replace('_', ' ', $nextStatus)); ?>">
                      <i class="<?php echo $checkIcon; ?> <?php echo $todo['status'] === 'completed' ? 'text-success' : 'text-muted'; ?>"></i>
                    </a>
                  </td>
                  <td id="todo-title-<?php echo $todo['id']; ?>">
                    <strong class="<?php echo $todo['status'] === 'completed' ? 'text-decoration-line-through' : ''; ?>">
                      <?php echo htmlspecialchars($todo['title']); ?>
                    </strong>
                    <?php if ($todo['description']): ?>
                      <br><small class="text-muted"><?php echo htmlspecialchars(mb_strimwidth($todo['description'], 0, 80, '...')); ?></small>
                    <?php endif; ?>
                    <?php if ($isOverdue): ?>
                      <span class="badge bg-danger ms-1">Overdue</span>
                    <?php endif; ?>
                  </td>
                  <td id="todo-priority-<?php echo $todo['id']; ?>"><?php echo $priorityBadge($todo['priority']); ?></td>
                  <td id="todo-due-<?php echo $todo['id']; ?>">
                    <?php echo $todo['due_date'] ? date('M j, Y', strtotime($todo['due_date'])) : '<span class="text-muted">—</span>'; ?>
                  </td>
                  <td id="todo-status-<?php echo $todo['id']; ?>"><?php echo $statusBadge($todo['status']); ?></td>
                  <td id="todo-actions-<?php echo $todo['id']; ?>">
                    <div class="btn-group btn-group-sm" id="todo-action-group-<?php echo $todo['id']; ?>">
                      <button class="btn btn-outline-primary btn-sm"
                              hx-get="/partials/todos/form.php?id=<?php echo $todo['id']; ?>"
                              hx-target="#todo-form-container"
                              hx-swap="innerHTML"
                              title="Edit"
                              id="todo-edit-btn-<?php echo $todo['id']; ?>">
                        <i class="feather-edit-2"></i>
                      </button>
                      <button class="btn btn-outline-danger btn-sm"
                              hx-post="/partials/todos/delete.php"
                              hx-vals='<?php echo json_encode(["id" => $todo["id"], "csrf_token" => $_SESSION["csrf_token"] ?? ""]); ?>'
                              hx-target="#page-content"
                              hx-confirm="Delete this todo?"
                              title="Delete"
                              id="todo-delete-btn-<?php echo $todo['id']; ?>">
                        <i class="feather-trash-2"></i>
                      </button>
                    </div>
                  </td>
                </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
      <?php endif; ?>
    </div>
  </div>
</div>
