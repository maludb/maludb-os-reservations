<?php
require_once '../../../helpers/session.php';
require_once '../../../helpers/csrf.php';
require_once '../../../helpers/validation.php';
require_once '../../../helpers/db.php';
require_once '../../../helpers/auth.php';

init_session();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo '<div class="alert alert-warning" id="invite-error-method">Invalid request method.</div>';
    exit;
}

if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
    unset($_SESSION['csrf_token']);
    $newToken = generate_csrf_token();
    echo '<div class="alert alert-danger" id="invite-error-csrf">Invalid security token. Please try again.</div>';
    echo '<input type="hidden" name="csrf_token" value="' . htmlspecialchars($newToken) . '" hx-swap-oob="outerHTML:#invite-form [name=csrf_token]">';
    exit;
}

$email = trim($_POST['email'] ?? '');
$password = $_POST['password'] ?? '';
$confirmPassword = $_POST['confirm_password'] ?? '';

if ($email === '' || $password === '' || $confirmPassword === '') {
    echo '<div class="alert alert-danger" id="invite-error-required">All fields are required.</div>';
    exit;
}

if (!validate_email($email)) {
    echo '<div class="alert alert-danger" id="invite-error-email">Please enter a valid email address.</div>';
    exit;
}

if ($password !== $confirmPassword) {
    echo '<div class="alert alert-danger" id="invite-error-match">Passwords do not match.</div>';
    exit;
}

$pwCheck = validate_password($password);
if (!$pwCheck['valid']) {
    echo '<div class="alert alert-danger" id="invite-error-strength">' . htmlspecialchars($pwCheck['message']) . '</div>';
    exit;
}

$pdo = db();

// Find user by email with pending invitation (password_hash = '!INVITED')
$stmt = $pdo->prepare("SELECT * FROM users WHERE email = ? AND is_active = 1");
$stmt->execute([$email]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$user) {
    echo '<div class="alert alert-danger" id="invite-error-notfound">No invitation found for this email. Please check with your restaurant admin.</div>';
    exit;
}

if ($user['password_hash'] !== '!INVITED') {
    echo '<div class="alert alert-danger" id="invite-error-already">This account already has a password. <a href="/login.php" class="alert-link">Sign in instead?</a></div>';
    exit;
}

// Set the password
$stmt = $pdo->prepare("UPDATE users SET password_hash = ? WHERE id = ?");
$stmt->execute([password_hash($password, PASSWORD_DEFAULT), $user['id']]);

// Log in
login_user($user, false);
header('HX-Redirect: /app.php');
echo '<div class="alert alert-success" id="invite-success">Password set! Redirecting...</div>';
