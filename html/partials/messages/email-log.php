<?php
/**
 * Email Message Log — read-only list of inbound/outbound email messages.
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
    $where .= " AND (from_address LIKE ? OR to_address LIKE ? OR subject LIKE ? OR body_text LIKE ?)";
    $searchLike = "%{$search}%";
    $params[] = $searchLike;
    $params[] = $searchLike;
    $params[] = $searchLike;
    $params[] = $searchLike;
}

// Count
$countStmt = $pdo->prepare("SELECT COUNT(*) FROM email_message_log {$where}");
$countStmt->execute($params);
$total = (int)$countStmt->fetchColumn();
$totalPages = max(1, ceil($total / $perPage));

// Fetch
$stmt = $pdo->prepare(
    "SELECT * FROM email_message_log {$where} ORDER BY created_at DESC LIMIT {$perPage} OFFSET {$offset}"
);
$stmt->execute($params);
$emails = $stmt->fetchAll();
?>

<div class="p-4" id="email-log-page">
    <div class="d-flex justify-content-between align-items-center mb-4" id="email-log-header">
        <h4 class="fw-bold mb-0" id="email-log-title">
            <i class="feather-mail me-2"></i>Email Messages
        </h4>
        <span class="badge bg-primary fs-6" id="email-log-count"><?php echo $total; ?> messages</span>
    </div>

    <!-- Filters -->
    <div class="card mb-4" id="email-log-filters-card">
        <div class="card-body py-2" id="email-log-filters-body">
            <form class="row g-2 align-items-end" id="email-log-filter-form"
                  hx-get="/partials/messages/email-log.php" hx-target="#page-content">
                <div class="col-md-3" id="email-log-filter-direction-wrap">
                    <label for="email-log-filter-direction" class="form-label form-label-sm mb-0">Direction</label>
                    <select class="form-select form-select-sm" id="email-log-filter-direction" name="direction">
                        <option value="">All</option>
                        <option value="inbound" <?php echo $directionFilter === 'inbound' ? 'selected' : ''; ?>>Inbound</option>
                        <option value="outbound" <?php echo $directionFilter === 'outbound' ? 'selected' : ''; ?>>Outbound</option>
                    </select>
                </div>
                <div class="col-md-5" id="email-log-filter-search-wrap">
                    <label for="email-log-filter-search" class="form-label form-label-sm mb-0">Search</label>
                    <input type="text" class="form-control form-control-sm" id="email-log-filter-search"
                           name="search" placeholder="Email address, subject, or body"
                           value="<?php echo htmlspecialchars($search); ?>">
                </div>
                <div class="col-md-2" id="email-log-filter-submit-wrap">
                    <button type="submit" class="btn btn-sm btn-primary w-100" id="email-log-filter-btn">Filter</button>
                </div>
                <div class="col-md-2" id="email-log-filter-reset-wrap">
                    <a href="#" class="btn btn-sm btn-outline-secondary w-100" id="email-log-reset-btn"
                       hx-get="/partials/messages/email-log.php" hx-target="#page-content">Reset</a>
                </div>
            </form>
        </div>
    </div>

    <!-- Email List -->
    <div class="card" id="email-log-list-card">
        <div class="card-body p-0" id="email-log-list-body">
            <?php if (empty($emails)): ?>
            <div class="text-center py-5 text-muted" id="email-log-empty">
                <i class="feather-mail fs-1 d-block mb-2"></i>
                <p class="mb-0">No email messages found.</p>
            </div>
            <?php else: ?>
            <div class="table-responsive" id="email-log-table-wrap">
                <table class="table table-hover mb-0" id="email-log-table">
                    <thead>
                        <tr id="email-log-table-head">
                            <th id="email-log-th-direction">Direction</th>
                            <th id="email-log-th-from">From</th>
                            <th id="email-log-th-to">To</th>
                            <th id="email-log-th-subject">Subject</th>
                            <th id="email-log-th-status">Status</th>
                            <th id="email-log-th-date">Date</th>
                        </tr>
                    </thead>
                    <tbody id="email-log-table-body">
                        <?php foreach ($emails as $e): ?>
                        <tr id="email-log-row-<?php echo $e['id']; ?>">
                            <td id="email-log-dir-<?php echo $e['id']; ?>">
                                <?php if ($e['direction'] === 'inbound'): ?>
                                <span class="badge bg-success">Inbound</span>
                                <?php else: ?>
                                <span class="badge bg-info">Outbound</span>
                                <?php endif; ?>
                            </td>
                            <td id="email-log-from-<?php echo $e['id']; ?>"><?php echo htmlspecialchars($e['from_address']); ?></td>
                            <td id="email-log-to-<?php echo $e['id']; ?>"><?php echo htmlspecialchars($e['to_address']); ?></td>
                            <td id="email-log-subject-<?php echo $e['id']; ?>"><?php echo htmlspecialchars(mb_substr($e['subject'] ?? '', 0, 60)); ?><?php echo mb_strlen($e['subject'] ?? '') > 60 ? '...' : ''; ?></td>
                            <td id="email-log-status-<?php echo $e['id']; ?>">
                                <span class="badge bg-<?php echo $e['status'] === 'failed' ? 'danger' : 'secondary'; ?>"><?php echo htmlspecialchars($e['status']); ?></span>
                            </td>
                            <td id="email-log-date-<?php echo $e['id']; ?>" class="text-nowrap"><?php echo date('M j, g:ia', strtotime($e['created_at'])); ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <?php endif; ?>
        </div>

        <?php if ($totalPages > 1): ?>
        <div class="card-footer" id="email-log-pagination">
            <nav id="email-log-pagination-nav">
                <ul class="pagination pagination-sm justify-content-center mb-0" id="email-log-pagination-list">
                    <?php for ($i = 1; $i <= $totalPages; $i++): ?>
                    <li class="page-item <?php echo $i === $page ? 'active' : ''; ?>" id="email-log-page-<?php echo $i; ?>">
                        <a class="page-link" href="#"
                           hx-get="/partials/messages/email-log.php?page=<?php echo $i; ?>&direction=<?php echo urlencode($directionFilter); ?>&search=<?php echo urlencode($search); ?>"
                           hx-target="#page-content"><?php echo $i; ?></a>
                    </li>
                    <?php endfor; ?>
                </ul>
            </nav>
        </div>
        <?php endif; ?>
    </div>
</div>
