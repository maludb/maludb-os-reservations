<?php
/**
 * Billing — Prepay Transaction History
 *
 * Shows all prepay balance transactions (deposits, applications, refunds).
 */
require_once __DIR__ . '/../../../helpers/auth.php';
require_once __DIR__ . '/../../../helpers/db.php';

requireAuth();
$restaurantId = currentRestaurantId();
$userId = currentUserId();
$pdo = db();

// Verify billing access
$accessStmt = $pdo->prepare("SELECT billing_user, prepay_balance FROM restaurants WHERE id = ?");
$accessStmt->execute([$restaurantId]);
$restaurant = $accessStmt->fetch();

$isBillingUser = ((int)($restaurant['billing_user'] ?? 0) === $userId);

if (!$isBillingUser && !isSuperAdmin()) {
    echo '<div class="alert alert-danger m-4">You do not have access to billing.</div>';
    exit;
}

$currentBalance = (float)($restaurant['prepay_balance'] ?? 0);

// Get transactions
$stmt = $pdo->prepare(
    "SELECT pt.*, u.first_name, u.last_name
     FROM prepay_transactions pt
     LEFT JOIN users u ON pt.created_by = u.id
     WHERE pt.restaurant_id = ?
     ORDER BY pt.created_at DESC"
);
$stmt->execute([$restaurantId]);
$transactions = $stmt->fetchAll();

$typeLabel = [
    'deposit'         => 'Deposit',
    'invoice_applied' => 'Applied to Invoice',
    'refund'          => 'Refund',
    'adjustment'      => 'Adjustment',
];
$typeBadge = [
    'deposit'         => 'success',
    'invoice_applied' => 'warning',
    'refund'          => 'info',
    'adjustment'      => 'secondary',
];
?>

<div class="p-4" id="billing-prepay-history-main">
    <div class="d-flex justify-content-between align-items-center mb-4" id="billing-prepay-history-header">
        <div id="billing-prepay-history-title-wrap">
            <h4 class="mb-0" id="billing-prepay-history-title">Prepay Balance History</h4>
            <small class="text-muted" id="billing-prepay-history-subtitle">
                Current Balance: <strong>$<?php echo number_format($currentBalance, 2); ?></strong>
            </small>
        </div>
        <div class="d-flex gap-2" id="billing-prepay-history-actions">
            <a href="#" class="btn btn-outline-secondary" id="billing-prepay-history-back-btn"
               hx-get="/partials/billing/invoices.php" hx-target="#page-content">
                <i class="feather-arrow-left me-1"></i> Back to Billing
            </a>
            <a href="#" class="btn btn-success" id="billing-prepay-history-add-btn"
               hx-get="/partials/billing/add-prepay.php" hx-target="#page-content">
                <i class="feather-plus-circle me-1"></i> Add Funds
            </a>
        </div>
    </div>

    <div class="card" id="billing-prepay-history-table-card">
        <div class="card-body p-0" id="billing-prepay-history-table-body">
            <?php if (empty($transactions)): ?>
                <div class="text-center text-muted py-5" id="billing-prepay-history-empty">
                    <i class="feather-inbox" style="font-size:48px;"></i>
                    <p class="mt-2 mb-0">No transactions yet.</p>
                </div>
            <?php else: ?>
                <div class="table-responsive" id="billing-prepay-history-table-wrap">
                    <table class="table table-hover mb-0" id="billing-prepay-history-table">
                        <thead class="table-light" id="billing-prepay-history-thead">
                            <tr>
                                <th id="billing-prepay-th-date">Date</th>
                                <th id="billing-prepay-th-type">Type</th>
                                <th id="billing-prepay-th-amount">Amount</th>
                                <th id="billing-prepay-th-balance">Balance After</th>
                                <th id="billing-prepay-th-note">Reference</th>
                                <th id="billing-prepay-th-by">By</th>
                            </tr>
                        </thead>
                        <tbody id="billing-prepay-history-tbody">
                            <?php foreach ($transactions as $tx): ?>
                            <tr id="billing-prepay-row-<?php echo (int)$tx['id']; ?>">
                                <td id="billing-prepay-date-<?php echo (int)$tx['id']; ?>">
                                    <?php echo date('M j, Y g:ia', strtotime($tx['created_at'])); ?>
                                </td>
                                <td id="billing-prepay-type-<?php echo (int)$tx['id']; ?>">
                                    <span class="badge bg-<?php echo $typeBadge[$tx['type']] ?? 'secondary'; ?>">
                                        <?php echo $typeLabel[$tx['type']] ?? $tx['type']; ?>
                                    </span>
                                </td>
                                <td id="billing-prepay-amount-<?php echo (int)$tx['id']; ?>" class="fw-semibold <?php echo (float)$tx['amount'] >= 0 ? 'text-success' : 'text-danger'; ?>">
                                    <?php echo (float)$tx['amount'] >= 0 ? '+' : ''; ?>$<?php echo number_format((float)$tx['amount'], 2); ?>
                                </td>
                                <td id="billing-prepay-balance-<?php echo (int)$tx['id']; ?>">
                                    $<?php echo number_format((float)$tx['balance_after'], 2); ?>
                                </td>
                                <td id="billing-prepay-note-<?php echo (int)$tx['id']; ?>">
                                    <?php echo htmlspecialchars($tx['reference_note'] ?? '—'); ?>
                                </td>
                                <td id="billing-prepay-by-<?php echo (int)$tx['id']; ?>">
                                    <?php if (!empty($tx['first_name'])): ?>
                                        <?php echo htmlspecialchars($tx['first_name'] . ' ' . $tx['last_name']); ?>
                                    <?php else: ?>
                                        <span class="text-muted">System</span>
                                    <?php endif; ?>
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
