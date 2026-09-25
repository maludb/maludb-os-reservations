<?php
require_once '../../../helpers/auth.php';
require_once '../../../helpers/csrf.php';

requireAuth();
requireManager();

$restaurantId = currentRestaurantId();

// Get sections for filter dropdown
$stmt = db()->prepare("SELECT id, name FROM sections WHERE restaurant_id = ? AND is_active = 1 ORDER BY display_order ASC");
$stmt->execute([$restaurantId]);
$sections = $stmt->fetchAll();

// Build query with optional section filter
$filterSection = isset($_GET['section_id']) ? (int)$_GET['section_id'] : 0;

$sql = "SELECT t.*, s.name AS section_name
        FROM tables t
        JOIN sections s ON t.section_id = s.id
        WHERE t.restaurant_id = ?";
$params = [$restaurantId];

if ($filterSection > 0) {
    $sql .= " AND t.section_id = ?";
    $params[] = $filterSection;
}

$sql .= " ORDER BY s.display_order ASC, t.sort_order ASC, t.table_name ASC";

$stmt = db()->prepare($sql);
$stmt->execute($params);
$tables = $stmt->fetchAll();
?>
<div class="main-content" id="tables-main"
     hx-get="/partials/tables/list.php"
     hx-trigger="refreshTableList from:body"
     hx-target="#tables-main"
     hx-swap="outerHTML">
    <div class="row" id="tables-row">
        <div class="col-12" id="tables-col">

            <div class="card" id="tables-card">
                <div class="card-header d-flex align-items-center justify-content-between" id="tables-card-header">
                    <h5 class="card-title mb-0" id="tables-card-title">
                        <i class="feather-layout me-2"></i>Table Setup
                    </h5>
                    <button class="btn btn-primary btn-sm" id="tables-add-btn"
                            hx-get="/partials/tables/form.php"
                            hx-target="#tables-modal-container"
                            hx-swap="innerHTML">
                        <i class="feather-plus me-1"></i> Add Table
                    </button>
                </div>
                <div class="card-body" id="tables-card-body">

                    <div id="tables-messages"></div>

                    <!-- Section filter -->
                    <div class="mb-3" id="tables-filter-group">
                        <select class="form-select form-select-sm d-inline-block w-auto" id="tables-filter-section"
                                hx-get="/partials/tables/list.php"
                                hx-target="#tables-main"
                                hx-swap="outerHTML"
                                name="section_id">
                            <option value="0">All Sections</option>
                            <?php foreach ($sections as $sec): ?>
                            <option value="<?php echo $sec['id']; ?>" <?php echo $filterSection === (int)$sec['id'] ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($sec['name']); ?>
                            </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="table-responsive" id="tables-table-wrapper">
                        <table class="table table-hover align-middle" id="tables-table">
                            <thead id="tables-table-head">
                                <tr>
                                    <th>Name</th>
                                    <th>Section</th>
                                    <th>Seats</th>
                                    <th>Status</th>
                                    <th>Order</th>
                                    <th>Notes</th>
                                    <th>Active</th>
                                    <th class="text-end">Actions</th>
                                </tr>
                            </thead>
                            <tbody id="tables-table-body">
                                <?php if (empty($tables)): ?>
                                <tr id="tables-empty-row">
                                    <td colspan="8" class="text-center text-muted py-4">No tables found. Add your first table to get started.</td>
                                </tr>
                                <?php else: ?>
                                <?php foreach ($tables as $t): ?>
                                <tr id="tables-row-<?php echo $t['id']; ?>">
                                    <td><strong><?php echo htmlspecialchars($t['table_name']); ?></strong></td>
                                    <td><?php echo htmlspecialchars($t['section_name']); ?></td>
                                    <td><?php echo (int)$t['min_seats']; ?>-<?php echo (int)$t['max_seats']; ?></td>
                                    <td>
                                        <?php
                                        $statusBadge = match($t['status']) {
                                            'available' => 'bg-success',
                                            'occupied' => 'bg-warning text-dark',
                                            'reserved' => 'bg-info text-dark',
                                            'blocked' => 'bg-secondary',
                                            default => 'bg-secondary'
                                        };
                                        ?>
                                        <span class="badge <?php echo $statusBadge; ?>"><?php echo ucfirst($t['status']); ?></span>
                                    </td>
                                    <td><?php echo (int)$t['sort_order']; ?></td>
                                    <td class="text-muted"><?php echo htmlspecialchars($t['notes'] ?? ''); ?></td>
                                    <td>
                                        <?php if ($t['is_active']): ?>
                                        <span class="badge bg-success" id="tables-status-<?php echo $t['id']; ?>">Active</span>
                                        <?php else: ?>
                                        <span class="badge bg-secondary" id="tables-status-<?php echo $t['id']; ?>">Inactive</span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="text-end" id="tables-actions-<?php echo $t['id']; ?>">
                                        <button class="btn btn-outline-primary btn-sm me-1"
                                                hx-get="/partials/tables/form.php?table_id=<?php echo $t['id']; ?>"
                                                hx-target="#tables-modal-container"
                                                hx-swap="innerHTML"
                                                title="Edit">
                                            <i class="feather-edit-2"></i>
                                        </button>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>

                </div>
            </div>

        </div>
    </div>

    <div id="tables-modal-container"></div>
</div>
