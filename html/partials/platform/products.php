<?php
require_once '../../../helpers/auth.php';
require_once '../../../helpers/csrf.php';

requireSuperAdmin();

$stmt = db()->query(
    "SELECT p.*,
            (SELECT COUNT(*) FROM product_features pf WHERE pf.product_id = p.id) as feature_count,
            (SELECT COUNT(*) FROM subscriptions s WHERE s.product_id = p.id AND s.status IN ('active','trialing')) as active_subs
     FROM products p
     ORDER BY p.sort_order, p.name"
);
$products = $stmt->fetchAll();
?>

<div class="main-content" id="platform-products-page">
    <div class="row" id="platform-products-header">
        <div class="col-12 d-flex justify-content-between align-items-center mb-4">
            <h4 class="fw-bold mb-0" id="platform-products-title">
                <i class="feather-package me-2"></i>Products & Pricing
            </h4>
            <button class="btn btn-primary"
                    hx-get="/partials/platform/product-form.php"
                    hx-target="#modal-container"
                    id="platform-add-product-btn">
                <i class="feather-plus me-1"></i> Add Product
            </button>
        </div>
    </div>

    <div class="row" id="platform-products-content">
        <div class="col-12">
            <div class="card border-0 shadow-sm" id="platform-products-card">
                <div class="card-body p-0" id="platform-products-card-body">
                    <?php if (empty($products)): ?>
                    <div class="text-center text-muted py-5" id="platform-no-products">
                        <i class="feather-package fs-1 mb-3 d-block"></i>
                        <p>No products yet. Add your first product to get started.</p>
                    </div>
                    <?php else: ?>
                    <div class="table-responsive" id="platform-products-table-wrap">
                        <table class="table table-hover mb-0" id="platform-products-table">
                            <thead class="bg-light" id="platform-products-thead">
                                <tr>
                                    <th>Product</th>
                                    <th>Price</th>
                                    <th>Billing</th>
                                    <th>Features</th>
                                    <th>Active Subs</th>
                                    <th>Status</th>
                                    <th class="text-end">Actions</th>
                                </tr>
                            </thead>
                            <tbody id="platform-products-tbody">
                                <?php foreach ($products as $p): ?>
                                <tr id="platform-product-row-<?php echo (int)$p['id']; ?>">
                                    <td>
                                        <strong><?php echo htmlspecialchars($p['name']); ?></strong>
                                        <?php if ($p['description']): ?>
                                        <br><small class="text-muted"><?php echo htmlspecialchars(mb_strimwidth($p['description'], 0, 60, '...')); ?></small>
                                        <?php endif; ?>
                                    </td>
                                    <td><span class="fw-semibold">$<?php echo number_format($p['price'], 2); ?></span></td>
                                    <td><span class="badge bg-light text-dark"><?php echo ucfirst(str_replace('_', ' ', $p['billing_interval'])); ?></span></td>
                                    <td><span class="badge bg-info"><?php echo (int)$p['feature_count']; ?></span></td>
                                    <td><span class="badge bg-primary"><?php echo (int)$p['active_subs']; ?></span></td>
                                    <td>
                                        <?php if ($p['is_active']): ?>
                                        <span class="badge bg-success">Active</span>
                                        <?php else: ?>
                                        <span class="badge bg-secondary">Inactive</span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="text-end">
                                        <button class="btn btn-sm btn-outline-info me-1"
                                                hx-get="/partials/platform/product-features.php?product_id=<?php echo (int)$p['id']; ?>"
                                                hx-target="#modal-container"
                                                title="Manage Features"
                                                id="platform-features-btn-<?php echo (int)$p['id']; ?>">
                                            <i class="feather-list"></i>
                                        </button>
                                        <button class="btn btn-sm btn-outline-primary me-1"
                                                hx-get="/partials/platform/product-form.php?id=<?php echo (int)$p['id']; ?>"
                                                hx-target="#modal-container"
                                                title="Edit"
                                                id="platform-edit-product-btn-<?php echo (int)$p['id']; ?>">
                                            <i class="feather-edit-2"></i>
                                        </button>
                                        <button class="btn btn-sm btn-outline-<?php echo $p['is_active'] ? 'warning' : 'success'; ?>"
                                                hx-post="/partials/platform/toggle-product.php"
                                                hx-vals='{"product_id": <?php echo (int)$p['id']; ?>, "csrf_token": "<?php echo generate_csrf_token(); ?>"}'
                                                hx-target="#page-content"
                                                hx-confirm="<?php echo $p['is_active'] ? 'Deactivate this product?' : 'Activate this product?'; ?>"
                                                title="<?php echo $p['is_active'] ? 'Deactivate' : 'Activate'; ?>"
                                                id="platform-toggle-product-btn-<?php echo (int)$p['id']; ?>">
                                            <i class="feather-<?php echo $p['is_active'] ? 'pause-circle' : 'play-circle'; ?>"></i>
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
