<?php
/**
 * Platform Admin — Billing Settings
 *
 * Configure billing for a specific restaurant: invoice day, description, price.
 * Accessed from the restaurant detail page or platform billing nav.
 */
require_once __DIR__ . '/../../../helpers/auth.php';
require_once __DIR__ . '/../../../helpers/db.php';
require_once __DIR__ . '/../../../helpers/csrf.php';

requireSuperAdmin();
$pdo = db();

$restaurantId = (int)($_GET['restaurant_id'] ?? 0);

// If no restaurant specified, show list of all restaurants with billing config
if ($restaurantId <= 0) {
    $restaurants = $pdo->query(
        "SELECT r.id, r.name, r.invoice_day_of_month, r.invoice_description,
                r.monthly_price, r.prepay_balance, r.is_active,
                u.first_name AS billing_first, u.last_name AS billing_last
         FROM restaurants r
         LEFT JOIN users u ON r.billing_user = u.id
         ORDER BY r.name"
    )->fetchAll();
    ?>
    <div class="p-4" id="platform-billing-list-main">
        <div class="d-flex justify-content-between align-items-center mb-4" id="platform-billing-list-header">
            <div id="platform-billing-list-title-wrap">
                <h4 class="mb-0" id="platform-billing-list-title">Billing Configuration</h4>
                <small class="text-muted" id="platform-billing-list-subtitle">Manage billing settings for all locations</small>
            </div>
        </div>

        <div class="card" id="platform-billing-list-card">
            <div class="card-body p-0" id="platform-billing-list-body">
                <div class="table-responsive" id="platform-billing-list-table-wrap">
                    <table class="table table-hover mb-0" id="platform-billing-list-table">
                        <thead class="table-light" id="platform-billing-list-thead">
                            <tr>
                                <th id="platform-billing-th-name">Location</th>
                                <th id="platform-billing-th-price">Monthly Price</th>
                                <th id="platform-billing-th-day">Invoice Day</th>
                                <th id="platform-billing-th-balance">Prepay Balance</th>
                                <th id="platform-billing-th-billing-user">Billing User</th>
                                <th id="platform-billing-th-status">Status</th>
                                <th id="platform-billing-th-actions" class="text-end">Actions</th>
                            </tr>
                        </thead>
                        <tbody id="platform-billing-list-tbody">
                            <?php foreach ($restaurants as $r): ?>
                            <tr id="platform-billing-row-<?php echo (int)$r['id']; ?>">
                                <td id="platform-billing-name-<?php echo (int)$r['id']; ?>" class="fw-semibold"><?php echo htmlspecialchars($r['name']); ?></td>
                                <td id="platform-billing-price-<?php echo (int)$r['id']; ?>">$<?php echo number_format((float)$r['monthly_price'], 2); ?></td>
                                <td id="platform-billing-day-<?php echo (int)$r['id']; ?>"><?php echo (int)$r['invoice_day_of_month']; ?></td>
                                <td id="platform-billing-balance-<?php echo (int)$r['id']; ?>">$<?php echo number_format((float)$r['prepay_balance'], 2); ?></td>
                                <td id="platform-billing-user-<?php echo (int)$r['id']; ?>">
                                    <?php echo $r['billing_first'] ? htmlspecialchars($r['billing_first'] . ' ' . $r['billing_last']) : '<span class="text-muted">Not set</span>'; ?>
                                </td>
                                <td id="platform-billing-status-<?php echo (int)$r['id']; ?>">
                                    <span class="badge bg-<?php echo $r['is_active'] ? 'success' : 'secondary'; ?>">
                                        <?php echo $r['is_active'] ? 'Active' : 'Inactive'; ?>
                                    </span>
                                </td>
                                <td class="text-end" id="platform-billing-actions-<?php echo (int)$r['id']; ?>">
                                    <a href="#" class="btn btn-sm btn-outline-primary"
                                       hx-get="/partials/platform/billing-settings.php?restaurant_id=<?php echo (int)$r['id']; ?>"
                                       hx-target="#page-content"
                                       id="platform-billing-edit-<?php echo (int)$r['id']; ?>">
                                        <i class="feather-edit-2"></i> Edit
                                    </a>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
    <?php
    exit;
}

// Edit billing settings for a specific restaurant
$stmt = $pdo->prepare(
    "SELECT id, name, invoice_day_of_month, invoice_description, monthly_price
     FROM restaurants WHERE id = ?"
);
$stmt->execute([$restaurantId]);
$restaurant = $stmt->fetch();

if (!$restaurant) {
    echo '<div class="alert alert-danger m-4">Restaurant not found.</div>';
    exit;
}
?>

<div class="p-4" id="platform-billing-edit-main">
    <div class="d-flex justify-content-between align-items-center mb-4" id="platform-billing-edit-header">
        <div id="platform-billing-edit-title-wrap">
            <h4 class="mb-0" id="platform-billing-edit-title">Billing Settings</h4>
            <small class="text-muted" id="platform-billing-edit-subtitle"><?php echo htmlspecialchars($restaurant['name']); ?></small>
        </div>
        <a href="#" class="btn btn-outline-secondary" id="platform-billing-edit-back-btn"
           hx-get="/partials/platform/billing-settings.php" hx-target="#page-content">
            <i class="feather-arrow-left me-1"></i> Back to List
        </a>
    </div>

    <div class="row justify-content-center" id="platform-billing-edit-form-row">
        <div class="col-lg-6" id="platform-billing-edit-form-col">
            <div class="card" id="platform-billing-edit-form-card">
                <div class="card-body" id="platform-billing-edit-form-body">
                    <form hx-post="/partials/platform/save-billing-settings.php"
                          hx-target="#page-content"
                          id="platform-billing-edit-form">
                        <?php echo csrf_field(); ?>
                        <input type="hidden" name="restaurant_id" value="<?php echo $restaurantId; ?>">

                        <div class="mb-3" id="platform-billing-day-wrap">
                            <label for="platform-billing-day-input" class="form-label fw-semibold">
                                Invoice Day of Month <span class="text-danger">*</span>
                            </label>
                            <input type="number" class="form-control" id="platform-billing-day-input"
                                   name="invoice_day_of_month" min="1" max="28" required
                                   value="<?php echo (int)$restaurant['invoice_day_of_month']; ?>">
                            <small class="text-muted" id="platform-billing-day-help">Day 1-28 when monthly invoice is generated</small>
                        </div>

                        <div class="mb-3" id="platform-billing-desc-wrap">
                            <label for="platform-billing-desc-input" class="form-label fw-semibold">
                                Invoice Line Description <span class="text-danger">*</span>
                            </label>
                            <input type="text" class="form-control" id="platform-billing-desc-input"
                                   name="invoice_description" maxlength="255" required
                                   value="<?php echo htmlspecialchars($restaurant['invoice_description']); ?>">
                        </div>

                        <div class="mb-3" id="platform-billing-price-wrap">
                            <label for="platform-billing-price-input" class="form-label fw-semibold">
                                Monthly Price <span class="text-danger">*</span>
                            </label>
                            <div class="input-group" id="platform-billing-price-group">
                                <span class="input-group-text" id="platform-billing-price-prefix">$</span>
                                <input type="number" class="form-control" id="platform-billing-price-input"
                                       name="monthly_price" min="0" step="0.01" required
                                       value="<?php echo number_format((float)$restaurant['monthly_price'], 2, '.', ''); ?>">
                            </div>
                        </div>

                        <div class="d-flex gap-2" id="platform-billing-edit-buttons">
                            <button type="submit" class="btn btn-primary" id="platform-billing-edit-submit-btn">
                                <i class="feather-save me-1"></i> Save Settings
                            </button>
                            <a href="#" class="btn btn-outline-secondary" id="platform-billing-edit-cancel-btn"
                               hx-get="/partials/platform/billing-settings.php" hx-target="#page-content">
                                Cancel
                            </a>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>
