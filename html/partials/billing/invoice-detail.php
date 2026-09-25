<?php
/**
 * Billing — Invoice Detail
 *
 * Shows a single invoice with line item, payment history, and pay option.
 */
require_once __DIR__ . '/../../../helpers/auth.php';
require_once __DIR__ . '/../../../helpers/db.php';

requireAuth();
$restaurantId = currentRestaurantId();
$userId = currentUserId();
$pdo = db();

$invoiceId = (int)($_GET['id'] ?? 0);
if ($invoiceId <= 0) {
    echo '<div class="alert alert-danger m-4">Invalid invoice.</div>';
    exit;
}

// Get invoice
$stmt = $pdo->prepare("SELECT * FROM invoices WHERE id = ? AND restaurant_id = ?");
$stmt->execute([$invoiceId, $restaurantId]);
$invoice = $stmt->fetch();

if (!$invoice) {
    echo '<div class="alert alert-danger m-4">Invoice not found.</div>';
    exit;
}

// Verify billing access
$accessStmt = $pdo->prepare("SELECT billing_user FROM restaurants WHERE id = ?");
$accessStmt->execute([$restaurantId]);
$restaurant = $accessStmt->fetch();
$isBillingUser = ((int)($restaurant['billing_user'] ?? 0) === $userId);

if (!$isBillingUser && !isSuperAdmin()) {
    echo '<div class="alert alert-danger m-4">You do not have access to billing.</div>';
    exit;
}

// Get payments for this invoice
$payStmt = $pdo->prepare(
    "SELECT ip.*, u.first_name, u.last_name
     FROM invoice_payments ip
     LEFT JOIN users u ON ip.created_by = u.id
     WHERE ip.invoice_id = ?
     ORDER BY ip.created_at DESC"
);
$payStmt->execute([$invoiceId]);
$payments = $payStmt->fetchAll();

$totalPaid = 0;
foreach ($payments as $p) {
    $totalPaid += (float)$p['amount'];
}
$remaining = max(0, (float)$invoice['amount'] - $totalPaid);

$statusBadge = [
    'unpaid'  => 'warning',
    'paid'    => 'success',
    'overdue' => 'danger',
    'void'    => 'secondary',
];

$methodLabel = [
    'prepay' => 'Prepay Balance',
    'direct' => 'Direct Payment',
    'manual' => 'Manual Adjustment',
];
?>

<div class="p-4" id="billing-detail-main">
    <div class="d-flex justify-content-between align-items-center mb-4" id="billing-detail-header">
        <div id="billing-detail-title-wrap">
            <h4 class="mb-0" id="billing-detail-title">
                Invoice <?php echo htmlspecialchars($invoice['invoice_number']); ?>
            </h4>
            <small class="text-muted" id="billing-detail-subtitle">
                Created <?php echo date('M j, Y', strtotime($invoice['created_at'])); ?>
            </small>
        </div>
        <div class="d-flex gap-2" id="billing-detail-actions">
            <a href="#" class="btn btn-outline-secondary" id="billing-detail-back-btn"
               hx-get="/partials/billing/invoices.php" hx-target="#page-content">
                <i class="feather-arrow-left me-1"></i> Back to Invoices
            </a>
            <?php if (in_array($invoice['status'], ['unpaid', 'overdue'])): ?>
            <a href="#" class="btn btn-success" id="billing-detail-pay-btn"
               hx-get="/partials/billing/pay-invoice.php?id=<?php echo $invoiceId; ?>"
               hx-target="#page-content">
                <i class="feather-credit-card me-1"></i> Pay Invoice
            </a>
            <?php endif; ?>
        </div>
    </div>

    <div class="row g-4" id="billing-detail-content-row">
        <!-- Invoice Info -->
        <div class="col-lg-8" id="billing-detail-info-col">
            <div class="card" id="billing-detail-info-card">
                <div class="card-header d-flex justify-content-between align-items-center" id="billing-detail-info-header">
                    <h6 class="mb-0" id="billing-detail-info-label">Invoice Details</h6>
                    <span class="badge bg-<?php echo $statusBadge[$invoice['status']] ?? 'secondary'; ?> fs-6" id="billing-detail-status-badge">
                        <?php echo ucfirst($invoice['status']); ?>
                    </span>
                </div>
                <div class="card-body" id="billing-detail-info-body">
                    <div class="table-responsive" id="billing-detail-info-table-wrap">
                        <table class="table mb-0" id="billing-detail-info-table">
                            <tbody>
                                <tr id="billing-detail-row-period">
                                    <td class="text-muted fw-semibold" style="width:180px;">Billing Period</td>
                                    <td><?php echo date('M j, Y', strtotime($invoice['period_start'])); ?> – <?php echo date('M j, Y', strtotime($invoice['period_end'])); ?></td>
                                </tr>
                                <tr id="billing-detail-row-due">
                                    <td class="text-muted fw-semibold">Due Date</td>
                                    <td>
                                        <?php echo date('M j, Y', strtotime($invoice['due_date'])); ?>
                                        <?php if ($invoice['status'] === 'overdue'): ?>
                                            <span class="text-danger ms-2"><i class="feather-alert-triangle"></i> Past due</span>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                                <?php if ($invoice['paid_at']): ?>
                                <tr id="billing-detail-row-paid-at">
                                    <td class="text-muted fw-semibold">Paid On</td>
                                    <td><?php echo date('M j, Y g:ia', strtotime($invoice['paid_at'])); ?></td>
                                </tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>

                    <hr id="billing-detail-line-divider">

                    <!-- Line Item -->
                    <div class="table-responsive" id="billing-detail-line-table-wrap">
                        <table class="table mb-0" id="billing-detail-line-table">
                            <thead class="table-light" id="billing-detail-line-thead">
                                <tr>
                                    <th id="billing-detail-line-th-desc">Description</th>
                                    <th class="text-end" id="billing-detail-line-th-amount">Amount</th>
                                </tr>
                            </thead>
                            <tbody id="billing-detail-line-tbody">
                                <tr id="billing-detail-line-item">
                                    <td><?php echo htmlspecialchars($invoice['line_description']); ?></td>
                                    <td class="text-end fw-bold">$<?php echo number_format((float)$invoice['amount'], 2); ?></td>
                                </tr>
                            </tbody>
                            <tfoot id="billing-detail-line-tfoot">
                                <tr id="billing-detail-line-total">
                                    <td class="fw-bold">Total</td>
                                    <td class="text-end fw-bold">$<?php echo number_format((float)$invoice['amount'], 2); ?></td>
                                </tr>
                                <?php if ($totalPaid > 0): ?>
                                <tr id="billing-detail-line-paid">
                                    <td class="text-success">Paid</td>
                                    <td class="text-end text-success">-$<?php echo number_format($totalPaid, 2); ?></td>
                                </tr>
                                <tr id="billing-detail-line-remaining">
                                    <td class="fw-bold">Remaining</td>
                                    <td class="text-end fw-bold <?php echo $remaining > 0 ? 'text-danger' : 'text-success'; ?>">
                                        $<?php echo number_format($remaining, 2); ?>
                                    </td>
                                </tr>
                                <?php endif; ?>
                            </tfoot>
                        </table>
                    </div>
                </div>
            </div>
        </div>

        <!-- Sidebar -->
        <div class="col-lg-4" id="billing-detail-sidebar-col">
            <!-- Payment History -->
            <div class="card" id="billing-detail-payments-card">
                <div class="card-header" id="billing-detail-payments-header">
                    <h6 class="mb-0" id="billing-detail-payments-label">Payment History</h6>
                </div>
                <div class="card-body p-0" id="billing-detail-payments-body">
                    <?php if (empty($payments)): ?>
                        <div class="text-center text-muted py-4" id="billing-detail-payments-empty">
                            No payments recorded.
                        </div>
                    <?php else: ?>
                        <ul class="list-group list-group-flush" id="billing-detail-payments-list">
                            <?php foreach ($payments as $pay): ?>
                            <li class="list-group-item" id="billing-detail-payment-<?php echo (int)$pay['id']; ?>">
                                <div class="d-flex justify-content-between" id="billing-detail-payment-row-<?php echo (int)$pay['id']; ?>">
                                    <div id="billing-detail-payment-info-<?php echo (int)$pay['id']; ?>">
                                        <div class="fw-semibold" id="billing-detail-payment-amount-<?php echo (int)$pay['id']; ?>">$<?php echo number_format((float)$pay['amount'], 2); ?></div>
                                        <small class="text-muted" id="billing-detail-payment-method-<?php echo (int)$pay['id']; ?>">
                                            <?php echo $methodLabel[$pay['payment_method']] ?? $pay['payment_method']; ?>
                                        </small>
                                    </div>
                                    <div class="text-end" id="billing-detail-payment-meta-<?php echo (int)$pay['id']; ?>">
                                        <small class="text-muted" id="billing-detail-payment-date-<?php echo (int)$pay['id']; ?>">
                                            <?php echo date('M j, Y', strtotime($pay['created_at'])); ?>
                                        </small>
                                        <?php if (!empty($pay['first_name'])): ?>
                                        <br><small class="text-muted" id="billing-detail-payment-by-<?php echo (int)$pay['id']; ?>">
                                            by <?php echo htmlspecialchars($pay['first_name'] . ' ' . $pay['last_name']); ?>
                                        </small>
                                        <?php endif; ?>
                                    </div>
                                </div>
                                <?php if (!empty($pay['reference_note'])): ?>
                                <small class="text-muted d-block mt-1" id="billing-detail-payment-note-<?php echo (int)$pay['id']; ?>">
                                    <?php echo htmlspecialchars($pay['reference_note']); ?>
                                </small>
                                <?php endif; ?>
                            </li>
                            <?php endforeach; ?>
                        </ul>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>
