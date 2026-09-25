<?php
require_once '../../../helpers/session.php';
require_once '../../../helpers/csrf.php';
require_once '../../../helpers/validation.php';
require_once '../../../models/User.php';

init_session();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Process password reset

    // Verify CSRF token
    if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
        http_response_code(403);
        echo '<div class="alert alert-danger" id="reset-password-error-csrf">
                <i class="feather-alert-triangle-fill"></i>
                Invalid security token. Please refresh the page and try again.
              </div>';
        exit;
    }

    // Validate required fields
    $required = ['token', 'password', 'confirm_password'];
    $missing = validate_required($required, $_POST);

    if (!empty($missing)) {
        echo '<div class="alert alert-danger" id="reset-password-error-required">
                <i class="feather-alert-triangle-fill"></i>
                Please fill in all required fields.
              </div>';
        exit;
    }

    $token = $_POST['token'];
    $password = $_POST['password'];
    $confirmPassword = $_POST['confirm_password'];

    // Check if passwords match
    if ($password !== $confirmPassword) {
        echo '<div class="alert alert-danger" id="reset-password-error-match">
                <i class="feather-alert-triangle-fill"></i>
                Passwords do not match. Please try again.
              </div>';
        exit;
    }

    // Validate password strength
    $passwordValidation = validate_password($password);
    if (!$passwordValidation['valid']) {
        echo '<div class="alert alert-danger" id="reset-password-error-strength">
                <i class="feather-alert-triangle-fill"></i>
                ' . htmlspecialchars($passwordValidation['message']) . '
              </div>';
        exit;
    }

    // Reset password
    $userModel = new User();
    $result = $userModel->resetPassword($token, $password);

    if (!$result) {
        echo '<div class="alert alert-danger" id="reset-password-error-failed">
                <i class="feather-alert-triangle-fill"></i>
                Invalid or expired reset token. Please request a new password reset.
                <br><a href="/forgot-password.php" class="alert-link">Request a new reset link</a>
              </div>';
        exit;
    }

    // Log password reset
    error_log("Password reset successful for token: $token");

    // Success - redirect to login with a success message
    $_SESSION['success_message'] = 'Password reset successfully! Please log in with your new password.';
    header('Location: /login.php');
    exit;

} else {
    // GET request - not supported
    http_response_code(405);
    echo '<div class="alert alert-warning" id="reset-password-error-method">
            Invalid request method.
          </div>';
}
