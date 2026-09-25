<?php
/**
 * Billing — Process Invoice Payment
 *
 * Handles payment submission: creates payment record, updates invoice status,
 * deducts prepay balance if applicable.
 */
require_once __DIR__ . '/../../../helpers/auth.php';
require_once __DIR__ . '/../../../helpers/db.php';
require_once __DIR__ . '/../../../helpers/csrf.php';

requireAuth();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    exit;
}

if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
    http_response_code(403);
    echo '<div class="alert alert-danger m-4">Invalid security token. Please refresh and try again.</div>';
    exit;
}

$restaurantId = currentRestaurantId();
$userId = currentUserId();
$pdo = db();

$invoiceId = (int)($_POST['invoice_id'] ?? 0);
$amount = (float)($_POST['amount'] ?? 0);
$paymentMethod = trim($_POST['payment_method'] ?? 'direct');
$referenceNote = trim($_POST['reference_note'] ?? '');

if ($invoiceId <= 0 || $amount <= 0) {
    echo '<div class="alert alert-danger m-4">Invalid payment details.</div>';
    exit;
}

if (!in_array($paymentMethod, ['prepay', 'direct'])) {
    $paymentMethod = 'direct';
}

// Get invoice
$invStmt = $pdo->prepare("SELECT * FROM invoices WHERE id = ? AND restaurant_id = ?");
$invStmt->execute([$invoiceId, $restaurantId]);
$invoice = $invStmt->fetch();

if (!$invoice || !in_array($invoice['status'], ['unpaid', 'overdue'])) {
    echo '<div class="alert alert-danger m-4">Invoice not found or already paid.</div>';
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

// Calculate remaining
$paidStmt = $pdo->prepare("SELECT COALESCE(SUM(amount), 0) FROM invoice_payments WHERE invoice_id = ?");
$paidStmt->execute([$invoiceId]);
$totalPaid = (float)$paidStmt->fetchColumn();
$remaining = round((float)$invoice['amount'] - $totalPaid, 2);

// Cap amount to remaining
if ($amount > $remaining) {
    $amount = $remaining;
}

// If prepay, verify sufficient balance
$prepayBalance = (float)($restaurant['prepay_balance'] ?? 0);
if ($paymentMethod === 'prepay' && $amount > $prepayBalance) {
    echo '<div class="alert alert-danger m-4">Insufficient prepay balance. Available: $' . number_format($prepayBalance, 2) . '</div>';
    exit;
}

try {
    $pdo->beginTransaction();

    // Create payment record
    $payStmt = $pdo->prepare(
        "INSERT INTO invoice_payments (invoice_id, restaurant_id, amount, payment_method, reference_note, created_by)
         VALUES (?, ?, ?, ?, ?, ?)"
    );
    $payStmt->execute([$invoiceId, $restaurantId, $amount, $paymentMethod, $referenceNote ?: null, $userId]);

    // If prepay, deduct from balance and create transaction
    if ($paymentMethod === 'prepay') {
        $newBalance = round($prepayBalance - $amount, 2);

        $updStmt = $pdo->prepare("UPDATE restaurants SET prepay_balance = ? WHERE id = ?");
        $updStmt->execute([$newBalance, $restaurantId]);

        $txStmt = $pdo->prepare(
            "INSERT INTO prepay_transactions (restaurant_id, amount, balance_after, type, reference_note, invoice_id, created_by)
             VALUES (?, ?, ?, 'invoice_applied', ?, ?, ?)"
        );
        $txStmt->execute([
            $restaurantId, -$amount, $newBalance,
            "Applied to " . $invoice['invoice_number'],
            $invoiceId, $userId
        ]);
    }

    // Check if invoice is fully paid
    $newTotalPaid = $totalPaid + $amount;
    if ($newTotalPaid >= (float)$invoice['amount']) {
        $paidStmt = $pdo->prepare("UPDATE invoices SET status = 'paid', paid_at = NOW() WHERE id = ?");
        $paidStmt->execute([$invoiceId]);
    }

    $pdo->commit();
} catch (Exception $e) {
    $pdo->rollBack();
    echo '<div class="alert alert-danger m-4">An error occurred processing the payment. Please try again.</div>';
    exit;
}

// Show invoice detail
$_GET['id'] = $invoiceId;
include __DIR__ . '/invoice-detail.php';
