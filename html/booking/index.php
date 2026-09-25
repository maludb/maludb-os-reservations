<?php
/**
 * Public Booking Widget — Standalone page (no auth required)
 * URL: /booking/index.php?restaurant=slug-name
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

// Load settings
$stmt = db()->prepare("SELECT setting_key, setting_value FROM settings WHERE restaurant_id = ?");
$stmt->execute([$restaurantId]);
$settings = [];
while ($row = $stmt->fetch()) {
    $settings[$row['setting_key']] = $row['setting_value'];
}

$maxParty = (int)($settings['max_online_party_size'] ?? 8);
$maxDays = (int)($settings['advance_booking_max_days'] ?? 60);
$today = date('Y-m-d');
$maxDate = date('Y-m-d', strtotime("+{$maxDays} days"));
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Book a Table — <?php echo htmlspecialchars($restaurant['name']); ?></title>
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
    <div class="booking-header py-4 mb-4" id="booking-header">
        <div class="container text-center" id="booking-header-container">
            <h2 class="mb-1" id="booking-restaurant-name"><?php echo htmlspecialchars($restaurant['name']); ?></h2>
            <p class="mb-0 opacity-75" id="booking-subtitle">Online Reservation</p>
            <?php if ($restaurant['phone']): ?>
            <p class="mb-0 small opacity-75" id="booking-phone">
                For parties larger than <?php echo $maxParty; ?>, please call <?php echo htmlspecialchars($restaurant['phone']); ?>
            </p>
            <?php endif; ?>
        </div>
    </div>

    <div class="container" id="booking-container">
        <div class="row justify-content-center" id="booking-row">
            <div class="col-lg-8 col-md-10" id="booking-col">

                <!-- Step 1: Date & Party Size -->
                <div class="card mb-3" id="booking-step1-card">
                    <div class="card-body" id="booking-step1-body">
                        <h5 class="card-title" id="booking-step1-title">
                            <span class="badge bg-primary me-2">1</span> Select Date &amp; Party Size
                        </h5>
                        <div class="row g-3 mt-2" id="booking-step1-row">
                            <div class="col-md-5" id="booking-date-group">
                                <label for="booking-date" class="form-label">Date</label>
                                <input type="date" class="form-control" id="booking-date" name="date"
                                       value="<?php echo $today; ?>" min="<?php echo $today; ?>" max="<?php echo $maxDate; ?>" required>
                            </div>
                            <div class="col-md-3" id="booking-party-group">
                                <label for="booking-party" class="form-label">Guests</label>
                                <select class="form-select" id="booking-party" name="party_size">
                                    <?php for ($i = 1; $i <= $maxParty; $i++): ?>
                                    <option value="<?php echo $i; ?>" <?php echo $i === 2 ? 'selected' : ''; ?>>
                                        <?php echo $i; ?> <?php echo $i === 1 ? 'guest' : 'guests'; ?>
                                    </option>
                                    <?php endfor; ?>
                                </select>
                            </div>
                            <div class="col-md-4 d-flex align-items-end" id="booking-check-group">
                                <button type="button" class="btn btn-primary w-100" id="booking-check-btn"
                                        hx-get="/booking/check-slots.php?restaurant=<?php echo urlencode($slug); ?>"
                                        hx-include="#booking-date, #booking-party"
                                        hx-target="#booking-slots"
                                        hx-swap="innerHTML">
                                    Check Availability
                                    <span class="htmx-indicator spinner-border spinner-border-sm ms-1"></span>
                                </button>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Step 2: Available Slots -->
                <div class="card mb-3" id="booking-step2-card">
                    <div class="card-body" id="booking-step2-body">
                        <h5 class="card-title" id="booking-step2-title">
                            <span class="badge bg-primary me-2">2</span> Choose a Time
                        </h5>
                        <div id="booking-slots">
                            <p class="text-muted mt-2" id="booking-slots-placeholder">Select a date and party size, then check availability.</p>
                        </div>
                    </div>
                </div>

                <!-- Step 3: Guest Info (hidden until slot selected) -->
                <div class="card mb-3" id="booking-step3-card" style="display: none;">
                    <div class="card-body" id="booking-step3-body">
                        <h5 class="card-title" id="booking-step3-title">
                            <span class="badge bg-primary me-2">3</span> Your Information
                        </h5>

                        <form id="booking-form"
                              hx-post="/booking/confirm.php"
                              hx-target="#booking-confirmation"
                              hx-swap="innerHTML">
                            <?php echo csrf_field(); ?>
                            <input type="hidden" name="restaurant" value="<?php echo htmlspecialchars($slug); ?>">
                            <input type="hidden" name="date" id="booking-form-date" value="">
                            <input type="hidden" name="time" id="booking-form-time" value="">
                            <input type="hidden" name="party_size" id="booking-form-party" value="">

                            <div class="alert alert-light" id="booking-summary">
                                <strong id="booking-summary-date"></strong> at <strong id="booking-summary-time"></strong>
                                for <strong id="booking-summary-party"></strong> guests
                            </div>

                            <div class="row g-3" id="booking-guest-row1">
                                <div class="col-md-6 mb-3" id="booking-fname-group">
                                    <label for="booking-fname" class="form-label">First Name <span class="text-danger">*</span></label>
                                    <input type="text" class="form-control" id="booking-fname" name="first_name" required>
                                </div>
                                <div class="col-md-6 mb-3" id="booking-lname-group">
                                    <label for="booking-lname" class="form-label">Last Name <span class="text-danger">*</span></label>
                                    <input type="text" class="form-control" id="booking-lname" name="last_name" required>
                                </div>
                            </div>
                            <div class="row g-3" id="booking-guest-row2">
                                <div class="col-md-6 mb-3" id="booking-guest-phone-group">
                                    <label for="booking-guest-phone" class="form-label">Phone <span class="text-danger">*</span></label>
                                    <input type="tel" class="form-control" id="booking-guest-phone" name="phone" required>
                                </div>
                                <div class="col-md-6 mb-3" id="booking-guest-email-group">
                                    <label for="booking-guest-email" class="form-label">Email <span class="text-danger">*</span></label>
                                    <input type="email" class="form-control" id="booking-guest-email" name="email" required>
                                </div>
                            </div>
                            <div class="mb-3" id="booking-requests-group">
                                <label for="booking-requests" class="form-label">Special Requests</label>
                                <textarea class="form-control" id="booking-requests" name="special_requests" rows="3"
                                          placeholder="Allergies, celebrations, seating preferences..."></textarea>
                            </div>

                            <button type="submit" class="btn btn-primary btn-lg w-100" id="booking-submit-btn">
                                <i class="feather-check me-1"></i> Confirm Reservation
                                <span class="htmx-indicator spinner-border spinner-border-sm ms-1"></span>
                            </button>
                        </form>
                    </div>
                </div>

                <!-- Confirmation result -->
                <div id="booking-confirmation"></div>

                <!-- Footer -->
                <div class="text-center text-muted py-4" id="booking-footer">
                    <small>
                        <?php echo htmlspecialchars($restaurant['name']); ?>
                        <?php if ($restaurant['phone']): ?> | <?php echo htmlspecialchars($restaurant['phone']); ?><?php endif; ?>
                        <?php if ($restaurant['website']): ?> | <a href="<?php echo htmlspecialchars($restaurant['website']); ?>" target="_blank"><?php echo htmlspecialchars($restaurant['website']); ?></a><?php endif; ?>
                    </small>
                </div>

            </div>
        </div>
    </div>

    <script>
    function selectPublicSlot(btn) {
        // Remove selected state from all
        document.querySelectorAll('.slot-btn').forEach(function(b) {
            b.classList.remove('selected');
        });
        btn.classList.add('selected');

        var time = btn.dataset.time;
        var display = btn.dataset.display;
        var date = document.getElementById('booking-date').value;
        var party = document.getElementById('booking-party').value;

        // Populate hidden fields
        document.getElementById('booking-form-date').value = date;
        document.getElementById('booking-form-time').value = time;
        document.getElementById('booking-form-party').value = party;

        // Update summary
        var dateObj = new Date(date + 'T12:00:00');
        var dateStr = dateObj.toLocaleDateString('en-US', {weekday: 'long', month: 'long', day: 'numeric', year: 'numeric'});
        document.getElementById('booking-summary-date').textContent = dateStr;
        document.getElementById('booking-summary-time').textContent = display;
        document.getElementById('booking-summary-party').textContent = party;

        // Show step 3
        document.getElementById('booking-step3-card').style.display = 'block';
        document.getElementById('booking-step3-card').scrollIntoView({behavior: 'smooth', block: 'start'});
    }
    </script>
</body>
</html>
