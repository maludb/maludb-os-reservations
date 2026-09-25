<?php
require_once '../../../helpers/auth.php';
require_once '../../../helpers/csrf.php';

requireAuth();
requireManager();

$restaurantId = currentRestaurantId();
$tableId = isset($_GET['table_id']) ? (int)$_GET['table_id'] : 0;
$table = null;
$isEdit = false;

if ($tableId > 0) {
    $stmt = db()->prepare("SELECT * FROM tables WHERE id = ? AND restaurant_id = ?");
    $stmt->execute([$tableId, $restaurantId]);
    $table = $stmt->fetch();

    if (!$table) {
        echo '<div class="alert alert-danger">Table not found.</div>';
        exit;
    }
    $isEdit = true;
}

// Get active sections for dropdown
$stmt = db()->prepare("SELECT id, name FROM sections WHERE restaurant_id = ? AND is_active = 1 ORDER BY display_order ASC");
$stmt->execute([$restaurantId]);
$sections = $stmt->fetchAll();
?>
<div class="modal fade show d-block" tabindex="-1" id="table-form-modal" style="background-color: rgba(0,0,0,0.5);">
    <div class="modal-dialog" id="table-form-dialog">
        <div class="modal-content" id="table-form-content">
            <div class="modal-header" id="table-form-header">
                <h5 class="modal-title" id="table-form-title">
                    <i class="feather-<?php echo $isEdit ? 'edit-2' : 'plus'; ?> me-2"></i>
                    <?php echo $isEdit ? 'Edit Table' : 'Add Table'; ?>
                </h5>
                <button type="button" class="btn-close" id="table-form-close-btn"
                        onclick="document.getElementById('tables-modal-container').innerHTML=''"></button>
            </div>
            <div class="modal-body" id="table-form-body">

                <div id="table-form-messages"></div>

                <form id="table-form"
                      hx-post="/partials/tables/save.php"
                      hx-target="#table-form-messages"
                      hx-swap="innerHTML">
                    <?php echo csrf_field(); ?>
                    <input type="hidden" name="table_id" value="<?php echo $tableId; ?>">

                    <div class="mb-3" id="table-form-name-group">
                        <label for="table-form-name" class="form-label">Table Name <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" id="table-form-name" name="table_name"
                               value="<?php echo htmlspecialchars($table['table_name'] ?? ''); ?>" required
                               placeholder="e.g., Table 1, Booth A">
                    </div>

                    <div class="mb-3" id="table-form-section-group">
                        <label for="table-form-section" class="form-label">Section <span class="text-danger">*</span></label>
                        <select class="form-select" id="table-form-section" name="section_id" required>
                            <option value="">Select Section...</option>
                            <?php foreach ($sections as $sec): ?>
                            <option value="<?php echo $sec['id']; ?>"
                                <?php echo ((int)($table['section_id'] ?? 0) === (int)$sec['id']) ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($sec['name']); ?>
                            </option>
                            <?php endforeach; ?>
                        </select>
                        <?php if (empty($sections)): ?>
                        <div class="form-text text-danger" id="table-form-no-sections">No active sections found. Please create a section first.</div>
                        <?php endif; ?>
                    </div>

                    <div class="row" id="table-form-seats-row">
                        <div class="col-md-6 mb-3" id="table-form-min-group">
                            <label for="table-form-min" class="form-label">Min Seats <span class="text-danger">*</span></label>
                            <input type="number" class="form-control" id="table-form-min" name="min_seats"
                                   value="<?php echo (int)($table['min_seats'] ?? 1); ?>" min="1" max="50" required>
                        </div>
                        <div class="col-md-6 mb-3" id="table-form-max-group">
                            <label for="table-form-max" class="form-label">Max Seats <span class="text-danger">*</span></label>
                            <input type="number" class="form-control" id="table-form-max" name="max_seats"
                                   value="<?php echo (int)($table['max_seats'] ?? 2); ?>" min="1" max="50" required>
                        </div>
                    </div>

                    <div class="mb-3" id="table-form-status-group">
                        <label for="table-form-status" class="form-label">Status</label>
                        <select class="form-select" id="table-form-status" name="status">
                            <?php
                            $statuses = ['available', 'occupied', 'reserved', 'blocked'];
                            $currentStatus = $table['status'] ?? 'available';
                            foreach ($statuses as $st):
                            ?>
                            <option value="<?php echo $st; ?>" <?php echo $currentStatus === $st ? 'selected' : ''; ?>>
                                <?php echo ucfirst($st); ?>
                            </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="mb-3" id="table-form-order-group">
                        <label for="table-form-order" class="form-label">Sort Order</label>
                        <input type="number" class="form-control" id="table-form-order" name="sort_order"
                               value="<?php echo (int)($table['sort_order'] ?? 0); ?>" min="0">
                    </div>

                    <div class="mb-3" id="table-form-notes-group">
                        <label for="table-form-notes" class="form-label">Notes</label>
                        <input type="text" class="form-control" id="table-form-notes" name="notes"
                               value="<?php echo htmlspecialchars($table['notes'] ?? ''); ?>"
                               maxlength="255" placeholder="e.g., Near window, Wheelchair accessible">
                    </div>

                    <div class="mb-3 form-check" id="table-form-active-group">
                        <input type="checkbox" class="form-check-input" id="table-form-active" name="is_active" value="1"
                               <?php echo ($table['is_active'] ?? 1) ? 'checked' : ''; ?>>
                        <label class="form-check-label" for="table-form-active">Active</label>
                    </div>

                    <div class="d-flex justify-content-end gap-2" id="table-form-buttons">
                        <button type="button" class="btn btn-secondary" id="table-form-cancel-btn"
                                onclick="document.getElementById('tables-modal-container').innerHTML=''">Cancel</button>
                        <button type="submit" class="btn btn-primary" id="table-form-save-btn">
                            <i class="feather-save me-1"></i> <?php echo $isEdit ? 'Update' : 'Add'; ?> Table
                            <span class="htmx-indicator spinner-border spinner-border-sm ms-2"></span>
                        </button>
                    </div>
                </form>

            </div>
        </div>
    </div>
</div>
