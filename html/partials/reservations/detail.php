<?php
/**
 * Single Reservation Detail View
 */
require_once '../../../helpers/auth.php';
require_once '../../../helpers/csrf.php';

requireAuth();

$restaurantId = currentRestaurantId();
$pdo = db();

// Get restaurant slug for voice agent context
$slugStmt = $pdo->prepare("SELECT slug FROM restaurants WHERE id = ?");
$slugStmt->execute([$restaurantId]);
$restaurantSlug = ($slugStmt->fetch())['slug'] ?? '';

$resId = (int)($_GET['id'] ?? 0);

if (!$resId) {
    echo '<div class="alert alert-danger" id="detail-invalid">Invalid reservation.</div>';
    exit;
}

// Fetch reservation with guest and table info
$stmt = db()->prepare(
    "SELECT r.*, g.first_name, g.last_name, g.phone as guest_phone, g.email as guest_email, g.id as guest_id,
            g.tags as guest_tags, g.visit_count, g.noshow_count,
            t.table_name, s.name as section_name,
            u.first_name as created_by_first, u.last_name as created_by_last
     FROM reservations r
     JOIN guests g ON r.guest_id = g.id
     LEFT JOIN tables t ON r.table_id = t.id
     LEFT JOIN sections s ON t.section_id = s.id
     LEFT JOIN users u ON r.created_by = u.id
     WHERE r.id = ? AND r.restaurant_id = ?"
);
$stmt->execute([$resId, $restaurantId]);
$res = $stmt->fetch();

if (!$res) {
    echo '<div class="alert alert-danger" id="detail-notfound">Reservation not found.</div>';
    exit;
}

// Get tables for reassignment dropdown
$stmt = db()->prepare(
    "SELECT t.id, t.table_name, t.min_seats, t.max_seats, sc.name as section_name
     FROM tables t JOIN sections sc ON t.section_id = sc.id
     WHERE t.restaurant_id = ? AND t.is_active = 1 AND sc.is_active = 1
     ORDER BY sc.display_order ASC, t.sort_order ASC"
);
$stmt->execute([$restaurantId]);
$tables = $stmt->fetchAll();

// Get activity log for this reservation
$stmt = db()->prepare(
    "SELECT al.*, u.first_name as user_first, u.last_name as user_last
     FROM activity_log al
     LEFT JOIN users u ON al.user_id = u.id
     WHERE al.restaurant_id = ? AND al.entity_type = 'reservation' AND al.entity_id = ?
     ORDER BY al.created_at DESC LIMIT 20"
);
$stmt->execute([$restaurantId, $resId]);
$activities = $stmt->fetchAll();

// Status transition map
$transitions = [
    'pending' => ['confirmed', 'cancelled'],
    'confirmed' => ['seated', 'cancelled', 'no_show'],
    'seated' => ['completed'],
    'completed' => [],
    'no_show' => [],
    'cancelled' => [],
];

$statusColors = [
    'pending' => 'warning',
    'confirmed' => 'primary',
    'seated' => 'success',
    'completed' => 'secondary',
    'no_show' => 'danger',
    'cancelled' => 'dark',
];

$badgeColor = $statusColors[$res['status']] ?? 'secondary';
$allowedTransitions = $transitions[$res['status']] ?? [];

$transitionLabels = [
    'confirmed' => ['Confirm', 'feather-check', 'primary'],
    'seated' => ['Seat', 'feather-log-in', 'success'],
    'completed' => ['Complete', 'feather-check-circle', 'secondary'],
    'cancelled' => ['Cancel', 'feather-x-circle', 'dark'],
    'no_show' => ['No Show', 'feather-user-x', 'danger'],
];
?>
<div class="main-content" id="detail-main">
    <div class="row" id="detail-row">
        <div class="col-xxl-8 col-xl-10 col-12" id="detail-col">

            <!-- Back navigation -->
            <div class="d-flex flex-wrap gap-2 mb-3" id="detail-nav">
                <button class="btn btn-outline-secondary btn-sm me-2" id="detail-back-cal"
                        hx-get="/partials/reservations/calendar.php?date=<?php echo $res['reservation_date']; ?>"
                        hx-target="#page-content" hx-swap="innerHTML">
                    <i class="feather-arrow-left me-1"></i> Back to Calendar
                </button>
                <button class="btn btn-outline-secondary btn-sm" id="detail-back-list"
                        hx-get="/partials/reservations/list.php"
                        hx-target="#page-content" hx-swap="innerHTML">
                    <i class="feather-list me-1"></i> List View
                </button>
            </div>

            <div id="detail-messages"></div>

            <!-- Main Info Card -->
            <div class="card mb-3" id="detail-info-card">
                <div class="card-header d-flex justify-content-between align-items-center" id="detail-info-header">
                    <h5 class="card-title mb-0" id="detail-info-title">
                        Reservation <code><?php echo htmlspecialchars($res['confirmation_code']); ?></code>
                    </h5>
                    <div id="detail-status-area">
                        <span class="badge bg-<?php echo $badgeColor; ?> fs-6" id="detail-status-badge">
                            <?php echo ucfirst(str_replace('_', ' ', $res['status'])); ?>
                        </span>
                    </div>
                </div>
                <div class="card-body" id="detail-info-body">
                    <div class="row" id="detail-info-row">
                        <div class="col-md-6" id="detail-info-left">
                            <table class="table table-borderless table-sm mb-0" id="detail-info-table">
                                <tr id="detail-date-row"><td class="text-muted" style="width:140px;">Date</td><td><strong><?php echo date('l, M j, Y', strtotime($res['reservation_date'])); ?></strong></td></tr>
                                <tr id="detail-time-row"><td class="text-muted">Time</td><td><strong><?php echo date('g:ia', strtotime($res['reservation_time'])); ?></strong></td></tr>
                                <tr id="detail-party-row"><td class="text-muted">Party Size</td><td><strong><?php echo (int)$res['party_size']; ?></strong></td></tr>
                                <tr id="detail-table-row"><td class="text-muted">Table</td><td><?php echo $res['table_name'] ? htmlspecialchars($res['table_name'] . ' (' . $res['section_name'] . ')') : '<span class="text-muted">Unassigned</span>'; ?></td></tr>
                                <tr id="detail-source-row"><td class="text-muted">Source</td><td><?php echo ucfirst($res['source']); ?></td></tr>
                                <tr id="detail-created-row"><td class="text-muted">Created</td><td><?php echo date('M j, Y g:ia', strtotime($res['created_at'])); ?><?php if ($res['created_by_first']): ?> by <?php echo htmlspecialchars($res['created_by_first'] . ' ' . $res['created_by_last']); ?><?php endif; ?></td></tr>
                                <?php if ($res['turn_time_minutes']): ?>
                                <tr id="detail-turn-row"><td class="text-muted">Turn Time</td><td><?php echo (int)$res['turn_time_minutes']; ?> min</td></tr>
                                <?php endif; ?>
                                <?php if ($res['seated_at']): ?>
                                <tr id="detail-seated-row"><td class="text-muted">Seated At</td><td><?php echo date('g:ia', strtotime($res['seated_at'])); ?></td></tr>
                                <?php endif; ?>
                                <?php if ($res['ordering_at']): ?>
                                <tr id="detail-ordering-row"><td class="text-muted">Ordering At</td><td><?php echo date('g:ia', strtotime($res['ordering_at'])); ?></td></tr>
                                <?php endif; ?>
                                <?php if ($res['eating_at']): ?>
                                <tr id="detail-eating-row"><td class="text-muted">Eating At</td><td><?php echo date('g:ia', strtotime($res['eating_at'])); ?></td></tr>
                                <?php endif; ?>
                                <?php if ($res['check_dropped_at']): ?>
                                <tr id="detail-checkdropped-row"><td class="text-muted">Check Dropped At</td><td><?php echo date('g:ia', strtotime($res['check_dropped_at'])); ?></td></tr>
                                <?php endif; ?>
                                <?php if ($res['paid_at']): ?>
                                <tr id="detail-paid-row"><td class="text-muted">Paid At</td><td><?php echo date('g:ia', strtotime($res['paid_at'])); ?></td></tr>
                                <?php endif; ?>
                                <?php if ($res['completed_at']): ?>
                                <tr id="detail-completed-row"><td class="text-muted">Completed At</td><td><?php echo date('g:ia', strtotime($res['completed_at'])); ?></td></tr>
                                <?php endif; ?>
                                <?php if ($res['cancelled_at']): ?>
                                <tr id="detail-cancelled-row"><td class="text-muted">Cancelled At</td><td><?php echo date('M j g:ia', strtotime($res['cancelled_at'])); ?></td></tr>
                                <?php endif; ?>
                            </table>
                        </div>
                        <div class="col-md-6" id="detail-info-right">
                            <!-- Guest Info -->
                            <div class="card bg-light" id="detail-guest-card">
                                <div class="card-body py-2" id="detail-guest-body">
                                    <h6 class="fw-bold mb-2" id="detail-guest-title">
                                        <i class="feather-user me-1"></i> Guest
                                        <a href="#" class="btn btn-outline-primary btn-sm float-end"
                                           hx-get="/partials/guests/detail.php?id=<?php echo $res['guest_id']; ?>"
                                           hx-target="#page-content" hx-swap="innerHTML"
                                           id="detail-guest-link">Profile</a>
                                    </h6>
                                    <p class="mb-1" id="detail-guest-name"><strong><?php echo htmlspecialchars($res['first_name'] . ' ' . $res['last_name']); ?></strong></p>
                                    <?php if ($res['guest_phone']): ?>
                                    <p class="mb-1 small" id="detail-guest-phone"><i class="feather-phone me-1"></i><?php echo htmlspecialchars($res['guest_phone']); ?></p>
                                    <?php endif; ?>
                                    <?php if ($res['guest_email']): ?>
                                    <p class="mb-1 small" id="detail-guest-email"><i class="feather-mail me-1"></i><?php echo htmlspecialchars($res['guest_email']); ?></p>
                                    <?php endif; ?>
                                    <p class="mb-0 small text-muted" id="detail-guest-stats">
                                        Visits: <?php echo (int)$res['visit_count']; ?> | No-shows: <?php echo (int)$res['noshow_count']; ?>
                                        <?php if ($res['guest_tags']): ?>
                                        <?php foreach (explode(',', $res['guest_tags']) as $tag): ?>
                                        <span class="badge bg-info text-dark ms-1"><?php echo htmlspecialchars(trim($tag)); ?></span>
                                        <?php endforeach; ?>
                                        <?php endif; ?>
                                    </p>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Status Actions -->
            <?php if (!empty($allowedTransitions)): ?>
            <div class="card mb-3" id="detail-actions-card">
                <div class="card-body py-3" id="detail-actions-body">
                    <div class="d-flex flex-wrap gap-2 align-items-center" id="detail-actions-flex">
                        <span class="fw-bold me-2" id="detail-actions-label">Actions:</span>
                        <?php foreach ($allowedTransitions as $nextStatus):
                            $label = $transitionLabels[$nextStatus] ?? [$nextStatus, 'feather-arrow-right', 'secondary'];
                        ?>
                        <button class="btn btn-<?php echo $label[2]; ?> btn-sm" id="detail-action-<?php echo $nextStatus; ?>"
                                hx-post="/partials/reservations/status-update.php"
                                hx-vals='{"reservation_id": <?php echo $resId; ?>, "new_status": "<?php echo $nextStatus; ?>", "csrf_token": "<?php echo htmlspecialchars(generate_csrf_token()); ?>"}'
                                hx-target="#page-content" hx-swap="innerHTML"
                                hx-confirm="Change status to <?php echo ucfirst(str_replace('_', ' ', $nextStatus)); ?>?"
                                >
                            <i class="<?php echo $label[1]; ?> me-1"></i> <?php echo $label[0]; ?>
                        </button>
                        <?php endforeach; ?>

                        <span class="ms-auto d-flex gap-2" id="detail-edit-btn-wrapper">
                            <button class="btn btn-retell-call btn-sm d-none" id="detail-call-btn"
                                    data-retell-call
                                    data-call-title="Call about Reservation"
                                    data-call-subtitle="<?php echo htmlspecialchars($res['first_name'] . ' ' . $res['last_name']); ?>"
                                    data-guest-name="<?php echo htmlspecialchars($res['first_name'] . ' ' . $res['last_name']); ?>"
                                    data-guest-phone="<?php echo htmlspecialchars($res['guest_phone'] ?? ''); ?>"
                                    data-guest-email="<?php echo htmlspecialchars($res['guest_email'] ?? ''); ?>"
                                    data-party-size="<?php echo (int)$res['party_size']; ?>"
                                    data-reservation-date="<?php echo htmlspecialchars($res['reservation_date']); ?>"
                                    data-reservation-time="<?php echo date('g:ia', strtotime($res['reservation_time'])); ?>"
                                    data-confirmation-code="<?php echo htmlspecialchars($res['confirmation_code']); ?>"
                                    data-reservation-status="<?php echo htmlspecialchars($res['status']); ?>"
                                    data-special-requests="<?php echo htmlspecialchars($res['special_requests'] ?? ''); ?>"
                                    data-restaurant-slug="<?php echo htmlspecialchars($restaurantSlug); ?>">
                                <i class="feather-phone me-1"></i> Call AI Agent
                            </button>
                            <button class="btn btn-outline-primary btn-sm" id="detail-edit-btn"
                                    hx-get="/partials/reservations/edit-form.php?id=<?php echo $resId; ?>"
                                    hx-target="#detail-edit-container" hx-swap="innerHTML">
                                <i class="feather-edit me-1"></i> Edit
                            </button>
                        </span>
                    </div>
                </div>
            </div>
            <?php endif; ?>

            <!-- Edit form container -->
            <div id="detail-edit-container"></div>

            <!-- Table Reassignment -->
            <div class="card mb-3" id="detail-reassign-card">
                <div class="card-body py-3" id="detail-reassign-body">
                    <div class="d-flex align-items-center gap-2" id="detail-reassign-flex">
                        <label class="fw-bold" for="detail-reassign-select" id="detail-reassign-label">Table:</label>
                        <select class="form-select form-select-sm d-inline-block w-auto" id="detail-reassign-select"
                                hx-post="/partials/reservations/update.php"
                                hx-vals='{"reservation_id": <?php echo $resId; ?>, "action": "reassign_table", "csrf_token": "<?php echo htmlspecialchars(generate_csrf_token()); ?>"}'
                                hx-target="#detail-messages" hx-swap="innerHTML"
                                hx-trigger="change"
                                name="table_id">
                            <option value="">Unassigned</option>
                            <?php foreach ($tables as $tbl): ?>
                            <option value="<?php echo $tbl['id']; ?>" <?php echo ((int)$res['table_id'] === (int)$tbl['id']) ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($tbl['table_name']); ?> (<?php echo htmlspecialchars($tbl['section_name']); ?>, <?php echo $tbl['min_seats']; ?>-<?php echo $tbl['max_seats']; ?>)
                            </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
            </div>

            <!-- Notes -->
            <div class="row mb-3" id="detail-notes-row">
                <div class="col-md-6" id="detail-requests-col">
                    <div class="card h-100" id="detail-requests-card">
                        <div class="card-header py-2" id="detail-requests-header"><h6 class="mb-0">Special Requests</h6></div>
                        <div class="card-body" id="detail-requests-body">
                            <textarea class="form-control" id="detail-requests-textarea" rows="3"
                                      hx-post="/partials/reservations/update.php"
                                      hx-vals='{"reservation_id": <?php echo $resId; ?>, "action": "update_requests", "csrf_token": "<?php echo htmlspecialchars(generate_csrf_token()); ?>"}'
                                      hx-target="#detail-messages" hx-swap="innerHTML"
                                      hx-trigger="change"
                                      name="special_requests"><?php echo htmlspecialchars($res['special_requests'] ?? ''); ?></textarea>
                        </div>
                    </div>
                </div>
                <div class="col-md-6" id="detail-notes-col">
                    <div class="card h-100" id="detail-notes-card">
                        <div class="card-header py-2" id="detail-notes-header"><h6 class="mb-0">Internal Notes (staff only)</h6></div>
                        <div class="card-body" id="detail-notes-body">
                            <textarea class="form-control" id="detail-notes-textarea" rows="3"
                                      hx-post="/partials/reservations/update.php"
                                      hx-vals='{"reservation_id": <?php echo $resId; ?>, "action": "update_notes", "csrf_token": "<?php echo htmlspecialchars(generate_csrf_token()); ?>"}'
                                      hx-target="#detail-messages" hx-swap="innerHTML"
                                      hx-trigger="change"
                                      name="internal_notes"><?php echo htmlspecialchars($res['internal_notes'] ?? ''); ?></textarea>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Activity Timeline -->
            <?php if (!empty($activities)): ?>
            <div class="card" id="detail-activity-card">
                <div class="card-header py-2" id="detail-activity-header"><h6 class="mb-0"><i class="feather-clock me-1"></i>Activity History</h6></div>
                <div class="card-body" id="detail-activity-body">
                    <div class="list-group list-group-flush" id="detail-activity-list">
                        <?php foreach ($activities as $act): ?>
                        <div class="list-group-item px-0 py-2" id="detail-activity-<?php echo $act['id']; ?>">
                            <div class="d-flex justify-content-between" id="detail-activity-row-<?php echo $act['id']; ?>">
                                <div>
                                    <span class="badge bg-secondary me-1"><?php echo htmlspecialchars($act['action']); ?></span>
                                    <?php echo htmlspecialchars($act['description'] ?? ''); ?>
                                    <?php if ($act['user_first']): ?>
                                    <small class="text-muted">by <?php echo htmlspecialchars($act['user_first'] . ' ' . $act['user_last']); ?></small>
                                    <?php endif; ?>
                                </div>
                                <small class="text-muted"><?php echo date('M j g:ia', strtotime($act['created_at'])); ?></small>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
            <?php endif; ?>

        </div>
    </div>
</div>
