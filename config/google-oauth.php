<?php
/**
 * Google OAuth 2.0 Configuration
 * Values come from config/local.php or environment (see config/app.php).
 */

require_once __DIR__ . '/app.php';

define('GOOGLE_CLIENT_ID', (string)app_config('GOOGLE_CLIENT_ID', ''));
define('GOOGLE_CLIENT_SECRET', (string)app_config('GOOGLE_CLIENT_SECRET', ''));

// Redirect URI is built dynamically in helpers/google-auth.php based on the current host.
// Register each domain's callback URL in Google Cloud Console: https://<your domain>/google-callback.php
