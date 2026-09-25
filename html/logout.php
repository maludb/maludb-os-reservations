<?php
/**
 * Logout Handler
 *
 * Destroys user session and redirects to login page
 */

require_once '../helpers/auth.php';

init_session();

if (isset($_SESSION['user_id'])) {
    error_log("User logged out - ID: {$_SESSION['user_id']}");
}

logout_user();

header('Location: /login.php');
exit;
