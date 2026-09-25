<?php
/**
 * Special Date Reservations — Lists reservations for a specific date
 * with buttons to launch a confirmation voice agent call for each.
 */
require_once __DIR__ . '/../../../helpers/auth.php';
require_once __DIR__ . '/../../../helpers/csrf.php';

requireAuth();
requireManager();

$restaurantId = currentRestaurantId();
$pdo = db();

$date = trim($_GET['date'] ?? '');
$agentId = trim($_GET['agent_id'] ?? '') ?: 'agent_59677a1c2a26618caabd3fb02f';

if (!$date || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
    echo '<div class="alert alert-danger">Invalid date.</div>';
    exit;
}

// Get restaurant slug
$stmt = $pdo->prepare("SELECT slug FROM restaurants WHERE id = ?");
$stmt->execute([$restaurantId]);
$restaurantSlug = ($stmt->fetch())['slug'] ?? '';

// Get reservations for this date
$stmt = $pdo->prepare(
    "SELECT r.*, g.first_name, g.last_name, g.phone, g.email, t.table_name
     FROM reservations r
     JOIN guests g ON r.guest_id = g.id
     LEFT JOIN tables t ON r.table_id = t.id
     WHERE r.restaurant_id = ? AND r.reservation_date = ?
       AND r.status NOT IN ('cancelled')
     ORDER BY r.reservation_time ASC"
);
$stmt->execute([$restaurantId, $date]);
$reservations = $stmt->fetchAll();

$dateDisplay = date('l, M j, Y', strtotime($date));

$statusColors = [
    'pending' => 'warning',
    'confirmed' => 'primary',
    'seated' => 'success',
    'completed' => 'secondary',
    'no_show' => 'danger',
];
?>
<div class="modal fade show d-block" tabindex="-1" id="sd-res-modal" style="background-color: rgba(0,0,0,0.5);">
    <div class="modal-dialog modal-xl" id="sd-res-dialog">
        <div class="modal-content" id="sd-res-content">
            <div class="modal-header" id="sd-res-header">
                <h5 class="modal-title" id="sd-res-title">
                    <i class="feather-calendar me-2"></i>Reservations for <?php echo htmlspecialchars($dateDisplay); ?>
                </h5>
                <button type="button" class="btn-close" id="sd-res-close-btn"
                        onclick="document.getElementById('special-dates-modal-container').innerHTML=''"></button>
            </div>
            <div class="modal-body" id="sd-res-body">
                <?php if (empty($reservations)): ?>
                <div class="text-center py-4 text-muted" id="sd-res-empty">
                    <i class="feather-inbox" style="font-size:2rem;"></i>
                    <p class="mt-2 mb-0">No reservations found for this date.</p>
                </div>
                <?php else: ?>
                <div class="mb-3 d-flex justify-content-between align-items-center" id="sd-res-summary">
                    <span id="sd-res-count">
                        <strong><?php echo count($reservations); ?></strong> reservation<?php echo count($reservations) !== 1 ? 's' : ''; ?>
                        &middot;
                        <strong><?php echo array_sum(array_column($reservations, 'party_size')); ?></strong> total covers
                    </span>
                </div>
                <div class="table-responsive" id="sd-res-table-wrap">
                    <table class="table table-hover align-middle mb-0" id="sd-res-table">
                        <thead id="sd-res-thead">
                            <tr>
                                <th>Time</th>
                                <th>Guest</th>
                                <th>Phone</th>
                                <th>Party</th>
                                <th>Table</th>
                                <th>Status</th>
                                <th>Code</th>
                                <th class="text-end">Confirm Call</th>
                            </tr>
                        </thead>
                        <tbody id="sd-res-tbody">
                            <?php foreach ($reservations as $r):
                                $guestName = trim($r['first_name'] . ' ' . $r['last_name']);
                                $badgeColor = $statusColors[$r['status']] ?? 'secondary';
                            ?>
                            <tr id="sd-res-row-<?php echo $r['id']; ?>">
                                <td id="sd-res-time-<?php echo $r['id']; ?>">
                                    <strong><?php echo date('g:ia', strtotime($r['reservation_time'])); ?></strong>
                                </td>
                                <td id="sd-res-guest-<?php echo $r['id']; ?>">
                                    <?php echo htmlspecialchars($guestName); ?>
                                    <?php if ($r['email']): ?>
                                    <br><small class="text-muted"><?php echo htmlspecialchars($r['email']); ?></small>
                                    <?php endif; ?>
                                </td>
                                <td id="sd-res-phone-<?php echo $r['id']; ?>">
                                    <?php echo htmlspecialchars($r['phone'] ?? '—'); ?>
                                </td>
                                <td id="sd-res-party-<?php echo $r['id']; ?>">
                                    <?php echo (int)$r['party_size']; ?>
                                </td>
                                <td id="sd-res-table-<?php echo $r['id']; ?>">
                                    <?php echo $r['table_name'] ? htmlspecialchars($r['table_name']) : '<span class="text-muted">TBD</span>'; ?>
                                </td>
                                <td id="sd-res-status-<?php echo $r['id']; ?>">
                                    <span class="badge bg-<?php echo $badgeColor; ?>">
                                        <?php echo ucfirst(str_replace('_', ' ', $r['status'])); ?>
                                    </span>
                                </td>
                                <td id="sd-res-code-<?php echo $r['id']; ?>">
                                    <code><?php echo htmlspecialchars($r['confirmation_code']); ?></code>
                                </td>
                                <td class="text-end" id="sd-res-call-<?php echo $r['id']; ?>">
                                    <button class="btn btn-retell-call btn-sm"
                                            data-retell-call
                                            data-retell-agent-id="<?php echo htmlspecialchars($agentId); ?>"
                                            data-call-title="Confirmation Call"
                                            data-call-subtitle="<?php echo htmlspecialchars($guestName); ?>"
                                            data-guest-name="<?php echo htmlspecialchars($guestName); ?>"
                                            data-guest-phone="<?php echo htmlspecialchars($r['phone'] ?? ''); ?>"
                                            data-guest-email="<?php echo htmlspecialchars($r['email'] ?? ''); ?>"
                                            data-party-size="<?php echo (int)$r['party_size']; ?>"
                                            data-reservation-date="<?php echo htmlspecialchars($dateDisplay); ?>"
                                            data-reservation-time="<?php echo date('g:ia', strtotime($r['reservation_time'])); ?>"
                                            data-confirmation-code="<?php echo htmlspecialchars($r['confirmation_code']); ?>"
                                            data-reservation-status="<?php echo htmlspecialchars($r['status']); ?>"
                                            data-special-requests="<?php echo htmlspecialchars($r['special_requests'] ?? ''); ?>"
                                            data-restaurant-slug="<?php echo htmlspecialchars($restaurantSlug); ?>">
                                        <i class="feather-phone me-1"></i> Call
                                    </button>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <?php endif; ?>
            </div>
            <div class="modal-footer" id="sd-res-footer">
                <button type="button" class="btn btn-secondary" id="sd-res-done-btn"
                        onclick="document.getElementById('special-dates-modal-container').innerHTML=''">Close</button>
            </div>
        </div>
    </div>
</div>
