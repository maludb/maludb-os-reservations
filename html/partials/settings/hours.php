<?php
require_once '../../../helpers/auth.php';
require_once '../../../helpers/csrf.php';

requireAuth();
requireManager();

$restaurantId = currentRestaurantId();

$dayNames = ['Sunday', 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday'];

// Get all operating hours for this restaurant
$stmt = db()->prepare(
    "SELECT * FROM operating_hours WHERE restaurant_id = ? ORDER BY day_of_week ASC, open_time ASC"
);
$stmt->execute([$restaurantId]);
$allHours = $stmt->fetchAll();

// Group by day_of_week
$hoursByDay = [];
for ($d = 0; $d <= 6; $d++) {
    $hoursByDay[$d] = [];
}
foreach ($allHours as $h) {
    $hoursByDay[(int)$h['day_of_week']][] = $h;
}
?>
<div class="main-content" id="hours-main"
     hx-get="/partials/settings/hours.php"
     hx-trigger="refreshHoursList from:body"
     hx-target="#hours-main"
     hx-swap="outerHTML">
    <div class="row" id="hours-row">
        <div class="col-12" id="hours-col">

            <div class="card" id="hours-card">
                <div class="card-header d-flex align-items-center justify-content-between" id="hours-card-header">
                    <h5 class="card-title mb-0" id="hours-card-title">
                        <i class="feather-clock me-2"></i>Operating Hours
                    </h5>
                    <button class="btn btn-primary btn-sm" id="hours-add-btn"
                            hx-get="/partials/settings/hours-form.php"
                            hx-target="#hours-modal-container"
                            hx-swap="innerHTML">
                        <i class="feather-plus me-1"></i> Add Service Period
                    </button>
                </div>
                <div class="card-body" id="hours-card-body">

                    <div id="hours-messages"></div>

                    <div class="table-responsive" id="hours-table-wrapper">
                        <?php for ($day = 0; $day <= 6; $day++): ?>
                        <div class="mb-4" id="hours-day-<?php echo $day; ?>">
                            <h6 class="fw-bold border-bottom pb-2" id="hours-day-title-<?php echo $day; ?>">
                                <?php echo $dayNames[$day]; ?>
                                <?php if (empty($hoursByDay[$day])): ?>
                                <span class="badge bg-secondary ms-2">Closed</span>
                                <?php endif; ?>
                            </h6>

                            <?php if (!empty($hoursByDay[$day])): ?>
                            <table class="table table-sm align-middle mb-0" id="hours-table-day-<?php echo $day; ?>">
                                <thead>
                                    <tr>
                                        <th>Service</th>
                                        <th>Open</th>
                                        <th>Close</th>
                                        <th>First Seating</th>
                                        <th>Last Seating</th>
                                        <th>Status</th>
                                        <th class="text-end">Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($hoursByDay[$day] as $h): ?>
                                    <tr id="hours-row-<?php echo $h['id']; ?>">
                                        <td><strong><?php echo htmlspecialchars($h['service_name']); ?></strong></td>
                                        <td><?php echo date('g:ia', strtotime($h['open_time'])); ?></td>
                                        <td><?php echo date('g:ia', strtotime($h['close_time'])); ?></td>
                                        <td><?php echo date('g:ia', strtotime($h['first_seating'])); ?></td>
                                        <td><?php echo date('g:ia', strtotime($h['last_seating'])); ?></td>
                                        <td>
                                            <?php if ($h['is_active']): ?>
                                            <span class="badge bg-success" id="hours-status-<?php echo $h['id']; ?>">Active</span>
                                            <?php else: ?>
                                            <span class="badge bg-secondary" id="hours-status-<?php echo $h['id']; ?>">Inactive</span>
                                            <?php endif; ?>
                                        </td>
                                        <td class="text-end" id="hours-actions-<?php echo $h['id']; ?>">
                                            <button class="btn btn-outline-primary btn-sm me-1"
                                                    hx-get="/partials/settings/hours-form.php?hour_id=<?php echo $h['id']; ?>"
                                                    hx-target="#hours-modal-container"
                                                    hx-swap="innerHTML"
                                                    title="Edit">
                                                <i class="feather-edit-2"></i>
                                            </button>
                                            <button class="btn btn-outline-<?php echo $h['is_active'] ? 'warning' : 'success'; ?> btn-sm"
                                                    hx-post="/partials/settings/save-hours.php"
                                                    hx-vals='{"action": "toggle", "hour_id": <?php echo $h['id']; ?>, "csrf_token": "<?php echo htmlspecialchars(generate_csrf_token()); ?>"}'
                                                    hx-target="#hours-messages"
                                                    hx-swap="innerHTML"
                                                    title="<?php echo $h['is_active'] ? 'Deactivate' : 'Activate'; ?>">
                                                <i class="feather-<?php echo $h['is_active'] ? 'eye-off' : 'eye'; ?>"></i>
                                            </button>
                                            <button class="btn btn-outline-danger btn-sm"
                                                    hx-post="/partials/settings/save-hours.php"
                                                    hx-vals='{"action": "delete", "hour_id": <?php echo $h['id']; ?>, "csrf_token": "<?php echo htmlspecialchars(generate_csrf_token()); ?>"}'
                                                    hx-target="#hours-messages"
                                                    hx-swap="innerHTML"
                                                    hx-confirm="Delete this service period?"
                                                    title="Delete">
                                                <i class="feather-trash-2"></i>
                                            </button>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                            <?php endif; ?>
                        </div>
                        <?php endfor; ?>
                    </div>

                </div>
            </div>

        </div>
    </div>

    <div id="hours-modal-container"></div>
</div>
