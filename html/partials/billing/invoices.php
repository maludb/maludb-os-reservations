<?php
/**
 * Billing — Invoice List
 *
 * Shows all invoices for the current restaurant with status, amounts, and actions.
 */
require_once __DIR__ . '/../../../helpers/auth.php';
require_once __DIR__ . '/../../../helpers/db.php';

requireAuth();
$restaurantId = currentRestaurantId();
$userId = currentUserId();
$pdo = db();

// Verify user has billing access (billing_user or super-admin)
$accessStmt = $pdo->prepare("SELECT billing_user, prepay_balance FROM restaurants WHERE id = ?");
$accessStmt->execute([$restaurantId]);
$restaurant = $accessStmt->fetch();
$billingUser = (int)($restaurant['billing_user'] ?? 0);
$prepayBalance = (float)($restaurant['prepay_balance'] ?? 0);

$isBillingUser = ($billingUser === $userId);
$isSuperAdmin = isSuperAdmin();

if (!$isBillingUser && !$isSuperAdmin) {
    echo '<div class="alert alert-danger m-4">You do not have access to billing.</div>';
    exit;
}

// Filter by status
$statusFilter = trim($_GET['status'] ?? '');

// Get invoices
$sql = "SELECT * FROM invoices WHERE restaurant_id = ?";
$params = [$restaurantId];
if ($statusFilter && in_array($statusFilter, ['unpaid', 'paid', 'overdue', 'void'])) {
    $sql .= " AND status = ?";
    $params[] = $statusFilter;
}
$sql .= " ORDER BY created_at DESC";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$invoices = $stmt->fetchAll();

// Summary stats
$summaryStmt = $pdo->prepare(
    "SELECT
        COUNT(*) AS total,
        SUM(CASE WHEN status IN ('unpaid','overdue') THEN 1 ELSE 0 END) AS unpaid_count,
        COALESCE(SUM(CASE WHEN status IN ('unpaid','overdue') THEN amount ELSE 0 END), 0) AS unpaid_total,
        COALESCE(SUM(CASE WHEN status = 'paid' THEN amount ELSE 0 END), 0) AS paid_total
     FROM invoices WHERE restaurant_id = ?"
);
$summaryStmt->execute([$restaurantId]);
$summary = $summaryStmt->fetch();

$statusBadge = [
    'unpaid'  => 'warning',
    'paid'    => 'success',
    'overdue' => 'danger',
    'void'    => 'secondary',
];
?>

<div class="p-4" id="billing-invoices-main">
    <div class="d-flex justify-content-between align-items-center mb-4" id="billing-invoices-header">
        <div id="billing-invoices-title-wrap">
            <h4 class="mb-0" id="billing-invoices-title">Billing</h4>
            <small class="text-muted" id="billing-invoices-subtitle">Invoices & Payments</small>
        </div>
        <div class="d-flex gap-2" id="billing-invoices-actions">
            <a href="#" class="btn btn-outline-success" id="billing-add-prepay-btn"
               hx-get="/partials/billing/add-prepay.php"
               hx-target="#page-content">
                <i class="feather-plus-circle me-1"></i> Add Prepay
            </a>
            <a href="#" class="btn btn-outline-primary" id="billing-prepay-history-btn"
               hx-get="/partials/billing/prepay-history.php"
               hx-target="#page-content">
                <i class="feather-list me-1"></i> Prepay History
            </a>
        </div>
    </div>

    <!-- Summary Cards -->
    <div class="row g-3 mb-4" id="billing-summary-row">
        <div class="col-sm-6 col-xl-3" id="billing-summary-prepay">
            <div class="card border-0 shadow-sm h-100" id="billing-summary-prepay-card">
                <div class="card-body d-flex align-items-center" id="billing-summary-prepay-body">
                    <div class="avatar-md bg-success bg-opacity-10 rounded-3 d-flex align-items-center justify-content-center me-3" id="billing-summary-prepay-icon">
                        <i class="feather-dollar-sign text-success" style="font-size:24px;"></i>
                    </div>
                    <div id="billing-summary-prepay-text">
                        <h3 class="mb-0 fw-bold" id="billing-summary-prepay-amount">$<?php echo number_format($prepayBalance, 2); ?></h3>
                        <small class="text-muted" id="billing-summary-prepay-label">Prepay Balance</small>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-sm-6 col-xl-3" id="billing-summary-unpaid">
            <div class="card border-0 shadow-sm h-100" id="billing-summary-unpaid-card">
                <div class="card-body d-flex align-items-center" id="billing-summary-unpaid-body">
                    <div class="avatar-md <?php echo (int)$summary['unpaid_count'] > 0 ? 'bg-danger' : 'bg-info'; ?> bg-opacity-10 rounded-3 d-flex align-items-center justify-content-center me-3" id="billing-summary-unpaid-icon">
                        <i class="feather-alert-circle <?php echo (int)$summary['unpaid_count'] > 0 ? 'text-danger' : 'text-info'; ?>" style="font-size:24px;"></i>
                    </div>
                    <div id="billing-summary-unpaid-text">
                        <h3 class="mb-0 fw-bold" id="billing-summary-unpaid-amount">$<?php echo number_format((float)$summary['unpaid_total'], 2); ?></h3>
                        <small class="text-muted" id="billing-summary-unpaid-label"><?php echo (int)$summary['unpaid_count']; ?> Unpaid Invoice<?php echo (int)$summary['unpaid_count'] !== 1 ? 's' : ''; ?></small>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-sm-6 col-xl-3" id="billing-summary-paid">
            <div class="card border-0 shadow-sm h-100" id="billing-summary-paid-card">
                <div class="card-body d-flex align-items-center" id="billing-summary-paid-body">
                    <div class="avatar-md bg-primary bg-opacity-10 rounded-3 d-flex align-items-center justify-content-center me-3" id="billing-summary-paid-icon">
                        <i class="feather-check-circle text-primary" style="font-size:24px;"></i>
                    </div>
                    <div id="billing-summary-paid-text">
                        <h3 class="mb-0 fw-bold" id="billing-summary-paid-amount">$<?php echo number_format((float)$summary['paid_total'], 2); ?></h3>
                        <small class="text-muted" id="billing-summary-paid-label">Total Paid</small>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-sm-6 col-xl-3" id="billing-summary-total">
            <div class="card border-0 shadow-sm h-100" id="billing-summary-total-card">
                <div class="card-body d-flex align-items-center" id="billing-summary-total-body">
                    <div class="avatar-md bg-secondary bg-opacity-10 rounded-3 d-flex align-items-center justify-content-center me-3" id="billing-summary-total-icon">
                        <i class="feather-file-text text-secondary" style="font-size:24px;"></i>
                    </div>
                    <div id="billing-summary-total-text">
                        <h3 class="mb-0 fw-bold" id="billing-summary-total-count"><?php echo (int)$summary['total']; ?></h3>
                        <small class="text-muted" id="billing-summary-total-label">Total Invoices</small>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Status Filter Tabs -->
    <div class="mb-3" id="billing-invoices-filters">
        <div class="btn-group" role="group" id="billing-invoices-filter-group">
            <a href="#" class="btn btn-sm <?php echo !$statusFilter ? 'btn-primary' : 'btn-outline-primary'; ?>"
               hx-get="/partials/billing/invoices.php" hx-target="#page-content" id="billing-filter-all">All</a>
            <a href="#" class="btn btn-sm <?php echo $statusFilter === 'unpaid' ? 'btn-warning' : 'btn-outline-warning'; ?>"
               hx-get="/partials/billing/invoices.php?status=unpaid" hx-target="#page-content" id="billing-filter-unpaid">Unpaid</a>
            <a href="#" class="btn btn-sm <?php echo $statusFilter === 'overdue' ? 'btn-danger' : 'btn-outline-danger'; ?>"
               hx-get="/partials/billing/invoices.php?status=overdue" hx-target="#page-content" id="billing-filter-overdue">Overdue</a>
            <a href="#" class="btn btn-sm <?php echo $statusFilter === 'paid' ? 'btn-success' : 'btn-outline-success'; ?>"
               hx-get="/partials/billing/invoices.php?status=paid" hx-target="#page-content" id="billing-filter-paid">Paid</a>
        </div>
    </div>

    <!-- Invoice Table -->
    <div class="card" id="billing-invoices-table-card">
        <div class="card-body p-0" id="billing-invoices-table-body">
            <?php if (empty($invoices)): ?>
                <div class="text-center text-muted py-5" id="billing-invoices-empty">
                    <i class="feather-file-text" style="font-size:48px;"></i>
                    <p class="mt-2 mb-0">No invoices found.</p>
                </div>
            <?php else: ?>
                <div class="table-responsive" id="billing-invoices-table-wrap">
                    <table class="table table-hover mb-0" id="billing-invoices-table">
                        <thead class="table-light" id="billing-invoices-thead">
                            <tr>
                                <th id="billing-invoices-th-number">Invoice #</th>
                                <th id="billing-invoices-th-date">Date</th>
                                <th id="billing-invoices-th-description">Description</th>
                                <th id="billing-invoices-th-period">Period</th>
                                <th id="billing-invoices-th-amount">Amount</th>
                                <th id="billing-invoices-th-due">Due Date</th>
                                <th id="billing-invoices-th-status">Status</th>
                                <th id="billing-invoices-th-actions" class="text-end">Actions</th>
                            </tr>
                        </thead>
                        <tbody id="billing-invoices-tbody">
                            <?php foreach ($invoices as $inv): ?>
                            <tr id="billing-invoice-row-<?php echo (int)$inv['id']; ?>">
                                <td id="billing-invoice-num-<?php echo (int)$inv['id']; ?>">
                                    <a href="#" class="fw-semibold text-decoration-none"
                                       hx-get="/partials/billing/invoice-detail.php?id=<?php echo (int)$inv['id']; ?>"
                                       hx-target="#page-content">
                                        <?php echo htmlspecialchars($inv['invoice_number']); ?>
                                    </a>
                                </td>
                                <td id="billing-invoice-date-<?php echo (int)$inv['id']; ?>"><?php echo date('M j, Y', strtotime($inv['created_at'])); ?></td>
                                <td id="billing-invoice-desc-<?php echo (int)$inv['id']; ?>"><?php echo htmlspecialchars($inv['line_description']); ?></td>
                                <td id="billing-invoice-period-<?php echo (int)$inv['id']; ?>">
                                    <?php echo date('M j', strtotime($inv['period_start'])); ?> – <?php echo date('M j, Y', strtotime($inv['period_end'])); ?>
                                </td>
                                <td id="billing-invoice-amount-<?php echo (int)$inv['id']; ?>" class="fw-semibold">$<?php echo number_format((float)$inv['amount'], 2); ?></td>
                                <td id="billing-invoice-due-<?php echo (int)$inv['id']; ?>"><?php echo date('M j, Y', strtotime($inv['due_date'])); ?></td>
                                <td id="billing-invoice-status-<?php echo (int)$inv['id']; ?>">
                                    <span class="badge bg-<?php echo $statusBadge[$inv['status']] ?? 'secondary'; ?>">
                                        <?php echo ucfirst($inv['status']); ?>
                                    </span>
                                </td>
                                <td class="text-end" id="billing-invoice-actions-<?php echo (int)$inv['id']; ?>">
                                    <a href="#" class="btn btn-sm btn-outline-primary"
                                       hx-get="/partials/billing/invoice-detail.php?id=<?php echo (int)$inv['id']; ?>"
                                       hx-target="#page-content"
                                       id="billing-invoice-view-<?php echo (int)$inv['id']; ?>">
                                        <i class="feather-eye"></i>
                                    </a>
                                    <?php if (in_array($inv['status'], ['unpaid', 'overdue'])): ?>
                                    <a href="#" class="btn btn-sm btn-success"
                                       hx-get="/partials/billing/pay-invoice.php?id=<?php echo (int)$inv['id']; ?>"
                                       hx-target="#page-content"
                                       id="billing-invoice-pay-<?php echo (int)$inv['id']; ?>">
                                        <i class="feather-credit-card me-1"></i> Pay
                                    </a>
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
