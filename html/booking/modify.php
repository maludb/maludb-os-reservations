<?php
/**
 * Public Reservation Modification — Standalone page (no auth)
 */
require_once '../../helpers/db.php';
require_once '../../helpers/restaurant.php';
require_once '../../helpers/csrf.php';
require_once '../../helpers/availability.php';

session_start();

$slug = trim($_GET['restaurant'] ?? $_POST['restaurant'] ?? '');
$code = strtoupper(trim($_GET['code'] ?? $_POST['code'] ?? ''));
$lastName = trim($_GET['last_name'] ?? $_POST['last_name'] ?? '');

if ($slug === '' || $code === '' || $lastName === '') {
    http_response_code(400);
    echo '<!DOCTYPE html><html><head><title>Invalid</title></head><body><h1>Missing required parameters</h1></body></html>';
    exit;
}

$restaurant = getRestaurantBySlug($slug);
if (!$restaurant || !$restaurant['is_active']) {
    http_response_code(404);
    echo '<!DOCTYPE html><html><head><title>Not Found</title></head><body><h1>404 — Restaurant not found</h1></body></html>';
    exit;
}

$restaurantId = (int)$restaurant['id'];
applyRestaurantTimezone($restaurantId);
$pdo = db();

// Find reservation
$stmt = $pdo->prepare(
    "SELECT r.*, g.first_name, g.last_name, g.phone, g.email
     FROM reservations r
     JOIN guests g ON r.guest_id = g.id
     WHERE r.restaurant_id = ? AND r.confirmation_code = ? AND g.last_name = ?"
);
$stmt->execute([$restaurantId, $code, $lastName]);
$res = $stmt->fetch();

if (!$res) {
    http_response_code(404);
    echo '<!DOCTYPE html><html><head><title>Not Found</title></head><body><h1>Reservation not found</h1></body></html>';
    exit;
}

if (!in_array($res['status'], ['pending', 'confirmed'])) {
    $lookupUrl = '/booking/lookup.php?restaurant=' . urlencode($slug);
    echo '<!DOCTYPE html><html><head><title>Cannot Modify</title><link rel="stylesheet" href="/assets/css/bootstrap.min.css"></head><body class="bg-light"><div class="container py-5"><div class="alert alert-warning">This reservation cannot be modified (status: ' . htmlspecialchars($res['status']) . '). <a href="' . $lookupUrl . '">Go back</a></div></div></body></html>';
    exit;
}

$maxParty = (int)getRestaurantSetting($restaurantId, 'max_online_party_size', 8);
$maxDays = (int)getRestaurantSetting($restaurantId, 'advance_booking_max_days', 60);
$today = date('Y-m-d');
$maxDate = date('Y-m-d', strtotime("+{$maxDays} days"));

// Handle POST (save modification)
$message = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'modify') {
    if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
        $message = '<div class="alert alert-danger">Invalid security token. Please refresh and try again.</div>';
    } else {
        $newDate = trim($_POST['new_date'] ?? '');
        $newTime = trim($_POST['new_time'] ?? '');
        $newParty = (int)($_POST['new_party_size'] ?? 0);

        if ($newDate === '' || $newTime === '' || $newParty < 1) {
            $message = '<div class="alert alert-danger">Date, time, and party size are required.</div>';
        } elseif ($newDate < $today) {
            $message = '<div class="alert alert-danger">Cannot book for past dates.</div>';
        } elseif ($newParty > $maxParty) {
            $message = '<div class="alert alert-danger">Online booking is limited to ' . $maxParty . ' guests.</div>';
        } else {
            // Update reservation
            $stmt = $pdo->prepare(
                "UPDATE reservations SET reservation_date = ?, reservation_time = ?, party_size = ?
                 WHERE id = ? AND restaurant_id = ?"
            );
            $stmt->execute([$newDate, $newTime, $newParty, $res['id'], $restaurantId]);

            // Log
            $stmt = $pdo->prepare(
                "INSERT INTO activity_log (restaurant_id, user_id, action, entity_type, entity_id, description, old_value, new_value, ip_address)
                 VALUES (?, NULL, 'update', 'reservation', ?, ?, ?, ?, ?)"
            );
            $desc = "Guest modified reservation #{$code}";
            $stmt->execute([
                $restaurantId, $res['id'], $desc,
                json_encode(['date' => $res['reservation_date'], 'time' => $res['reservation_time'], 'party_size' => $res['party_size']]),
                json_encode(['date' => $newDate, 'time' => $newTime, 'party_size' => $newParty]),
                $_SERVER['REMOTE_ADDR'] ?? null
            ]);

            // Refresh reservation data
            $res['reservation_date'] = $newDate;
            $res['reservation_time'] = $newTime;
            $res['party_size'] = $newParty;

            $message = '<div class="alert alert-success" id="modify-success">
                          Your reservation has been updated successfully!
                          <a href="/booking/lookup.php?restaurant=' . urlencode($slug) . '" class="alert-link">View your reservation</a>
                        </div>';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Modify Reservation — <?php echo htmlspecialchars($restaurant['name']); ?></title>
    <link rel="stylesheet" href="/assets/css/bootstrap.min.css">
    <script src="https://unpkg.com/htmx.org@2.0.8"></script>
    <style>
        body { background-color: #f8f9fa; }
        .booking-header { background: linear-gradient(135deg, #2c3e50, #3498db); color: white; }
        .slot-btn.selected { background-color: #0d6efd !important; color: white !important; border-color: #0d6efd !important; }
        .htmx-indicator { display: none; }
        .htmx-request .htmx-indicator { display: inline-block; }
    </style>
</head>
<body>
    <div class="booking-header py-4 mb-4" id="modify-header">
        <div class="container text-center" id="modify-header-container">
            <h2 class="mb-1" id="modify-restaurant-name"><?php echo htmlspecialchars($restaurant['name']); ?></h2>
            <p class="mb-0 opacity-75" id="modify-subtitle">Modify Your Reservation</p>
        </div>
    </div>

    <div class="container" id="modify-container">
        <div class="row justify-content-center" id="modify-row">
            <div class="col-lg-8 col-md-10" id="modify-col">

                <?php echo $message; ?>

                <div class="card mb-3" id="modify-current-card">
                    <div class="card-body" id="modify-current-body">
                        <h6 class="fw-bold" id="modify-current-title">Current Reservation</h6>
                        <p class="mb-1" id="modify-current-code">Code: <code><?php echo htmlspecialchars($res['confirmation_code']); ?></code></p>
                        <p class="mb-1" id="modify-current-details">
                            <?php echo date('l, F j, Y', strtotime($res['reservation_date'])); ?>
                            at <?php echo date('g:ia', strtotime($res['reservation_time'])); ?>
                            for <?php echo (int)$res['party_size']; ?> guests
                        </p>
                    </div>
                </div>

                <div class="card mb-3" id="modify-form-card">
                    <div class="card-body" id="modify-form-body">
                        <h5 class="card-title" id="modify-form-title">New Date &amp; Time</h5>

                        <!-- Check availability -->
                        <div class="row g-3 mb-3" id="modify-avail-row">
                            <div class="col-md-4" id="modify-date-group">
                                <label for="modify-date" class="form-label">Date</label>
                                <input type="date" class="form-control" id="modify-date" name="date"
                                       value="<?php echo htmlspecialchars($res['reservation_date']); ?>"
                                       min="<?php echo $today; ?>" max="<?php echo $maxDate; ?>">
                            </div>
                            <div class="col-md-3" id="modify-party-group">
                                <label for="modify-party" class="form-label">Guests</label>
                                <select class="form-select" id="modify-party" name="party_size">
                                    <?php for ($i = 1; $i <= $maxParty; $i++): ?>
                                    <option value="<?php echo $i; ?>" <?php echo $i === (int)$res['party_size'] ? 'selected' : ''; ?>>
                                        <?php echo $i; ?> guest<?php echo $i > 1 ? 's' : ''; ?>
                                    </option>
                                    <?php endfor; ?>
                                </select>
                            </div>
                            <div class="col-md-5 d-flex align-items-end" id="modify-check-group">
                                <button type="button" class="btn btn-outline-primary w-100" id="modify-check-btn"
                                        hx-get="/booking/check-slots.php?restaurant=<?php echo urlencode($slug); ?>"
                                        hx-include="#modify-date, #modify-party"
                                        hx-target="#modify-slots"
                                        hx-swap="innerHTML">
                                    Check Availability
                                    <span class="htmx-indicator spinner-border spinner-border-sm ms-1"></span>
                                </button>
                            </div>
                        </div>

                        <div id="modify-slots"></div>

                        <!-- Hidden form for submission -->
                        <form method="POST" id="modify-submit-form">
                            <?php echo csrf_field(); ?>
                            <input type="hidden" name="restaurant" value="<?php echo htmlspecialchars($slug); ?>">
                            <input type="hidden" name="code" value="<?php echo htmlspecialchars($code); ?>">
                            <input type="hidden" name="last_name" value="<?php echo htmlspecialchars($lastName); ?>">
                            <input type="hidden" name="action" value="modify">
                            <input type="hidden" name="new_date" id="modify-form-date" value="">
                            <input type="hidden" name="new_time" id="modify-form-time" value="">
                            <input type="hidden" name="new_party_size" id="modify-form-party" value="">

                            <button type="submit" class="btn btn-primary btn-lg w-100 mt-3" id="modify-submit-btn" disabled>
                                Confirm Changes
                            </button>
                        </form>
                    </div>
                </div>

                <div class="text-center" id="modify-back-link">
                    <a href="/booking/lookup.php?restaurant=<?php echo urlencode($slug); ?>" class="btn btn-outline-secondary btn-sm" id="modify-back-btn">
                        Back to Lookup
                    </a>
                </div>

                <div class="text-center text-muted py-4" id="modify-footer">
                    <small><?php echo htmlspecialchars($restaurant['name']); ?></small>
                </div>
            </div>
        </div>
    </div>

    <script>
    function selectPublicSlot(btn) {
        document.querySelectorAll('.slot-btn').forEach(function(b) { b.classList.remove('selected'); });
        btn.classList.add('selected');

        document.getElementById('modify-form-date').value = document.getElementById('modify-date').value;
        document.getElementById('modify-form-time').value = btn.dataset.time;
        document.getElementById('modify-form-party').value = document.getElementById('modify-party').value;
        document.getElementById('modify-submit-btn').disabled = false;
    }
    </script>
</body>
</html>
