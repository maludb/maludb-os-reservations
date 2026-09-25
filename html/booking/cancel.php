<?php
/**
 * Public Reservation Cancellation — Standalone page (no auth)
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
    "SELECT r.*, g.first_name, g.last_name
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

// Get cancellation policy
$cancellationPolicy = getRestaurantSetting($restaurantId, 'cancellation_policy', 'Please contact the restaurant for cancellation policy details.');

$cancelled = false;
$message = '';

// Handle POST (confirm cancellation)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'cancel') {
    if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
        $message = '<div class="alert alert-danger">Invalid security token. Please refresh and try again.</div>';
    } elseif (!in_array($res['status'], ['pending', 'confirmed'])) {
        $message = '<div class="alert alert-warning">This reservation cannot be cancelled (status: ' . htmlspecialchars($res['status']) . ').</div>';
    } else {
        $stmt = $pdo->prepare(
            "UPDATE reservations SET status = 'cancelled', cancelled_at = NOW() WHERE id = ? AND restaurant_id = ?"
        );
        $stmt->execute([$res['id'], $restaurantId]);

        // Log
        $stmt = $pdo->prepare(
            "INSERT INTO activity_log (restaurant_id, user_id, action, entity_type, entity_id, description, ip_address)
             VALUES (?, NULL, 'status_change', 'reservation', ?, ?, ?)"
        );
        $desc = "Guest cancelled reservation #{$code} for {$res['first_name']} {$res['last_name']}";
        $stmt->execute([$restaurantId, $res['id'], $desc, $_SERVER['REMOTE_ADDR'] ?? null]);

        // Send cancellation email
        require_once __DIR__ . '/../../helpers/notifications.php';
        sendCancellationEmail($res['id']);

        $cancelled = true;
        $res['status'] = 'cancelled';
    }
}

$canCancel = in_array($res['status'], ['pending', 'confirmed']);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Cancel Reservation — <?php echo htmlspecialchars($restaurant['name']); ?></title>
    <link rel="stylesheet" href="/assets/css/bootstrap.min.css">
    <style>
        body { background-color: #f8f9fa; }
        .booking-header { background: linear-gradient(135deg, #2c3e50, #3498db); color: white; }
    </style>
</head>
<body>
    <div class="booking-header py-4 mb-4" id="cancel-header">
        <div class="container text-center" id="cancel-header-container">
            <h2 class="mb-1" id="cancel-restaurant-name"><?php echo htmlspecialchars($restaurant['name']); ?></h2>
            <p class="mb-0 opacity-75" id="cancel-subtitle">Cancel Reservation</p>
        </div>
    </div>

    <div class="container" id="cancel-container">
        <div class="row justify-content-center" id="cancel-row">
            <div class="col-lg-6 col-md-8" id="cancel-col">

                <?php echo $message; ?>

                <?php if ($cancelled): ?>
                <div class="card border-success mb-3" id="cancel-success-card">
                    <div class="card-body text-center" id="cancel-success-body">
                        <div class="mb-3" id="cancel-success-icon" style="font-size: 3rem; color: #dc3545;">&#10007;</div>
                        <h4 class="text-danger" id="cancel-success-title">Reservation Cancelled</h4>
                        <p class="text-muted" id="cancel-success-msg">
                            Your reservation for <?php echo date('l, F j, Y', strtotime($res['reservation_date'])); ?>
                            at <?php echo date('g:ia', strtotime($res['reservation_time'])); ?> has been cancelled.
                        </p>
                        <a href="/booking/index.php?restaurant=<?php echo urlencode($slug); ?>" class="btn btn-primary" id="cancel-rebook-btn">
                            Make a New Reservation
                        </a>
                    </div>
                </div>
                <?php else: ?>

                <!-- Reservation details -->
                <div class="card mb-3" id="cancel-details-card">
                    <div class="card-body" id="cancel-details-body">
                        <h5 class="card-title" id="cancel-details-title">Reservation Details</h5>
                        <table class="table table-borderless mb-0" id="cancel-details-table">
                            <tr id="cancel-det-code"><td class="text-muted" style="width:140px;">Code</td><td><code><?php echo htmlspecialchars($res['confirmation_code']); ?></code></td></tr>
                            <tr id="cancel-det-date"><td class="text-muted">Date</td><td><?php echo date('l, F j, Y', strtotime($res['reservation_date'])); ?></td></tr>
                            <tr id="cancel-det-time"><td class="text-muted">Time</td><td><?php echo date('g:ia', strtotime($res['reservation_time'])); ?></td></tr>
                            <tr id="cancel-det-party"><td class="text-muted">Party Size</td><td><?php echo (int)$res['party_size']; ?></td></tr>
                            <tr id="cancel-det-guest"><td class="text-muted">Guest</td><td><?php echo htmlspecialchars($res['first_name'] . ' ' . $res['last_name']); ?></td></tr>
                        </table>
                    </div>
                </div>

                <!-- Cancellation policy -->
                <div class="card mb-3" id="cancel-policy-card">
                    <div class="card-body" id="cancel-policy-body">
                        <h6 class="fw-bold" id="cancel-policy-title">Cancellation Policy</h6>
                        <p class="text-muted mb-0" id="cancel-policy-text"><?php echo htmlspecialchars($cancellationPolicy); ?></p>
                    </div>
                </div>

                <?php if ($canCancel): ?>
                <form method="POST" id="cancel-form">
                    <?php echo csrf_field(); ?>
                    <input type="hidden" name="restaurant" value="<?php echo htmlspecialchars($slug); ?>">
                    <input type="hidden" name="code" value="<?php echo htmlspecialchars($code); ?>">
                    <input type="hidden" name="last_name" value="<?php echo htmlspecialchars($lastName); ?>">
                    <input type="hidden" name="action" value="cancel">

                    <button type="submit" class="btn btn-danger btn-lg w-100 mb-3" id="cancel-confirm-btn"
                            onclick="return confirm('Are you sure you want to cancel this reservation?');">
                        Confirm Cancellation
                    </button>
                </form>
                <?php else: ?>
                <div class="alert alert-warning" id="cancel-not-allowed">
                    This reservation cannot be cancelled (status: <?php echo ucfirst(str_replace('_', ' ', $res['status'])); ?>).
                </div>
                <?php endif; ?>

                <?php endif; ?>

                <div class="text-center" id="cancel-back-link">
                    <a href="/booking/lookup.php?restaurant=<?php echo urlencode($slug); ?>" class="btn btn-outline-secondary btn-sm" id="cancel-back-btn">
                        Back to Lookup
                    </a>
                </div>

                <div class="text-center text-muted py-4" id="cancel-footer">
                    <small><?php echo htmlspecialchars($restaurant['name']); ?></small>
                </div>

            </div>
        </div>
    </div>
</body>
</html>
