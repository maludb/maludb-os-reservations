<?php
/**
 * Public booking confirmation — POST handler
 * Creates reservation with source='online', status='pending'
 */
require_once '../../helpers/db.php';
require_once '../../helpers/restaurant.php';
require_once '../../helpers/csrf.php';
require_once '../../helpers/availability.php';

session_start();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo '<div class="alert alert-danger">Invalid request.</div>';
    exit;
}

if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
    echo '<div class="alert alert-danger">Invalid security token. Please refresh and try again.</div>';
    exit;
}

// Rate limiting: max 5 bookings per IP per hour
$ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
if (!isset($_SESSION['booking_attempts'])) {
    $_SESSION['booking_attempts'] = [];
}
$oneHourAgo = time() - 3600;
$_SESSION['booking_attempts'] = array_filter($_SESSION['booking_attempts'], function($t) use ($oneHourAgo) {
    return $t > $oneHourAgo;
});
if (count($_SESSION['booking_attempts']) >= 5) {
    echo '<div class="alert alert-danger" id="confirm-rate-limit">Too many booking attempts. Please try again later.</div>';
    exit;
}

$slug = trim($_POST['restaurant'] ?? '');
if ($slug === '') {
    echo '<div class="alert alert-danger">Business not specified.</div>';
    exit;
}

$restaurant = getRestaurantBySlug($slug);
if (!$restaurant || !$restaurant['is_active']) {
    echo '<div class="alert alert-danger">Business not found.</div>';
    exit;
}

$restaurantId = (int)$restaurant['id'];
applyRestaurantTimezone($restaurantId);

// Collect inputs
$date = trim($_POST['date'] ?? '');
$time = trim($_POST['time'] ?? '');
$partySize = (int)($_POST['party_size'] ?? 0);
$firstName = trim($_POST['first_name'] ?? '');
$lastName = trim($_POST['last_name'] ?? '');
$phone = trim($_POST['phone'] ?? '');
$email = trim($_POST['email'] ?? '');
$specialRequests = trim($_POST['special_requests'] ?? '') ?: null;

// Validate
if ($date === '' || $time === '' || $partySize < 1) {
    echo '<div class="alert alert-danger">Date, time, and party size are required.</div>';
    exit;
}

if ($firstName === '' || $lastName === '') {
    echo '<div class="alert alert-danger">First and last name are required.</div>';
    exit;
}

if ($phone === '' || $email === '') {
    echo '<div class="alert alert-danger">Phone and email are required for online bookings.</div>';
    exit;
}

if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    echo '<div class="alert alert-danger">Please enter a valid email address.</div>';
    exit;
}

// Validate party size against settings
$maxParty = (int)getRestaurantSetting($restaurantId, 'max_online_party_size', 8);
if ($partySize > $maxParty) {
    echo '<div class="alert alert-danger">Online booking is limited to ' . $maxParty . ' guests.</div>';
    exit;
}

// Validate date not in past
if ($date < date('Y-m-d')) {
    echo '<div class="alert alert-danger">Cannot book for past dates.</div>';
    exit;
}

$pdo = db();

try {
    $pdo->beginTransaction();

    // Find or create guest within this restaurant
    $guestId = 0;

    $stmt = $pdo->prepare("SELECT id FROM guests WHERE restaurant_id = ? AND email = ? LIMIT 1");
    $stmt->execute([$restaurantId, $email]);
    $existing = $stmt->fetch();

    if ($existing) {
        $guestId = (int)$existing['id'];
        $stmt = $pdo->prepare("UPDATE guests SET first_name = ?, last_name = ?, phone = ? WHERE id = ?");
        $stmt->execute([$firstName, $lastName, $phone, $guestId]);
    } else {
        $stmt = $pdo->prepare(
            "INSERT INTO guests (restaurant_id, first_name, last_name, email, phone, visit_count, noshow_count, is_active)
             VALUES (?, ?, ?, ?, ?, 0, 0, 1)"
        );
        $stmt->execute([$restaurantId, $firstName, $lastName, $email, $phone]);
        $guestId = (int)$pdo->lastInsertId();
    }

    // Generate confirmation code
    $confirmationCode = generateConfirmationCode($restaurantId, $date);

    // Create reservation — online = pending status
    $stmt = $pdo->prepare(
        "INSERT INTO reservations (restaurant_id, guest_id, table_id, reservation_date, reservation_time,
                party_size, status, confirmation_code, source, special_requests, created_by)
         VALUES (?, ?, NULL, ?, ?, ?, 'pending', ?, 'online', ?, NULL)"
    );
    $stmt->execute([$restaurantId, $guestId, $date, $time, $partySize, $confirmationCode, $specialRequests]);
    $resId = (int)$pdo->lastInsertId();

    // Log activity
    $stmt = $pdo->prepare(
        "INSERT INTO activity_log (restaurant_id, user_id, action, entity_type, entity_id, description, ip_address)
         VALUES (?, NULL, 'create', 'reservation', ?, ?, ?)"
    );
    $desc = "Online booking: {$firstName} {$lastName}, party of {$partySize} on {$date} at " . date('g:ia', strtotime($time));
    $stmt->execute([$restaurantId, $resId, $desc, $ip]);

    // Record rate limit
    $_SESSION['booking_attempts'][] = time();

    $pdo->commit();

    // Send confirmation email and SMS
    require_once __DIR__ . '/../../helpers/notifications.php';
    sendConfirmationEmail($resId);
    sendConfirmationSMS($resId);

    // Build lookup URL
    $lookupUrl = '/booking/lookup.php?restaurant=' . urlencode($slug);

    echo '<div class="card border-success" id="confirm-success-card">
            <div class="card-body text-center" id="confirm-success-body">
                <div class="mb-3" id="confirm-success-icon">
                    <span style="font-size: 3rem; color: #198754;">&#10003;</span>
                </div>
                <h4 class="text-success" id="confirm-success-title">Reservation Confirmed!</h4>
                <p class="mb-1" id="confirm-success-code">
                    Your confirmation code: <strong class="fs-4"><code>' . htmlspecialchars($confirmationCode) . '</code></strong>
                </p>
                <p class="text-muted small mb-3" id="confirm-success-note">Please save this code — you will need it to view or modify your reservation.</p>

                <div class="card bg-light d-inline-block text-start p-3 mb-3" id="confirm-success-details">
                    <p class="mb-1"><strong>Business:</strong> ' . htmlspecialchars($restaurant['name']) . '</p>
                    <p class="mb-1"><strong>Date:</strong> ' . date('l, F j, Y', strtotime($date)) . '</p>
                    <p class="mb-1"><strong>Time:</strong> ' . date('g:ia', strtotime($time)) . '</p>
                    <p class="mb-1"><strong>Party Size:</strong> ' . $partySize . '</p>
                    <p class="mb-1"><strong>Guest:</strong> ' . htmlspecialchars($firstName . ' ' . $lastName) . '</p>
                    <p class="mb-0"><strong>Status:</strong> <span class="badge bg-warning text-dark">Pending Confirmation</span></p>
                </div>

                <div id="confirm-success-actions">
                    <a href="' . htmlspecialchars($lookupUrl) . '" class="btn btn-outline-primary btn-sm" id="confirm-lookup-link">
                        View or Modify Reservation
                    </a>
                </div>
            </div>
          </div>';

    // Hide the form steps
    echo '<script>
        document.getElementById("booking-step1-card").style.display = "none";
        document.getElementById("booking-step2-card").style.display = "none";
        document.getElementById("booking-step3-card").style.display = "none";
    </script>';

} catch (Exception $e) {
    $pdo->rollBack();
    echo '<div class="alert alert-danger">An error occurred while creating your reservation. Please try again.</div>';
}
