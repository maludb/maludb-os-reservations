<?php
require_once '../../../helpers/session.php';
require_once '../../../helpers/csrf.php';
require_once '../../../helpers/validation.php';
require_once '../../../models/User.php';

init_session();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Process forgot password request

    // Verify CSRF token
    if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
        http_response_code(403);
        echo '<div class="alert alert-danger" id="forgot-password-error-csrf">
                <i class="feather-alert-triangle-fill"></i>
                Invalid security token. Please refresh the page and try again.
              </div>';
        exit;
    }

    // Validate required fields
    if (empty($_POST['email'])) {
        echo '<div class="alert alert-danger" id="forgot-password-error-required">
                <i class="feather-alert-triangle-fill"></i>
                Please enter your email address.
              </div>';
        exit;
    }

    // Sanitize input
    $email = sanitize_input($_POST['email']);

    // Validate email format
    if (!validate_email($email)) {
        echo '<div class="alert alert-danger" id="forgot-password-error-email">
                <i class="feather-alert-triangle-fill"></i>
                Please enter a valid email address.
              </div>';
        exit;
    }

    // Generate reset token (always show success message for security)
    $userModel = new User();
    $token = $userModel->createPasswordResetToken($email);

    if ($token) {
        // In a real application, you would send an email here
        // For now, we'll log it and show a success message
        error_log("Password reset token for $email: $token");
        error_log("Reset URL: http://{$_SERVER['HTTP_HOST']}/reset-password.php?token=$token");
    }

    // Always show success message (don't reveal if email exists)
    echo '<div class="alert alert-success" id="forgot-password-success">
            <i class="feather-check-circle-fill"></i>
            <strong>Check your email!</strong><br>
            If an account exists with this email, you will receive password reset instructions.
            <br><small class="text-muted">The link will expire in 1 hour.</small>
          </div>';

    exit;

} else {
    // GET request - not supported
    http_response_code(405);
    echo '<div class="alert alert-warning" id="forgot-password-error-method">
            Invalid request method.
          </div>';
}
