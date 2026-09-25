<?php
/**
 * Twilio SMS Helper — cURL-based (no Composer dependency)
 * Reads credentials from settings table per restaurant
 */
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/availability.php'; // for getRestaurantSetting()

/**
 * Send SMS via Twilio REST API
 * @return array ['success' => bool, 'sid' => string|null, 'error' => string|null]
 */
function twilioSend(int $restaurantId, string $to, string $body): array
{
    $accountSid = getRestaurantSetting($restaurantId, 'sms_api_key', '');
    $authToken  = getRestaurantSetting($restaurantId, 'sms_api_secret', '');
    $fromNumber = getRestaurantSetting($restaurantId, 'sms_from_number', '');

    if ($accountSid === '' || $authToken === '' || $fromNumber === '') {
        return ['success' => false, 'sid' => null, 'error' => 'Twilio credentials not configured'];
    }

    $url = "https://api.twilio.com/2010-04-01/Accounts/{$accountSid}/Messages.json";

    $data = [
        'To'   => $to,
        'From' => $fromNumber,
        'Body' => $body,
    ];

    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_POST           => true,
        CURLOPT_POSTFIELDS     => http_build_query($data),
        CURLOPT_USERPWD        => "{$accountSid}:{$authToken}",
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT        => 15,
        CURLOPT_HTTPHEADER     => ['Content-Type: application/x-www-form-urlencoded'],
    ]);

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curlError = curl_error($ch);
    curl_close($ch);

    if ($curlError) {
        return ['success' => false, 'sid' => null, 'error' => "cURL error: {$curlError}"];
    }

    $json = json_decode($response, true);

    if ($httpCode >= 200 && $httpCode < 300 && isset($json['sid'])) {
        return ['success' => true, 'sid' => $json['sid'], 'error' => null, 'response' => $response];
    }

    $errorMsg = $json['message'] ?? $json['error_message'] ?? "HTTP {$httpCode}";
    return ['success' => false, 'sid' => null, 'error' => $errorMsg, 'response' => $response];
}

/**
 * Send SMS — simple wrapper used by notification system
 * @return bool
 */
function sendSMS(int $restaurantId, string $to, string $message): bool
{
    $result = twilioSend($restaurantId, $to, $message);
    return $result['success'];
}
