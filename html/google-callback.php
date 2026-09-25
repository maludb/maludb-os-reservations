<?php
/**
 * Google OAuth 2.0 Callback Handler
 *
 * Handles both flows:
 *   - "create"  → Register new account via Google
 *   - "invite"  → Accept invitation via Google
 *   - "login"   → Sign in with Google
 */

require_once '../helpers/session.php';
require_once '../helpers/db.php';
require_once '../helpers/auth.php';
require_once '../helpers/google-auth.php';

init_session();

// --- Validate callback parameters ---
$code  = $_GET['code'] ?? '';
$state = $_GET['state'] ?? '';
$error = $_GET['error'] ?? '';

if ($error) {
    $_SESSION['google_error'] = 'Google sign-in was cancelled.';
    header('Location: /register.php');
    exit;
}

if (!$code || !$state) {
    $_SESSION['google_error'] = 'Invalid Google callback. Please try again.';
    header('Location: /register.php');
    exit;
}

// Decode state
$stateData = json_decode($state, true);
$flow = $stateData['flow'] ?? '';
$csrfState = $stateData['csrf'] ?? '';

// Verify CSRF state
if (!$csrfState || !isset($_SESSION['google_oauth_state']) || !hash_equals($_SESSION['google_oauth_state'], $csrfState)) {
    $_SESSION['google_error'] = 'Invalid security token. Please try again.';
    header('Location: /register.php');
    exit;
}
unset($_SESSION['google_oauth_state']);

// --- Exchange code for tokens ---
$tokens = google_exchange_code($code);
if (!$tokens || empty($tokens['access_token'])) {
    $_SESSION['google_error'] = 'Failed to authenticate with Google. Please try again.';
    header('Location: /register.php');
    exit;
}

// --- Get user info from Google ---
$googleUser = google_get_user_info($tokens['access_token']);
if (!$googleUser || empty($googleUser['id']) || empty($googleUser['email'])) {
    $_SESSION['google_error'] = 'Could not retrieve your Google account info. Please try again.';
    header('Location: /register.php');
    exit;
}

$googleId   = $googleUser['id'];
$email      = strtolower($googleUser['email']);
$firstName  = $googleUser['given_name'] ?? '';
$lastName   = $googleUser['family_name'] ?? '';

$pdo = db();

// --- Route based on flow ---
if ($flow === 'login') {
    handleLogin($pdo, $googleId, $email);
} elseif ($flow === 'invite') {
    handleInvite($pdo, $googleId, $email, $firstName, $lastName);
} elseif ($flow === 'create') {
    handleCreate($pdo, $googleId, $email, $firstName, $lastName);
} else {
    $_SESSION['google_error'] = 'Invalid authentication flow.';
    header('Location: /register.php');
    exit;
}

// =====================================================================
// Flow handlers
// =====================================================================

function handleLogin($pdo, $googleId, $email) {
    // First try by google_id
    $stmt = $pdo->prepare("SELECT * FROM users WHERE google_id = ? AND is_active = 1");
    $stmt->execute([$googleId]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    // Fallback: match by email if they registered with Google before google_id was tracked
    if (!$user) {
        $stmt = $pdo->prepare("SELECT * FROM users WHERE email = ? AND auth_provider = 'google' AND is_active = 1");
        $stmt->execute([$email]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($user && !$user['google_id']) {
            $pdo->prepare("UPDATE users SET google_id = ? WHERE id = ?")->execute([$googleId, $user['id']]);
        }
    }

    if (!$user) {
        $_SESSION['google_error'] = 'No account found for this Google account. Please register first.';
        header('Location: /login.php');
        exit;
    }

    login_user($user, false);
    header('Location: /app.php');
    exit;
}

function handleInvite($pdo, $googleId, $email, $firstName, $lastName) {
    // Find invited user by email
    $stmt = $pdo->prepare("SELECT * FROM users WHERE email = ? AND is_active = 1");
    $stmt->execute([$email]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$user) {
        $_SESSION['google_error'] = 'No invitation found for this Google email (' . htmlspecialchars($email) . '). Please check with your admin.';
        header('Location: /register.php?invite');
        exit;
    }

    if ($user['password_hash'] !== '!INVITED') {
        $_SESSION['google_error'] = 'This account is already set up. <a href="/login.php">Sign in instead.</a>';
        header('Location: /register.php?invite');
        exit;
    }

    // Accept invitation: set google auth and clear invited marker
    $stmt = $pdo->prepare(
        "UPDATE users SET google_id = ?, auth_provider = 'google', password_hash = '!GOOGLE',
         first_name = COALESCE(NULLIF(first_name, ''), ?), last_name = COALESCE(NULLIF(last_name, ''), ?)
         WHERE id = ?"
    );
    $stmt->execute([$googleId, $firstName, $lastName, $user['id']]);

    // Re-fetch updated user
    $stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
    $stmt->execute([$user['id']]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    login_user($user, false);
    header('Location: /app.php');
    exit;
}

function handleCreate($pdo, $googleId, $email, $firstName, $lastName) {
    // Check if google_id already exists
    $stmt = $pdo->prepare("SELECT id FROM users WHERE google_id = ?");
    $stmt->execute([$googleId]);
    if ($stmt->fetch()) {
        $_SESSION['google_error'] = 'An account with this Google account already exists. <a href="/login.php">Sign in instead.</a>';
        header('Location: /register.php');
        exit;
    }

    // Check if email already exists
    $stmt = $pdo->prepare("SELECT id FROM users WHERE email = ?");
    $stmt->execute([$email]);
    if ($stmt->fetch()) {
        $_SESSION['google_error'] = 'An account with this email already exists. <a href="/login.php">Sign in instead.</a>';
        header('Location: /register.php');
        exit;
    }

    // Store Google user info in session and redirect to company name form
    $_SESSION['google_pending'] = [
        'google_id'  => $googleId,
        'email'      => $email,
        'first_name' => $firstName,
        'last_name'  => $lastName,
    ];

    header('Location: /register.php?google_complete=1');
    exit;
}
