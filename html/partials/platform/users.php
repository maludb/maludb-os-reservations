<?php
require_once '../../../helpers/auth.php';
require_once '../../../helpers/csrf.php';

requireSuperAdmin();

$pdo = db();

$stmt = $pdo->query(
    "SELECT u.*,
            (SELECT STRING_AGG(r.name || ' (' || ur.role || ')', ', ')
             FROM user_restaurants ur
             JOIN restaurants r ON r.id = ur.restaurant_id
             WHERE ur.user_id = u.id AND ur.is_active = 1) as restaurant_assignments
     FROM users u
     ORDER BY u.role DESC, u.last_name, u.first_name"
);
$users = $stmt->fetchAll();
?>

<div class="main-content" id="platform-users-page">
    <div class="row" id="platform-users-header">
        <div class="col-12 d-flex justify-content-between align-items-center mb-4">
            <h4 class="fw-bold mb-0" id="platform-users-title">
                <i class="feather-user me-2"></i>All Users
            </h4>
            <button class="btn btn-primary"
                    hx-get="/partials/platform/platform-user-form.php"
                    hx-target="#modal-container"
                    id="platform-add-user-btn">
                <i class="feather-plus me-1"></i> Add User
            </button>
        </div>
    </div>

    <!-- Summary cards -->
    <div class="row g-3 mb-4" id="platform-users-stats">
        <?php
        $totalUsers = count($users);
        $superAdmins = count(array_filter($users, fn($u) => $u['role'] === 'super-admin'));
        $regularUsers = $totalUsers - $superAdmins;
        ?>
        <div class="col-md-3" id="platform-users-stat-total-wrap">
            <div class="card border-0 shadow-sm" id="platform-users-stat-total-card">
                <div class="card-body text-center py-3" id="platform-users-stat-total-body">
                    <h6 class="text-muted mb-1">Total Users</h6>
                    <h4 class="fw-bold mb-0"><?php echo $totalUsers; ?></h4>
                </div>
            </div>
        </div>
        <div class="col-md-3" id="platform-users-stat-superadmin-wrap">
            <div class="card border-0 shadow-sm" id="platform-users-stat-superadmin-card">
                <div class="card-body text-center py-3" id="platform-users-stat-superadmin-body">
                    <h6 class="text-muted mb-1">Super-Admins</h6>
                    <h4 class="fw-bold mb-0 text-danger"><?php echo $superAdmins; ?></h4>
                </div>
            </div>
        </div>
        <div class="col-md-3" id="platform-users-stat-regular-wrap">
            <div class="card border-0 shadow-sm" id="platform-users-stat-regular-card">
                <div class="card-body text-center py-3" id="platform-users-stat-regular-body">
                    <h6 class="text-muted mb-1">Regular Users</h6>
                    <h4 class="fw-bold mb-0"><?php echo $regularUsers; ?></h4>
                </div>
            </div>
        </div>
    </div>

    <div class="row" id="platform-users-content">
        <div class="col-12">
            <div class="card border-0 shadow-sm" id="platform-users-card">
                <div class="card-body p-0" id="platform-users-card-body">
                    <?php if (empty($users)): ?>
                    <div class="text-center text-muted py-5" id="platform-no-users">
                        <i class="feather-user fs-1 mb-3 d-block"></i>
                        <p>No users found.</p>
                    </div>
                    <?php else: ?>
                    <div class="table-responsive" id="platform-users-table-wrap">
                        <table class="table table-hover mb-0" id="platform-users-table">
                            <thead class="bg-light" id="platform-users-thead">
                                <tr>
                                    <th>Name</th>
                                    <th>Email</th>
                                    <th>Platform Role</th>
                                    <th>User Type</th>
                                    <th>Restaurant Assignments</th>
                                    <th>Status</th>
                                    <th>Last Login</th>
                                    <th class="text-end">Actions</th>
                                </tr>
                            </thead>
                            <tbody id="platform-users-tbody">
                                <?php foreach ($users as $u): ?>
                                <tr id="platform-user-row-<?php echo (int)$u['id']; ?>">
                                    <td>
                                        <strong><?php echo htmlspecialchars($u['first_name'] . ' ' . $u['last_name']); ?></strong>
                                        <?php if ($u['phone']): ?>
                                        <br><small class="text-muted"><?php echo htmlspecialchars($u['phone']); ?></small>
                                        <?php endif; ?>
                                    </td>
                                    <td><?php echo htmlspecialchars($u['email']); ?></td>
                                    <td>
                                        <?php
                                        $roleColors = ['super-admin' => 'danger', 'user' => 'secondary'];
                                        $roleColor = $roleColors[$u['role']] ?? 'secondary';
                                        ?>
                                        <span class="badge bg-<?php echo $roleColor; ?>"><?php echo htmlspecialchars($u['role']); ?></span>
                                    </td>
                                    <td id="platform-user-type-<?php echo (int)$u['id']; ?>">
                                        <?php
                                        $utLabels = ['system_owner' => 'System Owner', 'user' => 'User'];
                                        $utColors = ['system_owner' => 'primary', 'user' => 'secondary'];
                                        $ut = $u['user_type'] ?? 'user';
                                        ?>
                                        <span class="badge bg-<?php echo $utColors[$ut] ?? 'secondary'; ?>"><?php echo $utLabels[$ut] ?? ucfirst($ut); ?></span>
                                    </td>
                                    <td>
                                        <?php if ($u['role'] === 'super-admin'): ?>
                                        <span class="text-muted fst-italic">All restaurants</span>
                                        <?php elseif ($u['restaurant_assignments']): ?>
                                        <small><?php echo htmlspecialchars($u['restaurant_assignments']); ?></small>
                                        <?php else: ?>
                                        <span class="text-muted">None</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php if ($u['is_active']): ?>
                                        <span class="badge bg-success">Active</span>
                                        <?php else: ?>
                                        <span class="badge bg-secondary">Inactive</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php echo $u['last_login_at'] ? date('M j, Y g:ia', strtotime($u['last_login_at'])) : '<span class="text-muted">Never</span>'; ?>
                                    </td>
                                    <td class="text-end">
                                        <button class="btn btn-sm btn-outline-primary me-1"
                                                hx-get="/partials/platform/platform-user-form.php?id=<?php echo (int)$u['id']; ?>"
                                                hx-target="#modal-container"
                                                title="Edit"
                                                id="platform-edit-user-btn-<?php echo (int)$u['id']; ?>">
                                            <i class="feather-edit-2"></i>
                                        </button>
                                        <button class="btn btn-sm btn-outline-<?php echo $u['is_active'] ? 'warning' : 'success'; ?>"
                                                hx-post="/partials/platform/toggle-platform-user.php"
                                                hx-vals='{"user_id": <?php echo (int)$u['id']; ?>, "csrf_token": "<?php echo generate_csrf_token(); ?>"}'
                                                hx-target="#page-content"
                                                hx-confirm="<?php echo $u['is_active'] ? 'Deactivate this user?' : 'Activate this user?'; ?>"
                                                title="<?php echo $u['is_active'] ? 'Deactivate' : 'Activate'; ?>"
                                                id="platform-toggle-user-btn-<?php echo (int)$u['id']; ?>">
                                            <i class="feather-<?php echo $u['is_active'] ? 'user-x' : 'user-check'; ?>"></i>
                                        </button>
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
    </div>
</div>
