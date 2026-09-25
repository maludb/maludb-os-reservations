<?php
require_once '../helpers/session.php';
require_once '../helpers/csrf.php';

init_session();

// Redirect if already logged in
if (isset($_SESSION['user_id'])) {
    header('Location: /app.php');
    exit;
}

$csrf_token = generate_csrf_token();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1" />
    <title>Forgot Password - Task Tracker</title>

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
        .forgot-container {
            max-width: 450px;
            width: 100%;
        }
        .forgot-card {
            background: white;
            border-radius: 15px;
            box-shadow: 0 10px 40px rgba(0, 0, 0, 0.2);
            padding: 40px;
        }
        .forgot-header {
            text-align: center;
            margin-bottom: 30px;
        }
        .forgot-header h2 {
            color: #333;
            font-weight: 600;
            margin-bottom: 10px;
        }
        .forgot-header p {
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
        .info-box {
            background: #f8f9fa;
            border-left: 4px solid #667eea;
            padding: 15px;
            margin-bottom: 20px;
            border-radius: 5px;
        }
    </style>
</head>
<body>
    <div class="forgot-container" id="forgot-password-page-container">
        <div class="forgot-card" id="forgot-password-card">
            <div class="forgot-header" id="forgot-password-header">
                <h2><i class="bi bi-key-fill text-primary"></i> Forgot Password</h2>
                <p>Enter your email to reset your password</p>
            </div>

            <div class="info-box" id="forgot-password-info">
                <small>
                    <i class="bi bi-info-circle"></i>
                    We'll send you a password reset link that expires in 1 hour.
                </small>
            </div>

            <!-- Messages will appear here -->
            <div id="forgot-password-messages"></div>

            <!-- Forgot Password Form -->
            <form id="forgot-password-form" hx-post="/partials/auth/forgot-password.php" hx-target="#forgot-password-messages" hx-swap="innerHTML">
                <?php echo csrf_field(); ?>

                <div class="mb-3" id="email-field-group">
                    <label for="email" class="form-label">Email Address</label>
                    <div class="input-group">
                        <span class="input-group-text"><i class="bi bi-envelope"></i></span>
                        <input type="email"
                               class="form-control"
                               id="email"
                               name="email"
                               placeholder="Enter your email"
                               required
                               autofocus>
                    </div>
                </div>

                <div class="d-grid mb-3" id="submit-button-group">
                    <button type="submit" class="btn btn-primary btn-lg">
                        <span>Send Reset Link</span>
                        <span class="htmx-indicator spinner-border spinner-border-sm ms-2"></span>
                    </button>
                </div>
            </form>

            <div class="text-center" id="forgot-password-footer-links">
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
