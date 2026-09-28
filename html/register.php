<?php
require_once '../helpers/session.php';
require_once '../helpers/csrf.php';
require_once '../helpers/google-auth.php';

init_session();

if (isset($_SESSION['user_id'])) {
    header('Location: /app.php');
    exit;
}

$csrf_token = generate_csrf_token();

// Google error messages from callback
$googleError = $_SESSION['google_error'] ?? null;
if ($googleError) { unset($_SESSION['google_error']); }

// Google pending registration (need company name)
$googlePending = $_SESSION['google_pending'] ?? null;

// Generate a single Google OAuth state token (shared across both buttons — only one is clicked)
$googleOAuthState = bin2hex(random_bytes(16));
$_SESSION['google_oauth_state'] = $googleOAuthState;
$googleUrlCreate = google_auth_url(json_encode(['flow' => 'create', 'csrf' => $googleOAuthState]));
$googleUrlInvite = google_auth_url(json_encode(['flow' => 'invite', 'csrf' => $googleOAuthState]));
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1" />
    <title>Get Started - ZozoCal</title>

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
            padding: 40px 0;
            position: relative;
            z-index: 1;
        }
        .auth-minimal-inner {
            width: 100%;
            max-width: 500px;
        }
        .register-brand {
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
        .form-label {
            font-size: 13px;
            font-weight: 600;
            color: var(--zc-dark);
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
        .register-footer-text {
            text-align: center;
            margin-top: 24px;
            font-size: 13px;
            color: #9ca3af;
        }
        .register-footer-text a {
            color: var(--zc-primary);
            text-decoration: none;
            font-weight: 600;
        }
        .register-footer-text a:hover {
            text-decoration: underline;
        }
        .password-hint {
            font-size: 12px;
            color: #9ca3af;
            margin-top: 4px;
        }
        .tab-toggle {
            display: flex;
            background: #f0f1f5;
            border-radius: 10px;
            padding: 4px;
            margin-bottom: 28px;
        }
        .tab-toggle button {
            flex: 1;
            border: none;
            background: none;
            padding: 10px 16px;
            border-radius: 8px;
            font-size: 14px;
            font-weight: 600;
            color: var(--zc-dark);
            cursor: pointer;
            transition: all 0.2s;
        }
        .tab-toggle button.active {
            background: #fff;
            box-shadow: 0 2px 8px rgba(0,0,0,0.08);
            color: var(--zc-primary);
        }
        .tab-panel { display: none; }
        .tab-panel.active { display: block; }
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
    <main class="auth-minimal-wrapper" id="register-wrapper">
        <div class="auth-minimal-inner" id="register-inner">
            <div id="register-card-wrapper">
                <a href="/" class="register-brand" id="register-brand">ZozoCal</a>

                <div class="card mx-3 mx-sm-0" id="register-card">
                    <div class="card-body" id="register-card-body">

                        <!-- Tab Toggle -->
                        <div class="tab-toggle" id="register-tab-toggle">
                            <button type="button" class="active" id="register-tab-btn-create" onclick="switchTab('create')">Create Account</button>
                            <button type="button" id="register-tab-btn-invite" onclick="switchTab('invite')">Accept Invitation</button>
                        </div>

                        <!-- ═══ CREATE ACCOUNT TAB ═══ -->
                        <div class="tab-panel active" id="register-panel-create">
                            <h2 style="font-size:22px;font-weight:700;color:var(--zc-dark);margin-bottom:4px;" id="register-title">Create your account</h2>
                            <p style="font-size:14px;color:#9ca3af;margin-bottom:24px;" id="register-subtitle">Set up your restaurant's reservation system</p>

                            <?php if ($googleError): ?>
                                <div class="alert alert-danger" id="register-google-error"><?php echo $googleError; ?></div>
                            <?php endif; ?>

                            <div id="register-messages"></div>

                            <?php if ($googlePending): ?>
                            <!-- Google registration: just need company name -->
                            <div id="register-google-complete">
                                <div class="alert alert-info" id="register-google-info">
                                    Signed in as <strong><?php echo htmlspecialchars($googlePending['email']); ?></strong>. Enter your company name to finish.
                                </div>
                                <form id="register-google-form"
                                      class="w-100"
                                      hx-post="/partials/auth/google-complete.php"
                                      hx-target="#register-messages"
                                      hx-swap="innerHTML">
                                    <?php echo csrf_field(); ?>
                                    <div class="mb-3" id="register-google-company-group">
                                        <label class="form-label" for="register-google-company">Restaurant Name</label>
                                        <input type="text" class="form-control" id="register-google-company"
                                               name="restaurant_name" placeholder="Your Restaurant Name" required autofocus>
                                    </div>
                                    <button type="submit" class="btn btn-lg btn-primary w-100">
                                        Complete Registration
                                        <span class="htmx-indicator spinner-border spinner-border-sm ms-2"></span>
                                    </button>
                                </form>
                            </div>
                            <?php else: ?>

                            <!-- Google Sign-In Button -->
                            <a href="<?php echo htmlspecialchars($googleUrlCreate); ?>" class="btn-google" id="register-btn-google"
                               onclick="sessionStorage.setItem('google_state','create');">
                                <svg width="20" height="20" viewBox="0 0 48 48"><path fill="#EA4335" d="M24 9.5c3.54 0 6.71 1.22 9.21 3.6l6.85-6.85C35.9 2.38 30.47 0 24 0 14.62 0 6.51 5.38 2.56 13.22l7.98 6.19C12.43 13.72 17.74 9.5 24 9.5z"/><path fill="#4285F4" d="M46.98 24.55c0-1.57-.15-3.09-.38-4.55H24v9.02h12.94c-.58 2.96-2.26 5.48-4.78 7.18l7.73 6c4.51-4.18 7.09-10.36 7.09-17.65z"/><path fill="#FBBC05" d="M10.53 28.59a14.5 14.5 0 0 1 0-9.18l-7.98-6.19a24.0 24.0 0 0 0 0 21.56l7.98-6.19z"/><path fill="#34A853" d="M24 48c6.48 0 11.93-2.13 15.89-5.81l-7.73-6c-2.15 1.45-4.92 2.3-8.16 2.3-6.26 0-11.57-4.22-13.47-9.91l-7.98 6.19C6.51 42.62 14.62 48 24 48z"/></svg>
                                Sign in with Google
                            </a>

                            <div class="auth-divider" id="register-divider"><span>or</span></div>

                            <?php endif; ?>

                            <?php if (!$googlePending): ?>
                            <form id="register-form"
                                  class="w-100"
                                  hx-post="/partials/auth/register.php"
                                  hx-target="#register-messages"
                                  hx-swap="innerHTML">
                                <?php echo csrf_field(); ?>

                                <div class="row" id="register-name-row">
                                    <div class="col-6 mb-3" id="register-first-name-group">
                                        <label class="form-label" for="register-first-name">First Name</label>
                                        <input type="text" class="form-control" id="register-first-name"
                                               name="first_name" placeholder="Jane" required autofocus>
                                    </div>
                                    <div class="col-6 mb-3" id="register-last-name-group">
                                        <label class="form-label" for="register-last-name">Last Name</label>
                                        <input type="text" class="form-control" id="register-last-name"
                                               name="last_name" placeholder="Smith" required>
                                    </div>
                                </div>

                                <div class="mb-3" id="register-restaurant-group">
                                    <label class="form-label" for="register-restaurant">Restaurant Name</label>
                                    <input type="text" class="form-control" id="register-restaurant"
                                           name="restaurant_name" placeholder="Your Restaurant Name" required>
                                </div>

                                <div class="mb-3" id="register-email-group">
                                    <label class="form-label" for="register-email">Email</label>
                                    <input type="email" class="form-control" id="register-email"
                                           name="email" placeholder="you@restaurant.com" required>
                                </div>

                                <div class="mb-3" id="register-password-group">
                                    <label class="form-label" for="register-password">Password</label>
                                    <input type="password" class="form-control" id="register-password"
                                           name="password" placeholder="Create a password" required>
                                    <div class="password-hint" id="register-password-hint">Min 8 characters, with uppercase, lowercase, and a number</div>
                                </div>

                                <div class="mb-4" id="register-confirm-group">
                                    <label class="form-label" for="register-confirm">Confirm Password</label>
                                    <input type="password" class="form-control" id="register-confirm"
                                           name="confirm_password" placeholder="Confirm your password" required>
                                </div>

                                <div id="register-submit-container">
                                    <button type="submit" class="btn btn-lg btn-primary w-100">
                                        Create Account
                                        <span class="htmx-indicator spinner-border spinner-border-sm ms-2"></span>
                                    </button>
                                </div>
                            </form>
                            <?php endif; ?>
                        </div>

                        <!-- ═══ ACCEPT INVITATION TAB ═══ -->
                        <div class="tab-panel" id="register-panel-invite">
                            <h2 style="font-size:22px;font-weight:700;color:var(--zc-dark);margin-bottom:4px;" id="invite-title">Accept Invitation</h2>
                            <p style="font-size:14px;color:#9ca3af;margin-bottom:24px;" id="invite-subtitle">Your admin has created an account for you. Set your password to get started.</p>

                            <div id="invite-messages"></div>

                            <!-- Google Sign-In for Invitation -->
                            <a href="<?php echo htmlspecialchars($googleUrlInvite); ?>" class="btn-google" id="invite-btn-google"
                               onclick="sessionStorage.setItem('google_state','invite');">
                                <svg width="20" height="20" viewBox="0 0 48 48"><path fill="#EA4335" d="M24 9.5c3.54 0 6.71 1.22 9.21 3.6l6.85-6.85C35.9 2.38 30.47 0 24 0 14.62 0 6.51 5.38 2.56 13.22l7.98 6.19C12.43 13.72 17.74 9.5 24 9.5z"/><path fill="#4285F4" d="M46.98 24.55c0-1.57-.15-3.09-.38-4.55H24v9.02h12.94c-.58 2.96-2.26 5.48-4.78 7.18l7.73 6c4.51-4.18 7.09-10.36 7.09-17.65z"/><path fill="#FBBC05" d="M10.53 28.59a14.5 14.5 0 0 1 0-9.18l-7.98-6.19a24.0 24.0 0 0 0 0 21.56l7.98-6.19z"/><path fill="#34A853" d="M24 48c6.48 0 11.93-2.13 15.89-5.81l-7.73-6c-2.15 1.45-4.92 2.3-8.16 2.3-6.26 0-11.57-4.22-13.47-9.91l-7.98 6.19C6.51 42.62 14.62 48 24 48z"/></svg>
                                Accept with Google
                            </a>

                            <div class="auth-divider" id="invite-divider"><span>or</span></div>

                            <form id="invite-form"
                                  class="w-100"
                                  hx-post="/partials/auth/accept-invite.php"
                                  hx-target="#invite-messages"
                                  hx-swap="innerHTML">
                                <?php echo csrf_field(); ?>

                                <div class="mb-3" id="invite-email-group">
                                    <label class="form-label" for="invite-email">Email</label>
                                    <input type="email" class="form-control" id="invite-email"
                                           name="email" placeholder="your-email@restaurant.com" required>
                                    <div class="password-hint" id="invite-email-hint">Enter the email your admin used when adding you</div>
                                </div>

                                <div class="mb-3" id="invite-code-group">
                                    <label class="form-label" for="invite-code">Invitation Code</label>
                                    <input type="text" class="form-control" id="invite-code"
                                           name="invite_code" placeholder="The code your admin gave you" autocomplete="off" required>
                                </div>

                                <div class="mb-3" id="invite-password-group">
                                    <label class="form-label" for="invite-password">Create Password</label>
                                    <input type="password" class="form-control" id="invite-password"
                                           name="password" placeholder="Create a password" required>
                                    <div class="password-hint" id="invite-password-hint">Min 8 characters, with uppercase, lowercase, and a number</div>
                                </div>

                                <div class="mb-4" id="invite-confirm-group">
                                    <label class="form-label" for="invite-confirm">Confirm Password</label>
                                    <input type="password" class="form-control" id="invite-confirm"
                                           name="confirm_password" placeholder="Confirm your password" required>
                                </div>

                                <div id="invite-submit-container">
                                    <button type="submit" class="btn btn-lg btn-primary w-100">
                                        Accept Invitation
                                        <span class="htmx-indicator spinner-border spinner-border-sm ms-2"></span>
                                    </button>
                                </div>
                            </form>
                        </div>

                    </div>
                </div>

                <div class="register-footer-text" id="register-footer-text">
                    Already have an account? <a href="/login.php">Sign in</a>
                    <br><a href="/" style="font-weight:400;">&#8592; Back to ZozoCal</a>
                </div>
            </div>
        </div>
    </main>

    <!-- Bootstrap JS -->
    <script src="assets/vendor/jquery.min.js"></script>
    <script src="assets/vendor/bootstrap.min.js"></script>

    <script>
    function switchTab(tab) {
        document.querySelectorAll('.tab-toggle button').forEach(function(b) { b.classList.remove('active'); });
        document.querySelectorAll('.tab-panel').forEach(function(p) { p.classList.remove('active'); });
        document.getElementById('register-tab-btn-' + tab).classList.add('active');
        document.getElementById('register-panel-' + tab).classList.add('active');
    }
    // Auto-switch to invite tab if ?invite is in URL
    if (window.location.search.indexOf('invite') !== -1) {
        switchTab('invite');
    }
    </script>
</body>
</html>
