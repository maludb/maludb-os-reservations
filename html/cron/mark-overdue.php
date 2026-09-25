<?php
/**
 * Cron Job: Mark Overdue Invoices
 *
 * Runs daily. Marks unpaid invoices past their due date as 'overdue'.
 *
 * Crontab: 0 3 * * * php /var/www/html/cron/mark-overdue.php >> /var/www/logs/cron-invoices.log 2>&1
 */

require_once __DIR__ . '/../../helpers/services/billing.php';

$now = date('Y-m-d H:i:s');

echo "[$now] Checking for overdue invoices...\n";

$count = svc_billing_mark_overdue();

echo "[$now] Marked $count invoice(s) as overdue.\n";
