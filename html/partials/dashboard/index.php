<?php
/**
 * Staff Dashboard — Today's stats, upcoming arrivals, recent activity, alerts
 */
require_once __DIR__ . '/../../../helpers/auth.php';
require_once __DIR__ . '/../../../helpers/db.php';

requireAuth();
$restaurantId = currentRestaurantId();
$restaurantName = $_SESSION['current_restaurant_name'] ?? 'Restaurant';
$pdo = db();
$today = date('Y-m-d');
$now = date('H:i:s');

// --- Today's Stats ---
// Reservations today
$stmt = $pdo->prepare(
    "SELECT COUNT(*) FROM reservations
     WHERE restaurant_id = ? AND reservation_date = ? AND status NOT IN ('cancelled')"
);
$stmt->execute([$restaurantId, $today]);
$todayReservations = (int)$stmt->fetchColumn();

// Total covers today
$stmt = $pdo->prepare(
    "SELECT COALESCE(SUM(party_size), 0) FROM reservations
     WHERE restaurant_id = ? AND reservation_date = ? AND status NOT IN ('cancelled')"
);
$stmt->execute([$restaurantId, $today]);
$todayCovers = (int)$stmt->fetchColumn();

// Current occupancy
$stmt = $pdo->prepare("SELECT COUNT(*) FROM tables WHERE restaurant_id = ? AND is_active = 1");
$stmt->execute([$restaurantId]);
$totalTables = (int)$stmt->fetchColumn();

$stmt = $pdo->prepare("SELECT COUNT(*) FROM tables WHERE restaurant_id = ? AND is_active = 1 AND status = 'occupied'");
$stmt->execute([$restaurantId]);
$occupiedTables = (int)$stmt->fetchColumn();

$occupancyPct = $totalTables > 0 ? round(($occupiedTables / $totalTables) * 100) : 0;

// Waitlist count
$stmt = $pdo->prepare(
    "SELECT COUNT(*) FROM waitlist
     WHERE restaurant_id = ? AND status IN ('waiting', 'notified')"
);
$stmt->execute([$restaurantId]);
$waitlistCount = (int)$stmt->fetchColumn();

// --- Upcoming Arrivals (next 2 hours) ---
$twoHoursLater = date('H:i:s', strtotime('+2 hours'));
$stmt = $pdo->prepare(
    "SELECT r.id, r.reservation_time, r.party_size, r.status, r.confirmation_code,
            g.first_name, g.last_name, t.table_name
     FROM reservations r
     JOIN guests g ON r.guest_id = g.id
     LEFT JOIN tables t ON r.table_id = t.id
     WHERE r.restaurant_id = ? AND r.reservation_date = ?
       AND r.reservation_time BETWEEN ? AND ?
       AND r.status IN ('pending', 'confirmed')
     ORDER BY r.reservation_time ASC
     LIMIT 15"
);
$stmt->execute([$restaurantId, $today, $now, $twoHoursLater]);
$upcoming = $stmt->fetchAll();

// --- Recent Activity (last 10) ---
$stmt = $pdo->prepare(
    "SELECT a.action, a.description, a.created_at, u.first_name, u.last_name
     FROM activity_log a
     LEFT JOIN users u ON a.user_id = u.id
     WHERE a.restaurant_id = ?
     ORDER BY a.created_at DESC
     LIMIT 10"
);
$stmt->execute([$restaurantId]);
$activity = $stmt->fetchAll();

// --- Upcoming Events (next 30 days) ---
$thirtyDays = date('Y-m-d', strtotime('+30 days'));
$stmt = $pdo->prepare(
    "SELECT e.*
     FROM events e
     WHERE e.restaurant_id = ? AND e.event_date >= ? AND e.event_date <= ?
       AND e.status IN ('draft', 'published')
     ORDER BY e.event_date ASC, e.start_time ASC
     LIMIT 3"
);
$stmt->execute([$restaurantId, $today, $thirtyDays]);
$upcomingEvents = $stmt->fetchAll();

// --- Alerts ---
// No-shows today
$stmt = $pdo->prepare(
    "SELECT COUNT(*) FROM reservations
     WHERE restaurant_id = ? AND reservation_date = ? AND status = 'no_show'"
);
$stmt->execute([$restaurantId, $today]);
$noshowCount = (int)$stmt->fetchColumn();

// Unconfirmed (pending) reservations for today and tomorrow
$tomorrow = date('Y-m-d', strtotime('+1 day'));
$stmt = $pdo->prepare(
    "SELECT r.id, r.reservation_date, r.reservation_time, r.party_size, r.confirmation_code,
            g.first_name, g.last_name
     FROM reservations r
     JOIN guests g ON r.guest_id = g.id
     WHERE r.restaurant_id = ? AND r.reservation_date IN (?, ?)
       AND r.status = 'pending'
     ORDER BY r.reservation_date, r.reservation_time
     LIMIT 10"
);
$stmt->execute([$restaurantId, $today, $tomorrow]);
$unconfirmed = $stmt->fetchAll();

$statusColors = [
    'pending' => 'warning', 'confirmed' => 'primary', 'seated' => 'success',
    'completed' => 'secondary', 'no_show' => 'danger', 'cancelled' => 'dark',
];
?>

<?php
$userId = currentUserId();

// --- Billing cards: show if user is the billing_user for this restaurant ---
$showBillingCards = false;
$billingUser = 0;
$prepayBalance = 0.00;
$unpaidInvoiceCount = 0;
$unpaidInvoiceTotal = 0.00;
$totalInvoiceCount = 0;

// Check if current user is the billing_user for this restaurant
try {
    $billingStmt = $pdo->prepare("SELECT billing_user, prepay_balance FROM restaurants WHERE id = ?");
    $billingStmt->execute([$restaurantId]);
    $billingRow = $billingStmt->fetch();
    if ($billingRow) {
        $billingUser = (int)$billingRow['billing_user'];
        $prepayBalance = (float)($billingRow['prepay_balance'] ?? 0);
    }
} catch (Exception $e) {
    // prepay_balance column may not exist yet — try without it
    $billingStmt = $pdo->prepare("SELECT billing_user FROM restaurants WHERE id = ?");
    $billingStmt->execute([$restaurantId]);
    $billingRow = $billingStmt->fetch();
    if ($billingRow) {
        $billingUser = (int)$billingRow['billing_user'];
    }
}

if ($billingUser === $userId) {
    $showBillingCards = true;

    // Get unpaid invoices
    try {
        $invStmt = $pdo->prepare(
            "SELECT COUNT(*) AS cnt, COALESCE(SUM(amount), 0) AS total
             FROM invoices WHERE restaurant_id = ? AND status IN ('unpaid', 'overdue')"
        );
        $invStmt->execute([$restaurantId]);
        $invRow = $invStmt->fetch();
        $unpaidInvoiceCount = (int)$invRow['cnt'];
        $unpaidInvoiceTotal = (float)$invRow['total'];

        $totalInvStmt = $pdo->prepare("SELECT COUNT(*) FROM invoices WHERE restaurant_id = ?");
        $totalInvStmt->execute([$restaurantId]);
        $totalInvoiceCount = (int)$totalInvStmt->fetchColumn();
    } catch (Exception $e) {
        // invoices table may not exist yet
    }
}

// Last login
$user = get_user();
$lastLogin = $user['last_login_at'] ?? null;
?>

<div id="dashboard-main" class="p-4">
    <div class="d-flex justify-content-between align-items-center mb-4" id="dashboard-header">
        <div id="dashboard-title-wrap">
            <h4 class="mb-0" id="dashboard-title">Dashboard</h4>
            <small class="text-muted" id="dashboard-subtitle"><?php echo htmlspecialchars($restaurantName); ?> — <?php echo date('l, F j, Y'); ?></small>
        </div>
        <div class="d-flex gap-2" id="dashboard-header-actions">
            <a href="#" class="btn btn-primary" id="dashboard-create-reservation-btn"
               hx-get="/partials/reservations/create-form.php"
               hx-target="#page-content">
                <i class="feather-plus me-1"></i> Create Reservation
            </a>
        </div>
    </div>

    <!-- Billing Cards (visible to billing user) -->
    <?php if ($showBillingCards): ?>
    <div class="row g-3 mb-4" id="dashboard-billing-row">
        <div class="col-sm-6 col-xl-3" id="dashboard-billing-welcome">
            <div class="card border-0 shadow-sm h-100" id="dashboard-billing-welcome-card">
                <div class="card-body d-flex align-items-center" id="dashboard-billing-welcome-body">
                    <div class="avatar-md bg-primary bg-opacity-10 rounded-3 d-flex align-items-center justify-content-center me-3" id="dashboard-billing-welcome-icon">
                        <i class="feather-user text-primary" style="font-size:24px;"></i>
                    </div>
                    <div id="dashboard-billing-welcome-text">
                        <h5 class="mb-1 fw-bold" id="dashboard-billing-welcome-name">Welcome, <?php echo htmlspecialchars($user['first_name']); ?>!</h5>
                        <small class="text-muted" id="dashboard-billing-welcome-login">
                            <?php if ($lastLogin): ?>
                                Last login: <?php echo date('M j, Y g:ia', strtotime($lastLogin)); ?>
                            <?php else: ?>
                                First visit
                            <?php endif; ?>
                        </small>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-sm-6 col-xl-3" id="dashboard-billing-prepay">
            <div class="card border-0 shadow-sm h-100" id="dashboard-billing-prepay-card">
                <div class="card-body d-flex align-items-center" id="dashboard-billing-prepay-body">
                    <div class="avatar-md bg-success bg-opacity-10 rounded-3 d-flex align-items-center justify-content-center me-3" id="dashboard-billing-prepay-icon">
                        <i class="feather-dollar-sign text-success" style="font-size:24px;"></i>
                    </div>
                    <div id="dashboard-billing-prepay-text">
                        <h3 class="mb-0 fw-bold" id="dashboard-billing-prepay-amount">$<?php echo number_format($prepayBalance, 2); ?></h3>
                        <small class="text-muted" id="dashboard-billing-prepay-label">Prepay Balance</small>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-sm-6 col-xl-3" id="dashboard-billing-unpaid">
            <div class="card border-0 shadow-sm h-100" id="dashboard-billing-unpaid-card">
                <div class="card-body d-flex align-items-center" id="dashboard-billing-unpaid-body">
                    <div class="avatar-md <?php echo $unpaidInvoiceCount > 0 ? 'bg-danger' : 'bg-info'; ?> bg-opacity-10 rounded-3 d-flex align-items-center justify-content-center me-3" id="dashboard-billing-unpaid-icon">
                        <i class="feather-alert-circle <?php echo $unpaidInvoiceCount > 0 ? 'text-danger' : 'text-info'; ?>" style="font-size:24px;"></i>
                    </div>
                    <div id="dashboard-billing-unpaid-text">
                        <h3 class="mb-0 fw-bold" id="dashboard-billing-unpaid-count"><?php echo $unpaidInvoiceCount; ?></h3>
                        <small class="text-muted" id="dashboard-billing-unpaid-label">
                            Unpaid Invoice<?php echo $unpaidInvoiceCount !== 1 ? 's' : ''; ?>
                            <?php if ($unpaidInvoiceTotal > 0): ?>
                                ($<?php echo number_format($unpaidInvoiceTotal, 2); ?>)
                            <?php endif; ?>
                        </small>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-sm-6 col-xl-3" id="dashboard-billing-invoices">
            <div class="card border-0 shadow-sm h-100" id="dashboard-billing-invoices-card">
                <div class="card-body d-flex align-items-center" id="dashboard-billing-invoices-body">
                    <div class="avatar-md bg-secondary bg-opacity-10 rounded-3 d-flex align-items-center justify-content-center me-3" id="dashboard-billing-invoices-icon">
                        <i class="feather-file-text text-secondary" style="font-size:24px;"></i>
                    </div>
                    <div id="dashboard-billing-invoices-text">
                        <h3 class="mb-0 fw-bold" id="dashboard-billing-invoices-count"><?php echo $totalInvoiceCount; ?></h3>
                        <small class="text-muted" id="dashboard-billing-invoices-label">Invoice History</small>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <!-- Stats Cards -->
    <div class="row g-3 mb-4" id="dashboard-stats-row">
        <div class="col-sm-6 col-xl-3" id="dashboard-stat-reservations">
            <div class="card" id="dashboard-stat-reservations-card" style="cursor:pointer;"
                 hx-get="/partials/reservations/list.php?date_from=<?php echo $today; ?>&date_to=<?php echo $today; ?>"
                 hx-target="#page-content">
                <div class="card-body" id="dashboard-stat-reservations-body">
                    <div class="d-flex align-items-center" id="dashboard-stat-reservations-flex">
                        <div class="avatar-md bg-primary bg-opacity-10 rounded-3 d-flex align-items-center justify-content-center me-3" id="dashboard-stat-reservations-icon">
                            <i class="feather-calendar text-primary" style="font-size:24px;"></i>
                        </div>
                        <div id="dashboard-stat-reservations-text">
                            <h3 class="mb-0"><?php echo $todayReservations; ?></h3>
                            <small class="text-muted">Today's Reservations</small>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-sm-6 col-xl-3" id="dashboard-stat-covers">
            <div class="card" id="dashboard-stat-covers-card" style="cursor:pointer;"
                 hx-get="/partials/tables/list.php"
                 hx-target="#page-content">
                <div class="card-body" id="dashboard-stat-covers-body">
                    <div class="d-flex align-items-center" id="dashboard-stat-covers-flex">
                        <div class="avatar-md bg-success bg-opacity-10 rounded-3 d-flex align-items-center justify-content-center me-3" id="dashboard-stat-covers-icon">
                            <i class="feather-users text-success" style="font-size:24px;"></i>
                        </div>
                        <div id="dashboard-stat-covers-text">
                            <h3 class="mb-0"><?php echo $todayCovers; ?></h3>
                            <small class="text-muted">Total Covers</small>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-sm-6 col-xl-3" id="dashboard-stat-occupancy">
            <div class="card" id="dashboard-stat-occupancy-card" style="cursor:pointer;"
                 hx-get="/partials/tables/floor-plan.php"
                 hx-target="#page-content">
                <div class="card-body" id="dashboard-stat-occupancy-body">
                    <div class="d-flex align-items-center" id="dashboard-stat-occupancy-flex">
                        <div class="avatar-md bg-warning bg-opacity-10 rounded-3 d-flex align-items-center justify-content-center me-3" id="dashboard-stat-occupancy-icon">
                            <i class="feather-grid text-warning" style="font-size:24px;"></i>
                        </div>
                        <div id="dashboard-stat-occupancy-text">
                            <h3 class="mb-0"><?php echo $occupiedTables; ?>/<?php echo $totalTables; ?></h3>
                            <small class="text-muted">Occupancy (<?php echo $occupancyPct; ?>%)</small>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-sm-6 col-xl-3" id="dashboard-stat-waitlist">
            <div class="card" id="dashboard-stat-waitlist-card" style="cursor:pointer;"
                 hx-get="/partials/waitlist/index.php"
                 hx-target="#page-content">
                <div class="card-body" id="dashboard-stat-waitlist-body">
                    <div class="d-flex align-items-center" id="dashboard-stat-waitlist-flex">
                        <div class="avatar-md bg-info bg-opacity-10 rounded-3 d-flex align-items-center justify-content-center me-3" id="dashboard-stat-waitlist-icon">
                            <i class="feather-clock text-info" style="font-size:24px;"></i>
                        </div>
                        <div id="dashboard-stat-waitlist-text">
                            <h3 class="mb-0"><?php echo $waitlistCount; ?></h3>
                            <small class="text-muted">On Waitlist</small>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="row g-3" id="dashboard-content-row">
        <!-- Left Column -->
        <div class="col-lg-8" id="dashboard-left-col">
            <!-- Upcoming Arrivals -->
            <div class="card mb-3" id="dashboard-upcoming-card">
                <div class="card-header d-flex justify-content-between align-items-center" id="dashboard-upcoming-header">
                    <h6 class="mb-0" id="dashboard-upcoming-title">Upcoming Arrivals (Next 2 Hours)</h6>
                    <a href="#" class="btn btn-sm btn-outline-primary" id="dashboard-view-calendar"
                       hx-get="/partials/reservations/calendar.php"
                       hx-target="#page-content">View Calendar</a>
                </div>
                <div class="card-body p-0" id="dashboard-upcoming-body">
                    <?php if (empty($upcoming)): ?>
                    <div class="text-center py-4 text-muted" id="dashboard-upcoming-empty">
                        No upcoming arrivals in the next 2 hours.
                    </div>
                    <?php else: ?>
                    <div class="table-responsive" id="dashboard-upcoming-table-wrap">
                        <table class="table table-hover mb-0" id="dashboard-upcoming-table">
                            <thead id="dashboard-upcoming-thead">
                                <tr><th>Time</th><th>Guest</th><th>Party</th><th>Table</th><th>Status</th></tr>
                            </thead>
                            <tbody id="dashboard-upcoming-tbody">
                                <?php foreach ($upcoming as $u): ?>
                                <tr id="dashboard-arrival-<?php echo $u['id']; ?>" style="cursor:pointer;"
                                    hx-get="/partials/reservations/detail.php?id=<?php echo $u['id']; ?>"
                                    hx-target="#page-content">
                                    <td><?php echo date('g:ia', strtotime($u['reservation_time'])); ?></td>
                                    <td class="fw-semibold"><?php echo htmlspecialchars($u['first_name'] . ' ' . $u['last_name']); ?></td>
                                    <td><?php echo (int)$u['party_size']; ?></td>
                                    <td><?php echo htmlspecialchars($u['table_name'] ?? '—'); ?></td>
                                    <td><span class="badge bg-<?php echo $statusColors[$u['status']] ?? 'secondary'; ?>"><?php echo ucfirst($u['status']); ?></span></td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Alerts -->
            <?php if ($noshowCount > 0 || !empty($unconfirmed)): ?>
            <div class="card mb-3" id="dashboard-alerts-card">
                <div class="card-header" id="dashboard-alerts-header">
                    <h6 class="mb-0" id="dashboard-alerts-title">Alerts</h6>
                </div>
                <div class="card-body" id="dashboard-alerts-body">
                    <?php if ($noshowCount > 0): ?>
                    <div class="alert alert-danger py-2 mb-2" id="dashboard-alert-noshows">
                        <i class="feather-alert-circle me-1"></i>
                        <strong><?php echo $noshowCount; ?></strong> no-show<?php echo $noshowCount > 1 ? 's' : ''; ?> today.
                    </div>
                    <?php endif; ?>

                    <?php if (!empty($unconfirmed)): ?>
                    <div class="alert alert-warning py-2 mb-0" id="dashboard-alert-unconfirmed">
                        <i class="feather-alert-triangle me-1"></i>
                        <strong><?php echo count($unconfirmed); ?></strong> unconfirmed reservation<?php echo count($unconfirmed) > 1 ? 's' : ''; ?> needing action:
                        <ul class="mb-0 mt-1" id="dashboard-unconfirmed-list">
                            <?php foreach ($unconfirmed as $uc): ?>
                            <li id="dashboard-unconfirmed-<?php echo $uc['id']; ?>">
                                <a href="#" class="text-decoration-none"
                                   hx-get="/partials/reservations/detail.php?id=<?php echo $uc['id']; ?>"
                                   hx-target="#page-content">
                                    <?php echo htmlspecialchars($uc['first_name'] . ' ' . $uc['last_name']); ?>
                                    — <?php echo date('M j', strtotime($uc['reservation_date'])); ?> at <?php echo date('g:ia', strtotime($uc['reservation_time'])); ?>
                                    (party of <?php echo (int)$uc['party_size']; ?>)
                                </a>
                            </li>
                            <?php endforeach; ?>
                        </ul>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
            <?php endif; ?>
        </div>

        <!-- Right Column -->
        <div class="col-lg-4" id="dashboard-right-col">
            <!-- Upcoming Events -->
            <?php if (!empty($upcomingEvents)): ?>
            <div class="card mb-3" id="dashboard-events-card">
                <div class="card-header d-flex justify-content-between align-items-center" id="dashboard-events-header">
                    <h6 class="mb-0" id="dashboard-events-title">Upcoming Events</h6>
                    <a href="#" class="btn btn-sm btn-outline-primary" id="dashboard-view-events"
                       hx-get="/partials/events/list.php"
                       hx-target="#page-content">View All</a>
                </div>
                <div class="list-group list-group-flush" id="dashboard-events-list">
                    <?php foreach ($upcomingEvents as $ev): ?>
                    <a href="#" class="list-group-item list-group-item-action" id="dashboard-event-<?php echo $ev['id']; ?>"
                       hx-get="/partials/events/detail.php?id=<?php echo $ev['id']; ?>"
                       hx-target="#page-content">
                        <div class="d-flex justify-content-between" id="dashboard-event-top-<?php echo $ev['id']; ?>">
                            <strong class="small"><?php echo htmlspecialchars($ev['name']); ?></strong>
                            <span class="small text-muted"><?php echo date('M j', strtotime($ev['event_date'])); ?></span>
                        </div>
                        <div class="small text-muted mt-1" id="dashboard-event-cap-<?php echo $ev['id']; ?>">
                            Capacity: <?php echo (int)$ev['max_capacity']; ?> covers
                        </div>
                    </a>
                    <?php endforeach; ?>
                </div>
            </div>
            <?php endif; ?>

            <!-- Recent Activity -->
            <div class="card mb-3" id="dashboard-activity-card">
                <div class="card-header" id="dashboard-activity-header">
                    <h6 class="mb-0" id="dashboard-activity-title">Recent Activity</h6>
                </div>
                <div class="card-body p-0" id="dashboard-activity-body">
                    <?php if (empty($activity)): ?>
                    <div class="text-center py-4 text-muted" id="dashboard-activity-empty">No recent activity.</div>
                    <?php else: ?>
                    <div class="list-group list-group-flush" id="dashboard-activity-list">
                        <?php foreach ($activity as $a): ?>
                        <div class="list-group-item px-3 py-2" id="dashboard-activity-<?php echo md5($a['created_at'] . $a['description']); ?>">
                            <div class="small fw-semibold" id="dashboard-activity-desc-<?php echo md5($a['created_at']); ?>">
                                <?php echo htmlspecialchars(mb_strimwidth($a['description'], 0, 80, '...')); ?>
                            </div>
                            <div class="text-muted" style="font-size:0.75rem;" id="dashboard-activity-meta-<?php echo md5($a['created_at']); ?>">
                                <?php
                                    $who = $a['first_name'] ? ($a['first_name'] . ' ' . $a['last_name']) : 'System';
                                    echo htmlspecialchars($who) . ' — ' . date('g:ia', strtotime($a['created_at']));
                                ?>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>
