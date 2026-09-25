<?php
require_once '../../../helpers/auth.php';
require_once '../../../helpers/csrf.php';

requireSuperAdmin();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    exit;
}

if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
    http_response_code(403);
    echo '<div class="alert alert-danger">Invalid security token.</div>';
    exit;
}

require_once '../../../helpers/services/platform.php';

$productId = (int)($_POST['product_id'] ?? 0);

try {
    svc_platform_feature_add($productId, $_POST, currentUserId());
} catch (ServiceError $e) {
    echo '<div class="alert alert-danger" id="platform-feature-save-error">' . htmlspecialchars($e->getMessage()) . '</div>';
    exit;
}

$pdo = db();

// Re-render the features body
$_GET['product_id'] = $productId;
$features = $pdo->prepare("SELECT * FROM product_features WHERE product_id = ? ORDER BY sort_order, id");
$features->execute([$productId]);
$features = $features->fetchAll();

$product = $pdo->prepare("SELECT * FROM products WHERE id = ?");
$product->execute([$productId]);
$product = $product->fetch();
?>
<div id="platform-features-feedback">
    <div class="alert alert-success alert-dismissible fade show" role="alert">
        Feature added.
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
</div>

<div id="platform-features-list">
    <?php if (empty($features)): ?>
    <p class="text-muted text-center py-3" id="platform-no-features">No features defined yet.</p>
    <?php else: ?>
    <table class="table table-sm" id="platform-features-table">
        <thead class="bg-light" id="platform-features-thead">
            <tr>
                <th>Key</th>
                <th>Label</th>
                <th>Value</th>
                <th>Order</th>
                <th class="text-end">Actions</th>
            </tr>
        </thead>
        <tbody id="platform-features-tbody">
            <?php foreach ($features as $f): ?>
            <tr id="platform-feature-row-<?php echo (int)$f['id']; ?>">
                <td><code><?php echo htmlspecialchars($f['feature_key']); ?></code></td>
                <td><?php echo htmlspecialchars($f['feature_label']); ?></td>
                <td><?php echo htmlspecialchars($f['feature_value'] ?? '-'); ?></td>
                <td><?php echo (int)$f['sort_order']; ?></td>
                <td class="text-end">
                    <button class="btn btn-sm btn-outline-danger"
                            hx-post="/partials/platform/delete-product-feature.php"
                            hx-vals='{"feature_id": <?php echo (int)$f['id']; ?>, "product_id": <?php echo $productId; ?>, "csrf_token": "<?php echo generate_csrf_token(); ?>"}'
                            hx-target="#platform-product-features-body"
                            hx-confirm="Remove this feature?"
                            id="platform-delete-feature-btn-<?php echo (int)$f['id']; ?>">
                        <i class="feather-trash-2"></i>
                    </button>
                </td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
    <?php endif; ?>
</div>

<div class="card border mt-3" id="platform-add-feature-card">
    <div class="card-body" id="platform-add-feature-card-body">
        <h6 class="fw-semibold mb-3" id="platform-add-feature-heading">Add Feature</h6>
        <form hx-post="/partials/platform/save-product-feature.php"
              hx-target="#platform-product-features-body"
              id="platform-add-feature-form">
            <?php echo csrf_field(); ?>
            <input type="hidden" name="product_id" value="<?php echo $productId; ?>">
            <div class="row g-2" id="platform-add-feature-fields">
                <div class="col-md-3" id="platform-feature-key-wrap">
                    <input type="text" class="form-control form-control-sm" name="feature_key"
                           placeholder="feature_key" required id="platform-feature-key-input">
                </div>
                <div class="col-md-4" id="platform-feature-label-wrap">
                    <input type="text" class="form-control form-control-sm" name="feature_label"
                           placeholder="Display Label" required id="platform-feature-label-input">
                </div>
                <div class="col-md-2" id="platform-feature-value-wrap">
                    <input type="text" class="form-control form-control-sm" name="feature_value"
                           placeholder="Value" id="platform-feature-value-input">
                </div>
                <div class="col-md-1" id="platform-feature-sort-wrap">
                    <input type="number" class="form-control form-control-sm" name="sort_order"
                           placeholder="#" value="0" id="platform-feature-sort-input">
                </div>
                <div class="col-md-2" id="platform-feature-submit-wrap">
                    <button type="submit" class="btn btn-sm btn-primary w-100" id="platform-feature-add-btn">
                        <i class="feather-plus"></i> Add
                    </button>
                </div>
            </div>
        </form>
    </div>
</div>
