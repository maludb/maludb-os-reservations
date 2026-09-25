<?php
/**
 * Retell Custom Function: cancel_reservation
 * Cancels a pending or confirmed reservation.
 */
require_once __DIR__ . '/../../../helpers/retell-auth.php';
require_once __DIR__ . '/../../../helpers/voice-api.php';

$data = parseRetellRequest();
$args = getRetellArgs($data);

$slug = $args['restaurant_slug'] ?? '';
$confirmationCode = $args['confirmation_code'] ?? '';

if ($slug === '' || $confirmationCode === '') {
    retellResponse(['success' => false, 'error' => 'Missing required parameters: restaurant_slug, confirmation_code.']);
}

$result = voiceCancelReservation($slug, $confirmationCode);
retellResponse($result);
