<?php
/**
 * SMS Message Log — read-only list of inbound/outbound SMS messages.
 */
require_once __DIR__ . '/../../../helpers/auth.php';

requireAuth();

$restaurantId = currentRestaurantId();
$pdo = db();

// Pagination
$page = max(1, (int)($_GET['page'] ?? 1));
$perPage = 25;
$offset = ($page - 1) * $perPage;

// Filters
$directionFilter = $_GET['direction'] ?? '';
$search = trim($_GET['search'] ?? '');

$where = "WHERE restaurant_id = ?";
$params = [$restaurantId];

if ($directionFilter === 'inbound' || $directionFilter === 'outbound') {
    $where .= " AND direction = ?";
    $params[] = $directionFilter;
}
if ($search !== '') {
    $where .= " AND (from_number LIKE ? OR to_number LIKE ? OR body LIKE ?)";
    $searchLike = "%{$search}%";
    $params[] = $searchLike;
    $params[] = $searchLike;
    $params[] = $searchLike;
}

// Count
$countStmt = $pdo->prepare("SELECT COUNT(*) FROM sms_message_log {$where}");
$countStmt->execute($params);
$total = (int)$countStmt->fetchColumn();
$totalPages = max(1, ceil($total / $perPage));

// Fetch
$stmt = $pdo->prepare(
    "SELECT * FROM sms_message_log {$where} ORDER BY created_at DESC LIMIT {$perPage} OFFSET {$offset}"
);
$stmt->execute($params);
$messages = $stmt->fetchAll();
?>

<div class="p-4" id="sms-log-page">
    <div class="d-flex justify-content-between align-items-center mb-4" id="sms-log-header">
        <h4 class="fw-bold mb-0" id="sms-log-title">
            <i class="feather-message-square me-2"></i>SMS Messages
        </h4>
        <span class="badge bg-primary fs-6" id="sms-log-count"><?php echo $total; ?> messages</span>
    </div>

    <!-- Filters -->
    <div class="card mb-4" id="sms-log-filters-card">
        <div class="card-body py-2" id="sms-log-filters-body">
            <form class="row g-2 align-items-end" id="sms-log-filter-form"
                  hx-get="/partials/messages/sms-log.php" hx-target="#page-content">
                <div class="col-md-3" id="sms-log-filter-direction-wrap">
                    <label for="sms-log-filter-direction" class="form-label form-label-sm mb-0">Direction</label>
                    <select class="form-select form-select-sm" id="sms-log-filter-direction" name="direction">
                        <option value="">All</option>
                        <option value="inbound" <?php echo $directionFilter === 'inbound' ? 'selected' : ''; ?>>Inbound</option>
                        <option value="outbound" <?php echo $directionFilter === 'outbound' ? 'selected' : ''; ?>>Outbound</option>
                    </select>
                </div>
                <div class="col-md-5" id="sms-log-filter-search-wrap">
                    <label for="sms-log-filter-search" class="form-label form-label-sm mb-0">Search</label>
                    <input type="text" class="form-control form-control-sm" id="sms-log-filter-search"
                           name="search" placeholder="Phone number or message text"
                           value="<?php echo htmlspecialchars($search); ?>">
                </div>
                <div class="col-md-2" id="sms-log-filter-submit-wrap">
                    <button type="submit" class="btn btn-sm btn-primary w-100" id="sms-log-filter-btn">Filter</button>
                </div>
                <div class="col-md-2" id="sms-log-filter-reset-wrap">
                    <a href="#" class="btn btn-sm btn-outline-secondary w-100" id="sms-log-reset-btn"
                       hx-get="/partials/messages/sms-log.php" hx-target="#page-content">Reset</a>
                </div>
            </form>
        </div>
    </div>

    <!-- Message List -->
    <div class="card" id="sms-log-list-card">
        <div class="card-body p-0" id="sms-log-list-body">
            <?php if (empty($messages)): ?>
            <div class="text-center py-5 text-muted" id="sms-log-empty">
                <i class="feather-message-square fs-1 d-block mb-2"></i>
                <p class="mb-0">No SMS messages found.</p>
            </div>
            <?php else: ?>
            <div class="table-responsive" id="sms-log-table-wrap">
                <table class="table table-hover mb-0" id="sms-log-table">
                    <thead>
                        <tr id="sms-log-table-head">
                            <th id="sms-log-th-direction">Direction</th>
                            <th id="sms-log-th-from">From</th>
                            <th id="sms-log-th-to">To</th>
                            <th id="sms-log-th-body">Message</th>
                            <th id="sms-log-th-status">Status</th>
                            <th id="sms-log-th-date">Date</th>
                        </tr>
                    </thead>
                    <tbody id="sms-log-table-body">
                        <?php foreach ($messages as $m): ?>
                        <tr id="sms-log-row-<?php echo $m['id']; ?>">
                            <td id="sms-log-dir-<?php echo $m['id']; ?>">
                                <?php if ($m['direction'] === 'inbound'): ?>
                                <span class="badge bg-success">Inbound</span>
                                <?php else: ?>
                                <span class="badge bg-info">Outbound</span>
                                <?php endif; ?>
                            </td>
                            <td id="sms-log-from-<?php echo $m['id']; ?>"><?php echo htmlspecialchars($m['from_number']); ?></td>
                            <td id="sms-log-to-<?php echo $m['id']; ?>"><?php echo htmlspecialchars($m['to_number']); ?></td>
                            <td id="sms-log-body-<?php echo $m['id']; ?>"><?php echo htmlspecialchars(mb_substr($m['body'], 0, 80)); ?><?php echo mb_strlen($m['body']) > 80 ? '...' : ''; ?></td>
                            <td id="sms-log-status-<?php echo $m['id']; ?>">
                                <span class="badge bg-<?php echo $m['status'] === 'failed' ? 'danger' : 'secondary'; ?>"><?php echo htmlspecialchars($m['status']); ?></span>
                            </td>
                            <td id="sms-log-date-<?php echo $m['id']; ?>" class="text-nowrap"><?php echo date('M j, g:ia', strtotime($m['created_at'])); ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <?php endif; ?>
        </div>

        <?php if ($totalPages > 1): ?>
        <div class="card-footer" id="sms-log-pagination">
            <nav id="sms-log-pagination-nav">
                <ul class="pagination pagination-sm justify-content-center mb-0" id="sms-log-pagination-list">
                    <?php for ($i = 1; $i <= $totalPages; $i++): ?>
                    <li class="page-item <?php echo $i === $page ? 'active' : ''; ?>" id="sms-log-page-<?php echo $i; ?>">
                        <a class="page-link" href="#"
                           hx-get="/partials/messages/sms-log.php?page=<?php echo $i; ?>&direction=<?php echo urlencode($directionFilter); ?>&search=<?php echo urlencode($search); ?>"
                           hx-target="#page-content"><?php echo $i; ?></a>
                    </li>
                    <?php endfor; ?>
                </ul>
            </nav>
        </div>
        <?php endif; ?>
    </div>
</div>
