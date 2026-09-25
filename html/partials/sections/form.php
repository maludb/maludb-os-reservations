<?php
require_once '../../../helpers/auth.php';
require_once '../../../helpers/csrf.php';

requireAuth();
requireManager();

$restaurantId = currentRestaurantId();
$sectionId = isset($_GET['section_id']) ? (int)$_GET['section_id'] : 0;
$section = null;
$isEdit = false;

if ($sectionId > 0) {
    $stmt = db()->prepare("SELECT * FROM sections WHERE id = ? AND restaurant_id = ?");
    $stmt->execute([$sectionId, $restaurantId]);
    $section = $stmt->fetch();

    if (!$section) {
        echo '<div class="alert alert-danger">Section not found.</div>';
        exit;
    }
    $isEdit = true;
}
?>
<div class="modal fade show d-block" tabindex="-1" id="section-form-modal" style="background-color: rgba(0,0,0,0.5);">
    <div class="modal-dialog" id="section-form-dialog">
        <div class="modal-content" id="section-form-content">
            <div class="modal-header" id="section-form-header">
                <h5 class="modal-title" id="section-form-title">
                    <i class="feather-<?php echo $isEdit ? 'edit-2' : 'plus'; ?> me-2"></i>
                    <?php echo $isEdit ? 'Edit Section' : 'Add Section'; ?>
                </h5>
                <button type="button" class="btn-close" id="section-form-close-btn"
                        onclick="document.getElementById('sections-modal-container').innerHTML=''"></button>
            </div>
            <div class="modal-body" id="section-form-body">

                <div id="section-form-messages"></div>

                <form id="section-form"
                      hx-post="/partials/sections/save.php"
                      hx-target="#section-form-messages"
                      hx-swap="innerHTML">
                    <?php echo csrf_field(); ?>
                    <input type="hidden" name="section_id" value="<?php echo $sectionId; ?>">

                    <div class="mb-3" id="section-form-name-group">
                        <label for="section-form-name" class="form-label">Name <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" id="section-form-name" name="name"
                               value="<?php echo htmlspecialchars($section['name'] ?? ''); ?>" required>
                    </div>

                    <div class="mb-3" id="section-form-desc-group">
                        <label for="section-form-desc" class="form-label">Description</label>
                        <input type="text" class="form-control" id="section-form-desc" name="description"
                               value="<?php echo htmlspecialchars($section['description'] ?? ''); ?>"
                               maxlength="255">
                    </div>

                    <div class="mb-3" id="section-form-order-group">
                        <label for="section-form-order" class="form-label">Display Order</label>
                        <input type="number" class="form-control" id="section-form-order" name="display_order"
                               value="<?php echo (int)($section['display_order'] ?? 0); ?>" min="0">
                    </div>

                    <div class="row" id="section-form-hours-row">
                        <div class="col-md-6 mb-3" id="section-form-open-group">
                            <label for="section-form-open" class="form-label">Open Time (optional override)</label>
                            <input type="time" class="form-control" id="section-form-open" name="open_time"
                                   value="<?php echo htmlspecialchars($section['open_time'] ?? ''); ?>">
                        </div>
                        <div class="col-md-6 mb-3" id="section-form-close-group">
                            <label for="section-form-close" class="form-label">Close Time (optional override)</label>
                            <input type="time" class="form-control" id="section-form-close" name="close_time"
                                   value="<?php echo htmlspecialchars($section['close_time'] ?? ''); ?>">
                        </div>
                    </div>
                    <div class="form-text mb-3" id="section-form-hours-help">Leave blank to use the restaurant's default operating hours.</div>

                    <div class="mb-3 form-check" id="section-form-active-group">
                        <input type="checkbox" class="form-check-input" id="section-form-active" name="is_active" value="1"
                               <?php echo ($section['is_active'] ?? 1) ? 'checked' : ''; ?>>
                        <label class="form-check-label" for="section-form-active">Active</label>
                    </div>

                    <div class="d-flex justify-content-end gap-2" id="section-form-buttons">
                        <button type="button" class="btn btn-secondary" id="section-form-cancel-btn"
                                onclick="document.getElementById('sections-modal-container').innerHTML=''">Cancel</button>
                        <button type="submit" class="btn btn-primary" id="section-form-save-btn">
                            <i class="feather-save me-1"></i> <?php echo $isEdit ? 'Update' : 'Add'; ?> Section
                            <span class="htmx-indicator spinner-border spinner-border-sm ms-2"></span>
                        </button>
                    </div>
                </form>

            </div>
        </div>
    </div>
</div>
