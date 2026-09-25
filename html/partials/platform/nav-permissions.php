<?php
require_once '../../../helpers/auth.php';
require_once '../../../helpers/csrf.php';

requireSuperAdmin();

$pdo = db();

// Filters
$filterNavItem = trim($_GET['nav_item_id'] ?? '');

$sql = "SELECT * FROM nav_permissions WHERE 1=1";
$params = [];

if ($filterNavItem !== '') {
    $sql .= " AND nav_item_id LIKE ?";
    $params[] = '%' . $filterNavItem . '%';
}

$sql .= " ORDER BY nav_item_id, user_role, restaurant_role";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$permissions = $stmt->fetchAll();

// Get distinct nav_item_ids for filter dropdown
$navItems = $pdo->query("SELECT DISTINCT nav_item_id FROM nav_permissions ORDER BY nav_item_id")->fetchAll(PDO::FETCH_COLUMN);
?>

<div class="main-content" id="nav-permissions-page">
    <div class="row" id="nav-permissions-header">
        <div class="col-12 d-flex justify-content-between align-items-center mb-4">
            <h4 class="fw-bold mb-0" id="nav-permissions-title">
                <i class="feather-shield me-2"></i>Nav Permissions
            </h4>
            <button class="btn btn-primary"
                    hx-get="/partials/platform/nav-permission-form.php"
                    hx-target="#modal-container"
                    id="nav-permissions-add-btn">
                <i class="feather-plus me-1"></i> Add Permission
            </button>
        </div>
    </div>

    <!-- Filters -->
    <div class="row mb-3" id="nav-permissions-filter-row">
        <div class="col-md-4" id="nav-permissions-filter-nav-wrap">
            <select class="form-select" id="nav-permissions-filter-nav"
                    hx-get="/partials/platform/nav-permissions.php"
                    hx-target="#page-content"
                    hx-include="#nav-permissions-filter-location"
                    name="nav_item_id">
                <option value="">All Nav Items</option>
                <?php foreach ($navItems as $ni): ?>
                <option value="<?php echo htmlspecialchars($ni); ?>" <?php echo $filterNavItem === $ni ? 'selected' : ''; ?>>
                    <?php echo htmlspecialchars($ni); ?>
                </option>
                <?php endforeach; ?>
            </select>
        </div>
    </div>

    <div class="row" id="nav-permissions-content">
        <div class="col-12">
            <div class="card border-0 shadow-sm" id="nav-permissions-card">
                <div class="card-body p-0" id="nav-permissions-card-body">
                    <?php if (empty($permissions)): ?>
                    <div class="text-center text-muted py-5" id="nav-permissions-empty">
                        <i class="feather-shield fs-1 mb-3 d-block"></i>
                        <p>No nav permissions found.</p>
                    </div>
                    <?php else: ?>
                    <div class="table-responsive" id="nav-permissions-table-wrap">
                        <table class="table table-hover mb-0" id="nav-permissions-table">
                            <thead class="bg-light" id="nav-permissions-thead">
                                <tr>
                                    <th>Nav Item ID</th>
                                    <th>Label</th>
                                    <th>User Role</th>
                                    <th>Restaurant Role</th>
                                    <th>Active</th>
                                    <th class="text-end">Actions</th>
                                </tr>
                            </thead>
                            <tbody id="nav-permissions-tbody">
                                <?php foreach ($permissions as $p): ?>
                                <tr id="nav-perm-row-<?php echo (int)$p['id']; ?>">
                                    <td><code><?php echo htmlspecialchars($p['nav_item_id']); ?></code></td>
                                    <td><?php echo $p['label'] ? htmlspecialchars($p['label']) : '<span class="text-muted">-</span>'; ?></td>
                                    <td><?php echo $p['user_role'] ? htmlspecialchars($p['user_role']) : '<span class="text-muted">any</span>'; ?></td>
                                    <td><?php echo $p['restaurant_role'] ? htmlspecialchars($p['restaurant_role']) : '<span class="text-muted">any</span>'; ?></td>
                                    <td>
                                        <?php if ($p['is_active']): ?>
                                        <span class="badge bg-success">Yes</span>
                                        <?php else: ?>
                                        <span class="badge bg-secondary">No</span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="text-end">
                                        <button class="btn btn-sm btn-outline-primary me-1"
                                                hx-get="/partials/platform/nav-permission-form.php?id=<?php echo (int)$p['id']; ?>"
                                                hx-target="#modal-container"
                                                title="Edit"
                                                id="nav-perm-edit-btn-<?php echo (int)$p['id']; ?>">
                                            <i class="feather-edit-2"></i>
                                        </button>
                                        <button class="btn btn-sm btn-outline-danger"
                                                hx-post="/partials/platform/delete-nav-permission.php"
                                                hx-vals='{"permission_id": <?php echo (int)$p['id']; ?>, "csrf_token": "<?php echo generate_csrf_token(); ?>"}'
                                                hx-target="#page-content"
                                                hx-confirm="Delete this permission?"
                                                title="Delete"
                                                id="nav-perm-delete-btn-<?php echo (int)$p['id']; ?>">
                                            <i class="feather-trash-2"></i>
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
