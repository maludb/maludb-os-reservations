<?php
/**
 * Daily Reservation Calendar — Timeline view grouped by time slot
 */
require_once '../../../helpers/auth.php';
require_once '../../../helpers/csrf.php';

requireAuth();

$restaurantId = currentRestaurantId();
$date = trim($_GET['date'] ?? date('Y-m-d'));

// Validate date format
if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
    $date = date('Y-m-d');
}

$filterStatus = trim($_GET['status'] ?? '');
$filterSection = (int)($_GET['section_id'] ?? 0);

// Get sections for filter
$stmt = db()->prepare("SELECT id, name FROM sections WHERE restaurant_id = ? AND is_active = 1 ORDER BY display_order ASC");
$stmt->execute([$restaurantId]);
$sections = $stmt->fetchAll();

// Build reservation query
$sql = "SELECT r.*, g.first_name, g.last_name, g.phone as guest_phone,
               t.table_name, s.name as section_name
        FROM reservations r
        JOIN guests g ON r.guest_id = g.id
        LEFT JOIN tables t ON r.table_id = t.id
        LEFT JOIN sections s ON t.section_id = s.id
        WHERE r.restaurant_id = ? AND r.reservation_date = ? AND r.source != 'walk_in'";
$params = [$restaurantId, $date];

if ($filterStatus !== '') {
    $sql .= " AND r.status = ?";
    $params[] = $filterStatus;
} else {
    $sql .= " AND r.status != 'cancelled'";
}

if ($filterSection > 0) {
    $sql .= " AND t.section_id = ?";
    $params[] = $filterSection;
}

$sql .= " ORDER BY r.reservation_time ASC, r.created_at ASC";

$stmt = db()->prepare($sql);
$stmt->execute($params);
$reservations = $stmt->fetchAll();

// Group by time slot (round to nearest 30 min for grouping)
$grouped = [];
foreach ($reservations as $r) {
    $timeKey = substr($r['reservation_time'], 0, 5); // HH:MM
    $grouped[$timeKey][] = $r;
}

// Stats
$totalRes = count($reservations);
$totalCovers = array_sum(array_column($reservations, 'party_size'));

// Available tables count
$stmt = db()->prepare(
    "SELECT COUNT(*) as cnt FROM tables t
     JOIN sections s ON t.section_id = s.id
     WHERE t.restaurant_id = ? AND t.is_active = 1 AND s.is_active = 1"
);
$stmt->execute([$restaurantId]);
$totalTables = (int)$stmt->fetch()['cnt'];

// Occupied tables for this date (tables with active reservations)
$stmt = db()->prepare(
    "SELECT COUNT(DISTINCT table_id) as cnt FROM reservations
     WHERE restaurant_id = ? AND reservation_date = ? AND table_id IS NOT NULL
       AND status IN ('confirmed', 'seated')"
);
$stmt->execute([$restaurantId, $date]);
$occupiedTables = (int)$stmt->fetch()['cnt'];
$availableTables = $totalTables - $occupiedTables;

$statusColors = [
    'pending' => 'warning',
    'confirmed' => 'primary',
    'seated' => 'success',
    'completed' => 'secondary',
    'no_show' => 'danger',
    'cancelled' => 'dark',
];

$prevDate = date('Y-m-d', strtotime($date . ' -1 day'));
$nextDate = date('Y-m-d', strtotime($date . ' +1 day'));
?>
<div class="main-content" id="cal-main">
    <div class="row" id="cal-row">
        <div class="col-12" id="cal-col">

            <!-- Header with stats and controls -->
            <div class="card mb-3" id="cal-header-card">
                <div class="card-body" id="cal-header-body">
                    <div class="d-flex flex-wrap justify-content-between align-items-center gap-3" id="cal-header-flex">
                        <div id="cal-header-title">
                            <h5 class="mb-0" id="cal-date-title">
                                <i class="feather-calendar me-2"></i>
                                <?php echo date('l, F j, Y', strtotime($date)); ?>
                            </h5>
                        </div>
                        <div class="d-flex align-items-center gap-2" id="cal-header-nav">
                            <button class="btn btn-outline-secondary btn-sm" id="cal-prev-btn"
                                    hx-get="/partials/reservations/calendar.php?date=<?php echo $prevDate; ?>"
                                    hx-target="#page-content" hx-swap="innerHTML">
                                <i class="feather-chevron-left"></i>
                            </button>
                            <input type="date" class="form-control form-control-sm" id="cal-date-picker"
                                   value="<?php echo $date; ?>"
                                   hx-get="/partials/reservations/calendar.php"
                                   hx-target="#page-content" hx-swap="innerHTML"
                                   hx-trigger="change" name="date" style="width: 160px;">
                            <button class="btn btn-outline-secondary btn-sm" id="cal-next-btn"
                                    hx-get="/partials/reservations/calendar.php?date=<?php echo $nextDate; ?>"
                                    hx-target="#page-content" hx-swap="innerHTML">
                                <i class="feather-chevron-right"></i>
                            </button>
                            <button class="btn btn-outline-secondary btn-sm" id="cal-today-btn"
                                    hx-get="/partials/reservations/calendar.php?date=<?php echo date('Y-m-d'); ?>"
                                    hx-target="#page-content" hx-swap="innerHTML">
                                Today
                            </button>
                        </div>
                        <div class="d-flex gap-2" id="cal-header-actions">
                            <button class="btn btn-primary btn-sm" id="cal-new-res-btn"
                                    hx-get="/partials/reservations/create-form.php"
                                    hx-target="#page-content" hx-swap="innerHTML">
                                <i class="feather-plus me-1"></i> New Reservation
                            </button>
                            <button class="btn btn-outline-secondary btn-sm" id="cal-list-btn"
                                    hx-get="/partials/reservations/list.php"
                                    hx-target="#page-content" hx-swap="innerHTML">
                                <i class="feather-list me-1"></i> List View
                            </button>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Stats row -->
            <div class="row mb-3" id="cal-stats-row">
                <div class="col-md-4" id="cal-stat-res">
                    <div class="card" id="cal-stat-res-card">
                        <div class="card-body py-2 text-center" id="cal-stat-res-body">
                            <h4 class="mb-0"><?php echo $totalRes; ?></h4>
                            <small class="text-muted">Reservations</small>
                        </div>
                    </div>
                </div>
                <div class="col-md-4" id="cal-stat-covers">
                    <div class="card" id="cal-stat-covers-card">
                        <div class="card-body py-2 text-center" id="cal-stat-covers-body">
                            <h4 class="mb-0"><?php echo $totalCovers; ?></h4>
                            <small class="text-muted">Total Covers</small>
                        </div>
                    </div>
                </div>
                <div class="col-md-4" id="cal-stat-tables">
                    <div class="card" id="cal-stat-tables-card">
                        <div class="card-body py-2 text-center" id="cal-stat-tables-body">
                            <h4 class="mb-0"><?php echo $availableTables; ?> / <?php echo $totalTables; ?></h4>
                            <small class="text-muted">Available Tables</small>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Filters -->
            <div class="card mb-3" id="cal-filters-card">
                <div class="card-body py-2" id="cal-filters-body">
                    <div class="d-flex flex-wrap gap-2 align-items-center" id="cal-filters-flex">
                        <span class="text-muted small" id="cal-filters-label">Filters:</span>
                        <select class="form-select form-select-sm d-inline-block w-auto" id="cal-filter-status"
                                hx-get="/partials/reservations/calendar.php"
                                hx-include="#cal-date-picker, #cal-filter-section"
                                hx-target="#page-content" hx-swap="innerHTML"
                                name="status">
                            <option value="">All Statuses</option>
                            <?php foreach ($statusColors as $st => $col): ?>
                            <option value="<?php echo $st; ?>" <?php echo $filterStatus === $st ? 'selected' : ''; ?>>
                                <?php echo ucfirst(str_replace('_', ' ', $st)); ?>
                            </option>
                            <?php endforeach; ?>
                        </select>
                        <select class="form-select form-select-sm d-inline-block w-auto" id="cal-filter-section"
                                hx-get="/partials/reservations/calendar.php"
                                hx-include="#cal-date-picker, #cal-filter-status"
                                hx-target="#page-content" hx-swap="innerHTML"
                                name="section_id">
                            <option value="0">All Sections</option>
                            <?php foreach ($sections as $sec): ?>
                            <option value="<?php echo $sec['id']; ?>" <?php echo $filterSection === (int)$sec['id'] ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($sec['name']); ?>
                            </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
            </div>

            <!-- Timeline -->
            <div class="card" id="cal-timeline-card">
                <div class="card-body" id="cal-timeline-body">
                    <?php if (empty($grouped)): ?>
                    <div class="text-center text-muted py-5" id="cal-no-reservations">
                        <i class="feather-calendar" style="font-size: 2rem;"></i>
                        <p class="mt-2 mb-0">No reservations for this date.</p>
                    </div>
                    <?php else: ?>
                    <?php foreach ($grouped as $timeKey => $timeSlotRes): ?>
                    <div class="mb-4" id="cal-slot-<?php echo str_replace(':', '', $timeKey); ?>">
                        <h6 class="fw-bold text-muted border-bottom pb-1" id="cal-slot-title-<?php echo str_replace(':', '', $timeKey); ?>">
                            <?php echo date('g:ia', strtotime($timeKey)); ?>
                            <span class="badge bg-secondary ms-1"><?php echo count($timeSlotRes); ?></span>
                        </h6>
                        <div class="row g-2" id="cal-slot-cards-<?php echo str_replace(':', '', $timeKey); ?>">
                            <?php foreach ($timeSlotRes as $r):
                                $badgeColor = $statusColors[$r['status']] ?? 'secondary';
                            ?>
                            <div class="col-md-4 col-lg-3" id="cal-res-<?php echo $r['id']; ?>">
                                <div class="card border-start border-<?php echo $badgeColor; ?> border-3 h-100"
                                     style="cursor: pointer;"
                                     hx-get="/partials/reservations/detail.php?id=<?php echo $r['id']; ?>"
                                     hx-target="#page-content" hx-swap="innerHTML"
                                     id="cal-res-card-<?php echo $r['id']; ?>">
                                    <div class="card-body py-2 px-3" id="cal-res-body-<?php echo $r['id']; ?>">
                                        <div class="d-flex justify-content-between align-items-start" id="cal-res-header-<?php echo $r['id']; ?>">
                                            <strong class="small"><?php echo htmlspecialchars($r['first_name'] . ' ' . $r['last_name']); ?></strong>
                                            <span class="badge bg-<?php echo $badgeColor; ?>"><?php echo ucfirst(str_replace('_', ' ', $r['status'])); ?></span>
                                        </div>
                                        <div class="small text-muted" id="cal-res-details-<?php echo $r['id']; ?>">
                                            <span><i class="feather-users me-1"></i><?php echo (int)$r['party_size']; ?></span>
                                            <?php if ($r['table_name']): ?>
                                            <span class="ms-2"><i class="feather-grid me-1"></i><?php echo htmlspecialchars($r['table_name']); ?></span>
                                            <?php endif; ?>
                                            <?php if ($r['guest_phone']): ?>
                                            <span class="ms-2"><?php echo htmlspecialchars($r['guest_phone']); ?></span>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                    <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>

        </div>
    </div>
</div>
