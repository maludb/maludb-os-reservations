<?php
/**
 * Google OAuth 2.0 Helper Functions
 *
 * Server-side OAuth using PHP curl (no Composer dependencies)
 */

require_once __DIR__ . '/../config/google-oauth.php';

/**
 * Build the Google OAuth redirect URI dynamically from the current host.
 */
function google_redirect_uri(): string {
    $forwardedProto = $_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '';
    $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') || $forwardedProto === 'https' ? 'https' : 'http';
    $host = $_SERVER['HTTP_HOST'] ?? 'zozocal.com';
    return "{$protocol}://{$host}/google-callback.php";
}

/**
 * Build Google OAuth authorization URL
 *
 * @param string $state JSON-encoded state (flow type, etc.)
 * @return string The Google auth URL to redirect to
 */
function google_auth_url(string $state): string {
    $params = [
        'client_id'     => GOOGLE_CLIENT_ID,
        'redirect_uri'  => google_redirect_uri(),
        'response_type' => 'code',
        'scope'         => 'openid email profile',
        'access_type'   => 'online',
        'state'         => $state,
        'prompt'        => 'select_account',
    ];
    return 'https://accounts.google.com/o/oauth2/v2/auth?' . http_build_query($params);
}

/**
 * Exchange authorization code for tokens
 *
 * @param string $code Authorization code from Google
 * @return array|null Token response or null on failure
 */
function google_exchange_code(string $code): ?array {
    $postFields = [
        'code'          => $code,
        'client_id'     => GOOGLE_CLIENT_ID,
        'client_secret' => GOOGLE_CLIENT_SECRET,
        'redirect_uri'  => google_redirect_uri(),
        'grant_type'    => 'authorization_code',
    ];

    $ch = curl_init('https://oauth2.googleapis.com/token');
    curl_setopt_array($ch, [
        CURLOPT_POST           => true,
        CURLOPT_POSTFIELDS     => http_build_query($postFields),
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT        => 10,
        CURLOPT_HTTPHEADER     => ['Content-Type: application/x-www-form-urlencoded'],
    ]);
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($httpCode !== 200 || !$response) {
        error_log('Google token exchange failed: HTTP ' . $httpCode . ' — ' . $response);
        return null;
    }

    return json_decode($response, true);
}

/**
 * Get user info from Google using access token
 *
 * @param string $accessToken
 * @return array|null User info (id, email, given_name, family_name, picture) or null
 */
function google_get_user_info(string $accessToken): ?array {
    $ch = curl_init('https://www.googleapis.com/oauth2/v2/userinfo');
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT        => 10,
        CURLOPT_HTTPHEADER     => ['Authorization: Bearer ' . $accessToken],
    ]);
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($httpCode !== 200 || !$response) {
        error_log('Google userinfo failed: HTTP ' . $httpCode . ' — ' . $response);
        return null;
    }

    return json_decode($response, true);
}
