<?php
/**
 * Billing — Add Prepay Balance
 *
 * Form to add funds to the restaurant's prepay balance.
 */
require_once __DIR__ . '/../../../helpers/auth.php';
require_once __DIR__ . '/../../../helpers/db.php';
require_once __DIR__ . '/../../../helpers/csrf.php';

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
?>

<div class="p-4" id="billing-add-prepay-main">
    <div class="d-flex justify-content-between align-items-center mb-4" id="billing-add-prepay-header">
        <div id="billing-add-prepay-title-wrap">
            <h4 class="mb-0" id="billing-add-prepay-title">Add Prepay Funds</h4>
            <small class="text-muted" id="billing-add-prepay-subtitle">Current Balance: $<?php echo number_format($currentBalance, 2); ?></small>
        </div>
        <a href="#" class="btn btn-outline-secondary" id="billing-add-prepay-back-btn"
           hx-get="/partials/billing/invoices.php" hx-target="#page-content">
            <i class="feather-arrow-left me-1"></i> Back to Billing
        </a>
    </div>

    <div class="row justify-content-center" id="billing-add-prepay-form-row">
        <div class="col-lg-6" id="billing-add-prepay-form-col">
            <div class="card" id="billing-add-prepay-form-card">
                <div class="card-header" id="billing-add-prepay-form-header">
                    <h6 class="mb-0" id="billing-add-prepay-form-label">Deposit Funds</h6>
                </div>
                <div class="card-body" id="billing-add-prepay-form-body">
                    <form hx-post="/partials/billing/save-prepay.php"
                          hx-target="#page-content"
                          id="billing-add-prepay-form">
                        <?php echo csrf_field(); ?>

                        <div class="mb-3" id="billing-add-prepay-amount-wrap">
                            <label for="billing-prepay-amount" class="form-label fw-semibold">Amount <span class="text-danger">*</span></label>
                            <div class="input-group" id="billing-add-prepay-amount-group">
                                <span class="input-group-text" id="billing-prepay-amount-prefix">$</span>
                                <input type="number" class="form-control" id="billing-prepay-amount" name="amount"
                                       min="1" step="0.01" required placeholder="0.00">
                            </div>
                        </div>

                        <div class="mb-3" id="billing-add-prepay-note-wrap">
                            <label for="billing-prepay-note" class="form-label fw-semibold">Reference Note</label>
                            <input type="text" class="form-control" id="billing-prepay-note" name="reference_note"
                                   placeholder="e.g. Check #1234, Wire transfer, etc." maxlength="255">
                        </div>

                        <div class="d-flex gap-2" id="billing-add-prepay-buttons">
                            <button type="submit" class="btn btn-success" id="billing-add-prepay-submit-btn">
                                <i class="feather-plus-circle me-1"></i> Add Funds
                            </button>
                            <a href="#" class="btn btn-outline-secondary" id="billing-add-prepay-cancel-btn"
                               hx-get="/partials/billing/invoices.php" hx-target="#page-content">
                                Cancel
                            </a>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>
