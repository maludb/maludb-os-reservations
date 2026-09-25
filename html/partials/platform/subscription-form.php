<?php
require_once '../../../helpers/auth.php';
require_once '../../../helpers/csrf.php';

requireSuperAdmin();

$pdo = db();
$id = (int)($_GET['id'] ?? 0);
$sub = null;
$isEdit = false;

if ($id > 0) {
    $stmt = $pdo->prepare("SELECT * FROM subscriptions WHERE id = ?");
    $stmt->execute([$id]);
    $sub = $stmt->fetch();
    if (!$sub) {
        echo '<div class="alert alert-danger">Subscription not found.</div>';
        exit;
    }
    $isEdit = true;
}

$restaurants = $pdo->query("SELECT id, name FROM restaurants ORDER BY name")->fetchAll();
$products = $pdo->query("SELECT id, name, price, billing_interval FROM products WHERE is_active = 1 ORDER BY sort_order, name")->fetchAll();
?>

<div class="modal fade" data-bs-backdrop="static" data-bs-keyboard="false" tabindex="-1" id="platform-subscription-modal">
    <div class="modal-dialog" id="platform-subscription-modal-dialog">
        <div class="modal-content" id="platform-subscription-modal-content">
            <div class="modal-header" id="platform-subscription-modal-header">
                <h5 class="modal-title" id="platform-subscription-modal-title">
                    <?php echo $isEdit ? 'Edit Subscription' : 'Add Subscription'; ?>
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form hx-post="/partials/platform/save-subscription.php"
                  hx-target="#page-content"
                  id="platform-subscription-form">
                <div class="modal-body" id="platform-subscription-modal-body">
                    <?php echo csrf_field(); ?>
                    <?php if ($isEdit): ?>
                    <input type="hidden" name="id" value="<?php echo (int)$sub['id']; ?>">
                    <?php endif; ?>

                    <div id="platform-subscription-form-feedback"></div>

                    <div class="row g-3" id="platform-subscription-form-fields">
                        <div class="col-12" id="platform-sub-restaurant-wrap">
                            <label for="platform-sub-restaurant" class="form-label fw-semibold">Restaurant <span class="text-danger">*</span></label>
                            <select class="form-select" id="platform-sub-restaurant" name="restaurant_id" required <?php echo $isEdit ? 'disabled' : ''; ?>>
                                <option value="">Select restaurant...</option>
                                <?php foreach ($restaurants as $r): ?>
                                <option value="<?php echo (int)$r['id']; ?>" <?php echo ($sub['restaurant_id'] ?? '') == $r['id'] ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($r['name']); ?>
                                </option>
                                <?php endforeach; ?>
                            </select>
                            <?php if ($isEdit): ?>
                            <input type="hidden" name="restaurant_id" value="<?php echo (int)$sub['restaurant_id']; ?>">
                            <?php endif; ?>
                        </div>
                        <div class="col-12" id="platform-sub-product-wrap">
                            <label for="platform-sub-product" class="form-label fw-semibold">Product <span class="text-danger">*</span></label>
                            <select class="form-select" id="platform-sub-product" name="product_id" required>
                                <option value="">Select product...</option>
                                <?php foreach ($products as $p): ?>
                                <option value="<?php echo (int)$p['id']; ?>" <?php echo ($sub['product_id'] ?? '') == $p['id'] ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($p['name']); ?> — $<?php echo number_format($p['price'], 2); ?>/<?php echo $p['billing_interval']; ?>
                                </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-6" id="platform-sub-status-wrap">
                            <label for="platform-sub-status" class="form-label fw-semibold">Status <span class="text-danger">*</span></label>
                            <select class="form-select" id="platform-sub-status" name="status" required>
                                <option value="active" <?php echo ($sub['status'] ?? '') === 'active' ? 'selected' : ''; ?>>Active</option>
                                <option value="trialing" <?php echo ($sub['status'] ?? '') === 'trialing' ? 'selected' : ''; ?>>Trialing</option>
                                <option value="past_due" <?php echo ($sub['status'] ?? '') === 'past_due' ? 'selected' : ''; ?>>Past Due</option>
                                <option value="cancelled" <?php echo ($sub['status'] ?? '') === 'cancelled' ? 'selected' : ''; ?>>Cancelled</option>
                            </select>
                        </div>
                        <div class="col-md-6" id="platform-sub-started-wrap">
                            <label for="platform-sub-started" class="form-label fw-semibold">Start Date <span class="text-danger">*</span></label>
                            <input type="date" class="form-control" id="platform-sub-started" name="started_at"
                                   value="<?php echo htmlspecialchars($sub['started_at'] ?? date('Y-m-d')); ?>" required>
                        </div>
                        <div class="col-md-6" id="platform-sub-expires-wrap">
                            <label for="platform-sub-expires" class="form-label fw-semibold">Expiration Date</label>
                            <input type="date" class="form-control" id="platform-sub-expires" name="expires_at"
                                   value="<?php echo htmlspecialchars($sub['expires_at'] ?? ''); ?>">
                            <small class="text-muted">Leave blank for no expiration</small>
                        </div>
                        <div class="col-12" id="platform-sub-notes-wrap">
                            <label for="platform-sub-notes" class="form-label fw-semibold">Notes</label>
                            <textarea class="form-control" id="platform-sub-notes" name="notes" rows="2"><?php echo htmlspecialchars($sub['notes'] ?? ''); ?></textarea>
                        </div>
                    </div>
                </div>
                <div class="modal-footer" id="platform-subscription-modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal" id="platform-sub-cancel-btn">Cancel</button>
                    <button type="submit" class="btn btn-primary" id="platform-sub-save-btn">
                        <i class="feather-save me-1"></i> <?php echo $isEdit ? 'Update' : 'Create'; ?> Subscription
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>
