<?php
/**
 * Table Status Modal — Quick meal status updates for occupied tables
 */
require_once '../../../helpers/auth.php';
require_once '../../../helpers/csrf.php';
require_once '../../../helpers/meal-status.php';

requireAuth();

$restaurantId = currentRestaurantId();
$tableId = (int)($_GET['table_id'] ?? 0);

if (!$tableId) {
    echo '<div class="alert alert-danger">No table selected.</div>';
    exit;
}

$pdo = db();

// Get table info
$stmt = $pdo->prepare(
    "SELECT t.table_name, t.min_seats, t.max_seats, s.name as section_name
     FROM tables t JOIN sections s ON t.section_id = s.id
     WHERE t.id = ? AND t.restaurant_id = ?"
);
$stmt->execute([$tableId, $restaurantId]);
$table = $stmt->fetch();

if (!$table) {
    echo '<div class="alert alert-danger">Table not found.</div>';
    exit;
}

// Get the seated reservation for this table
$today = date('Y-m-d');
$stmt = $pdo->prepare(
    "SELECT r.id, r.party_size, r.reservation_time, r.seated_at, r.turn_time_minutes,
            r.meal_status, r.confirmation_code, r.special_requests,
            r.ordering_at, r.eating_at, r.check_dropped_at, r.paid_at,
            g.first_name, g.last_name, g.is_active as guest_active
     FROM reservations r
     JOIN guests g ON r.guest_id = g.id
     WHERE r.restaurant_id = ? AND r.table_id = ? AND r.reservation_date = ?
       AND r.status = 'seated'
     ORDER BY r.seated_at DESC LIMIT 1"
);
$stmt->execute([$restaurantId, $tableId, $today]);
$res = $stmt->fetch();

if (!$res) {
    echo '<div class="alert alert-warning">No seated reservation found for this table.</div>';
    exit;
}

$mealStatus = $res['meal_status'] ?: 'seated';
$turnMin = $res['turn_time_minutes'] ?: 90;
$statuses = getMealStatuses($restaurantId);
$currentDef = $statuses[$mealStatus];
$remainingMin = estimatedMinutesRemaining($mealStatus, $turnMin, $restaurantId);
$estTime = estimatedAvailableTime($mealStatus, $turnMin, null, $restaurantId);

// Time seated
$seatedMinAgo = $res['seated_at'] ? max(0, (int)((time() - strtotime($res['seated_at'])) / 60)) : null;

$guestName = ($res['guest_active'] || $res['first_name'] !== 'Walk-in')
    ? htmlspecialchars($res['first_name'] . ' ' . $res['last_name'])
    : 'Walk-in Guest';
?>
<div class="modal fade show d-block" tabindex="-1" id="tablestatus-modal" style="background-color: rgba(0,0,0,0.5);">
    <div class="modal-dialog" id="tablestatus-dialog">
        <div class="modal-content" id="tablestatus-content">
            <div class="modal-header" id="tablestatus-header">
                <h5 class="modal-title" id="tablestatus-title">
                    <?php echo htmlspecialchars($table['table_name']); ?>
                    <span class="badge bg-<?php echo $currentDef['color']; ?> ms-2"><?php echo $currentDef['label']; ?></span>
                </h5>
                <button type="button" class="btn-close" id="tablestatus-close"
                        onclick="document.getElementById('floorplan-modal-container').innerHTML=''"></button>
            </div>
            <div class="modal-body" id="tablestatus-body">

                <!-- Guest & Table Info -->
                <div class="row mb-3" id="tablestatus-info-row">
                    <div class="col-6" id="tablestatus-info-left">
                        <div class="text-muted small">Guest</div>
                        <div class="fw-bold"><?php echo $guestName; ?></div>
                    </div>
                    <div class="col-3" id="tablestatus-info-party">
                        <div class="text-muted small">Party</div>
                        <div class="fw-bold"><?php echo (int)$res['party_size']; ?></div>
                    </div>
                    <div class="col-3" id="tablestatus-info-time">
                        <div class="text-muted small">Seated</div>
                        <div class="fw-bold"><?php echo $seatedMinAgo !== null ? "{$seatedMinAgo}m ago" : '—'; ?></div>
                    </div>
                </div>

                <!-- Estimated Time -->
                <div class="alert alert-light py-2 mb-3" id="tablestatus-estimate">
                    <div class="d-flex justify-content-between align-items-center" id="tablestatus-estimate-flex">
                        <span id="tablestatus-estimate-label">
                            <i class="feather-clock me-1"></i>Est. available in <strong>~<?php echo $remainingMin; ?> min</strong>
                        </span>
                        <span class="text-muted" id="tablestatus-estimate-time">~<?php echo $estTime; ?></span>
                    </div>
                    <!-- Progress bar -->
                    <div class="progress mt-2" style="height: 6px;" id="tablestatus-progress">
                        <div class="progress-bar bg-<?php echo $currentDef['color']; ?>"
                             style="width: <?php echo $currentDef['pct']; ?>%"
                             id="tablestatus-progress-bar"></div>
                    </div>
                </div>

                <!-- Phase Timeline -->
                <?php
                $phases = [
                    ['from' => $res['seated_at'],        'to' => $res['ordering_at'],      'label' => 'Seated'],
                    ['from' => $res['ordering_at'],      'to' => $res['eating_at'],         'label' => 'Ordering'],
                    ['from' => $res['eating_at'],        'to' => $res['check_dropped_at'],  'label' => 'Eating'],
                    ['from' => $res['check_dropped_at'], 'to' => $res['paid_at'],           'label' => 'Check Dropped'],
                ];
                $hasTimeline = $res['ordering_at'] || $res['eating_at'] || $res['check_dropped_at'] || $res['paid_at'];
                ?>
                <?php if ($hasTimeline): ?>
                <div class="mb-3 small" id="tablestatus-timeline">
                    <div class="text-muted fw-bold mb-1" id="tablestatus-timeline-label">Actual Pace</div>
                    <div class="d-flex flex-wrap gap-2" id="tablestatus-timeline-badges">
                        <?php foreach ($phases as $ph):
                            if (!$ph['from']) continue;
                            $end = $ph['to'] ? strtotime($ph['to']) : time();
                            $dur = max(0, (int)(($end - strtotime($ph['from'])) / 60));
                        ?>
                        <span class="badge bg-light text-dark border" id="tablestatus-phase-<?php echo strtolower(str_replace(' ', '-', $ph['label'])); ?>">
                            <?php echo $ph['label']; ?>: <?php echo $dur; ?>m
                        </span>
                        <?php endforeach; ?>
                    </div>
                </div>
                <?php endif; ?>

                <?php if ($res['special_requests']): ?>
                <div class="alert alert-info py-2 mb-3 small" id="tablestatus-requests">
                    <i class="feather-info me-1"></i> <?php echo htmlspecialchars($res['special_requests']); ?>
                </div>
                <?php endif; ?>

                <div id="tablestatus-messages"></div>

                <!-- Meal Status Buttons -->
                <div class="mb-3" id="tablestatus-buttons">
                    <label class="form-label fw-bold" id="tablestatus-buttons-label">Update Status</label>
                    <div class="d-flex flex-wrap gap-2" id="tablestatus-buttons-grid">
                        <?php foreach ($statuses as $key => $def): ?>
                        <button class="btn btn-<?php echo $key === $mealStatus ? $def['color'] : 'outline-' . $def['color']; ?> btn-sm flex-fill"
                                id="tablestatus-btn-<?php echo $key; ?>"
                                <?php if ($key !== $mealStatus): ?>
                                hx-post="/partials/tables/update-meal-status.php"
                                hx-vals='<?php echo json_encode([
                                    'reservation_id' => $res['id'],
                                    'table_id' => $tableId,
                                    'meal_status' => $key,
                                    'csrf_token' => generate_csrf_token()
                                ]); ?>'
                                hx-target="#floorplan-modal-container"
                                hx-swap="innerHTML"
                                <?php else: ?>
                                disabled
                                <?php endif; ?>>
                            <?php echo $def['label']; ?>
                        </button>
                        <?php endforeach; ?>
                    </div>
                </div>

                <!-- Action Buttons -->
                <div class="d-flex gap-2 border-top pt-3" id="tablestatus-actions">
                    <button class="btn btn-primary btn-sm flex-fill" id="tablestatus-done-btn"
                            hx-get="/partials/tables/floor-plan.php"
                            hx-target="#page-content"
                            hx-swap="innerHTML">
                        <i class="feather-check me-1"></i> Done
                    </button>
                    <button class="btn btn-outline-success btn-sm" id="tablestatus-complete-btn"
                            hx-post="/partials/tables/update-meal-status.php"
                            hx-vals='<?php echo json_encode([
                                'reservation_id' => $res['id'],
                                'table_id' => $tableId,
                                'action' => 'complete',
                                'csrf_token' => generate_csrf_token()
                            ]); ?>'
                            hx-target="#page-content"
                            hx-swap="innerHTML"
                            hx-confirm="Complete this reservation and open the table?">
                        <i class="feather-check-circle me-1"></i> Open Table
                    </button>
                    <button class="btn btn-outline-secondary btn-sm" id="tablestatus-detail-btn"
                            hx-get="/partials/reservations/detail.php?id=<?php echo $res['id']; ?>"
                            hx-target="#page-content" hx-swap="innerHTML">
                        <i class="feather-eye me-1"></i> Details
                    </button>
                </div>

            </div>
        </div>
    </div>
</div>
