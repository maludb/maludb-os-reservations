# Plan — Make ZozoCal a restaurant-reservation-only system

Goal: remove the affiliate system and the professional appointment scheduler that were merged into
this codebase, so that only the restaurant reservation product remains. New registrations get a
restaurant, with no business-type choice.

## What research found

Three foreign products are in the tree, not two:

| Product | State | Footprint |
|---|---|---|
| Professional scheduling | Live, reachable from the nav | `html/partials/professional/` (26), `html/pro-booking/` (7), 5 helpers, MCP `pro.php`/`pro-tools.php`/`sms.php`/`sms-tools.php`, Retell + SMS pro webhooks, REST `html/api/v1/*` (8 of 11 files), 6 `professional_*` tables |
| Affiliate / prospects | Live, reachable from the nav | `html/partials/affiliate/` (23), 9 `platform/affiliate*` partials, `helpers/prospect-restaurant.php`, 6 tables, `affiliate_*` columns on restaurants/users/products/invoices |
| SalesCoach Pro ("Drajeo") | **Dead** — every page calls `currentOrgId()`, which no longer exists; its tables are not in the schema | all of `models/` except `User.php`, 18 orphan `html/partials/*` dirs (tasks, kanban, sessions, scoring, teams, …), 12 loose `html/partials/*.php`, `docs/migrations/`, `docs/sql/plans_and_subscriptions.sql`, `old_requirements.md` |

Restaurant code never uses the `models/` classes (raw PDO only), except `models/User.php` in the
password-reset flow. Billing (`invoices`, `subscriptions`, `products`, prepay) is restaurant billing
and stays; only its affiliate commission/price fields go.

Two things must be fixed *before* deletions or the restaurant product breaks:
- `html/partials/settings/retell-create-agent.php` points every new voice agent at the professional
  MCP server (`/api/mcp/pro.php`) with appointment tools. Must switch to `/api/mcp/server.php` and
  the reservation tools.
- `helpers/restaurant.php:52-61` gives super-admins only affiliate "in-setup" restaurants. Must
  return all active restaurants.

Bug noticed on the side: the nav links to `/partials/settings/reservations.php`, which does not exist.

## Decisions confirmed 2026-09-21

- Remove the dead SalesCoach Pro code as well.
- Keep restaurant billing (invoices, prepay, subscriptions, products, invoice cron); strip affiliate commission and affiliate price fields only.
- No migration script: rewrite `pg_schema.sql` restaurant-only and rebuild the empty local database from it.
- Trim `html/api/v1` to `_bootstrap.php`, `auth.php`, `todos.php` (keep `api_tokens`); rewrite the SMS webhook sender lookup against `guests`.

## Assumptions (say so if any is wrong)

- The local database is empty, so the schema file is rewritten restaurant-only and the database is
  rebuilt from it. No ALTER migration script is written.
- Each phase below is committed and pushed separately, on `main`, after the app is verified.
- `restaurants.status` keeps its column but the default changes from `in-setup` to `active`.
- `kobie/` stays (CLAUDE.md). `design/`, `design-notes.md`, `htmx-*` skill folders are left alone.
- `docs/activity.md` and `docs/plans/` stay as history even where they mention the old products.

## Tasks

### Phase 0 — Safety fixes first
- [x] 0.1 `retell-create-agent.php`: use `/api/mcp/server.php`, name `restaurant_mcp`, reservation tools from `server-tools.php`
- [x] 0.2 `helpers/restaurant.php`: super-admin branch returns all active restaurants; drop the `affiliate_id` branch
- [x] 0.3 Verify: login, dashboard and Integrations page still load

### Phase 1 — Delete the dead SalesCoach code (zero risk)
- [x] 1.1 Delete `models/*` except `User.php`
- [x] 1.2 Delete orphan partial dirs: activity, agents, analytics, calendar, coaching-calendar, context, kanban, models, notifications, organizations, products, projects, prospects, scoring, sessions, subscription, tasks, team, teams
- [x] 1.3 Delete loose `html/partials/{crm,customers,expenses,orders,projects,subscriptions,support,tasks,dashboard,profile,settings,reset-password}.php` and `html/partials/dashboard/{manager,rep}.php`
- [x] 1.4 Delete `docs/migrations/`, `docs/sql/plans_and_subscriptions.sql`, `old_requirements.md`, `html/app.php.backup`
- [x] 1.5 Grep for any remaining reference to the deleted paths; verify app loads

### Phase 2 — Remove professional scheduling
- [x] 2.1 Delete `html/partials/professional/`, `html/pro-booking/`
- [x] 2.2 Delete `helpers/professional-*.php`, `helpers/send-professional-reminders.php`
- [x] 2.3 Delete MCP `pro.php`, `pro-tools.php`, `sms.php`, `sms-tools.php`; Retell `pro-webhook.php`; SMS `pro-webhook.php`, `twilio_pro.php`, `text-agent-webhook.php`
- [x] 2.4 Delete REST v1 professional endpoints (`appointments, availability, availability-rules, calendar, clients, profile, services, time-off`); keep `_bootstrap.php`, `auth.php`, `todos.php`
- [x] 2.5 `helpers/openai-sms.php`, `helpers/openai-email.php`: drop the professional require and the location-type tool switch; always use restaurant tools
- [x] 2.6 `html/api/sms/webhook.php`: replace the `professional_clients` sender lookup with a `guests` lookup
- [x] 2.7 `html/api/email/webhook.php`: stop reading `location_type`
- [x] 2.8 Delete `requirements.md`, `docs/mcp-pro-prompt-snippet.md`, `docs/pro-webhook-response.json`, `docs/mobile-api-spec.md`, `docs/sql/professional_scheduling.sql`; fix comment in `sql/multi-language-relay.sql`
- [x] 2.9 Verify: voice/SMS/email agent settings pages, MCP server endpoint

### Phase 3 — Remove the affiliate system
- [x] 3.1 Delete `html/partials/affiliate/`, the 9 `html/partials/platform/affiliate*` / `save-affiliate*` / `update-commission-status.php` files, `helpers/prospect-restaurant.php`, `html/partials/auth/switch-mode.php`
- [x] 3.2 `helpers/auth.php`: remove affiliate session vars, `isAffiliate()`, `currentAffiliateId()`, the `affiliate` role
- [x] 3.3 `html/partials/dashboard/index.php`: remove affiliate lookups, "Become an Affiliate" button, the location-type wrapper around Create Reservation
- [x] 3.4 `html/partials/billing/*` (6 files): drop the `affiliate_id` access term; remove the commission card in `invoice-detail.php`
- [x] 3.5 `html/partials/platform/*`: remove affiliate role/badges/stat card (`users.php`, `platform-user-form.php`, `save-platform-user.php`), affiliate column (`subscriptions.php`, `subscription-form.php`), affiliate price (`products.php`, `product-form.php`, `save-product.php`), commission (`billing-settings.php`, `save-billing-settings.php`), in-setup filter (`restaurants.php`)
- [x] 3.6 `html/cron/generate-invoices.php`: remove commission math
- [x] 3.7 Delete `docs/sql/prospect_products.sql`, `prospect_restaurant_link.sql`, `fix_prospect_restaurant_ids.sql`
- [x] 3.8 Verify: platform pages, billing pages, invoice cron dry run

### Phase 4 — Registration and the main shell
- [x] 4.1 `html/register.php`: remove both "Business Type" selects and the affiliate subtitle
- [x] 4.2 `html/partials/auth/register.php`, `google-complete.php`: drop `location_type` handling and the `affiliates` insert; stop setting `is_affiliate`
- [x] 4.3 `html/app.php`: remove `$isProfessionalMode`/`$isAffiliateMode` and every ternary on them, the professional and affiliate nav sections, PIPELINE label, Affiliate Settings menu item, `professional-modal-container`; footer label to "Restaurant Reservation System"
- [x] 4.4 `helpers/auth.php` `getPermittedNavItems()`: drop the `location_type` parameter and clause
- [x] 4.5 Nav-permissions admin partials: remove the location-type field, filter and column
- [x] 4.6 `html/landing/zozocal/page.php`: remove "Professional Service Scheduling" marketing line
- [x] 4.7 Verify: register a restaurant, log in, walk every remaining nav item

### Phase 5 — Database
- [x] 5.1 `docs/sql/pg_schema.sql`: drop 6 `professional_*` and 6 affiliate/prospect tables with their indexes/triggers
- [x] 5.2 Drop columns: `restaurants.location_type`, `affiliate_id`, `affiliate_commission`; `users.product_type`, `is_affiliate`, `referred_by`, `referral_code`, `user_mode`; `nav_permissions.location_type`, `product_type`; `products.affiliate_price`; `invoices.affiliate_commission_amount`; `client_id`/`prospect_id` on `call_logs`, `sms_message_log`, `email_message_log`, `twilio_numbers`
- [x] 5.3 Fold the still-MySQL `invoicing.sql` tables (`invoice_payments`, `prepay_transactions`, restaurant invoice columns) into `pg_schema.sql` so the schema file is complete
- [x] 5.4 Write a restaurant-only, PostgreSQL-syntax `docs/sql/nav_permissions.sql` seed; delete `migrate_nav_permissions.sql`; fold `todos.sql` / `channel_message_logs.sql` nav rows into it
- [x] 5.5 Rebuild the local database from the new schema and seed; run `/test-db.php`
- [x] 5.6 `database-documentation.md` checked: it documents only the original 17 restaurant tables and never mentioned the removed products, so no change

### Phase 6 — Docs and wrap-up
- [x] 6.1 `README.md`: remove "What should be removed" section, reword "Current state"
- [x] 6.2 `tech-stack.md`: MariaDB/pdo_mysql references → PostgreSQL/pdo_pgsql
- [x] 6.3 Final grep for `professional_`, `affiliate`, `prospect`, `location_type`, `product_type`, `isProfessionalMode`, `Drajeo` across `html/ helpers/ config/ scripts/`
- [x] 6.4 Append to `docs/activity.md`; Review section below

## Review

**Completed 2026-09-21** in six phases, each committed and pushed separately (Phase 0 `19dcc59` … Phase 6).
The application is now a restaurant-reservation-only system. Registration has no business-type
choice; every signup creates a restaurant and makes the user its admin.

### What was removed
| Product | Files | Notes |
|---|---|---|
| SalesCoach Pro (dead) | 147 files, ~27k lines | `models/` (all but `User.php`), 19 partial folders, 12 loose partials, `helpers/functions.php`, `docs/migrations/`, `old_requirements.md` |
| Professional scheduling | 70 files, ~15k lines | partials, `pro-booking/`, 5 helpers, pro MCP + SMS MCP, pro Retell/SMS webhooks, 8 REST v1 endpoints, 6 tables, `requirements.md`, `docs/mobile-api-spec.md` |
| Affiliate system | 60 files, ~4.5k lines | `partials/affiliate/`, 9 platform partials, `prospect-restaurant.php`, `switch-mode.php`, 6 tables, affiliate columns on restaurants/users/products/invoices/nav_permissions/logs |

### Shared code changed
`app.php`, `register.php`, both registration handlers, `helpers/auth.php`, `helpers/restaurant.php`,
`helpers/openai-sms.php`, `helpers/openai-email.php`, SMS + email webhooks, dashboard, 7 billing
partials, 14 platform partials, invoice cron, `retell-create-agent.php`, `retell-assign-phone.php`,
`voice-prompts.php`, landing page copy.

### Database
- `docs/sql/pg_schema.sql`: 61 → 51 tables (12 removed, `invoice_payments` + `prepay_transactions` added). Billing schema folded in. `sms_message_log.guest_id` replaces the professional `client_id`. `restaurants.status` defaults to `active`.
- `docs/sql/nav_permissions.sql`: new PostgreSQL seed, 50 rows, restaurant-only. Load it after the schema.
- Local database rebuilt from these two files. It contains one test account: smoke@example.com (password kept out of the repo) (super-admin, restaurant "Smoke Bistro"). Delete it when real accounts exist.
- `docs/sql/invoicing.sql` converted to PostgreSQL; other files in `docs/sql/` are MySQL-era history and are superseded by `pg_schema.sql`.

### Pre-existing bugs fixed along the way
- Todos page 500: MySQL `FIELD()` → `CASE`.
- Waitlist page 500 and `platform/restaurant-dashboard.php`: MySQL `CURDATE()`/`CURTIME()`/`DATE_SUB()` → PostgreSQL equivalents.
- `platform/restaurants.php` `CURDATE()`.
- Billing pages and invoice cron failed: billing tables/columns had never been converted to PostgreSQL.
- Every new restaurant showed a permanent "In setup" banner (status default).
- "Reservation Rules" nav item linked to a page that never existed — removed.

### Verification
All 33 sidebar targets, the public pages (index, login, register, forgot-password, booking, terms,
privacy, test-db) return 200 as super-admin; registration through the real form works; both crons
run; MCP `tools/list` returns the 7 reservation tools; all tracked PHP files lint clean; no
reference to affiliate/professional/prospect/location_type/product_type remains in code.

### Follow-ups (not done, outside this plan)
- Remaining MySQL-only SQL in PHP (pre-existing, will error on PostgreSQL when hit):
  `ON DUPLICATE KEY UPDATE` in `settings/notifications.php`, `platform/save-platform-user.php`,
  `test-email.php`; `DAYOFWEEK()` in `reports/data.php`; `TIMESTAMPDIFF()` in `helpers/send-reminders.php`.
- The deleted professional MCP had todo tools and `get_good_news`; the restaurant MCP never had
  them. Port to `html/api/mcp/server-tools.php` if the voice agent should manage todos.
- `docs/sql/*.sql` other than `pg_schema.sql`, `nav_permissions.sql`, `invoicing.sql` are still
  MySQL syntax (history only). Could be deleted.
- `logs/` still holds old professional/prospect payloads and tokens (see README security note).
