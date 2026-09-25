<?php
/**
 * Retell Custom Function: check_availability
 * Returns available time slots for a date and party size.
 */
require_once __DIR__ . '/../../../helpers/retell-auth.php';
require_once __DIR__ . '/../../../helpers/voice-api.php';

$data = parseRetellRequest();
$args = getRetellArgs($data);

$slug = $args['restaurant_slug'] ?? '';
$date = $args['date'] ?? '';
$partySize = (int)($args['party_size'] ?? 0);

if ($slug === '' || $date === '' || $partySize === 0) {
    retellResponse(['success' => false, 'error' => 'Missing required parameters: restaurant_slug, date, party_size.']);
}

$result = voiceCheckAvailability($slug, $date, $partySize);
retellResponse($result);
