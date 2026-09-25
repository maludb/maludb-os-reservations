<?php
/**
 * Events List — Upcoming, past, and draft events
 */
require_once __DIR__ . '/../../../helpers/auth.php';
require_once __DIR__ . '/../../../helpers/db.php';
require_once __DIR__ . '/../../../helpers/csrf.php';

requireAuth();
$restaurantId = currentRestaurantId();
$pdo = db();
$today = date('Y-m-d');

$filter = trim($_GET['filter'] ?? 'upcoming');

// Build query based on filter
$where = ['e.restaurant_id = ?'];
$params = [$restaurantId];

if ($filter === 'upcoming') {
    $where[] = "e.event_date >= ?";
    $where[] = "e.status IN ('draft', 'published')";
    $params[] = $today;
    $orderBy = 'e.event_date ASC, e.start_time ASC';
} elseif ($filter === 'draft') {
    $where[] = "e.status = 'draft'";
    $orderBy = 'e.event_date ASC, e.start_time ASC';
} elseif ($filter === 'past') {
    $where[] = "(e.event_date < ? OR e.status IN ('completed', 'cancelled'))";
    $params[] = $today;
    $orderBy = 'e.event_date DESC, e.start_time DESC';
} else {
    $orderBy = 'e.event_date DESC, e.start_time DESC';
}

$whereClause = implode(' AND ', $where);

$stmt = $pdo->prepare(
    "SELECT e.*,
            (SELECT COALESCE(SUM(r.party_size), 0) FROM reservations r
             WHERE r.event_id = e.id AND r.status NOT IN ('cancelled', 'no_show')) AS booked_covers,
            (SELECT COUNT(*) FROM reservations r
             WHERE r.event_id = e.id AND r.status NOT IN ('cancelled', 'no_show')) AS booking_count
     FROM events e
     WHERE {$whereClause}
     ORDER BY {$orderBy}"
);
$stmt->execute($params);
$events = $stmt->fetchAll();

$statusColors = [
    'draft' => 'secondary', 'published' => 'success',
    'cancelled' => 'danger', 'completed' => 'dark',
];
?>

<div class="main-content" id="events-main">
    <div class="d-flex justify-content-between align-items-center mb-4" id="events-header">
        <h4 class="mb-0" id="events-title">Events</h4>
        <button class="btn btn-primary" id="events-create-btn"
                hx-get="/partials/events/form.php"
                hx-target="#events-modal-container"
                hx-swap="innerHTML">
            <i class="feather-plus me-1"></i> Create Event
        </button>
    </div>

    <div id="events-messages"></div>
    <div id="events-modal-container"></div>

    <!-- Filter Tabs -->
    <ul class="nav nav-tabs mb-3" id="events-filter-tabs">
        <?php
        $tabs = ['upcoming' => 'Upcoming', 'draft' => 'Drafts', 'past' => 'Past'];
        foreach ($tabs as $key => $label):
        ?>
        <li class="nav-item" id="events-tab-<?php echo $key; ?>">
            <a class="nav-link <?php echo $filter === $key ? 'active' : ''; ?>" href="#"
               hx-get="/partials/events/list.php?filter=<?php echo $key; ?>"
               hx-target="#page-content"
               hx-swap="innerHTML">
                <?php echo $label; ?>
            </a>
        </li>
        <?php endforeach; ?>
    </ul>

    <?php if (empty($events)): ?>
    <div class="card" id="events-empty-card">
        <div class="card-body text-center py-5 text-muted" id="events-empty-body">
            <i class="feather-calendar" style="font-size:48px;"></i>
            <p class="mt-3 mb-0">No <?php echo $filter; ?> events found.</p>
        </div>
    </div>
    <?php else: ?>

    <div class="row g-3" id="events-grid">
        <?php foreach ($events as $ev): ?>
        <div class="col-md-6 col-xl-4" id="events-card-col-<?php echo $ev['id']; ?>">
            <div class="card h-100" id="events-card-<?php echo $ev['id']; ?>"
                 style="cursor:pointer;"
                 hx-get="/partials/events/detail.php?id=<?php echo $ev['id']; ?>"
                 hx-target="#page-content">
                <div class="card-body" id="events-card-body-<?php echo $ev['id']; ?>">
                    <div class="d-flex justify-content-between align-items-start mb-2" id="events-card-top-<?php echo $ev['id']; ?>">
                        <h6 class="fw-bold mb-0" id="events-card-name-<?php echo $ev['id']; ?>">
                            <?php echo htmlspecialchars($ev['name']); ?>
                        </h6>
                        <span class="badge bg-<?php echo $statusColors[$ev['status']] ?? 'secondary'; ?>" id="events-card-status-<?php echo $ev['id']; ?>">
                            <?php echo ucfirst($ev['status']); ?>
                        </span>
                    </div>

                    <div class="text-muted small mb-3" id="events-card-datetime-<?php echo $ev['id']; ?>">
                        <i class="feather-calendar me-1"></i>
                        <?php echo date('l, M j, Y', strtotime($ev['event_date'])); ?>
                        <br>
                        <i class="feather-clock me-1"></i>
                        <?php echo date('g:ia', strtotime($ev['start_time'])); ?>
                        <?php if ($ev['end_time']): ?>
                            – <?php echo date('g:ia', strtotime($ev['end_time'])); ?>
                        <?php endif; ?>
                    </div>

                    <?php if ($ev['description']): ?>
                    <p class="small text-muted mb-3" id="events-card-desc-<?php echo $ev['id']; ?>">
                        <?php echo htmlspecialchars(mb_strimwidth($ev['description'], 0, 100, '...')); ?>
                    </p>
                    <?php endif; ?>

                    <!-- Capacity bar -->
                    <?php
                    $pct = $ev['max_capacity'] > 0 ? min(100, round(($ev['booked_covers'] / $ev['max_capacity']) * 100)) : 0;
                    $barColor = $pct >= 90 ? 'danger' : ($pct >= 70 ? 'warning' : 'success');
                    ?>
                    <div class="mb-2" id="events-card-capacity-<?php echo $ev['id']; ?>">
                        <div class="d-flex justify-content-between small mb-1" id="events-card-capacity-text-<?php echo $ev['id']; ?>">
                            <span><?php echo (int)$ev['booked_covers']; ?> / <?php echo (int)$ev['max_capacity']; ?> covers</span>
                            <span><?php echo (int)$ev['booking_count']; ?> booking<?php echo $ev['booking_count'] != 1 ? 's' : ''; ?></span>
                        </div>
                        <div class="progress" style="height:6px;" id="events-card-progress-<?php echo $ev['id']; ?>">
                            <div class="progress-bar bg-<?php echo $barColor; ?>" style="width:<?php echo $pct; ?>%"></div>
                        </div>
                    </div>

                    <?php if ($ev['price_per_person'] !== null): ?>
                    <div class="small fw-semibold text-primary" id="events-card-price-<?php echo $ev['id']; ?>">
                        $<?php echo number_format((float)$ev['price_per_person'], 2); ?> / person
                    </div>
                    <?php else: ?>
                    <div class="small text-muted" id="events-card-price-<?php echo $ev['id']; ?>">Free event</div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        <?php endforeach; ?>
    </div>

    <?php endif; ?>
</div>
