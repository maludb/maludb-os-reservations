<?php
require_once '../../../helpers/auth.php';
require_once '../../../helpers/csrf.php';

requireSuperAdmin();

$pdo = db();
$id = (int)($_GET['id'] ?? 0);
$perm = null;
$isEdit = false;

if ($id > 0) {
    $stmt = $pdo->prepare("SELECT * FROM nav_permissions WHERE id = ?");
    $stmt->execute([$id]);
    $perm = $stmt->fetch();
    if (!$perm) {
        echo '<div class="alert alert-danger">Permission not found.</div>';
        exit;
    }
    $isEdit = true;
}
?>

<div class="modal fade" data-bs-backdrop="static" data-bs-keyboard="false" tabindex="-1" id="nav-permission-modal">
    <div class="modal-dialog" id="nav-permission-modal-dialog">
        <div class="modal-content" id="nav-permission-modal-content">
            <div class="modal-header" id="nav-permission-modal-header">
                <h5 class="modal-title" id="nav-permission-modal-title">
                    <?php echo $isEdit ? 'Edit Nav Permission' : 'Add Nav Permission'; ?>
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form hx-post="/partials/platform/save-nav-permission.php"
                  hx-target="#page-content"
                  id="nav-permission-form">
                <div class="modal-body" id="nav-permission-modal-body">
                    <?php echo csrf_field(); ?>
                    <?php if ($isEdit): ?>
                    <input type="hidden" name="id" value="<?php echo (int)$perm['id']; ?>">
                    <?php endif; ?>

                    <div class="row g-3" id="nav-permission-form-fields">
                        <div class="col-md-6" id="nav-perm-nav-item-wrap">
                            <label for="nav-perm-nav-item" class="form-label fw-semibold">Nav Item ID <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" id="nav-perm-nav-item" name="nav_item_id"
                                   value="<?php echo htmlspecialchars($perm['nav_item_id'] ?? ''); ?>" required
                                   placeholder="e.g. nav-dashboard">
                            <small class="text-muted">HTML id of the sidenav &lt;li&gt; element</small>
                        </div>
                        <div class="col-md-6" id="nav-perm-label-wrap">
                            <label for="nav-perm-label" class="form-label fw-semibold">Label</label>
                            <input type="text" class="form-control" id="nav-perm-label" name="label"
                                   value="<?php echo htmlspecialchars($perm['label'] ?? ''); ?>"
                                   placeholder="e.g. Dashboard">
                            <small class="text-muted">Human-readable name for this button</small>
                        </div>
                        <div class="col-md-6" id="nav-perm-user-role-wrap">
                            <label for="nav-perm-user-role" class="form-label fw-semibold">User Role</label>
                            <select class="form-select" id="nav-perm-user-role" name="user_role">
                                <option value="" <?php echo empty($perm['user_role']) ? 'selected' : ''; ?>>any</option>
                                <option value="super-admin" <?php echo ($perm['user_role'] ?? '') === 'super-admin' ? 'selected' : ''; ?>>super-admin</option>
                                <option value="user" <?php echo ($perm['user_role'] ?? '') === 'user' ? 'selected' : ''; ?>>user</option>
                            </select>
                            <small class="text-muted">users.role — leave "any" to match all</small>
                        </div>
                        <div class="col-md-6" id="nav-perm-restaurant-role-wrap">
                            <label for="nav-perm-restaurant-role" class="form-label fw-semibold">Restaurant Role</label>
                            <select class="form-select" id="nav-perm-restaurant-role" name="restaurant_role">
                                <option value="" <?php echo empty($perm['restaurant_role']) ? 'selected' : ''; ?>>any</option>
                                <option value="admin" <?php echo ($perm['restaurant_role'] ?? '') === 'admin' ? 'selected' : ''; ?>>admin</option>
                                <option value="manager" <?php echo ($perm['restaurant_role'] ?? '') === 'manager' ? 'selected' : ''; ?>>manager</option>
                                <option value="user" <?php echo ($perm['restaurant_role'] ?? '') === 'user' ? 'selected' : ''; ?>>user</option>
                            </select>
                            <small class="text-muted">user_restaurants.role — leave "any" to match all</small>
                        </div>
                        <div class="col-md-6 d-flex align-items-end" id="nav-perm-active-wrap">
                            <div class="form-check form-switch">
                                <input class="form-check-input" type="checkbox" id="nav-perm-active" name="is_active" value="1"
                                       <?php echo ($perm['is_active'] ?? 1) ? 'checked' : ''; ?>>
                                <label class="form-check-label fw-semibold" for="nav-perm-active">Active</label>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer" id="nav-permission-modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary" id="nav-perm-save-btn">
                        <i class="feather-save me-1"></i> <?php echo $isEdit ? 'Update' : 'Create'; ?>
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>
