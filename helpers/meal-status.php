<?php
/**
 * Meal Status Helper
 *
 * Maps meal progress to estimated remaining time as a percentage of turn time.
 * Percentages are configurable per restaurant via the settings table.
 * Used by floor plan and availability predictions.
 */

require_once __DIR__ . '/db.php';

/** Default percentages (used when no restaurant setting exists) */
function getDefaultMealPercentages(): array
{
    return [
        'seated'        => 0,
        'ordering'      => 15,
        'eating'        => 40,
        'check_dropped' => 80,
        'paid'          => 95,
    ];
}

/** Static labels and colors (not configurable) */
function getMealStatusMeta(): array
{
    return [
        'seated'        => ['label' => 'Seated',        'color' => 'warning'],
        'ordering'      => ['label' => 'Ordering',      'color' => 'info'],
        'eating'        => ['label' => 'Eating',        'color' => 'primary'],
        'check_dropped' => ['label' => 'Check Dropped', 'color' => 'danger'],
        'paid'          => ['label' => 'Paid',          'color' => 'dark'],
    ];
}

/**
 * Load meal status percentages for a restaurant from the settings table.
 * Each status is stored as its own row: setting_key = 'meal_pct_seated', etc.
 * Falls back to defaults if not configured.
 */
function getMealPercentages(int $restaurantId = 0): array
{
    $defaults = getDefaultMealPercentages();

    if ($restaurantId > 0) {
        $keys = [];
        foreach ($defaults as $status => $val) {
            $keys[] = 'meal_pct_' . $status;
        }
        $placeholders = implode(',', array_fill(0, count($keys), '?'));
        $stmt = db()->prepare(
            "SELECT setting_key, setting_value FROM settings
             WHERE restaurant_id = ? AND setting_key IN ({$placeholders})"
        );
        $stmt->execute(array_merge([$restaurantId], $keys));
        $rows = $stmt->fetchAll();

        foreach ($rows as $row) {
            $status = str_replace('meal_pct_', '', $row['setting_key']);
            if (isset($defaults[$status]) && is_numeric($row['setting_value'])) {
                $defaults[$status] = (int)$row['setting_value'];
            }
        }
    }

    return $defaults;
}

/**
 * Meal status definitions: label, color, and % of meal completed.
 * Loads percentages from the database when a restaurant ID is available.
 */
function getMealStatuses(int $restaurantId = 0): array
{
    $meta = getMealStatusMeta();
    $pcts = getMealPercentages($restaurantId);

    $result = [];
    foreach ($meta as $key => $m) {
        $result[$key] = [
            'label' => $m['label'],
            'color' => $m['color'],
            'pct'   => $pcts[$key] ?? 0,
        ];
    }
    return $result;
}

/**
 * Get estimated minutes remaining for a given meal status and turn time.
 */
function estimatedMinutesRemaining(string $mealStatus, int $turnMinutes = 90, int $restaurantId = 0): int
{
    $pcts = getMealPercentages($restaurantId);
    $pct = $pcts[$mealStatus] ?? 0;
    return max(0, (int)round($turnMinutes * (1 - $pct / 100)));
}

/**
 * Get estimated available time for an occupied table.
 */
function estimatedAvailableTime(string $mealStatus, int $turnMinutes = 90, ?string $seatedAt = null, int $restaurantId = 0): string
{
    $remaining = estimatedMinutesRemaining($mealStatus, $turnMinutes, $restaurantId);
    $availableAt = time() + ($remaining * 60);
    return date('g:ia', $availableAt);
}
