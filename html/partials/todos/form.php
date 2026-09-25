<?php
require_once __DIR__ . '/../../../helpers/auth.php';
require_once __DIR__ . '/../../../helpers/csrf.php';

requireAuth();

$restaurantId = currentRestaurantId();
$userId = $_SESSION['user_id'] ?? 0;
$todo = null;
$editId = (int)($_GET['id'] ?? 0);

if ($editId > 0) {
    $stmt = db()->prepare("SELECT * FROM todos WHERE id = ? AND restaurant_id = ? AND user_id = ?");
    $stmt->execute([$editId, $restaurantId, $userId]);
    $todo = $stmt->fetch(PDO::FETCH_ASSOC);
}

$isEdit = $todo !== null;
$title = $todo['title'] ?? '';
$description = $todo['description'] ?? '';
$dueDate = $todo['due_date'] ?? '';
$priority = $todo['priority'] ?? 'medium';
?>

<div class="card border-primary mb-3" id="todo-form-card">
  <div class="card-header bg-primary text-white d-flex justify-content-between align-items-center" id="todo-form-header">
    <strong><?php echo $isEdit ? 'Edit Todo' : 'Add Todo'; ?></strong>
    <button type="button" class="btn btn-sm btn-light" onclick="document.getElementById('todo-form-container').innerHTML=''" id="todo-form-close-btn">
      <i class="feather-x"></i>
    </button>
  </div>
  <div class="card-body" id="todo-form-body">
    <form hx-post="/partials/todos/save.php" hx-target="#page-content" id="todo-form">
      <?php echo csrf_field(); ?>
      <?php if ($isEdit): ?>
        <input type="hidden" name="id" value="<?php echo $todo['id']; ?>">
      <?php endif; ?>

      <div class="row" id="todo-form-row-1">
        <div class="col-md-8 mb-3" id="todo-form-title-col">
          <label for="todo-title-input" class="form-label">Title <span class="text-danger">*</span></label>
          <input type="text" class="form-control" id="todo-title-input" name="title"
                 value="<?php echo htmlspecialchars($title); ?>" required maxlength="255"
                 placeholder="What needs to be done?">
        </div>
        <div class="col-md-4 mb-3" id="todo-form-priority-col">
          <label for="todo-priority-input" class="form-label">Priority</label>
          <select class="form-select" id="todo-priority-input" name="priority">
            <option value="low" <?php echo $priority === 'low' ? 'selected' : ''; ?>>Low</option>
            <option value="medium" <?php echo $priority === 'medium' ? 'selected' : ''; ?>>Medium</option>
            <option value="high" <?php echo $priority === 'high' ? 'selected' : ''; ?>>High</option>
          </select>
        </div>
      </div>

      <div class="row" id="todo-form-row-2">
        <div class="col-md-8 mb-3" id="todo-form-desc-col">
          <label for="todo-desc-input" class="form-label">Description</label>
          <textarea class="form-control" id="todo-desc-input" name="description" rows="2"
                    placeholder="Optional details..."><?php echo htmlspecialchars($description); ?></textarea>
        </div>
        <div class="col-md-4 mb-3" id="todo-form-due-col">
          <label for="todo-due-input" class="form-label">Due Date</label>
          <input type="date" class="form-control" id="todo-due-input" name="due_date"
                 value="<?php echo htmlspecialchars($dueDate); ?>">
        </div>
      </div>

      <div class="d-flex gap-2" id="todo-form-actions">
        <button type="submit" class="btn btn-primary" id="todo-form-submit-btn">
          <i class="feather-save me-1"></i> <?php echo $isEdit ? 'Update' : 'Create'; ?>
        </button>
        <button type="button" class="btn btn-outline-secondary" onclick="document.getElementById('todo-form-container').innerHTML=''" id="todo-form-cancel-btn">
          Cancel
        </button>
      </div>
    </form>
  </div>
</div>
