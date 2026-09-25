<?php
require_once '../helpers/session.php';
require_once '../helpers/csrf.php';
require_once '../models/User.php';

init_session();

// Redirect if already logged in
if (isset($_SESSION['user_id'])) {
    header('Location: /app.php');
    exit;
}

// Get token from URL
$token = $_GET['token'] ?? '';

if (empty($token)) {
    header('Location: /login.php');
    exit;
}

// Verify token is valid
$userModel = new User();
$resetData = $userModel->verifyPasswordResetToken($token);

if (!$resetData) {
    $errorMessage = 'Invalid or expired reset token. Please request a new password reset.';
}

$csrf_token = generate_csrf_token();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1" />
    <title>Reset Password - Task Tracker</title>

    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11/font/bootstrap-icons.css" rel="stylesheet">

    <!-- HTMX -->
    <script src="https://unpkg.com/htmx.org@2.0.8"></script>

    <style>
        body {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        .reset-container {
            max-width: 450px;
            width: 100%;
        }
        .reset-card {
            background: white;
            border-radius: 15px;
            box-shadow: 0 10px 40px rgba(0, 0, 0, 0.2);
            padding: 40px;
        }
        .reset-header {
            text-align: center;
            margin-bottom: 30px;
        }
        .reset-header h2 {
            color: #333;
            font-weight: 600;
            margin-bottom: 10px;
        }
        .reset-header p {
            color: #666;
            font-size: 14px;
        }
        .btn-primary {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            border: none;
            padding: 12px;
            font-weight: 500;
        }
        .btn-primary:hover {
            background: linear-gradient(135deg, #5568d3 0%, #6a3f8f 100%);
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(102, 126, 234, 0.4);
        }
        .form-control:focus {
            border-color: #667eea;
            box-shadow: 0 0 0 0.2rem rgba(102, 126, 234, 0.25);
        }
        .password-requirements {
            font-size: 12px;
            color: #666;
            margin-top: 5px;
        }
        .password-requirements li {
            margin-bottom: 2px;
        }
    </style>
</head>
<body>
    <div class="reset-container" id="reset-password-page-container">
        <div class="reset-card" id="reset-password-card">
            <div class="reset-header" id="reset-password-header">
                <h2><i class="bi bi-shield-lock-fill text-primary"></i> Reset Password</h2>
                <p>Enter your new password</p>
            </div>

            <!-- Messages will appear here -->
            <div id="reset-password-messages">
                <?php if (isset($errorMessage)): ?>
                    <div class="alert alert-danger" id="reset-password-error-token">
                        <i class="bi bi-exclamation-triangle-fill"></i>
                        <?php echo htmlspecialchars($errorMessage); ?>
                        <br><a href="/forgot-password.php" class="alert-link">Request a new reset link</a>
                    </div>
                <?php endif; ?>
            </div>

            <?php if (!isset($errorMessage)): ?>
            <!-- Reset Password Form -->
            <form id="reset-password-form" method="POST" action="/partials/auth/reset-password.php">
                <?php echo csrf_field(); ?>
                <input type="hidden" name="token" value="<?php echo htmlspecialchars($token); ?>">

                <div class="mb-3" id="password-field-group">
                    <label for="password" class="form-label">New Password</label>
                    <div class="input-group">
                        <span class="input-group-text"><i class="bi bi-lock"></i></span>
                        <input type="password"
                               class="form-control"
                               id="password"
                               name="password"
                               placeholder="Enter new password"
                               required
                               autofocus>
                    </div>
                    <div class="password-requirements" id="password-requirements">
                        <small>Password must contain:</small>
                        <ul class="mb-0 ps-3">
                            <li>At least 8 characters</li>
                            <li>One uppercase letter</li>
                            <li>One lowercase letter</li>
                            <li>One number</li>
                        </ul>
                    </div>
                </div>

                <div class="mb-3" id="confirm-password-field-group">
                    <label for="confirm_password" class="form-label">Confirm New Password</label>
                    <div class="input-group">
                        <span class="input-group-text"><i class="bi bi-lock-fill"></i></span>
                        <input type="password"
                               class="form-control"
                               id="confirm_password"
                               name="confirm_password"
                               placeholder="Re-enter new password"
                               required>
                    </div>
                </div>

                <div class="d-grid mb-3" id="submit-button-group">
                    <button type="submit" class="btn btn-primary btn-lg">
                        <span>Reset Password</span>
                        <span class="htmx-indicator spinner-border spinner-border-sm ms-2"></span>
                    </button>
                </div>
            </form>
            <?php endif; ?>

            <div class="text-center" id="reset-password-footer-links">
                <p class="mb-0">
                    <a href="/login.php" class="text-decoration-none">
                        <i class="bi bi-arrow-left"></i> Back to Sign In
                    </a>
                </p>
            </div>
        </div>
    </div>

    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
