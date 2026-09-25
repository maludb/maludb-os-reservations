<?php
/**
 * Availability Engine
 *
 * Core logic for calculating available reservation time slots.
 * All functions require $restaurantId as the first parameter.
 */

require_once __DIR__ . '/db.php';

/**
 * Get a restaurant setting value
 */
function getRestaurantSetting($restaurantId, $key, $default = null) {
    static $cache = [];
    $cacheKey = $restaurantId . ':' . $key;

    if (isset($cache[$cacheKey])) {
        return $cache[$cacheKey];
    }

    $stmt = db()->prepare("SELECT setting_value FROM settings WHERE restaurant_id = ? AND setting_key = ?");
    $stmt->execute([$restaurantId, $key]);
    $row = $stmt->fetch();
    $value = $row ? $row['setting_value'] : $default;
    $cache[$cacheKey] = $value;
    return $value;
}

/**
 * Get turn time and buffer for a party size and optional service period
 *
 * @return array [duration_minutes, buffer_minutes]
 */
function getTurnTime($restaurantId, $partySize, $servicePeriod = 'all') {
    $pdo = db();

    // Try specific service period first
    if ($servicePeriod !== 'all') {
        $stmt = $pdo->prepare(
            "SELECT duration_minutes, buffer_minutes FROM turn_times
             WHERE restaurant_id = ? AND service_period = ?
               AND min_party_size <= ? AND max_party_size >= ?
             ORDER BY min_party_size ASC LIMIT 1"
        );
        $stmt->execute([$restaurantId, $servicePeriod, $partySize, $partySize]);
        $row = $stmt->fetch();
        if ($row) {
            return [(int)$row['duration_minutes'], (int)$row['buffer_minutes']];
        }
    }

    // Fall back to 'all'
    $stmt = $pdo->prepare(
        "SELECT duration_minutes, buffer_minutes FROM turn_times
         WHERE restaurant_id = ? AND service_period = 'all'
           AND min_party_size <= ? AND max_party_size >= ?
         ORDER BY min_party_size ASC LIMIT 1"
    );
    $stmt->execute([$restaurantId, $partySize, $partySize]);
    $row = $stmt->fetch();

    if ($row) {
        return [(int)$row['duration_minutes'], (int)$row['buffer_minutes']];
    }

    // Default fallback
    $defaultBuffer = (int)getRestaurantSetting($restaurantId, 'default_buffer_minutes', 15);
    return [90, $defaultBuffer];
}

/**
 * Get operating hours for a specific date (handles special_dates overrides)
 *
 * @return array Array of service periods with times, or empty if closed
 */
function getOperatingHoursForDate($restaurantId, $date) {
    $pdo = db();

    // Check for special date
    $stmt = $pdo->prepare(
        "SELECT * FROM special_dates WHERE restaurant_id = ? AND special_date = ?"
    );
    $stmt->execute([$restaurantId, $date]);
    $specialDate = $stmt->fetch();

    // If blackout/closed
    if ($specialDate && $specialDate['is_closed']) {
        return [];
    }

    // If special date has custom hours
    if ($specialDate && $specialDate['custom_open_time'] && $specialDate['custom_close_time']) {
        return [[
            'service_name' => $specialDate['label'] ?: 'Special',
            'open_time' => $specialDate['custom_open_time'],
            'close_time' => $specialDate['custom_close_time'],
            'first_seating' => $specialDate['custom_first_seating'] ?: $specialDate['custom_open_time'],
            'last_seating' => $specialDate['custom_last_seating'] ?: $specialDate['custom_close_time'],
        ]];
    }

    // Regular operating hours for this day of week
    $dayOfWeek = (int)date('w', strtotime($date)); // 0=Sunday, 6=Saturday
    $stmt = $pdo->prepare(
        "SELECT service_name, open_time, close_time, first_seating, last_seating
         FROM operating_hours
         WHERE restaurant_id = ? AND day_of_week = ? AND is_active = 1
         ORDER BY open_time ASC"
    );
    $stmt->execute([$restaurantId, $dayOfWeek]);
    return $stmt->fetchAll();
}

/**
 * Get available time slots for a date and party size
 *
 * @return array Array of slot objects with time, available_tables count, service_name
 */
function getAvailableSlots($restaurantId, $date, $partySize, $staffMode = false) {
    $pdo = db();

    // Get operating hours for the date
    $servicePeriods = getOperatingHoursForDate($restaurantId, $date);
    if (empty($servicePeriods)) {
        if (!$staffMode) {
            return [];
        }
        // Staff can book on any day — provide default hours
        $servicePeriods = [
            ['service_name' => 'Lunch', 'open_time' => '11:00:00', 'close_time' => '15:00:00',
             'first_seating' => '11:00:00', 'last_seating' => '14:30:00'],
            ['service_name' => 'Dinner', 'open_time' => '17:00:00', 'close_time' => '22:00:00',
             'first_seating' => '17:00:00', 'last_seating' => '21:00:00'],
        ];
    }

    // Get settings
    $slotInterval = (int)getRestaurantSetting($restaurantId, 'time_slot_interval', 30);
    $maxCoversPerSlot = (int)getRestaurantSetting($restaurantId, 'max_covers_per_slot', 0);
    $onlineHoldPercent = (int)getRestaurantSetting($restaurantId, 'online_table_hold_percent', 80);

    // Get total active tables count for this restaurant that fit the party
    $stmt = $pdo->prepare(
        "SELECT COUNT(*) as total FROM tables t
         JOIN sections s ON t.section_id = s.id
         WHERE t.restaurant_id = ? AND t.is_active = 1 AND s.is_active = 1
           AND t.min_seats <= ? AND t.max_seats >= ?"
    );
    $stmt->execute([$restaurantId, $partySize, $partySize]);
    $totalFittingTables = (int)$stmt->fetch()['total'];

    // Max tables available online (overbooking protection)
    $maxOnlineTables = $onlineHoldPercent > 0
        ? (int)floor($totalFittingTables * $onlineHoldPercent / 100)
        : $totalFittingTables;

    $slots = [];

    foreach ($servicePeriods as $period) {
        $serviceName = $period['service_name'];
        list($turnTime, $buffer) = getTurnTime($restaurantId, $partySize, $serviceName);
        $totalOccupancy = $turnTime + $buffer;

        $firstSeating = $period['first_seating'];
        $lastSeating = $period['last_seating'];

        // Generate time slots at the configured interval
        $currentTime = strtotime($firstSeating);
        $endTime = strtotime($lastSeating);

        while ($currentTime <= $endTime) {
            $timeStr = date('H:i:s', $currentTime);

            // Count available tables at this time slot
            $availableTables = countAvailableTables($restaurantId, $date, $timeStr, $partySize, $totalOccupancy);

            // Apply online hold percent cap
            $availableTables = min($availableTables, $maxOnlineTables);

            // Apply max covers per slot
            if ($maxCoversPerSlot > 0) {
                $currentCovers = getCoversAtSlot($restaurantId, $date, $timeStr, $totalOccupancy);
                $remainingCovers = max(0, $maxCoversPerSlot - $currentCovers);
                if ($partySize > $remainingCovers) {
                    $availableTables = 0;
                }
            }

            $slots[] = [
                'time' => $timeStr,
                'time_display' => date('g:ia', $currentTime),
                'available_tables' => $availableTables,
                'service_name' => $serviceName,
                'turn_time' => $turnTime,
            ];

            $currentTime += $slotInterval * 60;
        }
    }

    return $slots;
}

/**
 * Count available tables for a specific time slot
 */
function countAvailableTables($restaurantId, $date, $time, $partySize, $totalOccupancyMinutes) {
    $pdo = db();

    $stmt = $pdo->prepare(
        "SELECT COUNT(*) as cnt
         FROM tables t
         JOIN sections s ON t.section_id = s.id
         WHERE t.restaurant_id = ?
           AND t.is_active = 1
           AND s.is_active = 1
           AND t.max_seats >= ?
           AND t.min_seats <= ?
           AND t.id NOT IN (
               SELECT r.table_id
               FROM reservations r
               WHERE r.restaurant_id = ?
                 AND r.reservation_date = ?
                 AND r.status IN ('pending', 'confirmed', 'seated')
                 AND r.table_id IS NOT NULL
                 AND EXTRACT(EPOCH FROM r.reservation_time) < EXTRACT(EPOCH FROM CAST(? AS time)) + (? * 60)
                 AND EXTRACT(EPOCH FROM r.reservation_time) + (COALESCE(r.turn_time_minutes, ?) * 60) > EXTRACT(EPOCH FROM CAST(? AS time))
           )"
    );
    $stmt->execute([
        $restaurantId,
        $partySize,
        $partySize,
        $restaurantId,
        $date,
        $time, $totalOccupancyMinutes,
        $totalOccupancyMinutes, $time
    ]);

    return (int)$stmt->fetch()['cnt'];
}

/**
 * Get total covers already booked at a time slot (for overbooking protection)
 */
function getCoversAtSlot($restaurantId, $date, $time, $totalOccupancyMinutes) {
    $pdo = db();

    $stmt = $pdo->prepare(
        "SELECT COALESCE(SUM(r.party_size), 0) as covers
         FROM reservations r
         WHERE r.restaurant_id = ?
           AND r.reservation_date = ?
           AND r.status IN ('pending', 'confirmed', 'seated')
           AND EXTRACT(EPOCH FROM r.reservation_time) < EXTRACT(EPOCH FROM CAST(? AS time)) + (? * 60)
           AND EXTRACT(EPOCH FROM r.reservation_time) + (COALESCE(r.turn_time_minutes, ?) * 60) > EXTRACT(EPOCH FROM CAST(? AS time))"
    );
    $stmt->execute([$restaurantId, $date, $time, $totalOccupancyMinutes, $totalOccupancyMinutes, $time]);

    return (int)$stmt->fetch()['covers'];
}

/**
 * Find the best available table for a specific date, time, and party size
 * Uses smallest-table-first logic to avoid wasting large tables on small parties
 *
 * @return int|null table_id or null if no table available
 */
function findAvailableTable($restaurantId, $date, $time, $partySize) {
    $pdo = db();

    list($turnTime, $buffer) = getTurnTime($restaurantId, $partySize);
    $totalOccupancy = $turnTime + $buffer;

    $stmt = $pdo->prepare(
        "SELECT t.id
         FROM tables t
         JOIN sections s ON t.section_id = s.id
         WHERE t.restaurant_id = ?
           AND t.is_active = 1
           AND s.is_active = 1
           AND t.max_seats >= ?
           AND t.min_seats <= ?
           AND t.id NOT IN (
               SELECT r.table_id
               FROM reservations r
               WHERE r.restaurant_id = ?
                 AND r.reservation_date = ?
                 AND r.status IN ('pending', 'confirmed', 'seated')
                 AND r.table_id IS NOT NULL
                 AND EXTRACT(EPOCH FROM r.reservation_time) < EXTRACT(EPOCH FROM CAST(? AS time)) + (? * 60)
                 AND EXTRACT(EPOCH FROM r.reservation_time) + (COALESCE(r.turn_time_minutes, ?) * 60) > EXTRACT(EPOCH FROM CAST(? AS time))
           )
         ORDER BY t.max_seats ASC, t.sort_order ASC
         LIMIT 1"
    );
    $stmt->execute([
        $restaurantId,
        $partySize,
        $partySize,
        $restaurantId,
        $date,
        $time, $totalOccupancy,
        $totalOccupancy, $time
    ]);

    $row = $stmt->fetch();
    return $row ? (int)$row['id'] : null;
}
