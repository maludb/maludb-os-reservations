<?php
/**
 * User Model
 *
 * Handles all database operations related to users and authentication
 */

require_once __DIR__ . '/../config/database.php';

class User {
    private $db;

    public function __construct() {
        $this->db = Database::getInstance()->getConnection();
    }

    /**
     * Create a new user (legacy, does not handle orgs)
     */
    public function create($data) {
        try {
            $hashedPassword = password_hash($data['password'], PASSWORD_DEFAULT);
            $username = $data['username'] ?? explode('@', $data['email'])[0];

            $stmt = $this->db->prepare("
                INSERT INTO users (email, password, first_name, last_name, username, role, status, created_at)
                VALUES (?, ?, ?, ?, ?, 'user', 'active', NOW())
            ");

            $result = $stmt->execute([
                $data['email'],
                $hashedPassword,
                $data['first_name'] ?? null,
                $data['last_name'] ?? null,
                $username
            ]);

            return $result ? $this->db->lastInsertId() : false;
        } catch (PDOException $e) {
            error_log("User creation error: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Find user by email with org context
     */
    public static function findByEmail(string $email): ?array {
        try {
            $db = Database::getInstance()->getConnection();
            $stmt = $db->prepare("SELECT u.*, o.name as org_name FROM users u LEFT JOIN orgs o ON u.org_id = o.id WHERE u.email = :email AND u.status = 'active'");
            $stmt->execute(['email' => $email]);
            $user = $stmt->fetch();
            return $user ?: null;
        } catch (PDOException $e) {
            error_log("User lookup error: " . $e->getMessage());
            return null;
        }
    }

    /**
     * Find user by ID with org context
     */
    public static function findById(int $id): ?array {
        try {
            $db = Database::getInstance()->getConnection();
            $stmt = $db->prepare("SELECT u.*, o.name as org_name FROM users u LEFT JOIN orgs o ON u.org_id = o.id WHERE u.id = :id");
            $stmt->execute(['id' => $id]);
            $user = $stmt->fetch();
            return $user ?: null;
        } catch (PDOException $e) {
            error_log("User lookup error: " . $e->getMessage());
            return null;
        }
    }

    /**
     * Verify user credentials
     */
    public function verifyPassword($email, $password) {
        $user = self::findByEmail($email);

        if (!$user) {
            return false;
        }

        if ($user['status'] !== 'active') {
            return false;
        }

        if (password_verify($password, $user['password'])) {
            unset($user['password']);
            return $user;
        }

        return false;
    }

    /**
     * Update remember me token
     */
    public function updateRememberToken($userId, $token) {
        try {
            $stmt = $this->db->prepare("UPDATE users SET remember_token = ? WHERE id = ?");
            return $stmt->execute([$token, $userId]);
        } catch (PDOException $e) {
            error_log("Remember token update error: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Create password reset token
     */
    public function createPasswordResetToken($email) {
        try {
            $user = self::findByEmail($email);
            if (!$user) {
                return false;
            }

            $token = bin2hex(random_bytes(32));
            $expiresAt = date('Y-m-d H:i:s', time() + 3600);

            $stmt = $this->db->prepare("INSERT INTO password_resets (email, token, created_at, expires_at) VALUES (?, ?, NOW(), ?)");
            $result = $stmt->execute([$email, $token, $expiresAt]);

            return $result ? $token : false;
        } catch (PDOException $e) {
            error_log("Password reset token creation error: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Verify password reset token
     */
    public function verifyPasswordResetToken($token) {
        try {
            $stmt = $this->db->prepare("SELECT id, email, token, expires_at, used_at FROM password_resets WHERE token = ? AND expires_at > NOW() AND used_at IS NULL ORDER BY created_at DESC LIMIT 1");
            $stmt->execute([$token]);
            $resetData = $stmt->fetch();
            return $resetData ?: false;
        } catch (PDOException $e) {
            error_log("Password reset token verification error: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Reset password using token
     */
    public function resetPassword($token, $newPassword) {
        try {
            $resetData = $this->verifyPasswordResetToken($token);
            if (!$resetData) {
                return false;
            }

            $this->db->beginTransaction();

            $hashedPassword = password_hash($newPassword, PASSWORD_DEFAULT);
            $stmt = $this->db->prepare("UPDATE users SET password = ?, updated_at = NOW() WHERE email = ?");
            $stmt->execute([$hashedPassword, $resetData['email']]);

            $stmt = $this->db->prepare("UPDATE password_resets SET used_at = NOW() WHERE id = ?");
            $stmt->execute([$resetData['id']]);

            $this->db->commit();
            return true;
        } catch (PDOException $e) {
            if ($this->db->inTransaction()) {
                $this->db->rollBack();
            }
            error_log("Password reset error: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Check if email exists
     */
    public function emailExists($email) {
        try {
            $stmt = $this->db->prepare("SELECT COUNT(*) FROM users WHERE email = ?");
            $stmt->execute([$email]);
            return $stmt->fetchColumn() > 0;
        } catch (PDOException $e) {
            error_log("Email check error: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Get all active users
     */
    public function getAllUsers() {
        try {
            $stmt = $this->db->prepare("SELECT id, first_name, last_name, email, username, avatar, status FROM users WHERE status = 'active' ORDER BY first_name ASC, last_name ASC");
            $stmt->execute();
            return $stmt->fetchAll();
        } catch (PDOException $e) {
            error_log("Get all users error: " . $e->getMessage());
            return [];
        }
    }

    /**
     * Create default team for new user (legacy)
     */
    public function createDefaultTeam($userId) {
        try {
            $this->db->beginTransaction();

            $stmt = $this->db->prepare("INSERT INTO teams (name, created_by, created_at) VALUES (?, ?, NOW())");
            $stmt->execute(['My Team', $userId]);
            $teamId = $this->db->lastInsertId();

            $stmt = $this->db->prepare("INSERT INTO team_members (team_id, user_id, role, joined_at) VALUES (?, ?, 'owner', NOW())");
            $stmt->execute([$teamId, $userId]);

            $this->db->commit();
            return true;
        } catch (PDOException $e) {
            if ($this->db->inTransaction()) {
                $this->db->rollBack();
            }
            error_log("Default team creation error: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Create a new org and user with admin role
     */
    public static function createWithOrg(array $userData, string $companyName): int {
        $db = Database::getInstance()->getConnection();
        $db->beginTransaction();
        try {
            $stmt = $db->prepare("INSERT INTO users (email, password, first_name, last_name, username, role, status) VALUES (:email, :password, :first_name, :last_name, :username, 'admin', 'active')");
            $stmt->execute([
                'email' => $userData['email'],
                'password' => password_hash($userData['password'], PASSWORD_DEFAULT),
                'first_name' => $userData['first_name'],
                'last_name' => $userData['last_name'],
                'username' => $userData['username'] ?? $userData['email']
            ]);
            $userId = (int)$db->lastInsertId();

            $stmt = $db->prepare("INSERT INTO orgs (name, created_by) VALUES (:name, :created_by)");
            $stmt->execute(['name' => $companyName, 'created_by' => $userId]);
            $orgId = (int)$db->lastInsertId();

            $stmt = $db->prepare("UPDATE users SET org_id = :org_id WHERE id = :id");
            $stmt->execute(['org_id' => $orgId, 'id' => $userId]);

            $stmt = $db->prepare("INSERT INTO org_members (team_id, user_id, role) VALUES (:team_id, :user_id, 'admin')");
            $stmt->execute(['team_id' => $orgId, 'user_id' => $userId]);

            $db->commit();
            return $userId;
        } catch (\Exception $e) {
            $db->rollBack();
            throw $e;
        }
    }

    /**
     * Create a new user within an existing org
     */
    public static function createForOrg(array $userData, int $orgId): int {
        $db = Database::getInstance()->getConnection();
        $db->beginTransaction();
        try {
            $stmt = $db->prepare("INSERT INTO users (email, password, first_name, last_name, username, role, status, org_id) VALUES (:email, :password, :first_name, :last_name, :username, 'user', 'active', :org_id)");
            $stmt->execute([
                'email' => $userData['email'],
                'password' => password_hash($userData['password'], PASSWORD_DEFAULT),
                'first_name' => $userData['first_name'],
                'last_name' => $userData['last_name'],
                'username' => $userData['username'] ?? $userData['email'],
                'org_id' => $orgId
            ]);
            $userId = (int)$db->lastInsertId();

            $stmt = $db->prepare("INSERT INTO org_members (team_id, user_id, role) VALUES (:team_id, :user_id, 'member')");
            $stmt->execute(['team_id' => $orgId, 'user_id' => $userId]);

            $db->commit();
            return $userId;
        } catch (\Exception $e) {
            $db->rollBack();
            throw $e;
        }
    }

    /**
     * Find an org by invite code/slug (currently matches name)
     */
    public static function findOrgByInviteCode(string $code): ?array {
        try {
            $db = Database::getInstance()->getConnection();
            $stmt = $db->prepare("SELECT id, name FROM orgs WHERE name = :code LIMIT 1");
            $stmt->execute(['code' => $code]);
            $org = $stmt->fetch();
            return $org ?: null;
        } catch (PDOException $e) {
            error_log("Org lookup error: " . $e->getMessage());
            return null;
        }
    }
}
