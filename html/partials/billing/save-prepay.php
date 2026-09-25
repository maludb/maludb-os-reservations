<?php
/**
 * Billing — Save Prepay Deposit
 *
 * Processes a prepay deposit: updates balance and creates transaction record.
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

// Verify billing access
$accessStmt = $pdo->prepare("SELECT billing_user, prepay_balance FROM restaurants WHERE id = ?");
$accessStmt->execute([$restaurantId]);
$restaurant = $accessStmt->fetch();

$isBillingUser = ((int)($restaurant['billing_user'] ?? 0) === $userId);

if (!$isBillingUser && !isSuperAdmin()) {
    echo '<div class="alert alert-danger m-4">Access denied.</div>';
    exit;
}

$amount = (float)($_POST['amount'] ?? 0);
$referenceNote = trim($_POST['reference_note'] ?? '');

if ($amount <= 0) {
    echo '<div class="alert alert-danger m-4">Amount must be greater than zero.</div>';
    exit;
}

$currentBalance = (float)($restaurant['prepay_balance'] ?? 0);
$newBalance = round($currentBalance + $amount, 2);

try {
    $pdo->beginTransaction();

    // Update restaurant prepay balance
    $updStmt = $pdo->prepare("UPDATE restaurants SET prepay_balance = ? WHERE id = ?");
    $updStmt->execute([$newBalance, $restaurantId]);

    // Create prepay transaction record
    $txStmt = $pdo->prepare(
        "INSERT INTO prepay_transactions (restaurant_id, amount, balance_after, type, reference_note, created_by)
         VALUES (?, ?, ?, 'deposit', ?, ?)"
    );
    $txStmt->execute([$restaurantId, $amount, $newBalance, $referenceNote ?: null, $userId]);

    $pdo->commit();
} catch (Exception $e) {
    $pdo->rollBack();
    echo '<div class="alert alert-danger m-4">An error occurred. Please try again.</div>';
    exit;
}

// Reload invoices page
include __DIR__ . '/invoices.php';
