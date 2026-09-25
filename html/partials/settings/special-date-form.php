<?php
require_once '../../../helpers/auth.php';
require_once '../../../helpers/csrf.php';

requireAuth();
requireManager();

$restaurantId = currentRestaurantId();
$sdId = isset($_GET['sd_id']) ? (int)$_GET['sd_id'] : 0;
$sd = null;
$isEdit = false;

if ($sdId > 0) {
    $stmt = db()->prepare("SELECT * FROM special_dates WHERE id = ? AND restaurant_id = ?");
    $stmt->execute([$sdId, $restaurantId]);
    $sd = $stmt->fetch();

    if (!$sd) {
        echo '<div class="alert alert-danger">Special date not found.</div>';
        exit;
    }
    $isEdit = true;
}
?>
<div class="modal fade show d-block" tabindex="-1" id="sd-form-modal" style="background-color: rgba(0,0,0,0.5);">
    <div class="modal-dialog modal-lg" id="sd-form-dialog">
        <div class="modal-content" id="sd-form-content">
            <div class="modal-header" id="sd-form-header">
                <h5 class="modal-title" id="sd-form-title">
                    <i class="feather-<?php echo $isEdit ? 'edit-2' : 'plus'; ?> me-2"></i>
                    <?php echo $isEdit ? 'Edit Special Date' : 'Add Special Date'; ?>
                </h5>
                <button type="button" class="btn-close" id="sd-form-close-btn"
                        onclick="document.getElementById('special-dates-modal-container').innerHTML=''"></button>
            </div>
            <div class="modal-body" id="sd-form-body">

                <div id="sd-form-messages"></div>

                <form id="sd-form"
                      hx-post="/partials/settings/save-special-date.php"
                      hx-target="#sd-form-messages"
                      hx-swap="innerHTML">
                    <?php echo csrf_field(); ?>
                    <input type="hidden" name="action" value="save">
                    <input type="hidden" name="sd_id" value="<?php echo $sdId; ?>">

                    <div class="row" id="sd-form-main-row">
                        <div class="col-md-4 mb-3" id="sd-form-date-group">
                            <label for="sd-form-date" class="form-label">Date <span class="text-danger">*</span></label>
                            <input type="date" class="form-control" id="sd-form-date" name="special_date"
                                   value="<?php echo htmlspecialchars($sd['special_date'] ?? ''); ?>" required>
                        </div>
                        <div class="col-md-4 mb-3" id="sd-form-type-group">
                            <label for="sd-form-type" class="form-label">Type <span class="text-danger">*</span></label>
                            <select class="form-select" id="sd-form-type" name="date_type" required>
                                <?php
                                $types = ['holiday', 'blackout', 'event', 'modified_hours'];
                                $currentType = $sd['date_type'] ?? '';
                                foreach ($types as $type):
                                ?>
                                <option value="<?php echo $type; ?>" <?php echo $currentType === $type ? 'selected' : ''; ?>>
                                    <?php echo ucfirst(str_replace('_', ' ', $type)); ?>
                                </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-4 mb-3" id="sd-form-label-group">
                            <label for="sd-form-label" class="form-label">Label <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" id="sd-form-label" name="label"
                                   value="<?php echo htmlspecialchars($sd['label'] ?? ''); ?>" required
                                   placeholder="e.g., Christmas Day, Valentine's Dinner">
                        </div>
                    </div>

                    <div class="mb-3 form-check" id="sd-form-closed-group">
                        <input type="checkbox" class="form-check-input" id="sd-form-closed" name="is_closed" value="1"
                               <?php echo ($sd['is_closed'] ?? 0) ? 'checked' : ''; ?>>
                        <label class="form-check-label" for="sd-form-closed">Closed (no reservations accepted)</label>
                    </div>

                    <hr>
                    <p class="text-muted mb-2" id="sd-form-hours-label">Custom Hours (optional — leave blank for default)</p>

                    <div class="row" id="sd-form-hours-row-1">
                        <div class="col-md-3 mb-3" id="sd-form-open-group">
                            <label for="sd-form-open" class="form-label">Open Time</label>
                            <input type="time" class="form-control" id="sd-form-open" name="custom_open_time"
                                   value="<?php echo htmlspecialchars($sd['custom_open_time'] ?? ''); ?>">
                        </div>
                        <div class="col-md-3 mb-3" id="sd-form-close-group">
                            <label for="sd-form-close" class="form-label">Close Time</label>
                            <input type="time" class="form-control" id="sd-form-close" name="custom_close_time"
                                   value="<?php echo htmlspecialchars($sd['custom_close_time'] ?? ''); ?>">
                        </div>
                        <div class="col-md-3 mb-3" id="sd-form-first-group">
                            <label for="sd-form-first" class="form-label">First Seating</label>
                            <input type="time" class="form-control" id="sd-form-first" name="custom_first_seating"
                                   value="<?php echo htmlspecialchars($sd['custom_first_seating'] ?? ''); ?>">
                        </div>
                        <div class="col-md-3 mb-3" id="sd-form-last-group">
                            <label for="sd-form-last" class="form-label">Last Seating</label>
                            <input type="time" class="form-control" id="sd-form-last" name="custom_last_seating"
                                   value="<?php echo htmlspecialchars($sd['custom_last_seating'] ?? ''); ?>">
                        </div>
                    </div>

                    <div class="row" id="sd-form-extra-row">
                        <div class="col-md-4 mb-3" id="sd-form-covers-group">
                            <label for="sd-form-covers" class="form-label">Max Covers (optional)</label>
                            <input type="number" class="form-control" id="sd-form-covers" name="max_covers"
                                   value="<?php echo $sd['max_covers'] ?? ''; ?>" min="0"
                                   placeholder="Leave blank for default">
                        </div>
                        <div class="col-md-4 mb-3" id="sd-form-voice-agent-group">
                            <label for="sd-form-voice-agent" class="form-label">Outbound Voice Agent</label>
                            <input type="text" class="form-control" id="sd-form-voice-agent" name="outbound_voice_agent"
                                   value="<?php echo htmlspecialchars($sd['outbound_voice_agent'] ?? ''); ?>"
                                   maxlength="80"
                                   placeholder="e.g., agent_xxxxxxxxxxxx">
                            <small class="text-muted">Retell agent ID for outbound calls on this date.</small>
                        </div>
                    </div>

                    <div class="mb-3" id="sd-form-notes-group">
                        <label for="sd-form-notes" class="form-label">Notes</label>
                        <textarea class="form-control" id="sd-form-notes" name="notes" rows="2"><?php echo htmlspecialchars($sd['notes'] ?? ''); ?></textarea>
                    </div>

                    <div class="d-flex justify-content-end gap-2" id="sd-form-buttons">
                        <button type="button" class="btn btn-secondary" id="sd-form-cancel-btn"
                                onclick="document.getElementById('special-dates-modal-container').innerHTML=''">Cancel</button>
                        <button type="submit" class="btn btn-primary" id="sd-form-save-btn">
                            <i class="feather-save me-1"></i> <?php echo $isEdit ? 'Update' : 'Add'; ?> Special Date
                            <span class="htmx-indicator spinner-border spinner-border-sm ms-2"></span>
                        </button>
                    </div>
                </form>

            </div>
        </div>
    </div>
</div>
