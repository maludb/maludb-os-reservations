<?php
/**
 * Restaurant context helpers for multi-tenant SaaS
 */

require_once __DIR__ . '/db.php';

/**
 * Get the current restaurant_id from session
 */
function getRestaurantId() {
    return $_SESSION['current_restaurant_id'] ?? null;
}

/**
 * Set the current restaurant_id in session
 */
function setRestaurantId($id) {
    $_SESSION['current_restaurant_id'] = (int) $id;
}

/**
 * Get a restaurant record by ID
 */
function getRestaurant($id) {
    $stmt = db()->prepare("SELECT * FROM restaurants WHERE id = ? AND is_active = 1");
    $stmt->execute([$id]);
    return $stmt->fetch();
}

/**
 * Get a restaurant record by slug
 */
function getRestaurantBySlug($slug) {
    $stmt = db()->prepare("SELECT * FROM restaurants WHERE slug = ? AND is_active = 1");
    $stmt->execute([$slug]);
    return $stmt->fetch();
}

/**
 * Get all restaurants a user has access to.
 * Super-admins get ALL active restaurants with admin role.
 */
function getUserRestaurants($userId) {
    $pdo = db();

    // Check if user is a super-admin (by users.role column)
    $roleStmt = $pdo->prepare("SELECT role FROM users WHERE id = ?");
    $roleStmt->execute([$userId]);
    $userRow = $roleStmt->fetch();

    if ($userRow && $userRow['role'] === 'super-admin') {
        // Super-admins see all active restaurants with admin role
        $stmt = $pdo->query(
            "SELECT r.*, 'admin' as role
             FROM restaurants r
             WHERE r.is_active = 1
             ORDER BY r.name"
        );
        return $stmt->fetchAll();
    }

    // Get restaurants the user is directly linked to
    $stmt = $pdo->prepare(
        "SELECT r.*, ur.role
         FROM restaurants r
         JOIN user_restaurants ur ON r.id = ur.restaurant_id
         WHERE ur.user_id = ? AND ur.is_active = 1 AND r.is_active = 1
         ORDER BY r.name"
    );
    $stmt->execute([$userId]);
    return $stmt->fetchAll();
}

/**
 * Get a user's role for a specific restaurant
 */
function getUserRole($userId, $restaurantId) {
    $stmt = db()->prepare(
        "SELECT role FROM user_restaurants
         WHERE user_id = ? AND restaurant_id = ? AND is_active = 1"
    );
    $stmt->execute([$userId, $restaurantId]);
    $row = $stmt->fetch();
    return $row ? $row['role'] : null;
}

/**
 * Set PHP's default timezone to the restaurant's configured timezone.
 * All subsequent date() calls will use this timezone automatically.
 */
function applyRestaurantTimezone($restaurantId): void
{
    $stmt = db()->prepare("SELECT timezone FROM restaurants WHERE id = ?");
    $stmt->execute([$restaurantId]);
    $row = $stmt->fetch();
    $tz = $row['timezone'] ?? 'America/Chicago';
    if ($tz && in_array($tz, timezone_identifiers_list())) {
        date_default_timezone_set($tz);
        db()->exec("SET TIME ZONE " . db()->quote($tz));
    }
}

/**
 * Generate a 6-digit confirmation code.
 * First 3 digits encode the reservation date (day-of-year),
 * last 3 digits are unique within that restaurant+date.
 */
function generateConfirmationCode(int $restaurantId, string $date): string
{
    $dayOfYear = str_pad((int)date('z', strtotime($date)) + 1, 3, '0', STR_PAD_LEFT);
    $pdo = db();
    $maxAttempts = 20;
    for ($i = 0; $i < $maxAttempts; $i++) {
        $suffix = str_pad(random_int(0, 999), 3, '0', STR_PAD_LEFT);
        $code = $dayOfYear . $suffix;
        $stmt = $pdo->prepare(
            "SELECT COUNT(*) FROM reservations WHERE restaurant_id = ? AND confirmation_code = ?"
        );
        $stmt->execute([$restaurantId, $code]);
        if ((int)$stmt->fetchColumn() === 0) {
            return $code;
        }
    }
    // Extremely unlikely fallback
    return $dayOfYear . str_pad(random_int(0, 999), 3, '0', STR_PAD_LEFT);
}

/**
 * Strip a phone number to digits only (removes +, spaces, dashes, parens)
 */
function normalizePhone(string $phone): string
{
    return preg_replace('/\D/', '', $phone);
}

/**
 * Get a restaurant by its phone number.
 * Normalizes both the input and stored phone to digits-only for comparison.
 */
function getRestaurantByPhone(string $phone)
{
    $digits = normalizePhone($phone);
    if ($digits === '') {
        return false;
    }

    // Try exact digits match first, then match last 10 digits (strip country code)
    $pdo = db();
    $stmt = $pdo->prepare("SELECT * FROM restaurants WHERE is_active = 1 AND phone IS NOT NULL AND phone != ''");
    $stmt->execute();
    $restaurants = $stmt->fetchAll();

    $digits10 = strlen($digits) > 10 ? substr($digits, -10) : $digits;

    foreach ($restaurants as $r) {
        $rDigits = normalizePhone($r['phone']);
        $rDigits10 = strlen($rDigits) > 10 ? substr($rDigits, -10) : $rDigits;
        if ($rDigits === $digits || $rDigits10 === $digits10) {
            return $r;
        }
    }
    return false;
}
