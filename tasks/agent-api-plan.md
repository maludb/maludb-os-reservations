# Plan — Make every admin function available to AI agents via REST API and MCP

Goal: an AI agent holding an API key can do anything a restaurant user, manager, admin or
super-admin can do in the web app, through either the REST API or an MCP server, with the
same role rules as the UI.

(The previous plan, the restaurant-only strip-out, is complete and archived in
`tasks/restaurant-only-plan.md`.)

## What research found

### What exists today

| Surface | Endpoint | Covers | Auth |
|---|---|---|---|
| REST v1 | `html/api/v1/auth.php` | password login → 90-day bearer token, logout | email + password |
| REST v1 | `html/api/v1/todos.php` | list/create/update/delete own todos | bearer token (`api_tokens`) |
| MCP | `html/api/mcp/server.php` + `server-tools.php` | 7 **guest-facing** tools: check availability, make / lookup / cancel / confirm / modify reservation, contact preferences | one shared `mcp_api_key` |
| Retell | `html/api/retell/*.php` | the same 7 guest functions for the voice agent, plus call webhooks | Retell signature |
| SMS / email | `html/api/sms/webhook.php`, `html/api/email/webhook.php` | inbound guest messages → OpenAI agents | Twilio / provider webhooks |

So agents can act **as a guest** (book, cancel) but cannot **administer** anything. Only todos
are available to a staff-level caller, and only over REST, not MCP.

### The gap

Everything else lives only in HTMX partials under `html/partials/`: ~55 POST handlers and
~40 read views. They require a browser session and CSRF token, return HTML, and hold their
business logic inline (raw PDO in the partial). Role checks use `requireAuth()` /
`requireManager()` / `requireAdmin()` / `requireSuperAdmin()`, which read `$_SESSION`.

| Area | UI functions with no API/MCP equivalent |
|---|---|
| Reservations | list / feed / calendar, detail, staff create (table, source, notes, turn-time override), edit, status changes (confirm, seat, complete, no-show, cancel), guest search |
| Tables & sections | list, floor-plan status, create/edit, activate/deactivate, assign walk-in, meal status |
| Waitlist | list, add, status change, notify by SMS, clear, check-in / seat / notify a reservation |
| Guests | list, search, detail + history, create/edit |
| Events | list, detail, create/edit, book |
| Settings | profile, hours, special dates (+ reservations on a date), turn times, meal percentages, notifications, email templates, integrations |
| Team | list users, invite / edit role, activate/deactivate |
| AI agents & phones | phone numbers, voice prompts, text agents, email agents, Retell create-agent / fetch / update prompt / assign phone / sync |
| Insight | dashboard summary, reports, email / SMS / voice message logs |
| Billing | invoices, invoice detail, pay invoice, prepay add / history |
| Platform (super-admin) | restaurants (CRUD, toggle, dashboard), platform users, products & features, subscriptions, account options, nav permissions, billing settings, invoice crons |

Out of scope on purpose: human sign-in flows (login page, register, forgot/reset password,
Google OAuth, accept invite, restaurant switcher). An agent gets a key instead.

### Problems in the existing surfaces (should be fixed as part of this work)

1. **MCP auth is not tenant-scoped.** `server.php:43` reads `mcp_api_key` from *any* restaurant
   (`LIMIT 1`, no `restaurant_id`), so one restaurant's key works for every restaurant's slug.
2. **MCP fails open.** If no restaurant has set a key, every call is allowed (`server.php:51`).
3. **REST login picks the first restaurant** (`auth.php`, `LIMIT 1`); a user in two restaurants,
   or a super-admin, cannot choose which one the token acts on.
4. **No long-lived agent keys.** Tokens come only from a password login; there is no UI to issue,
   name, list or revoke a key, and no `last_used_at`.
5. **No audit trail** for API/MCP writes (`activity_log` exists but nothing writes to it here).

## Proposed approach (simple, one source of truth)

1. **One operation registry.** Each admin action is declared once in `helpers/api/ops/<area>.php`:
   name (`reservations.update_status`), description, minimum role, JSON input schema, and a
   PHP handler returning an array. Both surfaces are generated from it, so REST and MCP can't drift
   apart and a missing operation is a missing entry.
2. **Business logic moves into service helpers** (`helpers/services/<area>.php`), lifted from
   the partial's inline code. The partial then calls the same function and keeps rendering HTML.
   Done one area at a time; the UI output is unchanged.
3. **REST:** a small router `html/api/v1/index.php` (+ `.htaccess` rewrite) serves
   `GET/POST/PATCH/DELETE /api/v1/<area>[/<id>[/<action>]]` from the registry, plus
   `GET /api/v1/openapi.json` generated from the same schemas so agents can discover it.
   `todos.php` and `auth.php` keep working.
4. **MCP:** a new `html/api/mcp/admin.php` (same JSON-RPC style as `server.php`) whose
   `tools/list` returns the registry filtered by the caller's role and whose `tools/call` runs
   the same handler. The guest server `server.php` stays for Retell/SMS/email agents.
5. **Auth:** agent API keys reuse `api_tokens` (add `name`, `last_used_at`, `revoked_at`). A key
   acts as its owning user in one restaurant, with that user's role. Super-admin keys may pick a
   restaurant per call (`X-Restaurant-Id` header / `restaurant_id` argument). The API fills the
   same `$_SESSION` user/role values the UI uses, so the existing `isManager()` etc. apply
   unchanged.
6. **Audit:** every write through REST or MCP adds an `activity_log` row naming the key.
7. **Secrets are write-only:** API keys for Retell/OpenAI/Twilio can be set but are returned masked.

## Decisions I need from you

1. **Service extraction (approach step 2).** Recommended: move logic into service helpers so the
   UI and API share it. The alternative (API handlers copy the SQL from the partials) is less work
   now but duplicates ~55 handlers that will drift. OK to extract?
2. **Paying invoices by agent.** Recommended: agents can read invoices and prepay history but
   `billing.pay_invoice` / `billing.add_prepay` require a human. Or allow them for admin keys?
3. **Separate admin MCP endpoint** (`admin.php`) rather than adding admin tools to the guest
   `server.php`. Recommended, so Retell agents never see admin tools.
4. **Order of work.** Proposed below: foundations, then front-of-house, config, team & agents,
   insight, billing, platform. Change the order if another area matters sooner.

## Tasks

### Phase 0 — Foundations
- [x] Schema: add `name`, `last_used_at`, `revoked_at` to `api_tokens` in `docs/sql/pg_schema.sql` and apply to the local DB
- [x] `_bootstrap.php`: reject revoked keys, record `last_used_at`, support `X-Restaurant-Id` for super-admins, fill the session role context
- [x] `auth.php` login: accept an optional `restaurant_id`
- [x] Registry + dispatcher: `helpers/api/registry.php` (declare op, role check, input validation against the schema, audit log)
- [x] REST router `html/api/v1/index.php` (PATH_INFO routing, no `.htaccess` — Apache has `AllowOverride None` and no mod_rewrite); `GET /api/v1/index.php/openapi.json`
- [x] Admin MCP server `html/api/mcp/admin.php` (initialize, tools/list by role, tools/call)
- [x] Settings → **API Keys** page (admin): create (show key once), list, revoke; nav permission row; unique div ids
- [x] Move todos into the registry (brings todos to MCP) and keep `todos.php` as-is
- [x] Smoke script `scripts/api-smoke.php`: calls every op through REST and MCP with manager and staff keys

### Phase 1 — Fix existing MCP/REST problems
- [x] Guest MCP: look up the key per restaurant, reject slugs from a different restaurant
- [x] Guest MCP: fail closed when no key is configured
- [x] Log guest-MCP writes to `activity_log`
- [x] (found while testing) `retell-create-agent.php` now creates the restaurant's MCP key if missing and sends it to Retell; `helpers/availability.php` `TIME_TO_SEC` ported to PostgreSQL (availability was failing everywhere)

### Phase 2 — Front-of-house (staff role and up)
- [x] Reservations: `list` (date range, status, search), `get`, `create`, `update`, `update_status`, `check_availability`
- [x] Tables: `list`, `floor_status`, `create`, `update`, `set_active`, `assign_walkin`, `update_meal_status`
- [x] Sections: `list`, `create`, `update`, `set_active`
- [x] Waitlist: `list`, `add`, `update_status`, `notify`, `clear`, `checkin_reservation`, `seat_reservation`, `notify_reservation`
- [x] Guests: `list`, `search`, `get` (with history), `create`, `update`
- [x] Events: `list`, `get`, `create`, `update`, `book`

### Phase 3 — Restaurant configuration (manager and up)
- [x] `settings.profile` get/update; `hours` get/update
- [x] `special_dates` list/create/update + reservations on a date
- [x] `turn_times` list/save; `meal_percentages` get/update
- [x] `notifications` get/update; `email_templates` list/update
- [x] `integrations` get (secrets masked) / update (admin)

### Phase 4 — Team, phone numbers and AI agents (admin)
- [x] `users` list / invite / update role / set_active
- [x] `phone_numbers` list / create / update / delete
- [x] `voice_prompts` list / get / save / delete / sync; Retell `create_agent`, `fetch_agent`, `update_prompt`, `list_unassigned_phones`, `assign_phone`, `prompt_templates`
- [x] `text_agents` and `email_agents` list / get / save / delete

### Phase 5 — Insight (read-only)
- [x] `dashboard.summary`; `reports.data` (same filters as the Reports page)
- [x] `messages.email_log`, `messages.sms_log`, `messages.voice_log` (paged)
- [x] `activity_log.list` (so an agent can review what agents did)

### Phase 6 — Billing (admin)
- [x] `invoices` list / get; `prepay.history`
- [x] ~~`invoices.pay` and `prepay.add`~~ — not added (decision 2: payments need a person)

### Phase 7 — Platform administration (super-admin keys)
- [x] `platform.restaurants` list / get / dashboard / create / update / set_active
- [x] `platform.users` list / create / update / set_active
- [x] `platform.products` and `product_features` CRUD; `subscriptions` list / create / update
- [x] `platform.account_options` CRUD / toggle; `nav_permissions` CRUD; `billing_settings` get/update
- [x] `platform.run_invoice_job` (generate invoices, mark overdue)

### Phase 8 — Documentation and keeping the gap closed
- [x] `docs/api.md`: auth, key creation, REST conventions, MCP connection (Claude / other clients), error codes
- [x] Parity check in `scripts/api-smoke.php`: list every POST handler in `html/partials/` that no operation claims, so new UI features without an API show up
- [x] Update `README.md` and `tech-stack.md`

## Review

**Decisions taken (approved plan, recommendations accepted):** shared service helpers; billing read-only for agents; separate admin MCP endpoint; phase order as written.

### What was built
- **One operation registry** (`helpers/api/registry.php`, `helpers/api/ops/*.php`): **145 operations** across 14 areas. The REST router (`/api/v1/index.php/<path>`), the admin MCP server (`/api/mcp/admin.php`) and the OpenAPI document (`/api/v1/index.php/openapi.json`) are all generated from it, so they cannot drift.
- **Service layer** (`helpers/services/*.php`): the write logic of 60 HTMX handlers was moved out of the partials; the partials now call the same functions as the API. HTML output and div ids are unchanged.
- **API keys:** Settings → API Keys (create/shown once/revoke); a key acts as its owner with the owner's role; super-admin keys can target any restaurant with `X-Restaurant-Id`; revoked, expired or deactivated-in-restaurant users are refused.
- **Audit:** every REST/MCP write adds an `activity_log` row (`api_call`) naming channel, operation and key, secrets removed.
- **Roles:** staff 45 operations, manager 71, admin 111, super-admin all 145. Secrets are write-only.
- **Parity guard:** `php scripts/api-smoke.php` fails if any POST handler in `html/partials/` is not mapped in `scripts/api-parity.php` (to operations or to an exemption reason); it also checks REST/MCP/OpenAPI agree, role filtering, 401/403, and calls every read operation over both surfaces. **Current result: all checks pass.**
- Docs: `docs/api.md` (auth, conventions, errors, examples, connecting Claude, how to add a feature); README and tech-stack updated.

### Exempt on purpose (9 handlers)
Human sign-in flows (login, register, password reset, Google, accept invite, restaurant switch), API-key management (a leaked key must not mint keys), and paying invoices / adding prepayments.

### Existing bugs found and fixed along the way
- Bearer `Authorization` header never reached PHP under mod_php, so the old `/api/v1/todos.php` could never authenticate.
- Guest MCP (`server.php`) accepted any restaurant's key for every restaurant and was open when no key was set; now tenant-bound and fail-closed.
- PostgreSQL migration leftovers: availability (`TIME_TO_SEC`, broke all booking/voice availability), Reports page (`HOUR`/`DAYOFWEEK`, bad column), guest tag filter (`FIND_IN_SET`), notifications save and platform user save (`ON DUPLICATE KEY`), platform toggles (`NOT` on SMALLINT), create product (placeholder count), create restaurant (wrong turn_times columns), text agents (missing `system_prompt`/`custom_webhook` columns — added to schema), duplicate-agent detection (MySQL error text).
- `waitlist/clear.php` ignored a failed CSRF check and wrote a non-existent column; `waitlist/notify-reservation.php` could never send SMS.
- Cross-tenant: deleting/saving a voice/text/email agent could wipe another restaurant's prompt variables.
- New guards: last active admin cannot be demoted/deactivated; super-admin cannot drop own role; time zone / email validation.

### Needs your attention
1. **Live Retell key hardcoded** in `helpers/retell-auth.php` (`getRetellApiKey()` fallback). Local test runs reached the real Retell account (a sub-agent created 2 LLMs + 1 agent and deleted them again). Recommend removing the fallback and rotating the key — not changed here because restaurants without their own key may rely on it.
2. **Existing Retell voice agents** configured without the restaurant's MCP key will now get 401 from `/api/mcp/server.php`; re-create them (Settings → Voice Prompts) or add the key header. `retell-create-agent` now also sends `id: restaurant_mcp` for the MCP entry (Retell rejected it without) — **untested against Retell** to avoid creating more real resources.
3. `retell_unassigned_phones` lists every number in the shared Retell account (same as the existing page).
4. ~~`tables.status = 'occupied'` is never reset~~ — **fixed 2026-09-25** (see follow-up below). Noted, unchanged: "Complete & Open Table" doesn't bump guest `visit_count`; remaining MySQL-only SQL in `helpers/send-reminders.php` (`TIMESTAMPDIFF`) and `html/test-email.php`.
5. Local test data left: guests, events, sections "Patio Test"/"UI Section", tables T1/T2/P1/UI1, some reservations/waitlist rows. Test API keys are revoked; the test guest-MCP key was removed.

### Follow-up 2026-09-25 — free the table's own status when a party leaves
- [x] `svc_table_release()` in `helpers/services/tables.php`: sets an `occupied` table back to `available` (a `blocked` table is left alone; skipped if another party is still seated there)
- [x] Called from "Open Table" (`svc_table_complete`) and from marking a seated reservation completed (`svc_reservation_set_status`)
- [x] Verified: seat from waitlist → `occupied`; Open Table (UI handler) → `available`; completed via API → `available`; blocked table stays `blocked`

Correction to the earlier note: the floor plan was never affected (it derives status from reservations), and the waitlist page's "available tables" query is computed but never displayed. The stale value showed on the dashboard's occupied-tables count, the Tables list badge, the table edit form and the API's table status.

