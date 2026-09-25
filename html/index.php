<?php
require_once '../helpers/session.php';
require_once '../helpers/landing.php';

init_session();

// If logged in, go straight to the app.
if (isset($_SESSION['user_id'])) {
    header('Location: /app.php');
    exit;
}

$landingRoute = resolveLandingPageRouteForRequest();
$pageInclude = $landingRoute['page_include'] ?? landingDefaultPageInclude();

// If the route points to login.php, redirect there so it handles its own
// session, CSRF, and Google OAuth setup as a standalone page.
if ($pageInclude === 'login.php') {
    header('Location: /login.php');
    exit;
}

$landingPageInclude = resolveLandingPageInclude($pageInclude);

if ($landingPageInclude === null) {
    http_response_code(500);
    echo 'Landing page configuration is invalid.';
    exit;
}

include $landingPageInclude;
