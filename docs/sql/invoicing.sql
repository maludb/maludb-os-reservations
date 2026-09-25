-- ============================================================
-- Invoicing & Payment System — PostgreSQL migration
-- Adds the billing columns and tables used by html/partials/billing/,
-- html/partials/platform/billing-settings.php and html/cron/generate-invoices.php.
-- (Already folded into pg_schema.sql for fresh installs.)
-- ============================================================

-- 1. Billing columns on restaurants (prepay_balance and billing_user already exist)
ALTER TABLE restaurants
  ADD COLUMN IF NOT EXISTS invoice_day_of_month SMALLINT NOT NULL DEFAULT 1,
  ADD COLUMN IF NOT EXISTS invoice_description VARCHAR(255) NOT NULL DEFAULT 'Monthly Service Fee',
  ADD COLUMN IF NOT EXISTS monthly_price DECIMAL(10,2) NOT NULL DEFAULT 0.00;

-- 2. Invoice columns
ALTER TABLE invoices
  ADD COLUMN IF NOT EXISTS invoice_number VARCHAR(20),
  ADD COLUMN IF NOT EXISTS line_description VARCHAR(255) NOT NULL DEFAULT 'Monthly Service Fee',
  ADD COLUMN IF NOT EXISTS period_start DATE,
  ADD COLUMN IF NOT EXISTS period_end DATE,
  ADD COLUMN IF NOT EXISTS paid_at TIMESTAMP NULL;
ALTER TABLE invoices DROP CONSTRAINT IF EXISTS chk_invoices_status;
ALTER TABLE invoices ADD CONSTRAINT chk_invoices_status CHECK (status IN ('unpaid','paid','overdue','void'));
CREATE UNIQUE INDEX IF NOT EXISTS uk_invoice_number ON invoices (invoice_number);
CREATE INDEX IF NOT EXISTS idx_invoices_due_date ON invoices (due_date);

-- 3. Invoice payments
CREATE TABLE IF NOT EXISTS invoice_payments (
    id SERIAL PRIMARY KEY,
    invoice_id INTEGER NOT NULL REFERENCES invoices (id) ON UPDATE CASCADE ON DELETE CASCADE,
    restaurant_id INTEGER NOT NULL REFERENCES restaurants (id) ON UPDATE CASCADE ON DELETE CASCADE,
    amount DECIMAL(10,2) NOT NULL,
    payment_method VARCHAR(20) NOT NULL,
    reference_note VARCHAR(255) DEFAULT NULL,
    created_by INTEGER DEFAULT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT chk_invoice_payments_method CHECK (payment_method IN ('prepay','direct','manual'))
);
CREATE INDEX IF NOT EXISTS idx_invoice_payments_invoice ON invoice_payments (invoice_id);
CREATE INDEX IF NOT EXISTS idx_invoice_payments_restaurant ON invoice_payments (restaurant_id);

-- 4. Prepay transactions
CREATE TABLE IF NOT EXISTS prepay_transactions (
    id SERIAL PRIMARY KEY,
    restaurant_id INTEGER NOT NULL REFERENCES restaurants (id) ON UPDATE CASCADE ON DELETE CASCADE,
    amount DECIMAL(10,2) NOT NULL,
    balance_after DECIMAL(10,2) NOT NULL,
    type VARCHAR(20) NOT NULL,
    reference_note VARCHAR(255) DEFAULT NULL,
    invoice_id INTEGER DEFAULT NULL,
    created_by INTEGER DEFAULT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT chk_prepay_transactions_type CHECK (type IN ('deposit','invoice_applied','refund','adjustment'))
);
CREATE INDEX IF NOT EXISTS idx_prepay_transactions_restaurant ON prepay_transactions (restaurant_id);
CREATE INDEX IF NOT EXISTS idx_prepay_transactions_invoice ON prepay_transactions (invoice_id);
