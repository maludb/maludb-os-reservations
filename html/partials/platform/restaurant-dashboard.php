<?php
require_once '../../../helpers/auth.php';
require_once '../../../helpers/csrf.php';

requireSuperAdmin();

$pdo = db();
$id = (int)($_GET['id'] ?? 0);

if ($id <= 0) {
    echo '<div class="alert alert-danger">Invalid restaurant.</div>';
    exit;
}

$stmt = $pdo->prepare("SELECT * FROM restaurants WHERE id = ?");
$stmt->execute([$id]);
$restaurant = $stmt->fetch();
if (!$restaurant) {
    echo '<div class="alert alert-danger">Restaurant not found.</div>';
    exit;
}

// Staff count
$stmt = $pdo->prepare(
    "SELECT u.id, u.first_name, u.last_name, u.email, u.phone, u.last_login_at, ur.role, ur.is_active
     FROM user_restaurants ur
     JOIN users u ON u.id = ur.user_id
     WHERE ur.restaurant_id = ?
     ORDER BY ur.role, u.last_name"
);
$stmt->execute([$id]);
$staff = $stmt->fetchAll();

// Today's reservations
$stmt = $pdo->prepare(
    "SELECT COUNT(*) FROM reservations WHERE restaurant_id = ? AND reservation_date = CURRENT_DATE"
);
$stmt->execute([$id]);
$todayReservations = (int)$stmt->fetchColumn();

// Total reservations
$stmt = $pdo->prepare("SELECT COUNT(*) FROM reservations WHERE restaurant_id = ?");
$stmt->execute([$id]);
$totalReservations = (int)$stmt->fetchColumn();

// Subscription info
$stmt = $pdo->prepare(
    "SELECT s.*, p.name as product_name, p.price, p.billing_interval
     FROM subscriptions s
     JOIN products p ON p.id = s.product_id
     WHERE s.restaurant_id = ?
     ORDER BY s.created_at DESC"
);
$stmt->execute([$id]);
$subscriptions = $stmt->fetchAll();

// Account options
$stmt = $pdo->prepare(
    "SELECT * FROM account_options WHERE restaurant_id = ? ORDER BY option_key"
);
$stmt->execute([$id]);
$options = $stmt->fetchAll();

// Active staff count
$activeStaff = 0;
foreach ($staff as $s) {
    if ($s['is_active']) $activeStaff++;
}

// Current subscription
$currentSub = null;
foreach ($subscriptions as $s) {
    if ($s['status'] === 'active' || $s['status'] === 'trialing') {
        $currentSub = $s;
        break;
    }
}
?>

<div class="main-content" id="platform-restaurant-dashboard-page">
    <!-- Header -->
    <div class="row mb-4" id="platform-restaurant-dashboard-header">
        <div class="col-12 d-flex justify-content-between align-items-center">
            <div id="platform-restaurant-dashboard-title-area">
                <button class="btn btn-sm btn-outline-secondary me-2"
                        hx-get="/partials/platform/restaurants.php"
                        hx-target="#page-content"
                        id="platform-restaurant-back-btn">
                    <i class="feather-arrow-left"></i>
                </button>
                <h4 class="fw-bold mb-0 d-inline" id="platform-restaurant-dashboard-name">
                    <?php echo htmlspecialchars($restaurant['name']); ?>
                </h4>
                <span class="ms-2" id="platform-restaurant-dashboard-status">
                    <?php if ($restaurant['is_active']): ?>
                    <span class="badge bg-success">Active</span>
                    <?php else: ?>
                    <span class="badge bg-secondary">Inactive</span>
                    <?php endif; ?>
                </span>
            </div>
            <div id="platform-restaurant-dashboard-actions">
                <button class="btn btn-outline-<?php echo $restaurant['is_active'] ? 'warning' : 'success'; ?> me-2"
                        hx-post="/partials/platform/toggle-restaurant.php"
                        hx-vals='{"restaurant_id": <?php echo (int)$restaurant['id']; ?>, "source": "dashboard", "csrf_token": "<?php echo generate_csrf_token(); ?>"}'
                        hx-target="#page-content"
                        hx-confirm="<?php echo $restaurant['is_active'] ? 'Deactivate this restaurant?' : 'Activate this restaurant?'; ?>"
                        id="platform-restaurant-dashboard-toggle-btn">
                    <i class="feather-<?php echo $restaurant['is_active'] ? 'pause-circle' : 'play-circle'; ?> me-1"></i>
                    <?php echo $restaurant['is_active'] ? 'Deactivate' : 'Activate'; ?>
                </button>
                <button class="btn btn-outline-primary"
                        hx-get="/partials/platform/restaurant-form.php?id=<?php echo (int)$restaurant['id']; ?>"
                        hx-target="#modal-container"
                        id="platform-restaurant-dashboard-edit-btn">
                    <i class="feather-edit-2 me-1"></i> Edit
                </button>
            </div>
        </div>
    </div>

    <!-- Stats cards -->
    <div class="row g-3 mb-4" id="platform-restaurant-stats-row">
        <div class="col-md-3" id="platform-restaurant-stat-plan">
            <div class="card border-0 shadow-sm" id="platform-restaurant-stat-plan-card">
                <div class="card-body text-center" id="platform-restaurant-stat-plan-body">
                    <h6 class="text-muted mb-1">Subscription</h6>
                    <?php if ($currentSub): ?>
                    <h4 class="fw-bold"><?php echo htmlspecialchars($currentSub['product_name']); ?></h4>
                    <small class="text-muted">
                        <span class="badge bg-success"><?php echo ucfirst($currentSub['status']); ?></span>
                    </small>
                    <?php else: ?>
                    <h4 class="fw-bold text-muted">None</h4>
                    <small class="text-muted">No active plan</small>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        <div class="col-md-3" id="platform-restaurant-stat-staff">
            <div class="card border-0 shadow-sm" id="platform-restaurant-stat-staff-card">
                <div class="card-body text-center" id="platform-restaurant-stat-staff-body">
                    <h6 class="text-muted mb-1">Staff</h6>
                    <h4 class="fw-bold"><?php echo $activeStaff; ?></h4>
                    <small class="text-muted"><?php echo count($staff); ?> total</small>
                </div>
            </div>
        </div>
        <div class="col-md-3" id="platform-restaurant-stat-today">
            <div class="card border-0 shadow-sm" id="platform-restaurant-stat-today-card">
                <div class="card-body text-center" id="platform-restaurant-stat-today-body">
                    <h6 class="text-muted mb-1">Today</h6>
                    <h4 class="fw-bold"><?php echo $todayReservations; ?></h4>
                    <small class="text-muted">reservations</small>
                </div>
            </div>
        </div>
        <div class="col-md-3" id="platform-restaurant-stat-total">
            <div class="card border-0 shadow-sm" id="platform-restaurant-stat-total-card">
                <div class="card-body text-center" id="platform-restaurant-stat-total-body">
                    <h6 class="text-muted mb-1">All Time</h6>
                    <h4 class="fw-bold"><?php echo number_format($totalReservations); ?></h4>
                    <small class="text-muted">reservations</small>
                </div>
            </div>
        </div>
    </div>

    <!-- Details card -->
    <div class="row mb-4" id="platform-restaurant-details-section">
        <div class="col-12">
            <div class="card border-0 shadow-sm" id="platform-restaurant-details-card">
                <div class="card-header bg-white" id="platform-restaurant-details-header">
                    <h5 class="mb-0 fw-semibold" id="platform-restaurant-details-title">Restaurant Details</h5>
                </div>
                <div class="card-body" id="platform-restaurant-details-body">
                    <div class="row g-3" id="platform-restaurant-details-fields">
                        <div class="col-md-4" id="platform-restaurant-detail-slug">
                            <label class="text-muted small">Slug</label>
                            <div><code><?php echo htmlspecialchars($restaurant['slug']); ?></code></div>
                        </div>
                        <div class="col-md-4" id="platform-restaurant-detail-phone">
                            <label class="text-muted small">Phone</label>
                            <div><?php echo $restaurant['phone'] ? htmlspecialchars($restaurant['phone']) : '<span class="text-muted">-</span>'; ?></div>
                        </div>
                        <div class="col-md-4" id="platform-restaurant-detail-email">
                            <label class="text-muted small">Email</label>
                            <div><?php echo $restaurant['email'] ? htmlspecialchars($restaurant['email']) : '<span class="text-muted">-</span>'; ?></div>
                        </div>
                        <div class="col-md-4" id="platform-restaurant-detail-address">
                            <label class="text-muted small">Address</label>
                            <div>
                                <?php
                                $addr = array_filter([
                                    $restaurant['address_line1'],
                                    $restaurant['address_line2']
                                ]);
                                $cityState = array_filter([
                                    $restaurant['city'],
                                    $restaurant['state']
                                ]);
                                $full = array_filter([
                                    implode(', ', $addr),
                                    implode(', ', $cityState),
                                    $restaurant['postal_code']
                                ]);
                                echo $full ? htmlspecialchars(implode(', ', $full)) : '<span class="text-muted">-</span>';
                                ?>
                            </div>
                        </div>
                        <div class="col-md-4" id="platform-restaurant-detail-website">
                            <label class="text-muted small">Website</label>
                            <div><?php echo $restaurant['website'] ? '<a href="' . htmlspecialchars($restaurant['website']) . '" target="_blank">' . htmlspecialchars($restaurant['website']) . '</a>' : '<span class="text-muted">-</span>'; ?></div>
                        </div>
                        <div class="col-md-4" id="platform-restaurant-detail-timezone">
                            <label class="text-muted small">Timezone</label>
                            <div><?php echo htmlspecialchars($restaurant['timezone']); ?></div>
                        </div>
                        <div class="col-md-4" id="platform-restaurant-detail-created">
                            <label class="text-muted small">Created</label>
                            <div><?php echo date('M j, Y', strtotime($restaurant['created_at'])); ?></div>
                        </div>
                        <div class="col-md-4" id="platform-restaurant-detail-updated">
                            <label class="text-muted small">Last Updated</label>
                            <div><?php echo date('M j, Y g:ia', strtotime($restaurant['updated_at'])); ?></div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Subscriptions section -->
    <div class="row mb-4" id="platform-restaurant-subscriptions-section">
        <div class="col-12">
            <div class="card border-0 shadow-sm" id="platform-restaurant-subscriptions-card">
                <div class="card-header bg-white d-flex justify-content-between align-items-center" id="platform-restaurant-subscriptions-header">
                    <h5 class="mb-0 fw-semibold" id="platform-restaurant-subscriptions-title">Subscriptions</h5>
                    <button class="btn btn-sm btn-primary"
                            hx-get="/partials/platform/subscription-form.php?restaurant_id=<?php echo (int)$restaurant['id']; ?>"
                            hx-target="#modal-container"
                            id="platform-restaurant-add-sub-btn">
                        <i class="feather-plus me-1"></i> Add Subscription
                    </button>
                </div>
                <div class="card-body p-0" id="platform-restaurant-subscriptions-body">
                    <?php if (empty($subscriptions)): ?>
                    <div class="text-center text-muted py-4" id="platform-restaurant-no-subs">
                        <p>No subscriptions.</p>
                    </div>
                    <?php else: ?>
                    <div class="table-responsive" id="platform-restaurant-subs-table-wrap">
                        <table class="table table-hover mb-0" id="platform-restaurant-subs-table">
                            <thead class="bg-light" id="platform-restaurant-subs-thead">
                                <tr>
                                    <th>Product</th>
                                    <th>Price</th>
                                    <th>Billing</th>
                                    <th>Status</th>
                                    <th>Started</th>
                                    <th>Expires</th>
                                </tr>
                            </thead>
                            <tbody id="platform-restaurant-subs-tbody">
                                <?php foreach ($subscriptions as $sub): ?>
                                <tr id="platform-restaurant-sub-row-<?php echo (int)$sub['id']; ?>">
                                    <td><strong><?php echo htmlspecialchars($sub['product_name']); ?></strong></td>
                                    <td>$<?php echo number_format($sub['price'], 2); ?></td>
                                    <td><?php echo ucfirst($sub['billing_interval']); ?></td>
                                    <td>
                                        <?php
                                        $subColors = ['active' => 'success', 'trialing' => 'info', 'past_due' => 'warning', 'cancelled' => 'secondary'];
                                        ?>
                                        <span class="badge bg-<?php echo $subColors[$sub['status']] ?? 'dark'; ?>">
                                            <?php echo ucfirst(str_replace('_', ' ', $sub['status'])); ?>
                                        </span>
                                    </td>
                                    <td><?php echo $sub['started_at'] ? date('M j, Y', strtotime($sub['started_at'])) : '-'; ?></td>
                                    <td><?php echo $sub['expires_at'] ? date('M j, Y', strtotime($sub['expires_at'])) : '-'; ?></td>
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

    <!-- Staff section -->
    <div class="row mb-4" id="platform-restaurant-staff-section">
        <div class="col-12">
            <div class="card border-0 shadow-sm" id="platform-restaurant-staff-card">
                <div class="card-header bg-white" id="platform-restaurant-staff-header">
                    <h5 class="mb-0 fw-semibold" id="platform-restaurant-staff-title">Staff</h5>
                </div>
                <div class="card-body p-0" id="platform-restaurant-staff-body">
                    <?php if (empty($staff)): ?>
                    <div class="text-center text-muted py-4" id="platform-restaurant-no-staff">
                        <p>No staff assigned.</p>
                    </div>
                    <?php else: ?>
                    <div class="table-responsive" id="platform-restaurant-staff-table-wrap">
                        <table class="table table-hover mb-0" id="platform-restaurant-staff-table">
                            <thead class="bg-light" id="platform-restaurant-staff-thead">
                                <tr>
                                    <th>Name</th>
                                    <th>Email</th>
                                    <th>Phone</th>
                                    <th>Role</th>
                                    <th>Status</th>
                                    <th>Last Login</th>
                                </tr>
                            </thead>
                            <tbody id="platform-restaurant-staff-tbody">
                                <?php foreach ($staff as $s): ?>
                                <tr id="platform-restaurant-staff-row-<?php echo (int)$s['id']; ?>">
                                    <td><strong><?php echo htmlspecialchars($s['first_name'] . ' ' . $s['last_name']); ?></strong></td>
                                    <td><?php echo htmlspecialchars($s['email']); ?></td>
                                    <td><?php echo $s['phone'] ? htmlspecialchars($s['phone']) : '<span class="text-muted">-</span>'; ?></td>
                                    <td><span class="badge bg-light text-dark"><?php echo ucfirst($s['role']); ?></span></td>
                                    <td>
                                        <?php if ($s['is_active']): ?>
                                        <span class="badge bg-success">Active</span>
                                        <?php else: ?>
                                        <span class="badge bg-secondary">Inactive</span>
                                        <?php endif; ?>
                                    </td>
                                    <td><?php echo $s['last_login_at'] ? date('M j, Y g:ia', strtotime($s['last_login_at'])) : '<span class="text-muted">Never</span>'; ?></td>
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

    <!-- Account Options section -->
    <div class="row" id="platform-restaurant-options-section">
        <div class="col-12">
            <div class="card border-0 shadow-sm" id="platform-restaurant-options-card">
                <div class="card-header bg-white d-flex justify-content-between align-items-center" id="platform-restaurant-options-header">
                    <h5 class="mb-0 fw-semibold" id="platform-restaurant-options-title">Account Options</h5>
                    <button class="btn btn-sm btn-primary"
                            hx-get="/partials/platform/account-option-form.php?restaurant_id=<?php echo (int)$restaurant['id']; ?>"
                            hx-target="#modal-container"
                            id="platform-restaurant-add-option-btn">
                        <i class="feather-plus me-1"></i> Add Option
                    </button>
                </div>
                <div class="card-body p-0" id="platform-restaurant-options-body">
                    <?php if (empty($options)): ?>
                    <div class="text-center text-muted py-4" id="platform-restaurant-no-options">
                        <p>No account options configured.</p>
                    </div>
                    <?php else: ?>
                    <div class="table-responsive" id="platform-restaurant-options-table-wrap">
                        <table class="table table-hover mb-0" id="platform-restaurant-options-table">
                            <thead class="bg-light" id="platform-restaurant-options-thead">
                                <tr>
                                    <th>Key</th>
                                    <th>Value</th>
                                    <th>Enabled</th>
                                    <th>Notes</th>
                                </tr>
                            </thead>
                            <tbody id="platform-restaurant-options-tbody">
                                <?php foreach ($options as $opt): ?>
                                <tr id="platform-restaurant-option-row-<?php echo (int)$opt['id']; ?>">
                                    <td><code><?php echo htmlspecialchars($opt['option_key']); ?></code></td>
                                    <td><?php echo htmlspecialchars($opt['option_value']); ?></td>
                                    <td>
                                        <?php if ($opt['enabled']): ?>
                                        <span class="badge bg-success">Yes</span>
                                        <?php else: ?>
                                        <span class="badge bg-secondary">No</span>
                                        <?php endif; ?>
                                    </td>
                                    <td><?php echo $opt['notes'] ? htmlspecialchars($opt['notes']) : '<span class="text-muted">-</span>'; ?></td>
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
