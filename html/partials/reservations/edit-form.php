<?php
/**
 * Edit Reservation Form — loaded inline on detail page
 */
require_once '../../../helpers/auth.php';
require_once '../../../helpers/csrf.php';

requireAuth();

$restaurantId = currentRestaurantId();
$resId = (int)($_GET['id'] ?? 0);

$stmt = db()->prepare("SELECT * FROM reservations WHERE id = ? AND restaurant_id = ?");
$stmt->execute([$resId, $restaurantId]);
$res = $stmt->fetch();

if (!$res) {
    echo '<div class="alert alert-danger">Reservation not found.</div>';
    exit;
}

// Get tables
$stmt = db()->prepare(
    "SELECT t.id, t.table_name, t.min_seats, t.max_seats, s.name as section_name
     FROM tables t JOIN sections s ON t.section_id = s.id
     WHERE t.restaurant_id = ? AND t.is_active = 1 AND s.is_active = 1
     ORDER BY s.display_order ASC, t.sort_order ASC"
);
$stmt->execute([$restaurantId]);
$tables = $stmt->fetchAll();
?>
<div class="card mb-3" id="edit-form-card">
    <div class="card-header d-flex justify-content-between align-items-center" id="edit-form-header">
        <h6 class="mb-0" id="edit-form-title"><i class="feather-edit me-1"></i> Edit Reservation</h6>
        <button type="button" class="btn-close" id="edit-form-close"
                onclick="document.getElementById('detail-edit-container').innerHTML=''"></button>
    </div>
    <div class="card-body" id="edit-form-body">

        <div id="edit-form-messages"></div>

        <form id="edit-form"
              hx-post="/partials/reservations/update.php"
              hx-target="#page-content"
              hx-swap="innerHTML">
            <?php echo csrf_field(); ?>
            <input type="hidden" name="reservation_id" value="<?php echo $resId; ?>">
            <input type="hidden" name="action" value="edit">

            <div class="row" id="edit-form-row1">
                <div class="col-md-4 mb-3" id="edit-form-date-group">
                    <label for="edit-form-date" class="form-label">Date <span class="text-danger">*</span></label>
                    <input type="date" class="form-control" id="edit-form-date" name="reservation_date"
                           value="<?php echo htmlspecialchars($res['reservation_date']); ?>" required>
                </div>
                <div class="col-md-4 mb-3" id="edit-form-time-group">
                    <label for="edit-form-time" class="form-label">Time <span class="text-danger">*</span></label>
                    <input type="time" class="form-control" id="edit-form-time" name="reservation_time"
                           value="<?php echo substr($res['reservation_time'], 0, 5); ?>" required>
                </div>
                <div class="col-md-4 mb-3" id="edit-form-party-group">
                    <label for="edit-form-party" class="form-label">Party Size <span class="text-danger">*</span></label>
                    <input type="number" class="form-control" id="edit-form-party" name="party_size"
                           value="<?php echo (int)$res['party_size']; ?>" min="1" max="50" required>
                </div>
            </div>

            <div class="row" id="edit-form-row2">
                <div class="col-md-6 mb-3" id="edit-form-table-group">
                    <label for="edit-form-table" class="form-label">Table</label>
                    <select class="form-select" id="edit-form-table" name="table_id">
                        <option value="">Unassigned</option>
                        <?php foreach ($tables as $tbl): ?>
                        <option value="<?php echo $tbl['id']; ?>" <?php echo ((int)$res['table_id'] === (int)$tbl['id']) ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($tbl['table_name']); ?> (<?php echo htmlspecialchars($tbl['section_name']); ?>, <?php echo $tbl['min_seats']; ?>-<?php echo $tbl['max_seats']; ?>)
                        </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-6 mb-3" id="edit-form-turn-group">
                    <label for="edit-form-turn" class="form-label">Turn Time Override (min)</label>
                    <input type="number" class="form-control" id="edit-form-turn" name="turn_time_minutes"
                           value="<?php echo $res['turn_time_minutes'] ?? ''; ?>" min="15" max="480"
                           placeholder="Leave blank for default">
                </div>
            </div>

            <div class="mb-3" id="edit-form-requests-group">
                <label for="edit-form-requests" class="form-label">Special Requests</label>
                <textarea class="form-control" id="edit-form-requests" name="special_requests" rows="2"><?php echo htmlspecialchars($res['special_requests'] ?? ''); ?></textarea>
            </div>

            <div class="mb-3" id="edit-form-notes-group">
                <label for="edit-form-notes" class="form-label">Internal Notes</label>
                <textarea class="form-control" id="edit-form-notes" name="internal_notes" rows="2"><?php echo htmlspecialchars($res['internal_notes'] ?? ''); ?></textarea>
            </div>

            <div class="d-flex gap-2" id="edit-form-buttons">
                <button type="submit" class="btn btn-primary btn-sm" id="edit-form-save">
                    <i class="feather-save me-1"></i> Save Changes
                    <span class="htmx-indicator spinner-border spinner-border-sm ms-2"></span>
                </button>
                <button type="button" class="btn btn-secondary btn-sm" id="edit-form-cancel"
                        onclick="document.getElementById('detail-edit-container').innerHTML=''">Cancel</button>
            </div>
        </form>

    </div>
</div>
