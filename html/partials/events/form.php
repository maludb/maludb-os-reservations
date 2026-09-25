<?php
/**
 * Event Create/Edit Modal Form
 */
require_once __DIR__ . '/../../../helpers/auth.php';
require_once __DIR__ . '/../../../helpers/db.php';
require_once __DIR__ . '/../../../helpers/csrf.php';

requireAuth();
$restaurantId = currentRestaurantId();

$id = (int)($_GET['id'] ?? 0);
$event = null;

if ($id > 0) {
    $stmt = db()->prepare("SELECT * FROM events WHERE id = ? AND restaurant_id = ?");
    $stmt->execute([$id, $restaurantId]);
    $event = $stmt->fetch();
    if (!$event) {
        echo '<div class="alert alert-danger">Event not found.</div>';
        exit;
    }
}

$isEdit = $event !== null;
$title = $isEdit ? 'Edit Event' : 'Create Event';
?>

<div class="modal show d-block" tabindex="-1" id="event-form-modal" style="background:rgba(0,0,0,0.5);">
    <div class="modal-dialog modal-lg" id="event-form-dialog">
        <div class="modal-content" id="event-form-content">
            <div class="modal-header" id="event-form-header">
                <h5 class="modal-title" id="event-form-title"><?php echo $title; ?></h5>
                <button type="button" class="btn-close" id="event-form-close"
                        onclick="document.getElementById('events-modal-container').innerHTML='';"></button>
            </div>
            <form hx-post="/partials/events/save.php" hx-target="#event-form-messages" hx-swap="innerHTML" id="event-form">
                <div class="modal-body" id="event-form-body">
                    <?php echo csrf_field(); ?>
                    <input type="hidden" name="action" value="save">
                    <?php if ($isEdit): ?>
                    <input type="hidden" name="id" value="<?php echo $event['id']; ?>">
                    <?php endif; ?>

                    <div id="event-form-messages"></div>

                    <div class="row g-3" id="event-form-row1">
                        <div class="col-12" id="event-form-name-group">
                            <label for="event-name" class="form-label">Event Name <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" id="event-name" name="name"
                                   value="<?php echo htmlspecialchars($event['name'] ?? ''); ?>" required
                                   placeholder="e.g., Wine Dinner, Valentine's Prix Fixe, Live Jazz Night">
                        </div>
                    </div>

                    <div class="row g-3 mt-1" id="event-form-row2">
                        <div class="col-md-4" id="event-form-date-group">
                            <label for="event-date" class="form-label">Date <span class="text-danger">*</span></label>
                            <input type="date" class="form-control" id="event-date" name="event_date"
                                   value="<?php echo htmlspecialchars($event['event_date'] ?? ''); ?>" required>
                        </div>
                        <div class="col-md-4" id="event-form-start-group">
                            <label for="event-start" class="form-label">Start Time <span class="text-danger">*</span></label>
                            <input type="time" class="form-control" id="event-start" name="start_time"
                                   value="<?php echo $event ? substr($event['start_time'], 0, 5) : ''; ?>" required>
                        </div>
                        <div class="col-md-4" id="event-form-end-group">
                            <label for="event-end" class="form-label">End Time</label>
                            <input type="time" class="form-control" id="event-end" name="end_time"
                                   value="<?php echo ($event && $event['end_time']) ? substr($event['end_time'], 0, 5) : ''; ?>">
                        </div>
                    </div>

                    <div class="row g-3 mt-1" id="event-form-row3">
                        <div class="col-md-4" id="event-form-capacity-group">
                            <label for="event-capacity" class="form-label">Max Capacity <span class="text-danger">*</span></label>
                            <input type="number" class="form-control" id="event-capacity" name="max_capacity"
                                   value="<?php echo (int)($event['max_capacity'] ?? 50); ?>" min="1" required>
                            <div class="form-text">Total seats/spots available</div>
                        </div>
                        <div class="col-md-4" id="event-form-price-group">
                            <label for="event-price" class="form-label">Price Per Person</label>
                            <div class="input-group">
                                <span class="input-group-text">$</span>
                                <input type="number" class="form-control" id="event-price" name="price_per_person"
                                       value="<?php echo $event['price_per_person'] ?? ''; ?>"
                                       step="0.01" min="0" placeholder="0.00">
                            </div>
                            <div class="form-text">Leave empty for free events</div>
                        </div>
                        <div class="col-md-4" id="event-form-status-group">
                            <label for="event-status" class="form-label">Status</label>
                            <select class="form-select" id="event-status" name="status">
                                <option value="draft" <?php echo ($event['status'] ?? 'draft') === 'draft' ? 'selected' : ''; ?>>Draft</option>
                                <option value="published" <?php echo ($event['status'] ?? '') === 'published' ? 'selected' : ''; ?>>Published</option>
                                <option value="cancelled" <?php echo ($event['status'] ?? '') === 'cancelled' ? 'selected' : ''; ?>>Cancelled</option>
                                <option value="completed" <?php echo ($event['status'] ?? '') === 'completed' ? 'selected' : ''; ?>>Completed</option>
                            </select>
                        </div>
                    </div>

                    <div class="mt-3" id="event-form-desc-group">
                        <label for="event-desc" class="form-label">Description</label>
                        <textarea class="form-control" id="event-desc" name="description" rows="3"
                                  placeholder="Public description of the event..."><?php echo htmlspecialchars($event['description'] ?? ''); ?></textarea>
                    </div>

                    <div class="row g-3 mt-1" id="event-form-row4">
                        <div class="col-md-6" id="event-form-public-group">
                            <div class="form-check mt-2">
                                <input class="form-check-input" type="checkbox" id="event-public" name="is_public" value="1"
                                       <?php echo ($event['is_public'] ?? 1) ? 'checked' : ''; ?>>
                                <label class="form-check-label" for="event-public">Public event (visible on booking page)</label>
                            </div>
                        </div>
                    </div>

                    <div class="mt-3" id="event-form-notes-group">
                        <label for="event-notes" class="form-label">Internal Notes</label>
                        <textarea class="form-control" id="event-notes" name="notes" rows="2"
                                  placeholder="Staff-only notes..."><?php echo htmlspecialchars($event['notes'] ?? ''); ?></textarea>
                    </div>
                </div>
                <div class="modal-footer" id="event-form-footer">
                    <button type="button" class="btn btn-secondary" id="event-form-cancel-btn"
                            onclick="document.getElementById('events-modal-container').innerHTML='';">Cancel</button>
                    <button type="submit" class="btn btn-primary" id="event-form-save-btn">
                        <?php echo $isEdit ? 'Update Event' : 'Create Event'; ?>
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>
