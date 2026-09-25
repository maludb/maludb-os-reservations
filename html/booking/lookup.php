<?php
/**
 * Public Reservation Lookup — Standalone page (no auth)
 * URL: /booking/lookup.php?restaurant=slug-name
 */
require_once '../../helpers/db.php';
require_once '../../helpers/restaurant.php';
require_once '../../helpers/csrf.php';

session_start();

$slug = trim($_GET['restaurant'] ?? '');
if ($slug === '') {
    http_response_code(404);
    echo '<!DOCTYPE html><html><head><title>Not Found</title></head><body><h1>404 — Restaurant not specified</h1></body></html>';
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

// Handle HTMX search
$isSearch = isset($_GET['code']);
if ($isSearch) {
    $code = strtoupper(trim($_GET['code'] ?? ''));
    $lastName = trim($_GET['last_name'] ?? '');

    if ($code === '' || $lastName === '') {
        echo '<div class="alert alert-warning" id="lookup-missing">Please enter both confirmation code and last name.</div>';
        exit;
    }

    $stmt = db()->prepare(
        "SELECT r.*, g.first_name, g.last_name, g.phone, g.email,
                t.table_name
         FROM reservations r
         JOIN guests g ON r.guest_id = g.id
         LEFT JOIN tables t ON r.table_id = t.id
         WHERE r.restaurant_id = ? AND r.confirmation_code = ? AND g.last_name = ?"
    );
    $stmt->execute([$restaurantId, $code, $lastName]);
    $res = $stmt->fetch();

    if (!$res) {
        echo '<div class="alert alert-warning" id="lookup-not-found">
                No reservation found with that confirmation code and last name.
              </div>';
        exit;
    }

    $statusColors = [
        'pending' => 'warning', 'confirmed' => 'primary', 'seated' => 'success',
        'completed' => 'secondary', 'no_show' => 'danger', 'cancelled' => 'dark',
    ];
    $badgeColor = $statusColors[$res['status']] ?? 'secondary';
    $canModify = in_array($res['status'], ['pending', 'confirmed']);
    $canCancel = in_array($res['status'], ['pending', 'confirmed']);

    echo '<div class="card" id="lookup-result-card">
            <div class="card-body" id="lookup-result-body">
                <h5 class="card-title" id="lookup-result-title">Reservation Details</h5>
                <table class="table table-borderless" id="lookup-result-table">
                    <tr id="lookup-res-code"><td class="text-muted" style="width:150px;">Confirmation</td><td><code>' . htmlspecialchars($res['confirmation_code']) . '</code></td></tr>
                    <tr id="lookup-res-status"><td class="text-muted">Status</td><td><span class="badge bg-' . $badgeColor . '">' . ucfirst(str_replace('_', ' ', $res['status'])) . '</span></td></tr>
                    <tr id="lookup-res-date"><td class="text-muted">Date</td><td>' . date('l, F j, Y', strtotime($res['reservation_date'])) . '</td></tr>
                    <tr id="lookup-res-time"><td class="text-muted">Time</td><td>' . date('g:ia', strtotime($res['reservation_time'])) . '</td></tr>
                    <tr id="lookup-res-party"><td class="text-muted">Party Size</td><td>' . (int)$res['party_size'] . '</td></tr>
                    <tr id="lookup-res-guest"><td class="text-muted">Guest</td><td>' . htmlspecialchars($res['first_name'] . ' ' . $res['last_name']) . '</td></tr>
                    ' . ($res['special_requests'] ? '<tr id="lookup-res-requests"><td class="text-muted">Special Requests</td><td>' . htmlspecialchars($res['special_requests']) . '</td></tr>' : '') . '
                </table>';

    if ($canModify || $canCancel) {
        echo '<div class="d-flex gap-2" id="lookup-result-actions">';
        if ($canModify) {
            echo '<a href="/booking/modify.php?restaurant=' . urlencode($slug) . '&code=' . urlencode($res['confirmation_code']) . '&last_name=' . urlencode($res['last_name']) . '" class="btn btn-outline-primary" id="lookup-modify-btn">
                    Modify Reservation
                  </a>';
        }
        if ($canCancel) {
            echo '<a href="/booking/cancel.php?restaurant=' . urlencode($slug) . '&code=' . urlencode($res['confirmation_code']) . '&last_name=' . urlencode($res['last_name']) . '" class="btn btn-outline-danger" id="lookup-cancel-btn">
                    Cancel Reservation
                  </a>';
        }
        echo '</div>';
    }

    echo '</div></div>';
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Reservation — <?php echo htmlspecialchars($restaurant['name']); ?></title>
    <link rel="stylesheet" href="/assets/css/bootstrap.min.css">
    <script src="https://unpkg.com/htmx.org@2.0.8"></script>
    <style>
        body { background-color: #f8f9fa; }
        .booking-header { background: linear-gradient(135deg, #2c3e50, #3498db); color: white; }
        .htmx-indicator { display: none; }
        .htmx-request .htmx-indicator { display: inline-block; }
    </style>
</head>
<body>
    <div class="booking-header py-4 mb-4" id="lookup-header">
        <div class="container text-center" id="lookup-header-container">
            <h2 class="mb-1" id="lookup-restaurant-name"><?php echo htmlspecialchars($restaurant['name']); ?></h2>
            <p class="mb-0 opacity-75" id="lookup-subtitle">Manage Your Reservation</p>
        </div>
    </div>

    <div class="container" id="lookup-container">
        <div class="row justify-content-center" id="lookup-row">
            <div class="col-lg-6 col-md-8" id="lookup-col">

                <div class="card mb-3" id="lookup-form-card">
                    <div class="card-body" id="lookup-form-body">
                        <h5 class="card-title" id="lookup-form-title">Look Up Your Reservation</h5>
                        <p class="text-muted" id="lookup-form-desc">Enter your confirmation code and last name to view your reservation.</p>

                        <div class="mb-3" id="lookup-code-group">
                            <label for="lookup-code" class="form-label">Confirmation Code <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" id="lookup-code" name="code"
                                   placeholder="e.g., A1B2C3D4E5" style="text-transform: uppercase;">
                        </div>
                        <div class="mb-3" id="lookup-name-group">
                            <label for="lookup-name" class="form-label">Last Name <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" id="lookup-name" name="last_name">
                        </div>
                        <button type="button" class="btn btn-primary w-100" id="lookup-search-btn"
                                hx-get="/booking/lookup.php?restaurant=<?php echo urlencode($slug); ?>"
                                hx-include="#lookup-code, #lookup-name"
                                hx-target="#lookup-results"
                                hx-swap="innerHTML">
                            Find Reservation
                            <span class="htmx-indicator spinner-border spinner-border-sm ms-1"></span>
                        </button>
                    </div>
                </div>

                <div id="lookup-results"></div>

                <div class="text-center mt-3" id="lookup-book-link">
                    <a href="/booking/index.php?restaurant=<?php echo urlencode($slug); ?>" class="btn btn-outline-secondary btn-sm" id="lookup-new-booking">
                        Make a New Reservation
                    </a>
                </div>

                <div class="text-center text-muted py-4" id="lookup-footer">
                    <small><?php echo htmlspecialchars($restaurant['name']); ?>
                    <?php if ($restaurant['phone']): ?> | <?php echo htmlspecialchars($restaurant['phone']); ?><?php endif; ?></small>
                </div>

            </div>
        </div>
    </div>
</body>
</html>
