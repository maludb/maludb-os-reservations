<?php
require_once '../../../helpers/auth.php';
require_once '../../../helpers/csrf.php';

requireAuth();
requireManager();

$restaurantId = currentRestaurantId();
$hourId = isset($_GET['hour_id']) ? (int)$_GET['hour_id'] : 0;
$hour = null;
$isEdit = false;

if ($hourId > 0) {
    $stmt = db()->prepare("SELECT * FROM operating_hours WHERE id = ? AND restaurant_id = ?");
    $stmt->execute([$hourId, $restaurantId]);
    $hour = $stmt->fetch();

    if (!$hour) {
        echo '<div class="alert alert-danger">Service period not found.</div>';
        exit;
    }
    $isEdit = true;
}

$dayNames = ['Sunday', 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday'];
?>
<div class="modal fade show d-block" tabindex="-1" id="hours-form-modal" style="background-color: rgba(0,0,0,0.5);">
    <div class="modal-dialog" id="hours-form-dialog">
        <div class="modal-content" id="hours-form-content">
            <div class="modal-header" id="hours-form-header">
                <h5 class="modal-title" id="hours-form-title">
                    <i class="feather-<?php echo $isEdit ? 'edit-2' : 'plus'; ?> me-2"></i>
                    <?php echo $isEdit ? 'Edit Service Period' : 'Add Service Period'; ?>
                </h5>
                <button type="button" class="btn-close" id="hours-form-close-btn"
                        onclick="document.getElementById('hours-modal-container').innerHTML=''"></button>
            </div>
            <div class="modal-body" id="hours-form-body">

                <div id="hours-form-messages"></div>

                <form id="hours-form"
                      hx-post="/partials/settings/save-hours.php"
                      hx-target="#hours-form-messages"
                      hx-swap="innerHTML">
                    <?php echo csrf_field(); ?>
                    <input type="hidden" name="action" value="save">
                    <input type="hidden" name="hour_id" value="<?php echo $hourId; ?>">

                    <div class="mb-3" id="hours-form-day-group">
                        <label for="hours-form-day" class="form-label">Day of Week <span class="text-danger">*</span></label>
                        <select class="form-select" id="hours-form-day" name="day_of_week" required>
                            <?php for ($d = 0; $d <= 6; $d++): ?>
                            <option value="<?php echo $d; ?>"
                                <?php echo ((int)($hour['day_of_week'] ?? -1) === $d) ? 'selected' : ''; ?>>
                                <?php echo $dayNames[$d]; ?>
                            </option>
                            <?php endfor; ?>
                        </select>
                    </div>

                    <div class="mb-3" id="hours-form-service-group">
                        <label for="hours-form-service" class="form-label">Service Name <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" id="hours-form-service" name="service_name"
                               value="<?php echo htmlspecialchars($hour['service_name'] ?? ''); ?>" required
                               placeholder="e.g., Lunch, Dinner, Brunch">
                    </div>

                    <div class="row" id="hours-form-times-row-1">
                        <div class="col-md-6 mb-3" id="hours-form-open-group">
                            <label for="hours-form-open" class="form-label">Open Time <span class="text-danger">*</span></label>
                            <input type="time" class="form-control" id="hours-form-open" name="open_time"
                                   value="<?php echo htmlspecialchars($hour['open_time'] ?? ''); ?>" required>
                        </div>
                        <div class="col-md-6 mb-3" id="hours-form-close-group">
                            <label for="hours-form-close" class="form-label">Close Time <span class="text-danger">*</span></label>
                            <input type="time" class="form-control" id="hours-form-close" name="close_time"
                                   value="<?php echo htmlspecialchars($hour['close_time'] ?? ''); ?>" required>
                        </div>
                    </div>

                    <div class="row" id="hours-form-times-row-2">
                        <div class="col-md-6 mb-3" id="hours-form-first-group">
                            <label for="hours-form-first" class="form-label">First Seating <span class="text-danger">*</span></label>
                            <input type="time" class="form-control" id="hours-form-first" name="first_seating"
                                   value="<?php echo htmlspecialchars($hour['first_seating'] ?? ''); ?>" required>
                        </div>
                        <div class="col-md-6 mb-3" id="hours-form-last-group">
                            <label for="hours-form-last" class="form-label">Last Seating <span class="text-danger">*</span></label>
                            <input type="time" class="form-control" id="hours-form-last" name="last_seating"
                                   value="<?php echo htmlspecialchars($hour['last_seating'] ?? ''); ?>" required>
                        </div>
                    </div>

                    <div class="form-text mb-3" id="hours-form-times-help">First seating must be >= open time. Last seating must be <= close time.</div>

                    <div class="mb-3 form-check" id="hours-form-active-group">
                        <input type="checkbox" class="form-check-input" id="hours-form-active" name="is_active" value="1"
                               <?php echo ($hour['is_active'] ?? 1) ? 'checked' : ''; ?>>
                        <label class="form-check-label" for="hours-form-active">Active</label>
                    </div>

                    <div class="d-flex justify-content-end gap-2" id="hours-form-buttons">
                        <button type="button" class="btn btn-secondary" id="hours-form-cancel-btn"
                                onclick="document.getElementById('hours-modal-container').innerHTML=''">Cancel</button>
                        <button type="submit" class="btn btn-primary" id="hours-form-save-btn">
                            <i class="feather-save me-1"></i> <?php echo $isEdit ? 'Update' : 'Add'; ?> Service Period
                            <span class="htmx-indicator spinner-border spinner-border-sm ms-2"></span>
                        </button>
                    </div>
                </form>

            </div>
        </div>
    </div>
</div>
