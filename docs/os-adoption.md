# ZozoCal in the MaluDB Business OS — the adoption record

ZozoCal-Restaurant is being **adopted** by the Business OS kernel (the `os-adopt` route of the
`maludb-os-integration` plugin 0.4.2): it keeps its code, schema and users table, gains the kernel's
sign-on beside them, and hands the management of people and restaurants to the kernel — while the flag
`OS_ENABLED` is on. With the flag off it runs exactly as it does today. Work happens on the branch
`os-adoption`; installing is the kernel's installer (`os-install`), never this branch.

Kernel build plan: phase 7, item **C3**. Owner decisions already made (2026-09-25): each restaurant is a
kernel **location of kind `site`**; the application declares its own roles and a grant is scope + role;
application users are not OS users by default (explicit grant only); standalone mode stays behind
`OS_ENABLED`; **fresh installs only** (no migration of the zozocal.com data); the OpenAI/Retell/Twilio
keys stay in the application for now (their move to the kernel is deferred).

## 1. Survey (2026-09-28)

Code wins over documentation; every answer cites the code.

### 1.1 Stack
- PHP 8.3 (`scripts/check-install.php:28` requires ≥ 8.1; `never` return type in `html/api/v1/_bootstrap.php:16`).
  Page-per-file plus HTMX partials: `html/app.php` is the shell (`requireAuth()` at the top), the nav loads
  `/partials/<area>/<x>.php` into `#page-content`. No router, no rewrites; the REST API routes on PATH_INFO.
- PostgreSQL 17 (`config/database.php:21-36`, DSN `pgsql:`, defaults db/user `zozocal`). **The current schema
  is `docs/sql/pg_schema.sql`** (51 tables) + `docs/sql/nav_permissions.sql` (`docs/sql/README.md`).
  `restaurant_reservations.sql` (MySQL v2.0) and `sql/multi-language-relay.sql` (MySQL, already folded in) are history.
- Config: `app_config()` (`config/app.php:11-24`) — environment, then git-ignored `config/local.php`, then default.
- Hard-coded per server: the default `to_number` `+18473930142` (`html/api/retell/restaurant-webhook.php:50`,
  `webhook-call-end.php:60`), a Retell agent id (`html/call.php:81`, `html/demo.php:580`), `zozocal.com`
  (`helpers/google-auth.php:16`, privacy/terms), addresses in `html/test-email.php:17-19`, `/var/www` paths in
  cron headers (`html/cron/*.php:7-8`, `helpers/send-reminders.php:6`, `scripts/process-voice-messages.php:15`).
- Code that does not match the schema (out of scope here, recorded): `models/User.php` (`users.status`,
  `password`, `orgs`, `password_resets`, `remember_token` — none exist), so **forgot/reset password is broken**;
  `helpers/voice-api.php:245,423,491` insert `activity_log` without the NOT NULL `entity_type`.

### 1.2 Every way into a session
- **The login function:** `login_user($user, $remember)` (`helpers/auth.php:49`). Sets `$_SESSION['user']`
  (`id, email, first_name, last_name, role, is_platform_admin`), `user_id`, `logged_in_at`,
  `current_restaurant_id`, `current_role`, `current_restaurant_name` (first membership; a super-admin with none
  gets the first active restaurant as `admin`), updates `users.last_login_at`.
- **Callers:** password form `html/partials/auth/login.php:52`; Google `html/google-callback.php:110` (login)
  and `:146` (invitation); Google sign-up `html/partials/auth/google-complete.php:136`; registration
  `html/partials/auth/register.php:151`; invitation acceptance `html/partials/auth/accept-invite.php:71`.
- **Remember-me does nothing:** the token is set (`auth.php:94-105`) and never read back.
- **Password reset** does not log in, and is broken (1.1).
- **Other session writers:** only `switchRestaurant()` (`auth.php:126-149`), called from
  `html/partials/auth/switch-restaurant.php` (POST, **no CSRF**). No impersonation.
- **The guard:** `requireAuth()` (`auth.php:157-173`) checks only that `$_SESSION['user']` exists — HTMX gets
  401 + `HX-Redirect: /login.php`, others `Location: /login.php`. Deactivating a user or membership does not
  end an open session. Callers: `requireAuth` 97 files, `requireAdmin` 12, `requireManager` 21,
  `requireSuperAdmin` 30; 135 of 188 PHP files under `html/` carry a guard.
- **Unguarded on purpose:** index, login, register, forgot/reset password, logout, google-callback, privacy,
  terms, sms-signup, landing pages, the auth partials, the public booking pages (`html/booking/*`, looked up by
  slug; confirm/cancel/modify check CSRF), the bearer-token API (`html/api/v1/*`) and MCP (`html/api/mcp/*`).
- **Unguarded gaps:** see 1.6.

### 1.3 People and tenants (`docs/sql/pg_schema.sql`)
- `users` (47-66): `email` UNIQUE, `password_hash` NOT NULL (markers `!INVITED`, `!GOOGLE`), `google_id`,
  `auth_provider`, `role` (`user` | `super-admin`), `is_platform_admin`, `is_active` smallint.
- `restaurants` (17-40): `slug` UNIQUE, `timezone`, `is_active` smallint (code tests `= 1`), SaaS billing columns.
- `user_restaurants` (159-171): `(user_id, restaurant_id)` UNIQUE, `role` ∈ admin | manager | user, `is_active`.
  A user may belong to several restaurants (`helpers/restaurant.php:44-72`).
- **Platform super-admin** (`users.role = 'super-admin'`): every restaurant as admin (`restaurant.php:52-60`,
  `auth.php:135-139`), the platform screens `html/partials/platform/*` (restaurants, users, products,
  subscriptions, nav permissions, billing).
- **Screens that manage people and tenants** (read-only under `OS_ENABLED`): restaurant staff
  `partials/settings/users.php`, `user-form.php`, `save-user.php`, `toggle-user.php` (`helpers/services/team.php`
  58, 136); platform users `partials/platform/users.php`, `platform-user-form.php`, `save-platform-user.php`,
  `toggle-platform-user.php` (`platform.php` 216, 267); restaurants `platform/restaurant-form.php`,
  `save-restaurant.php`, `toggle-restaurant.php` (`platform.php` 87, 174); self-service sign-up creates user +
  restaurant + admin membership (`register.php:81-99`).
- **Current tenant:** `$_SESSION['current_restaurant_id']`, chosen at login, changed by the switcher.

### 1.4 Machine access
- **API keys** (`api_tokens`, SHA-256): `helpers/api/auth.php:30-73` checks expiry, revocation, `users.is_active`
  and an active membership; not the restaurant's `is_active`. `X-Restaurant-Id` lets a super-admin's key act on
  any restaurant (64-68). `/api/v1/auth.php?action=login` issues 90-day keys (rate limit only with APCu).
- **MCP:** `html/api/mcp/server.php` (guest tools; bearer = a restaurant's `settings.mcp_api_key`),
  `html/api/mcp/admin.php` (registry operations; bearer = API key).
- **Webhooks:** Retell custom functions go through `parseRetellRequest` whose **signature check is commented
  out** (`helpers/retell-auth.php:94-101`); `restaurant-webhook.php`, `webhook-call-end.php` have none;
  Twilio `api/sms/webhook.php` has **no `X-Twilio-Signature` check** (`sms_agents.webhook_auth_token` unused);
  `api/email/webhook.php` has **none** (and runs OpenAI + sends mail on the restaurant's keys, 58-100).
- **Cron:** `html/cron/generate-invoices.php`, `mark-overdue.php` are **web-reachable** (no CLI check);
  `docs/deploy/crontab` also runs `helpers/send-reminders.php`, `scripts/process-voice-messages.php`.

### 1.5 What it holds that the kernel should (recorded; the move is deferred by the owner)
- Per-restaurant keys in `settings`: `retell_api_key`, `openai_api_key`, `sms_api_key`/`sms_api_secret`/
  `sms_from_number`, `mailersend_api_key`, `mcp_api_key`. From config: Google OAuth, `RETELL_DEFAULT_API_KEY`.
- Audit log `activity_log` (pg_schema 518) written by `svc_log()` (`helpers/services/_base.php:51-63`) plus
  direct inserts (booking confirm/modify/cancel, Retell webhooks, `voice-api.php`).
- Mail: MailerSend, falling back to `mail()` (`helpers/notifications.php:92-141`). SMS: Twilio REST. Voice: Retell.

### 1.6 Security gaps (fixed before sign-on, one commit each)
| # | Gap | Where |
|---|---|---|
| S1 | **Invitation takeover:** accepting an invitation needs only the email; anyone who knows an invited address sets its password and is signed in | `html/partials/auth/accept-invite.php:51-71` |
| S2 | **Cross-restaurant takeover:** a restaurant admin adding an existing email with a password overwrites that person's global password | `helpers/services/team.php:111-113` |
| S3 | Retell signature check commented out; two Retell webhooks with none; `lookup-reservation` then reveals a reservation by phone | `helpers/retell-auth.php:94-101`, `html/api/retell/*` |
| S4 | Twilio SMS webhook unsigned | `html/api/sms/webhook.php` |
| S5 | Inbound email webhook unsigned (spends the restaurant's OpenAI and mail) | `html/api/email/webhook.php` |
| S6 | One restaurant's Retell key used for every other (`getRetellApiKey()` step 2; four callers pass no restaurant) | `helpers/retell-auth.php:53-60`, `helpers/retell-api.php:23`, `create-web-call.php:74`, `create-demo-call.php:42`, `scripts/process-voice-messages.php:47` |
| S7 | Public diagnostics: `phpinfo()`, the table list, a mail sender that writes settings | `html/info.php`, `html/test-db.php`, `html/test-email.php` |
| S8 | `mcp_api_key` handed out without login | `html/downloads/retell-agent-import.php:38-53` |
| S9 | Public demo pages spend the first restaurant's Retell key | `html/demo.php`, `html/call.php`, `html/api/retell/create-demo-call.php` |
| S10 | Web-reachable cron | `html/cron/*.php` |
| S11 | Restaurant switch without CSRF | `html/partials/auth/switch-restaurant.php` |
| S12 | Secrets and personal data in logs: reset tokens, failed-login emails, raw Retell/SMS/email bodies | `forgot-password.php:134-135`, `reset-password.php:71`, `login.php:45`, `retell-auth.php:81`, `webhook-call-end.php:27`, `create-web-call.php:41`, `sms/webhook.php:41-42`, `email/webhook.php:45-46` |

## 2. Decisions

The owner decided every open item on 2026-09-28, each as recommended.

| Decision | Proposal | State |
|---|---|---|
| Scope kind | `location` — each restaurant is a kernel site | decided 2026-09-25 |
| Catalog key, DNS | `reservations` → `reservations.subello.com`; install path `/srv/apps/reservations`, database `subello_reservations`, internal port `127.0.0.1:8182` | decided 2026-09-28 |
| Business area | Sales & Service | decided 2026-09-28 |
| Roles | the three it has: `admin` "Restaurant admin" (capability `admin`, the one `is_admin`), `manager` "Manager" (`write`), `user` "Staff" (`write`); rights named after what the guards already allow | decided 2026-09-28 |
| How roles reach the kernel | an `app_roles` tool on the existing PHP MCP `html/api/mcp/admin.php`, admitting the kernel's 60-second token to that tool only | decided 2026-09-28 |
| Platform super-admin | the kernel's super-admin is ZozoCal's `super-admin` while `OS_ENABLED` is on; anyone else is `user` | decided 2026-09-28 |
| SaaS billing (products, subscriptions, invoices, prepay, the two billing crons) | left as it is, super-admin only; the billing crons are not installed by the OS deploy | decided 2026-09-28 |
| Model and provider keys | stay per restaurant in `settings` | deferred 2026-09-25 |

## 3. What the adoption owes afterwards (the rest of the contract, not built here)
The activity log shipped to MaluDB, the `mcp_*` views and the two MCP servers (records, activity), the keys
moved to the kernel, an expert agent. Named here so nobody builds substitutes.
