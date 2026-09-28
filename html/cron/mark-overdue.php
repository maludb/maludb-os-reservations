<?php
/**
 * Cron Job: Mark Overdue Invoices
 *
 * Runs daily. Marks unpaid invoices past their due date as 'overdue'.
 *
 * Crontab: 0 3 * * * php /var/www/html/cron/mark-overdue.php >> /var/www/logs/cron-invoices.log 2>&1
 */

// Run by cron with php-cli only, never over the web
if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

require_once __DIR__ . '/../../helpers/services/billing.php';

$now = date('Y-m-d H:i:s');

echo "[$now] Checking for overdue invoices...\n";

$count = svc_billing_mark_overdue();

echo "[$now] Marked $count invoice(s) as overdue.\n";
