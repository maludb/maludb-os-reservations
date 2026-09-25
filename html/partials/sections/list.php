<?php
require_once '../../../helpers/auth.php';
require_once '../../../helpers/csrf.php';

requireAuth();
requireManager();

$restaurantId = currentRestaurantId();

$stmt = db()->prepare(
    "SELECT * FROM sections WHERE restaurant_id = ? ORDER BY display_order ASC, name ASC"
);
$stmt->execute([$restaurantId]);
$sections = $stmt->fetchAll();
?>
<div class="main-content" id="sections-main"
     hx-get="/partials/sections/list.php"
     hx-trigger="refreshSectionList from:body"
     hx-target="#sections-main"
     hx-swap="outerHTML">
    <div class="row" id="sections-row">
        <div class="col-12" id="sections-col">

            <div class="card" id="sections-card">
                <div class="card-header d-flex align-items-center justify-content-between" id="sections-card-header">
                    <h5 class="card-title mb-0" id="sections-card-title">
                        <i class="feather-layers me-2"></i>Sections
                    </h5>
                    <button class="btn btn-primary btn-sm" id="sections-add-btn"
                            hx-get="/partials/sections/form.php"
                            hx-target="#sections-modal-container"
                            hx-swap="innerHTML">
                        <i class="feather-plus me-1"></i> Add Section
                    </button>
                </div>
                <div class="card-body" id="sections-card-body">

                    <div id="sections-messages"></div>

                    <div class="table-responsive" id="sections-table-wrapper">
                        <table class="table table-hover align-middle" id="sections-table">
                            <thead id="sections-table-head">
                                <tr>
                                    <th>Order</th>
                                    <th>Name</th>
                                    <th>Description</th>
                                    <th>Hours Override</th>
                                    <th>Status</th>
                                    <th class="text-end">Actions</th>
                                </tr>
                            </thead>
                            <tbody id="sections-table-body">
                                <?php if (empty($sections)): ?>
                                <tr id="sections-empty-row">
                                    <td colspan="6" class="text-center text-muted py-4">No sections found. Add your first section to get started.</td>
                                </tr>
                                <?php else: ?>
                                <?php foreach ($sections as $s): ?>
                                <tr id="sections-row-<?php echo $s['id']; ?>">
                                    <td><?php echo (int)$s['display_order']; ?></td>
                                    <td><strong><?php echo htmlspecialchars($s['name']); ?></strong></td>
                                    <td><?php echo htmlspecialchars($s['description'] ?? ''); ?></td>
                                    <td>
                                        <?php if ($s['open_time'] && $s['close_time']): ?>
                                        <span class="text-muted">
                                            <?php echo date('g:ia', strtotime($s['open_time'])); ?> - <?php echo date('g:ia', strtotime($s['close_time'])); ?>
                                        </span>
                                        <?php else: ?>
                                        <span class="text-muted">Default</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php if ($s['is_active']): ?>
                                        <span class="badge bg-success" id="sections-status-<?php echo $s['id']; ?>">Active</span>
                                        <?php else: ?>
                                        <span class="badge bg-secondary" id="sections-status-<?php echo $s['id']; ?>">Inactive</span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="text-end" id="sections-actions-<?php echo $s['id']; ?>">
                                        <button class="btn btn-outline-primary btn-sm me-1"
                                                hx-get="/partials/sections/form.php?section_id=<?php echo $s['id']; ?>"
                                                hx-target="#sections-modal-container"
                                                hx-swap="innerHTML"
                                                title="Edit">
                                            <i class="feather-edit-2"></i>
                                        </button>
                                        <button class="btn btn-outline-<?php echo $s['is_active'] ? 'warning' : 'success'; ?> btn-sm"
                                                hx-post="/partials/sections/toggle.php"
                                                hx-vals='{"section_id": <?php echo $s['id']; ?>, "csrf_token": "<?php echo htmlspecialchars(generate_csrf_token()); ?>"}'
                                                hx-target="#sections-messages"
                                                hx-swap="innerHTML"
                                                hx-confirm="Are you sure you want to <?php echo $s['is_active'] ? 'deactivate' : 'activate'; ?> this section?"
                                                title="<?php echo $s['is_active'] ? 'Deactivate' : 'Activate'; ?>">
                                            <i class="feather-<?php echo $s['is_active'] ? 'eye-off' : 'eye'; ?>"></i>
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

    <div id="sections-modal-container"></div>
</div>
