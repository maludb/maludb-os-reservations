<?php
/**
 * Retell Custom Function: lookup_reservation
 * Finds a reservation by confirmation code or phone number.
 */
require_once __DIR__ . '/../../../helpers/retell-auth.php';
require_once __DIR__ . '/../../../helpers/voice-api.php';

$data = parseRetellRequest();
$args = getRetellArgs($data);

$slug = $args['restaurant_slug'] ?? '';
$codeOrPhone = $args['confirmation_code'] ?? $args['guest_phone'] ?? '';

if ($slug === '' || $codeOrPhone === '') {
    retellResponse(['success' => false, 'error' => 'Missing required parameters: restaurant_slug and either confirmation_code or guest_phone.']);
}

$result = voiceLookupReservation($slug, $codeOrPhone);
retellResponse($result);
