<?php
/**
 * Waitlist Status Update — seat, left, no_response
 * GET with action=seat_form returns seating modal
 * POST updates status
 */
require_once __DIR__ . '/../../../helpers/auth.php';
require_once __DIR__ . '/../../../helpers/db.php';
require_once __DIR__ . '/../../../helpers/csrf.php';

requireAuth();
$restaurantId = currentRestaurantId();
$pdo = db();

// --- SEAT FORM (GET) ---
if ($_SERVER['REQUEST_METHOD'] === 'GET' && ($_GET['action'] ?? '') === 'seat_form') {
    $id = (int)($_GET['id'] ?? 0);

    $stmt = $pdo->prepare(
        "SELECT w.*, g.first_name, g.last_name, g.email
         FROM waitlist w LEFT JOIN guests g ON w.guest_id = g.id
         WHERE w.id = ? AND w.restaurant_id = ? AND w.status IN ('waiting', 'notified')"
    );
    $stmt->execute([$id, $restaurantId]);
    $entry = $stmt->fetch();

    if (!$entry) {
        echo '<div class="alert alert-warning">Waitlist entry not found or already processed.</div>';
        exit;
    }

    $name = $entry['guest_id'] ? ($entry['first_name'] . ' ' . $entry['last_name']) : ($entry['guest_name'] ?: 'Unknown');

    // Get open tables that fit the party (exclude tables with active seated/confirmed reservations today)
    $stmt = $pdo->prepare(
        "SELECT t.id, t.table_name, t.min_seats, t.max_seats, s.name AS section_name
         FROM tables t
         JOIN sections s ON t.section_id = s.id
         WHERE t.restaurant_id = ? AND t.is_active = 1
           AND t.max_seats >= ?
           AND t.id NOT IN (
               SELECT table_id FROM reservations
               WHERE restaurant_id = ? AND reservation_date = CURRENT_DATE
                 AND status IN ('confirmed', 'seated') AND table_id IS NOT NULL
           )
         ORDER BY t.min_seats ASC"
    );
    $stmt->execute([$restaurantId, $entry['party_size'], $restaurantId]);
    $tables = $stmt->fetchAll();

    echo '<div class="modal show d-block" tabindex="-1" id="waitlist-seat-modal" style="background:rgba(0,0,0,0.5);">
        <div class="modal-dialog" id="waitlist-seat-dialog">
            <div class="modal-content" id="waitlist-seat-content">
                <div class="modal-header" id="waitlist-seat-header">
                    <h5 class="modal-title" id="waitlist-seat-title">Seat Guest</h5>
                    <button type="button" class="btn-close" id="waitlist-seat-close"
                            onclick="document.getElementById(\'waitlist-modal-container\').innerHTML=\'\';"></button>
                </div>
                <form hx-post="/partials/waitlist/update-status.php" hx-target="#waitlist-messages" hx-swap="innerHTML" id="waitlist-seat-form">
                    <div class="modal-body" id="waitlist-seat-body">
                        ' . csrf_field() . '
                        <input type="hidden" name="id" value="' . $id . '">
                        <input type="hidden" name="status" value="seated">

                        <div id="waitlist-seat-messages"></div>

                        <p id="waitlist-seat-info"><strong>' . htmlspecialchars($name) . '</strong> — Party of ' . (int)$entry['party_size'] . '</p>';

    if (empty($tables)) {
        echo '<div class="alert alert-warning" id="waitlist-seat-no-tables">No open tables fit this party size. You can still seat them by selecting any open table below.</div>';
        // Show all open tables regardless of size
        $stmt = $pdo->prepare(
            "SELECT t.id, t.table_name, t.min_seats, t.max_seats, s.name AS section_name
             FROM tables t JOIN sections s ON t.section_id = s.id
             WHERE t.restaurant_id = ? AND t.is_active = 1
               AND t.id NOT IN (
                   SELECT table_id FROM reservations
                   WHERE restaurant_id = ? AND reservation_date = CURRENT_DATE
                     AND status IN ('confirmed', 'seated') AND table_id IS NOT NULL
               )
             ORDER BY t.min_seats ASC"
        );
        $stmt->execute([$restaurantId, $restaurantId]);
        $tables = $stmt->fetchAll();
    }

    if (!empty($tables)) {
        echo '<div class="mb-3" id="waitlist-seat-table-group">
                <label for="waitlist-seat-table" class="form-label">Select Table <span class="text-danger">*</span></label>
                <select class="form-select" id="waitlist-seat-table" name="table_id" required>
                    <option value="">Choose a table...</option>';
        foreach ($tables as $t) {
            echo '<option value="' . $t['id'] . '">'
                . htmlspecialchars($t['table_name']) . ' (' . $t['min_seats'] . '-' . $t['max_seats'] . ' seats) — '
                . htmlspecialchars($t['section_name']) . '</option>';
        }
        echo '</select></div>';
    } else {
        echo '<div class="alert alert-danger" id="waitlist-seat-none">No tables are currently available.</div>';
    }

    echo '      </div>
                <div class="modal-footer" id="waitlist-seat-footer">
                    <button type="button" class="btn btn-secondary" id="waitlist-seat-cancel-btn"
                            onclick="document.getElementById(\'waitlist-modal-container\').innerHTML=\'\';">Cancel</button>
                    <button type="submit" class="btn btn-success" id="waitlist-seat-confirm-btn"' . (empty($tables) ? ' disabled' : '') . '>Seat Guest</button>
                </div>
            </form>
        </div>
    </div>
</div>';
    exit;
}

// --- POST: Update Status ---
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo '<div class="alert alert-danger">Invalid request.</div>';
    exit;
}

if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
    echo '<div class="alert alert-danger">Invalid security token.</div>';
    exit;
}

$newStatus = $_POST['status'] ?? '';

require_once __DIR__ . '/../../../helpers/services/waitlist.php';

try {
    $r = svc_waitlist_set_status($restaurantId, currentUserId(), (int)($_POST['id'] ?? 0), $newStatus, (int)($_POST['table_id'] ?? 0));
} catch (ServiceError $e) {
    $cls = $e->status === 404 ? 'warning' : 'danger';
    echo '<div class="alert alert-' . $cls . '" id="waitlist-status-error">' . htmlspecialchars($e->getMessage()) . '</div>';
    exit;
} catch (Throwable $e) {
    echo '<div class="alert alert-danger" id="waitlist-status-error">An error occurred. Please try again.</div>';
    exit;
}

header('HX-Trigger: refreshWaitlist');

if ($newStatus === 'seated') {
    echo '<div class="alert alert-success" id="waitlist-status-seated">' . htmlspecialchars($r['name']) . ' has been seated.</div>';
    echo '<script>document.getElementById("waitlist-modal-container").innerHTML="";</script>';
} else {
    $label = $newStatus === 'left' ? 'left' : 'no response';
    echo '<div class="alert alert-info" id="waitlist-status-updated">' . htmlspecialchars($r['name']) . ' marked as ' . $label . '.</div>';
}
