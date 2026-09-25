<?php
require_once '../helpers/session.php';
require_once '../helpers/csrf.php';
require_once '../helpers/google-auth.php';

init_session();

// Redirect if already logged in
if (isset($_SESSION['user_id'])) {
    header('Location: /app.php');
    exit;
}

$csrf_token = generate_csrf_token();

$successMessage = $_SESSION['success_message'] ?? null;
if ($successMessage) {
    unset($_SESSION['success_message']);
}

// Google error messages from callback
$googleError = $_SESSION['google_error'] ?? null;
if ($googleError) { unset($_SESSION['google_error']); }

// Generate Google OAuth state for login
$googleOAuthState = bin2hex(random_bytes(16));
$_SESSION['google_oauth_state'] = $googleOAuthState;
$googleUrlLogin = google_auth_url(json_encode(['flow' => 'login', 'csrf' => $googleOAuthState]));
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1" />
    <title>Login - ZozoCal</title>

    <!-- Favicon -->
    <link rel="shortcut icon" type="image/x-icon" href="assets/images/favicon-vt.png" />

    <!-- Bootstrap CSS -->
    <link rel="stylesheet" type="text/css" href="assets/css/bootstrap.min.css" />
    <link rel="stylesheet" type="text/css" href="assets/vendor/kobie-vendors.min.css" />
    <link rel="stylesheet" type="text/css" href="assets/vendor/feather.min.css" />
    <link rel="stylesheet" type="text/css" href="assets/css/kobie-theme.min.css" />

    <!-- Google Fonts (match landing page) -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">

    <!-- HTMX -->
    <script src="https://unpkg.com/htmx.org@2.0.8"></script>

    <style>
        :root {
            --zc-primary: #667eea;
            --zc-secondary: #764ba2;
            --zc-gradient: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            --zc-dark: #1a1a2e;
        }

        body {
            font-family: 'Inter', sans-serif;
            background: linear-gradient(135deg, #f8f9ff 0%, #e8ecff 50%, #f0e6ff 100%);
            min-height: 100vh;
            position: relative;
            overflow-x: hidden;
        }
        body::before {
            content: '';
            position: absolute;
            top: -200px;
            right: -150px;
            width: 500px;
            height: 500px;
            background: radial-gradient(circle, rgba(102,126,234,0.08) 0%, transparent 70%);
            border-radius: 50%;
        }
        body::after {
            content: '';
            position: absolute;
            bottom: -150px;
            left: -100px;
            width: 400px;
            height: 400px;
            background: radial-gradient(circle, rgba(118,75,162,0.06) 0%, transparent 70%);
            border-radius: 50%;
        }
        .auth-minimal-wrapper {
            display: flex;
            align-items: center;
            justify-content: center;
            min-height: 100vh;
            padding: 20px 0;
            position: relative;
            z-index: 1;
        }
        .auth-minimal-inner {
            width: 100%;
            max-width: 460px;
        }
        .minimal-card-wrapper {
            width: 100%;
        }
        .login-brand {
            font-size: 28px;
            font-weight: 800;
            background: var(--zc-gradient);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
            text-decoration: none;
            display: block;
            text-align: center;
            margin-bottom: 32px;
        }
        .card {
            border: none;
            border-radius: 16px;
            box-shadow: 0 20px 60px rgba(102,126,234,0.12);
            overflow: visible;
        }
        .card-body {
            padding: 40px !important;
        }
        .form-control {
            border: 1px solid #e5e7eb;
            border-radius: 10px;
            padding: 12px 16px;
            font-size: 15px;
            background: #f8f9ff;
            transition: all 0.3s;
        }
        .form-control:focus {
            border-color: var(--zc-primary);
            box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.15);
            background: #fff;
        }
        .form-check-input:checked {
            background-color: var(--zc-primary);
            border-color: var(--zc-primary);
        }
        .btn-primary {
            background: var(--zc-gradient);
            border: none;
            border-radius: 10px;
            padding: 12px;
            font-weight: 600;
            font-size: 15px;
            transition: all 0.3s;
        }
        .btn-primary:hover {
            background: linear-gradient(135deg, #5568d3 0%, #6a3f8f 100%);
            transform: translateY(-2px);
            box-shadow: 0 8px 25px rgba(102, 126, 234, 0.4);
        }
        .login-footer-text {
            text-align: center;
            margin-top: 24px;
            font-size: 13px;
            color: #9ca3af;
        }
        .login-footer-text a {
            color: var(--zc-primary);
            text-decoration: none;
            font-weight: 600;
        }
        .login-footer-text a:hover {
            text-decoration: underline;
        }
        .btn-google {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
            width: 100%;
            padding: 12px;
            border: 1px solid #e5e7eb;
            border-radius: 10px;
            background: #fff;
            font-size: 15px;
            font-weight: 600;
            color: var(--zc-dark);
            cursor: pointer;
            transition: all 0.3s;
            text-decoration: none;
        }
        .btn-google:hover {
            background: #f8f9ff;
            border-color: var(--zc-primary);
            box-shadow: 0 4px 12px rgba(102,126,234,0.15);
            transform: translateY(-1px);
            color: var(--zc-dark);
        }
        .btn-google svg { flex-shrink: 0; }
        .auth-divider {
            display: flex;
            align-items: center;
            margin: 20px 0;
            color: #9ca3af;
            font-size: 13px;
        }
        .auth-divider::before,
        .auth-divider::after {
            content: '';
            flex: 1;
            height: 1px;
            background: #e5e7eb;
        }
        .auth-divider span {
            padding: 0 12px;
        }
    </style>
</head>
<body>
    <main class="auth-minimal-wrapper" id="login-wrapper">
        <div class="auth-minimal-inner" id="login-inner">
            <div class="minimal-card-wrapper" id="login-card-wrapper">
                <a href="/" class="login-brand" id="login-brand">ZozoCal</a>

                <div class="card mx-3 mx-sm-0" id="login-card">
                    <div class="card-body" id="login-card-body">
                        <!-- Header -->
                        <h2 style="font-size:22px;font-weight:700;color:var(--zc-dark);margin-bottom:4px;" id="login-title">Welcome back</h2>
                        <p style="font-size:14px;color:#9ca3af;margin-bottom:24px;" id="login-subtitle">Sign in to your account</p>

                        <!-- Messages -->
                        <div id="login-messages">
                            <?php if ($googleError): ?>
                                <div class="alert alert-danger alert-dismissible fade show" role="alert" id="login-google-error">
                                    <?php echo $googleError; ?>
                                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                                </div>
                            <?php endif; ?>
                            <?php if ($successMessage): ?>
                                <div class="alert alert-success alert-dismissible fade show" role="alert" id="login-success-msg">
                                    <?php echo htmlspecialchars($successMessage); ?>
                                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                                </div>
                            <?php endif; ?>
                        </div>

                        <!-- Google Sign-In Button -->
                        <a href="<?php echo htmlspecialchars($googleUrlLogin); ?>" class="btn-google" id="login-btn-google">
                            <svg width="20" height="20" viewBox="0 0 48 48"><path fill="#EA4335" d="M24 9.5c3.54 0 6.71 1.22 9.21 3.6l6.85-6.85C35.9 2.38 30.47 0 24 0 14.62 0 6.51 5.38 2.56 13.22l7.98 6.19C12.43 13.72 17.74 9.5 24 9.5z"/><path fill="#4285F4" d="M46.98 24.55c0-1.57-.15-3.09-.38-4.55H24v9.02h12.94c-.58 2.96-2.26 5.48-4.78 7.18l7.73 6c4.51-4.18 7.09-10.36 7.09-17.65z"/><path fill="#FBBC05" d="M10.53 28.59a14.5 14.5 0 0 1 0-9.18l-7.98-6.19a24.0 24.0 0 0 0 0 21.56l7.98-6.19z"/><path fill="#34A853" d="M24 48c6.48 0 11.93-2.13 15.89-5.81l-7.73-6c-2.15 1.45-4.92 2.3-8.16 2.3-6.26 0-11.57-4.22-13.47-9.91l-7.98 6.19C6.51 42.62 14.62 48 24 48z"/></svg>
                            Sign in with Google
                        </a>

                        <div class="auth-divider" id="login-divider"><span>or</span></div>

                        <!-- Login Form -->
                        <form id="login-form"
                              class="w-100"
                              hx-post="/partials/auth/login.php"
                              hx-target="#login-messages"
                              hx-swap="innerHTML">
                            <?php echo csrf_field(); ?>

                            <div class="mb-3" id="login-email-group">
                                <label class="form-label" style="font-size:13px;font-weight:600;color:var(--zc-dark);" for="login-email">Email</label>
                                <input type="email"
                                       class="form-control"
                                       id="login-email"
                                       name="email"
                                       placeholder="you@restaurant.com"
                                       required
                                       autofocus>
                            </div>

                            <div class="mb-3" id="login-password-group">
                                <label class="form-label" style="font-size:13px;font-weight:600;color:var(--zc-dark);" for="login-password">Password</label>
                                <input type="password"
                                       class="form-control"
                                       id="login-password"
                                       name="password"
                                       placeholder="Enter your password"
                                       required>
                            </div>

                            <div class="d-flex align-items-center justify-content-between mb-4" id="login-options-row">
                                <div id="login-remember-container">
                                    <div class="form-check">
                                        <input type="checkbox"
                                               class="form-check-input"
                                               id="login-remember"
                                               name="remember"
                                               value="1">
                                        <label class="form-check-label" style="font-size:13px;" for="login-remember">Remember Me</label>
                                    </div>
                                </div>
                            </div>

                            <div id="login-submit-container">
                                <button type="submit" class="btn btn-lg btn-primary w-100">
                                    Sign In
                                    <span class="htmx-indicator spinner-border spinner-border-sm ms-2"></span>
                                </button>
                            </div>
                        </form>
                    </div>
                </div>

                <div class="login-footer-text" id="login-footer-text">
                    <a href="/">&#8592; Back to ZozoCal</a>
                </div>
            </div>
        </div>
    </main>

    <!-- Bootstrap JS -->
    <script src="assets/vendor/jquery.min.js"></script>
    <script src="assets/vendor/bootstrap.min.js"></script>
</body>
</html>
