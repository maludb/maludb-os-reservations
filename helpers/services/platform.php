<?php
/**
 * Platform service — super-admin administration across all restaurants:
 * restaurants, platform users, products & features, subscriptions,
 * account options, nav permissions and per-restaurant billing settings.
 * Used by html/partials/platform/* and helpers/api/ops/platform.php.
 */

require_once __DIR__ . '/_base.php';

const PLATFORM_USER_ROLES = ['user', 'super-admin'];
const PLATFORM_RESTAURANT_ROLES = ['user', 'manager', 'admin'];
const PLATFORM_BILLING_INTERVALS = ['monthly', 'yearly', 'one_time'];
const PLATFORM_SUBSCRIPTION_STATUSES = ['active', 'trialing', 'past_due', 'cancelled'];

/** Fetch a row by id from a platform (not restaurant-scoped) table or throw NOT_FOUND. */
function svc_platform_row(string $table, int $id, string $what): array
{
    $stmt = db()->prepare("SELECT * FROM {$table} WHERE id = ?");
    $stmt->execute([$id]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$row) svc_not_found($what);
    return $row;
}

// ── Restaurants ─────────────────────────────────────────────

function svc_platform_restaurant_list(): array
{
    return db()->query(
        "SELECT r.*,
                (SELECT COUNT(*) FROM user_restaurants ur WHERE ur.restaurant_id = r.id AND ur.is_active = 1) AS staff_count,
                (SELECT COUNT(*) FROM reservations res WHERE res.restaurant_id = r.id AND res.reservation_date = CURRENT_DATE) AS today_reservations
         FROM restaurants r ORDER BY r.name"
    )->fetchAll(PDO::FETCH_ASSOC);
}

function svc_platform_restaurant_get(int $id): array
{
    return svc_platform_row('restaurants', $id, 'Restaurant');
}

/** Restaurant overview: staff, reservation counts, subscriptions, account options. */
function svc_platform_restaurant_dashboard(int $id): array
{
    $pdo = db();
    $r = svc_platform_restaurant_get($id);

    $stmt = $pdo->prepare(
        "SELECT u.id, u.first_name, u.last_name, u.email, ur.role, ur.is_active
         FROM user_restaurants ur JOIN users u ON u.id = ur.user_id
         WHERE ur.restaurant_id = ? ORDER BY ur.role, u.last_name"
    );
    $stmt->execute([$id]);
    $staff = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $stmt = $pdo->prepare("SELECT COUNT(*) FROM reservations WHERE restaurant_id = ? AND reservation_date = CURRENT_DATE");
    $stmt->execute([$id]);
    $today = (int)$stmt->fetchColumn();
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM reservations WHERE restaurant_id = ?");
    $stmt->execute([$id]);
    $total = (int)$stmt->fetchColumn();

    $stmt = $pdo->prepare(
        "SELECT s.*, p.name AS product_name, p.price AS product_price
         FROM subscriptions s JOIN products p ON p.id = s.product_id
         WHERE s.restaurant_id = ? ORDER BY s.created_at DESC"
    );
    $stmt->execute([$id]);
    $subs = $stmt->fetchAll(PDO::FETCH_ASSOC);

    return [
        'restaurant' => $r,
        'staff' => $staff,
        'today_reservations' => $today,
        'total_reservations' => $total,
        'subscriptions' => $subs,
        'account_options' => svc_platform_account_option_list($id),
    ];
}

/**
 * Create ($id = 0) or update a restaurant. A new restaurant gets default
 * settings, a Main Dining section, turn times and hours, and $creatorId as admin.
 * Input: name, slug, timezone, phone, email, address_line1, city, state, postal_code, website
 */
function svc_platform_restaurant_save(int $id, array $in, int $creatorId): array
{
    $name = trim($in['name'] ?? '');
    $slug = trim($in['slug'] ?? '');
    $timezone = trim($in['timezone'] ?? '');
    if ($name === '' || $slug === '' || $timezone === '') svc_fail('Name, slug, and timezone are required.', 'required');
    if (!preg_match('/^[a-z0-9\-]+$/', $slug)) svc_fail('Slug must contain only lowercase letters, numbers, and hyphens.', 'slug-format');
    if (!in_array($timezone, DateTimeZone::listIdentifiers(), true)) svc_fail('Unknown timezone.', 'timezone');

    $pdo = db();
    $stmt = $pdo->prepare("SELECT id FROM restaurants WHERE slug = ? AND id != ?");
    $stmt->execute([$slug, $id]);
    if ($stmt->fetch()) svc_fail('This slug is already in use. Please choose a different one.', 'slug-unique');

    $email = trim($in['email'] ?? '');
    if ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) svc_fail('Please enter a valid email address.', 'email');
    $website = trim($in['website'] ?? '');
    if ($website !== '' && !filter_var($website, FILTER_VALIDATE_URL)) svc_fail('Please enter a valid website URL.', 'url');

    $values = [
        $name, $slug, trim($in['phone'] ?? '') ?: null, $email ?: null, trim($in['address_line1'] ?? '') ?: null,
        trim($in['city'] ?? '') ?: null, trim($in['state'] ?? '') ?: null, trim($in['postal_code'] ?? '') ?: null,
        $website ?: null, $timezone,
    ];

    if ($id > 0) {
        svc_platform_restaurant_get($id);
        $pdo->prepare(
            "UPDATE restaurants SET name = ?, slug = ?, phone = ?, email = ?, address_line1 = ?,
                    city = ?, state = ?, postal_code = ?, website = ?, timezone = ?, updated_at = NOW()
             WHERE id = ?"
        )->execute([...$values, $id]);
        svc_log($id, $creatorId, 'update', 'restaurant', $id, "Platform: updated restaurant {$name}");
        return svc_platform_restaurant_get($id);
    }

    $pdo->beginTransaction();
    try {
        $stmt = $pdo->prepare(
            "INSERT INTO restaurants (name, slug, phone, email, address_line1, city, state, postal_code, website, timezone, is_active, created_at)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 1, NOW()) RETURNING id"
        );
        $stmt->execute($values);
        $rid = (int)$stmt->fetchColumn();

        $defaults = [
            'time_slot_interval' => '30', 'default_turn_time' => '90', 'buffer_time' => '15',
            'max_party_size' => '12', 'max_online_party_size' => '8', 'advance_booking_min_hours' => '2',
            'advance_booking_max_days' => '30', 'same_day_cutoff_hours' => '1', 'max_covers_per_slot' => '0',
            'online_table_hold_percent' => '70', 'confirmation_email_enabled' => '1', 'reminder_email_enabled' => '1',
            'reminder_hours_before' => '24', 'cancellation_email_enabled' => '1',
            'cancellation_policy' => 'Please cancel at least 2 hours before your reservation time.',
        ];
        $ins = $pdo->prepare("INSERT INTO settings (restaurant_id, setting_key, setting_value) VALUES (?, ?, ?)");
        foreach ($defaults as $k => $v) $ins->execute([$rid, $k, $v]);

        $pdo->prepare("INSERT INTO sections (restaurant_id, name, description, display_order, is_active) VALUES (?, 'Main Dining', 'Main dining area', 1, 1)")
            ->execute([$rid]);
        $pdo->prepare(
            "INSERT INTO turn_times (restaurant_id, min_party_size, max_party_size, service_period, duration_minutes, buffer_minutes)
             VALUES (?, 1, 4, 'all', 90, 15), (?, 5, 8, 'all', 105, 15), (?, 9, 12, 'all', 120, 15)"
        )->execute([$rid, $rid, $rid]);

        $hours = $pdo->prepare(
            "INSERT INTO operating_hours (restaurant_id, day_of_week, service_name, open_time, close_time, first_seating, last_seating, is_active)
             VALUES (?, ?, ?, ?, ?, ?, ?, 1)"
        );
        foreach ([2, 3, 4, 5, 6] as $day) { // Tue-Sat
            $hours->execute([$rid, $day, 'Lunch', '11:00:00', '14:30:00', '11:00:00', '14:00:00']);
            $hours->execute([$rid, $day, 'Dinner', '17:00:00', '22:00:00', '17:00:00', '21:00:00']);
        }
        $hours->execute([$rid, 0, 'Brunch', '10:00:00', '14:30:00', '10:00:00', '14:00:00']);
        $hours->execute([$rid, 0, 'Dinner', '17:00:00', '21:00:00', '17:00:00', '20:00:00']);

        if ($creatorId > 0) {
            $pdo->prepare("INSERT INTO user_restaurants (user_id, restaurant_id, role, is_active) VALUES (?, ?, 'admin', 1)")
                ->execute([$creatorId, $rid]);
        }
        svc_log($rid, $creatorId, 'create', 'restaurant', $rid, "Platform: created restaurant {$name}");
        $pdo->commit();
    } catch (Throwable $e) {
        $pdo->rollBack();
        throw $e;
    }
    return svc_platform_restaurant_get($rid);
}

function svc_platform_restaurant_set_active(int $id, bool $active, int $actorId): array
{
    $r = svc_platform_restaurant_get($id);
    db()->prepare("UPDATE restaurants SET is_active = ?, updated_at = NOW() WHERE id = ?")->execute([$active ? 1 : 0, $id]);
    svc_log($id, $actorId, 'status_change', 'restaurant', $id,
        'Platform: ' . ($active ? 'activated' : 'deactivated') . " restaurant {$r['name']}");
    return svc_platform_restaurant_get($id);
}

// ── Platform users ──────────────────────────────────────────

/** Users without password hashes. */
function svc_platform_user_list(): array
{
    return db()->query(
        "SELECT u.id, u.first_name, u.last_name, u.email, u.phone, u.role, u.is_active, u.auth_provider,
                u.last_login_at, u.created_at,
                (SELECT STRING_AGG(r.name || ' (' || ur.role || ')', ', ')
                 FROM user_restaurants ur JOIN restaurants r ON r.id = ur.restaurant_id
                 WHERE ur.user_id = u.id AND ur.is_active = 1) AS restaurant_assignments
         FROM users u ORDER BY u.role DESC, u.last_name, u.first_name"
    )->fetchAll(PDO::FETCH_ASSOC);
}

function svc_platform_user_get(int $id): array
{
    $u = svc_platform_row('users', $id, 'User');
    unset($u['password_hash'], $u['google_id']);
    $stmt = db()->prepare(
        "SELECT ur.restaurant_id, r.name AS restaurant_name, ur.role, ur.is_active
         FROM user_restaurants ur JOIN restaurants r ON r.id = ur.restaurant_id WHERE ur.user_id = ? ORDER BY r.name"
    );
    $stmt->execute([$id]);
    $u['restaurants'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
    return $u;
}

/**
 * Create ($id = 0) or update a platform user.
 * Input: first_name, last_name, email, phone, password (required on create), role,
 *        assign_restaurant_id + assign_role (optional restaurant membership)
 */
function svc_platform_user_save(int $id, array $in, int $actorId): array
{
    $firstName = trim($in['first_name'] ?? '');
    $lastName = trim($in['last_name'] ?? '');
    $email = trim($in['email'] ?? '');
    $phone = trim($in['phone'] ?? '');
    $password = (string)($in['password'] ?? '');
    $role = $in['role'] ?? 'user';

    if ($firstName === '' || $lastName === '' || $email === '') svc_fail('First name, last name, and email are required.');
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) svc_fail('Please enter a valid email address.', 'email');
    if (!in_array($role, PLATFORM_USER_ROLES, true)) svc_fail('Invalid platform role.', 'role');
    if ($id <= 0 && $password === '') svc_fail('Password is required for new users.', 'password');
    if ($password !== '' && strlen($password) < 8) svc_fail('Password must be at least 8 characters.', 'password');

    $pdo = db();
    $stmt = $pdo->prepare("SELECT id FROM users WHERE email = ? AND id != ?");
    $stmt->execute([$email, $id]);
    if ($stmt->fetch()) svc_fail('A user with this email already exists.', 'email');

    $isAdmin = $role === 'super-admin' ? 1 : 0;
    if ($id > 0) {
        svc_platform_row('users', $id, 'User');
        if ($id === $actorId && $role !== 'super-admin') svc_fail('You cannot remove your own super-admin role.', 'role');
        $pdo->prepare("UPDATE users SET first_name = ?, last_name = ?, email = ?, phone = ?, role = ?, is_platform_admin = ?, updated_at = NOW() WHERE id = ?")
            ->execute([$firstName, $lastName, $email, $phone ?: null, $role, $isAdmin, $id]);
        if ($password !== '') {
            $pdo->prepare("UPDATE users SET password_hash = ? WHERE id = ?")->execute([password_hash($password, PASSWORD_DEFAULT), $id]);
        }
        $assignRid = (int)($in['assign_restaurant_id'] ?? 0);
        $assignRole = $in['assign_role'] ?? '';
        if ($assignRid > 0 && in_array($assignRole, PLATFORM_RESTAURANT_ROLES, true)) {
            svc_platform_restaurant_get($assignRid);
            $pdo->prepare(
                "INSERT INTO user_restaurants (user_id, restaurant_id, role, is_active) VALUES (?, ?, ?, 1)
                 ON CONFLICT (user_id, restaurant_id) DO UPDATE SET role = EXCLUDED.role, is_active = 1, updated_at = NOW()"
            )->execute([$id, $assignRid, $assignRole]);
        }
        svc_log(0, $actorId, 'update', 'user', $id, "Platform: updated user {$email}");
    } else {
        $stmt = $pdo->prepare(
            "INSERT INTO users (first_name, last_name, email, phone, password_hash, role, is_platform_admin, is_active, created_at)
             VALUES (?, ?, ?, ?, ?, ?, ?, 1, NOW()) RETURNING id"
        );
        $stmt->execute([$firstName, $lastName, $email, $phone ?: null, password_hash($password, PASSWORD_DEFAULT), $role, $isAdmin]);
        $id = (int)$stmt->fetchColumn();
        svc_log(0, $actorId, 'create', 'user', $id, "Platform: created user {$email}");
    }
    return svc_platform_user_get($id);
}

function svc_platform_user_set_active(int $id, bool $active, int $actorId): array
{
    if ($id === $actorId && !$active) svc_fail('You cannot deactivate your own account.');
    $u = svc_platform_row('users', $id, 'User');
    db()->prepare("UPDATE users SET is_active = ?, updated_at = NOW() WHERE id = ?")->execute([$active ? 1 : 0, $id]);
    svc_log(0, $actorId, 'status_change', 'user', $id, 'Platform: ' . ($active ? 'activated' : 'deactivated') . " user {$u['email']}");
    return svc_platform_user_get($id);
}

/** Flip a user's active flag (UI toggle button). */
function svc_platform_user_toggle(int $id, int $actorId): array
{
    $u = svc_platform_row('users', $id, 'User');
    return svc_platform_user_set_active($id, !$u['is_active'], $actorId);
}

// ── Products & features ─────────────────────────────────────

function svc_platform_product_list(): array
{
    return db()->query(
        "SELECT p.*,
                (SELECT COUNT(*) FROM product_features pf WHERE pf.product_id = p.id) AS feature_count,
                (SELECT COUNT(*) FROM subscriptions s WHERE s.product_id = p.id AND s.status IN ('active','trialing')) AS active_subs
         FROM products p ORDER BY p.sort_order, p.name"
    )->fetchAll(PDO::FETCH_ASSOC);
}

function svc_platform_product_get(int $id): array
{
    $p = svc_platform_row('products', $id, 'Product');
    $p['features'] = svc_platform_feature_list($id);
    return $p;
}

/** Create ($id = 0) or update. Input: name, slug, description, price, billing_interval, sort_order, is_active */
function svc_platform_product_save(int $id, array $in, int $actorId): array
{
    $name = trim($in['name'] ?? '');
    $slug = trim($in['slug'] ?? '');
    $interval = $in['billing_interval'] ?? 'monthly';
    if ($name === '' || $slug === '') svc_fail('Name and slug are required.', 'required');
    if (!preg_match('/^[a-z0-9\-]+$/', $slug)) svc_fail('Slug must contain only lowercase letters, numbers, and hyphens.', 'slug');
    if (!in_array($interval, PLATFORM_BILLING_INTERVALS, true)) svc_fail('Invalid billing interval.', 'billing');

    $pdo = db();
    $stmt = $pdo->prepare("SELECT id FROM products WHERE slug = ? AND id != ?");
    $stmt->execute([$slug, $id]);
    if ($stmt->fetch()) svc_fail('This slug is already in use.', 'slug-unique');

    $values = [$name, $slug, trim($in['description'] ?? '') ?: null, round((float)($in['price'] ?? 0), 2),
               $interval, (int)($in['sort_order'] ?? 0), !empty($in['is_active']) ? 1 : 0];
    if ($id > 0) {
        svc_platform_row('products', $id, 'Product');
        $pdo->prepare("UPDATE products SET name = ?, slug = ?, description = ?, price = ?, billing_interval = ?, sort_order = ?, is_active = ?, updated_at = NOW() WHERE id = ?")
            ->execute([...$values, $id]);
    } else {
        $stmt = $pdo->prepare("INSERT INTO products (name, slug, description, price, billing_interval, sort_order, is_active) VALUES (?, ?, ?, ?, ?, ?, ?) RETURNING id");
        $stmt->execute($values);
        $id = (int)$stmt->fetchColumn();
    }
    svc_log(0, $actorId, 'save', 'product', $id, "Platform: saved product {$name}");
    return svc_platform_product_get($id);
}

function svc_platform_product_set_active(int $id, bool $active, int $actorId): array
{
    svc_platform_row('products', $id, 'Product');
    db()->prepare("UPDATE products SET is_active = ?, updated_at = NOW() WHERE id = ?")->execute([$active ? 1 : 0, $id]);
    svc_log(0, $actorId, 'status_change', 'product', $id, 'Platform: product ' . ($active ? 'activated' : 'deactivated'));
    return svc_platform_product_get($id);
}

function svc_platform_product_toggle(int $id, int $actorId): array
{
    $p = svc_platform_row('products', $id, 'Product');
    return svc_platform_product_set_active($id, !$p['is_active'], $actorId);
}

function svc_platform_feature_list(int $productId): array
{
    $stmt = db()->prepare("SELECT * FROM product_features WHERE product_id = ? ORDER BY sort_order, id");
    $stmt->execute([$productId]);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

/** Input: feature_key, feature_label, feature_value, sort_order */
function svc_platform_feature_add(int $productId, array $in, int $actorId): array
{
    $key = trim($in['feature_key'] ?? '');
    $label = trim($in['feature_label'] ?? '');
    if ($productId <= 0 || $key === '' || $label === '') svc_fail('Product, feature key, and label are required.');
    svc_platform_row('products', $productId, 'Product');
    $stmt = db()->prepare(
        "INSERT INTO product_features (product_id, feature_key, feature_label, feature_value, sort_order) VALUES (?, ?, ?, ?, ?) RETURNING *"
    );
    $stmt->execute([$productId, $key, $label, trim($in['feature_value'] ?? '') ?: null, (int)($in['sort_order'] ?? 0)]);
    svc_log(0, $actorId, 'create', 'product_feature', $productId, "Platform: added feature {$key}");
    return $stmt->fetch(PDO::FETCH_ASSOC);
}

function svc_platform_feature_delete(int $productId, int $featureId, int $actorId): void
{
    $stmt = db()->prepare("DELETE FROM product_features WHERE id = ? AND product_id = ?");
    $stmt->execute([$featureId, $productId]);
    if ($stmt->rowCount() === 0) svc_not_found('Feature');
    svc_log(0, $actorId, 'delete', 'product_feature', $productId, "Platform: removed feature #{$featureId}");
}

// ── Subscriptions ───────────────────────────────────────────

function svc_platform_subscription_list(?int $restaurantId = null): array
{
    $sql = "SELECT s.*, r.name AS restaurant_name, r.slug AS restaurant_slug, p.name AS product_name, p.price AS product_price
            FROM subscriptions s JOIN restaurants r ON r.id = s.restaurant_id JOIN products p ON p.id = s.product_id";
    $params = [];
    if ($restaurantId) { $sql .= " WHERE s.restaurant_id = ?"; $params[] = $restaurantId; }
    $stmt = db()->prepare($sql . " ORDER BY s.created_at DESC");
    $stmt->execute($params);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

/** Create ($id = 0) or update. Input: restaurant_id, product_id, status, started_at, expires_at, notes */
function svc_platform_subscription_save(int $id, array $in, int $actorId): array
{
    $rid = (int)($in['restaurant_id'] ?? 0);
    $productId = (int)($in['product_id'] ?? 0);
    $status = $in['status'] ?? 'active';
    if ($rid <= 0 || $productId <= 0 || trim($in['started_at'] ?? '') === '') svc_fail('Restaurant, product, and start date are required.');
    if (!in_array($status, PLATFORM_SUBSCRIPTION_STATUSES, true)) svc_fail('Invalid status.', 'status');
    $startedAt = svc_date($in['started_at'], 'started_at');
    $expiresAt = svc_date($in['expires_at'] ?? '', 'expires_at', false);
    svc_platform_restaurant_get($rid);
    svc_platform_row('products', $productId, 'Product');
    $cancelledAt = $status === 'cancelled' ? date('Y-m-d H:i:s') : null;
    $notes = trim($in['notes'] ?? '') ?: null;

    if ($id > 0) {
        svc_platform_row('subscriptions', $id, 'Subscription');
        db()->prepare("UPDATE subscriptions SET product_id = ?, status = ?, started_at = ?, expires_at = ?, cancelled_at = ?, notes = ?, updated_at = NOW() WHERE id = ?")
            ->execute([$productId, $status, $startedAt, $expiresAt, $cancelledAt, $notes, $id]);
    } else {
        $stmt = db()->prepare(
            "INSERT INTO subscriptions (restaurant_id, product_id, status, started_at, expires_at, cancelled_at, notes) VALUES (?, ?, ?, ?, ?, ?, ?) RETURNING id"
        );
        $stmt->execute([$rid, $productId, $status, $startedAt, $expiresAt, $cancelledAt, $notes]);
        $id = (int)$stmt->fetchColumn();
    }
    svc_log($rid, $actorId, 'save', 'subscription', $id, "Platform: saved subscription #{$id} ({$status})");
    return svc_platform_row('subscriptions', $id, 'Subscription');
}

// ── Account options ─────────────────────────────────────────

function svc_platform_account_option_list(?int $restaurantId = null): array
{
    $sql = "SELECT ao.*, r.name AS restaurant_name FROM account_options ao JOIN restaurants r ON r.id = ao.restaurant_id";
    $params = [];
    if ($restaurantId) { $sql .= " WHERE ao.restaurant_id = ?"; $params[] = $restaurantId; }
    $stmt = db()->prepare($sql . " ORDER BY r.name, ao.option_key");
    $stmt->execute($params);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

/**
 * Create ($id = 0; upserts on restaurant + key) or update value/enabled/notes.
 * Input: restaurant_id, option_key, option_value, enabled, notes
 */
function svc_platform_account_option_save(int $id, array $in, int $actorId): array
{
    $value = trim((string)($in['option_value'] ?? '1'));
    $enabled = !empty($in['enabled']) ? 1 : 0;
    $notes = trim($in['notes'] ?? '') ?: null;

    if ($id > 0) {
        $row = svc_platform_row('account_options', $id, 'Account option');
        db()->prepare("UPDATE account_options SET option_value = ?, enabled = ?, notes = ? WHERE id = ?")
            ->execute([$value, $enabled, $notes, $id]);
        $rid = (int)$row['restaurant_id'];
    } else {
        $rid = (int)($in['restaurant_id'] ?? 0);
        $key = trim($in['option_key'] ?? '');
        if ($rid <= 0 || $key === '') svc_fail('Restaurant and option key are required.');
        svc_platform_restaurant_get($rid);
        $stmt = db()->prepare(
            "INSERT INTO account_options (restaurant_id, option_key, option_value, enabled, notes) VALUES (?, ?, ?, ?, ?)
             ON CONFLICT (restaurant_id, option_key) DO UPDATE SET option_value = EXCLUDED.option_value, enabled = EXCLUDED.enabled, notes = EXCLUDED.notes
             RETURNING id"
        );
        $stmt->execute([$rid, $key, $value, $enabled, $notes]);
        $id = (int)$stmt->fetchColumn();
    }
    svc_log($rid, $actorId, 'save', 'account_option', $id, "Platform: saved account option #{$id}");
    return svc_platform_row('account_options', $id, 'Account option');
}

function svc_platform_account_option_set_enabled(int $id, bool $enabled, int $actorId): array
{
    $row = svc_platform_row('account_options', $id, 'Account option');
    db()->prepare("UPDATE account_options SET enabled = ? WHERE id = ?")->execute([$enabled ? 1 : 0, $id]);
    svc_log((int)$row['restaurant_id'], $actorId, 'status_change', 'account_option', $id,
        "Platform: account option {$row['option_key']} " . ($enabled ? 'enabled' : 'disabled'));
    return svc_platform_row('account_options', $id, 'Account option');
}

function svc_platform_account_option_toggle(int $id, int $actorId): array
{
    $row = svc_platform_row('account_options', $id, 'Account option');
    return svc_platform_account_option_set_enabled($id, !$row['enabled'], $actorId);
}

function svc_platform_account_option_delete(int $id, int $actorId): void
{
    $row = svc_platform_row('account_options', $id, 'Account option');
    db()->prepare("DELETE FROM account_options WHERE id = ?")->execute([$id]);
    svc_log((int)$row['restaurant_id'], $actorId, 'delete', 'account_option', $id, "Platform: deleted account option {$row['option_key']}");
}

// ── Nav permissions ─────────────────────────────────────────

function svc_platform_nav_permission_list(string $navItemId = ''): array
{
    $sql = "SELECT * FROM nav_permissions";
    $params = [];
    if ($navItemId !== '') { $sql .= " WHERE nav_item_id = ?"; $params[] = $navItemId; }
    $stmt = db()->prepare($sql . " ORDER BY nav_item_id, user_role, restaurant_role");
    $stmt->execute($params);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

/**
 * Create ($id = 0) or update a sidebar visibility rule. NULL role = any.
 * Input: nav_item_id, label, user_role, restaurant_role, is_active
 */
function svc_platform_nav_permission_save(int $id, array $in, int $actorId): array
{
    $navItemId = trim($in['nav_item_id'] ?? '');
    if ($navItemId === '') svc_fail('Nav Item ID is required.', 'nav_item_id');
    $values = [$navItemId, trim($in['label'] ?? '') ?: null, trim($in['user_role'] ?? '') ?: null,
               trim($in['restaurant_role'] ?? '') ?: null, !empty($in['is_active']) ? 1 : 0];

    $dup = db()->prepare(
        "SELECT id FROM nav_permissions WHERE nav_item_id = ? AND user_role IS NOT DISTINCT FROM ?
           AND restaurant_role IS NOT DISTINCT FROM ? AND id != ?"
    );
    $dup->execute([$values[0], $values[2], $values[3], $id]);
    if ($dup->fetch()) svc_fail('A rule for this nav item and role combination already exists.');

    if ($id > 0) {
        svc_platform_row('nav_permissions', $id, 'Nav permission');
        db()->prepare("UPDATE nav_permissions SET nav_item_id = ?, label = ?, user_role = ?, restaurant_role = ?, is_active = ? WHERE id = ?")
            ->execute([...$values, $id]);
    } else {
        $stmt = db()->prepare("INSERT INTO nav_permissions (nav_item_id, label, user_role, restaurant_role, is_active) VALUES (?, ?, ?, ?, ?) RETURNING id");
        $stmt->execute($values);
        $id = (int)$stmt->fetchColumn();
    }
    svc_log(0, $actorId, 'save', 'nav_permission', $id, "Platform: saved nav permission {$navItemId}");
    return svc_platform_row('nav_permissions', $id, 'Nav permission');
}

function svc_platform_nav_permission_delete(int $id, int $actorId): void
{
    $row = svc_platform_row('nav_permissions', $id, 'Nav permission');
    db()->prepare("DELETE FROM nav_permissions WHERE id = ?")->execute([$id]);
    svc_log(0, $actorId, 'delete', 'nav_permission', $id, "Platform: deleted nav permission {$row['nav_item_id']}");
}

// ── Billing settings (per restaurant) ───────────────────────

function svc_platform_billing_settings_list(): array
{
    return db()->query(
        "SELECT r.id, r.name, r.invoice_day_of_month, r.invoice_description, r.monthly_price, r.prepay_balance,
                r.billing_user, u.email AS billing_user_email
         FROM restaurants r LEFT JOIN users u ON r.billing_user = u.id ORDER BY r.name"
    )->fetchAll(PDO::FETCH_ASSOC);
}

function svc_platform_billing_settings_get(int $restaurantId): array
{
    $stmt = db()->prepare(
        "SELECT id, name, invoice_day_of_month, invoice_description, monthly_price, prepay_balance, billing_user
         FROM restaurants WHERE id = ?"
    );
    $stmt->execute([$restaurantId]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$row) svc_not_found('Restaurant');
    return $row;
}

/** Input: invoice_day_of_month (1-28), invoice_description, monthly_price */
function svc_platform_billing_settings_save(int $restaurantId, array $in, int $actorId): array
{
    if ($restaurantId <= 0) svc_fail('Invalid restaurant.');
    $day = (int)($in['invoice_day_of_month'] ?? 1);
    $desc = trim($in['invoice_description'] ?? '');
    $price = round((float)($in['monthly_price'] ?? 0), 2);
    if ($day < 1 || $day > 28) svc_fail('Invoice day must be between 1 and 28.', 'invoice_day_of_month');
    if ($desc === '') svc_fail('Invoice description is required.', 'invoice_description');
    if ($price < 0) svc_fail('Monthly price cannot be negative.', 'monthly_price');
    $old = svc_platform_billing_settings_get($restaurantId);
    db()->prepare("UPDATE restaurants SET invoice_day_of_month = ?, invoice_description = ?, monthly_price = ?, updated_at = NOW() WHERE id = ?")
        ->execute([$day, $desc, $price, $restaurantId]);
    svc_log($restaurantId, $actorId, 'update', 'billing_settings', $restaurantId, 'Platform: updated billing settings',
        array_intersect_key($old, array_flip(['invoice_day_of_month', 'invoice_description', 'monthly_price'])),
        ['invoice_day_of_month' => $day, 'invoice_description' => $desc, 'monthly_price' => $price]);
    return svc_platform_billing_settings_get($restaurantId);
}
