<?php
/**
 * Seat a reservation from the waitlist screen
 * GET: Show table selection modal
 * POST: Assign table and mark as seated
 */
require_once __DIR__ . '/../../../helpers/auth.php';
require_once __DIR__ . '/../../../helpers/db.php';
require_once __DIR__ . '/../../../helpers/csrf.php';

requireAuth();
$restaurantId = currentRestaurantId();
$pdo = db();

// --- SEAT FORM (GET) ---
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $resId = (int)($_GET['reservation_id'] ?? 0);

    $stmt = $pdo->prepare(
        "SELECT r.*, g.first_name, g.last_name
         FROM reservations r
         JOIN guests g ON r.guest_id = g.id
         WHERE r.id = ? AND r.restaurant_id = ? AND r.status IN ('pending', 'confirmed')"
    );
    $stmt->execute([$resId, $restaurantId]);
    $res = $stmt->fetch();

    if (!$res) {
        echo '<div class="alert alert-warning">Reservation not found or already seated.</div>';
        exit;
    }

    $name = $res['first_name'] . ' ' . $res['last_name'];

    // Get open tables that fit the party
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
    $stmt->execute([$restaurantId, $res['party_size'], $restaurantId]);
    $tables = $stmt->fetchAll();

    echo '<div class="modal show d-block" tabindex="-1" id="waitlist-seat-res-modal" style="background:rgba(0,0,0,0.5);">
        <div class="modal-dialog" id="waitlist-seat-res-dialog">
            <div class="modal-content" id="waitlist-seat-res-content">
                <div class="modal-header" id="waitlist-seat-res-header">
                    <h5 class="modal-title" id="waitlist-seat-res-title">Seat Reservation</h5>
                    <button type="button" class="btn-close" id="waitlist-seat-res-close"
                            onclick="document.getElementById(\'waitlist-modal-container\').innerHTML=\'\';"></button>
                </div>
                <form hx-post="/partials/waitlist/seat-reservation.php" hx-target="#waitlist-messages" hx-swap="innerHTML" id="waitlist-seat-res-form">
                    <div class="modal-body" id="waitlist-seat-res-body">
                        ' . csrf_field() . '
                        <input type="hidden" name="reservation_id" value="' . $resId . '">

                        <p id="waitlist-seat-res-info"><strong>' . htmlspecialchars($name) . '</strong> — Party of ' . (int)$res['party_size'] . '
                            <br><small class="text-muted">Reservation at ' . date('g:ia', strtotime($res['reservation_time'])) . '</small></p>';

    if (empty($tables)) {
        echo '<div class="alert alert-warning" id="waitlist-seat-res-no-fit">No open tables fit this party size.</div>';
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
        echo '<div class="mb-3" id="waitlist-seat-res-table-group">
                <label for="waitlist-seat-res-table" class="form-label">Select Table <span class="text-danger">*</span></label>
                <select class="form-select" id="waitlist-seat-res-table" name="table_id" required>
                    <option value="">Choose a table...</option>';
        foreach ($tables as $t) {
            echo '<option value="' . $t['id'] . '">'
                . htmlspecialchars($t['table_name']) . ' (' . $t['min_seats'] . '-' . $t['max_seats'] . ' seats) — '
                . htmlspecialchars($t['section_name']) . '</option>';
        }
        echo '</select></div>';
    } else {
        echo '<div class="alert alert-danger" id="waitlist-seat-res-none">No tables are currently available.</div>';
    }

    echo '      </div>
                <div class="modal-footer" id="waitlist-seat-res-footer">
                    <button type="button" class="btn btn-secondary" id="waitlist-seat-res-cancel-btn"
                            onclick="document.getElementById(\'waitlist-modal-container\').innerHTML=\'\';">Cancel</button>
                    <button type="submit" class="btn btn-success" id="waitlist-seat-res-confirm-btn"' . (empty($tables) ? ' disabled' : '') . '>Seat Guest</button>
                </div>
            </form>
        </div>
    </div>
</div>';
    exit;
}

// --- POST: Seat the reservation ---
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo '<div class="alert alert-danger">Invalid request.</div>';
    exit;
}

if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
    echo '<div class="alert alert-danger">Invalid security token.</div>';
    exit;
}

require_once __DIR__ . '/../../../helpers/services/waitlist.php';

try {
    $r = svc_waitlist_seat_reservation($restaurantId, currentUserId(), (int)($_POST['reservation_id'] ?? 0), (int)($_POST['table_id'] ?? 0));
} catch (ServiceError $e) {
    $cls = $e->status === 404 ? 'warning' : 'danger';
    echo '<div class="alert alert-' . $cls . '" id="seat-res-error">' . htmlspecialchars($e->getMessage()) . '</div>';
    exit;
} catch (Throwable $e) {
    echo '<div class="alert alert-danger" id="seat-res-error">An error occurred. Please try again.</div>';
    exit;
}

header('HX-Trigger: refreshWaitlist');
echo '<div class="alert alert-success alert-dismissible fade show" id="seat-res-success">
        <i class="feather-check-circle me-1"></i> ' . htmlspecialchars($r['name']) . ' seated at ' . htmlspecialchars($r['table_name']) . '.
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
      </div>';
echo '<script>document.getElementById("waitlist-modal-container").innerHTML="";</script>';
