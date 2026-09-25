<?php
require_once '../../../helpers/auth.php';
require_once '../../../helpers/csrf.php';

requireSuperAdmin();

$pdo = db();
$id = (int)($_GET['id'] ?? 0);
$option = null;
$isEdit = false;

if ($id > 0) {
    $stmt = $pdo->prepare("SELECT * FROM account_options WHERE id = ?");
    $stmt->execute([$id]);
    $option = $stmt->fetch();
    if (!$option) {
        echo '<div class="alert alert-danger">Option not found.</div>';
        exit;
    }
    $isEdit = true;
}

$restaurants = $pdo->query("SELECT id, name FROM restaurants ORDER BY name")->fetchAll();
?>

<div class="modal fade" data-bs-backdrop="static" data-bs-keyboard="false" tabindex="-1" id="platform-account-option-modal">
    <div class="modal-dialog" id="platform-account-option-modal-dialog">
        <div class="modal-content" id="platform-account-option-modal-content">
            <div class="modal-header" id="platform-account-option-modal-header">
                <h5 class="modal-title" id="platform-account-option-modal-title">
                    <?php echo $isEdit ? 'Edit Account Option' : 'Add Account Option'; ?>
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form hx-post="/partials/platform/save-account-option.php"
                  hx-target="#page-content"
                  id="platform-account-option-form">
                <div class="modal-body" id="platform-account-option-modal-body">
                    <?php echo csrf_field(); ?>
                    <?php if ($isEdit): ?>
                    <input type="hidden" name="id" value="<?php echo (int)$option['id']; ?>">
                    <?php endif; ?>

                    <div class="row g-3" id="platform-option-form-fields">
                        <div class="col-12" id="platform-option-restaurant-wrap">
                            <label for="platform-option-restaurant" class="form-label fw-semibold">Restaurant <span class="text-danger">*</span></label>
                            <select class="form-select" id="platform-option-restaurant" name="restaurant_id" required <?php echo $isEdit ? 'disabled' : ''; ?>>
                                <option value="">Select restaurant...</option>
                                <?php foreach ($restaurants as $r): ?>
                                <option value="<?php echo (int)$r['id']; ?>" <?php echo ($option['restaurant_id'] ?? '') == $r['id'] ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($r['name']); ?>
                                </option>
                                <?php endforeach; ?>
                            </select>
                            <?php if ($isEdit): ?>
                            <input type="hidden" name="restaurant_id" value="<?php echo (int)$option['restaurant_id']; ?>">
                            <?php endif; ?>
                        </div>
                        <div class="col-md-6" id="platform-option-key-wrap">
                            <label for="platform-option-key" class="form-label fw-semibold">Option Key <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" id="platform-option-key" name="option_key"
                                   value="<?php echo htmlspecialchars($option['option_key'] ?? ''); ?>" required
                                   <?php echo $isEdit ? 'readonly' : ''; ?>>
                        </div>
                        <div class="col-md-6" id="platform-option-value-wrap">
                            <label for="platform-option-value" class="form-label fw-semibold">Value</label>
                            <input type="text" class="form-control" id="platform-option-value" name="option_value"
                                   value="<?php echo htmlspecialchars($option['option_value'] ?? '1'); ?>">
                        </div>
                        <div class="col-md-6 d-flex align-items-end" id="platform-option-enabled-wrap">
                            <div class="form-check form-switch">
                                <input class="form-check-input" type="checkbox" id="platform-option-enabled" name="enabled" value="1"
                                       <?php echo ($option['enabled'] ?? 1) ? 'checked' : ''; ?>>
                                <label class="form-check-label fw-semibold" for="platform-option-enabled">Enabled</label>
                            </div>
                        </div>
                        <div class="col-12" id="platform-option-notes-wrap">
                            <label for="platform-option-notes" class="form-label fw-semibold">Notes</label>
                            <textarea class="form-control" id="platform-option-notes" name="notes" rows="2"><?php echo htmlspecialchars($option['notes'] ?? ''); ?></textarea>
                        </div>
                    </div>
                </div>
                <div class="modal-footer" id="platform-account-option-modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary" id="platform-option-save-btn">
                        <i class="feather-save me-1"></i> <?php echo $isEdit ? 'Update' : 'Create'; ?> Option
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>
