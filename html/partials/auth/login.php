<?php
require_once '../../../helpers/session.php';
require_once '../../../helpers/csrf.php';
require_once '../../../helpers/validation.php';
require_once '../../../helpers/auth.php';

init_session();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
        http_response_code(403);
        echo '<div class="alert alert-danger" id="login-error-csrf">
                Invalid security token. Please refresh the page and try again.
              </div>';
        exit;
    }

    $required = ['email', 'password'];
    $missing = validate_required($required, $_POST);

    if (!empty($missing)) {
        echo '<div class="alert alert-danger" id="login-error-required">
                Please fill in all required fields.
              </div>';
        exit;
    }

    $email = sanitize_input($_POST['email']);
    $password = $_POST['password'];
    $remember = isset($_POST['remember']) && $_POST['remember'] === '1';

    if (!validate_email($email)) {
        echo '<div class="alert alert-danger" id="login-error-email">
                Please enter a valid email address.
              </div>';
        exit;
    }

    // Authenticate against restaurant_reservations.users table
    $stmt = db()->prepare("SELECT * FROM users WHERE email = ? AND is_active = 1");
    $stmt->execute([$email]);
    $user = $stmt->fetch();

    if (!$user || !password_verify($password, $user['password_hash'])) {
        error_log("Failed login attempt for email: $email");
        echo '<div class="alert alert-danger" id="login-error-credentials">
                Invalid email or password. Please try again.
              </div>';
        exit;
    }

    login_user($user, $remember);

    error_log("Successful login for user ID: {$user['id']}");

    header('HX-Redirect: /app.php');
    echo '<div class="alert alert-success" id="login-success">
            Login successful! Redirecting...
          </div>';
    exit;

} else {
    http_response_code(405);
    echo '<div class="alert alert-warning" id="login-error-method">
            Invalid request method.
          </div>';
}
