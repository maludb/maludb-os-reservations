<?php
require_once '../../../helpers/auth.php';
require_once '../../../helpers/csrf.php';

requireAuth();
requireAdmin();

$restaurantId = currentRestaurantId();

// Query staff users for this restaurant
$stmt = db()->prepare(
    "SELECT u.id, u.first_name, u.last_name, u.email, u.phone, u.last_login_at,
            ur.role, ur.is_active as membership_active
     FROM user_restaurants ur
     JOIN users u ON u.id = ur.user_id
     WHERE ur.restaurant_id = ?
     ORDER BY ur.role ASC, u.last_name ASC"
);
$stmt->execute([$restaurantId]);
$users = $stmt->fetchAll();
?>
<div class="main-content" id="users-main"
     hx-get="/partials/settings/users.php"
     hx-trigger="refreshUserList from:body"
     hx-target="#users-main"
     hx-swap="outerHTML">
    <div class="row" id="users-row">
        <div class="col-12" id="users-col">

            <div class="card" id="users-card">
                <div class="card-header d-flex align-items-center justify-content-between" id="users-card-header">
                    <h5 class="card-title mb-0" id="users-card-title">
                        <i class="feather-user-plus me-2"></i>Staff Users
                    </h5>
                    <button class="btn btn-primary btn-sm" id="users-add-btn"
                            hx-get="/partials/settings/user-form.php"
                            hx-target="#users-modal-container"
                            hx-swap="innerHTML">
                        <i class="feather-plus me-1"></i> Add Staff Member
                    </button>
                </div>
                <div class="card-body" id="users-card-body">

                    <!-- Feedback message area -->
                    <div id="users-messages"></div>

                    <div class="table-responsive" id="users-table-wrapper">
                        <table class="table table-hover align-middle" id="users-table">
                            <thead id="users-table-head">
                                <tr>
                                    <th>Name</th>
                                    <th>Email</th>
                                    <th>Role</th>
                                    <th>Status</th>
                                    <th>Last Login</th>
                                    <th class="text-end">Actions</th>
                                </tr>
                            </thead>
                            <tbody id="users-table-body">
                                <?php if (empty($users)): ?>
                                <tr id="users-empty-row">
                                    <td colspan="6" class="text-center text-muted py-4">No staff members found.</td>
                                </tr>
                                <?php else: ?>
                                <?php foreach ($users as $u): ?>
                                <tr id="users-row-<?php echo $u['id']; ?>">
                                    <td>
                                        <?php echo htmlspecialchars($u['first_name'] . ' ' . $u['last_name']); ?>
                                        <?php if ($u['phone']): ?>
                                        <br><small class="text-muted"><?php echo htmlspecialchars($u['phone']); ?></small>
                                        <?php endif; ?>
                                    </td>
                                    <td><?php echo htmlspecialchars($u['email']); ?></td>
                                    <td>
                                        <?php
                                        $roleBadge = match($u['role']) {
                                            'admin' => 'bg-danger',
                                            'manager' => 'bg-warning text-dark',
                                            'user' => 'bg-info text-dark',
                                            default => 'bg-secondary'
                                        };
                                        ?>
                                        <span class="badge <?php echo $roleBadge; ?>"><?php echo ucfirst($u['role']); ?></span>
                                    </td>
                                    <td>
                                        <?php if ($u['membership_active']): ?>
                                        <span class="badge bg-success" id="users-status-<?php echo $u['id']; ?>">Active</span>
                                        <?php else: ?>
                                        <span class="badge bg-secondary" id="users-status-<?php echo $u['id']; ?>">Inactive</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php echo $u['last_login_at'] ? date('M j, Y g:ia', strtotime($u['last_login_at'])) : '<span class="text-muted">Never</span>'; ?>
                                    </td>
                                    <td class="text-end" id="users-actions-<?php echo $u['id']; ?>">
                                        <button class="btn btn-outline-primary btn-sm me-1"
                                                hx-get="/partials/settings/user-form.php?user_id=<?php echo $u['id']; ?>"
                                                hx-target="#users-modal-container"
                                                hx-swap="innerHTML"
                                                title="Edit">
                                            <i class="feather-edit-2"></i>
                                        </button>
                                        <button class="btn btn-outline-<?php echo $u['membership_active'] ? 'warning' : 'success'; ?> btn-sm"
                                                hx-post="/partials/settings/toggle-user.php"
                                                hx-vals='{"user_id": <?php echo $u['id']; ?>, "csrf_token": "<?php echo htmlspecialchars(generate_csrf_token()); ?>"}'
                                                hx-target="#users-messages"
                                                hx-swap="innerHTML"
                                                hx-confirm="Are you sure you want to <?php echo $u['membership_active'] ? 'deactivate' : 'activate'; ?> this user?"
                                                title="<?php echo $u['membership_active'] ? 'Deactivate' : 'Activate'; ?>">
                                            <i class="feather-<?php echo $u['membership_active'] ? 'user-x' : 'user-check'; ?>"></i>
                                        </button>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>

                </div>
            </div>

        </div>
    </div>

    <!-- Modal container for add/edit forms -->
    <div id="users-modal-container"></div>
</div>
