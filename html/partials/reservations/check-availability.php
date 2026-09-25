<?php
/**
 * HTMX endpoint: Check availability for a date and party size
 * Returns HTML partial showing available time slots as clickable buttons
 */
require_once '../../../helpers/auth.php';
require_once '../../../helpers/availability.php';

requireAuth();

$restaurantId = currentRestaurantId();
if (!$restaurantId) {
    echo '<div class="alert alert-danger" id="avail-no-restaurant">No restaurant selected.</div>';
    exit;
}

$date = trim($_GET['reservation_date'] ?? $_GET['date'] ?? '');
$partySize = (int)($_GET['party_size'] ?? 0);

if ($date === '' || $partySize < 1) {
    echo '<div class="alert alert-warning" id="avail-missing-params">Please select a date and party size.</div>';
    exit;
}

// Validate date is not in the past
$today = date('Y-m-d');
if ($date < $today) {
    echo '<div class="alert alert-warning" id="avail-past-date">Cannot check availability for past dates.</div>';
    exit;
}

$slots = getAvailableSlots($restaurantId, $date, $partySize, true);

if (empty($slots)) {
    echo '<div class="alert alert-info" id="avail-no-slots">
            <i class="feather-info me-1"></i> No availability on this date. The restaurant may be closed or fully booked.
          </div>';
    exit;
}

$hasAvailable = false;
foreach ($slots as $s) {
    if ($s['available_tables'] > 0) {
        $hasAvailable = true;
        break;
    }
}

if (!$hasAvailable) {
    echo '<div class="alert alert-warning" id="avail-fully-booked">
            <i class="feather-alert-circle me-1"></i> All time slots are fully booked for this date and party size.
          </div>';
    exit;
}

// Group slots by service period
$grouped = [];
foreach ($slots as $s) {
    $grouped[$s['service_name']][] = $s;
}
?>
<div id="avail-results">
    <p class="text-muted mb-2" id="avail-results-label">
        Available slots for <strong><?php echo date('l, M j, Y', strtotime($date)); ?></strong>
        &mdash; Party of <strong><?php echo $partySize; ?></strong>
    </p>

    <?php foreach ($grouped as $serviceName => $serviceSlots): ?>
    <div class="mb-3" id="avail-service-<?php echo htmlspecialchars(strtolower(str_replace(' ', '-', $serviceName))); ?>">
        <h6 class="fw-bold" id="avail-service-title-<?php echo htmlspecialchars(strtolower(str_replace(' ', '-', $serviceName))); ?>">
            <?php echo htmlspecialchars($serviceName); ?>
        </h6>
        <div class="d-flex flex-wrap gap-2" id="avail-slots-<?php echo htmlspecialchars(strtolower(str_replace(' ', '-', $serviceName))); ?>">
            <?php foreach ($serviceSlots as $slot): ?>
            <?php if ($slot['available_tables'] > 0): ?>
            <button type="button"
                    class="btn btn-outline-primary btn-sm avail-slot-btn"
                    data-time="<?php echo htmlspecialchars($slot['time']); ?>"
                    data-display="<?php echo htmlspecialchars($slot['time_display']); ?>"
                    data-turn-time="<?php echo $slot['turn_time']; ?>"
                    onclick="selectTimeSlot(this)"
                    id="avail-slot-<?php echo str_replace(':', '', $slot['time']); ?>">
                <?php echo htmlspecialchars($slot['time_display']); ?>
                <span class="badge bg-success ms-1"><?php echo $slot['available_tables']; ?></span>
            </button>
            <?php else: ?>
            <button type="button"
                    class="btn btn-outline-secondary btn-sm" disabled
                    id="avail-slot-<?php echo str_replace(':', '', $slot['time']); ?>">
                <?php echo htmlspecialchars($slot['time_display']); ?>
                <span class="badge bg-secondary ms-1">0</span>
            </button>
            <?php endif; ?>
            <?php endforeach; ?>
        </div>
    </div>
    <?php endforeach; ?>
</div>

<script>
function selectTimeSlot(btn) {
    // Remove active state from all slot buttons
    document.querySelectorAll('.avail-slot-btn').forEach(function(b) {
        b.classList.remove('btn-primary');
        b.classList.add('btn-outline-primary');
    });
    // Set active state on clicked button
    btn.classList.remove('btn-outline-primary');
    btn.classList.add('btn-primary');

    // Set the hidden time field in the reservation form if it exists
    var timeInput = document.getElementById('res-time');
    if (timeInput) {
        timeInput.value = btn.dataset.time;
    }
    var turnInput = document.getElementById('res-turn-time');
    if (turnInput) {
        turnInput.value = btn.dataset.turnTime;
    }
    // Show the selected time display
    var display = document.getElementById('res-selected-time');
    if (display) {
        display.textContent = btn.dataset.display;
        display.style.display = 'inline';
    }
}
</script>
