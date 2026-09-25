<?php
require_once '../../../helpers/auth.php';
require_once '../../../helpers/csrf.php';

requireSuperAdmin();

$id = (int)($_GET['id'] ?? 0);
$product = null;
$isEdit = false;

if ($id > 0) {
    $stmt = db()->prepare("SELECT * FROM products WHERE id = ?");
    $stmt->execute([$id]);
    $product = $stmt->fetch();
    if (!$product) {
        echo '<div class="alert alert-danger" id="platform-product-form-not-found">Product not found.</div>';
        exit;
    }
    $isEdit = true;
}
?>

<div class="modal fade" data-bs-backdrop="static" data-bs-keyboard="false" tabindex="-1" id="platform-product-modal">
    <div class="modal-dialog modal-lg" id="platform-product-modal-dialog">
        <div class="modal-content" id="platform-product-modal-content">
            <div class="modal-header" id="platform-product-modal-header">
                <h5 class="modal-title" id="platform-product-modal-title">
                    <?php echo $isEdit ? 'Edit Product' : 'Add Product'; ?>
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form hx-post="/partials/platform/save-product.php"
                  hx-target="#page-content"
                  id="platform-product-form">
                <div class="modal-body" id="platform-product-modal-body">
                    <?php echo csrf_field(); ?>
                    <?php if ($isEdit): ?>
                    <input type="hidden" name="id" value="<?php echo (int)$product['id']; ?>">
                    <?php endif; ?>

                    <div id="platform-product-form-feedback"></div>

                    <div class="row g-3" id="platform-product-form-fields">
                        <div class="col-md-8" id="platform-product-name-wrap">
                            <label for="platform-product-name" class="form-label fw-semibold">Product Name <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" id="platform-product-name" name="name"
                                   value="<?php echo htmlspecialchars($product['name'] ?? ''); ?>" required>
                        </div>
                        <div class="col-md-4" id="platform-product-slug-wrap">
                            <label for="platform-product-slug" class="form-label fw-semibold">Slug <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" id="platform-product-slug" name="slug"
                                   value="<?php echo htmlspecialchars($product['slug'] ?? ''); ?>"
                                   pattern="[a-z0-9\-]+" title="Lowercase letters, numbers, and hyphens only"
                                   <?php echo $isEdit ? '' : 'oninput="this.value=this.value.toLowerCase().replace(/[^a-z0-9-]/g,\'-\').replace(/--+/g,\'-\')"'; ?>
                                   required>
                        </div>
                        <div class="col-12" id="platform-product-desc-wrap">
                            <label for="platform-product-desc" class="form-label fw-semibold">Description</label>
                            <textarea class="form-control" id="platform-product-desc" name="description" rows="2"><?php echo htmlspecialchars($product['description'] ?? ''); ?></textarea>
                        </div>
                        <div class="col-md-4" id="platform-product-price-wrap">
                            <label for="platform-product-price" class="form-label fw-semibold">Price <span class="text-danger">*</span></label>
                            <div class="input-group">
                                <span class="input-group-text">$</span>
                                <input type="number" class="form-control" id="platform-product-price" name="price"
                                       value="<?php echo htmlspecialchars($product['price'] ?? '0.00'); ?>"
                                       step="0.01" min="0" required>
                            </div>
                        </div>
                        <div class="col-md-4" id="platform-product-billing-wrap">
                            <label for="platform-product-billing" class="form-label fw-semibold">Billing Interval <span class="text-danger">*</span></label>
                            <select class="form-select" id="platform-product-billing" name="billing_interval" required>
                                <option value="monthly" <?php echo ($product['billing_interval'] ?? '') === 'monthly' ? 'selected' : ''; ?>>Monthly</option>
                                <option value="yearly" <?php echo ($product['billing_interval'] ?? '') === 'yearly' ? 'selected' : ''; ?>>Yearly</option>
                                <option value="one_time" <?php echo ($product['billing_interval'] ?? '') === 'one_time' ? 'selected' : ''; ?>>One Time</option>
                            </select>
                        </div>
                        <div class="col-md-6" id="platform-product-sort-wrap">
                            <label for="platform-product-sort" class="form-label fw-semibold">Sort Order</label>
                            <input type="number" class="form-control" id="platform-product-sort" name="sort_order"
                                   value="<?php echo (int)($product['sort_order'] ?? 0); ?>" min="0">
                        </div>
                        <div class="col-md-6 d-flex align-items-end" id="platform-product-active-wrap">
                            <div class="form-check form-switch">
                                <input class="form-check-input" type="checkbox" id="platform-product-active" name="is_active" value="1"
                                       <?php echo ($product['is_active'] ?? 1) ? 'checked' : ''; ?>>
                                <label class="form-check-label fw-semibold" for="platform-product-active">Active</label>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer" id="platform-product-modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal" id="platform-product-cancel-btn">Cancel</button>
                    <button type="submit" class="btn btn-primary" id="platform-product-save-btn">
                        <i class="feather-save me-1"></i> <?php echo $isEdit ? 'Update' : 'Create'; ?> Product
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>
