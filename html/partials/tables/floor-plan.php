<?php
/**
 * Visual Floor Plan — Tables grouped by section with real-time status
 */
require_once '../../../helpers/auth.php';
require_once '../../../helpers/csrf.php';
require_once '../../../helpers/availability.php';
require_once '../../../helpers/meal-status.php';

requireAuth();

$restaurantId = currentRestaurantId();
$today = date('Y-m-d');
$now = date('H:i:s');

// Get all active sections with their tables
$stmt = db()->prepare(
    "SELECT s.id as section_id, s.name as section_name, s.display_order,
            t.id as table_id, t.table_name, t.min_seats, t.max_seats, t.status, t.is_active, t.notes
     FROM sections s
     LEFT JOIN tables t ON t.section_id = s.id AND t.restaurant_id = ? AND t.is_active = 1
     WHERE s.restaurant_id = ? AND s.is_active = 1
     ORDER BY s.display_order ASC, t.sort_order ASC, t.table_name ASC"
);
$stmt->execute([$restaurantId, $restaurantId]);
$rows = $stmt->fetchAll();

// Group by section
$sections = [];
foreach ($rows as $row) {
    $sid = $row['section_id'];
    if (!isset($sections[$sid])) {
        $sections[$sid] = [
            'name' => $row['section_name'],
            'tables' => [],
        ];
    }
    if ($row['table_id']) {
        $sections[$sid]['tables'][] = $row;
    }
}

// Get current reservations for today to determine real-time table status
$stmt = db()->prepare(
    "SELECT r.id, r.table_id, r.reservation_time, r.party_size, r.status, r.turn_time_minutes,
            r.seated_at, r.meal_status, g.first_name, g.last_name, g.is_active as guest_active
     FROM reservations r
     JOIN guests g ON r.guest_id = g.id
     WHERE r.restaurant_id = ? AND r.reservation_date = ?
       AND r.status IN ('confirmed', 'seated')
       AND r.table_id IS NOT NULL
     ORDER BY r.reservation_time ASC"
);
$stmt->execute([$restaurantId, $today]);
$todayRes = $stmt->fetchAll();

// Map table_id to current/upcoming reservation
$tableReservations = [];
foreach ($todayRes as $tr) {
    $tid = (int)$tr['table_id'];
    // Prefer seated over confirmed, keep earliest
    if (!isset($tableReservations[$tid]) || $tr['status'] === 'seated') {
        $tableReservations[$tid] = $tr;
    }
}

$statusColors = [
    'available' => 'success',
    'occupied' => 'warning',
    'reserved' => 'info',
    'blocked' => 'secondary',
];
?>
<div class="main-content" id="floorplan-main"
     hx-get="/partials/tables/floor-plan.php"
     hx-trigger="every 30s"
     hx-target="#floorplan-main"
     hx-swap="outerHTML">
    <div class="row" id="floorplan-row">
        <div class="col-12" id="floorplan-col">

            <div class="card mb-3" id="floorplan-header-card">
                <div class="card-body py-2 d-flex justify-content-between align-items-center" id="floorplan-header-body">
                    <h5 class="mb-0" id="floorplan-title">
                        <i class="feather-grid me-2"></i>Floor Plan
                        <small class="text-muted ms-2"><?php echo date('l, M j, Y'); ?></small>
                    </h5>
                    <div id="floorplan-header-actions">
                        <button class="btn btn-primary btn-sm" id="floorplan-new-res-btn"
                                hx-get="/partials/reservations/create-form.php"
                                hx-target="#page-content" hx-swap="innerHTML">
                            <i class="feather-plus me-1"></i> New Reservation
                        </button>
                    </div>
                </div>
            </div>

            <div id="floorplan-messages"></div>

            <?php foreach ($sections as $sid => $sec): ?>
            <div class="card mb-3" id="floorplan-section-<?php echo $sid; ?>">
                <div class="card-header py-2" id="floorplan-section-header-<?php echo $sid; ?>">
                    <h6 class="mb-0" id="floorplan-section-title-<?php echo $sid; ?>">
                        <?php echo htmlspecialchars($sec['name']); ?>
                        <span class="badge bg-secondary ms-1"><?php echo count($sec['tables']); ?> tables</span>
                    </h6>
                </div>
                <div class="card-body" id="floorplan-section-body-<?php echo $sid; ?>">
                    <?php if (empty($sec['tables'])): ?>
                    <p class="text-muted mb-0" id="floorplan-section-empty-<?php echo $sid; ?>">No tables in this section.</p>
                    <?php else: ?>
                    <div class="row g-3" id="floorplan-section-grid-<?php echo $sid; ?>">
                        <?php foreach ($sec['tables'] as $tbl):
                            $tid = (int)$tbl['table_id'];
                            $resInfo = $tableReservations[$tid] ?? null;

                            // Determine effective status
                            $effectiveStatus = 'available';
                            if ($tbl['status'] === 'blocked') {
                                $effectiveStatus = 'blocked';
                            } elseif ($resInfo && $resInfo['status'] === 'seated') {
                                $effectiveStatus = 'occupied';
                            } elseif ($resInfo && $resInfo['status'] === 'confirmed') {
                                $effectiveStatus = 'reserved';
                            }

                            $borderColor = $statusColors[$effectiveStatus];

                            // Calculate remaining time if seated (using meal status)
                            $remainingMin = null;
                            $mealDef = null;
                            if ($effectiveStatus === 'occupied') {
                                $mealSt = $resInfo['meal_status'] ?: 'seated';
                                $turnMin = $resInfo['turn_time_minutes'] ?: 90;
                                $allMealStatuses = getMealStatuses($restaurantId);
                                $mealDef = $allMealStatuses[$mealSt];
                                $remainingMin = estimatedMinutesRemaining($mealSt, $turnMin, $restaurantId);
                            }
                        ?>
                        <div class="col-6 col-md-4 col-lg-3 col-xl-2" id="floorplan-table-col-<?php echo $tid; ?>">
                            <div class="card border-<?php echo $borderColor; ?> border-2 h-100"
                                 style="cursor: pointer;"
                                 id="floorplan-table-<?php echo $tid; ?>"
                                 <?php if ($effectiveStatus === 'available'): ?>
                                 hx-get="/partials/tables/assign-walkin.php?table_id=<?php echo $tid; ?>"
                                 hx-target="#floorplan-modal-container" hx-swap="innerHTML"
                                 <?php elseif ($effectiveStatus === 'occupied' && $resInfo): ?>
                                 hx-get="/partials/tables/table-status.php?table_id=<?php echo $tid; ?>"
                                 hx-target="#floorplan-modal-container" hx-swap="innerHTML"
                                 <?php elseif ($resInfo): ?>
                                 hx-get="/partials/reservations/detail.php?id=<?php echo $resInfo['id']; ?>"
                                 hx-target="#page-content" hx-swap="innerHTML"
                                 <?php endif; ?>>
                                <div class="card-body p-2 text-center" id="floorplan-table-body-<?php echo $tid; ?>">
                                    <div class="fw-bold" id="floorplan-table-name-<?php echo $tid; ?>">
                                        <?php echo htmlspecialchars($tbl['table_name']); ?>
                                    </div>
                                    <div class="small text-muted" id="floorplan-table-seats-<?php echo $tid; ?>">
                                        <?php echo (int)$tbl['min_seats']; ?>-<?php echo (int)$tbl['max_seats']; ?> seats
                                    </div>
                                    <span class="badge bg-<?php echo $borderColor; ?> mt-1" id="floorplan-table-status-<?php echo $tid; ?>">
                                        <?php echo ucfirst($effectiveStatus); ?>
                                    </span>

                                    <?php if ($resInfo): ?>
                                    <div class="small mt-1" id="floorplan-table-guest-<?php echo $tid; ?>">
                                        <?php
                                        $displayName = ($resInfo['guest_active'] || $resInfo['first_name'] !== 'Walk-in')
                                            ? htmlspecialchars($resInfo['first_name'] . ' ' . substr($resInfo['last_name'], 0, 1) . '.')
                                            : 'Walk-in';
                                        ?>
                                        <strong><?php echo $displayName; ?></strong>
                                        <span class="text-muted">(<?php echo (int)$resInfo['party_size']; ?>)</span>
                                    </div>
                                    <?php if ($effectiveStatus === 'occupied' && $mealDef): ?>
                                    <div class="mt-1" id="floorplan-table-meal-<?php echo $tid; ?>">
                                        <span class="badge bg-<?php echo $mealDef['color']; ?>"><?php echo $mealDef['label']; ?></span>
                                    </div>
                                    <?php if ($remainingMin !== null): ?>
                                    <div class="small fw-semibold <?php echo $remainingMin <= 10 ? 'text-danger' : ($remainingMin <= 20 ? 'text-warning' : 'text-muted'); ?>" id="floorplan-table-remaining-<?php echo $tid; ?>">
                                        ~<?php echo $remainingMin; ?> min
                                    </div>
                                    <?php endif; ?>
                                    <?php elseif ($effectiveStatus === 'reserved'): ?>
                                    <div class="small text-muted" id="floorplan-table-time-<?php echo $tid; ?>">
                                        <?php echo date('g:ia', strtotime($resInfo['reservation_time'])); ?>
                                    </div>
                                    <?php endif; ?>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
            <?php endforeach; ?>

            <!-- Legend -->
            <div class="card" id="floorplan-legend-card">
                <div class="card-body py-2" id="floorplan-legend-body">
                    <div class="d-flex flex-wrap gap-3 mb-1" id="floorplan-legend-tables">
                        <span class="small" id="floorplan-legend-label"><strong>Tables:</strong></span>
                        <span class="badge bg-success" id="floorplan-legend-avail">Available</span>
                        <span class="badge bg-warning text-dark" id="floorplan-legend-occ">Occupied</span>
                        <span class="badge bg-info text-dark" id="floorplan-legend-res">Reserved</span>
                        <span class="badge bg-secondary" id="floorplan-legend-block">Blocked</span>
                    </div>
                    <div class="d-flex flex-wrap gap-3" id="floorplan-legend-meal">
                        <span class="small" id="floorplan-legend-meal-label"><strong>Meal:</strong></span>
                        <?php $mealLegend = getMealStatuses($restaurantId); foreach ($mealLegend as $mk => $md): ?>
                        <span class="badge bg-<?php echo $md['color']; ?>" style="font-size: 0.65rem;" id="floorplan-legend-meal-<?php echo $mk; ?>"><?php echo $md['label']; ?></span>
                        <?php endforeach; ?>
                        <span class="small text-muted ms-auto" id="floorplan-legend-refresh">Auto-refreshes every 30s</span>
                    </div>
                </div>
            </div>

        </div>
    </div>

    <div id="floorplan-modal-container"></div>
</div>
