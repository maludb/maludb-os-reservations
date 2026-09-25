<?php
/**
 * Billing service — a restaurant's invoices and prepay balance (read-only for
 * agents), plus the invoice jobs run by html/cron/*.php.
 * Paying invoices and adding prepayments stay human-only (UI partials).
 */

require_once __DIR__ . '/_base.php';

const BILLING_INVOICE_STATUSES = ['unpaid', 'paid', 'overdue', 'void'];

/** Billing is visible to the restaurant's billing user and to super-admins. */
function svc_billing_check_access(int $rid, int $uid, bool $isSuperAdmin): void
{
    if ($isSuperAdmin) return;
    $stmt = db()->prepare("SELECT billing_user FROM restaurants WHERE id = ?");
    $stmt->execute([$rid]);
    if ((int)$stmt->fetchColumn() !== $uid) {
        throw new ServiceError('Only the restaurant\'s billing user or a super-admin can see billing.', 'FORBIDDEN', 403);
    }
}

/** Invoices (optionally by status) with totals and prepay balance. */
function svc_billing_invoice_list(int $rid, string $status = ''): array
{
    $sql = "SELECT * FROM invoices WHERE restaurant_id = ?";
    $params = [$rid];
    if ($status !== '') { $sql .= " AND status = ?"; $params[] = $status; }
    $stmt = db()->prepare($sql . " ORDER BY created_at DESC");
    $stmt->execute($params);
    $invoices = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $stmt = db()->prepare(
        "SELECT COUNT(*) AS total,
                SUM(CASE WHEN status IN ('unpaid','overdue') THEN 1 ELSE 0 END) AS unpaid_count,
                COALESCE(SUM(CASE WHEN status IN ('unpaid','overdue') THEN amount ELSE 0 END), 0) AS unpaid_total,
                COALESCE(SUM(CASE WHEN status = 'paid' THEN amount ELSE 0 END), 0) AS paid_total
         FROM invoices WHERE restaurant_id = ?"
    );
    $stmt->execute([$rid]);
    $summary = $stmt->fetch(PDO::FETCH_ASSOC);
    $summary['prepay_balance'] = svc_billing_prepay_balance($rid);

    return ['summary' => $summary, 'invoices' => $invoices];
}

/** One invoice with its payments. */
function svc_billing_invoice_get(int $rid, int $id): array
{
    $invoice = svc_row('invoices', $id, $rid, 'Invoice');
    $stmt = db()->prepare(
        "SELECT ip.*, u.first_name, u.last_name FROM invoice_payments ip
         LEFT JOIN users u ON ip.created_by = u.id WHERE ip.invoice_id = ? ORDER BY ip.created_at DESC"
    );
    $stmt->execute([$id]);
    $invoice['payments'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
    $invoice['amount_paid'] = round(array_sum(array_column($invoice['payments'], 'amount')), 2);
    $invoice['amount_due'] = round(max(0, (float)$invoice['amount'] - $invoice['amount_paid']), 2);
    return $invoice;
}

function svc_billing_prepay_balance(int $rid): float
{
    $stmt = db()->prepare("SELECT prepay_balance FROM restaurants WHERE id = ?");
    $stmt->execute([$rid]);
    return (float)$stmt->fetchColumn();
}

/** Prepay deposits and applications, newest first. */
function svc_billing_prepay_history(int $rid): array
{
    $stmt = db()->prepare(
        "SELECT pt.*, u.first_name, u.last_name FROM prepay_transactions pt
         LEFT JOIN users u ON pt.created_by = u.id WHERE pt.restaurant_id = ? ORDER BY pt.created_at DESC"
    );
    $stmt->execute([$rid]);
    return ['prepay_balance' => svc_billing_prepay_balance($rid), 'transactions' => $stmt->fetchAll(PDO::FETCH_ASSOC)];
}

/**
 * Create invoices for active restaurants whose invoice_day_of_month is $day
 * (default today), auto-applying prepay balance. Returns ['created' => n, 'log' => [lines]].
 */
function svc_billing_generate_invoices(?int $day = null): array
{
    $pdo = db();
    $day = $day ?? (int)date('j');
    $log = [];

    $stmt = $pdo->prepare(
        "SELECT id, name, invoice_description, monthly_price, prepay_balance
         FROM restaurants WHERE invoice_day_of_month = ? AND monthly_price > 0 AND is_active = 1"
    );
    $stmt->execute([$day]);
    $restaurants = $stmt->fetchAll(PDO::FETCH_ASSOC);
    if (!$restaurants) return ['created' => 0, 'log' => ["No restaurants due for invoicing on day {$day}."]];

    $maxId = (int)$pdo->query("SELECT MAX(id) FROM invoices")->fetchColumn();
    $created = 0;

    foreach ($restaurants as $r) {
        $rid = (int)$r['id'];
        $amount = (float)$r['monthly_price'];
        $prepay = (float)$r['prepay_balance'];
        $invoiceNumber = 'INV-' . str_pad(++$maxId, 6, '0', STR_PAD_LEFT);

        try {
            $pdo->beginTransaction();
            $ins = $pdo->prepare(
                "INSERT INTO invoices (restaurant_id, invoice_number, line_description, amount, status, period_start, period_end, due_date)
                 VALUES (?, ?, ?, ?, 'unpaid', ?, ?, ?) RETURNING id"
            );
            $ins->execute([$rid, $invoiceNumber, $r['invoice_description'], $amount,
                           date('Y-m-d', strtotime('-1 month')), date('Y-m-d'), date('Y-m-d', strtotime('+30 days'))]);
            $invoiceId = (int)$ins->fetchColumn();

            if ($prepay > 0) {
                $apply = min($prepay, $amount);
                $newBalance = round($prepay - $apply, 2);
                $pdo->prepare("INSERT INTO invoice_payments (invoice_id, restaurant_id, amount, payment_method, reference_note) VALUES (?, ?, ?, 'prepay', 'Auto-applied from prepay balance')")
                    ->execute([$invoiceId, $rid, $apply]);
                $pdo->prepare("INSERT INTO prepay_transactions (restaurant_id, amount, balance_after, type, reference_note, invoice_id) VALUES (?, ?, ?, 'invoice_applied', ?, ?)")
                    ->execute([$rid, -$apply, $newBalance, "Applied to {$invoiceNumber}", $invoiceId]);
                $pdo->prepare("UPDATE restaurants SET prepay_balance = ? WHERE id = ?")->execute([$newBalance, $rid]);
                if ($apply >= $amount) {
                    $pdo->prepare("UPDATE invoices SET status = 'paid', paid_at = NOW() WHERE id = ?")->execute([$invoiceId]);
                    $log[] = "Invoice {$invoiceNumber} for '{$r['name']}' — \${$amount} — PAID via prepay";
                } else {
                    $log[] = "Invoice {$invoiceNumber} for '{$r['name']}' — \${$amount} — \${$apply} applied from prepay, remaining: \$" . round($amount - $apply, 2);
                }
            } else {
                $log[] = "Invoice {$invoiceNumber} for '{$r['name']}' — \${$amount} — unpaid";
            }
            $pdo->commit();
            $created++;
        } catch (Throwable $e) {
            $pdo->rollBack();
            $log[] = "ERROR creating invoice for restaurant {$rid}: " . $e->getMessage();
        }
    }
    return ['created' => $created, 'log' => $log];
}

/** Mark unpaid invoices past their due date as overdue. Returns the number changed. */
function svc_billing_mark_overdue(): int
{
    $stmt = db()->prepare("UPDATE invoices SET status = 'overdue', updated_at = NOW() WHERE status = 'unpaid' AND due_date < ?");
    $stmt->execute([date('Y-m-d')]);
    return $stmt->rowCount();
}
