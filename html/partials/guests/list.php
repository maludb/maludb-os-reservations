<?php
/**
 * Guest Directory — List with search and tag filter
 */
require_once __DIR__ . '/../../../helpers/auth.php';
require_once __DIR__ . '/../../../helpers/db.php';
require_once __DIR__ . '/../../../helpers/csrf.php';

requireAuth();
$restaurantId = currentRestaurantId();
$pdo = db();

// Search & filter params
$search = trim($_GET['search'] ?? '');
$tagFilter = trim($_GET['tag'] ?? '');
$page = max(1, (int)($_GET['page'] ?? 1));
$perPage = 25;
$offset = ($page - 1) * $perPage;

// Build query
$where = ['g.restaurant_id = ?', 'g.is_active = 1'];
$params = [$restaurantId];

if ($search !== '') {
    $where[] = "(g.first_name LIKE ? OR g.last_name LIKE ? OR g.email LIKE ? OR g.phone LIKE ? OR CONCAT(g.first_name, ' ', g.last_name) LIKE ?)";
    $s = "%{$search}%";
    $params = array_merge($params, [$s, $s, $s, $s, $s]);
}

if ($tagFilter !== '') {
    $where[] = "? = ANY(regexp_split_to_array(g.tags, '\\s*,\\s*'))";
    $params[] = $tagFilter;
}

$whereClause = implode(' AND ', $where);

// Count
$stmt = $pdo->prepare("SELECT COUNT(*) FROM guests g WHERE {$whereClause}");
$stmt->execute($params);
$total = (int)$stmt->fetchColumn();
$totalPages = max(1, ceil($total / $perPage));

// Fetch
$stmt = $pdo->prepare(
    "SELECT g.* FROM guests g
     WHERE {$whereClause}
     ORDER BY g.last_name, g.first_name
     LIMIT {$perPage} OFFSET {$offset}"
);
$stmt->execute($params);
$guests = $stmt->fetchAll();

// Get distinct tags for filter
$stmt = $pdo->prepare("SELECT DISTINCT tags FROM guests WHERE restaurant_id = ? AND tags IS NOT NULL AND tags != ''");
$stmt->execute([$restaurantId]);
$allTags = [];
foreach ($stmt->fetchAll() as $row) {
    foreach (explode(',', $row['tags']) as $t) {
        $t = trim($t);
        if ($t !== '') $allTags[$t] = true;
    }
}
ksort($allTags);
$allTags = array_keys($allTags);
?>

<div class="main-content" id="guests-main"
     hx-get="/partials/guests/list.php"
     hx-trigger="refreshGuestList from:body"
     hx-target="#guests-main"
     hx-swap="outerHTML">

    <div class="d-flex justify-content-between align-items-center mb-4" id="guests-header">
        <h4 class="mb-0" id="guests-title">Guest Directory</h4>
        <div class="d-flex gap-2" id="guests-header-actions">
            <button class="btn btn-retell-call" id="guests-campaign-btn"
                    onclick="document.getElementById('campaign-modal').style.display='flex'">
                <i class="feather-phone me-1"></i> Launch Campaign
            </button>
            <button class="btn btn-primary" id="guests-add-btn"
                    hx-get="/partials/guests/form.php"
                    hx-target="#guest-modal-container"
                    hx-swap="innerHTML">
                <i class="feather-plus me-1"></i> Add Guest
            </button>
        </div>
    </div>

    <div id="guests-messages"></div>
    <div id="guest-modal-container"></div>

    <!-- Search & Filters -->
    <div class="card mb-3" id="guests-filter-card">
        <div class="card-body" id="guests-filter-body">
            <div class="row g-3" id="guests-filter-row">
                <div class="col-md-6" id="guests-search-group">
                    <input type="text" class="form-control" id="guests-search" name="search"
                           placeholder="Search by name, email, or phone..."
                           value="<?php echo htmlspecialchars($search); ?>"
                           hx-get="/partials/guests/list.php"
                           hx-include="#guests-tag-filter"
                           hx-trigger="keyup changed delay:300ms"
                           hx-target="#guests-main"
                           hx-swap="outerHTML">
                </div>
                <div class="col-md-4" id="guests-tag-group">
                    <select class="form-select" id="guests-tag-filter" name="tag"
                            hx-get="/partials/guests/list.php"
                            hx-include="#guests-search"
                            hx-trigger="change"
                            hx-target="#guests-main"
                            hx-swap="outerHTML">
                        <option value="">All Tags</option>
                        <?php foreach ($allTags as $tag): ?>
                        <option value="<?php echo htmlspecialchars($tag); ?>" <?php echo $tagFilter === $tag ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars(ucfirst($tag)); ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-2 text-end" id="guests-count-group">
                    <span class="text-muted lh-lg"><?php echo $total; ?> guest<?php echo $total !== 1 ? 's' : ''; ?></span>
                </div>
            </div>
        </div>
    </div>

    <!-- Guest Table -->
    <div class="card" id="guests-table-card">
        <div class="card-body p-0" id="guests-table-body">
            <?php if (empty($guests)): ?>
            <div class="text-center py-5 text-muted" id="guests-empty">
                <p class="mb-0">No guests found.</p>
            </div>
            <?php else: ?>
            <div class="table-responsive" id="guests-table-wrap">
                <table class="table table-hover mb-0" id="guests-table">
                    <thead id="guests-thead">
                        <tr>
                            <th>Name</th>
                            <th>Email</th>
                            <th>Phone</th>
                            <th>Tags</th>
                            <th class="text-center">Visits</th>
                            <th class="text-center">No-Shows</th>
                            <th>Last Visit</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody id="guests-tbody">
                        <?php foreach ($guests as $g): ?>
                        <tr id="guest-row-<?php echo $g['id']; ?>">
                            <td>
                                <a href="#" class="text-decoration-none fw-semibold"
                                   hx-get="/partials/guests/detail.php?id=<?php echo $g['id']; ?>"
                                   hx-target="#page-content"
                                   hx-push-url="false">
                                    <?php echo htmlspecialchars($g['first_name'] . ' ' . $g['last_name']); ?>
                                </a>
                                <?php if (!empty($g['do_not_call'])): ?><span class="badge bg-danger ms-1" title="Do Not Call" id="guest-dnc-<?php echo $g['id']; ?>"><i class="feather-phone-off"></i></span><?php endif; ?>
                                <?php if (!empty($g['do_not_email'])): ?><span class="badge bg-danger ms-1" title="Do Not Email" id="guest-dne-<?php echo $g['id']; ?>"><i class="feather-mail"></i></span><?php endif; ?>
                                <?php if (!empty($g['do_not_text'])): ?><span class="badge bg-danger ms-1" title="Do Not Text" id="guest-dnt-<?php echo $g['id']; ?>"><i class="feather-message-circle"></i></span><?php endif; ?>
                            </td>
                            <td><?php echo htmlspecialchars($g['email'] ?? '—'); ?></td>
                            <td><?php echo htmlspecialchars($g['phone'] ?? '—'); ?></td>
                            <td>
                                <?php if ($g['tags']):
                                    foreach (explode(',', $g['tags']) as $tag):
                                        $tag = trim($tag);
                                        if ($tag === '') continue;
                                        $color = $tag === 'vip' ? 'warning' : ($tag === 'reviewer' ? 'info' : 'secondary');
                                ?>
                                <span class="badge bg-<?php echo $color; ?> me-1"><?php echo htmlspecialchars(ucfirst($tag)); ?></span>
                                <?php endforeach; endif; ?>
                            </td>
                            <td class="text-center"><?php echo (int)$g['visit_count']; ?></td>
                            <td class="text-center">
                                <?php if ((int)$g['noshow_count'] > 0): ?>
                                <span class="text-danger fw-bold"><?php echo (int)$g['noshow_count']; ?></span>
                                <?php else: ?>
                                0
                                <?php endif; ?>
                            </td>
                            <td><?php echo $g['last_visit_at'] ? date('M j, Y', strtotime($g['last_visit_at'])) : '—'; ?></td>
                            <td>
                                <button class="btn btn-sm btn-outline-primary" title="Edit"
                                        hx-get="/partials/guests/form.php?id=<?php echo $g['id']; ?>"
                                        hx-target="#guest-modal-container"
                                        hx-swap="innerHTML">
                                    <i class="feather-edit"></i>
                                </button>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>

            <?php if ($totalPages > 1): ?>
            <div class="card-footer" id="guests-pagination">
                <nav id="guests-pagination-nav">
                    <ul class="pagination pagination-sm justify-content-center mb-0" id="guests-pagination-ul">
                        <?php for ($p = 1; $p <= $totalPages; $p++): ?>
                        <li class="page-item <?php echo $p === $page ? 'active' : ''; ?>" id="guests-page-<?php echo $p; ?>">
                            <a class="page-link" href="#"
                               hx-get="/partials/guests/list.php?page=<?php echo $p; ?>&search=<?php echo urlencode($search); ?>&tag=<?php echo urlencode($tagFilter); ?>"
                               hx-target="#guests-main"
                               hx-swap="outerHTML">
                                <?php echo $p; ?>
                            </a>
                        </li>
                        <?php endfor; ?>
                    </ul>
                </nav>
            </div>
            <?php endif; ?>

            <?php endif; ?>
        </div>
    </div>

    <!-- Launch Campaign Modal -->
    <div id="campaign-modal" style="display:none; position:fixed; top:0; left:0; width:100%; height:100%; background:rgba(0,0,0,0.5); z-index:9999; align-items:center; justify-content:center;">
        <div class="card" style="width:100%; max-width:460px;" id="campaign-modal-card">
            <div class="card-header d-flex justify-content-between align-items-center" id="campaign-modal-header">
                <h5 class="mb-0" id="campaign-modal-title"><i class="feather-phone me-2"></i>Launch Campaign</h5>
                <button type="button" class="btn-close" id="campaign-modal-close"
                        onclick="document.getElementById('campaign-modal').style.display='none'"></button>
            </div>
            <div class="card-body" id="campaign-modal-body">
                <div class="mb-3" id="campaign-agent-group">
                    <label for="campaign-agent-id" class="form-label fw-semibold">Agent ID</label>
                    <input type="text" class="form-control" id="campaign-agent-id"
                           placeholder="agent_xxxxxxxxxxxx">
                    <small class="text-muted">Enter the Retell agent ID to use for this campaign.</small>
                </div>
            </div>
            <div class="card-footer d-flex justify-content-end gap-2" id="campaign-modal-footer">
                <button class="btn btn-secondary" id="campaign-cancel-btn"
                        onclick="document.getElementById('campaign-modal').style.display='none'">Cancel</button>
                <button class="btn btn-retell-call" id="campaign-start-btn">
                    <i class="feather-play me-1"></i> Start Campaign
                </button>
            </div>
        </div>
    </div>
</div>
