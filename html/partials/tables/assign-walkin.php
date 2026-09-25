<?php
/**
 * Quick walk-in seating form — creates a reservation and seats immediately
 */
require_once '../../../helpers/auth.php';
require_once '../../../helpers/csrf.php';

requireAuth();

$restaurantId = currentRestaurantId();
$tableId = (int)($_GET['table_id'] ?? $_POST['table_id'] ?? 0);

// Handle POST (save walk-in)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
        echo '<div class="alert alert-danger">Invalid security token.</div>';
        exit;
    }

    require_once __DIR__ . '/../../../helpers/services/tables.php';

    try {
        svc_table_seat_walkin($restaurantId, currentUserId(), (int)($_POST['table_id'] ?? 0), $_POST);
    } catch (ServiceError $e) {
        echo '<div class="alert alert-danger" id="walkin-save-error">' . htmlspecialchars($e->getMessage()) . '</div>';
        exit;
    } catch (Throwable $e) {
        echo '<div class="alert alert-danger" id="walkin-save-error">An error occurred. Please try again.</div>';
        exit;
    }

    // Reload floor plan (closes modal and shows updated table status)
    header('HX-Retarget: #page-content');
    header('HX-Reswap: innerHTML');
    include __DIR__ . '/floor-plan.php';
    exit;
}

// GET — show the walk-in form
if (!$tableId) {
    echo '<div class="alert alert-danger">No table selected.</div>';
    exit;
}

// Get table info
$stmt = db()->prepare(
    "SELECT t.table_name, t.min_seats, t.max_seats, s.name as section_name
     FROM tables t JOIN sections s ON t.section_id = s.id
     WHERE t.id = ? AND t.restaurant_id = ?"
);
$stmt->execute([$tableId, $restaurantId]);
$table = $stmt->fetch();

if (!$table) {
    echo '<div class="alert alert-danger">Table not found.</div>';
    exit;
}
?>
<div class="modal fade show d-block" tabindex="-1" id="walkin-modal" style="background-color: rgba(0,0,0,0.5);">
    <div class="modal-dialog" id="walkin-dialog">
        <div class="modal-content" id="walkin-content">
            <div class="modal-header" id="walkin-header">
                <h5 class="modal-title" id="walkin-title">
                    <i class="feather-log-in me-2"></i>Seat Walk-In
                </h5>
                <button type="button" class="btn-close" id="walkin-close"
                        onclick="document.getElementById('floorplan-modal-container').innerHTML=''"></button>
            </div>
            <div class="modal-body" id="walkin-body">

                <div class="alert alert-light" id="walkin-table-info">
                    <strong><?php echo htmlspecialchars($table['table_name']); ?></strong>
                    (<?php echo htmlspecialchars($table['section_name']); ?>, <?php echo $table['min_seats']; ?>-<?php echo $table['max_seats']; ?> seats)
                </div>

                <div id="walkin-messages"></div>

                <form id="walkin-form"
                      hx-post="/partials/tables/assign-walkin.php"
                      hx-target="#walkin-messages"
                      hx-swap="innerHTML">
                    <?php echo csrf_field(); ?>
                    <input type="hidden" name="table_id" value="<?php echo $tableId; ?>">

                    <div class="row" id="walkin-name-row">
                        <div class="col-md-6 mb-3" id="walkin-fname-group">
                            <label for="walkin-fname" class="form-label">First Name</label>
                            <input type="text" class="form-control" id="walkin-fname" name="first_name" autofocus
                                   placeholder="Optional">
                        </div>
                        <div class="col-md-6 mb-3" id="walkin-lname-group">
                            <label for="walkin-lname" class="form-label">Last Name</label>
                            <input type="text" class="form-control" id="walkin-lname" name="last_name">
                        </div>
                    </div>

                    <div class="mb-3" id="walkin-party-group">
                        <label for="walkin-party" class="form-label">Party Size <span class="text-danger">*</span></label>
                        <input type="number" class="form-control" id="walkin-party" name="party_size"
                               value="2" min="1" max="<?php echo (int)$table['max_seats']; ?>" required>
                    </div>

                    <div class="d-flex justify-content-end gap-2" id="walkin-buttons">
                        <button type="button" class="btn btn-secondary" id="walkin-cancel"
                                onclick="document.getElementById('floorplan-modal-container').innerHTML=''">Cancel</button>
                        <button type="submit" class="btn btn-success" id="walkin-submit">
                            <i class="feather-log-in me-1"></i> Seat Now
                            <span class="htmx-indicator spinner-border spinner-border-sm ms-2"></span>
                        </button>
                    </div>
                </form>

            </div>
        </div>
    </div>
</div>
