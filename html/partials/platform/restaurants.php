<?php
require_once '../../../helpers/auth.php';
require_once '../../../helpers/csrf.php';

requireSuperAdmin();

$stmt = db()->query(
    "SELECT r.*,
            (SELECT COUNT(*) FROM user_restaurants ur WHERE ur.restaurant_id = r.id AND ur.is_active = 1) as staff_count,
            (SELECT COUNT(*) FROM reservations res WHERE res.restaurant_id = r.id AND res.reservation_date = CURRENT_DATE) as today_reservations
     FROM restaurants r
     ORDER BY r.name"
);
$restaurants = $stmt->fetchAll();
?>

<div class="main-content" id="platform-restaurants-page">
    <div class="row" id="platform-restaurants-header">
        <div class="col-12 d-flex justify-content-between align-items-center mb-4">
            <h4 class="fw-bold mb-0" id="platform-restaurants-title">
                <i class="feather-briefcase me-2"></i>Manage Locations
            </h4>
            <button class="btn btn-primary"
                    hx-get="/partials/platform/restaurant-form.php"
                    hx-target="#modal-container"
                    id="platform-add-restaurant-btn">
                <i class="feather-plus me-1"></i> Add Restaurant
            </button>
        </div>
    </div>

    <div class="row" id="platform-restaurants-content">
        <div class="col-12">
            <div class="card border-0 shadow-sm" id="platform-restaurants-card">
                <div class="card-body p-0" id="platform-restaurants-card-body">
                    <?php if (empty($restaurants)): ?>
                    <div class="text-center text-muted py-5" id="platform-no-restaurants">
                        <i class="feather-briefcase fs-1 mb-3 d-block"></i>
                        <p>No restaurants yet. Add your first restaurant to get started.</p>
                    </div>
                    <?php else: ?>
                    <div class="table-responsive" id="platform-restaurants-table-wrap">
                        <table class="table table-hover mb-0" id="platform-restaurants-table">
                            <thead class="bg-light" id="platform-restaurants-thead">
                                <tr>
                                    <th>Name</th>
                                    <th>Slug</th>
                                    <th>City/State</th>
                                    <th>Staff</th>
                                    <th>Today</th>
                                    <th>Status</th>
                                    <th>Created</th>
                                    <th class="text-end">Actions</th>
                                </tr>
                            </thead>
                            <tbody id="platform-restaurants-tbody">
                                <?php foreach ($restaurants as $r): ?>
                                <tr id="platform-restaurant-row-<?php echo (int)$r['id']; ?>">
                                    <td>
                                        <strong><?php echo htmlspecialchars($r['name']); ?></strong>
                                        <?php if ($r['email']): ?>
                                        <br><small class="text-muted"><?php echo htmlspecialchars($r['email']); ?></small>
                                        <?php endif; ?>
                                    </td>
                                    <td><code><?php echo htmlspecialchars($r['slug']); ?></code></td>
                                    <td>
                                        <?php
                                        $location = array_filter([$r['city'], $r['state']]);
                                        echo $location ? htmlspecialchars(implode(', ', $location)) : '<span class="text-muted">-</span>';
                                        ?>
                                    </td>
                                    <td><span class="badge bg-light text-dark"><?php echo (int)$r['staff_count']; ?></span></td>
                                    <td><span class="badge bg-info"><?php echo (int)$r['today_reservations']; ?></span></td>
                                    <td>
                                        <?php if ($r['is_active']): ?>
                                        <span class="badge bg-success">Active</span>
                                        <?php else: ?>
                                        <span class="badge bg-secondary">Inactive</span>
                                        <?php endif; ?>
                                    </td>
                                    <td><?php echo date('M j, Y', strtotime($r['created_at'])); ?></td>
                                    <td class="text-end">
                                        <button class="btn btn-sm btn-outline-primary"
                                                hx-get="/partials/platform/restaurant-dashboard.php?id=<?php echo (int)$r['id']; ?>"
                                                hx-target="#page-content"
                                                title="Dashboard"
                                                id="platform-edit-btn-<?php echo (int)$r['id']; ?>">
                                            <i class="feather-edit-2"></i>
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
