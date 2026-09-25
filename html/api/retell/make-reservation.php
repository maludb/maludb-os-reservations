<?php
/**
 * Retell Custom Function: make_reservation
 * Books a reservation and returns confirmation code.
 */
require_once __DIR__ . '/../../../helpers/retell-auth.php';
require_once __DIR__ . '/../../../helpers/voice-api.php';

$data = parseRetellRequest();
$args = getRetellArgs($data);

$slug = $args['restaurant_slug'] ?? '';
$date = $args['date'] ?? '';
$time = $args['time'] ?? '';
$partySize = (int)($args['party_size'] ?? 0);
$guestName = $args['guest_name'] ?? '';
$guestPhone = $args['guest_phone'] ?? '';
$guestEmail = $args['guest_email'] ?? '';
$specialRequests = $args['special_requests'] ?? '';

if ($slug === '' || $date === '' || $time === '' || $partySize === 0 || $guestName === '' || $guestPhone === '') {
    retellResponse(['success' => false, 'error' => 'Missing required parameters: restaurant_slug, date, time, party_size, guest_name, guest_phone.']);
}

$result = voiceMakeReservation($slug, $date, $time, $partySize, $guestName, $guestPhone, $guestEmail, $specialRequests);
retellResponse($result);
