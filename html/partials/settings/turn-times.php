<?php
require_once '../../../helpers/auth.php';
require_once '../../../helpers/csrf.php';
require_once '../../../helpers/meal-status.php';

requireAuth();
requireManager();

$restaurantId = currentRestaurantId();

$stmt = db()->prepare(
    "SELECT * FROM turn_times WHERE restaurant_id = ? ORDER BY min_party_size ASC, service_period ASC"
);
$stmt->execute([$restaurantId]);
$turnTimes = $stmt->fetchAll();

// Load current meal progress percentages
$mealPcts = getMealPercentages($restaurantId);
$mealMeta = getMealStatusMeta();
?>
<div class="main-content" id="turn-times-main"
     hx-get="/partials/settings/turn-times.php"
     hx-trigger="refreshTurnTimes from:body"
     hx-target="#turn-times-main"
     hx-swap="outerHTML">
    <div class="row" id="turn-times-row">
        <div class="col-xxl-8 col-xl-10 col-12" id="turn-times-col">

            <div class="card" id="turn-times-card">
                <div class="card-header d-flex align-items-center justify-content-between" id="turn-times-card-header">
                    <h5 class="card-title mb-0" id="turn-times-card-title">
                        <i class="feather-watch me-2"></i>Turn Times
                    </h5>
                    <button class="btn btn-primary btn-sm" id="turn-times-add-btn"
                            hx-get="/partials/settings/turn-time-form.php"
                            hx-target="#turn-times-modal-container"
                            hx-swap="innerHTML">
                        <i class="feather-plus me-1"></i> Add Turn Time
                    </button>
                </div>
                <div class="card-body" id="turn-times-card-body">

                    <div id="turn-times-messages"></div>

                    <p class="text-muted mb-3" id="turn-times-help">Turn times define how long a party occupies a table. The buffer is cleanup time between seatings.</p>

                    <div class="table-responsive" id="turn-times-table-wrapper">
                        <table class="table table-hover align-middle" id="turn-times-table">
                            <thead id="turn-times-table-head">
                                <tr>
                                    <th>Party Size</th>
                                    <th>Service Period</th>
                                    <th>Duration</th>
                                    <th>Buffer</th>
                                    <th>Total</th>
                                    <th class="text-end">Actions</th>
                                </tr>
                            </thead>
                            <tbody id="turn-times-table-body">
                                <?php if (empty($turnTimes)): ?>
                                <tr id="turn-times-empty-row">
                                    <td colspan="6" class="text-center text-muted py-4">No turn times configured.</td>
                                </tr>
                                <?php else: ?>
                                <?php foreach ($turnTimes as $tt): ?>
                                <tr id="turn-times-row-<?php echo $tt['id']; ?>">
                                    <td><strong><?php echo (int)$tt['min_party_size']; ?>–<?php echo (int)$tt['max_party_size']; ?></strong> guests</td>
                                    <td>
                                        <?php if ($tt['service_period'] === 'all'): ?>
                                        <span class="badge bg-primary">All</span>
                                        <?php else: ?>
                                        <span class="badge bg-info text-dark"><?php echo htmlspecialchars($tt['service_period']); ?></span>
                                        <?php endif; ?>
                                    </td>
                                    <td><?php echo (int)$tt['duration_minutes']; ?> min</td>
                                    <td><?php echo (int)$tt['buffer_minutes']; ?> min</td>
                                    <td class="text-muted"><?php echo (int)$tt['duration_minutes'] + (int)$tt['buffer_minutes']; ?> min</td>
                                    <td class="text-end" id="turn-times-actions-<?php echo $tt['id']; ?>">
                                        <button class="btn btn-outline-primary btn-sm me-1"
                                                hx-get="/partials/settings/turn-time-form.php?tt_id=<?php echo $tt['id']; ?>"
                                                hx-target="#turn-times-modal-container"
                                                hx-swap="innerHTML"
                                                title="Edit">
                                            <i class="feather-edit-2"></i>
                                        </button>
                                        <button class="btn btn-outline-danger btn-sm"
                                                hx-post="/partials/settings/save-turn-time.php"
                                                hx-vals='{"action": "delete", "tt_id": <?php echo $tt['id']; ?>, "csrf_token": "<?php echo htmlspecialchars(generate_csrf_token()); ?>"}'
                                                hx-target="#turn-times-messages"
                                                hx-swap="innerHTML"
                                                hx-confirm="Delete this turn time rule?"
                                                title="Delete">
                                            <i class="feather-trash-2"></i>
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

            <!-- Meal Progress Estimates -->
            <div class="card mt-3" id="meal-pct-card">
                <div class="card-header" id="meal-pct-card-header">
                    <h5 class="card-title mb-0" id="meal-pct-card-title">
                        <i class="feather-bar-chart-2 me-2"></i>Meal Progress Estimates
                    </h5>
                </div>
                <div class="card-body" id="meal-pct-card-body">

                    <div id="meal-pct-messages"></div>

                    <p class="text-muted mb-3" id="meal-pct-help">
                        Set the estimated percentage of the meal completed at each status.
                        These are used to predict when a table will become available on the floor plan.
                    </p>

                    <form id="meal-pct-form"
                          hx-post="/partials/settings/save-meal-percentages.php"
                          hx-target="#meal-pct-messages"
                          hx-swap="innerHTML">
                        <?php echo csrf_field(); ?>

                        <div class="row g-3 mb-3" id="meal-pct-inputs">
                            <?php foreach ($mealMeta as $key => $meta): ?>
                            <div class="col-6 col-md" id="meal-pct-group-<?php echo $key; ?>">
                                <label for="meal-pct-<?php echo $key; ?>" class="form-label small fw-bold">
                                    <span class="badge bg-<?php echo $meta['color']; ?> me-1">&nbsp;</span>
                                    <?php echo $meta['label']; ?>
                                </label>
                                <div class="input-group input-group-sm" id="meal-pct-ig-<?php echo $key; ?>">
                                    <input type="number" class="form-control" id="meal-pct-<?php echo $key; ?>"
                                           name="pct_<?php echo $key; ?>"
                                           value="<?php echo (int)$mealPcts[$key]; ?>"
                                           min="0" max="100" required>
                                    <span class="input-group-text">%</span>
                                </div>
                            </div>
                            <?php endforeach; ?>
                        </div>

                        <button type="submit" class="btn btn-primary btn-sm" id="meal-pct-save">
                            <i class="feather-save me-1"></i> Save Estimates
                            <span class="htmx-indicator spinner-border spinner-border-sm ms-2"></span>
                        </button>
                    </form>

                </div>
            </div>

        </div>
    </div>

    <div id="turn-times-modal-container"></div>
</div>
