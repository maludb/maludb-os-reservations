<?php
/**
 * Searchable Reservation List with pagination
 */
require_once '../../../helpers/auth.php';
require_once '../../../helpers/csrf.php';

requireAuth();

$restaurantId = currentRestaurantId();

// Search/filter params
$search = trim($_GET['search'] ?? '');
$dateFrom = trim($_GET['date_from'] ?? '');
$dateTo = trim($_GET['date_to'] ?? '');
$filterStatus = trim($_GET['status'] ?? '');
$page = max(1, (int)($_GET['page'] ?? 1));
$perPage = 25;
$offset = ($page - 1) * $perPage;

// Build query
$where = ["r.restaurant_id = ?", "r.source != 'walk_in'"];
$params = [$restaurantId];

if ($search !== '') {
    $where[] = "(g.first_name LIKE ? OR g.last_name LIKE ? OR g.phone LIKE ? OR g.email LIKE ?
                 OR r.confirmation_code LIKE ? OR CONCAT(g.first_name, ' ', g.last_name) LIKE ?)";
    $s = '%' . $search . '%';
    array_push($params, $s, $s, $s, $s, $s, $s);
}

if ($dateFrom !== '') {
    $where[] = "r.reservation_date >= ?";
    $params[] = $dateFrom;
}

if ($dateTo !== '') {
    $where[] = "r.reservation_date <= ?";
    $params[] = $dateTo;
}

if ($filterStatus !== '') {
    $where[] = "r.status = ?";
    $params[] = $filterStatus;
} else {
    $where[] = "r.status != 'cancelled'";
}

$whereClause = implode(' AND ', $where);

// Count total
$stmt = db()->prepare("SELECT COUNT(*) as cnt FROM reservations r JOIN guests g ON r.guest_id = g.id WHERE {$whereClause}");
$stmt->execute($params);
$total = (int)$stmt->fetch()['cnt'];
$totalPages = max(1, ceil($total / $perPage));

// Fetch page
$sql = "SELECT r.*, g.first_name, g.last_name, g.phone as guest_phone, g.email as guest_email,
               t.table_name
        FROM reservations r
        JOIN guests g ON r.guest_id = g.id
        LEFT JOIN tables t ON r.table_id = t.id
        WHERE {$whereClause}
        ORDER BY r.reservation_date DESC, r.reservation_time DESC
        LIMIT {$perPage} OFFSET {$offset}";

$stmt = db()->prepare($sql);
$stmt->execute($params);
$reservations = $stmt->fetchAll();

$statusColors = [
    'pending' => 'warning',
    'confirmed' => 'primary',
    'seated' => 'success',
    'completed' => 'secondary',
    'no_show' => 'danger',
    'cancelled' => 'dark',
];
?>
<div class="main-content" id="reslist-main">
    <div class="row" id="reslist-row">
        <div class="col-12" id="reslist-col">

            <div class="card" id="reslist-card">
                <div class="card-header d-flex align-items-center justify-content-between" id="reslist-card-header">
                    <h5 class="card-title mb-0" id="reslist-card-title">
                        <i class="feather-list me-2"></i>Reservations
                        <span class="badge bg-secondary ms-1"><?php echo $total; ?></span>
                    </h5>
                    <div class="d-flex" id="reslist-header-actions">
                        <button class="btn btn-primary btn-sm me-1" id="reslist-new-btn"
                                hx-get="/partials/reservations/create-form.php"
                                hx-target="#page-content" hx-swap="innerHTML">
                            <i class="feather-plus me-1"></i> New Reservation
                        </button>
                        <button class="btn btn-outline-secondary btn-sm" id="reslist-cal-btn"
                                hx-get="/partials/reservations/calendar.php"
                                hx-target="#page-content" hx-swap="innerHTML">
                            <i class="feather-calendar me-1"></i> Calendar
                        </button>
                    </div>
                </div>
                <div class="card-body" id="reslist-card-body">

                    <!-- Search/Filter -->
                    <div class="row mb-3 g-2" id="reslist-filters">
                        <div class="col-md-4" id="reslist-search-group">
                            <input type="text" class="form-control form-control-sm" id="reslist-search"
                                   placeholder="Search name, phone, email, code..."
                                   value="<?php echo htmlspecialchars($search); ?>"
                                   hx-get="/partials/reservations/list.php"
                                   hx-trigger="keyup changed delay:400ms"
                                   hx-include="#reslist-date-from, #reslist-date-to, #reslist-status"
                                   hx-target="#page-content" hx-swap="innerHTML"
                                   name="search">
                        </div>
                        <div class="col-md-2" id="reslist-datefrom-group">
                            <input type="date" class="form-control form-control-sm" id="reslist-date-from"
                                   value="<?php echo htmlspecialchars($dateFrom); ?>" name="date_from"
                                   hx-get="/partials/reservations/list.php"
                                   hx-trigger="change"
                                   hx-include="#reslist-search, #reslist-date-to, #reslist-status"
                                   hx-target="#page-content" hx-swap="innerHTML"
                                   placeholder="From">
                        </div>
                        <div class="col-md-2" id="reslist-dateto-group">
                            <input type="date" class="form-control form-control-sm" id="reslist-date-to"
                                   value="<?php echo htmlspecialchars($dateTo); ?>" name="date_to"
                                   hx-get="/partials/reservations/list.php"
                                   hx-trigger="change"
                                   hx-include="#reslist-search, #reslist-date-from, #reslist-status"
                                   hx-target="#page-content" hx-swap="innerHTML">
                        </div>
                        <div class="col-md-2" id="reslist-status-group">
                            <select class="form-select form-select-sm" id="reslist-status" name="status"
                                    hx-get="/partials/reservations/list.php"
                                    hx-trigger="change"
                                    hx-include="#reslist-search, #reslist-date-from, #reslist-date-to"
                                    hx-target="#page-content" hx-swap="innerHTML">
                                <option value="">All Statuses</option>
                                <?php foreach ($statusColors as $st => $col): ?>
                                <option value="<?php echo $st; ?>" <?php echo $filterStatus === $st ? 'selected' : ''; ?>>
                                    <?php echo ucfirst(str_replace('_', ' ', $st)); ?>
                                </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-2 text-end" id="reslist-clear-group">
                            <button class="btn btn-outline-secondary btn-sm" id="reslist-clear-btn"
                                    hx-get="/partials/reservations/list.php"
                                    hx-target="#page-content" hx-swap="innerHTML">
                                Clear
                            </button>
                        </div>
                    </div>

                    <!-- Results table -->
                    <div class="table-responsive" id="reslist-table-wrapper">
                        <table class="table table-hover align-middle" id="reslist-table">
                            <thead id="reslist-table-head">
                                <tr>
                                    <th>Code</th>
                                    <th>Date / Time</th>
                                    <th>Guest</th>
                                    <th>Party</th>
                                    <th>Table</th>
                                    <th>Status</th>
                                    <th class="text-end">Actions</th>
                                </tr>
                            </thead>
                            <tbody id="reslist-table-body">
                                <?php if (empty($reservations)): ?>
                                <tr id="reslist-empty-row">
                                    <td colspan="7" class="text-center text-muted py-4">No reservations found.</td>
                                </tr>
                                <?php else: ?>
                                <?php foreach ($reservations as $r):
                                    $badgeColor = $statusColors[$r['status']] ?? 'secondary';
                                ?>
                                <tr id="reslist-row-<?php echo $r['id']; ?>">
                                    <td><code><?php echo htmlspecialchars($r['confirmation_code']); ?></code></td>
                                    <td>
                                        <?php echo date('M j, Y', strtotime($r['reservation_date'])); ?>
                                        <br><small class="text-muted"><?php echo date('g:ia', strtotime($r['reservation_time'])); ?></small>
                                    </td>
                                    <td>
                                        <?php echo htmlspecialchars($r['first_name'] . ' ' . $r['last_name']); ?>
                                        <?php if ($r['guest_phone']): ?>
                                        <br><small class="text-muted"><?php echo htmlspecialchars($r['guest_phone']); ?></small>
                                        <?php endif; ?>
                                    </td>
                                    <td><?php echo (int)$r['party_size']; ?></td>
                                    <td><?php echo $r['table_name'] ? htmlspecialchars($r['table_name']) : '<span class="text-muted">Unassigned</span>'; ?></td>
                                    <td><span class="badge bg-<?php echo $badgeColor; ?>"><?php echo ucfirst(str_replace('_', ' ', $r['status'])); ?></span></td>
                                    <td class="text-end" id="reslist-actions-<?php echo $r['id']; ?>">
                                        <button class="btn btn-outline-primary btn-sm"
                                                hx-get="/partials/reservations/detail.php?id=<?php echo $r['id']; ?>"
                                                hx-target="#page-content" hx-swap="innerHTML"
                                                title="View Details">
                                            <i class="feather-eye"></i>
                                        </button>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>

                    <!-- Pagination -->
                    <?php if ($totalPages > 1): ?>
                    <nav id="reslist-pagination">
                        <ul class="pagination pagination-sm justify-content-center mb-0" id="reslist-pagination-list">
                            <?php for ($p = 1; $p <= $totalPages; $p++): ?>
                            <li class="page-item <?php echo $p === $page ? 'active' : ''; ?>" id="reslist-page-<?php echo $p; ?>">
                                <a class="page-link" href="#"
                                   hx-get="/partials/reservations/list.php?page=<?php echo $p; ?>&search=<?php echo urlencode($search); ?>&date_from=<?php echo urlencode($dateFrom); ?>&date_to=<?php echo urlencode($dateTo); ?>&status=<?php echo urlencode($filterStatus); ?>"
                                   hx-target="#page-content" hx-swap="innerHTML">
                                    <?php echo $p; ?>
                                </a>
                            </li>
                            <?php endfor; ?>
                        </ul>
                    </nav>
                    <?php endif; ?>

                </div>
            </div>

        </div>
    </div>
</div>
