<?php
/**
 * Retell Custom Function: update_contact_preferences
 * Updates a guest's do-not-call, do-not-email, do-not-text flags.
 */
require_once __DIR__ . '/../../../helpers/retell-auth.php';
require_once __DIR__ . '/../../../helpers/voice-api.php';

$data = parseRetellRequest();
$args = getRetellArgs($data);

$slug = $args['restaurant_slug'] ?? '';
$phone = $args['guest_phone'] ?? '';

if ($slug === '' || $phone === '') {
    retellResponse(['success' => false, 'error' => 'Missing required parameters: restaurant_slug, guest_phone.']);
}

$prefs = [];
foreach (['do_not_call', 'do_not_email', 'do_not_text'] as $f) {
    if (isset($args[$f])) {
        $prefs[$f] = (bool)$args[$f];
    }
}

if (empty($prefs)) {
    retellResponse(['success' => false, 'error' => 'Provide at least one preference: do_not_call, do_not_email, or do_not_text.']);
}

$result = voiceUpdateContactPreferences($slug, $phone, $prefs);
retellResponse($result);
