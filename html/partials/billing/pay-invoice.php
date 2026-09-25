<?php
/**
 * Billing — Pay Invoice Form
 *
 * Shows payment options for an unpaid/overdue invoice.
 */
require_once __DIR__ . '/../../../helpers/auth.php';
require_once __DIR__ . '/../../../helpers/db.php';
require_once __DIR__ . '/../../../helpers/csrf.php';

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

if (!in_array($invoice['status'], ['unpaid', 'overdue'])) {
    echo '<div class="alert alert-info m-4">This invoice is already ' . htmlspecialchars($invoice['status']) . '.</div>';
    exit;
}

// Verify billing access
$accessStmt = $pdo->prepare("SELECT billing_user, prepay_balance FROM restaurants WHERE id = ?");
$accessStmt->execute([$restaurantId]);
$restaurant = $accessStmt->fetch();

$isBillingUser = ((int)($restaurant['billing_user'] ?? 0) === $userId);

if (!$isBillingUser && !isSuperAdmin()) {
    echo '<div class="alert alert-danger m-4">Access denied.</div>';
    exit;
}

$prepayBalance = (float)($restaurant['prepay_balance'] ?? 0);

// Calculate remaining amount
$paidStmt = $pdo->prepare("SELECT COALESCE(SUM(amount), 0) FROM invoice_payments WHERE invoice_id = ?");
$paidStmt->execute([$invoiceId]);
$totalPaid = (float)$paidStmt->fetchColumn();
$remaining = round((float)$invoice['amount'] - $totalPaid, 2);

$canPayWithPrepay = ($prepayBalance > 0);
?>

<div class="p-4" id="billing-pay-invoice-main">
    <div class="d-flex justify-content-between align-items-center mb-4" id="billing-pay-invoice-header">
        <div id="billing-pay-invoice-title-wrap">
            <h4 class="mb-0" id="billing-pay-invoice-title">Pay Invoice <?php echo htmlspecialchars($invoice['invoice_number']); ?></h4>
            <small class="text-muted" id="billing-pay-invoice-subtitle">
                Amount Due: <strong class="text-danger">$<?php echo number_format($remaining, 2); ?></strong>
            </small>
        </div>
        <a href="#" class="btn btn-outline-secondary" id="billing-pay-invoice-back-btn"
           hx-get="/partials/billing/invoice-detail.php?id=<?php echo $invoiceId; ?>" hx-target="#page-content">
            <i class="feather-arrow-left me-1"></i> Back to Invoice
        </a>
    </div>

    <div class="row justify-content-center" id="billing-pay-invoice-form-row">
        <div class="col-lg-6" id="billing-pay-invoice-form-col">
            <!-- Invoice Summary -->
            <div class="card mb-3" id="billing-pay-invoice-summary-card">
                <div class="card-body" id="billing-pay-invoice-summary-body">
                    <div class="d-flex justify-content-between mb-2" id="billing-pay-invoice-summary-desc">
                        <span><?php echo htmlspecialchars($invoice['line_description']); ?></span>
                        <span class="fw-bold">$<?php echo number_format((float)$invoice['amount'], 2); ?></span>
                    </div>
                    <?php if ($totalPaid > 0): ?>
                    <div class="d-flex justify-content-between mb-2 text-success" id="billing-pay-invoice-summary-paid">
                        <span>Previously Paid</span>
                        <span>-$<?php echo number_format($totalPaid, 2); ?></span>
                    </div>
                    <?php endif; ?>
                    <hr>
                    <div class="d-flex justify-content-between fw-bold" id="billing-pay-invoice-summary-remaining">
                        <span>Remaining Due</span>
                        <span class="text-danger">$<?php echo number_format($remaining, 2); ?></span>
                    </div>
                </div>
            </div>

            <!-- Payment Form -->
            <div class="card" id="billing-pay-invoice-form-card">
                <div class="card-header" id="billing-pay-invoice-form-header">
                    <h6 class="mb-0" id="billing-pay-invoice-form-label">Payment Method</h6>
                </div>
                <div class="card-body" id="billing-pay-invoice-form-body">
                    <form hx-post="/partials/billing/process-payment.php"
                          hx-target="#page-content"
                          id="billing-pay-invoice-form">
                        <?php echo csrf_field(); ?>
                        <input type="hidden" name="invoice_id" value="<?php echo $invoiceId; ?>">

                        <div class="mb-3" id="billing-pay-method-wrap">
                            <label class="form-label fw-semibold" id="billing-pay-method-label">Pay With</label>

                            <?php if ($canPayWithPrepay): ?>
                            <div class="form-check mb-2" id="billing-pay-method-prepay-wrap">
                                <input class="form-check-input" type="radio" name="payment_method" value="prepay"
                                       id="billing-pay-method-prepay" checked>
                                <label class="form-check-label" for="billing-pay-method-prepay">
                                    Prepay Balance
                                    <span class="text-success fw-semibold">($<?php echo number_format($prepayBalance, 2); ?> available)</span>
                                </label>
                            </div>
                            <?php endif; ?>

                            <div class="form-check mb-2" id="billing-pay-method-direct-wrap">
                                <input class="form-check-input" type="radio" name="payment_method" value="direct"
                                       id="billing-pay-method-direct" <?php echo !$canPayWithPrepay ? 'checked' : ''; ?>>
                                <label class="form-check-label" for="billing-pay-method-direct">
                                    Direct Payment (check, wire, etc.)
                                </label>
                            </div>
                        </div>

                        <div class="mb-3" id="billing-pay-amount-wrap">
                            <label for="billing-pay-amount" class="form-label fw-semibold">Payment Amount</label>
                            <div class="input-group" id="billing-pay-amount-group">
                                <span class="input-group-text" id="billing-pay-amount-prefix">$</span>
                                <input type="number" class="form-control" id="billing-pay-amount" name="amount"
                                       min="0.01" max="<?php echo $remaining; ?>" step="0.01"
                                       value="<?php echo number_format($remaining, 2, '.', ''); ?>" required>
                            </div>
                        </div>

                        <div class="mb-3" id="billing-pay-note-wrap">
                            <label for="billing-pay-note" class="form-label fw-semibold">Reference Note</label>
                            <input type="text" class="form-control" id="billing-pay-note" name="reference_note"
                                   placeholder="e.g. Check #1234, confirmation number, etc." maxlength="255">
                        </div>

                        <div class="d-flex gap-2" id="billing-pay-buttons">
                            <button type="submit" class="btn btn-success" id="billing-pay-submit-btn">
                                <i class="feather-credit-card me-1"></i> Submit Payment
                            </button>
                            <a href="#" class="btn btn-outline-secondary" id="billing-pay-cancel-btn"
                               hx-get="/partials/billing/invoice-detail.php?id=<?php echo $invoiceId; ?>"
                               hx-target="#page-content">
                                Cancel
                            </a>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>
