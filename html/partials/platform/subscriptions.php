<?php
require_once '../../../helpers/auth.php';
require_once '../../../helpers/csrf.php';

requireSuperAdmin();

$pdo = db();

$stmt = $pdo->query(
    "SELECT s.*, r.name as restaurant_name, r.slug as restaurant_slug, p.name as product_name, p.price as product_price,
            p.billing_interval
     FROM subscriptions s
     JOIN restaurants r ON r.id = s.restaurant_id
     JOIN products p ON p.id = s.product_id
     ORDER BY s.created_at DESC"
);
$subscriptions = $stmt->fetchAll();

// Get restaurants and products for the form
$restaurants = $pdo->query("SELECT id, name FROM restaurants ORDER BY name")->fetchAll();
$products = $pdo->query("SELECT id, name, price, billing_interval FROM products WHERE is_active = 1 ORDER BY sort_order, name")->fetchAll();
?>

<div class="main-content" id="platform-subscriptions-page">
    <div class="row" id="platform-subscriptions-header">
        <div class="col-12 d-flex justify-content-between align-items-center mb-4">
            <h4 class="fw-bold mb-0" id="platform-subscriptions-title">
                <i class="feather-credit-card me-2"></i>Subscriptions
            </h4>
            <button class="btn btn-primary"
                    hx-get="/partials/platform/subscription-form.php"
                    hx-target="#modal-container"
                    id="platform-add-subscription-btn">
                <i class="feather-plus me-1"></i> Add Subscription
            </button>
        </div>
    </div>

    <div class="row" id="platform-subscriptions-content">
        <div class="col-12">
            <div class="card border-0 shadow-sm" id="platform-subscriptions-card">
                <div class="card-body p-0" id="platform-subscriptions-card-body">
                    <?php if (empty($subscriptions)): ?>
                    <div class="text-center text-muted py-5" id="platform-no-subscriptions">
                        <i class="feather-credit-card fs-1 mb-3 d-block"></i>
                        <p>No subscriptions yet.</p>
                    </div>
                    <?php else: ?>
                    <div class="table-responsive" id="platform-subscriptions-table-wrap">
                        <table class="table table-hover mb-0" id="platform-subscriptions-table">
                            <thead class="bg-light" id="platform-subscriptions-thead">
                                <tr>
                                    <th>Restaurant</th>
                                    <th>Product</th>
                                    <th>Price</th>
                                    <th>Billing</th>
                                    <th>Status</th>
                                    <th>Started</th>
                                    <th>Expires</th>
                                    <th class="text-end">Actions</th>
                                </tr>
                            </thead>
                            <tbody id="platform-subscriptions-tbody">
                                <?php foreach ($subscriptions as $s): ?>
                                <tr id="platform-subscription-row-<?php echo (int)$s['id']; ?>">
                                    <td><strong><?php echo htmlspecialchars($s['restaurant_name']); ?></strong></td>
                                    <td><?php echo htmlspecialchars($s['product_name']); ?></td>
                                    <td>$<?php echo number_format($s['product_price'], 2); ?></td>
                                    <td><span class="badge bg-light text-dark"><?php echo ucfirst(str_replace('_', ' ', $s['billing_interval'])); ?></span></td>
                                    <td>
                                        <?php
                                        $statusColors = ['active' => 'success', 'trialing' => 'info', 'past_due' => 'warning', 'cancelled' => 'secondary'];
                                        $color = $statusColors[$s['status']] ?? 'dark';
                                        ?>
                                        <span class="badge bg-<?php echo $color; ?>"><?php echo ucfirst(str_replace('_', ' ', $s['status'])); ?></span>
                                    </td>
                                    <td><?php echo date('M j, Y', strtotime($s['started_at'])); ?></td>
                                    <td><?php echo $s['expires_at'] ? date('M j, Y', strtotime($s['expires_at'])) : '-'; ?></td>
                                    <td class="text-end">
                                        <button class="btn btn-sm btn-outline-primary me-1"
                                                hx-get="/partials/platform/subscription-form.php?id=<?php echo (int)$s['id']; ?>"
                                                hx-target="#modal-container"
                                                title="Edit"
                                                id="platform-edit-sub-btn-<?php echo (int)$s['id']; ?>">
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
