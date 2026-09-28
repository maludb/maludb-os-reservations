<?php
/**
 * Team service — staff users of a restaurant and the restaurant's phone numbers.
 * Used by html/partials/settings/{save-user,toggle-user,save-phone-number,delete-phone-number}.php
 * and the API (helpers/api/ops/team.php).
 */

require_once __DIR__ . '/_base.php';
require_once __DIR__ . '/../validation.php';

const TEAM_ROLES = ['admin', 'manager', 'user'];

/* ---------- Staff users ---------- */

function svc_team_list(int $rid): array
{
    $stmt = db()->prepare(
        "SELECT u.id, u.first_name, u.last_name, u.email, u.phone, u.last_login_at,
                ur.role, ur.is_active AS membership_active,
                (u.password_hash = '!INVITED') AS invite_pending
         FROM user_restaurants ur
         JOIN users u ON u.id = ur.user_id
         WHERE ur.restaurant_id = ?
         ORDER BY ur.role ASC, u.last_name ASC"
    );
    $stmt->execute([$rid]);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

function svc_team_get(int $rid, int $userId): array
{
    foreach (svc_team_list($rid) as $u) {
        if ((int)$u['id'] === $userId) return $u;
    }
    svc_not_found('User');
}

/** Throw if $userId is the restaurant's only active admin. */
function svc_team_guard_last_admin(int $rid, int $userId, string $message): void
{
    $stmt = db()->prepare(
        "SELECT COUNT(*) FROM user_restaurants WHERE restaurant_id = ? AND role = 'admin' AND is_active = 1 AND user_id != ?"
    );
    $stmt->execute([$rid, $userId]);
    $stmt2 = db()->prepare("SELECT 1 FROM user_restaurants WHERE restaurant_id = ? AND user_id = ? AND role = 'admin' AND is_active = 1");
    $stmt2->execute([$rid, $userId]);
    if ($stmt2->fetchColumn() && (int)$stmt->fetchColumn() === 0) {
        throw new ServiceError($message, 'CONFLICT', 409);
    }
}

/**
 * Add ($userId = 0) or edit a staff member.
 * Input: first_name, last_name, email, phone, role, password (optional).
 * A new user without a password is marked invited and sets one via Accept Invitation, with the
 * one-time code returned here as 'invite_code' (shown to the admin once; editing a pending invitation
 * issues a new code).
 * An existing account with the same email is linked to the restaurant instead.
 */
function svc_team_save(int $rid, int $userId, array $in): array
{
    $firstName = trim($in['first_name'] ?? '');
    $lastName = trim($in['last_name'] ?? '');
    $email = trim($in['email'] ?? '');
    $phone = trim($in['phone'] ?? '');
    $role = trim($in['role'] ?? '');
    $password = (string)($in['password'] ?? '');

    if ($firstName === '' || $lastName === '' || $email === '') {
        svc_fail('First name, last name, and email are required.', 'required');
    }
    if (!validate_email($email)) svc_fail('Please enter a valid email address.', 'email');
    if (!in_array($role, TEAM_ROLES, true)) svc_fail('Please select a valid role.', 'role');
    if ($password !== '') {
        $pwCheck = validate_password($password);
        if (!$pwCheck['valid']) svc_fail($pwCheck['message'], 'pw');
    }

    $pdo = db();
    $inviteCode = null;
    $pdo->beginTransaction();
    try {
        if ($userId > 0) {
            $stmt = $pdo->prepare("SELECT role FROM user_restaurants WHERE user_id = ? AND restaurant_id = ?");
            $stmt->execute([$userId, $rid]);
            $currentRole = $stmt->fetchColumn();
            if ($currentRole === false) {
                throw new ServiceError('User not found in this restaurant.', 'NOT_FOUND', 404, 'notfound');
            }
            if ($currentRole === 'admin' && $role !== 'admin') {
                svc_team_guard_last_admin($rid, $userId, 'This is the only active admin; make someone else admin first.');
            }
            if ($password !== '') {
                $pdo->prepare("UPDATE users SET first_name = ?, last_name = ?, phone = ?, password_hash = ? WHERE id = ?")
                    ->execute([$firstName, $lastName, $phone ?: null, password_hash($password, PASSWORD_DEFAULT), $userId]);
            } else {
                $pdo->prepare("UPDATE users SET first_name = ?, last_name = ?, phone = ? WHERE id = ?")
                    ->execute([$firstName, $lastName, $phone ?: null, $userId]);
            }
            $pdo->prepare("UPDATE user_restaurants SET role = ? WHERE user_id = ? AND restaurant_id = ?")
                ->execute([$role, $userId, $rid]);
            if ($password === '') {
                $stmt = $pdo->prepare("SELECT password_hash = '!INVITED' FROM users WHERE id = ?");
                $stmt->execute([$userId]);
                if ($stmt->fetchColumn()) $inviteCode = svc_team_issue_invite($pdo, $userId);
            }
        } else {
            $stmt = $pdo->prepare("SELECT id FROM users WHERE email = ?");
            $stmt->execute([$email]);
            $existingId = (int)($stmt->fetchColumn() ?: 0);

            if ($existingId) {
                $userId = $existingId;
                $stmt = $pdo->prepare("SELECT id FROM user_restaurants WHERE user_id = ? AND restaurant_id = ?");
                $stmt->execute([$userId, $rid]);
                if ($stmt->fetch()) {
                    throw new ServiceError('This user is already a member of this restaurant.', 'CONFLICT', 409, 'exists');
                }
                if ($password !== '') {
                    $pdo->prepare("UPDATE users SET first_name = ?, last_name = ?, phone = ?, password_hash = ? WHERE id = ?")
                        ->execute([$firstName, $lastName, $phone ?: null, password_hash($password, PASSWORD_DEFAULT), $userId]);
                }
            } else {
                $hash = $password !== '' ? password_hash($password, PASSWORD_DEFAULT) : '!INVITED';
                $stmt = $pdo->prepare(
                    "INSERT INTO users (first_name, last_name, email, phone, password_hash, is_platform_admin, is_active)
                     VALUES (?, ?, ?, ?, ?, 0, 1) RETURNING id"
                );
                $stmt->execute([$firstName, $lastName, $email, $phone ?: null, $hash]);
                $userId = (int)$stmt->fetchColumn();
                if ($password === '') $inviteCode = svc_team_issue_invite($pdo, $userId);
            }
            $pdo->prepare("INSERT INTO user_restaurants (user_id, restaurant_id, role, is_active) VALUES (?, ?, ?, 1)")
                ->execute([$userId, $rid, $role]);
        }
        $pdo->commit();
    } catch (Throwable $e) {
        $pdo->rollBack();
        throw $e;
    }
    $user = svc_team_get($rid, $userId);
    if ($inviteCode !== null) $user['invite_code'] = $inviteCode;
    return $user;
}

/** A new one-time invitation code for $userId, valid 14 days. Only its SHA-256 is stored. */
function svc_team_issue_invite(PDO $pdo, int $userId): string
{
    $alphabet = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789';
    $code = '';
    for ($i = 0; $i < 10; $i++) $code .= $alphabet[random_int(0, strlen($alphabet) - 1)];
    $pdo->prepare("UPDATE users SET invite_code_hash = ?, invite_expires_at = NOW() + INTERVAL '14 days' WHERE id = ?")
        ->execute([hash('sha256', $code), $userId]);
    return $code;
}

/** Activate/deactivate a membership ($active = null toggles). Cannot target yourself. */
function svc_team_set_active(int $rid, int $actorId, int $userId, ?bool $active = null): array
{
    if ($userId === $actorId) {
        throw new ServiceError('You cannot deactivate yourself.', 'CONFLICT', 409, 'self');
    }
    $stmt = db()->prepare("SELECT is_active FROM user_restaurants WHERE user_id = ? AND restaurant_id = ?");
    $stmt->execute([$userId, $rid]);
    $current = $stmt->fetchColumn();
    if ($current === false) {
        throw new ServiceError('User not found in this restaurant.', 'NOT_FOUND', 404, 'notfound');
    }
    $new = $active === null ? !$current : $active;
    if (!$new) svc_team_guard_last_admin($rid, $userId, 'This is the only active admin and cannot be deactivated.');

    db()->prepare("UPDATE user_restaurants SET is_active = ? WHERE user_id = ? AND restaurant_id = ?")
        ->execute([$new ? 1 : 0, $userId, $rid]);
    return svc_team_get($rid, $userId);
}

/* ---------- Restaurant phone numbers ---------- */

function svc_phone_list(int $rid): array
{
    $stmt = db()->prepare("SELECT * FROM restaurant_phone_numbers WHERE restaurant_id = ? ORDER BY is_primary DESC, created_at ASC");
    $stmt->execute([$rid]);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

/**
 * Create ($id = 0) or update a phone number.
 * Input: phone_number, label, language_code, language_name, is_primary.
 * Marking one primary clears the flag on the others.
 */
function svc_phone_save(int $rid, int $id, array $in): array
{
    $phone = trim($in['phone_number'] ?? '');
    if ($phone === '') svc_fail('Phone number is required.', 'phone_number');
    $label = trim($in['label'] ?? '') ?: null;
    $langCode = trim($in['language_code'] ?? '') ?: 'en';
    $langName = trim($in['language_name'] ?? '') ?: 'English';
    $isPrimary = !empty($in['is_primary']) ? 1 : 0;

    if ($id > 0) svc_row('restaurant_phone_numbers', $id, $rid, 'Phone number');

    $pdo = db();
    $pdo->beginTransaction();
    try {
        if ($isPrimary) {
            $pdo->prepare("UPDATE restaurant_phone_numbers SET is_primary = 0 WHERE restaurant_id = ? AND id != ?")
                ->execute([$rid, $id]);
        }
        if ($id > 0) {
            $pdo->prepare(
                "UPDATE restaurant_phone_numbers SET phone_number = ?, label = ?, language_code = ?, language_name = ?, is_primary = ?
                 WHERE id = ? AND restaurant_id = ?"
            )->execute([$phone, $label, $langCode, $langName, $isPrimary, $id, $rid]);
        } else {
            $stmt = $pdo->prepare(
                "INSERT INTO restaurant_phone_numbers (restaurant_id, phone_number, label, language_code, language_name, is_primary)
                 VALUES (?, ?, ?, ?, ?, ?) RETURNING id"
            );
            $stmt->execute([$rid, $phone, $label, $langCode, $langName, $isPrimary]);
            $id = (int)$stmt->fetchColumn();
        }
        $pdo->commit();
    } catch (Throwable $e) {
        $pdo->rollBack();
        throw $e;
    }
    return svc_row('restaurant_phone_numbers', $id, $rid, 'Phone number');
}

function svc_phone_delete(int $rid, int $id): void
{
    $stmt = db()->prepare("DELETE FROM restaurant_phone_numbers WHERE id = ? AND restaurant_id = ?");
    $stmt->execute([$id, $rid]);
    if ($stmt->rowCount() === 0) svc_not_found('Phone number');
}
