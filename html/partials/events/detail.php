<?php
/**
 * Event Detail — Info card + reservation list + add booking
 */
require_once __DIR__ . '/../../../helpers/auth.php';
require_once __DIR__ . '/../../../helpers/db.php';
require_once __DIR__ . '/../../../helpers/csrf.php';

requireAuth();
$restaurantId = currentRestaurantId();
$pdo = db();

$eventId = (int)($_GET['id'] ?? 0);
if ($eventId < 1) {
    echo '<div class="alert alert-danger" id="event-detail-error">Invalid event ID.</div>';
    exit;
}

$stmt = $pdo->prepare("SELECT * FROM events WHERE id = ? AND restaurant_id = ?");
$stmt->execute([$eventId, $restaurantId]);
$event = $stmt->fetch();

if (!$event) {
    echo '<div class="alert alert-warning" id="event-detail-not-found">Event not found.</div>';
    exit;
}

// Get bookings for this event
$stmt = $pdo->prepare(
    "SELECT r.id, r.party_size, r.status, r.confirmation_code, r.special_requests, r.created_at,
            g.first_name, g.last_name, g.phone, g.email
     FROM reservations r
     JOIN guests g ON r.guest_id = g.id
     WHERE r.event_id = ? AND r.restaurant_id = ?
     ORDER BY r.created_at DESC"
);
$stmt->execute([$eventId, $restaurantId]);
$bookings = $stmt->fetchAll();

// Stats
$totalCovers = 0;
$activeBookings = 0;
foreach ($bookings as $b) {
    if (!in_array($b['status'], ['cancelled', 'no_show'])) {
        $totalCovers += (int)$b['party_size'];
        $activeBookings++;
    }
}
$spotsLeft = max(0, (int)$event['max_capacity'] - $totalCovers);
$pct = $event['max_capacity'] > 0 ? min(100, round(($totalCovers / $event['max_capacity']) * 100)) : 0;
$barColor = $pct >= 90 ? 'danger' : ($pct >= 70 ? 'warning' : 'success');

$statusColors = [
    'draft' => 'secondary', 'published' => 'success',
    'cancelled' => 'danger', 'completed' => 'dark',
];
$resStatusColors = [
    'pending' => 'warning', 'confirmed' => 'primary', 'seated' => 'success',
    'completed' => 'secondary', 'no_show' => 'danger', 'cancelled' => 'dark',
];
?>

<div id="event-detail-main">
    <div class="d-flex justify-content-between align-items-center mb-4" id="event-detail-header">
        <div id="event-detail-title-wrap">
            <h4 class="mb-0" id="event-detail-name">
                <?php echo htmlspecialchars($event['name']); ?>
                <span class="badge bg-<?php echo $statusColors[$event['status']] ?? 'secondary'; ?> ms-2" id="event-detail-status">
                    <?php echo ucfirst($event['status']); ?>
                </span>
            </h4>
            <small class="text-muted" id="event-detail-date">
                <?php echo date('l, F j, Y', strtotime($event['event_date'])); ?>
                &middot;
                <?php echo date('g:ia', strtotime($event['start_time'])); ?>
                <?php if ($event['end_time']): ?>– <?php echo date('g:ia', strtotime($event['end_time'])); ?><?php endif; ?>
            </small>
        </div>
        <div id="event-detail-actions">
            <button class="btn btn-outline-primary btn-sm" id="event-detail-edit-btn"
                    hx-get="/partials/events/form.php?id=<?php echo $eventId; ?>"
                    hx-target="#event-detail-modal-container"
                    hx-swap="innerHTML">
                <i class="feather-edit me-1"></i> Edit
            </button>
            <?php if (in_array($event['status'], ['draft', 'published'])): ?>
            <button class="btn btn-outline-danger btn-sm" id="event-detail-cancel-btn"
                    hx-post="/partials/events/save.php"
                    hx-vals='<?php echo json_encode(["action" => "cancel", "id" => $eventId, "csrf_token" => generate_csrf_token()]); ?>'
                    hx-target="#page-content"
                    hx-swap="innerHTML"
                    hx-confirm="Cancel this event? Existing bookings will remain.">
                <i class="feather-x-circle me-1"></i> Cancel Event
            </button>
            <?php endif; ?>
            <button class="btn btn-outline-secondary btn-sm" id="event-detail-back-btn"
                    hx-get="/partials/events/list.php"
                    hx-target="#page-content">
                <i class="feather-arrow-left me-1"></i> Back
            </button>
        </div>
    </div>

    <div id="event-detail-messages"></div>
    <div id="event-detail-modal-container"></div>

    <div class="row" id="event-detail-row">
        <!-- Left: Info -->
        <div class="col-lg-4" id="event-detail-left">
            <div class="card mb-3" id="event-info-card">
                <div class="card-body" id="event-info-body">
                    <h6 class="fw-bold mb-3" id="event-info-title">Event Details</h6>

                    <?php if ($event['description']): ?>
                    <p class="small" id="event-info-desc"><?php echo nl2br(htmlspecialchars($event['description'])); ?></p>
                    <?php endif; ?>

                    <table class="table table-borderless table-sm mb-0" id="event-info-table">
                        <tr id="event-info-date-row">
                            <td class="text-muted" style="width:90px;">Date</td>
                            <td><?php echo date('M j, Y', strtotime($event['event_date'])); ?></td>
                        </tr>
                        <tr id="event-info-time-row">
                            <td class="text-muted">Time</td>
                            <td>
                                <?php echo date('g:ia', strtotime($event['start_time'])); ?>
                                <?php if ($event['end_time']): ?>– <?php echo date('g:ia', strtotime($event['end_time'])); ?><?php endif; ?>
                            </td>
                        </tr>
                        <tr id="event-info-price-row">
                            <td class="text-muted">Price</td>
                            <td>
                                <?php echo $event['price_per_person'] !== null
                                    ? '$' . number_format((float)$event['price_per_person'], 2) . ' / person'
                                    : 'Free'; ?>
                            </td>
                        </tr>
                        <tr id="event-info-public-row">
                            <td class="text-muted">Visibility</td>
                            <td><?php echo $event['is_public'] ? 'Public' : 'Private'; ?></td>
                        </tr>
                    </table>
                </div>
            </div>

            <!-- Capacity -->
            <div class="card mb-3" id="event-capacity-card">
                <div class="card-body" id="event-capacity-body">
                    <h6 class="fw-bold mb-3" id="event-capacity-title">Capacity</h6>
                    <div class="row text-center mb-3" id="event-capacity-stats">
                        <div class="col-4" id="event-cap-booked">
                            <div class="fs-4 fw-bold text-primary"><?php echo $totalCovers; ?></div>
                            <small class="text-muted">Booked</small>
                        </div>
                        <div class="col-4" id="event-cap-left">
                            <div class="fs-4 fw-bold <?php echo $spotsLeft === 0 ? 'text-danger' : 'text-success'; ?>"><?php echo $spotsLeft; ?></div>
                            <small class="text-muted">Available</small>
                        </div>
                        <div class="col-4" id="event-cap-total">
                            <div class="fs-4 fw-bold text-muted"><?php echo (int)$event['max_capacity']; ?></div>
                            <small class="text-muted">Total</small>
                        </div>
                    </div>
                    <div class="progress" style="height:8px;" id="event-capacity-bar">
                        <div class="progress-bar bg-<?php echo $barColor; ?>" style="width:<?php echo $pct; ?>%"></div>
                    </div>
                </div>
            </div>

            <?php if ($event['notes']): ?>
            <div class="card mb-3" id="event-notes-card">
                <div class="card-body" id="event-notes-body">
                    <h6 class="fw-bold mb-2" id="event-notes-title">Staff Notes</h6>
                    <p class="small mb-0" id="event-notes-text"><?php echo nl2br(htmlspecialchars($event['notes'])); ?></p>
                </div>
            </div>
            <?php endif; ?>
        </div>

        <!-- Right: Bookings -->
        <div class="col-lg-8" id="event-detail-right">
            <div class="card mb-3" id="event-bookings-card">
                <div class="card-header d-flex justify-content-between align-items-center" id="event-bookings-header">
                    <h6 class="mb-0" id="event-bookings-title">Bookings (<?php echo $activeBookings; ?>)</h6>
                    <?php if ($spotsLeft > 0 && in_array($event['status'], ['draft', 'published'])): ?>
                    <button class="btn btn-sm btn-primary" id="event-add-booking-btn"
                            hx-get="/partials/events/book.php?event_id=<?php echo $eventId; ?>"
                            hx-target="#event-detail-modal-container"
                            hx-swap="innerHTML">
                        <i class="feather-plus me-1"></i> Add Booking
                    </button>
                    <?php endif; ?>
                </div>
                <div class="card-body p-0" id="event-bookings-body">
                    <?php if (empty($bookings)): ?>
                    <div class="text-center py-4 text-muted" id="event-bookings-empty">
                        No bookings yet.
                    </div>
                    <?php else: ?>
                    <div class="table-responsive" id="event-bookings-table-wrap">
                        <table class="table table-hover mb-0" id="event-bookings-table">
                            <thead id="event-bookings-thead">
                                <tr>
                                    <th>Guest</th>
                                    <th>Party</th>
                                    <th>Phone</th>
                                    <th>Status</th>
                                    <th>Code</th>
                                    <th>Requests</th>
                                </tr>
                            </thead>
                            <tbody id="event-bookings-tbody">
                                <?php foreach ($bookings as $b): ?>
                                <tr id="event-booking-<?php echo $b['id']; ?>" style="cursor:pointer;"
                                    hx-get="/partials/reservations/detail.php?id=<?php echo $b['id']; ?>"
                                    hx-target="#page-content">
                                    <td class="fw-semibold"><?php echo htmlspecialchars($b['first_name'] . ' ' . $b['last_name']); ?></td>
                                    <td><?php echo (int)$b['party_size']; ?></td>
                                    <td><?php echo htmlspecialchars($b['phone'] ?? '—'); ?></td>
                                    <td>
                                        <span class="badge bg-<?php echo $resStatusColors[$b['status']] ?? 'secondary'; ?>">
                                            <?php echo ucfirst($b['status']); ?>
                                        </span>
                                    </td>
                                    <td><code><?php echo htmlspecialchars($b['confirmation_code']); ?></code></td>
                                    <td class="small text-muted"><?php echo htmlspecialchars(mb_strimwidth($b['special_requests'] ?? '', 0, 40, '...')); ?></td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>
