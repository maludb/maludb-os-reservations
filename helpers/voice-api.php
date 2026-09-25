<?php
/**
 * Voice API Service Layer
 *
 * Shared functions for Retell Custom Functions and MCP server.
 * No session dependency — restaurant identified by slug parameter.
 * All functions return ['success' => bool, ...data or 'error' => string].
 */

require_once __DIR__ . '/db.php';
require_once __DIR__ . '/restaurant.php';
require_once __DIR__ . '/availability.php';
require_once __DIR__ . '/notifications.php';

/**
 * Resolve relative date words (today, tomorrow, day names) and auto-correct
 * past-year dates when the LLM sends the wrong year.
 */
function voiceFixDate(string $date): string
{
    $lower = strtolower(trim($date));

    // Handle relative keywords
    if ($lower === 'today') {
        return date('Y-m-d');
    }
    if ($lower === 'tomorrow') {
        return date('Y-m-d', strtotime('+1 day'));
    }

    // Handle day-of-week names (e.g. "monday", "tuesday")
    $days = ['monday', 'tuesday', 'wednesday', 'thursday', 'friday', 'saturday', 'sunday'];
    if (in_array($lower, $days)) {
        $target = strtotime("next {$lower}");
        // If today IS that day, use today
        if (strtolower(date('l')) === $lower) {
            return date('Y-m-d');
        }
        return date('Y-m-d', $target);
    }

    // Fallback: auto-correct past-year dates
    $today = date('Y-m-d');
    if ($date >= $today) {
        return $date;
    }
    $corrected = date('Y') . substr($date, 4);
    if ($corrected >= $today) {
        return $corrected;
    }
    return (date('Y') + 1) . substr($date, 4);
}

/**
 * Check availability for a date and party size
 */
function voiceCheckAvailability(string $slug, string $date, int $partySize): array
{
    $date = voiceFixDate($date);

    $restaurant = getRestaurantBySlug($slug);
    if (!$restaurant) {
        return ['success' => false, 'error' => 'Restaurant not found.'];
    }

    $restaurantId = (int)$restaurant['id'];
    applyRestaurantTimezone($restaurantId);

    // Validate date
    $today = date('Y-m-d');
    if ($date < $today) {
        return ['success' => false, 'error' => 'Cannot book a date in the past.'];
    }

    $maxDays = (int)getRestaurantSetting($restaurantId, 'advance_booking_max_days', 30);
    $maxDate = date('Y-m-d', strtotime("+{$maxDays} days"));
    if ($date > $maxDate) {
        return ['success' => false, 'error' => "Reservations can only be made up to {$maxDays} days in advance."];
    }

    // Validate party size
    $maxParty = (int)getRestaurantSetting($restaurantId, 'max_online_party_size', 8);
    if ($partySize < 1) {
        return ['success' => false, 'error' => 'Party size must be at least 1.'];
    }
    if ($partySize > $maxParty) {
        return ['success' => false, 'error' => "For parties larger than {$maxParty}, please call the restaurant directly."];
    }

    // Same-day cutoff
    if ($date === $today) {
        $cutoffHours = (int)getRestaurantSetting($restaurantId, 'same_day_cutoff_hours', 1);
        $cutoffTime = date('H:i:s', strtotime("+{$cutoffHours} hours"));
    }

    $slots = getAvailableSlots($restaurantId, $date, $partySize);

    if (empty($slots)) {
        return [
            'success' => true,
            'restaurant_name' => $restaurant['name'],
            'date' => $date,
            'date_display' => date('l, F j, Y', strtotime($date)),
            'party_size' => $partySize,
            'available_slots' => [],
            'message' => 'No availability for this date and party size.',
        ];
    }

    // Filter same-day past times and cutoff
    if ($date === $today) {
        $cutoff = $cutoffTime ?? date('H:i:s');
        $slots = array_values(array_filter($slots, function ($s) use ($cutoff) {
            return $s['time'] >= $cutoff;
        }));
    }

    // Only return available slots (tables > 0)
    $available = array_values(array_filter($slots, function ($s) {
        return $s['available_tables'] > 0;
    }));

    // Group by service period for readable output
    $grouped = [];
    foreach ($available as $s) {
        $grouped[$s['service_name']][] = $s['time_display'];
    }

    $message = count($available) > 0
        ? count($available) . ' time slots available.'
        : 'No availability for this date and party size.';

    return [
        'success' => true,
        'restaurant_name' => $restaurant['name'],
        'date' => $date,
        'date_display' => date('l, F j, Y', strtotime($date)),
        'party_size' => $partySize,
        'available_slots' => $available,
        'slots_by_period' => $grouped,
        'message' => $message,
    ];
}

/**
 * Make a reservation
 */
function voiceMakeReservation(
    string $slug, string $date, string $time, int $partySize,
    string $guestName, string $guestPhone,
    string $guestEmail = '', string $specialRequests = ''
): array {
    $date = voiceFixDate($date);

    $restaurant = getRestaurantBySlug($slug);
    if (!$restaurant) {
        return ['success' => false, 'error' => 'Restaurant not found.'];
    }

    $restaurantId = (int)$restaurant['id'];
    applyRestaurantTimezone($restaurantId);
    $pdo = db();

    // Validate date
    $today = date('Y-m-d');
    if ($date < $today) {
        return ['success' => false, 'error' => 'Cannot book a date in the past.'];
    }

    // Validate time format — accept HH:MM or HH:MM:SS
    if (!preg_match('/^\d{1,2}:\d{2}(:\d{2})?$/', $time)) {
        return ['success' => false, 'error' => 'Invalid time format. Use HH:MM.'];
    }
    // Normalize to HH:MM:SS
    if (strlen($time) <= 5) {
        $time .= ':00';
    }

    // Validate party size
    $maxParty = (int)getRestaurantSetting($restaurantId, 'max_online_party_size', 8);
    if ($partySize < 1 || $partySize > $maxParty) {
        return ['success' => false, 'error' => "Party size must be between 1 and {$maxParty}."];
    }

    // Validate guest name
    if (trim($guestName) === '') {
        return ['success' => false, 'error' => 'Guest name is required.'];
    }

    // Parse first/last name
    $nameParts = explode(' ', trim($guestName), 2);
    $firstName = $nameParts[0];
    $lastName = $nameParts[1] ?? '';

    // Validate phone
    if (trim($guestPhone) === '') {
        return ['success' => false, 'error' => 'Phone number is required.'];
    }

    // Find or create guest (match by phone within restaurant)
    $stmt = $pdo->prepare(
        "SELECT id FROM guests WHERE restaurant_id = ? AND phone = ? LIMIT 1"
    );
    $stmt->execute([$restaurantId, $guestPhone]);
    $existingGuest = $stmt->fetch();

    if ($existingGuest) {
        $guestId = (int)$existingGuest['id'];
    } else {
        $stmt = $pdo->prepare(
            "INSERT INTO guests (restaurant_id, first_name, last_name, phone, email, created_at)
             VALUES (?, ?, ?, ?, ?, NOW())"
        );
        $stmt->execute([$restaurantId, $firstName, $lastName, $guestPhone, $guestEmail ?: null]);
        $guestId = (int)$pdo->lastInsertId();
    }

    // Auto-assign table
    $tableId = findAvailableTable($restaurantId, $date, $time, $partySize);

    // Generate confirmation code
    $confirmationCode = generateConfirmationCode($restaurantId, $date);

    // Get turn time
    list($turnTime,) = getTurnTime($restaurantId, $partySize);

    // Insert reservation
    $stmt = $pdo->prepare(
        "INSERT INTO reservations
         (restaurant_id, guest_id, table_id, reservation_date, reservation_time,
          party_size, status, source, confirmation_code, turn_time_minutes,
          special_requests, created_at)
         VALUES (?, ?, ?, ?, ?, ?, 'confirmed', 'phone', ?, ?, ?, NOW())"
    );
    $stmt->execute([
        $restaurantId, $guestId, $tableId, $date, $time,
        $partySize, $confirmationCode, $turnTime,
        $specialRequests ?: null
    ]);
    $reservationId = (int)$pdo->lastInsertId();

    // Log activity
    try {
        $stmt = $pdo->prepare(
            "INSERT INTO activity_log (restaurant_id, user_id, reservation_id, action, description, ip_address, created_at)
             VALUES (?, NULL, ?, 'reservation_created', ?, ?, NOW())"
        );
        $stmt->execute([
            $restaurantId, $reservationId,
            "Phone reservation by {$firstName} {$lastName} for {$partySize} on {$date} at {$time}",
            $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0'
        ]);
    } catch (Exception $e) {
        // Don't fail on logging
    }

    // Send confirmation email and SMS
    try {
        sendConfirmationEmail($reservationId);
        sendConfirmationSMS($reservationId);
    } catch (Exception $e) {
        // Don't fail on notification
    }

    return [
        'success' => true,
        'confirmation_code' => $confirmationCode,
        'restaurant_name' => $restaurant['name'],
        'date' => $date,
        'date_display' => date('l, F j, Y', strtotime($date)),
        'time' => $time,
        'time_display' => date('g:ia', strtotime($time)),
        'party_size' => $partySize,
        'guest_name' => trim("{$firstName} {$lastName}"),
        'status' => 'confirmed',
        'message' => "Reservation confirmed! Confirmation code is {$confirmationCode}.",
    ];
}

/**
 * Look up a reservation by confirmation code or phone
 */
function voiceLookupReservation(string $slug, string $codeOrPhone): array
{
    $restaurant = getRestaurantBySlug($slug);
    if (!$restaurant) {
        return ['success' => false, 'error' => 'Restaurant not found.'];
    }

    $restaurantId = (int)$restaurant['id'];
    applyRestaurantTimezone($restaurantId);
    $pdo = db();

    $reservations = [];

    // Try confirmation code first (unique — returns one)
    $stmt = $pdo->prepare(
        "SELECT r.*, g.first_name, g.last_name, g.phone, g.email, t.table_name
         FROM reservations r
         JOIN guests g ON r.guest_id = g.id
         LEFT JOIN tables t ON r.table_id = t.id
         WHERE r.restaurant_id = ? AND r.confirmation_code = ?
         LIMIT 1"
    );
    $stmt->execute([$restaurantId, strtoupper(trim($codeOrPhone))]);
    $row = $stmt->fetch();
    if ($row) {
        $reservations[] = $row;
    }

    // If not found by code, try by phone (return ALL active reservations)
    if (empty($reservations)) {
        $stmt = $pdo->prepare(
            "SELECT r.*, g.first_name, g.last_name, g.phone, g.email, t.table_name
             FROM reservations r
             JOIN guests g ON r.guest_id = g.id
             LEFT JOIN tables t ON r.table_id = t.id
             WHERE r.restaurant_id = ? AND g.phone = ?
               AND r.status IN ('pending', 'confirmed', 'seated')
             ORDER BY r.reservation_date ASC, r.reservation_time ASC"
        );
        $stmt->execute([$restaurantId, trim($codeOrPhone)]);
        $reservations = $stmt->fetchAll();
    }

    if (empty($reservations)) {
        return ['success' => false, 'error' => 'No reservation found with that confirmation code or phone number.'];
    }

    // Build formatted list
    $formatted = [];
    foreach ($reservations as $r) {
        $formatted[] = [
            'confirmation_code' => $r['confirmation_code'],
            'guest_name' => $r['first_name'] . ' ' . $r['last_name'],
            'phone' => $r['phone'] ?? '',
            'email' => $r['email'] ?? '',
            'date' => $r['reservation_date'],
            'date_display' => date('l, F j, Y', strtotime($r['reservation_date'])),
            'time' => $r['reservation_time'],
            'time_display' => date('g:ia', strtotime($r['reservation_time'])),
            'party_size' => (int)$r['party_size'],
            'status' => $r['status'],
            'table' => $r['table_name'] ?? 'TBD',
            'special_requests' => $r['special_requests'] ?? '',
            'can_cancel' => in_array($r['status'], ['pending', 'confirmed']),
            'can_confirm' => $r['status'] === 'pending',
        ];
    }

    // Build summary message
    $first = $reservations[0];
    $guestName = $first['first_name'] . ' ' . $first['last_name'];
    if (count($reservations) === 1) {
        $message = "Reservation found for {$guestName} on "
            . date('l, F j', strtotime($first['reservation_date']))
            . " at " . date('g:ia', strtotime($first['reservation_time']))
            . " for {$first['party_size']} guests. Status: {$first['status']}.";
    } else {
        $message = count($reservations) . " reservations found for {$guestName}:";
        foreach ($reservations as $i => $r) {
            $num = $i + 1;
            $message .= " ({$num}) {$r['confirmation_code']} — "
                . date('l, F j', strtotime($r['reservation_date']))
                . " at " . date('g:ia', strtotime($r['reservation_time']))
                . " for {$r['party_size']} guests, status: {$r['status']}.";
        }
    }

    return [
        'success' => true,
        'reservation' => $formatted[0],
        'reservations' => $formatted,
        'total_reservations' => count($formatted),
        'can_cancel' => $formatted[0]['can_cancel'],
        'can_confirm' => $formatted[0]['can_confirm'],
        'message' => $message,
    ];
}

/**
 * Cancel a reservation
 */
function voiceCancelReservation(string $slug, string $confirmationCode): array
{
    $restaurant = getRestaurantBySlug($slug);
    if (!$restaurant) {
        return ['success' => false, 'error' => 'Restaurant not found.'];
    }

    $restaurantId = (int)$restaurant['id'];
    applyRestaurantTimezone($restaurantId);
    $pdo = db();

    $stmt = $pdo->prepare(
        "SELECT r.id, r.status, r.reservation_date, r.reservation_time, r.party_size,
                g.first_name, g.last_name
         FROM reservations r
         JOIN guests g ON r.guest_id = g.id
         WHERE r.restaurant_id = ? AND r.confirmation_code = ?
         LIMIT 1"
    );
    $stmt->execute([$restaurantId, strtoupper(trim($confirmationCode))]);
    $reservation = $stmt->fetch();

    if (!$reservation) {
        return ['success' => false, 'error' => 'No reservation found with that confirmation code.'];
    }

    if (!in_array($reservation['status'], ['pending', 'confirmed'])) {
        return ['success' => false, 'error' => "This reservation cannot be cancelled because it is already {$reservation['status']}."];
    }

    // Cancel
    $stmt = $pdo->prepare(
        "UPDATE reservations SET status = 'cancelled', cancelled_at = NOW() WHERE id = ?"
    );
    $stmt->execute([$reservation['id']]);

    // Log activity
    try {
        $stmt = $pdo->prepare(
            "INSERT INTO activity_log (restaurant_id, user_id, reservation_id, action, description, ip_address, created_at)
             VALUES (?, NULL, ?, 'reservation_cancelled', ?, ?, NOW())"
        );
        $stmt->execute([
            $restaurantId, $reservation['id'],
            "Phone cancellation for {$reservation['first_name']} {$reservation['last_name']} — {$reservation['reservation_date']} at {$reservation['reservation_time']}",
            $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0'
        ]);
    } catch (Exception $e) {}

    // Send cancellation email
    try {
        sendCancellationEmail((int)$reservation['id']);
    } catch (Exception $e) {}

    return [
        'success' => true,
        'confirmation_code' => strtoupper(trim($confirmationCode)),
        'message' => "Reservation for {$reservation['first_name']} {$reservation['last_name']} on "
            . date('l, F j', strtotime($reservation['reservation_date']))
            . " at " . date('g:ia', strtotime($reservation['reservation_time']))
            . " has been cancelled.",
    ];
}

/**
 * Confirm a pending reservation
 */
function voiceConfirmReservation(string $slug, string $confirmationCode): array
{
    $restaurant = getRestaurantBySlug($slug);
    if (!$restaurant) {
        return ['success' => false, 'error' => 'Restaurant not found.'];
    }

    $restaurantId = (int)$restaurant['id'];
    applyRestaurantTimezone($restaurantId);
    $pdo = db();

    $stmt = $pdo->prepare(
        "SELECT r.id, r.status, r.reservation_date, r.reservation_time, r.party_size,
                g.first_name, g.last_name
         FROM reservations r
         JOIN guests g ON r.guest_id = g.id
         WHERE r.restaurant_id = ? AND r.confirmation_code = ?
         LIMIT 1"
    );
    $stmt->execute([$restaurantId, strtoupper(trim($confirmationCode))]);
    $reservation = $stmt->fetch();

    if (!$reservation) {
        return ['success' => false, 'error' => 'No reservation found with that confirmation code.'];
    }

    if ($reservation['status'] !== 'pending') {
        if ($reservation['status'] === 'confirmed') {
            return ['success' => true, 'message' => 'This reservation is already confirmed.'];
        }
        return ['success' => false, 'error' => "This reservation cannot be confirmed because it is {$reservation['status']}."];
    }

    // Confirm
    $stmt = $pdo->prepare("UPDATE reservations SET status = 'confirmed' WHERE id = ?");
    $stmt->execute([$reservation['id']]);

    // Log activity
    try {
        $stmt = $pdo->prepare(
            "INSERT INTO activity_log (restaurant_id, user_id, reservation_id, action, description, ip_address, created_at)
             VALUES (?, NULL, ?, 'reservation_confirmed', ?, ?, NOW())"
        );
        $stmt->execute([
            $restaurantId, $reservation['id'],
            "Phone confirmation for {$reservation['first_name']} {$reservation['last_name']} — {$reservation['reservation_date']} at {$reservation['reservation_time']}",
            $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0'
        ]);
    } catch (Exception $e) {}

    // Send confirmation email and SMS
    try {
        sendConfirmationEmail((int)$reservation['id']);
        sendConfirmationSMS((int)$reservation['id']);
    } catch (Exception $e) {}

    return [
        'success' => true,
        'confirmation_code' => strtoupper(trim($confirmationCode)),
        'message' => "Reservation confirmed for {$reservation['first_name']} {$reservation['last_name']} on "
            . date('l, F j', strtotime($reservation['reservation_date']))
            . " at " . date('g:ia', strtotime($reservation['reservation_time']))
            . " for {$reservation['party_size']} guests.",
    ];
}

/**
 * Update guest contact preferences (do not call/email/text)
 */
function voiceUpdateContactPreferences(string $slug, string $guestPhone, array $preferences): array
{
    $restaurant = getRestaurantBySlug($slug);
    if (!$restaurant) {
        return ['success' => false, 'error' => 'Restaurant not found.'];
    }

    $restaurantId = (int)$restaurant['id'];
    applyRestaurantTimezone($restaurantId);
    $pdo = db();

    // Find guest by phone
    $stmt = $pdo->prepare(
        "SELECT id, first_name, last_name, do_not_call, do_not_email, do_not_text
         FROM guests WHERE restaurant_id = ? AND phone = ? AND is_active = 1 LIMIT 1"
    );
    $stmt->execute([$restaurantId, trim($guestPhone)]);
    $guest = $stmt->fetch();

    if (!$guest) {
        return ['success' => false, 'error' => 'No guest found with that phone number.'];
    }

    $allowed = ['do_not_call', 'do_not_email', 'do_not_text'];
    $updates = [];
    $params = [];
    $changed = [];

    foreach ($allowed as $field) {
        if (isset($preferences[$field])) {
            $val = $preferences[$field] ? 1 : 0;
            $updates[] = "{$field} = ?";
            $params[] = $val;
            $label = str_replace('_', ' ', $field);
            $changed[] = "{$label}: " . ($val ? 'yes' : 'no');
        }
    }

    if (empty($updates)) {
        return ['success' => false, 'error' => 'No valid preferences provided. Use do_not_call, do_not_email, or do_not_text.'];
    }

    $params[] = $guest['id'];
    $params[] = $restaurantId;
    $stmt = $pdo->prepare("UPDATE guests SET " . implode(', ', $updates) . " WHERE id = ? AND restaurant_id = ?");
    $stmt->execute($params);

    $guestName = trim($guest['first_name'] . ' ' . $guest['last_name']);

    return [
        'success' => true,
        'guest_name' => $guestName,
        'updated' => $changed,
        'message' => "Contact preferences updated for {$guestName}: " . implode(', ', $changed) . '.',
    ];
}

/**
 * Modify an existing reservation — cancels the old one and books a new one
 */
function voiceModifyReservation(
    string $slug, string $confirmationCode,
    string $date, string $time, int $partySize,
    string $guestName, string $guestPhone,
    string $guestEmail = '', string $specialRequests = ''
): array {
    // Look up the existing reservation first
    $restaurant = getRestaurantBySlug($slug);
    if (!$restaurant) {
        return ['success' => false, 'error' => 'Restaurant not found.'];
    }

    $restaurantId = (int)$restaurant['id'];
    applyRestaurantTimezone($restaurantId);
    $pdo = db();

    $stmt = $pdo->prepare(
        "SELECT r.id, r.status, r.reservation_date, r.reservation_time, r.party_size,
                g.first_name, g.last_name
         FROM reservations r
         JOIN guests g ON r.guest_id = g.id
         WHERE r.restaurant_id = ? AND r.confirmation_code = ?
         LIMIT 1"
    );
    $stmt->execute([$restaurantId, strtoupper(trim($confirmationCode))]);
    $existing = $stmt->fetch();

    if (!$existing) {
        return ['success' => false, 'error' => 'No reservation found with that confirmation code.'];
    }

    if (!in_array($existing['status'], ['pending', 'confirmed'])) {
        return ['success' => false, 'error' => "Cannot modify this reservation because it is already {$existing['status']}."];
    }

    // Cancel the old reservation
    $cancelResult = voiceCancelReservation($slug, $confirmationCode);
    if (!($cancelResult['success'] ?? false)) {
        return ['success' => false, 'error' => 'Failed to cancel the original reservation: ' . ($cancelResult['error'] ?? 'Unknown error.')];
    }

    // Book the new reservation
    $newResult = voiceMakeReservation($slug, $date, $time, $partySize, $guestName, $guestPhone, $guestEmail, $specialRequests);
    if (!($newResult['success'] ?? false)) {
        return ['success' => false, 'error' => 'Original reservation was cancelled but failed to book the new one: ' . ($newResult['error'] ?? 'Unknown error.')];
    }

    $oldDate = date('l, F j', strtotime($existing['reservation_date']));
    $oldTime = date('g:ia', strtotime($existing['reservation_time']));

    return [
        'success' => true,
        'old_confirmation_code' => strtoupper(trim($confirmationCode)),
        'new_confirmation_code' => $newResult['confirmation_code'],
        'restaurant_name' => $newResult['restaurant_name'],
        'date' => $newResult['date'],
        'date_display' => $newResult['date_display'],
        'time' => $newResult['time'],
        'time_display' => $newResult['time_display'],
        'party_size' => $newResult['party_size'],
        'guest_name' => $newResult['guest_name'],
        'status' => $newResult['status'],
        'message' => "Reservation modified. Original reservation ({$confirmationCode}) for {$oldDate} at {$oldTime} has been cancelled. "
            . "New reservation confirmed for {$newResult['date_display']} at {$newResult['time_display']} for {$partySize} guests. "
            . "New confirmation code is {$newResult['confirmation_code']}.",
    ];
}
