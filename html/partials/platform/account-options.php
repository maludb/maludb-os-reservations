<?php
require_once '../../../helpers/auth.php';
require_once '../../../helpers/csrf.php';

requireSuperAdmin();

$pdo = db();

// Get restaurant filter
$filterRestaurant = (int)($_GET['restaurant_id'] ?? 0);

$restaurants = $pdo->query("SELECT id, name FROM restaurants ORDER BY name")->fetchAll();

// Build query
$sql = "SELECT ao.*, r.name as restaurant_name
        FROM account_options ao
        JOIN restaurants r ON r.id = ao.restaurant_id";
$params = [];
if ($filterRestaurant > 0) {
    $sql .= " WHERE ao.restaurant_id = ?";
    $params[] = $filterRestaurant;
}
$sql .= " ORDER BY r.name, ao.option_key";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$options = $stmt->fetchAll();
?>

<div class="main-content" id="platform-account-options-page">
    <div class="row" id="platform-account-options-header">
        <div class="col-12 d-flex justify-content-between align-items-center mb-4">
            <h4 class="fw-bold mb-0" id="platform-account-options-title">
                <i class="feather-toggle-right me-2"></i>Account Options
            </h4>
            <button class="btn btn-primary"
                    hx-get="/partials/platform/account-option-form.php"
                    hx-target="#modal-container"
                    id="platform-add-option-btn">
                <i class="feather-plus me-1"></i> Add Option
            </button>
        </div>
    </div>

    <!-- Filter -->
    <div class="row mb-3" id="platform-options-filter-row">
        <div class="col-md-4" id="platform-options-filter-wrap">
            <select class="form-select" id="platform-options-filter-restaurant"
                    hx-get="/partials/platform/account-options.php"
                    hx-target="#page-content"
                    name="restaurant_id">
                <option value="0">All Restaurants</option>
                <?php foreach ($restaurants as $r): ?>
                <option value="<?php echo (int)$r['id']; ?>" <?php echo $filterRestaurant == $r['id'] ? 'selected' : ''; ?>>
                    <?php echo htmlspecialchars($r['name']); ?>
                </option>
                <?php endforeach; ?>
            </select>
        </div>
    </div>

    <div class="row" id="platform-account-options-content">
        <div class="col-12">
            <div class="card border-0 shadow-sm" id="platform-account-options-card">
                <div class="card-body p-0" id="platform-account-options-card-body">
                    <?php if (empty($options)): ?>
                    <div class="text-center text-muted py-5" id="platform-no-options">
                        <i class="feather-toggle-right fs-1 mb-3 d-block"></i>
                        <p>No account options found.</p>
                    </div>
                    <?php else: ?>
                    <div class="table-responsive" id="platform-options-table-wrap">
                        <table class="table table-hover mb-0" id="platform-options-table">
                            <thead class="bg-light" id="platform-options-thead">
                                <tr>
                                    <th>Restaurant</th>
                                    <th>Option Key</th>
                                    <th>Value</th>
                                    <th>Enabled</th>
                                    <th>Notes</th>
                                    <th class="text-end">Actions</th>
                                </tr>
                            </thead>
                            <tbody id="platform-options-tbody">
                                <?php foreach ($options as $o): ?>
                                <tr id="platform-option-row-<?php echo (int)$o['id']; ?>">
                                    <td><?php echo htmlspecialchars($o['restaurant_name']); ?></td>
                                    <td><code><?php echo htmlspecialchars($o['option_key']); ?></code></td>
                                    <td><?php echo htmlspecialchars($o['option_value']); ?></td>
                                    <td>
                                        <?php if ($o['enabled']): ?>
                                        <span class="badge bg-success">On</span>
                                        <?php else: ?>
                                        <span class="badge bg-secondary">Off</span>
                                        <?php endif; ?>
                                    </td>
                                    <td><small class="text-muted"><?php echo htmlspecialchars($o['notes'] ?? '-'); ?></small></td>
                                    <td class="text-end">
                                        <button class="btn btn-sm btn-outline-primary me-1"
                                                hx-get="/partials/platform/account-option-form.php?id=<?php echo (int)$o['id']; ?>"
                                                hx-target="#modal-container"
                                                title="Edit"
                                                id="platform-edit-option-btn-<?php echo (int)$o['id']; ?>">
                                            <i class="feather-edit-2"></i>
                                        </button>
                                        <button class="btn btn-sm btn-outline-<?php echo $o['enabled'] ? 'warning' : 'success'; ?>"
                                                hx-post="/partials/platform/toggle-account-option.php"
                                                hx-vals='{"option_id": <?php echo (int)$o['id']; ?>, "csrf_token": "<?php echo generate_csrf_token(); ?>"}'
                                                hx-target="#page-content"
                                                title="<?php echo $o['enabled'] ? 'Disable' : 'Enable'; ?>"
                                                id="platform-toggle-option-btn-<?php echo (int)$o['id']; ?>">
                                            <i class="feather-<?php echo $o['enabled'] ? 'toggle-right' : 'toggle-left'; ?>"></i>
                                        </button>
                                        <button class="btn btn-sm btn-outline-danger"
                                                hx-post="/partials/platform/delete-account-option.php"
                                                hx-vals='{"option_id": <?php echo (int)$o['id']; ?>, "csrf_token": "<?php echo generate_csrf_token(); ?>"}'
                                                hx-target="#page-content"
                                                hx-confirm="Delete this option?"
                                                title="Delete"
                                                id="platform-delete-option-btn-<?php echo (int)$o['id']; ?>">
                                            <i class="feather-trash-2"></i>
                                        </button>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>
