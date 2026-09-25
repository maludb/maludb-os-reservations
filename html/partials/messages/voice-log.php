<?php
/**
 * Voice Call Log — read-only list of inbound/outbound calls from call_logs.
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
    $where .= " AND (from_number LIKE ? OR to_number LIKE ? OR call_summary LIKE ?)";
    $searchLike = "%{$search}%";
    $params[] = $searchLike;
    $params[] = $searchLike;
    $params[] = $searchLike;
}

// Count
$countStmt = $pdo->prepare("SELECT COUNT(*) FROM call_logs {$where}");
$countStmt->execute($params);
$total = (int)$countStmt->fetchColumn();
$totalPages = max(1, ceil($total / $perPage));

// Fetch
$stmt = $pdo->prepare(
    "SELECT * FROM call_logs {$where} ORDER BY created_at DESC LIMIT {$perPage} OFFSET {$offset}"
);
$stmt->execute($params);
$calls = $stmt->fetchAll();
?>

<div class="p-4" id="voice-log-page">
    <div class="d-flex justify-content-between align-items-center mb-4" id="voice-log-header">
        <h4 class="fw-bold mb-0" id="voice-log-title">
            <i class="feather-phone-call me-2"></i>Voice Calls
        </h4>
        <span class="badge bg-primary fs-6" id="voice-log-count"><?php echo $total; ?> calls</span>
    </div>

    <!-- Filters -->
    <div class="card mb-4" id="voice-log-filters-card">
        <div class="card-body py-2" id="voice-log-filters-body">
            <form class="row g-2 align-items-end" id="voice-log-filter-form"
                  hx-get="/partials/messages/voice-log.php" hx-target="#page-content">
                <div class="col-md-3" id="voice-log-filter-direction-wrap">
                    <label for="voice-log-filter-direction" class="form-label form-label-sm mb-0">Direction</label>
                    <select class="form-select form-select-sm" id="voice-log-filter-direction" name="direction">
                        <option value="">All</option>
                        <option value="inbound" <?php echo $directionFilter === 'inbound' ? 'selected' : ''; ?>>Inbound</option>
                        <option value="outbound" <?php echo $directionFilter === 'outbound' ? 'selected' : ''; ?>>Outbound</option>
                    </select>
                </div>
                <div class="col-md-5" id="voice-log-filter-search-wrap">
                    <label for="voice-log-filter-search" class="form-label form-label-sm mb-0">Search</label>
                    <input type="text" class="form-control form-control-sm" id="voice-log-filter-search"
                           name="search" placeholder="Phone number or call summary"
                           value="<?php echo htmlspecialchars($search); ?>">
                </div>
                <div class="col-md-2" id="voice-log-filter-submit-wrap">
                    <button type="submit" class="btn btn-sm btn-primary w-100" id="voice-log-filter-btn">Filter</button>
                </div>
                <div class="col-md-2" id="voice-log-filter-reset-wrap">
                    <a href="#" class="btn btn-sm btn-outline-secondary w-100" id="voice-log-reset-btn"
                       hx-get="/partials/messages/voice-log.php" hx-target="#page-content">Reset</a>
                </div>
            </form>
        </div>
    </div>

    <!-- Call List -->
    <div class="card" id="voice-log-list-card">
        <div class="card-body p-0" id="voice-log-list-body">
            <?php if (empty($calls)): ?>
            <div class="text-center py-5 text-muted" id="voice-log-empty">
                <i class="feather-phone-off fs-1 d-block mb-2"></i>
                <p class="mb-0">No voice calls found.</p>
            </div>
            <?php else: ?>
            <div class="table-responsive" id="voice-log-table-wrap">
                <table class="table table-hover mb-0" id="voice-log-table">
                    <thead>
                        <tr id="voice-log-table-head">
                            <th id="voice-log-th-direction">Direction</th>
                            <th id="voice-log-th-from">From</th>
                            <th id="voice-log-th-to">To</th>
                            <th id="voice-log-th-duration">Duration</th>
                            <th id="voice-log-th-summary">Summary</th>
                            <th id="voice-log-th-status">Status</th>
                            <th id="voice-log-th-date">Date</th>
                        </tr>
                    </thead>
                    <tbody id="voice-log-table-body">
                        <?php foreach ($calls as $c): ?>
                        <?php
                        $durationSec = ($c['duration_ms'] ?? 0) / 1000;
                        $durationStr = $durationSec >= 60
                            ? floor($durationSec / 60) . 'm ' . ($durationSec % 60) . 's'
                            : round($durationSec) . 's';
                        ?>
                        <tr id="voice-log-row-<?php echo $c['id']; ?>">
                            <td id="voice-log-dir-<?php echo $c['id']; ?>">
                                <?php if ($c['direction'] === 'inbound'): ?>
                                <span class="badge bg-success">Inbound</span>
                                <?php else: ?>
                                <span class="badge bg-info">Outbound</span>
                                <?php endif; ?>
                            </td>
                            <td id="voice-log-from-<?php echo $c['id']; ?>"><?php echo htmlspecialchars($c['from_number'] ?? '—'); ?></td>
                            <td id="voice-log-to-<?php echo $c['id']; ?>"><?php echo htmlspecialchars($c['to_number'] ?? '—'); ?></td>
                            <td id="voice-log-dur-<?php echo $c['id']; ?>"><?php echo $durationSec > 0 ? $durationStr : '—'; ?></td>
                            <td id="voice-log-summary-<?php echo $c['id']; ?>"><?php echo htmlspecialchars(mb_substr($c['call_summary'] ?? '', 0, 80)); ?><?php echo mb_strlen($c['call_summary'] ?? '') > 80 ? '...' : ''; ?></td>
                            <td id="voice-log-status-<?php echo $c['id']; ?>">
                                <span class="badge bg-secondary"><?php echo htmlspecialchars($c['status'] ?? 'ended'); ?></span>
                            </td>
                            <td id="voice-log-date-<?php echo $c['id']; ?>" class="text-nowrap"><?php echo date('M j, g:ia', strtotime($c['created_at'])); ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <?php endif; ?>
        </div>

        <?php if ($totalPages > 1): ?>
        <div class="card-footer" id="voice-log-pagination">
            <nav id="voice-log-pagination-nav">
                <ul class="pagination pagination-sm justify-content-center mb-0" id="voice-log-pagination-list">
                    <?php for ($i = 1; $i <= $totalPages; $i++): ?>
                    <li class="page-item <?php echo $i === $page ? 'active' : ''; ?>" id="voice-log-page-<?php echo $i; ?>">
                        <a class="page-link" href="#"
                           hx-get="/partials/messages/voice-log.php?page=<?php echo $i; ?>&direction=<?php echo urlencode($directionFilter); ?>&search=<?php echo urlencode($search); ?>"
                           hx-target="#page-content"><?php echo $i; ?></a>
                    </li>
                    <?php endfor; ?>
                </ul>
            </nav>
        </div>
        <?php endif; ?>
    </div>
</div>
