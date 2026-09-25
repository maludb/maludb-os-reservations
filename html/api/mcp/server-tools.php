<?php
/**
 * Restaurant MCP Tool Definitions & Executor
 * Shared by MCP server and SMS agent
 */

require_once __DIR__ . '/../../../helpers/db.php';
require_once __DIR__ . '/../../../helpers/voice-api.php';

if (!function_exists('encodeToolPayload')) {
    function encodeToolPayload(array $payload): string
    {
        $json = json_encode(
            $payload,
            JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE
        );

        if ($json !== false) {
            return $json;
        }

        return json_encode(
            [
                'success' => false,
                'error' => 'Unable to encode tool result as JSON.',
            ],
            JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE
        );
    }
}

if (!function_exists('getToolDefinitions')) {
    function getToolDefinitions(): array
    {
        return [
            [
                'name' => 'check_availability',
                'description' => 'Check available reservation time slots for a specific date and party size at a restaurant.',
                'inputSchema' => [
                    'type' => 'object',
                    'required' => ['restaurant_slug', 'date', 'party_size'],
                    'properties' => [
                        'restaurant_slug' => ['type' => 'string', 'description' => 'Restaurant identifier slug (e.g. "the-italian-place")'],
                        'date' => ['type' => 'string', 'description' => 'Reservation date. Use "today", "tomorrow", or a day name like "monday", "tuesday", etc. Also accepts YYYY-MM-DD format.'],
                        'party_size' => ['type' => 'integer', 'description' => 'Number of guests (1-12)'],
                    ],
                ],
            ],
            [
                'name' => 'make_reservation',
                'description' => 'Book a new restaurant reservation. Returns a confirmation code.',
                'inputSchema' => [
                    'type' => 'object',
                    'required' => ['restaurant_slug', 'date', 'time', 'party_size', 'guest_name', 'guest_phone'],
                    'properties' => [
                        'restaurant_slug' => ['type' => 'string', 'description' => 'Restaurant identifier slug'],
                        'date' => ['type' => 'string', 'description' => 'Reservation date. Use "today", "tomorrow", or a day name like "monday", "tuesday", etc. Also accepts YYYY-MM-DD format.'],
                        'time' => ['type' => 'string', 'description' => 'Reservation time in HH:MM format (24-hour)'],
                        'party_size' => ['type' => 'integer', 'description' => 'Number of guests'],
                        'guest_name' => ['type' => 'string', 'description' => 'Full name of the guest (first and last)'],
                        'guest_phone' => ['type' => 'string', 'description' => 'Guest phone number'],
                        'guest_email' => ['type' => 'string', 'description' => 'Guest email address (optional)'],
                        'special_requests' => ['type' => 'string', 'description' => 'Special requests like allergies, celebrations, seating preferences (optional)'],
                    ],
                ],
            ],
            [
                'name' => 'lookup_reservation',
                'description' => 'Look up existing reservations by confirmation code or guest phone number. When searching by phone, returns ALL active reservations for that guest. Check the total_reservations count and reservations array to see all results.',
                'inputSchema' => [
                    'type' => 'object',
                    'required' => ['restaurant_slug'],
                    'properties' => [
                        'restaurant_slug' => ['type' => 'string', 'description' => 'Restaurant identifier slug'],
                        'confirmation_code' => ['type' => 'string', 'description' => 'The 6-digit reservation confirmation code'],
                        'guest_phone' => ['type' => 'string', 'description' => 'Guest phone number to search by (used if no confirmation code)'],
                    ],
                ],
            ],
            [
                'name' => 'cancel_reservation',
                'description' => 'Cancel an existing reservation. Only pending or confirmed reservations can be cancelled.',
                'inputSchema' => [
                    'type' => 'object',
                    'required' => ['restaurant_slug', 'confirmation_code'],
                    'properties' => [
                        'restaurant_slug' => ['type' => 'string', 'description' => 'Restaurant identifier slug'],
                        'confirmation_code' => ['type' => 'string', 'description' => 'The 6-digit reservation confirmation code to cancel'],
                    ],
                ],
            ],
            [
                'name' => 'confirm_reservation',
                'description' => 'Confirm a pending reservation.',
                'inputSchema' => [
                    'type' => 'object',
                    'required' => ['restaurant_slug', 'confirmation_code'],
                    'properties' => [
                        'restaurant_slug' => ['type' => 'string', 'description' => 'Restaurant identifier slug'],
                        'confirmation_code' => ['type' => 'string', 'description' => 'The 6-digit reservation confirmation code to confirm'],
                    ],
                ],
            ],
            [
                'name' => 'modify_reservation',
                'description' => 'Modify an existing reservation. Use this when a guest wants to change the date, time, or party size of their reservation. This cancels the old reservation and books a new one. Always use this instead of calling cancel + make_reservation separately.',
                'inputSchema' => [
                    'type' => 'object',
                    'required' => ['restaurant_slug', 'confirmation_code', 'date', 'time', 'party_size', 'guest_name', 'guest_phone'],
                    'properties' => [
                        'restaurant_slug' => ['type' => 'string', 'description' => 'Restaurant identifier slug'],
                        'confirmation_code' => ['type' => 'string', 'description' => 'The 6-digit confirmation code of the existing reservation to modify'],
                        'date' => ['type' => 'string', 'description' => 'New reservation date. Use "today", "tomorrow", or a day name like "monday", "tuesday", etc. Also accepts YYYY-MM-DD format.'],
                        'time' => ['type' => 'string', 'description' => 'New reservation time in HH:MM format (24-hour)'],
                        'party_size' => ['type' => 'integer', 'description' => 'New number of guests'],
                        'guest_name' => ['type' => 'string', 'description' => 'Full name of the guest'],
                        'guest_phone' => ['type' => 'string', 'description' => 'Guest phone number'],
                        'guest_email' => ['type' => 'string', 'description' => 'Guest email address (optional)'],
                        'special_requests' => ['type' => 'string', 'description' => 'Special requests (optional)'],
                    ],
                ],
            ],
            [
                'name' => 'update_contact_preferences',
                'description' => 'Update a guest\'s contact preferences such as do-not-call, do-not-email, or do-not-text. Use when a guest requests to opt out of (or back into) a contact method.',
                'inputSchema' => [
                    'type' => 'object',
                    'required' => ['restaurant_slug', 'guest_phone'],
                    'properties' => [
                        'restaurant_slug' => ['type' => 'string', 'description' => 'Restaurant identifier slug'],
                        'guest_phone' => ['type' => 'string', 'description' => 'Guest phone number to identify the guest'],
                        'do_not_call' => ['type' => 'boolean', 'description' => 'Set to true to opt out of calls, false to opt back in'],
                        'do_not_email' => ['type' => 'boolean', 'description' => 'Set to true to opt out of emails, false to opt back in'],
                        'do_not_text' => ['type' => 'boolean', 'description' => 'Set to true to opt out of texts, false to opt back in'],
                    ],
                ],
            ],
        ];
    }
}

if (!function_exists('executeTool')) {
    function executeTool(string $toolName, array $args): array
    {
        switch ($toolName) {
            case 'check_availability':
                $slug = $args['restaurant_slug'] ?? '';
                $date = $args['date'] ?? '';
                $partySize = (int)($args['party_size'] ?? 0);
                if ($slug === '' || $date === '' || $partySize === 0) {
                    return ['isError' => true, 'content' => [['type' => 'text', 'text' => 'Missing required parameters: restaurant_slug, date, party_size.']]];
                }
                $result = voiceCheckAvailability($slug, $date, $partySize);
                break;

            case 'make_reservation':
                $slug = $args['restaurant_slug'] ?? '';
                $date = $args['date'] ?? '';
                $time = $args['time'] ?? '';
                $partySize = (int)($args['party_size'] ?? 0);
                $guestName = $args['guest_name'] ?? '';
                $guestPhone = $args['guest_phone'] ?? '';
                $guestEmail = $args['guest_email'] ?? '';
                $specialRequests = $args['special_requests'] ?? '';
                if ($slug === '' || $date === '' || $time === '' || $partySize === 0 || $guestName === '' || $guestPhone === '') {
                    return ['isError' => true, 'content' => [['type' => 'text', 'text' => 'Missing required parameters.']]];
                }
                $result = voiceMakeReservation($slug, $date, $time, $partySize, $guestName, $guestPhone, $guestEmail, $specialRequests);
                break;

            case 'lookup_reservation':
                $slug = $args['restaurant_slug'] ?? '';
                $codeOrPhone = $args['confirmation_code'] ?? $args['guest_phone'] ?? '';
                if ($slug === '' || $codeOrPhone === '') {
                    return ['isError' => true, 'content' => [['type' => 'text', 'text' => 'Missing restaurant_slug and either confirmation_code or guest_phone.']]];
                }
                $result = voiceLookupReservation($slug, $codeOrPhone);
                break;

            case 'cancel_reservation':
                $slug = $args['restaurant_slug'] ?? '';
                $code = $args['confirmation_code'] ?? '';
                if ($slug === '' || $code === '') {
                    return ['isError' => true, 'content' => [['type' => 'text', 'text' => 'Missing restaurant_slug or confirmation_code.']]];
                }
                $result = voiceCancelReservation($slug, $code);
                break;

            case 'confirm_reservation':
                $slug = $args['restaurant_slug'] ?? '';
                $code = $args['confirmation_code'] ?? '';
                if ($slug === '' || $code === '') {
                    return ['isError' => true, 'content' => [['type' => 'text', 'text' => 'Missing restaurant_slug or confirmation_code.']]];
                }
                $result = voiceConfirmReservation($slug, $code);
                break;

            case 'modify_reservation':
                $slug = $args['restaurant_slug'] ?? '';
                $code = $args['confirmation_code'] ?? '';
                $date = $args['date'] ?? '';
                $time = $args['time'] ?? '';
                $partySize = (int)($args['party_size'] ?? 0);
                $guestName = $args['guest_name'] ?? '';
                $guestPhone = $args['guest_phone'] ?? '';
                $guestEmail = $args['guest_email'] ?? '';
                $specialRequests = $args['special_requests'] ?? '';
                if ($slug === '' || $code === '' || $date === '' || $time === '' || $partySize === 0 || $guestName === '' || $guestPhone === '') {
                    return ['isError' => true, 'content' => [['type' => 'text', 'text' => 'Missing required parameters.']]];
                }
                $result = voiceModifyReservation($slug, $code, $date, $time, $partySize, $guestName, $guestPhone, $guestEmail, $specialRequests);
                break;

            case 'update_contact_preferences':
                $slug = $args['restaurant_slug'] ?? '';
                $phone = $args['guest_phone'] ?? '';
                if ($slug === '' || $phone === '') {
                    return ['isError' => true, 'content' => [['type' => 'text', 'text' => 'Missing restaurant_slug or guest_phone.']]];
                }
                $prefs = [];
                foreach (['do_not_call', 'do_not_email', 'do_not_text'] as $f) {
                    if (isset($args[$f])) {
                        $prefs[$f] = (bool)$args[$f];
                    }
                }
                if (empty($prefs)) {
                    return ['isError' => true, 'content' => [['type' => 'text', 'text' => 'Provide at least one preference: do_not_call, do_not_email, or do_not_text.']]];
                }
                $result = voiceUpdateContactPreferences($slug, $phone, $prefs);
                break;

            default:
                return ['isError' => true, 'content' => [['type' => 'text', 'text' => "Unknown tool: {$toolName}"]]];
        }

        $isError = !($result['success'] ?? false);
        $text = encodeToolPayload($result);

        return [
            'isError' => $isError,
            'content' => [['type' => 'text', 'text' => $text]],
        ];
    }
}
