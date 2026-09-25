<?php
require_once '../../../helpers/auth.php';
require_once '../../../helpers/csrf.php';

requireAuth();
requireManager();

$restaurantId = currentRestaurantId();

$stmt = db()->prepare(
    "SELECT * FROM special_dates WHERE restaurant_id = ? ORDER BY special_date ASC"
);
$stmt->execute([$restaurantId]);
$specialDates = $stmt->fetchAll();
?>
<div class="main-content" id="special-dates-main"
     hx-get="/partials/settings/special-dates.php"
     hx-trigger="refreshSpecialDates from:body"
     hx-target="#special-dates-main"
     hx-swap="outerHTML">
    <div class="row" id="special-dates-row">
        <div class="col-12" id="special-dates-col">

            <div class="card" id="special-dates-card">
                <div class="card-header d-flex align-items-center justify-content-between" id="special-dates-card-header">
                    <h5 class="card-title mb-0" id="special-dates-card-title">
                        <i class="feather-star me-2"></i>Special Dates
                    </h5>
                    <button class="btn btn-primary btn-sm" id="special-dates-add-btn"
                            hx-get="/partials/settings/special-date-form.php"
                            hx-target="#special-dates-modal-container"
                            hx-swap="innerHTML">
                        <i class="feather-plus me-1"></i> Add Special Date
                    </button>
                </div>
                <div class="card-body" id="special-dates-card-body">

                    <div id="special-dates-messages"></div>

                    <div class="table-responsive" id="special-dates-table-wrapper">
                        <table class="table table-hover align-middle" id="special-dates-table">
                            <thead id="special-dates-table-head">
                                <tr>
                                    <th>Date</th>
                                    <th>Label</th>
                                    <th>Type</th>
                                    <th>Closed?</th>
                                    <th>Custom Hours</th>
                                    <th>Max Covers</th>
                                    <th>Voice Agent</th>
                                    <th class="text-end">Actions</th>
                                </tr>
                            </thead>
                            <tbody id="special-dates-table-body">
                                <?php if (empty($specialDates)): ?>
                                <tr id="special-dates-empty-row">
                                    <td colspan="8" class="text-center text-muted py-4">No special dates configured.</td>
                                </tr>
                                <?php else: ?>
                                <?php foreach ($specialDates as $sd): ?>
                                <tr id="special-dates-row-<?php echo $sd['id']; ?>">
                                    <td><strong><?php echo date('M j, Y', strtotime($sd['special_date'])); ?></strong>
                                        <br><small class="text-muted"><?php echo date('l', strtotime($sd['special_date'])); ?></small>
                                    </td>
                                    <td><?php echo htmlspecialchars($sd['label']); ?></td>
                                    <td>
                                        <?php
                                        $typeBadge = match($sd['date_type']) {
                                            'holiday' => 'bg-danger',
                                            'blackout' => 'bg-dark',
                                            'event' => 'bg-info text-dark',
                                            'modified_hours' => 'bg-warning text-dark',
                                            default => 'bg-secondary'
                                        };
                                        ?>
                                        <span class="badge <?php echo $typeBadge; ?>"><?php echo ucfirst(str_replace('_', ' ', $sd['date_type'])); ?></span>
                                    </td>
                                    <td>
                                        <?php if ($sd['is_closed']): ?>
                                        <span class="badge bg-danger">Yes</span>
                                        <?php else: ?>
                                        <span class="badge bg-success">No</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php if ($sd['custom_open_time'] && $sd['custom_close_time']): ?>
                                        <?php echo date('g:ia', strtotime($sd['custom_open_time'])); ?> - <?php echo date('g:ia', strtotime($sd['custom_close_time'])); ?>
                                        <?php else: ?>
                                        <span class="text-muted">Default</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php echo $sd['max_covers'] ? (int)$sd['max_covers'] : '<span class="text-muted">Default</span>'; ?>
                                    </td>
                                    <td id="special-dates-agent-<?php echo $sd['id']; ?>">
                                        <?php echo !empty($sd['outbound_voice_agent']) ? htmlspecialchars($sd['outbound_voice_agent']) : '<span class="text-muted">—</span>'; ?>
                                    </td>
                                    <td class="text-end" id="special-dates-actions-<?php echo $sd['id']; ?>">
                                        <button class="btn btn-outline-success btn-sm me-1"
                                                hx-get="/partials/settings/special-date-reservations.php?date=<?php echo urlencode($sd['special_date']); ?>&agent_id=<?php echo urlencode($sd['outbound_voice_agent'] ?? ''); ?>"
                                                hx-target="#special-dates-modal-container"
                                                hx-swap="innerHTML"
                                                title="View Reservations">
                                            <i class="feather-calendar"></i>
                                        </button>
                                        <button class="btn btn-outline-primary btn-sm me-1"
                                                hx-get="/partials/settings/special-date-form.php?sd_id=<?php echo $sd['id']; ?>"
                                                hx-target="#special-dates-modal-container"
                                                hx-swap="innerHTML"
                                                title="Edit">
                                            <i class="feather-edit-2"></i>
                                        </button>
                                        <button class="btn btn-outline-danger btn-sm"
                                                hx-post="/partials/settings/save-special-date.php"
                                                hx-vals='{"action": "delete", "sd_id": <?php echo $sd['id']; ?>, "csrf_token": "<?php echo htmlspecialchars(generate_csrf_token()); ?>"}'
                                                hx-target="#special-dates-messages"
                                                hx-swap="innerHTML"
                                                hx-confirm="Delete this special date?"
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

        </div>
    </div>

    <div id="special-dates-modal-container"></div>
</div>
