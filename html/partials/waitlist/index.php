<?php
/**
 * Waitlist Management — Active waitlist page
 */
require_once __DIR__ . '/../../../helpers/auth.php';
require_once __DIR__ . '/../../../helpers/db.php';
require_once __DIR__ . '/../../../helpers/csrf.php';
require_once __DIR__ . '/../../../helpers/availability.php';
require_once __DIR__ . '/../../../helpers/meal-status.php';

requireAuth();
$restaurantId = currentRestaurantId();
$pdo = db();

// Get restaurant slug for voice agent context
$slugStmt = $pdo->prepare("SELECT slug FROM restaurants WHERE id = ?");
$slugStmt->execute([$restaurantId]);
$restaurantSlug = ($slugStmt->fetch())['slug'] ?? '';

// Fetch active waitlist entries (waiting + notified)
$stmt = $pdo->prepare(
    "SELECT w.*, g.first_name, g.last_name, g.phone AS guest_phone
     FROM waitlist w
     LEFT JOIN guests g ON w.guest_id = g.id
     WHERE w.restaurant_id = ? AND w.status IN ('waiting', 'notified')
     ORDER BY w.queue_position ASC"
);
$stmt->execute([$restaurantId]);
$waitlistEntries = $stmt->fetchAll();

// Fetch today's upcoming reservations (pending/confirmed, not walk-ins)
$stmt = $pdo->prepare(
    "SELECT r.*, g.first_name, g.last_name, g.phone AS guest_phone, g.email AS guest_email
     FROM reservations r
     JOIN guests g ON r.guest_id = g.id
     WHERE r.restaurant_id = ? AND r.reservation_date = CURRENT_DATE
       AND r.status IN ('pending', 'confirmed')
       AND r.source != 'walk_in'
     ORDER BY r.reservation_time ASC"
);
$stmt->execute([$restaurantId]);
$todayReservations = $stmt->fetchAll();

// Build combined list: reservations get a sort_time based on reservation_time,
// waitlist entries get sort_time based on created_at
$nowTime = date('H:i:s');
$combinedEntries = [];

foreach ($todayReservations as $r) {
    $isPast = $r['reservation_time'] <= $nowTime;
    $combinedEntries[] = [
        'type' => 'reservation',
        'sort_time' => $r['reservation_time'],
        'is_past' => $isPast,
        'data' => $r,
    ];
}

foreach ($waitlistEntries as $w) {
    $combinedEntries[] = [
        'type' => 'waitlist',
        'sort_time' => date('H:i:s', strtotime($w['created_at'])),
        'is_past' => false,
        'data' => $w,
    ];
}

// Sort: past-time reservations first, then by sort_time
usort($combinedEntries, function ($a, $b) {
    // Past reservations always come first
    if ($a['is_past'] && !$b['is_past']) return -1;
    if (!$a['is_past'] && $b['is_past']) return 1;
    // Within same group, sort by time
    return strcmp($a['sort_time'], $b['sort_time']);
});

// Keep $entries as waitlist-only for counting
$entries = $waitlistEntries;

// Count recently completed (seated/left/no_response in last 2 hours)
$stmt = $pdo->prepare(
    "SELECT w.*, g.first_name, g.last_name
     FROM waitlist w
     LEFT JOIN guests g ON w.guest_id = g.id
     WHERE w.restaurant_id = ? AND w.status IN ('seated', 'left', 'no_response')
       AND w.updated_at >= NOW() - INTERVAL '2 hours'
     ORDER BY w.updated_at DESC
     LIMIT 20"
);
$stmt->execute([$restaurantId]);
$recent = $stmt->fetchAll();

// Get available tables count for seating
$stmt = $pdo->prepare(
    "SELECT t.id, t.table_name, t.min_seats, t.max_seats, s.name AS section_name
     FROM tables t
     JOIN sections s ON t.section_id = s.id
     WHERE t.restaurant_id = ? AND t.is_active = 1 AND t.status = 'available'
     ORDER BY t.min_seats ASC"
);
$stmt->execute([$restaurantId]);
$availableTables = $stmt->fetchAll();

$statusColors = [
    'waiting' => 'warning', 'notified' => 'info', 'seated' => 'success',
    'left' => 'secondary', 'no_response' => 'danger',
];

// --- Estimate: next available tables based on seated reservations ---
$totalActiveStmt = $pdo->prepare(
    "SELECT COUNT(*) FROM tables WHERE restaurant_id = ? AND is_active = 1"
);
$totalActiveStmt->execute([$restaurantId]);
$totalActiveTables = (int)$totalActiveStmt->fetchColumn();

// Count tables occupied by active reservations today
$occupiedCountStmt = $pdo->prepare(
    "SELECT COUNT(DISTINCT table_id) FROM reservations
     WHERE restaurant_id = ? AND reservation_date = CURRENT_DATE
       AND status IN ('confirmed', 'seated') AND table_id IS NOT NULL"
);
$occupiedCountStmt->execute([$restaurantId]);
$occupiedCount = (int)$occupiedCountStmt->fetchColumn();
$freeCount = max(0, $totalActiveTables - $occupiedCount);

// Get occupied tables with meal status for time estimates (one row per table, prefer latest seated)
$occupiedStmt = $pdo->prepare(
    "SELECT t.table_name, t.min_seats, t.max_seats, r.meal_status, r.seated_at,
            r.party_size
     FROM reservations r
     JOIN tables t ON r.table_id = t.id
     WHERE r.restaurant_id = ? AND r.reservation_date = CURRENT_DATE
       AND r.status = 'seated' AND r.meal_status IS NOT NULL
       AND r.id = (
           SELECT r2.id FROM reservations r2
           WHERE r2.table_id = r.table_id AND r2.restaurant_id = r.restaurant_id
             AND r2.reservation_date = CURRENT_DATE AND r2.status = 'seated' AND r2.meal_status IS NOT NULL
           ORDER BY r2.seated_at DESC LIMIT 1
       )
     ORDER BY r.seated_at ASC"
);
$occupiedStmt->execute([$restaurantId]);
$occupiedTables = $occupiedStmt->fetchAll();

// Calculate estimated minutes remaining for each occupied table
$defaultTurn = (int)getRestaurantSetting($restaurantId, 'default_turn_time', 90);
$tableEstimates = [];
foreach ($occupiedTables as $ot) {
    $remaining = estimatedMinutesRemaining($ot['meal_status'], $defaultTurn, $restaurantId);
    $tableEstimates[] = [
        'table_name' => $ot['table_name'],
        'min_seats'  => (int)$ot['min_seats'],
        'max_seats'  => (int)$ot['max_seats'],
        'meal_status'=> $ot['meal_status'],
        'remaining'  => $remaining,
        'available_at' => date('g:ia', time() + $remaining * 60),
    ];
}
// Sort by soonest available
usort($tableEstimates, fn($a, $b) => $a['remaining'] - $b['remaining']);

// Calculate two key estimates:
// 1. Next table: minutes until soonest table opens
$nextTableMinutes = !empty($tableEstimates) ? $tableEstimates[0]['remaining'] : null;
if ($freeCount > 0) {
    $nextTableMinutes = 0; // tables already open
}

// 2. New guest wait: if someone joins now, how long until they'd be seated?
// They need to wait for everyone ahead of them + next available table
// Count walk-in waitlist entries + checked-in or past-time reservations (they need tables too)
$waitingCount = count($entries);
foreach ($todayReservations as $r) {
    $checkedIn = !empty($r['checked_in_at']);
    $isPast = $r['reservation_time'] <= $nowTime;
    if ($checkedIn || $isPast) {
        $waitingCount++;
    }
}
if ($freeCount > $waitingCount) {
    // More open tables than people waiting — seat immediately
    $newGuestWaitMinutes = 0;
} elseif (!empty($tableEstimates)) {
    // Need to work through the queue: each waiting person takes a table as it opens
    // Figure out which table the new guest (at the end of the line) would get
    $tablesNeeded = $waitingCount - $freeCount + 1; // +1 for the new guest
    if ($tablesNeeded <= count($tableEstimates)) {
        $newGuestWaitMinutes = $tableEstimates[$tablesNeeded - 1]['remaining'];
    } else {
        // More people waiting than tables turning over — estimate using average turnover
        $avgRemaining = array_sum(array_column($tableEstimates, 'remaining')) / count($tableEstimates);
        $rounds = ceil($tablesNeeded / count($tableEstimates));
        $newGuestWaitMinutes = (int)($avgRemaining * $rounds);
    }
} else {
    $newGuestWaitMinutes = null; // can't estimate
}
?>

<div class="main-content" id="waitlist-main"
     hx-get="/partials/waitlist/index.php"
     hx-trigger="refreshWaitlist from:body, every 15s"
     hx-target="#waitlist-main"
     hx-swap="outerHTML">

    <div class="d-flex justify-content-between align-items-center mb-4" id="waitlist-header">
        <div id="waitlist-title-wrap">
            <h4 class="mb-0" id="waitlist-title">Waitlist</h4>
            <small class="text-muted" id="waitlist-count"><?php echo count($entries); ?> waiting<?php echo count($todayReservations) ? ', ' . count($todayReservations) . ' reservation' . (count($todayReservations) > 1 ? 's' : '') : ''; ?></small>
        </div>
        <div class="d-flex gap-2" id="waitlist-header-actions">
            <?php if (!empty($entries)): ?>
            <button class="btn btn-outline-danger" id="waitlist-clear-btn"
                    hx-post="/partials/waitlist/clear.php"
                    hx-vals='<?php echo json_encode(["csrf_token" => generate_csrf_token()]); ?>'
                    hx-target="#waitlist-messages"
                    hx-swap="innerHTML"
                    hx-confirm="Are you sure you want to clear all waitlist entries? This will mark all waiting and notified guests as 'left'.">
                <i class="feather-trash-2 me-1"></i> Clear Waitlist
            </button>
            <?php endif; ?>
            <button class="btn btn-primary" id="waitlist-add-btn"
                    hx-get="/partials/waitlist/add-form.php"
                    hx-target="#waitlist-modal-container"
                    hx-swap="innerHTML">
                <i class="feather-plus me-1"></i> Add to Waitlist
            </button>
        </div>
    </div>

    <!-- Table Availability Estimate -->
    <div class="card mb-3" id="waitlist-estimate-card">
        <div class="card-body py-3" id="waitlist-estimate-body">
            <div class="d-flex flex-wrap gap-4 align-items-center" id="waitlist-estimate-stats">
                <div id="waitlist-estimate-free">
                    <span class="fs-4 fw-bold <?php echo $freeCount > 0 ? 'text-success' : 'text-danger'; ?>"><?php echo $freeCount; ?></span>
                    <span class="text-muted ms-1">of <?php echo $totalActiveTables; ?> tables open</span>
                </div>
                <div id="waitlist-estimate-occupied">
                    <span class="fs-4 fw-bold text-warning"><?php echo count($occupiedTables); ?></span>
                    <span class="text-muted ms-1">seated</span>
                </div>
                <div class="border-start ps-4" id="waitlist-estimate-next-table">
                    <small class="text-muted d-block">Next Table</small>
                    <?php if ($nextTableMinutes === 0): ?>
                    <span class="fs-5 fw-bold text-success">Now</span>
                    <?php elseif ($nextTableMinutes !== null): ?>
                    <span class="fs-5 fw-bold">~<?php echo $nextTableMinutes; ?>m</span>
                    <small class="text-muted">(<?php echo $tableEstimates[0]['table_name']; ?> @ <?php echo $tableEstimates[0]['available_at']; ?>)</small>
                    <?php else: ?>
                    <span class="fs-5 text-muted">—</span>
                    <?php endif; ?>
                </div>
                <div class="border-start ps-4" id="waitlist-estimate-new-guest">
                    <small class="text-muted d-block">New Guest Wait</small>
                    <?php if ($newGuestWaitMinutes === 0): ?>
                    <span class="fs-5 fw-bold text-success">Seat Now</span>
                    <?php elseif ($newGuestWaitMinutes !== null): ?>
                    <span class="fs-5 fw-bold <?php echo $newGuestWaitMinutes > 30 ? 'text-danger' : ($newGuestWaitMinutes > 15 ? 'text-warning' : ''); ?>">~<?php echo $newGuestWaitMinutes; ?>m</span>
                    <small class="text-muted">(<?php echo $waitingCount; ?> ahead)</small>
                    <?php else: ?>
                    <span class="fs-5 text-muted">—</span>
                    <?php endif; ?>
                </div>
            </div>
            <?php if (!empty($tableEstimates)): ?>
            <div class="mt-2" id="waitlist-estimate-detail">
                <small class="text-muted">Upcoming table turnover:</small>
                <div class="d-flex gap-2 mt-1 overflow-hidden" id="waitlist-estimate-badges">
                    <?php
                    $mealMeta = getMealStatusMeta();
                    foreach (array_slice($tableEstimates, 0, 5) as $te):
                        $meta = $mealMeta[$te['meal_status']] ?? ['label' => $te['meal_status'], 'color' => 'secondary'];
                    ?>
                    <span class="badge bg-light text-dark border" id="waitlist-est-<?php echo htmlspecialchars($te['table_name']); ?>">
                        <?php echo htmlspecialchars($te['table_name']); ?>
                        (<?php echo $te['min_seats']; ?>-<?php echo $te['max_seats']; ?>)
                        <span class="badge bg-<?php echo $meta['color']; ?> ms-1"><?php echo $meta['label']; ?></span>
                        ~<?php echo $te['remaining']; ?>m
                    </span>
                    <?php endforeach; ?>
                </div>
            </div>
            <?php endif; ?>
        </div>
    </div>

    <div id="waitlist-messages"></div>
    <div id="waitlist-modal-container"></div>

    <!-- Active Waitlist + Reservations -->
    <div class="card mb-3" id="waitlist-active-card">
        <div class="card-body p-0" id="waitlist-active-body">
            <?php if (empty($combinedEntries)): ?>
            <div class="text-center py-5 text-muted" id="waitlist-empty">
                <p class="mb-0">No one currently on the waitlist and no upcoming reservations.</p>
            </div>
            <?php else: ?>
            <div class="table-responsive" id="waitlist-table-wrap">
                <table class="table table-hover mb-0" id="waitlist-table">
                    <thead id="waitlist-thead">
                        <tr>
                            <th style="width:50px;">Time</th>
                            <th>Guest</th>
                            <th>Party</th>
                            <th>Phone</th>
                            <th>Wait / ETA</th>
                            <th>Quoted</th>
                            <th>Status</th>
                            <th>Type</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody id="waitlist-tbody">
                        <?php $pos = 0; foreach ($combinedEntries as $item):
                            $pos++;
                            $isRes = $item['type'] === 'reservation';
                        ?>

                        <?php if ($isRes):
                            $r = $item['data'];
                            $name = $r['first_name'] . ' ' . $r['last_name'];
                            $phone = $r['guest_phone'] ?? '';
                            $checkedIn = !empty($r['checked_in_at']);
                            $isPast = $item['is_past'];
                            $rowBg = $checkedIn ? 'rgba(25, 135, 84, 0.1)' : 'rgba(220, 53, 69, 0.1)';
                        ?>
                        <tr id="waitlist-res-row-<?php echo $r['id']; ?>" style="background-color: <?php echo $rowBg; ?>;">
                            <td class="fw-bold">
                                <?php echo date('g:ia', strtotime($r['reservation_time'])); ?>
                                <?php if ($isPast): ?>
                                <br><small class="text-danger fw-bold">PAST</small>
                                <?php endif; ?>
                            </td>
                            <td>
                                <span class="fw-semibold"><?php echo htmlspecialchars($name); ?></span>
                                <?php if ($r['special_requests']): ?>
                                <br><small class="text-muted fst-italic"><?php echo htmlspecialchars($r['special_requests']); ?></small>
                                <?php endif; ?>
                            </td>
                            <td><span class="badge bg-light text-dark"><?php echo (int)$r['party_size']; ?></span></td>
                            <td><?php echo htmlspecialchars($phone ?: '—'); ?></td>
                            <td>
                                <?php
                                $minsUntil = (int)((strtotime($r['reservation_time']) - strtotime($nowTime)) / 60);
                                if ($isPast): ?>
                                <span class="text-danger fw-bold"><?php echo abs($minsUntil); ?>m ago</span>
                                <?php else: ?>
                                <span class="<?php echo $minsUntil <= 10 ? 'text-warning fw-bold' : 'text-muted'; ?>">in <?php echo $minsUntil; ?>m</span>
                                <?php endif; ?>
                            </td>
                            <td><span class="text-muted">—</span></td>
                            <td>
                                <?php if ($checkedIn): ?>
                                <span class="badge bg-success">Checked In</span>
                                <br><small class="text-muted"><?php echo date('g:ia', strtotime($r['checked_in_at'])); ?></small>
                                <?php else: ?>
                                <span class="badge bg-<?php echo $r['status'] === 'confirmed' ? 'primary' : 'warning'; ?>">
                                    <?php echo ucfirst($r['status']); ?>
                                </span>
                                <?php endif; ?>
                            </td>
                            <td><span class="badge bg-info">Reservation</span></td>
                            <td>
                                <div class="btn-group btn-group-sm" id="waitlist-res-actions-<?php echo $r['id']; ?>">
                                    <?php if (!$checkedIn): ?>
                                    <button class="btn btn-success" title="Check In"
                                            hx-post="/partials/waitlist/checkin-reservation.php"
                                            hx-vals='<?php echo json_encode(["reservation_id" => $r['id'], "csrf_token" => generate_csrf_token()]); ?>'
                                            hx-target="#waitlist-messages"
                                            hx-swap="innerHTML">
                                        <i class="feather-log-in me-1"></i>Check In
                                    </button>
                                    <?php endif; ?>
                                    <button class="btn btn-info" title="Call Guest"
                                            hx-post="/partials/waitlist/notify-reservation.php"
                                            hx-vals='<?php echo json_encode(["reservation_id" => $r['id'], "csrf_token" => generate_csrf_token()]); ?>'
                                            hx-target="#waitlist-messages"
                                            hx-swap="innerHTML">
                                        <i class="feather-bell me-1"></i><?php echo $phone ? 'Table Ready' : 'Call Guest'; ?>
                                    </button>
                                    <button class="btn btn-outline-success" title="Seat"
                                            hx-get="/partials/waitlist/seat-reservation.php?reservation_id=<?php echo (int)$r['id']; ?>"
                                            hx-target="#waitlist-modal-container"
                                            hx-swap="innerHTML">
                                        <i class="feather-check me-1"></i>Seat
                                    </button>
                                </div>
                            </td>
                        </tr>

                        <?php else:
                            $e = $item['data'];
                            $name = $e['guest_id'] ? ($e['first_name'] . ' ' . $e['last_name']) : ($e['guest_name'] ?: 'Unknown');
                            $phone = $e['phone'] ?: ($e['guest_phone'] ?? '');
                            $waitMinutes = (int)((time() - strtotime($e['created_at'])) / 60);
                            $estimatedWait = $e['estimated_wait_minutes'] ? (int)$e['estimated_wait_minutes'] : null;
                            $notifyCount = (int)($e['notify_count'] ?? 0);
                        ?>
                        <tr id="waitlist-row-<?php echo $e['id']; ?>">
                            <td class="fw-bold"><?php echo date('g:ia', strtotime($e['created_at'])); ?></td>
                            <td>
                                <span class="fw-semibold"><?php echo htmlspecialchars($name); ?></span>
                                <?php if ($e['seating_preference']): ?>
                                <br><small class="text-muted"><?php echo htmlspecialchars($e['seating_preference']); ?></small>
                                <?php endif; ?>
                                <?php if ($e['notes']): ?>
                                <br><small class="text-muted fst-italic"><?php echo htmlspecialchars($e['notes']); ?></small>
                                <?php endif; ?>
                            </td>
                            <td><span class="badge bg-light text-dark"><?php echo (int)$e['party_size']; ?></span></td>
                            <td><?php echo htmlspecialchars($phone ?: '—'); ?></td>
                            <td>
                                <span class="<?php echo ($estimatedWait !== null && $waitMinutes > $estimatedWait) ? 'text-danger fw-bold' : ($waitMinutes > 15 ? 'text-warning' : ''); ?>">
                                    <?php echo $waitMinutes; ?>m
                                </span>
                            </td>
                            <td>
                                <?php if ($estimatedWait !== null): ?>
                                <span class="<?php echo $waitMinutes > $estimatedWait ? 'text-danger' : 'text-muted'; ?>">
                                    <?php echo $estimatedWait; ?>m
                                </span>
                                <?php else: ?>
                                <span class="text-muted">—</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <span class="badge bg-<?php echo $statusColors[$e['status']] ?? 'secondary'; ?>">
                                    <?php echo ucfirst($e['status']); ?>
                                </span>
                                <?php if ($e['status'] === 'notified' && $e['notified_at']): ?>
                                <br><small class="text-muted">
                                    <?php echo date('g:ia', strtotime($e['notified_at'])); ?>
                                    <?php if ($notifyCount > 0): ?>
                                    (<?php echo $notifyCount; ?>x)
                                    <?php endif; ?>
                                </small>
                                <?php endif; ?>
                            </td>
                            <td><span class="badge bg-warning text-dark">Walk-in</span></td>
                            <td>
                                <div class="btn-group btn-group-sm" id="waitlist-actions-<?php echo $e['id']; ?>">
                                    <?php if ($notifyCount < 2): ?>
                                    <button class="btn <?php echo $notifyCount === 0 ? 'btn-info' : 'btn-outline-warning'; ?>" title="<?php echo $notifyCount === 0 ? 'Table Ready' : 'Call Again (2nd)'; ?>"
                                            hx-post="/partials/waitlist/notify.php"
                                            hx-vals='<?php echo json_encode(["id" => $e['id'], "csrf_token" => generate_csrf_token()]); ?>'
                                            hx-target="#waitlist-messages"
                                            hx-swap="innerHTML">
                                        <i class="feather-bell me-1"></i><?php echo $notifyCount === 0 ? ($phone ? 'Table Ready' : 'Call Guest') : '2nd Call'; ?>
                                    </button>
                                    <?php endif; ?>
                                    <button class="btn btn-outline-success" title="Seat"
                                            hx-get="/partials/waitlist/update-status.php?id=<?php echo $e['id']; ?>&action=seat_form"
                                            hx-target="#waitlist-modal-container"
                                            hx-swap="innerHTML">
                                        <i class="feather-check"></i>
                                    </button>
                                    <?php if ($notifyCount >= 2): ?>
                                    <button class="btn btn-danger" title="No Response — Give Up Table"
                                            hx-post="/partials/waitlist/update-status.php"
                                            hx-vals='<?php echo json_encode(["id" => $e['id'], "status" => "no_response", "csrf_token" => generate_csrf_token()]); ?>'
                                            hx-target="#waitlist-messages"
                                            hx-swap="innerHTML"
                                            hx-confirm="Guest called 2 times with no response. Give up their table?">
                                        <i class="feather-phone-off me-1"></i>Give Up
                                    </button>
                                    <?php else: ?>
                                    <button class="btn btn-outline-secondary" title="Left"
                                            hx-post="/partials/waitlist/update-status.php"
                                            hx-vals='<?php echo json_encode(["id" => $e['id'], "status" => "left", "csrf_token" => generate_csrf_token()]); ?>'
                                            hx-target="#waitlist-messages"
                                            hx-swap="innerHTML"
                                            hx-confirm="Mark as left?">
                                        <i class="feather-x"></i>
                                    </button>
                                    <button class="btn btn-outline-danger" title="No Response"
                                            hx-post="/partials/waitlist/update-status.php"
                                            hx-vals='<?php echo json_encode(["id" => $e['id'], "status" => "no_response", "csrf_token" => generate_csrf_token()]); ?>'
                                            hx-target="#waitlist-messages"
                                            hx-swap="innerHTML"
                                            hx-confirm="Mark as no response?">
                                        <i class="feather-phone-off"></i>
                                    </button>
                                    <?php endif; ?>
                                </div>
                            </td>
                        </tr>
                        <?php endif; ?>

                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Recent Activity -->
    <?php if (!empty($recent)): ?>
    <div class="card" id="waitlist-recent-card">
        <div class="card-header" id="waitlist-recent-header">
            <h6 class="mb-0" id="waitlist-recent-title">Recent (Last 2 Hours)</h6>
        </div>
        <div class="card-body p-0" id="waitlist-recent-body">
            <div class="table-responsive" id="waitlist-recent-table-wrap">
                <table class="table table-sm mb-0" id="waitlist-recent-table">
                    <tbody id="waitlist-recent-tbody">
                        <?php foreach ($recent as $r):
                            $rName = $r['guest_id'] ? ($r['first_name'] . ' ' . $r['last_name']) : ($r['guest_name'] ?: 'Unknown');
                        ?>
                        <tr id="waitlist-recent-row-<?php echo $r['id']; ?>">
                            <td><?php echo htmlspecialchars($rName); ?></td>
                            <td>Party of <?php echo (int)$r['party_size']; ?></td>
                            <td><span class="badge bg-<?php echo $statusColors[$r['status']] ?? 'secondary'; ?>"><?php echo ucfirst(str_replace('_', ' ', $r['status'])); ?></span></td>
                            <td class="text-muted"><?php echo date('g:ia', strtotime($r['updated_at'])); ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
    <?php endif; ?>
</div>
