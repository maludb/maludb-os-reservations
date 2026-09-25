<?php
/**
 * Complete Google Registration — saves company name and creates account
 */

require_once '../../../helpers/session.php';
require_once '../../../helpers/csrf.php';
require_once '../../../helpers/validation.php';
require_once '../../../helpers/db.php';
require_once '../../../helpers/auth.php';

init_session();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo '<div class="alert alert-warning">Invalid request method.</div>';
    exit;
}

if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
    unset($_SESSION['csrf_token']);
    $newToken = generate_csrf_token();
    echo '<div class="alert alert-danger">Invalid security token. Please try again.</div>';
    echo '<input type="hidden" name="csrf_token" value="' . htmlspecialchars($newToken) . '" hx-swap-oob="outerHTML:[name=csrf_token]">';
    exit;
}

if (empty($_SESSION['google_pending'])) {
    echo '<div class="alert alert-danger">Google session expired. Please try again.</div>';
    exit;
}

$restaurantName = sanitize_input($_POST['restaurant_name'] ?? '');
if ($restaurantName === '') {
    echo '<div class="alert alert-danger">Company name is required.</div>';
    exit;
}

$gp        = $_SESSION['google_pending'];
$googleId  = $gp['google_id'];
$email     = $gp['email'];
$firstName = $gp['first_name'];
$lastName  = $gp['last_name'];

$pdo = db();

// Generate slug
$slug = strtolower(trim(preg_replace('/[^a-zA-Z0-9]+/', '-', $restaurantName), '-'));
$slugBase = $slug;
$slugSuffix = 1;
while (true) {
    $check = $pdo->prepare("SELECT id FROM restaurants WHERE slug = ?");
    $check->execute([$slug]);
    if (!$check->fetch()) break;
    $slug = $slugBase . '-' . $slugSuffix;
    $slugSuffix++;
}

$pdo->beginTransaction();
try {
    // 1. Create user
    $stmt = $pdo->prepare(
        "INSERT INTO users (first_name, last_name, email, password_hash, google_id, auth_provider, company_name, user_type, is_platform_admin, is_active, created_at)
         VALUES (?, ?, ?, '!GOOGLE', ?, 'google', ?, 'system_owner', 0, 1, NOW())"
    );
    $stmt->execute([$firstName, $lastName, $email, $googleId, $restaurantName]);
    $userId = (int)$pdo->lastInsertId();

    // 2. Create restaurant
    $stmt = $pdo->prepare(
        "INSERT INTO restaurants (name, slug, email, timezone, is_active, created_at)
         VALUES (?, ?, ?, 'America/Chicago', 1, NOW())"
    );
    $stmt->execute([$restaurantName, $slug, $email]);
    $restaurantId = (int)$pdo->lastInsertId();

    // 3. Assign user as owner/admin
    $pdo->prepare(
        "INSERT INTO user_restaurants (user_id, restaurant_id, role, is_active) VALUES (?, ?, 'admin', 1)"
    )->execute([$userId, $restaurantId]);

    // 4. Create default settings
    $pdo->prepare(
        "INSERT INTO settings (restaurant_id, setting_key, setting_value) VALUES
            (?, 'time_slot_interval', '30'),
            (?, 'default_turn_time', '90'),
            (?, 'buffer_time', '15'),
            (?, 'max_party_size', '12'),
            (?, 'max_online_party_size', '8'),
            (?, 'advance_booking_min_hours', '2'),
            (?, 'advance_booking_max_days', '30'),
            (?, 'same_day_cutoff_hours', '1'),
            (?, 'max_covers_per_slot', '0'),
            (?, 'online_table_hold_percent', '70'),
            (?, 'confirmation_email_enabled', '1'),
            (?, 'reminder_email_enabled', '1'),
            (?, 'reminder_hours_before', '24'),
            (?, 'cancellation_email_enabled', '1'),
            (?, 'cancellation_policy', 'Please cancel at least 2 hours before your reservation time.')"
    )->execute(array_fill(0, 15, $restaurantId));

    // 5. Create default section
    $pdo->prepare(
        "INSERT INTO sections (restaurant_id, name, description, display_order, is_active) VALUES (?, 'Main Dining', 'Main dining area', 1, 1)"
    )->execute([$restaurantId]);

    // 6. Create default turn times
    $pdo->prepare(
        "INSERT INTO turn_times (restaurant_id, min_party_size, max_party_size, service_period, duration_minutes, buffer_minutes)
         VALUES (?, 1, 4, 'all', 90, 15), (?, 5, 8, 'all', 105, 15), (?, 9, 12, 'all', 120, 15)"
    )->execute([$restaurantId, $restaurantId, $restaurantId]);

    // 8. Create default operating hours
    $hoursStmt = $pdo->prepare(
        "INSERT INTO operating_hours (restaurant_id, day_of_week, service_name, open_time, close_time, first_seating, last_seating, is_active)
         VALUES (?, ?, ?, ?, ?, ?, ?, 1)"
    );
    foreach ([2, 3, 4, 5, 6] as $day) {
        $hoursStmt->execute([$restaurantId, $day, 'Lunch', '11:00:00', '14:30:00', '11:00:00', '14:00:00']);
        $hoursStmt->execute([$restaurantId, $day, 'Dinner', '17:00:00', '22:00:00', '17:00:00', '21:00:00']);
    }
    $hoursStmt->execute([$restaurantId, 0, 'Brunch', '10:00:00', '14:30:00', '10:00:00', '14:00:00']);
    $hoursStmt->execute([$restaurantId, 0, 'Dinner', '17:00:00', '21:00:00', '17:00:00', '20:00:00']);

    $pdo->commit();

    // Clear pending session
    unset($_SESSION['google_pending']);

    // Log in
    $user = $pdo->prepare("SELECT * FROM users WHERE id = ?");
    $user->execute([$userId]);
    $userData = $user->fetch(PDO::FETCH_ASSOC);

    if ($userData) {
        login_user($userData, false);
        header('HX-Redirect: /app.php');
        echo '<div class="alert alert-success">Account created! Redirecting...</div>';
    }

} catch (Exception $e) {
    $pdo->rollBack();
    error_log('Google registration error: ' . $e->getMessage());
    echo '<div class="alert alert-danger">Failed to create account. Please try again later.</div>';
}
