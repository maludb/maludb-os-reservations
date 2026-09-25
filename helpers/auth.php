<?php
/**
 * Authentication Helper
 *
 * Provides authentication and authorization functions
 * for the multi-tenant restaurant reservation system
 *
 * Platform roles (users.role column):
 *   super-admin — Platform vendor administrator (access to all restaurants and users)
 *   user        — Normal user (access determined by user_restaurants table)
 *
 * Restaurant roles (user_restaurants.role column):
 *   admin       — Restaurant administrator/purchaser (all setup, invite users)
 *   manager     — Restaurant manager (all setup access)
 *   user        — Restaurant staff (view/make/change/seat reservations, clear tables)
 */

require_once __DIR__ . '/session.php';
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/restaurant.php';

/**
 * Check if user is authenticated
 * Redirects to login page if not authenticated
 */
function check_auth() {
    requireAuth();
    return true;
}

/**
 * Get current user ID from session
 */
function get_user_id() {
    return currentUserId();
}

/**
 * Get current user data from session
 */
function get_user() {
    session_start_once();
    return $_SESSION['user'] ?? null;
}

/**
 * Login user: authenticate, load restaurant memberships, set session
 */
function login_user($user, $remember = false) {
    session_start_once();
    regenerate_session();

    // Load user's restaurant memberships
    $restaurants = getUserRestaurants($user['id']);
    $firstRestaurant = $restaurants[0] ?? null;

    $platformRole = $user['role'] ?? 'user';

    $sessionUser = [
        'id' => $user['id'],
        'email' => $user['email'],
        'first_name' => $user['first_name'],
        'last_name' => $user['last_name'],
        'role' => $platformRole,
        // Keep for backward compat during transition
        'is_platform_admin' => ($platformRole === 'super-admin') ? 1 : 0,
    ];

    $_SESSION['user'] = $sessionUser;
    $_SESSION['user_id'] = $sessionUser['id'];
    $_SESSION['logged_in_at'] = time();

    // Set current restaurant context
    // Super-admins get all restaurants; pick the first if they have one assigned
    if ($platformRole === 'super-admin' && !$firstRestaurant) {
        // Super-admin with no direct restaurant assignment — load first restaurant in system
        $anyRestaurant = db()->query("SELECT id, name FROM restaurants WHERE is_active = 1 ORDER BY name LIMIT 1")->fetch();
        if ($anyRestaurant) {
            $_SESSION['current_restaurant_id'] = (int)$anyRestaurant['id'];
            $_SESSION['current_role'] = 'admin';
            $_SESSION['current_restaurant_name'] = $anyRestaurant['name'];
        }
    } elseif ($firstRestaurant) {
        $_SESSION['current_restaurant_id'] = (int)$firstRestaurant['id'];
        $_SESSION['current_role'] = $firstRestaurant['role'];
        $_SESSION['current_restaurant_name'] = $firstRestaurant['name'];
    }

    // Update last_login_at
    $stmt = db()->prepare("UPDATE users SET last_login_at = NOW() WHERE id = ?");
    $stmt->execute([$user['id']]);

    // Handle remember me
    if ($remember) {
        $token = bin2hex(random_bytes(32));
        $_SESSION['remember_token'] = $token;
        setcookie(
            'remember_token',
            $token,
            time() + (30 * 24 * 60 * 60),
            '/',
            '',
            isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on',
            true
        );
        return $token;
    }

    return null;
}

/**
 * Logout user and destroy session
 */
function logout_user() {
    session_start_once();
    if (isset($_COOKIE['remember_token'])) {
        setcookie('remember_token', '', time() - 3600, '/');
    }
    destroy_session();
}

/**
 * Switch the current restaurant context for the logged-in user
 */
function switchRestaurant($restaurantId) {
    session_start_once();
    $userId = currentUserId();
    if (!$userId) return false;

    $restaurant = getRestaurant($restaurantId);
    if (!$restaurant) return false;

    // Super-admins can switch to any restaurant with admin-level access
    if (isSuperAdmin()) {
        $_SESSION['current_restaurant_id'] = (int)$restaurantId;
        $_SESSION['current_role'] = 'admin';
        $_SESSION['current_restaurant_name'] = $restaurant['name'];
        return true;
    }

    $role = getUserRole($userId, $restaurantId);
    if (!$role) return false;

    $_SESSION['current_restaurant_id'] = (int)$restaurantId;
    $_SESSION['current_role'] = $role;
    $_SESSION['current_restaurant_name'] = $restaurant['name'];
    return true;
}

function session_start_once(): void {
    if (session_status() === PHP_SESSION_NONE) {
        init_session();
    }
}

function requireAuth(): void {
    session_start_once();
    if (empty($_SESSION['user'])) {
        if (isset($_SERVER['HTTP_HX_REQUEST'])) {
            http_response_code(401);
            header('HX-Redirect: /login.php');
        } else {
            header('Location: /login.php');
        }
        exit;
    }
    // Set timezone for all date() calls to use this restaurant's timezone
    $rid = $_SESSION['current_restaurant_id'] ?? null;
    if ($rid) {
        applyRestaurantTimezone($rid);
    }
}

/**
 * Access guards — halt with 403 if role requirement not met
 */
function requireAdmin(): void {
    requireAuth();
    if (!isAdmin()) {
        http_response_code(403);
        echo '<div class="alert alert-danger">Access denied. Admin role required.</div>';
        exit;
    }
}

function requireManager(): void {
    requireAuth();
    if (!isManager()) {
        http_response_code(403);
        echo '<div class="alert alert-danger">Access denied. Manager role required.</div>';
        exit;
    }
}

function requireSuperAdmin(): void {
    requireAuth();
    if (!isSuperAdmin()) {
        http_response_code(403);
        echo '<div class="alert alert-danger">Access denied. Super-admin required.</div>';
        exit;
    }
}

/**
 * Role checks for current restaurant context
 *
 * Hierarchy: super-admin > admin > manager > user
 */
function isAdmin(): bool {
    return ($_SESSION['current_role'] ?? '') === 'admin';
}

function isManager(): bool {
    $role = $_SESSION['current_role'] ?? '';
    return $role === 'admin' || $role === 'manager';
}

function isStaff(): bool {
    $role = $_SESSION['current_role'] ?? '';
    return in_array($role, ['admin', 'manager', 'user']);
}

function isSuperAdmin(): bool {
    return ($_SESSION['user']['role'] ?? '') === 'super-admin';
}

// Backward-compatible aliases
function isOwner(): bool { return isAdmin(); }
function isHost(): bool { return isStaff(); }
function isPlatformAdmin(): bool { return isSuperAdmin(); }
function requireOwner(): void { requireAdmin(); }
function requirePlatformAdmin(): void { requireSuperAdmin(); }

function currentUserId(): int {
    session_start_once();
    return (int)($_SESSION['user']['id'] ?? 0);
}

function currentRestaurantId(): ?int {
    session_start_once();
    return isset($_SESSION['current_restaurant_id']) ? (int)$_SESSION['current_restaurant_id'] : null;
}

/**
 * Get permitted navigation item IDs for the current user context.
 * Queries the nav_permissions table using NULL-means-any matching logic.
 */
function getPermittedNavItems(?string $userRole, ?string $restaurantRole): array {
    $sql = "SELECT DISTINCT nav_item_id FROM nav_permissions
            WHERE is_active = 1
              AND (user_role IS NULL OR user_role = ?)
              AND (restaurant_role IS NULL OR restaurant_role = ?)";
    $stmt = db()->prepare($sql);
    $stmt->execute([$userRole, $restaurantRole]);
    return $stmt->fetchAll(PDO::FETCH_COLUMN, 0);
}
