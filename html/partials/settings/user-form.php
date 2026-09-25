<?php
require_once '../../../helpers/auth.php';
require_once '../../../helpers/csrf.php';

requireAuth();
requireAdmin();

$restaurantId = currentRestaurantId();
$userId = isset($_GET['user_id']) ? (int)$_GET['user_id'] : 0;
$user = null;
$membership = null;
$isEdit = false;

if ($userId > 0) {
    // Verify user belongs to this restaurant
    $stmt = db()->prepare(
        "SELECT u.id, u.first_name, u.last_name, u.email, u.phone,
                ur.role, ur.is_active
         FROM user_restaurants ur
         JOIN users u ON u.id = ur.user_id
         WHERE ur.restaurant_id = ? AND ur.user_id = ?"
    );
    $stmt->execute([$restaurantId, $userId]);
    $user = $stmt->fetch();

    if (!$user) {
        echo '<div class="alert alert-danger">User not found in this restaurant.</div>';
        exit;
    }
    $isEdit = true;
}
?>
<div class="modal fade show d-block" tabindex="-1" id="user-form-modal" style="background-color: rgba(0,0,0,0.5);">
    <div class="modal-dialog" id="user-form-dialog">
        <div class="modal-content" id="user-form-content">
            <div class="modal-header" id="user-form-header">
                <h5 class="modal-title" id="user-form-title">
                    <i class="feather-<?php echo $isEdit ? 'edit-2' : 'user-plus'; ?> me-2"></i>
                    <?php echo $isEdit ? 'Edit Staff Member' : 'Add Staff Member'; ?>
                </h5>
                <button type="button" class="btn-close" id="user-form-close-btn"
                        onclick="document.getElementById('users-modal-container').innerHTML=''"></button>
            </div>
            <div class="modal-body" id="user-form-body">

                <div id="user-form-messages"></div>

                <form id="user-form"
                      hx-post="/partials/settings/save-user.php"
                      hx-target="#user-form-messages"
                      hx-swap="innerHTML">
                    <?php echo csrf_field(); ?>
                    <input type="hidden" name="user_id" value="<?php echo $userId; ?>">

                    <div class="row" id="user-form-name-row">
                        <div class="col-md-6 mb-3" id="user-form-fname-group">
                            <label for="user-form-fname" class="form-label">First Name <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" id="user-form-fname" name="first_name"
                                   value="<?php echo htmlspecialchars($user['first_name'] ?? ''); ?>" required>
                        </div>
                        <div class="col-md-6 mb-3" id="user-form-lname-group">
                            <label for="user-form-lname" class="form-label">Last Name <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" id="user-form-lname" name="last_name"
                                   value="<?php echo htmlspecialchars($user['last_name'] ?? ''); ?>" required>
                        </div>
                    </div>

                    <div class="mb-3" id="user-form-email-group">
                        <label for="user-form-email" class="form-label">Email <span class="text-danger">*</span></label>
                        <input type="email" class="form-control" id="user-form-email" name="email"
                               value="<?php echo htmlspecialchars($user['email'] ?? ''); ?>" required
                               <?php echo $isEdit ? 'readonly style="background-color:#f8f9fa;"' : ''; ?>>
                        <?php if (!$isEdit): ?>
                        <div class="form-text" id="user-form-email-help">If this email already exists, the user will be linked to this restaurant.</div>
                        <?php endif; ?>
                    </div>

                    <div class="mb-3" id="user-form-phone-group">
                        <label for="user-form-phone" class="form-label">Phone</label>
                        <input type="tel" class="form-control" id="user-form-phone" name="phone"
                               value="<?php echo htmlspecialchars($user['phone'] ?? ''); ?>">
                    </div>

                    <div class="mb-3" id="user-form-role-group">
                        <label for="user-form-role" class="form-label">Role <span class="text-danger">*</span></label>
                        <select class="form-select" id="user-form-role" name="role" required>
                            <option value="">Select Role...</option>
                            <option value="admin" <?php echo ($user['role'] ?? '') === 'admin' ? 'selected' : ''; ?>>Admin</option>
                            <option value="manager" <?php echo ($user['role'] ?? '') === 'manager' ? 'selected' : ''; ?>>Manager</option>
                            <option value="user" <?php echo ($user['role'] ?? '') === 'user' ? 'selected' : ''; ?>>User</option>
                        </select>
                    </div>

                    <div class="mb-3" id="user-form-password-group">
                        <label for="user-form-password" class="form-label">Password</label>
                        <input type="password" class="form-control" id="user-form-password" name="password"
                               minlength="8">
                        <?php if ($isEdit): ?>
                        <div class="form-text" id="user-form-password-help">Leave blank to keep current password.</div>
                        <?php else: ?>
                        <div class="form-text" id="user-form-password-help">Leave blank to let user set their own password via Accept Invitation. Or set one now (min 8 chars, 1 uppercase, 1 lowercase, 1 number).</div>
                        <?php endif; ?>
                    </div>

                    <div class="d-flex justify-content-end gap-2" id="user-form-buttons">
                        <button type="button" class="btn btn-secondary" id="user-form-cancel-btn"
                                onclick="document.getElementById('users-modal-container').innerHTML=''">Cancel</button>
                        <button type="submit" class="btn btn-primary" id="user-form-save-btn">
                            <i class="feather-save me-1"></i> <?php echo $isEdit ? 'Update' : 'Add'; ?> User
                            <span class="htmx-indicator spinner-border spinner-border-sm ms-2"></span>
                        </button>
                    </div>
                </form>

            </div>
        </div>
    </div>
</div>
