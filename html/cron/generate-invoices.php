<?php
/**
 * Cron Job: Generate Invoices
 *
 * Runs daily. Creates invoices for restaurants whose invoice_day_of_month matches today.
 * Auto-applies prepay balance when available.
 *
 * Crontab: 0 2 * * * php /var/www/html/cron/generate-invoices.php >> /var/www/logs/cron-invoices.log 2>&1
 */

require_once __DIR__ . '/../../helpers/services/billing.php';

$now = date('Y-m-d H:i:s');
$today = (int)date('j'); // Day of month (1-31)

echo "[$now] Invoice generation started for day $today\n";

$result = svc_billing_generate_invoices($today);
foreach ($result['log'] as $line) {
    echo "[$now] $line\n";
}

echo "[$now] Invoice generation complete. Created {$result['created']} invoice(s).\n";
