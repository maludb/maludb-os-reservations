<?php
/**
 * Public availability endpoint — no auth required
 * Uses restaurant slug to identify restaurant
 */
require_once '../../helpers/db.php';
require_once '../../helpers/restaurant.php';
require_once '../../helpers/availability.php';

$slug = trim($_GET['restaurant'] ?? '');
if ($slug === '') {
    echo '<div class="alert alert-danger" id="slots-no-slug">Restaurant not specified.</div>';
    exit;
}

$restaurant = getRestaurantBySlug($slug);
if (!$restaurant || !$restaurant['is_active']) {
    echo '<div class="alert alert-danger" id="slots-not-found">Restaurant not found.</div>';
    exit;
}

$restaurantId = (int)$restaurant['id'];
applyRestaurantTimezone($restaurantId);
$date = trim($_GET['date'] ?? '');
$partySize = (int)($_GET['party_size'] ?? 0);

if ($date === '' || $partySize < 1) {
    echo '<div class="alert alert-warning" id="slots-missing">Please select a date and party size.</div>';
    exit;
}

// Load settings for public booking rules
$maxParty = (int)getRestaurantSetting($restaurantId, 'max_online_party_size', 8);
$minHours = (int)getRestaurantSetting($restaurantId, 'advance_booking_min_hours', 2);
$maxDays = (int)getRestaurantSetting($restaurantId, 'advance_booking_max_days', 60);
$sameDayCutoff = getRestaurantSetting($restaurantId, 'same_day_cutoff_time', '20:00');

// Validate party size
if ($partySize > $maxParty) {
    echo '<div class="alert alert-warning" id="slots-party-limit">
            Online booking is limited to ' . $maxParty . ' guests. For larger parties, please call us'
            . ($restaurant['phone'] ? ' at ' . htmlspecialchars($restaurant['phone']) : '') . '.
          </div>';
    exit;
}

// Validate date range
$today = date('Y-m-d');
if ($date < $today) {
    echo '<div class="alert alert-warning" id="slots-past">Cannot book for past dates.</div>';
    exit;
}

$maxDate = date('Y-m-d', strtotime("+{$maxDays} days"));
if ($date > $maxDate) {
    echo '<div class="alert alert-warning" id="slots-too-far">Booking is available up to ' . $maxDays . ' days in advance.</div>';
    exit;
}

// Same-day cutoff
if ($date === $today) {
    $now = date('H:i');
    if ($now >= $sameDayCutoff) {
        echo '<div class="alert alert-warning" id="slots-cutoff">Same-day online booking is closed after ' . date('g:ia', strtotime($sameDayCutoff)) . '. Please call us.</div>';
        exit;
    }
}

// Get available slots
$slots = getAvailableSlots($restaurantId, $date, $partySize);

if (empty($slots)) {
    echo '<div class="alert alert-info" id="slots-closed">No availability on this date. The restaurant may be closed.</div>';
    exit;
}

// Filter out slots that violate min advance booking hours
$minBookingTime = date('H:i:s', strtotime("+{$minHours} hours"));
if ($date === $today) {
    $slots = array_filter($slots, function($s) use ($minBookingTime) {
        return $s['time'] >= $minBookingTime;
    });
}

// Check if any available
$available = array_filter($slots, function($s) { return $s['available_tables'] > 0; });

if (empty($available)) {
    echo '<div class="alert alert-warning" id="slots-full">No available time slots for this date and party size.</div>';
    exit;
}

// Group by service period
$grouped = [];
foreach ($slots as $s) {
    $grouped[$s['service_name']][] = $s;
}
?>
<div id="slots-results" class="mt-2">
    <p class="text-muted small" id="slots-results-label">
        <?php echo date('l, F j, Y', strtotime($date)); ?> &mdash; <?php echo $partySize; ?> guest<?php echo $partySize > 1 ? 's' : ''; ?>
    </p>

    <?php foreach ($grouped as $serviceName => $serviceSlots): ?>
    <div class="mb-3" id="slots-service-<?php echo htmlspecialchars(strtolower(str_replace(' ', '-', $serviceName))); ?>">
        <h6 class="fw-bold" id="slots-service-title-<?php echo htmlspecialchars(strtolower(str_replace(' ', '-', $serviceName))); ?>">
            <?php echo htmlspecialchars($serviceName); ?>
        </h6>
        <div class="d-flex flex-wrap gap-2" id="slots-grid-<?php echo htmlspecialchars(strtolower(str_replace(' ', '-', $serviceName))); ?>">
            <?php foreach ($serviceSlots as $slot): ?>
            <?php if ($slot['available_tables'] > 0): ?>
            <button type="button"
                    class="btn btn-outline-primary slot-btn"
                    data-time="<?php echo htmlspecialchars($slot['time']); ?>"
                    data-display="<?php echo htmlspecialchars($slot['time_display']); ?>"
                    onclick="selectPublicSlot(this)"
                    id="slot-<?php echo str_replace(':', '', $slot['time']); ?>">
                <?php echo htmlspecialchars($slot['time_display']); ?>
            </button>
            <?php else: ?>
            <button type="button" class="btn btn-outline-secondary" disabled
                    id="slot-<?php echo str_replace(':', '', $slot['time']); ?>">
                <?php echo htmlspecialchars($slot['time_display']); ?>
            </button>
            <?php endif; ?>
            <?php endforeach; ?>
        </div>
    </div>
    <?php endforeach; ?>
</div>
