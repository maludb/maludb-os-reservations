<?php
/**
 * API operations: billing (read-only — paying invoices and adding prepayments
 * stay human-only in the UI). Visible to the restaurant's billing user and super-admins.
 */

require_once __DIR__ . '/../../services/billing.php';

$billingAccess = fn($ctx) => svc_billing_check_access($ctx['restaurant_id'], $ctx['user_id'], ($ctx['platform_role'] ?? '') === 'super-admin');

api_op('billing_invoices_list', [
    'description' => "List this restaurant's invoices (newest first) with unpaid/paid totals and the prepay balance. Only the billing user or a super-admin may call this.",
    'method' => 'GET', 'path' => 'billing/invoices',
    'input' => ['status' => ['type' => 'string', 'enum' => BILLING_INVOICE_STATUSES]],
    'handler' => function ($in, $ctx) use ($billingAccess) {
        $billingAccess($ctx);
        return svc_billing_invoice_list($ctx['restaurant_id'], $in['status'] ?? '');
    },
]);

api_op('billing_invoices_get', [
    'description' => 'Get one invoice with its payments, amount paid and amount due.',
    'method' => 'GET', 'path' => 'billing/invoices/{id}',
    'handler' => function ($in, $ctx) use ($billingAccess) {
        $billingAccess($ctx);
        return ['invoice' => svc_billing_invoice_get($ctx['restaurant_id'], $in['id'])];
    },
]);

api_op('billing_prepay_history', [
    'description' => 'Prepay balance and the history of deposits and amounts applied to invoices.',
    'method' => 'GET', 'path' => 'billing/prepay',
    'handler' => function ($in, $ctx) use ($billingAccess) {
        $billingAccess($ctx);
        return svc_billing_prepay_history($ctx['restaurant_id']);
    },
]);
