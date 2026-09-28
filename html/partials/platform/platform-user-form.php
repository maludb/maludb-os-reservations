<?php
require_once '../../../helpers/auth.php';
require_once '../../../helpers/csrf.php';

requireSuperAdmin();
if (os_enabled()) { echo os_managed_notice('platform-user-form-os-managed'); exit; }

$pdo = db();
$id = (int)($_GET['id'] ?? 0);
$user = null;
$isEdit = false;
$userRestaurants = [];

if ($id > 0) {
    $stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
    $stmt->execute([$id]);
    $user = $stmt->fetch();
    if (!$user) {
        echo '<div class="alert alert-danger">User not found.</div>';
        exit;
    }
    $isEdit = true;

    // Get current restaurant assignments
    $urStmt = $pdo->prepare(
        "SELECT ur.restaurant_id, ur.role, r.name as restaurant_name
         FROM user_restaurants ur
         JOIN restaurants r ON r.id = ur.restaurant_id
         WHERE ur.user_id = ? AND ur.is_active = 1
         ORDER BY r.name"
    );
    $urStmt->execute([$id]);
    $userRestaurants = $urStmt->fetchAll();
}

$restaurants = $pdo->query("SELECT id, name FROM restaurants WHERE is_active = 1 ORDER BY name")->fetchAll();
?>

<div class="modal fade" data-bs-backdrop="static" data-bs-keyboard="false" tabindex="-1" id="platform-user-modal">
    <div class="modal-dialog modal-lg" id="platform-user-modal-dialog">
        <div class="modal-content" id="platform-user-modal-content">
            <div class="modal-header" id="platform-user-modal-header">
                <h5 class="modal-title" id="platform-user-modal-title">
                    <?php echo $isEdit ? 'Edit User' : 'Add User'; ?>
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form hx-post="/partials/platform/save-platform-user.php"
                  hx-target="#page-content"
                  id="platform-user-form">
                <div class="modal-body" id="platform-user-modal-body">
                    <?php echo csrf_field(); ?>
                    <?php if ($isEdit): ?>
                    <input type="hidden" name="id" value="<?php echo (int)$user['id']; ?>">
                    <?php endif; ?>

                    <div id="platform-user-form-feedback"></div>

                    <div class="row g-3" id="platform-user-form-fields">
                        <div class="col-md-6" id="platform-user-fname-wrap">
                            <label for="platform-user-fname" class="form-label fw-semibold">First Name <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" id="platform-user-fname" name="first_name"
                                   value="<?php echo htmlspecialchars($user['first_name'] ?? ''); ?>" required>
                        </div>
                        <div class="col-md-6" id="platform-user-lname-wrap">
                            <label for="platform-user-lname" class="form-label fw-semibold">Last Name <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" id="platform-user-lname" name="last_name"
                                   value="<?php echo htmlspecialchars($user['last_name'] ?? ''); ?>" required>
                        </div>
                        <div class="col-md-6" id="platform-user-email-wrap">
                            <label for="platform-user-email" class="form-label fw-semibold">Email <span class="text-danger">*</span></label>
                            <input type="email" class="form-control" id="platform-user-email" name="email"
                                   value="<?php echo htmlspecialchars($user['email'] ?? ''); ?>" required>
                        </div>
                        <div class="col-md-6" id="platform-user-phone-wrap">
                            <label for="platform-user-phone" class="form-label fw-semibold">Phone</label>
                            <input type="tel" class="form-control" id="platform-user-phone" name="phone"
                                   value="<?php echo htmlspecialchars($user['phone'] ?? ''); ?>">
                        </div>
                        <div class="col-md-6" id="platform-user-password-wrap">
                            <label for="platform-user-password" class="form-label fw-semibold">Password <?php echo $isEdit ? '' : '<span class="text-danger">*</span>'; ?></label>
                            <input type="password" class="form-control" id="platform-user-password" name="password"
                                   <?php echo $isEdit ? '' : 'required'; ?>>
                            <?php if ($isEdit): ?>
                            <small class="text-muted">Leave blank to keep current password</small>
                            <?php endif; ?>
                        </div>
                        <div class="col-md-6" id="platform-user-role-wrap">
                            <label for="platform-user-role" class="form-label fw-semibold">Platform Role <span class="text-danger">*</span></label>
                            <select class="form-select" id="platform-user-role" name="role" required>
                                <option value="user" <?php echo ($user['role'] ?? 'user') === 'user' ? 'selected' : ''; ?>>User</option>
                                <option value="super-admin" <?php echo ($user['role'] ?? '') === 'super-admin' ? 'selected' : ''; ?>>Super-Admin</option>
                            </select>
                            <small class="text-muted">Super-admins have access to all restaurants</small>
                        </div>
                    </div>

                    <?php if ($isEdit): ?>
                    <!-- Restaurant assignments -->
                    <hr class="my-3">
                    <h6 class="fw-semibold mb-3" id="platform-user-assignments-heading">Restaurant Assignments</h6>
                    <?php if (!empty($userRestaurants)): ?>
                    <div class="table-responsive mb-3" id="platform-user-assignments-table-wrap">
                        <table class="table table-sm mb-0" id="platform-user-assignments-table">
                            <thead class="bg-light">
                                <tr>
                                    <th>Restaurant</th>
                                    <th>Role</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($userRestaurants as $ur): ?>
                                <tr id="platform-user-assignment-<?php echo (int)$ur['restaurant_id']; ?>">
                                    <td><?php echo htmlspecialchars($ur['restaurant_name']); ?></td>
                                    <td><span class="badge bg-light text-dark"><?php echo htmlspecialchars($ur['role']); ?></span></td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                    <?php else: ?>
                    <p class="text-muted" id="platform-user-no-assignments">No restaurant assignments.</p>
                    <?php endif; ?>

                    <!-- Add assignment -->
                    <div class="row g-2" id="platform-user-add-assignment">
                        <div class="col-md-5" id="platform-user-assign-restaurant-wrap">
                            <select class="form-select form-select-sm" name="assign_restaurant_id" id="platform-user-assign-restaurant">
                                <option value="">Add to restaurant...</option>
                                <?php foreach ($restaurants as $r): ?>
                                <option value="<?php echo (int)$r['id']; ?>"><?php echo htmlspecialchars($r['name']); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-4" id="platform-user-assign-role-wrap">
                            <select class="form-select form-select-sm" name="assign_role" id="platform-user-assign-role">
                                <option value="user">User</option>
                                <option value="manager">Manager</option>
                                <option value="admin">Admin</option>
                            </select>
                        </div>
                    </div>
                    <small class="text-muted">Select a restaurant and role to add/update assignment on save</small>
                    <?php endif; ?>
                </div>
                <div class="modal-footer" id="platform-user-modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary" id="platform-user-save-btn">
                        <i class="feather-save me-1"></i> <?php echo $isEdit ? 'Update' : 'Create'; ?> User
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>
