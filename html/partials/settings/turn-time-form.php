<?php
require_once '../../../helpers/auth.php';
require_once '../../../helpers/csrf.php';

requireAuth();
requireManager();

$restaurantId = currentRestaurantId();
$ttId = isset($_GET['tt_id']) ? (int)$_GET['tt_id'] : 0;
$tt = null;
$isEdit = false;

if ($ttId > 0) {
    $stmt = db()->prepare("SELECT * FROM turn_times WHERE id = ? AND restaurant_id = ?");
    $stmt->execute([$ttId, $restaurantId]);
    $tt = $stmt->fetch();

    if (!$tt) {
        echo '<div class="alert alert-danger">Turn time not found.</div>';
        exit;
    }
    $isEdit = true;
}
?>
<div class="modal fade show d-block" tabindex="-1" id="tt-form-modal" style="background-color: rgba(0,0,0,0.5);">
    <div class="modal-dialog" id="tt-form-dialog">
        <div class="modal-content" id="tt-form-content">
            <div class="modal-header" id="tt-form-header">
                <h5 class="modal-title" id="tt-form-title">
                    <i class="feather-<?php echo $isEdit ? 'edit-2' : 'plus'; ?> me-2"></i>
                    <?php echo $isEdit ? 'Edit Turn Time' : 'Add Turn Time'; ?>
                </h5>
                <button type="button" class="btn-close" id="tt-form-close-btn"
                        onclick="document.getElementById('turn-times-modal-container').innerHTML=''"></button>
            </div>
            <div class="modal-body" id="tt-form-body">

                <div id="tt-form-messages"></div>

                <form id="tt-form"
                      hx-post="/partials/settings/save-turn-time.php"
                      hx-target="#tt-form-messages"
                      hx-swap="innerHTML">
                    <?php echo csrf_field(); ?>
                    <input type="hidden" name="action" value="save">
                    <input type="hidden" name="tt_id" value="<?php echo $ttId; ?>">

                    <div class="row" id="tt-form-size-row">
                        <div class="col-md-6 mb-3" id="tt-form-min-group">
                            <label for="tt-form-min" class="form-label">Min Party Size <span class="text-danger">*</span></label>
                            <input type="number" class="form-control" id="tt-form-min" name="min_party_size"
                                   value="<?php echo (int)($tt['min_party_size'] ?? 1); ?>" min="1" max="50" required>
                        </div>
                        <div class="col-md-6 mb-3" id="tt-form-max-group">
                            <label for="tt-form-max" class="form-label">Max Party Size <span class="text-danger">*</span></label>
                            <input type="number" class="form-control" id="tt-form-max" name="max_party_size"
                                   value="<?php echo (int)($tt['max_party_size'] ?? 2); ?>" min="1" max="50" required>
                        </div>
                    </div>

                    <div class="mb-3" id="tt-form-service-group">
                        <label for="tt-form-service" class="form-label">Service Period <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" id="tt-form-service" name="service_period"
                               value="<?php echo htmlspecialchars($tt['service_period'] ?? 'all'); ?>" required
                               placeholder="all, Lunch, Dinner, Brunch...">
                        <div class="form-text" id="tt-form-service-help">Use "all" to apply to every service period, or enter a specific name.</div>
                    </div>

                    <div class="row" id="tt-form-time-row">
                        <div class="col-md-6 mb-3" id="tt-form-duration-group">
                            <label for="tt-form-duration" class="form-label">Duration (minutes) <span class="text-danger">*</span></label>
                            <input type="number" class="form-control" id="tt-form-duration" name="duration_minutes"
                                   value="<?php echo (int)($tt['duration_minutes'] ?? 90); ?>" min="15" max="480" required>
                        </div>
                        <div class="col-md-6 mb-3" id="tt-form-buffer-group">
                            <label for="tt-form-buffer" class="form-label">Buffer (minutes) <span class="text-danger">*</span></label>
                            <input type="number" class="form-control" id="tt-form-buffer" name="buffer_minutes"
                                   value="<?php echo (int)($tt['buffer_minutes'] ?? 15); ?>" min="0" max="60" required>
                        </div>
                    </div>

                    <div class="d-flex justify-content-end gap-2" id="tt-form-buttons">
                        <button type="button" class="btn btn-secondary" id="tt-form-cancel-btn"
                                onclick="document.getElementById('turn-times-modal-container').innerHTML=''">Cancel</button>
                        <button type="submit" class="btn btn-primary" id="tt-form-save-btn">
                            <i class="feather-save me-1"></i> <?php echo $isEdit ? 'Update' : 'Add'; ?> Turn Time
                            <span class="htmx-indicator spinner-border spinner-border-sm ms-2"></span>
                        </button>
                    </div>
                </form>

            </div>
        </div>
    </div>
</div>
