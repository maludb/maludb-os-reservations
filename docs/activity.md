# Activity Log

## 2026-05-17 — MySQL to PostgreSQL Database Migration

**Prompt:** "We want to port the database to a PostgreSQL database using our new MaluDB extensions. There is a file html/pdo-test.php that connects to the PostqreSQL database successfully. Please update the codebase and migrate the database to the zozocal database on the new server."

### Changes Made

1. **config/database.php** — Updated DSN from `mysql:` to `pgsql:`, host from `localhost` to `192.168.100.163`, port `3306` → `5432`, updated password, removed `MYSQL_ATTR_INIT_COMMAND` and `charset=utf8mb4`

2. **docs/sql/pg_schema.sql** — Created comprehensive PostgreSQL schema (1629 lines, 61 tables, 37 triggers) converted from MySQL:
   - `AUTO_INCREMENT` → `SERIAL`
   - `INT UNSIGNED` → `INTEGER`
   - `TINYINT(1)` → `SMALLINT` (preserves 0/1 PHP compatibility)
   - `ENUM(...)` → `VARCHAR(50)` + CHECK constraints
   - `ON UPDATE CURRENT_TIMESTAMP` → trigger function `update_updated_at()`
   - Removed `ENGINE=InnoDB`, `CHARSET`, `COLLATE` clauses
   - Inline indexes → `CREATE INDEX` statements

3. **PHP query fixes** (4 files):
   - `html/partials/platform/save-account-option.php` — `ON DUPLICATE KEY UPDATE` → `ON CONFLICT DO UPDATE`
   - `html/partials/platform/users.php` — `GROUP_CONCAT` → `STRING_AGG`
   - `models/Activity.php` — `DATE_SUB(NOW(), INTERVAL)` → PostgreSQL interval syntax
   - `html/partials/affiliate/setup-prospect-company.php` — `INSERT IGNORE` → `ON CONFLICT DO NOTHING`

4. **Data migration** — 1,640 rows migrated across all 61 tables with zero errors

5. **Verification** — All tests passed: PDO connection, JOINs, STRING_AGG, boolean columns, updated_at triggers, lastInsertId

---

## 2026-04-09 — Add get_good_news Tool to Pro MCP Server

**Prompt:** "I want to create an MCP server for our voice agent to use when the goal of the voice agent is to give good news. Please do this as a tool to pro.php."

### Changes Made
- Added `get_good_news` tool definition to `getProToolDefinitions()` in `html/api/mcp/pro-tools.php`
- Added executor case in `executeProTool()` for the new tool
- Added `proMcpGetGoodNews()` handler function that queries the `good_news` table
- Tool accepts `booking_slug` (required), optional `category` filter, and optional `limit` (default 5, max 20)
- Returns active good news articles ordered by most recently fetched

## 2026-03-30 — Fix: Prospect-to-Restaurant Creation Not Working

**Prompt:** "When I'm on partials/affiliate/prospect-detail.php?id=23 for a prospect in 'new' status and move it to in-setup, I expect a record for the prospect to be added to 'restaurants' and a record added to 'user_restaurants' for my id with admin role."

### Root Cause
In `save-prospect.php` line 92, new prospects were created with `restaurant_id = currentRestaurantId()` (the affiliate's own restaurant ID). This pre-populated `restaurant_id` caused `createRestaurantFromProspect()` to skip restaurant creation since it checks `empty($fullProspect['restaurant_id'])`.

### Changes Made
- **`html/partials/affiliate/save-prospect.php`**: Changed `currentRestaurantId()` to `null` when inserting new prospects so `restaurant_id` starts empty and gets properly set by `createRestaurantFromProspect()`.
- **`docs/sql/fix_prospect_restaurant_ids.sql`**: Migration to clear incorrect `restaurant_id` values on existing prospects that point to affiliate-type restaurants instead of actual prospect restaurants.

## 2026-03-30 — Personal Todo List Feature

**Prompt:** "We need to add a 'todo list' feature into the calendar application please review the codebase and come up with a plan to add a personal todo list in the front end application, the api, and have related tools in the MCP server."

### Changes Made
- **Database schema** (`docs/sql/todos.sql`): Created `todos` table (restaurant_id, user_id, title, description, due_date, priority, status, sort_order, completed_at) plus nav_permissions inserts for all location types.
- **Sidebar navigation** (`html/app.php`): Added "Todo List" nav item with `feather-check-square` icon in both professional mode (after Calendar) and restaurant mode (after Events).
- **Frontend UI** (5 files in `html/partials/todos/`):
  - `list.php` — Main view with filter tabs (All/Pending/In Progress/Completed), sort controls (Due Date/Priority/Newest), overdue highlighting, inline status toggle, edit/delete actions.
  - `form.php` — Add/Edit inline form with title, description, due date, priority fields.
  - `save.php` — Create/update POST handler with CSRF validation and ownership checks.
  - `update-status.php` — Quick status toggle (pending → in_progress → completed → pending).
  - `delete.php` — Delete handler with ownership verification.
- **REST API** (`html/api/v1/todos.php`): Full CRUD endpoint — GET (list with filters), POST (create), PUT (update), DELETE — using token auth via `_bootstrap.php`.
- **MCP tools** (`html/api/mcp/pro-tools.php`): Added 4 tools — `list_todos`, `create_todo`, `update_todo`, `complete_todo` — with handler functions that resolve booking_slug → restaurant context.

## 2026-03-27 — Retell Voice Agent API Integration (Planning)

**Prompt:** "In this session our goal is to be able to create and modify Retell voice agents using the Retell API so let's start planning the process. All Retell voice agents we are creating are going to have the same format and be single prompt voice agents. The API will be used in partials/settings/voice-prompts.php to create and update the Retell voice agents. Each agent will use the end_call, transfer_call, and agent_transfer Retell function. They will have both agent level and phone number level web hooks and will use MCPs."

### Research & Planning
- Reviewed Retell API documentation (two-layer architecture: Agent + Retell LLM)
- Analyzed existing voice-prompts.php, save-voice-prompt.php, retell-auth.php, and pro-webhook.php
- Wrote comprehensive build plan to `tasks/todo.md` covering 6 phases

### Changes Made

**Phase 1 — `helpers/retell-api.php` (new)**
- `retellApiCall()` — Base cURL function with Bearer auth, JSON encoding, error handling
- CRUD functions: `retellCreateLlm`, `retellUpdateLlm`, `retellGetLlm`, `retellCreateAgent`, `retellUpdateAgent`, `retellGetAgent`, `retellListAgents`, `retellDeleteAgent`, `retellDeleteLlm`
- `retellLanguageCode()` — Maps short codes (en) to Retell locale codes (en-US)
- `retellBuildTools()` — Builds the tools array (end_call always, transfer_call/agent_swap conditional)
- `retellBuildLlmPayload()` — Builds full LLM config from DB row (prompt, tools, MCP, dynamic vars)
- `retellBuildAgentPayload()` — Builds Agent config from DB row (voice, language, webhooks)

**Phase 2 — `docs/sql/retell_agent_columns.sql` (new)**
- ALTER TABLE adding 13 columns to `restaurant_prompts`: retell_llm_id, voice_id, voice_model, model, agent_prompt, begin_message, transfer_number, agent_swap_id, webhook_url, inbound_webhook_url, mcp_url, mcp_headers, is_synced

**Phase 3 — `html/partials/settings/voice-prompts.php` (updated)**
- Reorganized form into collapsible sections: Prompt & Model, Functions, Webhooks, MCP, Custom Variables
- Added fields: model select, voice_id, voice_model, agent_prompt, begin_message, transfer_number, agent_swap_id, webhook_url, inbound_webhook_url, mcp_url, mcp_headers
- Changed editPrompt() to accept a single JSON object instead of many positional args
- Agent cards now show: Synced/Not Synced badge, LLM ID, model, voice, Sync button
- Submit button changed to "Save & Sync to Retell"

**Phase 4 — `html/partials/settings/save-voice-prompt.php` (updated)**
- Persists all new fields to database
- After DB save, builds Retell LLM + Agent payloads and calls API
- Create flow: Creates LLM first, stores llm_id, then creates Agent, stores agent_id
- Update flow: Updates existing LLM then Agent
- Sets is_synced flag based on API success
- Shows combined success/warning message with sync details

**Phase 5 — `html/partials/settings/sync-voice-prompt.php` (new)**
- Manual sync endpoint triggered by the refresh button on each agent card
- Same create/update logic as save handler but reads from existing DB row
- HTMX integration with feedback and list refresh

## 2026-03-26 — Add Polish and Romanian to language dropdowns

**Prompt:** "In all language dropdowns make sure Polish and Romanian are in every dropdown."

### Changes Made
- **`html/partials/settings/voice-prompts.php`** — Added Polish (pl) and Romanian (ro) to the `<select>` dropdown and the `languageMap` JS object
- **`html/partials/settings/phone-numbers.php`** — Added Polish (pl) and Romanian (ro) to the `<select>` dropdown and the `phoneLangMap` JS object

## 2026-03-26 — Fix location switcher for affiliate users

**Prompt:** "I do not see the Location switcher at the top and whenever there is more that one restaurant to switch to, the main 'affiliate' location needs to be in the switcher."

### Root Cause
1. `createRestaurantFromProspect()` stored the **user** ID in `restaurants.affiliate_id` instead of the affiliate **restaurant** ID
2. `getUserRestaurants()` only returned restaurants directly linked via `user_restaurants`, not restaurants belonging to the user's affiliate

### Changes Made
- **`helpers/prospect-restaurant.php`** — Changed `restaurants.affiliate_id` to use `$prospect['affiliate_id']` (the affiliate restaurant ID) instead of `$affiliateUserId` (the user ID)
- **`helpers/restaurant.php`** — Updated `getUserRestaurants()` to also include restaurants where `restaurants.affiliate_id` matches any affiliate restaurant the user belongs to. The affiliate location itself always appears in the list.
- **Database** — Fixed `restaurants.affiliate_id` for restaurants 14-18 (was user ID 9, now restaurant ID 8). Fixed `prospects.affiliate_id` for old prospects (was 4, now 8). Reset prospect #12's `restaurant_id` and re-created its restaurant properly.

## 2026-03-26 — Add prospect location to topnav switcher on in-setup

**Prompt:** "In /partials/affiliate/prospect-detail.php when the prospect status is 'in-setup' the restaurant / location needs to be added to the location switcher in the topnav."

### Changes Made
- **`html/partials/affiliate/update-prospect-status.php`** — When a restaurant is auto-created during status change to `in_setup`, trigger a full page reload (`HX-Redirect`) so the topnav location switcher re-renders with the new restaurant
- **`html/partials/affiliate/prospect-detail.php`** — Added "Switch to Location" button when prospect is `in_setup` and has a `restaurant_id`, allowing quick switch to that location

## 2026-03-26 — Fix prospect not appearing in list & missing restaurant_id

**Prompt:** "The last prospect I created did not throw an error but is not in the list and the restaurant_id in the record is null. It should be the id of the current restaurant location."

### Changes Made
- **`html/partials/affiliate/prospects.php`** — Changed line 27 from `$restaurant['affiliate_id']` (always 0 for affiliate locations) to `currentAffiliateId()` so the list query matches the correct affiliate_id
- **`html/partials/affiliate/save-prospect.php`** — Added `restaurant_id` to the INSERT, set to `currentRestaurantId()`
- **Database** — Fixed existing prospect #12: set `restaurant_id = 19`

## 2026-03-26 — Fix "Affiliate Account Required" for affiliate locations

**Prompt:** "If I'm logged in as a user with admin access and restaurants.location_type='affiliate' When I try to add a prospect I get 'Affiliate Account Required'"

### Changes Made
- **`helpers/auth.php`** — Fixed `currentAffiliateId()`: affiliate locations have `affiliate_id = 0` because they ARE the affiliate. The function now checks `location_type = 'affiliate'` and returns the restaurant's own `id` in that case, instead of looking for a separate `affiliate_id` value.

## 2026-03-26 — Multi-Domain Support (zozocal.com + app.zelable.com)

**Prompt:** "We want this application to run off two domains zozocal.com and app.zelable.com. The app should operate identically for both." / "The app.zelable.com landing page should be the zozocal login page."

### Changes Made
- **`config/google-oauth.php`** — Removed hardcoded `GOOGLE_REDIRECT_URI` constant; replaced with a comment noting both domains must be registered in Google Cloud Console
- **`helpers/google-auth.php`** — Added `google_redirect_uri()` function that dynamically builds the OAuth redirect URI from `$_SERVER['HTTP_HOST']`; updated `google_auth_url()` and `google_exchange_code()` to use it
- **`docs/sql/landing_page_routes.sql`** — Added INSERT for `app.zelable.com` → `login.php`
- **`html/index.php`** — Added check: if the landing route points to `login.php`, redirect to `/login.php` instead of including it (so login.php handles its own session/CSRF/OAuth init)
- **Database** — Inserted `app.zelable.com` route into live `landing_page_routes` table

### Note
User must add `https://app.zelable.com/google-callback.php` as an authorized redirect URI in Google Cloud Console for Google sign-in to work on the new domain.

## 2026-03-22 — SMS MCP Server

**Prompt:** "Next we need to create an mcp server like /api/mcp/pro.php but for sms text messaging. One critical difference is we can only take appointments from known clients. We are also using the receiving phone number not the slug to identify the company. and we are using from_number to identify the client."

**Changes:**
- Created `html/api/mcp/sms-tools.php` — Tool definitions and executor using `to_number`/`from_number` instead of `booking_slug`, with known-client-only gate on booking actions. Resolves phones to slug via `restaurant_phone_numbers` → `professional_profiles`, then delegates to existing `proVoice*` functions.
- Created `html/api/mcp/sms.php` — MCP JSON-RPC 2.0 server endpoint for SMS agents, same auth/structure as `pro.php`, logs to `logs/mcp-sms.log`

## 2026-03-21 — Text Agent Context Webhook

**Prompt:** "We need to create a webhook that returns data from the database to the text messaging agent. It will be passed a payload with from_number, to_number, message, agent_id. The to_number is used to find the restaurant_id from the restaurant_phone_numbers table by phone_number. Return language_name, restaurant data, and professional_clients data if a matching record exists."

**Changes:**
- Created `html/api/sms/text-agent-webhook.php` — New webhook that accepts JSON POST payload, looks up restaurant by to_number via restaurant_phone_numbers, returns language_name, business info, client info (if matched by from_number), and SMS conversation history

## 2026-03-20 — Invoicing & Payment System

**Prompt:** "We need to start to build the invoicing and payment part of the system. We want the user to be able to prepay money on account that gets applied to invoice and we want them to be able to pay individual invoice. We need the following on the restaurants table, day of month invoice is created, the invoice line description (everything is bundled so there will only be one line), monthly price, affiliate commission. Then we need a table to store invoices, a cron job to create invoices on the correct day of month. And then a way to add to the prepaid balance and a way to pay invoices directly."

**Changes:**
- Created `docs/sql/invoicing.sql` — Full database migration: ALTER TABLE restaurants (4 new billing columns), CREATE TABLE invoices, invoice_payments, prepay_transactions, plus nav_permissions entries
- Created `html/cron/generate-invoices.php` — Daily cron job that creates invoices on each restaurant's configured day, auto-applies prepay balance
- Created `html/cron/mark-overdue.php` — Daily cron job that marks unpaid invoices past due date as overdue
- Created `html/partials/billing/invoices.php` — Invoice list with summary cards, status filter tabs, and action buttons
- Created `html/partials/billing/invoice-detail.php` — Single invoice view with line item, payment history, commission info
- Created `html/partials/billing/add-prepay.php` — Form to deposit funds to prepay balance
- Created `html/partials/billing/save-prepay.php` — Handler for prepay deposits
- Created `html/partials/billing/prepay-history.php` — Prepay transaction history with running balance
- Created `html/partials/billing/pay-invoice.php` — Payment form with prepay/direct options
- Created `html/partials/billing/process-payment.php` — Payment processor that handles prepay deduction and invoice status updates
- Created `html/partials/platform/billing-settings.php` — Platform admin billing config (list + edit form per restaurant)
- Created `html/partials/platform/save-billing-settings.php` — Save handler for billing config
- Updated `html/app.php` — Added BILLING nav section and Platform Billing Config link
- User needs to run `docs/sql/invoicing.sql` and install crontab entries

## 2026-03-19 — Dashboard Billing Cards for Billing User / Affiliate

**Prompt:** "If a user is the billing_id for the company or the affiliate setting up the company, the main dashboard needs to have three cards across the top. A welcome card that shows the user's name and last login, one for Prepay Balance, and one for Unpaid invoices."

**Changes:**
- Updated `html/partials/dashboard/index.php` — Added conditional billing cards row at top of dashboard
- Shows for users who are either the `billing_user` on the restaurant or the affiliate who set up the company
- Welcome card: user's first name + last login timestamp
- Prepay Balance card: reads from `restaurants.prepay_balance` column
- Unpaid Invoices card: counts from `invoices` table (gracefully handles missing table)
- User needs to add `prepay_balance` column to restaurants table and create `invoices` table

## 2026-03-19 — Affiliate Users Management in Pipeline Sidenav

**Prompt:** "In /partials/affiliate/prospect-detail.php a user.product_type='affiliate' needs to have a button in the left sidenav in the PIPELINE section under 'Client' and before 'Products' called 'Users' that displays all of the users that have been invited for the company he is working on. It needs to show the user and their invitation status. They need to be able edit or cancel the invitation and mark the user as the one responsible for the invoices and payments for the company. I added a column to the restaurants table that identifies who should have access to invoices and making payments."

**Changes:**
- Added `nav-affiliate-users` nav item in `html/app.php` between "Clients" and "Products" in the PIPELINE section
- Created `html/partials/affiliate/users.php` — Users list page grouped by company, showing name, email, phone, role, invitation status (Pending Invite/Active/Removed), and billing contact badge
- Created `html/partials/affiliate/edit-client-user-form.php` — Modal to edit user name, phone, and role
- Created `html/partials/affiliate/update-client-user.php` — Save handler for user edits
- Created `html/partials/affiliate/cancel-client-user.php` — Deactivates user_restaurants row and clears billing_user if needed
- Created `html/partials/affiliate/set-billing-user.php` — Sets billing_user column on restaurants table
- User needs to run SQL to add nav_permissions entry for `nav-affiliate-users`

## 2026-03-19 — SMS Signup Page & Make a Reservation Nav Link

**Prompt:** "On the landing page in the top navigation we need a link that says make a reservation and pops up a new page that is a 'Register for ZozoCal' page that shows a page with the simple design of terms.php. On the page is the text 'Opt in to SMS Message' and a form with First Name and Phone number. The top button is 'Sign up for SMS' in red and the bottom button is 'No thanks'. Below the form is the disclaimer text about SMS consent. Also add links to privacy policy and terms. After clicking either button, reload index.html."

**Changes:**
- Created `html/sms-signup.php` — New SMS opt-in page styled like terms.php with navbar, hero, form (First Name + Phone), red "Sign up for SMS" button, "No thanks" button, legal disclaimer, and privacy/terms links. Both buttons redirect to `/`.
- Updated `html/landing/zozocal/page.php` — Added "Make a Reservation" link to the top navbar.
- Updated `html/landing/default/coming-soon.php` — Added "Make a Reservation" button to the actions area.

## 2026-03-18 — Professional Dashboard Setup Checklist

**Prompt:** "On dashboard.php if the restaurant status='in-setup' hide id='professional-dashboard-stats-row' and id='professional-dashboard-content-row' and add a new row for setup checklist. That row should have the following items and show if they are complete: Setup Company Profile, Setup Phone Numbers, Setup Email, Add Agents, Add Services, Setup Availability, Invite Users."

**Changes:**
- `html/partials/professional/dashboard.php` — When restaurant status is `in-setup`, the stats row and content row are hidden and replaced with a setup checklist card containing 7 items
- Each item queries the database to determine completion: `professional_profiles`, `restaurant_phone_numbers`, `email_agent_prompts`, `restaurant_prompts`, `professional_services`, `professional_availability_rules`, `user_restaurants`
- Items show green check (complete) or yellow circle (not started), with a link to the relevant setup page
- A progress bar at the bottom shows overall completion percentage (e.g. "3 of 7 complete — 43%")

## 2026-03-18 — Restaurant Status & Affiliate ID in Prospect Upgrade Flow

**Prompt:** "I added a status column and an affiliate_id column to the restaurants table. When the record is inserted into the restaurants table as part of the prospect upgrade process I want the status to be added as in-setup and the user_id of the logged in user added to the affiliate_id column. When the prospect is moved to active also update the status in restaurants to active. If the status of the current restaurant is not active, display the status in the header in a highlighted banner."

**Changes:**
- `helpers/prospect-restaurant.php` — Added `status='in-setup'` and `affiliate_id` (logged-in user_id) to the restaurant INSERT
- `html/partials/affiliate/setup-prospect-company.php` — Updates restaurant status to `active` when prospect completes company setup (moves to client)
- `html/partials/affiliate/update-prospect-status.php` — Updates restaurant status to `active` when prospect status changes to client
- `html/app.php` — Loads restaurant `status` column; displays a yellow warning banner below the header when status is not `active`, showing the current status (e.g. "In Setup")

## 2026-03-18 — Invite Client User Button on Prospect Detail

**Prompt:** "In prospect-detail.php if the status is in-setup add a button 'Invite Client User' to the header that allows the user to invite a user to the company following the process used by admin accounts to add users."

**Changes:**
- Added "Invite Client User" button to prospect-detail.php header, visible when status is `in_setup` and the company has been created (`restaurant_id` exists)
- Created `invite-client-user-form.php` — modal form mirroring the admin user-form.php (fields: name, email, phone, role, password)
- Created `save-client-user.php` — handler mirroring admin save-user.php, links new/existing users to the prospect's restaurant with the selected role; uses `!INVITED` password marker when no password is set

## 2026-03-18 — Padding & Text Changes

**Prompts:**
- "The page partials/professional/client-detail.php needs padding around the edges. Please increase padding by 20px."
- "In prospect-detail.php find and change 'This will create a restaurant account you can switch to.' to 'This will create a company account you can switch to.'"

**Changes:**
- Added 20px padding to professional client detail page wrapper div
- Changed "restaurant account" to "company account" in prospect detail setup confirmation

## 2026-03-18 — SMS Webhook + Client Linking + Communication History

**Prompt:** "Next we need to create a webhook for incoming SMS text messages. The webhook needs to match the company by recipient phone number and identify the client based on the from_number. The log tables need to have both the restaurant_id and the id from professional_clients and when a system user views a professional client in the system, they need to be able to see all communications with the client."

**Changes:**
- Created SQL migration `docs/sql/channel_logs_client_id.sql` adding `client_id` column + index to `sms_message_log`, `email_message_log`, and `call_logs`
- Updated `html/api/sms/webhook.php`: matches company via `restaurant_phone_numbers` first (then text_agent_prompts, then settings fallback), identifies client by normalizing from_number against `professional_clients.phone`, logs both inbound and outbound messages to `sms_message_log` with restaurant_id, client_id, phone_number_id
- Updated `html/partials/professional/client-detail.php`: added Communications History section that queries all three log tables (SMS, voice, email) by client_id and displays a unified timeline sorted by date
- Updated `tasks/todo.md` with plan and review

---

## 2026-03-18 — Channel Message Logs (SMS, Voice, Email)

**Prompt:** "Next we need the infrastructure to log sms text messages sent and received from a company. For all location_types we want to add SMS Messages to the SCHEDULING section of the sidenav under calendar. We also need to create the table that tracks all inbound and outbound messages. While we are at it we also need to do the same for the other channels, voice and email."

**Changes:**
- Created SQL migration `docs/sql/channel_message_logs.sql` with `sms_message_log` and `email_message_log` tables plus nav_permissions for all 3 channels across all location_types
- Added SMS Messages, Voice Calls, Email Messages nav items to SCHEDULING section in `html/app.php` for both professional mode (after Calendar) and restaurant mode (after Events)
- Created `html/partials/messages/sms-log.php` — paginated SMS log with direction/search filters
- Created `html/partials/messages/voice-log.php` — paginated voice call log reading from existing `call_logs` table
- Created `html/partials/messages/email-log.php` — paginated email log with direction/search filters
- Updated `tasks/todo.md` with plan and review

---

## 2026-03-18 — Company Phone Numbers

**Prompt:** "Next we need to have the ability for a single company, represented in the restaurants table to have multiple phone numbers. Please create a table to track phone number attached to a company and associate a default language with each number. Please create the necessary tables and add 'Phone Number' to the left sidenav for all restaurant.location_types."

**Changes:**
- Created SQL migration `docs/sql/restaurant_phone_numbers.sql` with `restaurant_phone_numbers` table (id, restaurant_id, phone_number, label, language_code, language_name, is_primary, is_active, timestamps) and nav_permissions inserts for all location_types
- Added `nav-phone-numbers` nav item to SETTINGS section in `html/app.php` with feather-phone icon
- Created `html/partials/settings/phone-numbers.php` — list + inline add/edit form (HTMX, card-based, same pattern as Voice Agents)
- Created `html/partials/settings/save-phone-number.php` — create/update handler with primary flag management
- Created `html/partials/settings/delete-phone-number.php` — delete handler with ownership check
- Updated `tasks/todo.md` with plan and review

## 2026-03-18 — Multi-Language Voice Agent Relay (Professional)

**Prompt:** "I want to add multi language capabilities. I want a business to have more than one phone number each speaking a different language. If the customer speaks english and the business speaks spanish I want the ability to have the customer leave a message in english, translate it into Spanish and generate an outbound call from the Spanish agent to deliver the message."

**Actions:**
- Designed database schema: ALTER `restaurant_prompts` to add `language_code`, `language_name`, `is_primary_language`; CREATE `voice_messages` relay queue table
- Updated `html/partials/settings/voice-prompts.php` — added language dropdown (11 languages + Other), primary language checkbox, language badges on agent cards
- Updated `html/partials/settings/save-voice-prompt.php` — persists language fields, clears other agents' primary flag when one is marked primary
- Updated `html/api/retell/pro-webhook.php` — passes language dynamic vars on inbound; detects language mismatch on call_ended and queues voice_messages for relay; handles outbound delivery confirmation
- Created `scripts/process-voice-messages.php` — cron worker picks up pending messages, calls Retell API with target language agent, passes message_text as dynamic variable, retries up to 3x
- Feature gated behind `account_options.multi_language_enabled` per business

## 2026-03-17 — Professional calendar week view uses icon-only action buttons

**Prompt:** "In /partials/professional/calendar.php?view=week for each appointment that shows edit, complete, cancel, no show, remove the word from the buttons and only leave the icons.  The goal is make the action buttons fit in 1 row."

**Actions:**
- Reviewed the shared `professionalRenderAppointmentCard()` function in `html/partials/professional/calendar.php`, which is used by the week view appointment cards
- Updated the week-view action buttons to render icons only for edit and status transitions while keeping text labels in other calendar views
- Tightened the week-view action row with a compact no-wrap layout and kept `title` and `aria-label` attributes on the buttons for clarity

## 2026-03-17 — Professional calendar search for upcoming appointments

**Prompt:** "In calendar.php we need to add a search text box to allow the user to search for appointments for a specific user.  The need to be able to search by phone number or last name and return reservations for any upcoming date.  In this case the date picker is ignored."

**Actions:**
- Reviewed `html/partials/professional/calendar.php` to trace the current view/date query logic and the HTMX filter wiring
- Added a `search` filter and a calendar search input for last name or phone number, preserving the existing status and service filters
- Updated the appointment query so active searches force the calendar into the upcoming list and search across future appointments only, ignoring the selected date
- Updated the upcoming empty state copy and URL parameter preservation so the search stays active across internal calendar refreshes

## 2026-03-17 — Professional calendar date picker refreshes appointment list

**Prompt:** "When I change the date pick date I expect the list of reservations to be the reservations for the new date."

**Actions:**
- Reviewed the HTMX wiring in `html/partials/professional/calendar.php` after the earlier one-day upcoming-range change
- Updated the calendar's internal navigation, date picker, view switcher, and filter controls to refresh `#professional-calendar-main` with `outerHTML` instead of depending on `#page-content`
- Applied the same local refresh behavior to month-view day links so internal calendar navigation consistently reloads the appointment list for the selected date

## 2026-03-17 — Professional calendar upcoming view uses selected date

**Prompt:** "In /partials/professional/calendar.php?view=upcoming only show appointments for the in id=\"professional-calendar-date-picker\""

**Actions:**
- Reviewed `html/partials/professional/calendar.php` and confirmed the `upcoming` branch used a 30-day date range starting from the selected date
- Updated the `upcoming` branch so it only queries appointments on the selected date, changes the subtitle to `Upcoming on ...`, and makes the previous/next navigation move by one day to stay aligned with `professional-calendar-date-picker`

## 2026-03-17 — Investigate MCP `list_services` JSON parse error

**Prompts:**
- "When testing the MCP server with the list_services tool, the tool returned JSON parse error, no services were returned."
- "proceed"
- "Plese make the fixes."
- "Does the code contain logging?  I am still receiving the error?"
- "The profile of the business should come from restaurants table for the mcp server.  Please show me the queries list_services uses."
- "Change the code to look for restaurants without professional_profiles."
- "We received a 500 error calling the modify_appointment endpoint from the MCP server."
- "Next the cancel_appointment tool is throwing a 500 error.  Please make sure your last fix applies to all mcp server endpoints."

**Actions:**
- Reviewed `html/api/mcp/pro.php`, `html/api/mcp/pro-tools.php`, `helpers/professional-voice-api.php`, `tasks/todo.md`, and `logs/mcp-pro.log`
- Confirmed from `logs/mcp-pro.log` that recent `initialize` and `tools/list` requests to `/api/mcp/pro.php` completed successfully on 2026-03-17, so the issue is likely inside the `list_services` tool result rather than the MCP handshake
- Added a focused task section to `tasks/todo.md` for reproducing the `list_services` tool failure, applying the smallest fix, and verifying the JSON-RPC responses end to end
- Queried the live application database and confirmed the local `professional_profiles` table currently has `0` rows while `professional_services` has active public-bookable rows, which explains why `list_services` was hitting the error path for unknown booking slugs
- Updated `html/api/mcp/pro-tools.php` and `html/api/mcp/server-tools.php` so MCP tool responses always return JSON text for both success and error cases, using `JSON_INVALID_UTF8_SUBSTITUTE` to avoid malformed payloads
- Updated `html/api/mcp/pro.php` and `html/api/mcp/server.php` to emit JSON-RPC responses through a hardened encoder with a fallback internal error payload
- Verified with a live `php -r` execution of `executeProTool('list_services', ['booking_slug' => 'webhook-test'])` that the returned `content[0].text` now decodes cleanly as JSON and reports `{"success":false,"error":"Professional profile not found."}`
- Committed the fix as `0ca30a9` (`Fix MCP tool JSON response encoding`) and pushed it to `main`
- Confirmed from `helpers/professional-availability.php` that `list_services` originally resolved the business via `professional_profiles.booking_slug`; updated that helper to fall back to `restaurants.slug` when no professional profile slug exists
- Updated the same helper so missing profile fields now fall back to `restaurants.name`, `restaurants.phone`, `restaurants.email`, and `restaurants.slug`
- Verified with a live `php -r` execution that `getProfessionalProfileByBookingSlug('webhook-test')` now resolves restaurant `11` without a `professional_profiles` row and that `list_services` returns `Webhook Test` with 1 public service as valid JSON
- Traced the `modify_appointment` 500 to `professional_user_id = 0` in the MCP helper for restaurants without `professional_profiles.owner_user_id`; this violates the `professional_appointments -> users.id` foreign key
- Updated `helpers/professional-voice-api.php` so both `book_appointment` and `modify_appointment` resolve a valid professional user id from the profile owner, existing appointment provider, or active `user_restaurants` records
- Updated `html/api/mcp/pro.php` to catch and log unexpected throwables during `tools/call` and return a JSON-RPC internal error instead of a raw 500
- Verified the fix with a live transactional `php -r` run of `executeProTool('modify_appointment', ...)` for `webhook-test`; it returned a successful JSON result for moving appointment `B59183DC` to `08:00`, and the transaction was rolled back after verification so no appointment or notification-setting changes were persisted
- Traced the `cancel_appointment` 500 from `logs/mcp-pro.log` to `SQLSTATE[42S22]: Unknown column 'a.cancellation_notice_hours'`; removed that nonexistent column from the cancellation lookup query because cancellation notice hours already come from the resolved profile
- Updated `html/api/mcp/server.php` so restaurant MCP `tools/call` now has the same throwable logging and JSON-RPC internal error handling as the professional MCP server
- Verified the fix with a live transactional `php -r` run of `executeProTool('cancel_appointment', ...)` for `webhook-test`; it returned a successful JSON result for confirmation code `B59183DC`, and the transaction was rolled back after verification so the appointment status was not persisted as cancelled

## 2026-03-17 — Document professional MCP server and prompt snippet

**Prompts:**
- "I'm trying to document the mcp server in this project https://zozocal.com/api/mcp/pro.php when I asked chatgpt to write sample prompts I received this message, what does it mean:  I’m going to inspect the MCP endpoint so I can ground the prompt snippet in the actual tool surface instead of guessing.

The endpoint itself is not exposing a readable tool manifest over a normal web fetch, so I’m grounding this in ZozoCal’s visible product capabilities and writing the snippet so it works cleanly with whatever tool names your MCP server actually exposes."
- "Yes, do that."
- "and write it to a file I can use as a prompt snippet."

**Changes:**
- Reviewed `html/api/mcp/pro.php`, `html/api/mcp/pro-tools.php`, and `helpers/professional-voice-api.php` to capture the real JSON-RPC methods, authentication behavior, tool list, and tool semantics for the professional MCP server
- Created `docs/mcp-pro-prompt-snippet.md` with a paste-ready prompt snippet, the exact tools exposed by `/api/mcp/pro.php`, grounded sample prompts, and a curl example for `tools/list`
- Updated `tasks/todo.md` with the task checklist and review section for this documentation work

## 2026-03-17 — Align MCP server with professional appointment flow

**Prompt:** "We need to make sure the MCP server /api/mcp/pro.php properly handles adding and changing professional appointments. We have added columns and changed the process flow for professional appointments so please make sure the MCP server is performing the same tasks with all of the required fields."

**Changes:**
- `html/api/mcp/pro-tools.php`: Added 8 new optional fields to `book_appointment` and `modify_appointment` tool definitions; updated `executeProTool()` to pass extras through
- `helpers/professional-voice-api.php`:
  - `proVoiceBookAppointment()` — INSERT now includes all service contact/address columns and internal_notes; client lookup now also checks email and updates existing client info
  - `proVoiceModifyAppointment()` — Rewritten from cancel+rebook to in-place UPDATE matching save-appointment.php flow (preserves confirmation code, includes all new fields)
  - `proVoiceLookupAppointment()` — Response now includes service contact/address fields

## 2026-03-17 — Fix appointment save error (postal code overflow)

**Prompt:** "I'm getting the error when updating an appointment. Unable to save the appointment."

**Changes:**
- Root cause: `service_postal_code` varchar(5) column overflow with STRICT_TRANS_TABLES
- `html/partials/professional/save-appointment.php`: Truncate postal code to 5 chars server-side
- `html/partials/professional/appointment-form.php`: Added maxlength="5" to postal code input

## 2026-03-17 — Add service contact and address fields to appointment form

**Prompt:** "Please make sure the reservation screen for professional services includes these fields" (service_contact_name, service_phone, service_contact_method, service_address_1, service_city, service_state, service_postal_code)

**Changes:**
- Updated `html/partials/professional/appointment-form.php` — added new "3. Service Contact & Location" section with fields for contact name, phone, contact method, address, city, state, postal code
- Updated `html/partials/professional/save-appointment.php` — reads the 7 new fields from POST and includes them in both INSERT and UPDATE queries

## 2026-03-17 — Auto-create restaurant when prospect moves to in-setup or client

**Prompt:** "When I move a prospect in the prospects table to in-setup or client I need to automatically add a record for that client in the restaurants table and the affiliate that created the client needs to be in the restaurant_users table as an admin for that location."

**Changes:**
- Created `docs/sql/prospect_restaurant_link.sql` — migration to add `restaurant_id` column to `prospects` table
- Created `helpers/prospect-restaurant.php` — shared helper `createRestaurantFromProspect()` that creates a restaurant record (with `location_type` mapped from `product_interest`), adds the affiliate user to `user_restaurants` as admin, and links back via `prospects.restaurant_id`
- Updated `html/partials/affiliate/update-prospect-status.php` — calls auto-creation when status changes to `in_setup` or `client`
- Updated `html/partials/affiliate/save-prospect.php` — calls auto-creation when saving with `in_setup` or `client` status
- Updated `html/partials/affiliate/setup-prospect-company.php` — reuses existing restaurant if already auto-created, then adds defaults (settings, sections, turn times, hours) on top

## 2026-03-17 — Fix webhook restaurant resolution by agent_id and affiliate parity

**Prompt:** "When I pass an agent id into webhook-pro.php where is the company information coming from? I called webhook-pro.php with agent id agent_f6e1213a094c19f7b8a75d17ad and it returned kinetic seas restaurant instead of ZozoCal Demos. The restaurant_prompts table for that agent id shows restaurant_id = 8 which is ZozoCal Demos but has a location type of 'affiliate'. I also need the webhooks for affiliate location types to be the same as professional location types."

**Root cause:** Both `pro-webhook.php` and `restaurant-webhook.php` resolved the restaurant by `to_number` (phone) first via `getRestaurantByPhone()`. When the phone matched a different restaurant (Kinetic Seas), the agent_id lookup then searched `restaurant_prompts WHERE restaurant_id = [wrong ID] AND agent_id = ?`, finding nothing. The `business_name` came from the phone-matched restaurant.

**Changes:**
- Updated `html/api/retell/pro-webhook.php` — when agent_id is provided via query string, resolve the restaurant from `restaurant_prompts` first (joining to `restaurants` to verify `is_active`), fall back to phone lookup only if no match
- Updated `html/api/retell/restaurant-webhook.php` — same agent_id-first resolution logic
- Updated `helpers/openai-sms.php` — `getToolsForLocationType()` and `executeSmsTool()` now treat `'affiliate'` same as `'professional'` (use pro tools)
- Updated `helpers/openai-email.php` — `getEmailToolsForLocationType()` and `executeEmailTool()` now treat `'affiliate'` same as `'professional'` (use pro tools)

## 2026-03-17 — Fix Edit button in Voice Prompts page

**Prompt:** "In voice-prompts.php the button does nothing" (Edit button on voice agent prompts)

**Changes:**
- Moved the `<script>` block (containing `editPrompt()` and `addVariableRow()` functions) from outside to inside the `#voice-prompts-page` div in `html/partials/settings/voice-prompts.php`
- The script was being excluded during HTMX swaps because `hx-select="#voice-prompts-page"` only includes content within that div

## 2026-03-17 — Document pro-webhook JSON response structure

**Prompt:** "Please write the structure of the JSON returned from the webhook_pro.php to a file in docs."

**Changes:**
- Created `docs/pro-webhook-response.json` documenting the full call_inbound response structure including all dynamic_variables and metadata fields

## 2026-03-17 — Add "Move to In-Setup" button on prospect detail page

**Prompt:** "On /partials/affiliate/prospect-detail.php if the status of the prospect is 'New', 'Contacted', or 'Qualified' have a button on the page to change their status to 'In-Setup' so the user does not have to go into Edit Prospect."

**Changes:**
- Created `update-prospect-status.php` — lightweight endpoint that updates prospect status, logs a status_change activity, and reloads the detail page
- Added "Move to In-Setup" button in the header actions area of `prospect-detail.php`, visible only when status is New, Contacted, or Qualified
- Button includes a confirmation dialog before changing status

## 2026-03-17 — Add missing client fields and next appointment lookup to pro-webhook

**Prompt:** "Please let me know the variables that are returned by the api/retell/pro-webhook.php webhook. If the user is a known customer the webhook should return first_name, last_name, email, phone, notes, service_address_line_1, service_city, service_state, service_postal_code, last_service_date, preferred_contact_method, and last_appointment_date from professional_clients, and should check the professional_appointments table and return appointment fields from the next appointment if there is one and set a has_appointment flag."

**Changes:**
- Added `client_preferred_contact_method` and `client_last_appointment_date` to the known-client dynamic variables
- Added next appointment lookup query on `professional_appointments` (joined on `restaurant_id` and `client_id`, filtered to future dates with status pending/confirmed)
- Returns 14 appointment variables (`appointment_date`, `appointment_start_at`, `appointment_service_name`, `appointment_price`, `appointment_confirmation_code`, `appointment_client_notes`, `appointment_service_contact_name`, `appointment_service_contact_phone`, `appointment_service_contact_method`, `appointment_service_address_1`, `appointment_service_city`, `appointment_service_state`, `appointment_service_postal_code`) plus `has_appointment` flag

## 2026-03-16 — Update professional mode footer label

**Prompt:** "In the footer of the website change '© 2026 Professional Scheduling System' to '© 2026 Drajeo/ZozoCal Scheduling System'"

**Changes:**
- Updated `$appFooterLabel` in `html/app.php` from "Professional Scheduling System" to "Drajeo/ZozoCal Scheduling System"

## 2026-03-16 — Add service address to professional_clients and update pro-webhook

**Prompt:** "Next we want to add columns to the professional_clients table and use the professional_clients table instead of the guests table in the webhook. We also want to be able to maintain the new fields in the application. For professional clients we need to track the entire service address and last_service_date. These need to be returned for known callers."

**Changes:**
- Added 5 columns to professional_clients table: service_address_line1, service_city, service_state, service_postal_code, last_service_date
- Updated pro-webhook.php to query professional_clients instead of guests table, returning all new address fields and last_service_date for known callers
- Updated client-form.php with Service Address section (address, city, state, postal code, last service date)
- Updated save-client.php to persist new fields on both INSERT and UPDATE
- Updated client-detail.php with new Service Address card in sidebar
- Updated SQL schema file (professional_scheduling.sql)

## 2026-03-16 — Rename guest keys to client in pro-webhook JSON output

**Prompt:** "Next do the same changing guest to client."

**Changes:**
- Updated `html/api/retell/pro-webhook.php` JSON output keys:
  - `guest_name` → `client_name`, `guest_first_name` → `client_first_name`, etc. (all 14 guest_* keys)
  - `guest_id` → `client_id` (in metadata)
  - Updated activity log description text from "known guest" to "known client"

## 2026-03-16 — Rename restaurant keys to business in pro-webhook JSON output

**Prompt:** "We want to update the webhook to properly support professional accounts. Every place the json returned include 'restaurant' in the output keys we want 'business' instead."

**Changes:**
- Updated `html/api/retell/pro-webhook.php` JSON output keys:
  - `restaurant_name` → `business_name`
  - `restaurant_slug` → `business_slug`
  - `restaurant_phone` → `business_phone`
  - `restaurant_id` → `business_id` (in metadata)
- Applied in both the "not found" fallback and the main dynamic_variables/metadata blocks

## 2026-03-16 — Replace "Restaurant" with "Business" in Confirmation Messages

**Prompt:** "Please replace the word 'Restaurant' with 'Business' wherever you find it in confirmation messages."

**Changes:**
- Updated `html/booking/confirm.php`:
  - Error message: "Restaurant not specified." → "Business not specified."
  - Error message: "Restaurant not found." → "Business not found."
  - Success card label: "Restaurant:" → "Business:"

## 2026-03-16 — Company Setup Button for In-Setup Prospects

**Prompt:** "In partials/affiliate/prospect-detail.php when the prospect status is in-setup the screen should show a company setup button that allows the user to setup a new record in both the restaurants table and the user_restaurants table for the current user. Then the user should be able to switch to that company in the company switcher in the top navbar."

**Changes:**
- Added a green "Company Setup" button in `prospect-detail.php` header actions, visible only when prospect status is `in_setup`
- Created `html/partials/affiliate/setup-prospect-company.php` backend handler that:
  - Creates a new `restaurants` record from the prospect's business data (name, phone, email, address, etc.)
  - Creates a `user_restaurants` record linking the current user as `admin`
  - Bootstraps default settings, section, turn times, and operating hours
  - Updates prospect status from `in_setup` to `client`
  - Logs a status change activity on the prospect timeline
- After setup, the new restaurant appears in the company switcher on next page load via `getUserRestaurants()`

## 2026-03-15 — Prevent Modal Close on Backdrop Click

**Prompt:** "For modal popups when the user accidentally clicks off the modal it closes the modal and user loses their entered data. Is there a HTMX setting we are missing?"

**Changes:**
- Added `data-bs-backdrop="static"` and `data-bs-keyboard="false"` to all 32 modal divs across the application
- This is a Bootstrap 5 setting (not HTMX) that prevents modals from closing when clicking outside or pressing Escape
- Users must now explicitly click the close button or cancel to dismiss modals, preventing accidental data loss
- Files updated across: agents, prospects, projects, models, platform, professional, affiliate, and tasks partials

## 2026-03-15 — Voice Agents: Table to Cards Layout

**Prompt:** "In voice agent maintenance the div id='voice_prompts_list_header' it has a horizontal scroll bar, please change the table to a list of cards without a scrollbar."

**Changes:**
- Replaced the `table-responsive` table in `html/partials/settings/voice-prompts.php` with a stacked card layout
- Each agent now renders as its own card showing description, agent ID, phone, variables, greeting, and webhook URL
- Webhook URL uses `word-break: break-all` to wrap within the card instead of causing overflow
- Removed horizontal scrollbar issue entirely
- All div elements have unique IDs

## 2026-03-13 — Email Agents Feature + Infrastructure

**Prompt:** "Next, we need to create a button in the sidebar for 'EMail Agents' using the same process we used to build SMS agents."

**Changes:**
- Added `nav-email-agents` sidenav item in `html/app.php` below Text Agents (feather-mail icon)
- Added 3 nav_permissions rows (restaurant, professional, affiliate) for admin role
- Created `email_agent_prompts` table — agent config (agent_id, email_address, greeting, description)
- Created `email_agent_prompt_details` table — custom variables per email agent
- Created `email_prompts` table — LLM system prompt + config (model, temperature, max_tokens)
- Created `email_conversations` table — tracks conversation threads by from/to email
- Created `email_messages` table — full message history with tool call tracking
- Created `html/partials/settings/email-agents.php` — full CRUD UI page
- Created `html/partials/settings/save-email-agent.php` and `delete-email-agent.php` — handlers
- Created `helpers/openai-email.php` — OpenAI API helper with MCP tool calling for email
- Created `html/api/email/webhook.php` — inbound email webhook (SendGrid Inbound Parse format), processes through OpenAI, sends reply via MailerSend
- SQL schema saved to `docs/sql/email_agent_tables.sql`

## 2026-03-13 — SMS Agent Infrastructure (OpenAI + Tool Calling)

**Prompt:** "Next we need to create the infrastructure for SMS agents. Assume we are using the OpenAI API and ChatGPT 4.1 is the model we will use. Create a new database table called sms_prompts that we can use with the LLM to receive, process, and send text messages. The text message system will use the MCP tools in server.php and pro.php so they both need to have tool calling ability. When a message is received we need to send the message history for the current conversation along with the system prompt we are creating to process the SMS message."

**Changes:**
- Created `sms_prompts` table — LLM system prompt + config (model, temperature, max_tokens) per restaurant, linked to text_agent_prompts
- Created `sms_conversations` table — tracks conversation threads by from/to phone numbers
- Created `sms_messages` table — stores full message history (user/assistant/tool roles) with tool_call_id, tool_name, tool_args
- Extracted shared tool definitions into `html/api/mcp/server-tools.php` and `html/api/mcp/pro-tools.php` so both MCP servers and SMS agent can reuse them
- Refactored `server.php` and `pro.php` to use shared tool files
- Created `helpers/openai-sms.php` — OpenAI API helper that:
  - Converts MCP tool definitions to OpenAI function calling format
  - Loads conversation history + system prompt with {{variable}} injection
  - Runs tool-call loop (up to 5 iterations) executing tools via existing MCP executors
  - Saves all messages (user, assistant, tool) to sms_messages
- Created `html/api/sms/webhook.php` — Twilio inbound SMS webhook that:
  - Looks up restaurant by "To" phone number
  - Routes to correct tool set based on location_type (restaurant vs professional)
  - Processes message through OpenAI and sends reply via Twilio
- SQL schema saved to `docs/sql/sms_tables.sql`

## 2026-03-13 — Add Text Agents Feature

**Prompt:** "For all location_type values add a new Button below 'Voice Agents' called 'Text Agents'. Start making it the same as Voice Agents and then we will modify it later. Create corresponding data structures so the application can automatically respond to text messages and access tools from SMS messages."

**Changes:**
- Added `nav-text-agents` sidenav item in `html/app.php` below Voice Agents (feather-message-square icon)
- Added 3 nav_permissions rows (restaurant, professional, affiliate) for admin role
- Created `text_agent_prompts` table (mirrors `restaurant_prompts` structure for SMS)
- Created `text_agent_prompt_details` table (custom variables per text agent)
- Created `html/partials/settings/text-agents.php` — full CRUD UI page
- Created `html/partials/settings/save-text-agent.php` — save handler
- Created `html/partials/settings/delete-text-agent.php` — delete handler
- SQL schema saved to `docs/sql/text_agent_tables.sql`

## 2026-03-13 — Rename Voice Agent Prompts Title

**Prompt:** "in voice-prompts.php change the title 'Voice Agent Prompts' to 'Voice Agents'."

**Changes:**
- Changed page title from "Voice Agent Prompts" to "Voice Agents" in `html/partials/settings/voice-prompts.php`

## 2026-03-13 — Show Business Profile for All Location Types

**Prompt:** "In the left sidenav for every restaurants.location_type show the 'Business Profile' link in the SETTINGS section."

**Changes:**
- Added `nav-profile` permission row for `affiliate` location_type in `nav_permissions` table (it was already enabled for `restaurant` and `professional`)
- Updated `docs/sql/nav_permissions.sql` to include the new row
- Business Profile link now appears in SETTINGS section for all three location types: restaurant, professional, and affiliate

## 2026-03-13 — Affiliate Products Catalog Page

**Prompt:** "For affiliate locations we want to add a button called 'Products' to display available products that they are selling to their prospects. We want them displayed as full width cards showing all the features from product_features. The affiliates cannot edit the product list or descriptions."

**Changes:**
- Created `partials/affiliate/products.php` — read-only product catalog with full-width cards showing name, description, pricing (retail + affiliate), and all features from product_features
- Added `nav-affiliate-products` nav item in app.php sidebar under Pipeline section (after Clients)
- Added nav_permissions for admin and manager roles in affiliate locations
- Updated `docs/sql/nav_permissions.sql` with the new permission records

## 2026-03-13 — Prospect Products Tracking

**Prompt:** "We want to be able to track the products that are pitched to each prospect. Create a prospect_products table linked to the affiliate user, prospect, and product from the products table. Store price as recommended_price, affiliate_price, and a quoted_price field. Add status for eventual conversion to affiliate sales. Do database changes and add functionality to add, edit, and delete products."

**Changes:**
- Created `prospect_products` table (id, prospect_id, affiliate_id, product_id, recommended_price, affiliate_price, quoted_price, status, notes, created_at, updated_at) with unique constraint on prospect+product
- Also created missing `prospect_activities` table (was causing 500 error on prospect detail page)
- Created `save-prospect-product.php` — add/edit pitched products with auto-populated pricing from products table
- Created `delete-prospect-product.php` — remove pitched products with ownership verification
- Updated `prospect-detail.php` — added Products Pitched card with: inline add form (product dropdown auto-fills quoted price), table showing all pitched products with recommended/affiliate/quoted pricing, inline status change dropdown (pitched/interested/accepted/declined/converted), delete button
- Replaced "Meetings" stat card with "Products Pitched" count in quick stats row
- SQL migration saved to `docs/sql/prospect_products.sql`

## 2026-03-13 — Prospect CRM Dashboard

**Prompt:** "I want to change the 'Edit Prospect' button in the prospects lists, we want the edit button to launch a full dashboard that allows for notes to be created, records notes about different communications and does any additional prospect tracking users would expect from a CRM."

**Changes:**
- Requires new `prospect_activities` table (SQL provided to user)
- Rebuilt `prospect-detail.php` as a full CRM dashboard with: quick stats row (days in pipeline, total activities, calls, meetings), activity log form (note/call/email/meeting), activity timeline with delete, business info card, contact card, timeline card, and legacy notes display
- Created `save-prospect-activity.php` — backend to insert activity records
- Created `delete-prospect-activity.php` — backend to remove activity records
- Updated `prospects.php` — edit button now navigates to CRM dashboard (eye icon) instead of opening the edit modal

## 2026-03-12 — Fix restaurant switcher to reload sidenav

**Prompt:** "I created a new entry for nav-professional-appointments that should be displayed whenever the location_type='professional' but it is not showing Appointments when I switch my restaurant to 'AI University' in the pulldown"

**Root cause:** The restaurant switcher (`switch-restaurant.php`) used `hx-target="#page-content"` which only swapped the main content area. The sidenav was rendered server-side on initial page load and never re-rendered, so switching to a restaurant with a different `location_type` didn't update the nav permissions.

**Fix:** Changed `switch-restaurant.php` to use `HX-Redirect: /app.php` (full page reload) instead of swapping just `#page-content`. This matches the pattern already used by `switch-mode.php`.

## 2026-03-12 — Professional & affiliate SCHEDULING sections

**Prompt:** "For restaurants.location_type='professional' we want the SCHEDULING section to include Dashboard, Appointments, Services, and Calendar. For restaurants.location_type='affiliate' we want the SCHEDULING section to include Dashboard, Appointments, and Calendar."

**Changes:**
- Reordered professional SCHEDULING in `app.php`: Dashboard, Appointments, Services, Calendar (Services moved up from BUSINESS section)
- Comment updated to "PROFESSIONAL/AFFILIATE MODE: SCHEDULING section" since both share the same nav items
- Added affiliate nav_permissions records: `nav-caption-professional`, `nav-professional-dashboard`, `nav-professional-appointments`, `nav-professional-calendar` with `location_type='affiliate'`
- Updated seed file and migration SQL

## 2026-03-12 — Rename RESERVATIONS to SCHEDULING, fix nav_permissions data

**Prompt:** "I need help setting up the records in nav_permissions. Let's start with restaurants.product_type='restaurants' and any role. We want to display in sidenav at the top in the 'SCHEDULING' section, Dashboard, Reservations, Floor Plan, and Waitlist. Make sure they are in that section and removed from the 'RESERVATIONS' section of the sidenav."

**Changes:**
- Renamed `nav-caption-reservations` to `nav-caption-scheduling` in `app.php` sidenav with label "SCHEDULING"
- Reordered items: Dashboard, Reservations, Floor Plan, Waitlist under SCHEDULING; Events moved below Waitlist
- Updated entire `docs/sql/nav_permissions.sql` seed file: all rows now use `location_type` column instead of `product_type` (which is no longer queried)
- Created `docs/sql/migrate_nav_permissions.sql` — migration for live database: moves product_type values into location_type, clears product_type, renames caption

## 2026-03-12 — Remove product_type and Switch Mode

**Prompt:** "We want to remove all references to user.product_type and remove the Switch Mode menu selections in the account dropdown. It should not be used in deciding which links to display in the left sidenav."

**Changes:**
- `helpers/auth.php` — removed `product_type` param from `getPermittedNavItems()` (now 3 dimensions: user_role, restaurant_role, location_type). Removed `canSwitchMode()`.
- `html/app.php` — removed `product_type` DB query, `$currentProductType`, `$isProfessionalMode/$isAffiliateMode` now driven by `$currentLocationType` from the restaurant. Removed entire Switch Mode dropdown from header. Management caption label uses `$currentLocationType`.
- `html/partials/dashboard/index.php` — uses `restaurants.location_type` instead of `users.product_type` for "Create Reservation" button visibility.
- `html/register.php` — renamed "Product Type" dropdown to "Business Type", field name `location_type`, removed "Systems Integrator" option.
- `html/partials/auth/register.php` — sets `restaurants.location_type` instead of `users.product_type` on registration.
- `html/partials/auth/google-complete.php` — same change for Google registration.
- `html/partials/platform/users.php` — removed Product column from user list table.
- Nav Permissions CRUD — removed product_type from list, form, save, and filters.
- `html/partials/auth/switch-mode.php` — now orphaned (no longer referenced from UI).

## 2026-03-12 — Add label column to nav_permissions

**Prompt:** "Please add a 'Label' column to the table and the screens to make identifying the button easier."

**Changes:**
- Added `label VARCHAR(100) NULL` column to `nav_permissions` CREATE TABLE in `docs/sql/nav_permissions.sql`
- Updated all INSERT statements with human-readable labels (e.g. "Dashboard", "Floor Plan", "SETTINGS caption")
- Added commented-out ALTER TABLE migration for existing databases
- Updated list view (`nav-permissions.php`) with Label column in the table
- Updated form (`nav-permission-form.php`) with Label input field
- Updated save handler (`save-nav-permission.php`) to persist label on insert/update

## 2026-03-12 — Nav Permissions CRUD for super-admin

**Prompt:** "Add a hardcoded button in the sidenav for super-admin user role. Maintain the permission based navigation."

**Changes:**
- Added hardcoded `nav-nav-permissions` sidenav button in `app.php` inside `<?php if (isSuperAdmin()): ?>` block (not driven by nav_permissions table)
- Created `html/partials/platform/nav-permissions.php` — list view with nav_item_id and product_type filters
- Created `html/partials/platform/nav-permission-form.php` — modal form for add/edit with dropdowns for all 4 permission dimensions
- Created `html/partials/platform/save-nav-permission.php` — save handler (insert/update)
- Created `html/partials/platform/delete-nav-permission.php` — delete handler with CSRF protection

## 2026-03-12 — Database-driven nav permissions & header role gating

**Prompt:** "We need to make sure we have the user roles and privileges setup properly. The pulldown menu at the top right that displays the name should only have the switch mode options if the users.role='super-admin' or users.user_type='affiliate'. If the users.id exists in more than one user_restaurants record the Restaurant Switcher dropdown should be displayed. There is a new column in restaurants called location_type. I want to add a table to maintain the relationship between the sidenav button IDs and the driving users.role, user_restaurants.role, restaurants.location_type, and users.product_type."

**Changes:**
- Created `docs/sql/nav_permissions.sql` with:
  - ALTER to add `location_type ENUM('restaurant','professional','affiliate')` column to `restaurants`
  - CREATE TABLE `nav_permissions` mapping nav item IDs to 4 permission dimensions (user_role, restaurant_role, location_type, product_type) with NULL-means-any logic
  - INSERT statements populating all current sidenav permission rules into the table
- Added `getPermittedNavItems()` and `canSwitchMode()` functions to `helpers/auth.php`
- Updated `app.php`:
  - Loads `location_type` from current restaurant and queries `nav_permissions` table at page load
  - Replaced all hardcoded PHP `if/else` sidenav conditionals with `$navAllowed('id')` checks against the permissions array
  - Gated "Switch Mode" dropdown to only show for `users.role='super-admin'` or `users.user_type='affiliate'`
  - Restaurant Switcher already correctly gated by `$hasMultipleRestaurants` (count of user_restaurants records)

## 2026-03-11 — Fix sidebar to use product_type exclusively

**Prompt:** "What is shown in the sidenav is only dependent on product type. User type should not have any influence on what is in the sidenav. Only role and product_type."

**Changes:**
- Changed all sidebar conditions in `app.php` from `$currentUserType` to `$currentProductType`
- MANAGEMENT section (Prospects vs Table Setup etc.) — already fixed to `$currentProductType`
- SETTINGS section (Restaurant Profile, Reservation Rules, Staff Users) — changed two remaining `$currentUserType !== 'affiliate'` checks to `$currentProductType !== 'affiliate'`
- Now the sidebar is entirely controlled by `product_type` and role, with no `user_type` influence

## 2026-03-11 — Affiliate Sidebar & Prospects CRUD

**Prompt:** "When the top level page loads if the product_type = 'affiliate', replace Table Setup, Sections, Operating Hours, Turn Times, Special Dates with Prospects, In-Setup, Clients. Create prospects table and CRUD screens."

**Changes:**
- Updated `app.php` sidebar — when `product_type = 'affiliate'`, MANAGEMENT section shows Prospects, In-Setup, Clients instead of restaurant setup items
- Created `html/partials/affiliate/prospects.php` — list view with status filtering
- Created `html/partials/affiliate/prospect-form.php` — create/edit modal form
- Created `html/partials/affiliate/save-prospect.php` — save handler (insert/update)
- Created `html/partials/affiliate/prospect-detail.php` — full detail view
- Created `html/partials/affiliate/delete-prospect.php` — delete handler
- SQL provided for `prospects` table

## 2026-03-11 — Mode Switcher & Affiliate Settings in Dropdown

**Prompt:** "In the top right pulldown menu, if users.is_affiliate=1 show Affiliate Settings. Add 3 mode selections: Restaurant Mode, Professional Mode, Affiliate Mode. These set users.product_type. After mode change do a full page reload."

**Changes:**
- Updated `app.php` — loads `is_affiliate` and `product_type` from DB; Affiliate Settings shows when `is_affiliate=1`; added 3 mode switches with active state highlighting
- Created `html/partials/auth/switch-mode.php` — updates `users.product_type` and triggers full page reload via `HX-Redirect`

## 2026-03-11 — Affiliate Settings & Become an Affiliate

**Prompt:** "In the top right pulldown menu, add an affiliate settings link. In the main dashboard if the user does not have a record in affiliates table, add a button called Become an Affiliate."

**Changes:**
- Updated `app.php` — added "Affiliate Settings" link to top-right dropdown (visible to affiliates only)
- Created `html/partials/affiliate/settings.php` — self-service affiliate settings page (company info, contact, payout)
- Created `html/partials/affiliate/save-settings.php` — save handler for affiliate settings
- Created `html/partials/affiliate/become-affiliate.php` — creates affiliate record for current user
- Updated `html/partials/dashboard/index.php` — added "Become an Affiliate" button in header (hidden if already affiliate)

## 2026-03-11 — Affiliate Company Info & User/Product Types

**Prompt:** "In the affiliates table add all Company related columns for the affiliate to be paid in the US. In the users table add User Type and Product Type."

**Changes:**
- SQL provided for 10 new columns on `affiliates` (company_name, company_type, tax_id, business address, contact info)
- SQL provided for `user_type` and `product_type` columns on `users` (VARCHAR, not ENUM)
- Updated `affiliate-form.php` — added Company Information section with all business fields
- Updated `save-affiliate.php` — saves all new company fields on insert and update
- Updated `affiliate-detail.php` — displays company info card (with masked tax ID)
- Updated `register.php` — added Product Type dropdown to both manual and Google registration forms
- Updated `partials/auth/register.php` — saves `user_type` (system_owner) and `product_type` on new users
- Updated `partials/auth/google-complete.php` — saves `user_type` and `product_type` for Google registrations
- Updated `partials/platform/users.php` — added User Type and Product columns to the table

## 2026-03-11 — Add Google OAuth Authentication

**Prompt:** "Add Google Authentication to the registration page, register.php. We want the user to be able to register with Google and if they have been invited and have a Google account be able to use their Google account."

**Changes:**
- Created `config/google-oauth.php` — Google Client ID/Secret configuration
- Created `helpers/google-auth.php` — Helper functions: build auth URL, exchange code for tokens, get user info via curl
- Created `html/google-callback.php` — OAuth callback handler for create, invite, and login flows
- Created `html/partials/auth/google-complete.php` — Completes Google registration (asks for company name)
- Updated `html/register.php` — Added "Sign in with Google" button to both Create Account and Accept Invitation tabs
- Updated `html/login.php` — Added "Sign in with Google" button
- SQL provided for `google_id` and `auth_provider` columns on `users` table

## 2026-03-11 — Update Register Page Subtitle

**Prompt:** "On register.php change 'Get started with ZozoCal for your restaurant' to 'Get Started with ZozoCal as an Affiliate or System Owner'."

**Changes:**
- Updated subtitle text on `html/register.php`

## 2026-03-10 — Fix lookup_reservation to Return All Reservations

**Prompt:** "When the agent calls the lookup_reservations MCP endpoint it only returns one reservation even if the guest has more than one reservation. If there is more than one reservation they all need to be returned and used."

**Changes:**
- Updated `voiceLookupReservation()` in `helpers/voice-api.php`:
  - Phone-number lookup now removes `LIMIT 1` and returns ALL active reservations (pending/confirmed/seated)
  - Response now includes `reservations` array (all results), `total_reservations` count, and `reservation` (first result for backward compatibility)
  - Each reservation in the array includes its own `can_cancel` and `can_confirm` flags
  - Message summarizes all reservations when multiple are found, listing each with confirmation code, date, time, party size, and status
- Updated MCP tool description in `html/api/mcp/server.php` to document multi-result behavior

## 2026-03-10 — Clickable Dashboard Stats Cards

**Prompt:** "On the dashboard if the user clicks on the Today's Reservations card I want to display the reservations list. if they click on 'Total Covers' display the list of all tables seated throughout the entire data, if they click on 'Occupancy' show the floor plan and if they click 'On Waitlist' display the waitlist."

**Changes:**
- Updated `html/partials/dashboard/index.php` — added HTMX `hx-get` and `hx-target="#page-content"` attributes to each of the 4 stats cards:
  - **Today's Reservations** → loads reservations list filtered to today's date
  - **Total Covers** → loads the tables list (all seated tables)
  - **Occupancy** → loads the floor plan view
  - **On Waitlist** → loads the waitlist view
- Added `cursor:pointer` styling to each card for visual click affordance

## 2026-03-10 — Shorter 6-Digit Confirmation Codes

**Prompt:** "Change the format of reservation confirmation numbers to 6 digits where the first 3 digits represent the date and the last 3 are unique."

**Changes:**
- Added `generateConfirmationCode()` helper in `helpers/restaurant.php` — first 3 digits are day-of-year (001-366), last 3 are unique within that restaurant+date
- Updated all 6 files that generate confirmation codes to use the new helper: `helpers/voice-api.php`, `html/partials/reservations/save.php`, `html/partials/waitlist/update-status.php`, `html/partials/tables/assign-walkin.php`, `html/partials/events/save-booking.php`, `html/booking/confirm.php`
- Updated MCP tool descriptions to reference "6-digit" codes

---

## 2026-03-10 — Add modify_reservation MCP Tool

**Prompt:** "I just called the voice agent to change my reservation and it booked a new reservation and did not change or cancel the original."

**Changes:**
- Added `modify_reservation` tool to MCP server (`html/api/mcp/server.php`) — cancels the old reservation and books a new one atomically
- Added `voiceModifyReservation()` function in `helpers/voice-api.php`
- Tool description explicitly tells the LLM to use this instead of calling cancel + make_reservation separately

---

## 2026-03-10 — Fix Webhook Missing Variables & Phone Fallback

**Prompt:** "Look at the log and note that the voice_agent_greeting variable is not returned even though the record is in restaurant_prompt_details. Write the inbound GET variables and JSON payload to the log file."

**Root cause:** Retell sends two webhook calls — the first without the `?id=` parameter, so agent prompt variables were skipped. Retell uses the first response.

**Changes:**
- Added phone number fallback in `webhook-call-begin.php`: when `?id=` is not provided, looks up the agent prompt by matching `to_number` against `restaurant_prompts.phone_number`
- Added GET params and raw payload to `logs/webhook-response.log` for debugging

---

## 2026-03-10 — Restaurant Prompt Details (Custom Variables)

**Prompt:** "We want to create a restaurant_prompt_details table so we can break the prompts into multiple return variables — define the variable name and the value that it returns."

**SQL for user to run:**
```sql
CREATE TABLE restaurant_prompt_details (
    id INT AUTO_INCREMENT PRIMARY KEY,
    restaurant_prompt_id INT NOT NULL,
    variable_name VARCHAR(100) NOT NULL,
    variable_value TEXT NOT NULL,
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_prompt_id (restaurant_prompt_id),
    UNIQUE KEY uq_prompt_variable (restaurant_prompt_id, variable_name)
);
```

**Changes:**
- Updated `html/api/retell/webhook-call-begin.php` to query `restaurant_prompt_details` and merge custom variables into dynamic variables returned to Retell
- Updated `html/partials/settings/voice-prompts.php` admin UI with a "Custom Variables" section in the form (add/remove name/value rows) and a Variables column in the list
- Updated `html/partials/settings/save-voice-prompt.php` to save variable details in a transaction
- Updated `html/partials/settings/delete-voice-prompt.php` to cascade delete details

---

## 2026-03-10 — Webhook Date/Time Variables for Voice Agent

**Prompt:** "I want the webhook retell/webhook-call-begin.php to return the variables 'time', 'today', 'tomorrow', 'dow', 'monday' through 'sunday'."

**Changes:**
- Updated `html/api/retell/webhook-call-begin.php` to return date/time dynamic variables in the restaurant's timezone: `time`, `today`, `tomorrow`, `dow`, and each day of the week (`monday` through `sunday`) with the actual date of the next occurrence
- Removed old `current_date` and `current_date_display` variables in favor of the new ones

---

## 2026-03-10 — MCP Server Relative Date Support

**Prompt:** "I asked the agent to make a reservations for Today. We need to change the flow in order for the MCP server to support Today, Tomorrow, and any of the Next 4 days of the week, Monday, Tuesday, ..."

**Changes:**
- Updated `voiceFixDate()` in `helpers/voice-api.php` to resolve relative date words: "today", "tomorrow", and day names ("monday" through "sunday")
- Updated MCP tool definitions in `html/api/mcp/server.php` for `check_availability` and `make_reservation` to tell the LLM to use these keywords instead of guessing YYYY-MM-DD dates

---

## 2026-03-10 — Clear Waitlist Button

**Prompt:** "Please add a 'Clear Waitlist' button to the wait list screen."

**Changes:**
- Added "Clear Waitlist" button to the waitlist header in `html/partials/waitlist/index.php`, shown only when there are active waitlist entries
- Created `html/partials/waitlist/clear.php` backend handler that marks all waiting/notified entries as "left" and logs the action
- Button includes a confirmation dialog before clearing

---

## 2026-03-10 — Display Restaurant Phone in Header

**Prompt:** "In the main top header. Display the restaurant phone number next to the current restaurant name in the format (###) ###-####."

**Changes:**
- Modified `html/app.php` to query the restaurant's phone number from the database and format it as (###) ###-####
- Added the formatted phone number next to the restaurant name in the top header bar with slightly smaller, normal-weight styling

---

## 2026-03-09 — Voice Agent Prompt Management

**Prompt:** "We need admins and super-admins to be able to manage voice agent prompts for a restaurant. The restaurant_prompt table will need to include agent_id, phone_number, voice_agent_greeting, and description. The webhook /api/retell/webhook-call-begin.php should have a get variable id=[agent_id] added so that it can query the additional dynamic prompt values in addition what is passed from the from_phone and to_phone."

**Changes:**
- Provided SQL for new `restaurant_prompts` table (user creates)
- Created `html/partials/settings/voice-prompts.php` — CRUD management page for voice agent prompts
- Created `html/partials/settings/save-voice-prompt.php` — save/update handler
- Created `html/partials/settings/delete-voice-prompt.php` — delete handler
- Added "Voice Prompts" nav item in `html/app.php` under Settings (admin + super-admin)
- Updated `html/api/retell/webhook-call-begin.php` to accept `?id=agent_id` query param and override `voice_agent_greeting` from `restaurant_prompts` table
- Updated `html/partials/settings/integrations.php` webhook URL display to show `?id=` pattern

## 2026-03-09 — Waitlist Estimated Wait Time Fix

**Prompt:** "On the waitlist screen now that reservations are part of the waitlist, please update the estimated New Guest wait time to take this into account."

**Changes:**
- Updated `html/partials/waitlist/index.php` to include checked-in and past-time reservations in the waiting count for the "New Guest Wait" estimate

## 2026-03-09 — Reservations on Waitlist Screen

**Prompt:** "We want to show reservations on the waitlist screen combined with people on the waitlist ordered so they show in the expected seating order. Reservations on the waitlist should be highlighted in a light red if they have not checked in and in a light green if they have checked in. There needs to be a checkin button for the reservation. If the reservation time has passed, the reservations need to be moved to the top of the waitlist."

**DB change:** `ALTER TABLE reservations ADD COLUMN checked_in_at TIMESTAMP NULL DEFAULT NULL AFTER seated_at;`

**Changes:**

1. **Combined waitlist view** — Today's pending/confirmed reservations are merged with waitlist entries in a single table, sorted by expected seating time.
2. **Sort order** — Past-time reservations sort to the top (with "PAST" label), then remaining entries by time.
3. **Row highlighting** — Reservation rows have light red background when not checked in, light green when checked in.
4. **Check-in button** — New "Check In" button on reservation rows sets `checked_in_at` timestamp and auto-confirms pending reservations.
5. **Seat from waitlist** — Reservation Seat button calls status-update.php with `from_waitlist=1` param to refresh waitlist instead of loading detail page.
6. **Type column** — New "Type" column distinguishes "Reservation" (blue badge) from "Walk-in" (yellow badge).
7. **Header count** — Shows both waiting count and reservation count.

**Files created:**
- `html/partials/waitlist/checkin-reservation.php`

**Files modified:**
- `html/partials/waitlist/index.php` — Merged reservations into combined list
- `html/partials/reservations/status-update.php` — Added from_waitlist redirect support

---

## 2026-03-09 — Super-Admin Restaurant Dashboard

**Prompt:** "In /partials/platform/restaurants.php when we go to edit a restaurant we want the super-admin to get a restaurant dashboard where they see all of the information for the restaurant, including billing and services."

**Changes:**

1. **Restaurant Dashboard** — Created `html/partials/platform/restaurant-dashboard.php` with full-page dashboard showing restaurant details, stats, subscriptions, staff, and account options. Follows same pattern as affiliate-detail.php.
2. **Edit button updated** — Clicking Edit on a restaurant in the list now navigates to the dashboard (hx-target="#page-content") instead of opening a modal.
3. **Save redirect** — After editing a restaurant via the modal form on the dashboard, the page reloads the dashboard instead of the restaurant list.
4. **Dashboard sections:** Stats cards (subscription, staff count, today's reservations, all-time reservations), restaurant details, subscriptions table, staff table, account options table. Back button returns to restaurant list. Edit button opens the existing modal form.

**Files created:**
- `html/partials/platform/restaurant-dashboard.php`

**Files modified:**
- `html/partials/platform/restaurants.php` — Edit button targets dashboard
- `html/partials/platform/save-restaurant.php` — Edit save redirects to dashboard

---

## 2026-03-09 — Platform Role on Users Table, Super-Admin All-Restaurant Access, Global Users Page

**Prompt:** "Since the super-admin role has access to all restaurants, the role column needs to be on the users table and not the user_restaurants table for the super-admin role and the user needs to be able to see a list of all restaurants and users."

**DB change:** `ALTER TABLE users ADD COLUMN role ENUM('user','affiliate','super-admin') NOT NULL DEFAULT 'user'`

**Changes:**

1. **Auth updated** — `isSuperAdmin()` now checks `users.role = 'super-admin'` instead of `is_platform_admin` flag. Session stores `role` from users table. `is_platform_admin` kept in sync for backward compat.
2. **Super-admin restaurant access** — `getUserRestaurants()` returns ALL active restaurants for super-admins. `switchRestaurant()` allows super-admins to switch to any restaurant with admin-level access.
3. **Global Users page** — New `platform/users.php` shows all users with platform role, restaurant assignments, status, last login. Summary cards show counts by role.
4. **User form** — Super-admin can create/edit users, set platform role (user/affiliate/super-admin), set password, and add restaurant assignments.
5. **Toggle user** — Activate/deactivate users (with self-deactivation protection).
6. **Sidebar** — Added "Users" link under PLATFORM section.

**Files created:**
- `html/partials/platform/users.php`, `platform-user-form.php`, `save-platform-user.php`, `toggle-platform-user.php`

**Files modified:**
- `helpers/auth.php` — Uses `users.role` for super-admin/affiliate checks, session stores platform role
- `helpers/restaurant.php` — `getUserRestaurants()` returns all restaurants for super-admins
- `html/app.php` — Added Users link in PLATFORM sidebar

---

## 2026-03-09 — SaaS Products, Subscriptions, Affiliates & Account Options

**Prompt:** "We want to start building the SaaS portions of the application including plans, users, and options. We also want to have an affiliate program where users of role='affiliate' are attached to user accounts and get a commission. We need to design the database to handle various products and options on their account. The super-admin is the only role that can manage this part of the system. We need a product pricing table that defines each product along with it's price and affiliate price."

**DB tables created (by user):** `products`, `product_features`, `subscriptions`, `account_options` (already existed), `affiliates`, `affiliate_referrals`, `affiliate_commissions`

**Changes:**

1. **Product Management** — Products & Pricing page with CRUD (name, slug, price, affiliate_price, billing interval, sort order, active toggle). Features sub-management per product.
2. **Subscription Management** — Assign products to restaurants with status tracking (active/trialing/past_due/cancelled), start/expiration dates.
3. **Account Options** — Per-restaurant option toggles with key/value pairs, filterable by restaurant.
4. **Affiliate Management** — Create affiliates from existing or new users. Affiliate code, commission rate, payout method/email, status tracking.
5. **Affiliate Detail** — Referral list and commission history per affiliate. Add referrals (link restaurants), add commissions, approve/reject/pay workflow.
6. **Affiliate Dashboard** — Affiliate users see their own referral stats and commission history via "My Referrals" nav item.
7. **Sidebar Updates** — Added Products & Pricing, Subscriptions, Account Options, Affiliates under PLATFORM (super-admin only). Added AFFILIATE section for affiliate users.
8. **Auth Updates** — `isAffiliate()` function, session tracks `is_affiliate` and `affiliate_id` on login.

**Files created:**
- `html/partials/platform/products.php`, `product-form.php`, `save-product.php`, `toggle-product.php`
- `html/partials/platform/product-features.php`, `save-product-feature.php`, `delete-product-feature.php`
- `html/partials/platform/subscriptions.php`, `subscription-form.php`, `save-subscription.php`
- `html/partials/platform/account-options.php`, `account-option-form.php`, `save-account-option.php`, `toggle-account-option.php`, `delete-account-option.php`
- `html/partials/platform/affiliates.php`, `affiliate-form.php`, `save-affiliate.php`
- `html/partials/platform/affiliate-detail.php`, `affiliate-referral-form.php`, `save-affiliate-referral.php`
- `html/partials/platform/affiliate-commission-form.php`, `save-affiliate-commission.php`, `update-commission-status.php`
- `html/partials/affiliate/dashboard.php`

**Files modified:**
- `html/app.php` — Added PLATFORM nav items and AFFILIATE section
- `helpers/auth.php` — Added `isAffiliate()`, affiliate session data on login

---

## 2026-03-08 — Waitlist Table Ready Button with Call Tracking

**Prompt:** "We need to add a SMS table ready button or call button to show that we have announced the table is ready. If a phone is on file the SMS is sent otherwise we are just marking that we called the guest. Also register the time they were called and allow for a second call before giving up the table."

**DB change:** `ALTER TABLE waitlist ADD COLUMN notify_count TINYINT UNSIGNED NOT NULL DEFAULT 0`

**Changes:**
1. Replaced the old "Notify" button (only shown when phone on file) with a "Table Ready" / "Call Guest" button that always appears
2. `notify.php` now handles both cases: sends SMS if phone exists, otherwise just marks as called. Tracks `notify_count` and `notified_at`
3. Button flow by call count:
   - **0 calls:** "Table Ready" (solid blue) or "Call Guest" (if no phone) — sends SMS or marks called
   - **1 call:** "2nd Call" (outline warning) — sends SMS again or marks called again
   - **2 calls:** Table Ready button disappears, replaced by prominent red "Give Up" button with confirmation
4. Status column shows notification time and count (e.g. "Notified 6:30pm (2x)")
5. Left/No Response buttons show normally for 0-1 calls, replaced by single "Give Up" after 2 calls

**Files modified:** `html/partials/waitlist/index.php`, `html/partials/waitlist/notify.php`

---

## 2026-03-08 — Waitlist Table Availability Estimate

**Prompt:** "We need an estimate added to the top of the reservations page based on the status of the seated tables."

**Changes:**
Added an estimate card to the top of the waitlist page showing:
- Open tables count vs total active tables
- Number of currently seated tables
- Next available table (name, estimated minutes, estimated time) based on meal status
- Up to 6 upcoming table turnovers with table name, seat range, meal status badge, and estimated minutes remaining

Uses the existing `meal-status.php` helper to calculate remaining time based on each table's meal progress percentage and the restaurant's turn time setting. Auto-refreshes with the page's 15-second poll.

**Files modified:** `html/partials/waitlist/index.php`

---

## 2026-03-08 — Waitlist Quoted Wait Time

**Prompt:** "On the waitlist page we need to be able to provide an estimated wait time and mark what we told the guest what the wait time would be when they were added to the list."

**Changes:**
1. Added "Quoted Wait Time" required field to the add-to-waitlist form (`add-form.php`)
2. Updated `save.php` to use the staff-entered quoted wait time instead of auto-calculating
3. Split the single "Wait Time" column into two columns on the waitlist table:
   - **Actual Wait** — live elapsed time since added (turns red if exceeds quoted time)
   - **Quoted** — what the guest was told (turns red if actual exceeds it)

**Files modified:** `html/partials/waitlist/add-form.php`, `html/partials/waitlist/save.php`, `html/partials/waitlist/index.php`

---

## 2026-03-08 — Restaurant Timezone Support

**Prompt:** "We need to add timezones to restaurant settings and display all times in the restaurant timezone."

**Approach:** The `timezone` column and profile settings dropdown already existed. The fix was to set PHP's default timezone per-request using `date_default_timezone_set()` so all existing `date()` calls automatically use the correct timezone — zero changes to 148+ display files.

**Changes:**
1. Added `applyRestaurantTimezone($restaurantId)` function to `helpers/restaurant.php`
2. Called it in `requireAuth()` in `helpers/auth.php` — covers all authenticated dashboard pages
3. Called it in all 6 booking pages (`index`, `check-slots`, `confirm`, `cancel`, `lookup`, `modify`)
4. Called it in all 6 voice API functions in `helpers/voice-api.php`
5. Called it per-restaurant in `helpers/send-reminders.php` cron loop
6. Called it in webhook endpoints (`webhook-call-begin.php`, `webhook-call-end.php`)

**Files modified:** `helpers/restaurant.php`, `helpers/auth.php`, `helpers/voice-api.php`, `helpers/send-reminders.php`, `html/booking/*.php` (6 files), `html/api/retell/webhook-call-begin.php`, `html/api/retell/webhook-call-end.php`

---

## 2026-03-08 — SMS Confirmation Toggle

**Prompt:** "Add a setting for SMS confirmation messages when reservations are made."

**Changes:**
1. Added `sendConfirmationSMS()` function to `helpers/notifications.php` — sends a text with guest name, restaurant, date/time, party size, and confirmation code
2. Added `notification_confirmation_sms` toggle to notification settings UI (defaults to off)
3. Added `sendConfirmationSMS()` calls alongside `sendConfirmationEmail()` in all 4 trigger points:
   - `html/booking/confirm.php` (online booking)
   - `html/partials/reservations/status-update.php` (staff confirms)
   - `helpers/voice-api.php` (voice agent creates/confirms reservation, 2 locations)

**Files modified:** `helpers/notifications.php`, `html/partials/settings/notifications.php`, `html/booking/confirm.php`, `html/partials/reservations/status-update.php`, `helpers/voice-api.php`

---

## 2026-03-08 — MailerSend Email Integration

**Prompt:** "I want to use mailersend to send all email from the app to improve deliverability."

**Changes:**

1. Installed Composer and MailerSend PHP SDK (`mailersend/mailersend` v0.36.0)
2. Updated `helpers/notifications.php`:
   - Added Composer autoloader and MailerSend SDK imports
   - Replaced `sendEmail()` function to use MailerSend API when an API key is configured
   - Falls back to PHP `mail()` if no API key is set (graceful degradation)
   - Same function signature — all callers (confirmation, reminder, cancellation) work without changes
3. Updated `html/partials/settings/notifications.php`:
   - Added "Email Provider (MailerSend)" settings card with API key field
   - Added `save_mailersend` action handler to save the key to the `settings` table
4. Added `.gitignore` for `vendor/` and `composer` files
5. Saved MailerSend API key for both restaurants (1 and 3) in the database

**Files created:** `.gitignore`, `composer.json`, `composer.lock`
**Files modified:** `helpers/notifications.php`, `html/partials/settings/notifications.php`

## 2026-03-08 — Special Events System with Event-Specific Reservations

**Prompt:** "We want to create the ability to have special events and take reservations for the events specifically."

**DB changes required:**
```sql
CREATE TABLE events (...); -- see restaurant_reservations.sql
ALTER TABLE reservations ADD COLUMN event_id INT UNSIGNED NULL,
    ADD CONSTRAINT fk_reservations_event FOREIGN KEY (event_id) REFERENCES events(id) ON DELETE SET NULL;
```

**Files created (6):**
1. `html/partials/events/list.php` — Events listing page with upcoming/draft/past filter tabs, card grid with capacity progress bars
2. `html/partials/events/form.php` — Create/edit event modal (name, date, time, capacity, price, status, description, public flag, notes)
3. `html/partials/events/save.php` — POST handler for create/update/cancel
4. `html/partials/events/detail.php` — Event detail with info card, capacity stats, and reservation table with add booking button
5. `html/partials/events/book.php` — Quick-book modal with guest search, new guest form, party size, special requests
6. `html/partials/events/save-booking.php` — Booking handler: validates capacity, finds/creates guest, creates reservation linked to event

**Files modified (4):**
1. `html/app.php` — Added "Events" nav item with feather-star icon under RESERVATIONS
2. `html/partials/dashboard/index.php` — Added "Upcoming Events" card showing next 3 events with capacity bars
3. `html/partials/guests/search.php` — Added data-* attributes to search results for guest selection in booking forms
4. `restaurant_reservations.sql` — Added events CREATE TABLE definition

---

## 2026-03-08 — Save Call Transcript & Recording on Call End

**Prompt:** "AT the end of the call i want the end of call webhook to save the transcript and audio to the database or system."

**DB change required:**
```sql
CREATE TABLE call_logs (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    restaurant_id INT UNSIGNED NULL,
    guest_id INT UNSIGNED NULL,
    retell_call_id VARCHAR(100) NULL,
    direction ENUM('inbound','outbound') NOT NULL DEFAULT 'inbound',
    from_number VARCHAR(20) NOT NULL,
    to_number VARCHAR(20) NOT NULL,
    status VARCHAR(30) NOT NULL DEFAULT 'ended',
    duration_ms INT UNSIGNED NULL,
    disconnection_reason VARCHAR(100) NULL,
    transcript LONGTEXT NULL,
    recording_url VARCHAR(500) NULL,
    call_summary TEXT NULL,
    metadata JSON NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    ended_at TIMESTAMP NULL,
    INDEX idx_restaurant (restaurant_id),
    INDEX idx_guest (guest_id),
    INDEX idx_retell_call (retell_call_id),
    INDEX idx_created (restaurant_id, created_at),
    INDEX idx_direction (restaurant_id, direction),
    CONSTRAINT fk_calllogs_restaurant FOREIGN KEY (restaurant_id) REFERENCES restaurants(id) ON UPDATE CASCADE ON DELETE SET NULL,
    CONSTRAINT fk_calllogs_guest FOREIGN KEY (guest_id) REFERENCES guests(id) ON UPDATE CASCADE ON DELETE SET NULL
) ENGINE=InnoDB;
```

**Actions:**
1. `html/api/retell/webhook-call-end.php` — Rewrote to insert into `call_logs` table. Extracts transcript, recording_url, call_summary, call_analysis from Retell payload. Matches guest by phone. Keeps short activity_log audit entry. Added file logging.
2. `restaurant_reservations.sql` — Added `call_logs` CREATE TABLE definition.

---

## 2026-03-08 — Add Contact Preferences Tool to MCP Server & Retell

**Prompt:** "I want the voice agents to be able to set these fields. Please update the MCP servers to be able to update contact status."

**Actions:**
1. `helpers/voice-api.php` — Added `voiceUpdateContactPreferences()` function. Looks up guest by phone number within the restaurant, updates whichever flags are provided (do_not_call, do_not_email, do_not_text).
2. `html/api/mcp/server.php` — Added `update_contact_preferences` tool definition with boolean params and wired it into `executeTool()`.
3. `html/api/retell/update-contact-preferences.php` — New Retell custom function endpoint following the same pattern as other endpoints.

**Tool signature:**
- Required: `restaurant_slug`, `guest_phone`
- Optional booleans: `do_not_call`, `do_not_email`, `do_not_text` (set true to opt out, false to opt in)

---

## 2026-03-08 — Add Do Not Call/Email/Text Flags to Guest CRM

**Prompt:** "I want to add 'Do not Call' and 'Do not Email' and 'Do Not Text' flags to customers in the CRM."

**DB change required:**
```sql
ALTER TABLE guests
  ADD COLUMN do_not_call TINYINT(1) NOT NULL DEFAULT 0,
  ADD COLUMN do_not_email TINYINT(1) NOT NULL DEFAULT 0,
  ADD COLUMN do_not_text TINYINT(1) NOT NULL DEFAULT 0;
```

**Actions:**
1. `html/partials/guests/detail.php` — Added toggle switches in the Contact Information card for each opt-out flag. Changes save inline via HTMX.
2. `html/partials/guests/form.php` — Added checkboxes under "Contact Preferences" in the add/edit guest modal.
3. `html/partials/guests/save.php` — Updated save (create/update) to persist the 3 flags. Added new `update_optout` action for inline toggle updates from the detail page.
4. `html/partials/guests/list.php` — Red badge indicators (phone-off, mail, message-circle icons) appear next to guest names when opt-outs are active.

---

## 2026-03-08 — Fix Guest Directory Scrollbar

**Prompt:** "Please fix the scrollbar in Guest Directory."

**Actions:**
1. `html/app.php` — Moved `kobie-custom.css` to load after `OverlayScrollbars.min.css` so custom CSS always wins.
2. `html/assets/css/kobie-custom.css` — Added `!important` to all `::-webkit-scrollbar` overrides to beat the theme's 5px thin defaults. Added `.table-responsive` explicit scrollbar styling and `.nxl-content { overflow-y: auto }` for proper main content scrolling.

---

## 2026-03-08 — Track Actual Meal Status Timestamps for Time Refinement

**Prompt:** "I want to be able to track actual times for each status change for later refinement of the projected times."

**DB change:** Added 4 timestamp columns to `reservations`: `ordering_at`, `eating_at`, `check_dropped_at`, `paid_at` (follows existing `seated_at`/`completed_at` pattern).

**Actions:**
1. `html/partials/tables/update-meal-status.php` — Records `NOW()` in the corresponding timestamp column when meal status changes.
2. `html/partials/tables/table-status.php` — Shows "Actual Pace" timeline with real durations per phase (e.g. "Seated: 8m, Ordering: 12m").
3. `html/partials/reservations/detail.php` — Shows all meal phase timestamps in the reservation detail view.

**Future use:** Actual vs projected can be queried with simple column subtraction, e.g. `TIMESTAMPDIFF(MINUTE, seated_at, ordering_at)` to compute real phase durations for refining the configurable percentages.

---

## 2026-03-08 — Make Meal Progress Percentages Configurable Per Restaurant

**Prompt:** "Please make them configurable by restaurant."

**Actions:**
1. Updated `helpers/meal-status.php` — `getMealPercentages()` now loads from individual settings rows (`meal_pct_seated`, `meal_pct_ordering`, etc.) per restaurant, falling back to defaults.
2. Created `html/partials/settings/save-meal-percentages.php` — POST handler that upserts one setting row per status.
3. Updated `html/partials/settings/turn-times.php` — Added "Meal Progress Estimates" card with percentage inputs for each status.
4. Updated all callers (floor plan, table status modal) to pass `$restaurantId`.

**Storage:** Each status is its own row in the `settings` table (e.g. `meal_pct_seated = 0`, `meal_pct_ordering = 15`). No JSON.

---

## 2026-03-08 — Table Meal Status Tracker with Time Predictions

**Prompt:** "For seated tables I need the hostess to be able to update the status of the meal. I also want to be able to predict when a table will be open based on the status."

**Actions:**
1. Added `meal_status` column to reservations table (ENUM: seated, ordering, eating, check_dropped, paid)
2. Created `helpers/meal-status.php` — status definitions with % of meal completed, used to estimate remaining time
3. Created `html/partials/tables/table-status.php` — modal for occupied tables showing guest info, progress bar, estimated available time, and one-tap status buttons
4. Created `html/partials/tables/update-meal-status.php` — POST handler for status updates and "Complete & Open Table"
5. Updated floor plan to show meal status badge + estimated time on occupied tables, click opens status modal
6. Updated walk-in seating and reservation status transitions to set meal_status

**Time prediction logic:** Each status = percentage through the meal (0% → 15% → 40% → 80% → 95%). Remaining time = turn_time × (1 - pct/100). Shows "~30 min" and "~7:45pm" on floor plan and modal.

---

## 2026-03-08 — Walk-Ins No Longer Auto-Added to CRM

**Prompt:** "We do not want to add walkins into the CRM unless we specify it. We are not always going to ask a name for walkins."

**Actions:**
1. `html/partials/tables/assign-walkin.php` — Name fields are now optional. If no name is given, a placeholder guest (`Walk-in Guest`, `is_active=0`) is created for the reservation but won't appear in the CRM. If a name IS provided, the guest is added to the CRM normally (`is_active=1`).
2. `html/partials/guests/list.php` — Added `is_active = 1` filter so anonymous walk-in placeholders never appear in the guest directory.

---

## 2026-03-08 — Fix Edit Reservation Escaping to Top-Level Page

**Prompt:** "At the end of edit reservation, the partial escapes and becomes the top level page."

**Root Cause:** `update.php` used `HX-Redirect` header which causes a full browser navigation to the partial URL, breaking out of the SPA layout.

**Fix:** Replaced `HX-Redirect` with a PHP `include` of `detail.php`, so the updated detail view is returned as HTMX content directly into `#page-content`.

---

## 2026-03-08 — Fix Edit Button on Reservation Detail Page

**Prompt:** "When in the view reservation screen, the edit button does nothing."

**Root Cause:** The `#detail-edit-container` div was at the very bottom of the page (after notes and activity sections). When the Edit button loaded the form via HTMX, it appeared off-screen so the user couldn't see it.

**Fix:** Moved `#detail-edit-container` to directly after the status actions card, so the edit form appears right below the Edit button when clicked.

---

## 2026-03-08 — Hide Cancelled Reservations from Default Views

**Prompt:** "When a reservation is cancelled do not show it in the upcoming reservation list."

**Actions:**
1. `html/partials/reservations/list.php` — Added `r.status != 'cancelled'` when no status filter is selected. Users can still view cancelled reservations by selecting "Cancelled" in the status dropdown.
2. `html/partials/reservations/calendar.php` — Same change for the daily calendar view.
3. Dashboard "Upcoming Arrivals" already excluded cancelled (no change needed).

---

## 2026-03-08 — Server-Side Auto-Correct for Wrong Date Year from LLM

**Prompt:** "Still get errors."

**Problem:** Despite passing `current_date` as a dynamic variable, Retell's LLM still sends `2024-03-08` instead of `2026-03-08`. The LLM is ignoring the variable.

**Fix:** Added `voiceFixDate()` helper in `helpers/voice-api.php` that auto-corrects the year server-side. If the LLM sends a past date but the month/day are valid, it replaces the year with the current year (or next year if that date already passed). Applied to both `voiceCheckAvailability` and `voiceMakeReservation`.

---

## 2026-03-08 — Fix: Retell Agent Sending Wrong Year (2024 instead of 2026)

**Prompt:** "Check the logs"

**Root Cause:** MCP server logs showed Retell's LLM was calling `check_availability` with `"date": "2024-03-08"` — two years in the past. The LLM didn't know the current date, so it guessed 2024. Our API correctly returned "Cannot book a date in the past", which the voice agent reported as a "technical issue."

**Fix:** Added `current_date` (YYYY-MM-DD) and `current_date_display` (e.g. "Sunday, March 8, 2026") as dynamic variables in the call-begin webhook response. The Retell agent's prompt can now reference `{{current_date}}` to know today's date.

---

## 2026-03-08 — Add Logging to MCP Server

**Prompt:** User called voice agent and got "technical issue" error. Asked if MCP server has logging.

**Findings:**
- `webhook-call-begin.php` — working fine, logging correctly to `logs/webhook-call-begin.log`
- Retell custom function endpoints — had logging code but `logs/retell-custom-functions.log` didn't exist (never been called)
- `html/api/mcp/server.php` — had NO logging at all

**Actions:**
1. Added logging helper functions (`mcpLog`, `mcpLogJson`) to MCP server
2. Logs every request: method, raw body, auth failures, tool calls with args, tool results, and responses
3. All logs write to `logs/mcp-server.log`
4. Created both `logs/mcp-server.log` and `logs/retell-custom-functions.log` with write permissions

---

## 2026-03-08 — Fix Web Call Dynamic Variables (Root Cause)

**Prompt:** "Still did not work. Try again."

**Root Cause:** `call.php` and `demo.php` were calling the Retell API directly from browser JavaScript (`fetch('https://api.retellai.com/v2/create-web-call', ...)`), completely bypassing our server-side `create-web-call.php` endpoint. This meant:
1. No dynamic variables (restaurant info, guest CRM data) were being populated
2. The Retell API key was exposed in the browser source code

**Actions:**
1. **`call.php`** — Changed to route through `/api/retell/create-demo-call.php` instead of calling Retell directly. Removed exposed API key.
2. **`demo.php`** — Changed to route through `/api/retell/create-demo-call.php`. Removed exposed API key. Apartment demo variables sent via `metadata`.
3. **`create-web-call.php`** — Fixed guest lookup to also check inside `metadata` object for `guest_phone`/`guest_id` (retell-integration.js sends data nested in metadata).
4. **`webhook-call-begin.php`** — Fixed activity_log INSERT: added missing `entity_type` column (`'voice_call'`) that was causing SQL error.

**Note:** The main app pages (guests, reservations, waitlist) already used `retell-integration.js` which routes through our server correctly.

---

## 2026-03-08 — Retell JSON Rewritten to Match Actual Import Format

**Prompt:** User provided an actual Retell export showing the correct format. Key difference: Retell connects to MCP servers natively via the `mcps` array — not as individual custom function tools.

**Actions:**
1. Rewrote `html/downloads/retell-agent-import.php` to match Retell's actual import schema:
   - Top-level: `agent_id`, `channel`, `agent_name`, `response_engine`, `webhook_url`, `version`
   - LLM config nested under `retellLlmData` (not `llm`)
   - Tools connect via `mcps` array pointing to our MCP server (`/api/mcp/server.php`)
   - Removed individual custom function tool definitions — MCP auto-discovers tools
   - Added `end_call` tool (Retell built-in)
   - Included bearer token in `mcps.headers` if `mcp_api_key` is set
   - `default_dynamic_variables` moved inside `retellLlmData`
2. The 5 reservation tools are now served from the MCP server, not individual endpoints

---

## 2026-03-08 — Dynamic Retell JSON & MCP Usage Examples

**Prompt:** "Why is YOURDOMAIN.com intentional? This is a self contained application."

**Actions:**
1. Replaced static `html/downloads/retell-agent-import.json` with dynamic `html/downloads/retell-agent-import.php` that:
   - Auto-detects the server URL (protocol + host)
   - Loads restaurant name, slug, and phone from the database
   - Generates JSON with all tool URLs pointing to the actual server
   - Downloads as `retell-agent-import.json` (Content-Disposition header)
   - Requires admin auth
2. Updated integrations page download link to use the PHP endpoint
3. Removed "replace YOURDOMAIN.com" instructions since URLs are now auto-populated

---

## 2026-03-08 — MCP Usage Examples & Retell Import Download

**Prompt:** "In reviewing the JSON you are not accessing the MCP servers we created in the application and you are referencing APIs and reservations system for custom domains. Please check your work and if you are not capable of doing the task, just give me instructions on how to call the MCP tools you created. Also make the JSON document something I can download under the html folder."

**Actions:**
1. Copied `docs/retell-agent-import.json` to `html/downloads/retell-agent-import.json` for direct download
2. Added "Retell Agent Import File" card to integrations page with download button
3. Expanded the MCP Server card with full curl usage examples for all 5 tools (list tools, check_availability, make_reservation, lookup_reservation, cancel_reservation) — each with Copy button and actual server URL/slug
4. Clarified the two integration paths: Retell Custom Functions (individual URLs per tool) vs MCP Server (single JSON-RPC 2.0 endpoint) — both share the same backend

---

## 2026-03-08 — Retell Agent Import JSON File

**Prompt:** "Can you create a retell formatted JSON file that can be used with the Import feature of Retell?"

**Actions:**
1. Created `docs/retell-agent-import.json` — complete Retell-compatible import file containing:
   - LLM configuration with full CRM-aware prompt (general_prompt)
   - begin_message for the initial greeting
   - All 21 default_dynamic_variables (restaurant info, CRM guest fields, custom prompt sections)
   - 5 custom function tools (check_availability, make_reservation, lookup_reservation, confirm_reservation, cancel_reservation) with full parameter schemas, descriptions, and execution messages
   - Agent configuration (voice, responsiveness, backchannel, keywords, silence timeout)
   - Post-call analysis fields (call_purpose, reservation_completed, confirmation_code, known_guest)
   - Setup instructions in `_setup_instructions` key
2. Updated `docs/retell-voice-agent-prompt.md` — added Quick Start import instructions section above manual setup steps

---

## 2026-03-08 — Rewrite Retell Prompt for CRM Webhook Integration

**Prompt:** "Please rewrite the retell prompt to support data from the CRM system being passed into the prompt from the webhook."

**Actions:**
1. Rewrote the Retell agent prompt in `docs/retell-voice-agent-prompt.md` to use all dynamic variables from the call begin webhook:
   - Added "Caller Information" section with conditional logic for known vs unknown guests
   - Added "Known Guest Profile" block listing all CRM fields (name, tags, dietary, allergies, seating pref, favorite server, notes, visit/no-show counts)
   - Added "How to Use This Guest Profile" instructions teaching the agent to greet by name, pre-fill details, respect preferences, mention favorite server, and use tags
   - Added "Restaurant Information" section using `voice_agent_specials` and `voice_agent_custom_info`
   - Split conversation flows into Known Guest vs New Caller paths (greeting, reservation making, reservation lookup)
   - Known guests get pre-filled name/phone/email/preferences with confirmation instead of collection
   - Known guests get automatic reservation lookup by phone without asking for confirmation code
   - Added rules: skip empty variables, pre-fill over re-ask, caller corrections override CRM data, mention specials naturally
2. Updated Dynamic Variables section to document the full webhook-driven approach with a table of all variables
3. Updated configuration steps to reference webhook setup

---

## 2026-03-08 — Retell Webhook Endpoints (Call Begin & Call End)

**Prompt:** "Please create the URL for the Retell webhook that fires when the call begins and the call ends. When the call begins we will get the standard variables from the agent including from_number and to_number. to_number needs to be linked to the restaurant so the right information can be returned. We need to be able to return custom sections of the prompt and if the from_number is in the customer contacts that information should be passed to the agents."

**Actions:**
1. Added `normalizePhone()` and `getRestaurantByPhone()` to `helpers/restaurant.php` — strips phone numbers to digits-only so Retell's E.164 format (`+15551234567`) matches stored numbers in any format
2. Created `html/api/retell/webhook-call-begin.php` — matches `to_number` to a restaurant, looks up `from_number` in that restaurant's guest contacts, loads custom prompt settings, returns `dynamic_variables` with restaurant info, full guest CRM data (name, tags, dietary, allergies, seating pref, visit count, etc.), and custom prompt sections
3. Created `html/api/retell/webhook-call-end.php` — logs call metadata (duration, status, disconnection reason) and transcript to `activity_log`
4. Updated `html/partials/settings/integrations.php` — added webhook URLs table and "Voice Agent — Custom Prompt Sections" card with 3 textarea fields (greeting, specials, custom info)
5. Updated `html/partials/settings/save-integrations.php` — added `save_prompt_sections` action handler
6. Updated `docs/retell-voice-agent-prompt.md` — added webhook documentation with example response payload

---

## 2026-03-08 — Bearer Token Generate & Copy for Admin

**Prompt:** "Please add the functionality in the app for admin or super-admin roles to create bearer tokens. Add a button to automatically copy it to the clipboard."

**Actions:**
1. Enhanced the MCP Bearer Token field on the Integrations settings page (`html/partials/settings/integrations.php`):
   - Added Show/Hide toggle button (eye icon)
   - Added Copy to Clipboard button with "Copied" feedback
   - Enhanced Generate button to auto-copy to clipboard and reveal the token
   - Made field full-width (`col-12`) with monospace font for readability
   - Added helper text reminding to save after generating
2. Page already restricted to admin role via `requireAdmin()`

---

## 2026-03-08 — Updated Retell Voice Agent Prompt Doc

**Prompt:** "I want the MCP servers written in PHP. I believe APIs and MCP servers were in the original plan so check that before building new MPC servers."

**Actions:**
1. Verified existing API infrastructure already built (Prompts 22-24 from retell-voice-api-plan.md):
   - `html/api/mcp/server.php` — JSON-RPC 2.0 MCP server with Bearer token auth
   - `helpers/voice-api.php` — shared service layer (5 functions)
   - `html/api/retell/*.php` — 6 Retell Custom Functions endpoints with HMAC auth
   - `helpers/retell-auth.php` — Retell signature verification
2. Updated `docs/retell-voice-agent-prompt.md` to match actual implementation:
   - Fixed tool names (`make_reservation` not `create_reservation`)
   - Fixed parameter names (`guest_name`/`guest_phone` not `first_name`/`last_name`)
   - Removed `modify_reservation` (not implemented)
   - Added `confirm_reservation` tool (exists but was missing from doc)
   - Replaced hypothetical `/booking/*.php` endpoints with actual `/api/mcp/server.php` and `/api/retell/*.php` endpoints
   - Added MCP and Retell Custom Functions integration sections with examples
   - Added web call and demo call endpoint references

---

## 2026-03-08 — Terms of Service and Privacy Policy Pages

**Prompt:** "On the landing page we need to create terms of service and privacy policy pages. The pages need to address SMS text messages so we can get A2C approval to send confirmation text messages."

**Actions:**
1. Created `html/terms.php` — Terms of Service page with full SMS section (Section 4) covering: message types, guest consent, frequency, opt-out (STOP), message/data rates, HELP keyword, supported carriers, and restaurant obligations under TCPA
2. Created `html/privacy.php` — Privacy Policy page with dedicated SMS privacy section (Section 4) covering: phone number collection/use, message types, consent mechanism, opt-out process, explicit no-sell/no-share of phone numbers, SMS delivery provider disclosure
3. Updated landing page footer — replaced "Get In Touch" column with "Legal" column containing Terms, Privacy, and support email links. Added Terms/Privacy links to footer bottom bar.
4. Both pages match ZozoCal design (Inter font, gradient hero, brand navbar, dark footer)

---

## 2026-03-07 — Accept Invitation Flow for Staff Users

**Prompt:** "When I create a new staff user, I need register.php to have a button that says 'Accept Invitation' and allow the password to be created for the new user that was invited by admin user."

**Actions:**
1. Updated `register.php` — added tabbed UI with "Create Account" and "Accept Invitation" tabs. URL param `?invite` auto-switches to the invite tab.
2. Updated `partials/settings/save-user.php` — password is now optional when adding staff. If no password is set, stores `!INVITED` marker hash (user can't log in until they accept).
3. Updated `partials/settings/user-form.php` — password field no longer required, help text explains the invitation flow.
4. Created `partials/auth/accept-invite.php` — handler that finds user by email, verifies they have the `!INVITED` marker, lets them set their password, and logs them in.

---

## 2026-03-07 — Register Page and Get Started Flow

**Prompt:** "The 'Get Started' button on the landing page needs to go to register.php and register.php needs to be updated for this application. It is still using the template and does not work properly."

**Actions:**
1. Updated all "Get Started" links on landing page (`index.php`) to point to `/register.php` instead of `/login.php`
2. Rewrote `html/register.php` — matches ZozoCal design (Inter font, gradient background, rounded card, brand link). Fields: First Name, Last Name, Restaurant Name, Email, Password, Confirm Password
3. Rewrote `html/partials/auth/register.php` — works with restaurant_reservations database:
   - Creates user in `users` table with hashed password
   - Creates restaurant in `restaurants` table with auto-generated slug
   - Links user as admin via `user_restaurants`
   - Seeds defaults: settings, sections, turn times, operating hours (same as platform admin create)
   - Auto-logs in and redirects to `/app.php`
4. Removed old SalesCoach references (plans, organizations, invitations, Skilliks branding)

---

## 2026-03-07 — Landing Page for ZozoCal

**Prompt:** "We need to create a new landing page for the application instead of going directly to the login page. The application name is zozocal.com and is a restaurant SaaS application for restaurant reservations. Use OpenTable reservation management page as guidance."

**Actions:**
1. Replaced `html/index.php` (was a simple redirect to login) with a full marketing landing page
2. Landing page sections inspired by OpenTable's reservation management page:
   - Fixed navbar with ZozoCal brand, nav links, Login/Get Started CTAs
   - Hero section with headline, subtitle, and mock reservation dashboard card with floating badges
   - Stats bar: 24/7 Online Bookings, -40% No-Shows, +25% Table Utilization, 5-Star Experience
   - Features grid (6 cards): Online Reservations, Floor Plan, Guest CRM, Waitlist, Notifications, Reports
   - How It Works (3 steps): Set Up, Accept Reservations, Manage & Grow
   - Benefits section with check lists and visual mock-ups (guest booking + team dashboard)
   - Final CTA section with gradient background
   - Footer with links and copyright
3. Uses the same purple/blue gradient brand (#667eea to #764ba2) from the login page
4. Fully responsive with mobile breakpoints
5. All divs have unique IDs per CLAUDE.md
6. Logged-in users still redirect to /app.php automatically

---

## 2026-03-07 — Special Date Reservations List with Confirmation Voice Agent

**Prompt:** "Next for Special dates we want to have a button to show all reservations for the date and then launch a confirmation voice agent from each reservation in the list that uses agent id agent_59677a1c2a26618caabd3fb02f"

**Actions:**
1. Added green calendar button to each special date row in `special-dates.php` that loads the reservations modal
2. Created `html/partials/settings/special-date-reservations.php` — modal showing all non-cancelled reservations for the date with time, guest, phone, party size, table, status, confirmation code
3. Each reservation row has a "Call" button that launches the confirmation voice agent (`agent_59677a1c2a26618caabd3fb02f` default, or the date's `outbound_voice_agent` if set) with full reservation context passed as metadata

---

## 2026-03-07 — Standalone Retell Voice Agent Demo Page

**Prompt:** "Next create a stand-alone page to demonstrate clicking a button get a website. create demo.php and use the contents of apartments.com/arrive-michigan-avenue to create the design. Have a button on it that calls retell voice agent with the id of agent_92e939c02514b77d812f5b7240"

**Actions:**
1. Created `html/demo.php` — standalone apartment listing page inspired by Apartments.com design
2. Includes: hero image, pricing bar, section tabs (Overview, Floor Plans, Amenities, Location, Reviews), contact sidebar
3. Two "Speak with AI Agent" CTA buttons (pricing bar + sidebar) that call Retell agent `agent_92e939c02514b77d812f5b7240`
4. Passes property metadata (name, address, price range, unit types) to the agent as dynamic variables
5. Self-contained: own Bootstrap 5 CSS/JS, Retell SDK, call overlay UI, no dependency on app.php
6. All divs have unique IDs per CLAUDE.md instructions

---

## 2026-03-07 — Add outbound_voice_agent field to Special Dates

**Prompt:** "Next I added a column to the special dates table called outbound_voice_agent. I need to you to add that field to the special dates maintenance forms."

**Actions:**
1. Added "Voice Agent" column to the special dates list table in `special-dates.php`
2. Added "Outbound Voice Agent" text input to the special date form in `special-date-form.php`
3. Updated `save-special-date.php` to read, validate, and persist `outbound_voice_agent` on both INSERT and UPDATE

---

## 2026-03-07 — Retell Voice Agent Web Calling Infrastructure

**Prompt:** "Next we want to install the infrastructure required to call Retell Voice agents by clicking buttons on the application. We want the process to include sending information to the voice agent when the button is clicked and allowing the conversation to happen through the computer."

**Actions:**
1. Fixed `html/api/retell/create-web-call.php` — removed dependency on missing `config/retell.php`, now reads API key and agent ID from settings table, supports passing metadata (guest name, phone, reservation details) as `retell_llm_dynamic_variables`
2. Added Retell Web SDK (`retell-client-js-sdk` v3.5.1) and `retell-integration.js` script tags to `html/app.php`
3. Rewrote `html/assets/js/retell-integration.js` — full call overlay UI with connecting/active/agent-talking states, call timer, contextual info display, microphone audio through browser
4. Added Retell call overlay CSS styles to `html/assets/css/kobie-custom.css` — animated pulse ring, backdrop blur overlay, status indicators
5. Added "Call AI Agent" button to `html/partials/reservations/detail.php` — passes guest name, phone, email, party size, date, time, confirmation code, status, special requests, and restaurant slug to the agent
6. Added "Call AI Agent" button to `html/partials/guests/detail.php` — passes guest name, phone, email, and restaurant slug
7. Added "Call AI Agent" button to each waitlist entry in `html/partials/waitlist/index.php` — passes guest name, phone, party size, and restaurant slug
8. Added `retell_agent_id` setting to `html/partials/settings/integrations.php` and `save-integrations.php` — admins can configure their Retell Agent ID in Settings > Integrations

---

## 2026-03-05 — Project Planning Session

**Prompt:** "This project is migrating an existing application to a new Restaurant reservation system that has requirements defined in restaurant-reservation-requirements.md and the database defined in database-documentation.md. Please read the existing codebase and begin creating a plan to build the application. Do not start building, instead create a series of prompts to build the application."

**Actions:**
1. Read restaurant-reservation-requirements.md (full requirements document with 4 phases)
2. Read database-documentation.md (15 tables, full schema)
3. Read restaurant_reservations.sql (executable DDL with seed data)
4. Explored existing codebase: app.php (SalesCoach Pro HTMX shell), login.php, auth.php, csrf.php, session.php, database.php, config files
5. Explored design template folder for Kobie Bootstrap 5 theme patterns
6. Clarified with user:
   - Full replacement of SalesCoach content (keep infrastructure)
   - New database (restaurant_reservations)
   - All 4 phases covered
   - Full Twilio/SMTP notification integration
7. Created design document: docs/plans/2026-03-05-restaurant-reservation-design.md
8. Created 20 build prompts in tasks/todo.md organized across 4 phases:
   - Phase 1 (Prompts 1-5): Foundation — DB setup, auth, app shell, settings, user management
   - Phase 2 (Prompts 6-9): Table & Schedule — Sections, tables, hours, turn times, special dates
   - Phase 3 (Prompts 10-14): Reservation Core — Availability engine, CRUD, calendar, status workflow, floor plan
   - Phase 4 (Prompts 15-20): Guest Experience — Public booking, self-service, CRM, waitlist, notifications, dashboard/reports

**Files Created:**
- docs/plans/2026-03-05-restaurant-reservation-design.md
- docs/activity.md
- tasks/todo.md (replaced old SalesCoach todo)

## 2026-03-05 — Multi-Tenant SaaS Conversion

**Prompt:** "Please carefully review the design documents and restaurant_reservations.sql and update the database design to make the application support multiple restaurants as a SaaS application. The existing build prompts and database design assume the system is for a single restaurant. We want the system to support multiple restaurants. Please make the required changes to the sql file and the build prompts."

**Actions:**
1. Read all design documents: restaurant_reservations.sql, database-documentation.md, restaurant-reservation-requirements.md, design document, tasks/todo.md
2. Created plan in tasks/todo.md and verified with user
3. Updated restaurant_reservations.sql (v2.0 Multi-Tenant SaaS):
   - Added `restaurants` table as central tenant entity (name, slug, contact info, timezone)
   - Added `user_restaurants` junction table (per-restaurant roles: owner, manager, host)
   - Moved role from `users` table to `user_restaurants`; added `is_platform_admin` to `users`
   - Added `restaurant_id` FK to: settings, sections, tables, guests, reservations, waitlist, operating_hours, special_dates, turn_times, notifications_log, activity_log, email_templates
   - Updated unique constraints to be scoped by restaurant_id
   - Removed restaurant profile fields from settings (now on restaurants table directly)
   - Updated seed data to reference default restaurant (id=1)
4. Updated tasks/todo.md with 21 build prompts (was 20):
   - All prompts updated to scope queries by restaurant_id
   - Prompt 1: Added helpers/restaurant.php for tenant context management
   - Prompt 2: Auth updated for per-restaurant roles and restaurant switching
   - Prompt 3: App shell includes restaurant switcher and platform admin section
   - Prompt 4: Profile edits restaurants table directly
   - Prompt 5: User management via user_restaurants
   - Prompts 6-20: All queries scoped by restaurant_id
   - Prompt 15-16: Public booking uses restaurant slug
   - Added Prompt 21: Platform restaurant management (CRUD for restaurants)
5. Updated database-documentation.md:
   - Added restaurants and user_restaurants table docs
   - Updated ER diagram for multi-tenant architecture
   - Updated all table docs with restaurant_id column
   - Updated availability engine query with restaurant_id
   - Updated key design decisions section
6. Updated restaurant-reservation-requirements.md:
   - Updated Section 1.2 Scope for multi-tenant SaaS
   - Updated Section 2 Roles: platform_admin + per-restaurant roles (owner/manager/host)
   - Updated Section 4 database table overview
   - Updated Section 6.4 from "Scalability Considerations" to "Multi-Tenant Architecture"
7. Updated docs/plans/2026-03-05-restaurant-reservation-design.md for multi-tenant architecture

**Files Modified:**
- restaurant_reservations.sql (complete rewrite for multi-tenant)
- tasks/todo.md (21 updated build prompts)
- database-documentation.md (multi-tenant schema docs)
- restaurant-reservation-requirements.md (multi-tenant requirements)
- docs/plans/2026-03-05-restaurant-reservation-design.md (updated design doc)

## 2026-03-05 — Prompt 1: Database Setup and Configuration

**Prompt:** "Please begin prompt #1" / "Do the steps in Prompt #1 without breaking the current config files or database"

**Actions:**
1. Database already created by user — skipped SQL execution
2. config/database.php already configured and validated — left untouched
3. Created helpers/db.php — db() shortcut function for PDO connection
4. Created helpers/restaurant.php — multi-tenant context helpers:
   - getRestaurantId(), setRestaurantId(), getRestaurant(), getRestaurantBySlug(), getUserRestaurants(), getUserRole()
5. Verified all helpers work against live database — restaurants table reads correctly, all functions return expected values

**Critical instruction from user:** NEVER modify config/database.php or the database directly. Saved to memory.

**Files Created:**
- helpers/db.php
- helpers/restaurant.php

## 2026-03-06 — Prompt 2: Authentication Adaptation

**Prompt:** "Proceed to prompt #2 and do not change the database."

**Actions:**
1. Updated helpers/auth.php — replaced SalesCoach role logic with multi-tenant restaurant roles:
   - login_user() now loads restaurant memberships, sets current_restaurant_id/role/name in session
   - Added isOwner(), isManager(), isHost(), isPlatformAdmin() role checks
   - Added switchRestaurant() for changing restaurant context
   - Added requireOwner(), requireManager(), requirePlatformAdmin() guards
   - Added currentRestaurantId() helper
   - Removed org_id, plan_id, and SalesCoach-specific session data
2. Updated html/partials/auth/login.php — authenticates directly against users table via PDO (no User model), uses password_verify()
3. Simplified html/login.php — clean Kobie-themed login page titled "Restaurant Reservation System", removed SalesCoach branding
4. Updated html/logout.php — removed User model dependency, redirects to /login.php
5. Replaced html/index.php — simple redirect to /app.php (logged in) or /login.php (not logged in)
6. Removed SalesCoach-specific files: helpers/plan.php, helpers/retell.php, helpers/scoring.php, config/retell.php
7. Verified login flow works: admin@restaurant.com / password (note: seed hash is for 'password', not 'changeme123' as SQL comment says)

**Note:** Seed password hash in DB is for 'password' not 'changeme123'. User manages DB directly.

**Files Modified:**
- helpers/auth.php
- html/partials/auth/login.php
- html/login.php
- html/logout.php
- html/index.php

**Files Removed:**
- helpers/plan.php
- helpers/retell.php
- helpers/scoring.php
- config/retell.php

## 2026-03-06 — Prompt 3: App Shell and Sidebar Navigation

**Prompt:** "Continue with the next prompt."

**Actions:**
1. Rewrote html/app.php — replaced SalesCoach sidebar with restaurant reservation navigation:
   - RESERVATIONS: Dashboard, Reservations, Floor Plan, Waitlist
   - GUESTS: Guest Directory
   - MANAGEMENT (manager+owner): Table Setup, Sections, Operating Hours, Reports
   - SETTINGS (owner): Restaurant Profile, Reservation Rules, Notifications, Staff Users
   - PLATFORM (platform_admin): Manage Restaurants
   - Logout
2. Updated page title to show current restaurant name
3. Updated header with user name/role and restaurant switcher dropdown (for multi-restaurant users)
4. Removed all SalesCoach JS (Retell SDK, retell-integration.js, etc.)
5. Updated HTMX from 1.9.10 to 2.0.8
6. Created dashboard placeholder partial (partials/dashboard/index.php) — "Coming Soon" card
7. Created partials/auth/switch-restaurant.php — HTMX endpoint to switch restaurant context
8. All divs have unique id attributes
9. Created required directories for future partials

**Files Modified:**
- html/app.php (complete rewrite)
- html/partials/dashboard/index.php (replaced SalesCoach dashboard)

**Files Created:**
- html/partials/auth/switch-restaurant.php

## 2026-03-06 — Prompt 4: Restaurant Profile Settings

**Prompt:** "Continue with Prompt #4"

**Actions:**
1. Created html/partials/settings/profile.php — Restaurant profile edit form:
   - Fields: Name, Slug (read-only), Phone, Email, Address, City, State, ZIP, Website, Timezone dropdown
   - Bootstrap 5 card layout, HTMX hx-post to save
   - CSRF protection, owner-only access
2. Created html/partials/settings/save-profile.php — POST handler:
   - Validates required fields (name, timezone), email format, URL format
   - Updates restaurants table WHERE id = current_restaurant_id
   - Updates session restaurant name on save
   - Returns success/error feedback via HTMX

**Files Modified:**
- html/partials/settings/profile.php (replaced placeholder)

**Files Created:**
- html/partials/settings/save-profile.php

## 2026-03-06 — Prompt 5: Staff User Management

**Prompt:** "Please continue with Prompt #5"

**Actions:**
1. Created html/partials/settings/users.php — Staff user list page:
   - Queries user_restaurants JOIN users WHERE restaurant_id = current_restaurant_id
   - Columns: Name, Email, Role (color-coded badge), Status (Active/Inactive), Last Login, Actions
   - "Add Staff Member" button loads modal form via HTMX
   - Edit and Toggle (activate/deactivate) action buttons per row
   - Owner-only access (requireOwner)
   - Auto-refreshes via HX-Trigger: refreshUserList event
2. Created html/partials/settings/user-form.php — Add/Edit user modal form:
   - Fields: First Name, Last Name, Email, Phone, Role dropdown (owner/manager/host), Password
   - Email read-only on edit; for new users, notes that existing emails will be linked
   - Password required for new users, optional for edits
   - HTMX hx-post to save-user.php
3. Created html/partials/settings/save-user.php — POST handler:
   - Validates all fields, email format, password strength
   - For new users: checks if email exists — if so, links to restaurant; if not, creates user + membership
   - For edits: updates user record and role in user_restaurants
   - Uses transactions for data integrity
   - CSRF protection, returns success/error messages
4. Created html/partials/settings/toggle-user.php — Toggle user_restaurants.is_active:
   - Validates ownership of restaurant membership
   - Prevents self-deactivation
   - Soft deactivate/activate for this restaurant only
   - CSRF protection

**Files Created:**
- html/partials/settings/users.php
- html/partials/settings/user-form.php
- html/partials/settings/save-user.php
- html/partials/settings/toggle-user.php

## 2026-03-06 — Prompt 6: Sections CRUD

**Prompt:** "Please continue with Prompt #6"

**Actions:**
1. Created html/partials/sections/list.php — Section management page:
   - Queries sections WHERE restaurant_id = current_restaurant_id
   - Table: Order, Name, Description, Hours Override, Status, Actions
   - "Add Section" button loads modal form via HTMX
   - Edit and Toggle Active buttons per row
   - Manager+ access (requireManager)
   - Auto-refreshes via HX-Trigger: refreshSectionList
2. Created html/partials/sections/form.php — Add/Edit section modal:
   - Fields: Name, Description, Display Order, Open/Close Time overrides, Is Active
   - Hours override help text explains default behavior
3. Created html/partials/sections/save.php — POST handler:
   - Validates name required, unique name per restaurant, hour pairing
   - Inserts/updates with restaurant_id scoping
   - CSRF protection
4. Created html/partials/sections/toggle.php — Toggle is_active:
   - Validates section belongs to current restaurant
   - CSRF protection

Sidebar already had Sections link from Prompt 3 — no changes needed to app.php.

**Files Created:**
- html/partials/sections/list.php
- html/partials/sections/form.php
- html/partials/sections/save.php
- html/partials/sections/toggle.php

## 2026-03-06 — Prompt 7: Tables CRUD

**Prompt:** "Please continue with Prompt #7"

**Actions:**
1. Created html/partials/tables/list.php — Table management page:
   - Queries tables JOIN sections WHERE restaurant_id = current_restaurant_id
   - Columns: Name, Section, Seats (min-max), Status (color-coded), Sort Order, Notes, Active, Actions
   - Section filter dropdown to filter by section
   - "Add Table" button loads modal form via HTMX
   - Edit and Toggle Active buttons per row
   - Manager+ access, auto-refreshes via HX-Trigger: refreshTableList
2. Created html/partials/tables/form.php — Add/Edit table modal:
   - Fields: Table Name, Section (dropdown from active sections), Min/Max Seats, Status, Sort Order, Notes, Is Active
   - Warns if no active sections exist
3. Created html/partials/tables/save.php — POST handler:
   - Validates all fields, unique table name per restaurant, min <= max seats
   - Validates section_id belongs to same restaurant
   - CSRF protection
4. Created html/partials/tables/toggle.php — Toggle is_active:
   - Validates table belongs to current restaurant
   - CSRF protection

Sidebar already had Table Setup link from Prompt 3.

**Files Created:**
- html/partials/tables/list.php
- html/partials/tables/form.php
- html/partials/tables/save.php
- html/partials/tables/toggle.php

## 2026-03-06 — Prompt 8: Operating Hours Configuration

**Prompt:** "Please continue with Prompt #8"

**Actions:**
1. Created html/partials/settings/hours.php — Operating hours management:
   - Queries operating_hours WHERE restaurant_id = current_restaurant_id
   - Displays weekly grid grouped by day (Sunday-Saturday)
   - Each day shows service periods with Open, Close, First Seating, Last Seating, Status
   - "Add Service Period" button loads modal form via HTMX
   - Per-row Edit, Toggle Active, and Delete buttons
   - Days with no services show "Closed" badge
   - Manager+ access, auto-refreshes via HX-Trigger
2. Created html/partials/settings/hours-form.php — Add/Edit service period modal:
   - Fields: Day of Week dropdown, Service Name, Open/Close Time, First/Last Seating, Is Active
   - Help text explains seating time constraints
3. Created html/partials/settings/save-hours.php — Multi-action POST handler:
   - action=save: validates all times (open < close, first >= open, last <= close, first < last), unique day+service per restaurant
   - action=toggle: toggles is_active
   - action=delete: removes service period
   - All scoped to restaurant_id, CSRF protected

Sidebar already had Operating Hours link from Prompt 3.

**Files Created:**
- html/partials/settings/hours.php
- html/partials/settings/hours-form.php
- html/partials/settings/save-hours.php

## 2026-03-06 — Prompt 9: Turn Times and Special Dates

**Prompt:** "Please continue with Prompt #9"

**Actions:**
1. Created html/partials/settings/turn-times.php — Turn time configuration list:
   - Displays party size range, service period, duration, buffer, total time
   - Add, Edit, Delete actions via HTMX
2. Created html/partials/settings/turn-time-form.php — Add/Edit turn time modal:
   - Fields: Min/Max Party Size, Service Period (all or specific), Duration, Buffer
3. Created html/partials/settings/save-turn-time.php — POST handler:
   - action=save: validates ranges, uniqueness, inserts/updates
   - action=delete: removes turn time rule
4. Created html/partials/settings/special-dates.php — Special dates list:
   - Displays date, label, type (color-coded badge), closed status, custom hours, max covers
   - Add, Edit, Delete actions via HTMX
5. Created html/partials/settings/special-date-form.php — Add/Edit special date modal (modal-lg):
   - Fields: Date picker, Type dropdown, Label, Is Closed checkbox
   - Optional custom hours (open, close, first/last seating), max covers, notes
6. Created html/partials/settings/save-special-date.php — POST handler:
   - action=save: validates required fields, custom hours pairing, time ordering, uniqueness
   - action=delete: removes special date
7. Added Turn Times and Special Dates links to sidebar under MANAGEMENT section in app.php

All files: CSRF protection, restaurant_id scoping, manager+ access, unique div IDs.

**Files Created:**
- html/partials/settings/turn-times.php
- html/partials/settings/turn-time-form.php
- html/partials/settings/save-turn-time.php
- html/partials/settings/special-dates.php
- html/partials/settings/special-date-form.php
- html/partials/settings/save-special-date.php

**Files Modified:**
- html/app.php (added Turn Times and Special Dates sidebar links)

## 2026-03-06 — Prompt 10: Availability Engine

**Prompt:** "Please continue with Prompt #10"

**Actions:**
1. Created helpers/availability.php — Core availability calculation engine:
   - getRestaurantSetting() — loads settings from settings table with static cache
   - getTurnTime($restaurantId, $partySize, $servicePeriod) — finds turn time + buffer, tries specific service period first then falls back to 'all'
   - getOperatingHoursForDate($restaurantId, $date) — checks special_dates for overrides/blackouts, then falls back to regular operating_hours for the day of week
   - getAvailableSlots($restaurantId, $date, $partySize) — generates all time slots at configured interval, counts available tables per slot, applies overbooking protection (max_covers_per_slot, online_table_hold_percent)
   - countAvailableTables() — counts tables fitting party size that don't have overlapping reservations (uses TIME_TO_SEC overlap logic from database docs)
   - getCoversAtSlot() — sums party sizes of overlapping reservations for cover limits
   - findAvailableTable($restaurantId, $date, $time, $partySize) — finds smallest available table (smallest-table-first logic)
2. Created html/partials/reservations/check-availability.php — HTMX endpoint:
   - Accepts GET params: date, party_size
   - Validates date not in past
   - Returns slots grouped by service period as clickable buttons
   - Available slots show table count badge, unavailable slots are disabled
   - JavaScript selectTimeSlot() populates hidden form fields for reservation creation

**Files Created:**
- helpers/availability.php
- html/partials/reservations/check-availability.php

## 2026-03-06 — Prompt 11: Staff Reservation Creation

**Prompt:** "Please continue with Prompt #11"

**Actions:**
1. Created html/partials/reservations/create-form.php — Multi-step reservation form:
   - Step 1: Date picker, party size, "Check Availability" button (triggers HTMX)
   - Step 2: Available time slots displayed from check-availability endpoint
   - Step 3: Guest search (HTMX keyup with 300ms delay) + name/phone/email fields
   - Step 4: Table assignment (optional, dropdown or auto-assign), source, special requests, internal notes
   - Step 5: Submit button, cancel navigates to calendar
2. Created html/partials/reservations/save.php — POST handler:
   - Validates all required fields (date, time, party size, guest name)
   - Finds or creates guest record (matches by email then phone within restaurant)
   - Auto-assigns table via findAvailableTable() if not manually selected
   - Generates 10-char confirmation code via bin2hex(random_bytes(5))
   - Staff-created = auto-confirmed status
   - Logs activity to activity_log with restaurant_id
   - Returns success card with confirmation details and navigation buttons
3. Created html/partials/reservations/guest-search.php — HTMX guest search:
   - Searches guests by name, email, phone within current restaurant
   - Returns clickable list items that populate the form fields via JavaScript
   - Minimum 2 characters to trigger search

**Files Created:**
- html/partials/reservations/create-form.php
- html/partials/reservations/save.php
- html/partials/reservations/guest-search.php

## 2026-03-06 — Prompt 12: Reservation Calendar and List Views

**Prompt:** "Please continue with Prompt #12"

**Actions:**
1. Created html/partials/reservations/calendar.php — Daily reservation calendar:
   - Date navigation (prev/next day, date picker, today button)
   - Stats row: Total Reservations, Total Covers, Available Tables
   - Filters: status dropdown, section dropdown
   - Timeline grouped by time slot with reservation cards
   - Cards show: Guest Name, Party Size, Table, Status (color-coded badge)
   - Click card loads detail view via HTMX
   - "New Reservation" and "List View" buttons
2. Created html/partials/reservations/list.php — Searchable reservation list:
   - Search by name, phone, email, confirmation code (HTMX keyup 400ms)
   - Date range filters, status filter
   - Paginated results (25 per page) with HTMX page navigation
   - Table: Code, Date/Time, Guest, Party, Table, Status, Actions
   - View detail button per row
3. Created html/partials/reservations/feed.php — HTMX redirect endpoint for calendar refresh

**Files Created:**
- html/partials/reservations/calendar.php
- html/partials/reservations/list.php
- html/partials/reservations/feed.php

## 2026-03-06 — Prompt 13: Reservation Detail and Status Workflow

**Prompt:** "Please continue with Prompt #13"

**Actions:**
1. Created html/partials/reservations/detail.php — Full reservation detail view:
   - Validates reservation belongs to current restaurant
   - Info card: date, time, party size, table, source, created by, timestamps
   - Guest card with profile link, phone, email, visit/noshow counts, tags
   - Status action buttons for valid transitions (pending→confirmed/cancelled, confirmed→seated/cancelled/no_show, seated→completed)
   - Table reassignment dropdown (inline HTMX)
   - Editable special requests and internal notes (inline save on change)
   - Activity history timeline from activity_log
   - Edit button loads inline edit form
2. Created html/partials/reservations/status-update.php — Status change handler:
   - Validates transition map, updates status + timestamps (seated_at, completed_at, cancelled_at)
   - Updates guest stats: noshow_count on no_show, visit_count + last_visit_at on completed
   - Logs with old/new JSON values
3. Created html/partials/reservations/edit-form.php — Inline edit form:
   - Fields: Date, Time, Party Size, Table, Turn Time Override, Special Requests, Internal Notes
4. Created html/partials/reservations/update.php — Multi-action POST handler:
   - action=edit: full reservation update with activity logging
   - action=reassign_table: table reassignment with validation
   - action=update_requests / update_notes: inline note saves

**Files Created:**
- html/partials/reservations/detail.php
- html/partials/reservations/status-update.php
- html/partials/reservations/edit-form.php
- html/partials/reservations/update.php

## 2026-03-06 — Prompt 14: Floor Plan View

**Prompt:** "Please continue with Prompt #14"

**Actions:**
1. Created html/partials/tables/floor-plan.php — Visual floor plan:
   - Tables grouped by section as card grid
   - Each table card shows: name, seats, real-time status (available/occupied/reserved/blocked)
   - Occupied tables show guest name, party size, remaining time (red if <=10 min)
   - Reserved tables show guest name and reservation time
   - Click available table → walk-in seating form
   - Click occupied/reserved table → reservation detail
   - Auto-refreshes every 30s via hx-trigger
   - Color-coded legend at bottom
2. Created html/partials/tables/table-status.php — Single table status endpoint
3. Created html/partials/tables/assign-walkin.php — Quick walk-in seating:
   - Modal form with table info, guest name, party size
   - Creates guest + reservation (source=walk_in, status=seated, seated_at=NOW())
   - Activity logging, confirmation code generation

**Files Created:**
- html/partials/tables/floor-plan.php
- html/partials/tables/table-status.php
- html/partials/tables/assign-walkin.php

## 2026-03-06 — Prompt 15: Public Booking Widget

**Prompt:** "Please continue with Prompt #15"

**Actions:**
1. Created html/booking/index.php — Public-facing booking page (standalone, no auth):
   - URL: /booking/index.php?restaurant=slug-name
   - Looks up restaurant by slug, shows 404 if not found/inactive
   - Clean mobile-friendly design with gradient header showing restaurant name
   - Step 1: Date picker + party size selector + Check Availability button
   - Step 2: Available slots loaded via HTMX, click to select
   - Step 3: Guest info form (name, phone, email, special requests) — appears on slot selection
   - Step 4: Confirm → shows confirmation page
   - Standalone HTML head with Bootstrap 5 + HTMX
2. Created html/booking/check-slots.php — Public availability endpoint:
   - Validates: slug, party size limit, date range (advance_booking_max_days), same-day cutoff, min advance hours
   - Uses getAvailableSlots() from availability engine
   - Returns slots grouped by service period as clickable buttons
3. Created html/booking/confirm.php — POST handler:
   - Rate limiting: max 5 bookings per IP per hour (session-based)
   - Validates all inputs, email format, party size limit
   - Find or create guest within restaurant
   - Creates reservation: source='online', status='pending'
   - Shows confirmation card with code and details
   - CSRF protection, activity logging

**Files Created:**
- html/booking/index.php
- html/booking/check-slots.php
- html/booking/confirm.php

## 2026-03-06 — Prompt 16: Guest Self-Service (Lookup, Modify, Cancel)

**Prompt:** "Please continue with Prompt #16"

**Actions:**
1. Created html/booking/lookup.php — Reservation lookup page (standalone, no auth):
   - URL: /booking/lookup.php?restaurant=slug-name
   - Search by confirmation code + last name via HTMX
   - Returns reservation details card with status badge, date/time, party size, guest info
   - Shows Modify and Cancel buttons for pending/confirmed reservations
   - Links pass code + last_name as query params to modify/cancel pages
2. Created html/booking/modify.php — Reservation modification page (standalone, no auth):
   - Validates reservation exists and is modifiable (pending/confirmed status)
   - Shows current reservation details
   - Date/party size picker with "Check Availability" button (reuses check-slots.php)
   - Slot selection populates hidden form, submit saves changes
   - POST handler updates reservation_date, reservation_time, party_size
   - Activity logging with old/new JSON values
3. Created html/booking/cancel.php — Reservation cancellation page (standalone, no auth):
   - Validates reservation exists, shows reservation details
   - Displays cancellation_policy from restaurant settings
   - Confirm cancellation button with JavaScript confirm dialog
   - POST handler sets status='cancelled', cancelled_at=NOW()
   - Activity logging, shows success message with rebook link

All files: CSRF protection, restaurant slug-based lookup, standalone HTML with Bootstrap 5 + HTMX, unique div IDs.

**Files Created:**
- html/booking/lookup.php
- html/booking/modify.php
- html/booking/cancel.php

## 2026-03-06 — Prompt 17: Guest Management (CRM)

**Prompt:** "Please continue with Prompt #17"

**Actions:**
1. Created html/partials/guests/list.php — Guest directory:
   - Searchable table with HTMX keyup search (300ms delay)
   - Columns: Name (clickable → detail), Email, Phone, Tags (color-coded badges), Visits, No-Shows, Last Visit
   - Tag filter dropdown (auto-populated from existing tags)
   - Pagination (25 per page)
   - "Add Guest" button loads modal form
   - Auto-refreshes via HX-Trigger: refreshGuestList
2. Created html/partials/guests/detail.php — Full guest profile:
   - Contact info card with address
   - Stats card: visits, no-shows, last visit
   - Tags: editable badges with add/remove via HTMX (dropdown add, x to remove)
   - Preferences: inline-editable dietary restrictions, allergies, seating preference, favorite server (save on change)
   - Notes: inline-editable textarea (save on change)
   - Reservation history: table of all past/future reservations, clickable to detail
3. Created html/partials/guests/form.php — Add/Edit guest modal:
   - Fields: Name, Email, Phone, Address, City/State/ZIP, Tags, Seating Preference, Dietary, Allergies, Notes
   - HTMX POST to save.php
4. Created html/partials/guests/save.php — Multi-action POST handler:
   - action=save: create/update guest with validation, restaurant_id scoping
   - action=add_tag / remove_tag: tag management, returns refreshed detail view
   - action=update_pref: inline preference save for dietary/allergies/seating/server
   - action=update_notes: inline notes save
   - All actions CSRF protected
5. Created html/partials/guests/search.php — HTMX search endpoint:
   - Searches by name, email, phone within restaurant
   - Returns clickable list items linking to guest detail
   - Minimum 2 characters, limit 10 results

All files: CSRF protection, restaurant_id scoping, unique div IDs.

**Files Created:**
- html/partials/guests/list.php
- html/partials/guests/detail.php
- html/partials/guests/form.php
- html/partials/guests/save.php
- html/partials/guests/search.php

## 2026-03-06 — Prompt 18: Walk-In and Waitlist Management

**Prompt:** "Please continue with Prompt #18"

**Actions:**
1. Created html/partials/waitlist/index.php — Active waitlist page:
   - Queries active entries (waiting/notified) ordered by queue position
   - Table: Position, Guest Name, Party Size, Phone, Wait Time (actual + estimated), Status, Added Time, Actions
   - Action buttons: Notify (SMS), Seat (modal with table selection), Left, No Response
   - Recent activity section showing seated/left/no_response from last 2 hours
   - Auto-refreshes every 15s via hx-trigger
   - Refreshes on HX-Trigger: refreshWaitlist
2. Created html/partials/waitlist/add-form.php — Add to waitlist modal:
   - Guest search (reuses guests/search.php) to link existing guest profiles
   - Fields: Guest Name, Party Size, Phone, Seating Preference, Notes
   - JavaScript intercepts search results to populate form instead of navigating
3. Created html/partials/waitlist/save.php — POST handler:
   - Calculates queue position (max current + 1 within restaurant)
   - Estimates wait time based on available tables, total tables, and average turn time
   - Inserts waitlist record with restaurant_id, activity logging
4. Created html/partials/waitlist/update-status.php — Status change handler:
   - GET action=seat_form: modal with available table selection (filtered by party size)
   - POST status=seated: creates guest (if needed), reservation (walk_in/seated), updates table status, links waitlist to reservation
   - POST status=left/no_response: marks terminal status
   - All within transaction, activity logging
5. Created html/partials/waitlist/notify.php — SMS notification:
   - Updates status to 'notified', records notified_at
   - Sends SMS via Twilio helper if configured, graceful fallback if not
   - Logs to notifications_log (with try/catch for table existence)
   - Activity logging

All files: CSRF protection, restaurant_id scoping, unique div IDs.

**Files Created:**
- html/partials/waitlist/index.php
- html/partials/waitlist/add-form.php
- html/partials/waitlist/save.php
- html/partials/waitlist/update-status.php
- html/partials/waitlist/notify.php

## 2026-03-06 — Prompt 19: Email and SMS Notifications

**Prompt:** "Please continue with Prompt #19"

**Actions:**
1. Created helpers/twilio.php — Twilio SMS via cURL (no Composer):
   - twilioSend() — REST API call with Account SID, Auth Token, From Number from settings table
   - sendSMS() — simple boolean wrapper
2. Created helpers/notifications.php — Full notification service:
   - loadTemplate() — loads from email_templates table, replaces {{placeholders}}
   - getReservationVars() — builds standard placeholder map from reservation + guest + restaurant
   - sendEmail() — PHP mail() with multipart/alternative (HTML + text)
   - logNotification() — logs to notifications_log table
   - sendConfirmationEmail() — confirmation with template fallback to default HTML
   - sendReminderEmail() — reminder with template fallback
   - sendCancellationEmail() — cancellation notice with template fallback
   - sendWaitlistSMS() — SMS via Twilio for waitlist notifications
3. Created html/partials/settings/notifications.php — Notification settings page (replaced SalesCoach version):
   - Toggle switches: confirmation email, reminder email, cancellation email, waitlist SMS
   - From email address, reminder hours before setting
   - Twilio SMS settings: Account SID, Auth Token, From Number
   - Test SMS button with result feedback
   - Link to email template editor
4. Created html/partials/settings/email-templates.php — Email template editor:
   - List of templates with type, subject, status, actions
   - Add/edit form with template type selector, subject, HTML body, text body, active toggle
   - Available placeholders reference card
   - Delete with confirmation
5. Integrated notifications into existing flows:
   - html/booking/confirm.php — sends confirmation email after online booking
   - html/booking/cancel.php — sends cancellation email after guest self-cancellation
   - html/partials/reservations/status-update.php — sends confirmation on confirm, cancellation on cancel
   - html/partials/waitlist/notify.php — uses sendWaitlistSMS() via notification service
6. Created helpers/send-reminders.php — Cron-compatible reminder script:
   - Iterates all active restaurants
   - Checks each restaurant's reminder_hours_before setting
   - Finds confirmed/pending reservations within reminder window not yet reminded
   - Sends reminder emails, logs results
7. Fixed html/partials/waitlist/notify.php — corrected notification_type column name (was 'type')

**Files Created:**
- helpers/twilio.php
- helpers/notifications.php
- helpers/send-reminders.php
- html/partials/settings/email-templates.php

**Files Modified:**
- html/partials/settings/notifications.php (complete rewrite from SalesCoach)
- html/booking/confirm.php (added confirmation email)
- html/booking/cancel.php (added cancellation email)
- html/partials/reservations/status-update.php (added notification emails)
- html/partials/waitlist/notify.php (integrated sendWaitlistSMS, fixed column name)

## 2026-03-06 — Prompt 20: Dashboard and Reports

**Prompt:** "Please continue with Prompt #20"

**Actions:**
1. Replaced html/partials/dashboard/index.php — Full staff dashboard:
   - Stats cards: Today's Reservations, Total Covers, Occupancy (occupied/total tables with %), Waitlist Count
   - Upcoming Arrivals: Next 2 hours of reservations with guest, party, table, status (clickable to detail)
   - Alerts: No-shows today count, unconfirmed reservations needing action (clickable links)
   - Recent Activity: Latest 10 activity_log entries with user and timestamp
   - All queries scoped by restaurant_id, efficient aggregates
2. Created html/partials/reports/index.php — Reports page:
   - Date range selector (default last 30 days) with Apply button
   - Loads data via HTMX from data.php endpoint
   - Manager+ access
3. Created html/partials/reports/data.php — HTMX endpoint with metrics and ApexCharts:
   - Metrics cards: Reservation volume, Avg party size, No-show rate, Cancellation rate
   - Charts:
     - Reservations by Day (area chart)
     - Booking Source donut (walk-in vs online vs phone)
     - Covers by Hour (bar chart)
     - Reservations by Section (horizontal bar)
     - Peak Hours Heatmap (day of week x hour)
     - Guest Frequency pie (new vs returning)
   - All queries scoped by restaurant_id and date range

**Files Modified:**
- html/partials/dashboard/index.php (complete rewrite)

**Files Created:**
- html/partials/reports/index.php
- html/partials/reports/data.php

## 2026-03-07 — Prompt 21: Platform Restaurant Management

**Prompt:** "Please continue your last step."

**Actions:**
1. Committed and pushed Prompt 20 changes (dashboard + reports)
2. Created html/partials/platform/restaurants.php — Platform admin restaurant list:
   - Lists all restaurants with name, slug, city/state, staff count, today's reservations, status, created date
   - "Add Restaurant" button loads modal form via HTMX
   - Edit and Activate/Deactivate buttons per row with confirmation
   - Platform admin only access (requirePlatformAdmin)
3. Created html/partials/platform/restaurant-form.php — Add/Edit restaurant modal:
   - Fields: Name, Slug (auto-formatted), Phone, Email, Address, City, State, ZIP, Website, Timezone
   - Slug validation with lowercase/hyphen pattern
   - Edit mode pre-fills existing data
4. Created html/partials/platform/save-restaurant.php — POST handler:
   - Validates slug uniqueness, email format, URL format
   - For edits: updates restaurant record, updates session if editing current restaurant
   - For new: creates restaurant + default settings, section, turn times, operating hours (Tue-Sat lunch/dinner, Sun brunch/dinner), assigns creating user as owner
   - All within transaction for new restaurants
5. Created html/partials/platform/toggle-restaurant.php — Toggle is_active status:
   - Validates restaurant exists, toggles status, reloads list

Sidebar already had "Manage Restaurants" link from Prompt 3 — no changes needed to app.php.

**Files Created:**
- html/partials/platform/restaurants.php
- html/partials/platform/restaurant-form.php
- html/partials/platform/save-restaurant.php
- html/partials/platform/toggle-restaurant.php

## 2026-03-07 — Retell AI Integration Research

**Prompt:** "Research how Retell AI voice agents integrate with external systems. I need to understand: 1) How Retell AI calls external APIs/tools during a voice call, 2) What is the custom function/tool calling mechanism, 3) What format does Retell expect for API responses, 4) Does Retell support MCP servers natively, 5) What is the typical architecture for connecting Retell AI to a reservation system."

**Actions:**
1. Searched web for Retell AI custom functions, webhooks, MCP support, and reservation integration patterns
2. Fetched and analyzed Retell AI official documentation pages:
   - https://docs.retellai.com/build/single-multi-prompt/custom-function
   - https://docs.retellai.com/build/single-multi-prompt/mcp
   - https://docs.retellai.com/features/webhook-overview
   - https://docs.retellai.com/build/book-calendar
3. Fetched Retell AI blog posts on MCP integration and API architecture
4. Compiled comprehensive research summary (no code written)

**Key Findings:**
- Retell AI has two primary integration mechanisms: Custom Functions and MCP
- Custom Functions: Retell POSTs to your endpoint with call context + args, expects any 2xx response (string/JSON/buffer, max 15,000 chars)
- MCP: Native support via HTTP-based MCP servers, sub-second latency, configurable timeouts
- Webhooks: Separate system for call lifecycle events (call_started, call_ended, call_analyzed, etc.)
- Cal.com preset integration for booking, or custom functions for custom reservation systems
- Security: X-Retell-Signature header for request verification

## 2026-03-07 — Prompts 22-25: Retell AI Voice Agent Integration

**Prompt:** "No changes needed, please begin."

**Actions:**

### Prompt 22 — Shared Voice API Service Layer
1. Created helpers/voice-api.php — 5 pure functions with no session dependency:
   - voiceCheckAvailability($slug, $date, $partySize) — validates inputs, calls getAvailableSlots(), returns grouped results
   - voiceMakeReservation($slug, $date, $time, $partySize, $guestName, $guestPhone, $guestEmail, $specialRequests) — upserts guest by phone, auto-assigns table, creates reservation (source='phone', status='confirmed'), sends confirmation email
   - voiceLookupReservation($slug, $codeOrPhone) — searches by confirmation_code first, then by phone (most recent active)
   - voiceCancelReservation($slug, $confirmationCode) — validates cancellable status, updates to cancelled, sends email
   - voiceConfirmReservation($slug, $confirmationCode) — validates pending status, updates to confirmed, sends email
   - All functions: activity logging, natural-language message field for voice reading

### Prompt 23 — Retell Custom Functions API
2. Created helpers/retell-auth.php — Request verification:
   - verifyRetellSignature() — HMAC SHA-256 verification of X-Retell-Signature
   - parseRetellRequest() — reads raw body, verifies signature, parses JSON
   - getRetellArgs() — extracts args from Retell request format
   - retellResponse() — sends JSON response
3. Created 5 API endpoints under html/api/retell/:
   - check-availability.php — params: restaurant_slug, date, party_size
   - make-reservation.php — params: restaurant_slug, date, time, party_size, guest_name, guest_phone, guest_email, special_requests
   - lookup-reservation.php — params: restaurant_slug, confirmation_code or guest_phone
   - cancel-reservation.php — params: restaurant_slug, confirmation_code
   - confirm-reservation.php — params: restaurant_slug, confirmation_code

### Prompt 24 — MCP Server
4. Created html/api/mcp/server.php — JSON-RPC 2.0 MCP server:
   - Handles: initialize, notifications/initialized, tools/list, tools/call
   - Auth: Bearer token from Authorization header, key stored as 'mcp_api_key' setting
   - 5 tool definitions with full inputSchema (JSON Schema format)
   - Routes tools/call to voiceXxx() functions from shared service layer
   - Proper JSON-RPC error codes (-32601, -32602, -32603)

### Prompt 25 — Settings UI and Documentation
5. Created html/partials/settings/integrations.php — Integration settings page:
   - Retell API Key and MCP API Key fields with save
   - MCP key generation button
   - Retell Custom Function endpoint URLs table (copy-paste ready)
   - MCP Server URL with copy button
   - Restaurant slug display
   - Test Connection button (tests check_availability for tomorrow)
6. Created html/partials/settings/save-integrations.php — POST handler:
   - action=save_keys: upserts retell_api_key and mcp_api_key settings
   - action=test: runs voiceCheckAvailability() and displays results
7. Added "Integrations" link to sidebar under SETTINGS (owner only) in app.php
8. Created docs/retell-setup-guide.md — Complete setup documentation:
   - Custom Function configuration with copy-paste parameter schemas for all 5 functions
   - MCP Server connection instructions
   - Example agent system prompt with conversation flows
   - Testing instructions

**Files Created:**
- helpers/voice-api.php
- helpers/retell-auth.php
- html/api/retell/check-availability.php
- html/api/retell/make-reservation.php
- html/api/retell/lookup-reservation.php
- html/api/retell/cancel-reservation.php
- html/api/retell/confirm-reservation.php
- html/api/mcp/server.php
- html/partials/settings/integrations.php
- html/partials/settings/save-integrations.php
- docs/retell-setup-guide.md
- tasks/retell-voice-api-plan.md

**Files Modified:**
- html/app.php (added Integrations sidebar link)

## 2026-03-07 — Role-Based Security Update

**Prompt:** "We need to add user roles to the application and restrict access to different sections based on the role. The 'super-admin' is the vendor administrator that can view and assist any restaurant. The 'admin' is the administrator for each restaurant. This is the default role for the purchaser of the application. They have access to all the setup for their restaurants. They can also invite other users to their restaurants in 'admin' or other roles. The 'manager' role has access to all the setup of their restaurants. The 'user' role is for the restaurant staff to view the restaurant's reservations, make reservations, change reservations, seat reservations, and clear tables."

**Actions:**
1. Updated helpers/auth.php — Complete role system overhaul:
   - Renamed role values: owner→admin, host→user, manager stays same
   - New functions: isAdmin(), isManager(), isStaff(), isSuperAdmin()
   - New guards: requireAdmin(), requireManager(), requireSuperAdmin()
   - Backward-compatible aliases kept: isOwner()→isAdmin(), isHost()→isStaff(), isPlatformAdmin()→isSuperAdmin(), requireOwner()→requireAdmin(), requirePlatformAdmin()→requireSuperAdmin()
   - Role hierarchy: super-admin > admin > manager > user

2. Updated html/app.php sidebar access control:
   - RESERVATIONS section: all roles (no gate)
   - GUESTS section: all roles (no gate)
   - MANAGEMENT section: isManager() — admin + manager
   - SETTINGS section: isAdmin() — admin only
   - PLATFORM section: isSuperAdmin() — super-admin only

3. Updated html/partials/settings/user-form.php:
   - Role dropdown: admin, manager, user (was owner, manager, host)

4. Updated html/partials/settings/save-user.php:
   - Role validation: admin, manager, user
   - Access: requireAdmin()

5. Updated html/partials/settings/users.php:
   - Role badge colors: admin=red, manager=yellow, user=blue
   - Access: requireAdmin()

6. Updated all settings partials from requireOwner() to requireAdmin():
   - profile.php, save-profile.php, notifications.php, email-templates.php
   - integrations.php, save-integrations.php, toggle-user.php

7. Updated all platform partials from requirePlatformAdmin() to requireSuperAdmin():
   - restaurants.php, restaurant-form.php, save-restaurant.php, toggle-restaurant.php

8. Updated save-restaurant.php: default role for new restaurant creator changed from 'owner' to 'admin'

**NOTE:** The user_restaurants table in the database still has the old role values ('owner', 'host'). The user needs to update existing records:
```sql
UPDATE user_restaurants SET role = 'admin' WHERE role = 'owner';
UPDATE user_restaurants SET role = 'user' WHERE role = 'host';
```

**Files Modified:**
- helpers/auth.php (role system rewrite)
- html/app.php (sidebar access control)
- html/partials/settings/user-form.php (role dropdown)
- html/partials/settings/save-user.php (role validation)
- html/partials/settings/users.php (role badges)
- html/partials/settings/profile.php (requireAdmin)
- html/partials/settings/save-profile.php (requireAdmin)
- html/partials/settings/notifications.php (requireAdmin)
- html/partials/settings/email-templates.php (requireAdmin)
- html/partials/settings/integrations.php (requireAdmin)
- html/partials/settings/save-integrations.php (requireAdmin)
- html/partials/settings/toggle-user.php (requireAdmin)
- html/partials/platform/restaurants.php (requireSuperAdmin)
- html/partials/platform/restaurant-form.php (requireSuperAdmin)
- html/partials/platform/save-restaurant.php (requireSuperAdmin + default role)
- html/partials/platform/toggle-restaurant.php (requireSuperAdmin)

## 2026-03-11 — Codex Onboarding Analysis

**Prompt:** "This application was initially developed using claude code and we are starting to use codex.  Please read requirements.md and tech-stack.md, then analyze the codebase to make any notes you need going forward."

**Changes:**
- Confirmed the requirements content currently lives in `restaurant-reservation-requirements.md`; `requirements.md` is not present at the repo root
- Reviewed `tech-stack.md`, `restaurant-reservation-requirements.md`, the main app shell, shared auth/session/db helpers, public booking flow, platform/admin screens, and Retell/MCP integration files
- Added a new onboarding analysis section to `tasks/todo.md` capturing the active architecture, legacy/stale areas, and concrete drift points to watch in future work

## 2026-03-11 — Host-Based Landing Page Routing Plan

**Prompt:** "In the next series of steps, we want to consolidate applications into this application. 
The first thing we wanna do is change the flow when a user accesses the system first, we want the landing page to use the URL
that the user used to visit the site to control the front page of the website. To do this, we want to add a table to the database
That connects the system variable identifying the URL to a row in the database which points to PHP files that are injected
Into the landing page for that URL. Let's start by adding that functionality and put the existing landing
page into the database for the url of https://zozocal.com"

**Changes:**
- Reviewed the current public landing-page entry point in `html/index.php`
- Confirmed there is no existing host-based landing-page routing layer yet
- Added a new implementation plan section to `tasks/todo.md` covering the database mapping table, extracted landing include files, host resolution, index routing, and the initial `https://zozocal.com` seed row
- Stopped before code changes so the plan can be verified first

## 2026-03-11 — Host-Based Landing Page Routing Implementation

**Prompt:** "In the next series of steps, we want to consolidate applications into this application. 
The first thing we wanna do is change the flow when a user accesses the system first, we want the landing page to use the URL
that the user used to visit the site to control the front page of the website. To do this, we want to add a table to the database
That connects the system variable identifying the URL to a row in the database which points to PHP files that are injected
Into the landing page for that URL. Let's start by adding that functionality and put the existing landing
page into the database for the url of https://zozocal.com"

**Changes:**
- Added `helpers/landing.php` for request-host normalization, route lookup, and safe include-path validation
- Replaced the monolithic public router in `html/index.php` with a lightweight landing-page resolver
- Extracted the current ZozoCal marketing page into `html/landing/zozocal/page.php`
- Added `docs/sql/landing_page_routes.sql` with the `landing_page_routes` table definition and the `zozocal.com` seed row
- Applied the SQL to the live `zozocal` database and confirmed the `zozocal.com` mapping exists
- Verified PHP syntax for the changed PHP files

## 2026-03-11 — Missing Host Coming Soon Fallback

**Prompt:** "If the URL is not in the landing_page_routes table add a "Coming Soon" page with a login button."

**Changes:**
- Changed the landing-page fallback in `helpers/landing.php` so unmapped hosts no longer default to the ZozoCal marketing page
- Added `html/landing/default/coming-soon.php` with a Coming Soon message, current host display, and a Login button to `/login.php`
- Left explicit `landing_page_routes` mappings unchanged, so `zozocal.com` still resolves to the ZozoCal landing page
- Verified PHP syntax for the changed files

## 2026-03-11 — Dashboard Create Reservation Visibility

**Prompt:** "ON /partials/dashboard/index.php if the current user's product_type is not 'restaurant' hide the "Create Reservation" button."

**Actions:**
- Reviewed the repository instructions and existing tracking files before making changes
- Inspected `html/partials/dashboard/index.php` to locate the dashboard header action buttons
- Checked existing `product_type` lookup usage in `html/app.php` so the dashboard change can follow the same pattern
- Added a new plan section with todo checklist and review placeholder to `tasks/todo.md`
- Paused before editing application code so the plan can be verified first

**Prompt:** "Proceed"

**Changes:**
- Updated `html/partials/dashboard/index.php` to query the current user's `product_type` from `users`
- Wrapped the dashboard header "Create Reservation" button in a `product_type === 'restaurant'` condition
- Verified PHP syntax for `html/partials/dashboard/index.php`
- Updated the dashboard task section in `tasks/todo.md` with completed items and review notes

## 2026-03-11 — Professional Scheduling Product Design and Planning

**Prompt:** "In the next step we need to add scheduling functionality to the application for users whose product_type='professional'.  Then a user is 'professional' they have an application calendar for their service related professional business. Please design a scheduling system for single person professional business with all the expected functionality. When the user has product_type of 'professional' the system hides the restaurant system functionality and replaces it with the functionaly for a single person professional service business.  Please create a design and a plan to implement this functionality.  Please start with the database changes required and build a series of prompts necessary to build the application.  The application must use the existing LAMP, HTMX, Bootstrap tech stack. Please update requirements.md to include the requirements for this new functionality."

**Actions:**
- Reviewed the current app shell and mode-switching behavior in `html/app.php` and `html/partials/auth/switch-mode.php`
- Confirmed the repository does not currently have a root `requirements.md`
- Reviewed the existing product requirements document in `restaurant-reservation-requirements.md` for context
- Designed a professional scheduling approach that reuses the existing tenant/auth model to minimize refactor risk
- Added a new planning section to `tasks/todo.md`
- Created `requirements.md` with the professional-mode database design, functional requirements, phased implementation plan, and prompt sequence
- Did not begin feature implementation in this step

## 2026-03-11 — Professional Scheduling Step 1 Planning

**Prompt:** "Begin step 1."

**Actions:**
- Reviewed the new professional scheduling requirements and prompt sequence to confirm step 1 is the database-schema phase
- Reviewed existing SQL files in `docs/sql/` and the main schema in `restaurant_reservations.sql` to match naming, key, and timestamp conventions
- Added a step-specific implementation plan to `tasks/todo.md`
- Paused before creating the SQL file so the step-1 plan can be verified first

**Prompt:** "Please create the sql file but do not use the ENUM data type. Any place you were using the ENUM type use varchar."

**Changes:**
- Created `docs/sql/professional_scheduling.sql` for the professional scheduling product
- Added six tables scoped to the existing tenant model: `professional_profiles`, `professional_services`, `professional_availability_rules`, `professional_time_off`, `professional_clients`, and `professional_appointments`
- Added foreign keys back to `restaurants`, `users`, `professional_clients`, and `professional_services`
- Added indexes for booking slugs, service listings, availability lookups, client searches, and appointment scheduling/reporting queries
- Used `VARCHAR` columns for all typed/status fields instead of `ENUM`
- Reviewed the SQL file and confirmed there are no `ENUM` data types in it
- Did not apply the SQL to the live database in this step

## 2026-03-11 — Professional Scheduling Step 2 Planning

**Prompt:** "Tables are created, please perform step 2."

**Actions:**
- Reviewed the current authenticated shell in `html/app.php`, including sidebar sections, header behavior, and the default HTMX page load
- Confirmed there are no existing `html/partials/professional/` screens yet
- Added a step-specific implementation plan to `tasks/todo.md`
- Paused before changing the professional-mode shell so the step-2 plan can be verified first

**Prompt:** "Approved"

**Changes:**
- Updated `html/app.php` to branch the authenticated shell for `product_type = 'professional'`
- Added a dedicated professional sidebar with Dashboard, Calendar, Appointments, Clients, Services, Availability, Time Off, Settings, and Reports
- Changed the default HTMX page load to `/partials/professional/dashboard.php` for professional mode
- Updated the user dropdown Settings link to point to the professional placeholder settings view in professional mode
- Limited the affiliate sidebar section to affiliate mode so professional mode stays focused on professional functionality
- Added `html/partials/professional/dashboard.php` as the reusable professional placeholder partial for the new shell links
- Verified PHP syntax for `html/app.php` and `html/partials/professional/dashboard.php`

## 2026-03-11 — Professional Scheduling Step 3 Planning

**Prompt:** "Perfect, next continue to step 3."

**Actions:**
- Reviewed the professional scheduling requirements to confirm step 3 is the professional profile/settings screen and save handler
- Reviewed the existing restaurant profile screen in `html/partials/settings/profile.php` to reuse the current HTMX and Bootstrap form pattern
- Reviewed the professional-mode placeholder and shell entry points so the new screen can replace the placeholder settings link cleanly
- Added a step-specific implementation plan to `tasks/todo.md`
- Paused before editing the professional settings flow so the step-3 plan can be verified first

**Prompt:** "Proceed"

**Changes:**
- Added `html/partials/professional/settings.php` as the professional profile/settings screen
- Added `html/partials/professional/save-settings.php` to validate input and upsert `professional_profiles`
- Implemented settings fields for business name, display name, business phone, business email, timezone, booking slug, default location type, default location label, booking instructions, and cancellation policy
- Loaded default settings values from `professional_profiles`, with fallback values from `restaurants`, `settings`, and the current user when no professional profile row exists yet
- Updated `html/app.php` so professional-mode Settings links point to the real settings screen
- Updated `html/partials/professional/dashboard.php` so its settings calls-to-action point to the real settings screen
- Updated `restaurants.timezone` on professional settings save so app-level timezone behavior stays aligned with the professional profile timezone
- Verified PHP syntax for `html/app.php`, `html/partials/professional/dashboard.php`, `html/partials/professional/settings.php`, and `html/partials/professional/save-settings.php`

## 2026-03-11 — Professional Scheduling Step 4 Planning

**Prompt:** "Perfect, next continue to step 4."

**Actions:**
- Reviewed the step-4 requirement in `requirements.md` to confirm this step is the professional services CRUD implementation
- Reviewed existing list-plus-modal CRUD patterns in `html/partials/platform/products.php` and `html/partials/affiliate/prospects.php`
- Confirmed the shared `#modal-container` behavior is already available in `html/app.php` for HTMX-loaded Bootstrap modals
- Added a step-specific implementation plan to `tasks/todo.md`
- Paused before building the professional services screens so the step-4 plan can be verified first

**Prompt:** "Approved"

**Changes:**
- Added `html/partials/professional/services.php` as the professional services list screen
- Added `html/partials/professional/service-form.php` as the HTMX-loaded Bootstrap modal for service create/edit
- Added `html/partials/professional/save-service.php` to validate and create/update `professional_services`
- Added `html/partials/professional/toggle-service.php` to activate and deactivate services
- Implemented service fields for name, description, duration, buffers, price, color, location type, location label, sort order, public booking status, and active status
- Updated `html/app.php` so the professional sidebar Services link opens the real services screen
- Updated `html/partials/professional/dashboard.php` so its Services link opens the real services screen
- Verified PHP syntax for `html/app.php`, `html/partials/professional/dashboard.php`, `html/partials/professional/services.php`, `html/partials/professional/service-form.php`, `html/partials/professional/save-service.php`, and `html/partials/professional/toggle-service.php`

## 2026-03-11 — Professional Scheduling Step 5 Planning

**Prompt:** "Perfect, next continue to step 5."

**Actions:**
- Reviewed the step-5 requirement in `requirements.md` to confirm this step is recurring availability and date-specific time-off management
- Reviewed the existing restaurant `hours` and `special-dates` CRUD patterns as the closest local references
- Identified a simple first implementation shape: one CRUD flow for `professional_availability_rules` and one CRUD flow for `professional_time_off`
- Added a step-specific implementation plan to `tasks/todo.md`
- Paused before building the availability and time-off screens so the step-5 plan can be verified first

**Prompt:** "Approved"

**Changes:**
- Added `html/partials/professional/availability.php` as the recurring availability list screen
- Added `html/partials/professional/availability-form.php` as the availability create/edit modal
- Added `html/partials/professional/save-availability.php` and `html/partials/professional/toggle-availability.php` for recurring availability save, delete, and active-status toggling
- Added `html/partials/professional/time-off.php` as the date-specific blocked-time list screen
- Added `html/partials/professional/time-off-form.php` as the time-off create/edit modal
- Added `html/partials/professional/save-time-off.php` for time-off save and delete
- Implemented recurring availability fields for weekday, start time, end time, location type, location label, and active status
- Implemented time-off fields for start/end dates, start/end times, all-day flag, reason, and notes
- Added overlap validation so active recurring availability windows cannot overlap on the same weekday
- Updated `html/app.php` and `html/partials/professional/dashboard.php` so Availability and Time Off links open the real screens
- Verified PHP syntax for `html/app.php`, `html/partials/professional/dashboard.php`, `html/partials/professional/availability.php`, `html/partials/professional/availability-form.php`, `html/partials/professional/save-availability.php`, `html/partials/professional/toggle-availability.php`, `html/partials/professional/time-off.php`, `html/partials/professional/time-off-form.php`, and `html/partials/professional/save-time-off.php`

## 2026-03-11 — Professional Scheduling Step 6 Planning

**Prompt:** "Perfect, next continue to step 6."

**Actions:**
- Reviewed the step-6 requirement in `requirements.md` to confirm this step is the server-side professional slot-generation engine
- Reviewed the existing restaurant `helpers/availability.php` implementation to reuse the general helper shape without mixing restaurant table logic into professional scheduling
- Chose a low-risk implementation approach: add a separate professional availability helper rather than modifying the restaurant reservation engine
- Added a step-specific implementation plan to `tasks/todo.md`
- Paused before building the slot engine so the step-6 plan can be verified first

**Prompt:** "Approved"

**Changes:**
- Added `helpers/professional-availability.php` as the dedicated slot engine for professional scheduling
- Added helper functions to load the professional profile, selected service, recurring availability windows, overlapping time-off blocks, and overlapping appointment occupied ranges
- Added `getProfessionalAvailableSlots()` to generate slots for one service and one date using the professional timezone, slot interval, booking notice, booking horizon, and effective buffers
- Added `validateProfessionalSlot()` so later appointment and public-booking screens can validate one selected slot against the same rules used by slot generation
- Implemented overlap detection against `professional_time_off` and buffered `professional_appointments` ranges to prevent double-booking for the single provider
- Added reusable unavailability reason and message values so later UI screens can explain why a selected time cannot be booked
- Verified PHP syntax for `helpers/professional-availability.php`

## 2026-03-11 — Professional Scheduling Step 7 Planning

**Prompt:** "Perfect, next continue to step 7."

**Actions:**
- Reviewed the step-7 requirement in `requirements.md` to confirm this step is the professional appointment calendar and CRUD implementation
- Reviewed the existing restaurant reservation calendar and reservation create form as the closest local HTMX patterns to reuse
- Confirmed the professional shell still routes Calendar and Appointments to placeholders, so this step needs to replace those placeholder links with a real appointment calendar screen
- Confirmed `professional_appointments.client_id` is required, so the step-7 plan includes a lightweight client lookup/create path inside the appointment form even though the full client directory is planned for step 8
- Added a step-specific implementation plan to `tasks/todo.md`
- Paused before building the appointment calendar and CRUD screens so the step-7 plan can be verified first

**Prompt:** "Approved"

**Changes:**
- Added `html/partials/professional/calendar.php` as the first real professional appointment calendar screen with `day`, `week`, `month`, and `upcoming` views
- Added status and service filters plus stats cards for the selected appointment range
- Added `html/partials/professional/appointment-form.php` as the HTMX-loaded modal for create, edit, and reschedule flows
- Added `html/partials/professional/check-slots.php` so the appointment form can load conflict-free bookable times from the professional slot engine
- Added `html/partials/professional/client-search.php` so staff can reuse existing clients while the full client CRM is still deferred to step 8
- Added `html/partials/professional/save-appointment.php` to create and update `professional_appointments`, including inline client create/reuse, service snapshot fields, confirmation codes, and slot validation
- Added `html/partials/professional/update-appointment-status.php` for quick confirm, complete, cancel, and no-show actions from the calendar screen
- Updated `html/app.php` and `html/partials/professional/dashboard.php` so professional Calendar and Appointments links now open the real calendar screen instead of placeholders
- Verified PHP syntax for `html/partials/professional/calendar.php`, `appointment-form.php`, `check-slots.php`, `client-search.php`, `save-appointment.php`, `update-appointment-status.php`, `html/app.php`, and `html/partials/professional/dashboard.php`

## 2026-03-11 — Professional Scheduling Step 8 Planning

**Prompt:** "Perfect, next continue to step 8."

**Actions:**
- Reviewed the step-8 requirement in `requirements.md` to confirm this step is the professional client directory and client detail CRM work
- Reviewed the existing guest list and guest detail screens as the closest local CRM patterns to reuse for the professional client screens
- Reviewed the step-7 appointment workflow and confirmed it already includes lightweight inline client create/reuse logic, so step 8 can build the formal client directory around the same `professional_clients` table without reworking appointment creation
- Chose a small first-pass scope for the professional CRM: searchable client list, client detail with appointment history, and a create/edit modal for the main client fields
- Added a step-specific implementation plan to `tasks/todo.md`
- Paused before building the professional client CRM so the step-8 plan can be verified first

**Prompt:** "Approved"

**Changes:**
- Added `html/partials/professional/clients.php` as the professional client directory with search, summary cards, appointment counts, and pagination
- Added `html/partials/professional/client-detail.php` as the client profile screen with contact information, notes, internal notes, and appointment history
- Added `html/partials/professional/client-form.php` and `html/partials/professional/save-client.php` for create and edit flows backed by `professional_clients`
- Added formal support for preferred contact method and marketing opt-in in the professional client edit flow
- Updated `html/app.php` and `html/partials/professional/dashboard.php` so professional Clients links now open the real client directory instead of placeholders
- Lightly connected the appointment workflow to the new CRM by adding a `Manage Clients` path in the appointment modal and support for opening a new appointment preselected to a client from the client detail screen
- Verified PHP syntax for `html/partials/professional/clients.php`, `client-detail.php`, `client-form.php`, `save-client.php`, `appointment-form.php`, `html/app.php`, and `html/partials/professional/dashboard.php`

## 2026-03-11 — Professional Scheduling Step 9 Planning

**Prompt:** "Perfect, next continue to step 9."

**Actions:**
- Reviewed the step-9 requirement in `requirements.md` to confirm this step is the first public booking flow for professional services
- Reviewed the existing restaurant public booking pages in `html/booking/` to reuse the smallest practical standalone no-auth patterns without mixing restaurant reservation logic into professional scheduling
- Reviewed the current professional scheduling schema, slot engine, and appointment save flow to confirm the public booking flow can reuse `booking_slug`, `is_public_booking_enabled`, `is_public_bookable`, `getProfessionalAvailableSlots()`, and `professional_appointments.source = 'public_booking'`
- Chose a low-risk implementation approach: add a separate public professional booking path instead of modifying the existing restaurant booking pages
- Added a step-specific implementation plan to `tasks/todo.md`
- Paused before building the public booking pages so the step-9 plan can be verified first

**Prompt:** "Approved"

**Changes:**
- Added `getProfessionalProfileByBookingSlug()` to `helpers/professional-availability.php` so public booking pages can resolve a professional business from its saved booking slug while reusing the existing profile cache and slot engine
- Added `html/pro-booking/index.php` as the standalone public booking page for professional services, with Bootstrap cards for service/date selection, slot selection, and client details
- Rendered public bookable services from `professional_services` and surfaced the professional booking instructions, cancellation policy, and default location on the public booking page
- Added `html/pro-booking/check-slots.php` to load public appointment times from `getProfessionalAvailableSlots()` using the profile-level public-booking rules
- Added `html/pro-booking/confirm.php` to revalidate the selected slot, find or create a `professional_clients` record, insert a `professional_appointments` row with `source = 'public_booking'`, and return the confirmation-code success state
- Updated `html/partials/professional/dashboard.php` to show a `View Booking Page` action and updated the dashboard copy to reflect that public booking is now live
- Updated `html/partials/professional/settings.php` to show a `View Booking Page` link when a saved professional profile booking slug exists and updated the booking slug help text
- Verified PHP syntax for `helpers/professional-availability.php`, `html/pro-booking/index.php`, `html/pro-booking/check-slots.php`, `html/pro-booking/confirm.php`, `html/partials/professional/dashboard.php`, and `html/partials/professional/settings.php`

## 2026-03-11 — Professional Scheduling Step 10 Planning

**Prompt:** "Perfect, next continue to step 10."

**Actions:**
- Reviewed the step-10 requirement in `requirements.md` to confirm this step covers appointment confirmation, reminders, cancellations, and public self-service reschedule/cancel flows
- Reviewed the existing restaurant public lookup/modify/cancel pages in `html/booking/` as the closest local standalone no-auth self-service patterns to reuse
- Reviewed the existing notification infrastructure in `helpers/notifications.php`, `html/partials/settings/notifications.php`, and `helpers/send-reminders.php` to identify what can be reused directly and what needs professional-specific appointment variables
- Reviewed the professional settings save flow and current public booking/appointment save flows to confirm where professional notification preferences, cancellation notice checks, and notification triggers should be added
- Noted a schema constraint: the existing `notifications_log` table is reservation-oriented, so the step-10 plan keeps logging simple and reuses the existing infrastructure only where it fits cleanly
- Added a step-specific implementation plan to `tasks/todo.md`
- Paused before building the professional self-service and notification flows so the step-10 plan can be verified first

**Prompt:** "Approved"

**Changes:**
- Added `helpers/professional-booking.php` for shared professional appointment lookup by confirmation code, self-service restriction checks, status formatting, and lightweight `activity_log` audit entries
- Added `helpers/professional-notifications.php` for appointment-specific confirmation, reminder, and cancellation email/SMS delivery using the existing MailerSend/Twilio transport and notification settings keys
- Added `helpers/send-professional-reminders.php` as a cron-compatible professional reminder runner with dedupe based on `activity_log` reminder entries newer than the appointment's `updated_at`
- Extended `html/partials/professional/settings.php` and `html/partials/professional/save-settings.php` to manage `is_public_booking_enabled`, `cancellation_notice_hours`, reminder timing, notification from email, and confirmation/reminder/cancellation email/SMS toggles
- Added `html/pro-booking/lookup.php`, `html/pro-booking/check-modify-slots.php`, `html/pro-booking/modify.php`, and `html/pro-booking/cancel.php` so clients can look up, reschedule, and cancel appointments using the booking slug, confirmation code, and last name
- Updated `html/pro-booking/index.php` to expose the new self-service lookup path and updated `html/pro-booking/confirm.php` to log the booking, send confirmation notifications, and link into appointment management
- Updated `html/partials/professional/save-appointment.php` and `html/partials/professional/update-appointment-status.php` so staff-side create/status flows send professional confirmation or cancellation notifications where appropriate
- Updated `html/partials/professional/dashboard.php` so the professional dashboard copy now reflects that self-service changes and notifications are live and reports are the only remaining build step
- Verified PHP syntax for `helpers/professional-booking.php`, `helpers/professional-notifications.php`, `helpers/send-professional-reminders.php`, `html/pro-booking/index.php`, `html/pro-booking/confirm.php`, `html/pro-booking/lookup.php`, `html/pro-booking/check-modify-slots.php`, `html/pro-booking/modify.php`, `html/pro-booking/cancel.php`, `html/partials/professional/settings.php`, `html/partials/professional/save-settings.php`, `html/partials/professional/save-appointment.php`, `html/partials/professional/update-appointment-status.php`, and `html/partials/professional/dashboard.php`

## 2026-03-12 — Professional Scheduling Step 11 Planning

**Prompt:** "Please begin step 11."

**Actions:**
- Reviewed the step-11 requirement in `requirements.md` to confirm this step is the first professional reporting screen
- Reviewed the existing restaurant reports implementation in `html/partials/reports/index.php` and `html/partials/reports/data.php` to reuse the repo's HTMX page/data split without dragging restaurant-specific metrics into professional mode
- Reviewed the current professional shell routing in `html/app.php` and confirmed the Reports nav still points to the professional dashboard placeholder route
- Reviewed the professional appointment calendar data shape to align the new reports filters and date-range conventions with the existing professional scheduling screens
- Chose a low-risk approach: build dedicated professional reports partials backed directly by `professional_appointments`, `professional_services`, `professional_clients`, and professional profile settings instead of trying to generalize the restaurant reporting code
- Added a step-specific implementation plan to `tasks/todo.md`
- Paused before building the professional reports screen so the step-11 plan can be verified first

**Prompt:** "Approved"

**Changes:**
- Added `html/partials/professional/reports.php` as the dedicated professional reporting page with date-range filters, day/week/month grouping, and an HTMX-loaded report body
- Added `html/partials/professional/reports-data.php` to calculate summary metrics, appointment counts by period, service revenue, top clients, outcome counts, and service utilization from `professional_appointments`, `professional_clients`, professional availability, and time off
- Calculated utilization from booked appointment minutes versus configured availability minutes for the selected range, subtracting overlapping professional time-off blocks from the available window totals
- Added lightweight ApexCharts output for appointments by period and revenue by service while keeping the page useful through summary cards and tables even without deeper visualization
- Updated `html/app.php` so the professional Reports nav now opens the new reports screen instead of the dashboard placeholder route
- Updated `html/partials/professional/dashboard.php` so reporting is reflected as complete and the dashboard now links directly into the live professional reports screen
- Verified PHP syntax for `html/partials/professional/reports.php`, `html/partials/professional/reports-data.php`, `html/partials/professional/dashboard.php`, and `html/app.php`

## 2026-03-12 — Professional Scheduling HTMX Modal Error Investigation

**Prompt:** "New appointment and most HTMX popups throw a htmx:targetError in the console."

**Actions:**
- Reviewed the app shell modal handling in `html/app.php`, including the global modal container, the HTMX after-swap auto-show logic, and the close-modal cleanup logic
- Reviewed the main professional popup entry points in `html/partials/professional/dashboard.php`, `calendar.php`, `clients.php`, `client-detail.php`, and the modal partials to compare their `hx-target` selectors against the actual container placement
- Confirmed that many professional popup triggers target `#modal-container`, while the shared modal container in `html/app.php` currently sits outside `#page-content`, which is the HTMX root where the professional partials are rendered
- Confirmed a separate mismatch in `html/partials/professional/dashboard.php`: the primary “New Appointment” button currently routes to the calendar page instead of opening `appointment-form.php` in the modal flow
- Added a focused hotfix plan to `tasks/todo.md`
- Paused before editing code so the hotfix plan can be verified first

**Prompt:** "I approve the plan."

**Changes:**
- Rechecked the professional modal save flow and found the stronger root cause: the modal save handlers were sending `HX-Trigger` with `closeModal`, which could close and clear the modal before HTMX finished swapping into the modal feedback target
- Updated `html/partials/professional/save-service.php`, `save-availability.php`, `save-time-off.php`, `save-client.php`, and `save-appointment.php` to use `HX-Trigger-After-Swap` for the refresh plus `closeModal` events
- Updated `html/partials/professional/dashboard.php` so the primary `New Appointment` button now opens `appointment-form.php` in `#modal-container` instead of routing to the calendar page
- Updated `html/app.php` so the HTMX event handlers guard against missing targets and Bootstrap modal auto-show only runs for hidden fade-style modal fragments
- Verified PHP syntax for `html/app.php`, `html/partials/professional/dashboard.php`, `html/partials/professional/save-service.php`, `save-availability.php`, `save-time-off.php`, `save-client.php`, and `save-appointment.php`
- Confirmed there are no remaining immediate `HX-Trigger` headers with `closeModal` in the professional modal save handlers

## 2026-03-12 — Professional Scheduling Shared Modal Target Investigation

**Prompt:** "In /partials/professional/services.php clicks the \"Add Service\" button a htmx.org@2.0.8:1 htmx:targetError occurs.  Whatever the problem is, is happening on most screens.  Please go through each of the pages with buttons looking to fix this error."

**Actions:**
- Reviewed the main professional screens with popup buttons, starting with `html/partials/professional/services.php`, `availability.php`, `time-off.php`, `clients.php`, `client-detail.php`, `calendar.php`, and `dashboard.php`
- Confirmed the repeated pattern: these professional screens use `hx-target="#modal-container"` for Bootstrap modal loads but do not render a local modal container inside their own HTMX fragment, unlike older CRUD screens in the app that keep page-local modal containers
- Listed the affected professional pages and confirmed the problem is multi-page rather than isolated to the Services screen
- Added a focused multi-page hotfix plan to `tasks/todo.md`
- Paused before editing code so the broader popup-target plan can be verified first

**Prompt:** "Approved"

**Changes:**
- Updated `html/partials/professional/dashboard.php`, `services.php`, `availability.php`, `time-off.php`, `clients.php`, `client-detail.php`, and `calendar.php` so each screen renders a page-local `#professional-modal-container`
- Retargeted every professional popup trigger on those screens from `#modal-container` to `#professional-modal-container`, covering add/edit service, availability, time-off, client, and appointment actions
- Updated `html/app.php` so the shared `closeModal` event clears both `#modal-container` and `#professional-modal-container`, keeping the current generic HTMX/Bootstrap modal auto-show behavior unchanged for both patterns
- Verified by searching `html/partials/professional/` and confirming there are no remaining `hx-target="#modal-container"` selectors in the professional screens
- Verified PHP syntax for `html/app.php`, `html/partials/professional/dashboard.php`, `services.php`, `availability.php`, `time-off.php`, `clients.php`, `client-detail.php`, and `calendar.php`

## 2026-03-12 — Professional Scheduling Persistent Modal Host Investigation

**Prompt:** "In /partials/professional/services.php clicks the \"Add Service\" button a htmx.org@2.0.8:1 htmx:targetError occurs.  Whatever the problem is, is happening on most screens.  Please go through each of the pages with buttons looking to fix this error."

**Actions:**
- Re-read the live professional Services open/save flow in `html/partials/professional/services.php`, `service-form.php`, and `save-service.php` after the earlier shared-target change
- Re-checked the full professional popup target map in `html/partials/professional/` and confirmed the remaining popup buttons now point at `#professional-modal-container`
- Compared the professional modal flow against older working CRUD patterns and noted the key difference: the professional modal host currently lives inside the same HTMX fragments that are refreshed immediately after successful save actions
- Identified the more likely remaining failure mode: professional list/detail fragments such as Services and Calendar can refresh while their modal target is still being torn down, making the target lifecycle unstable across open/save flows
- Added a follow-up hotfix plan to `tasks/todo.md` that uses one persistent app-level modal host plus safer close-versus-refresh sequencing
- Paused before editing the application code so the updated plan can be verified first

## 2026-03-12 — Fix Persistent Professional Modal Container

**Prompt:** "In /partials/professional/services.php the 'Add Service' button causes htmx:targetError when it tries to load /partials/professional/service-form.php"

**Changes:**
- Moved `#professional-modal-container` from inside each professional partial to a persistent app-level location in `html/app.php` (next to the existing `#modal-container`)
- Removed the local `<div id="professional-modal-container"></div>` from 7 professional partials: services.php, availability.php, calendar.php, dashboard.php, time-off.php, clients.php, client-detail.php
- Root cause: the modal container lived inside HTMX fragments that get replaced via outerHTML swap on list refresh, destroying the target before HTMX could resolve it
- The persistent app-level container ensures `#professional-modal-container` always exists in the DOM regardless of page refreshes

## 2026-03-12 — Fix Inherited outerHTML Swap on Professional Modal Buttons

**Prompt:** "The error was not fixed."

**Root cause:** In HTMX 2.0.8, `hx-swap` is resolved via `getClosestAttributeValue`, meaning it inherits from ancestor elements. All professional modal-loading buttons sit inside a parent div (e.g., `#professional-services-main`) that has `hx-swap="outerHTML"` for list refresh. The buttons inherited this `outerHTML` swap mode, causing the modal container to be **destroyed** (replaced by modal HTML) on first use. Subsequent clicks then fail with `htmx:targetError` because the container element no longer exists.

**Fix:** Added explicit `hx-swap="innerHTML"` to all 21 professional modal-loading buttons across 7 files (services.php, availability.php, time-off.php, clients.php, client-detail.php, dashboard.php, calendar.php). This prevents them from inheriting the parent's `outerHTML` swap and preserves the modal container element.

## 2026-03-12 — Professional MCP Server Planning

**Prompt:** "We have an mcp server /api/mcp/server.php for restaurants and we want to create the same functionality in an MCP server called pro.php that provides all the same tools but for professional service scheduling instead of restaurant scheduling. Please plan the development of this feature."

**Actions:**
- Read the existing restaurant MCP server (`html/api/mcp/server.php`) — JSON-RPC 2.0 over HTTP with 7 tools (check_availability, make_reservation, lookup/cancel/confirm/modify_reservation, update_contact_preferences)
- Read the restaurant service layer (`helpers/voice-api.php`) that backs the MCP tools
- Researched the professional scheduling data model: 6 tables (professional_profiles, professional_services, professional_appointments, professional_clients, professional_availability_rules, professional_time_off)
- Read the professional availability engine (`helpers/professional-availability.php`) with slot generation, validation, and timezone support
- Read the professional booking helper (`helpers/professional-booking.php`) with appointment context loading and self-service rules
- Mapped each restaurant tool to its professional equivalent, identifying key differences (booking_slug instead of restaurant_slug, service_id instead of party_size, professional_clients instead of guests, etc.)
- Added a new `list_services` tool since professionals have multiple services to choose from (restaurants don't need this)
- Wrote a development plan to `tasks/todo.md` with 3 steps: service layer, MCP endpoint, verification

## 2026-03-12 — Professional MCP Server Implementation

**Prompt:** "Please continue." (after MCP server plan was approved)

**Changes:**

### Step 1 — Service Layer (`helpers/professional-voice-api.php`)
- Created new file with 8 public functions + 2 internal helpers
- `proVoiceResolveProfile($slug)` — resolve professional profile by booking_slug, set timezone
- `proVoiceGenerateConfirmationCode($pdo, $restaurantId)` — generate unique 8-char hex confirmation codes
- `proVoiceListServices($slug)` — list active, public-bookable services with name/duration/price
- `proVoiceCheckAvailability($slug, $serviceId, $date)` — check available slots using professional availability engine
- `proVoiceBookAppointment(...)` — validate slot, find-or-create client, insert appointment (source='api'), send notifications
- `proVoiceLookupAppointment($slug, $codeOrPhone)` — search by confirmation code or client phone
- `proVoiceCancelAppointment($slug, $confirmationCode)` — cancel with notice window validation
- `proVoiceConfirmAppointment($slug, $confirmationCode)` — pending → confirmed transition
- `proVoiceModifyAppointment(...)` — cancel old + book new pattern
- `proVoiceUpdateClientPreferences($slug, $clientPhone, $preferences)` — update marketing_opt_in and preferred_contact_method

### Step 2 — MCP Endpoint (`html/api/mcp/pro.php`)
- Created JSON-RPC 2.0 endpoint mirroring `server.php` structure
- 8 tools: list_services, check_availability, book_appointment, lookup_appointment, cancel_appointment, confirm_appointment, modify_appointment, update_client_preferences
- Bearer token auth from `settings.mcp_api_key`
- Logging to `logs/mcp-pro.log`
- Server info: ZozoCal Professional Scheduling v1.0.0

### Step 3 — Verification
- PHP syntax checks pass on both new files
- `initialize` endpoint returns correct protocol version and server info
- `tools/list` endpoint returns all 8 tool definitions with proper inputSchema
- Full end-to-end testing deferred (requires professional profile with booking_slug + MCP API key configured)

## 2026-03-20 — Fix Affiliate Modal Error Display

**Prompt:** "When creating an affiliate with save-affiliate.php, the error 'A user with this email already exists' was displayed on the main page instead of inside the modal dialog."

**Changes:**
- `html/partials/platform/affiliate-form.php` — Changed form `hx-target` from `#page-content` to `#platform-affiliate-form-feedback` so validation errors display inside the modal's feedback div
- `html/partials/platform/save-affiliate.php` — Added `HX-Retarget: #page-content` and `HX-Reswap: innerHTML` headers on the success path so the affiliate list still correctly replaces the page content after a successful save

## 2026-03-20 — Create Business When Adding Affiliate

**Prompt:** "When I add an affiliate using save-affiliate.php, in addition to creating the user to invite, we also need to create his business in the restaurants table and the link from the new user to the business in the user_restaurants table. restaurants.location_type will be affiliate. restaurants.status will be 'active'. user_restaurants.role will be admin."

**Changes:**
- `html/partials/platform/save-affiliate.php` — After creating the affiliate record, now also:
  - Fetches the user's info for restaurant name/contact fallbacks
  - Creates a restaurant row with `location_type='affiliate'`, `status='active'`, using company info from the affiliate form (name, address, phone, email) with user info as fallback
  - Generates a unique slug from the business/company name
  - Creates a `user_restaurants` link with `role='admin'`

## 2026-03-20 — Filter Locations to Affiliate In-Setup Only

**Prompt:** "Change the status from 'active' to 'in-setup' for new restaurant records created when a new affiliate is created. When logged in as a super-admin only show locations from the restaurants table where location_type='affiliate' and status='in-setup'."

**Changes:**
- `html/partials/platform/save-affiliate.php` — Changed new restaurant status from `'active'` to `'in-setup'`
- `html/partials/platform/restaurants.php` — Added `WHERE r.location_type = 'affiliate' AND r.status = 'in-setup'` filter to the super-admin Manage Locations query

## 2026-03-20 — Filter Super-Admin Restaurant Switcher

**Prompt:** "In the restaurant-switcher, we want the list to include only where restaurants.status='in-setup' and restaurants.location_type='affiliate'."

**Changes:**
- `helpers/restaurant.php` — Updated `getUserRestaurants()` super-admin query to filter by `location_type = 'affiliate' AND status = 'in-setup'` instead of showing all active restaurants

## 2026-03-20 — Show Business Name in Affiliates List

**Prompt:** "In partials/platform/affiliates.php when showing the list of affiliates, also show the business name related to the record in 'restaurants' table for the affiliate."

**Changes:**
- `html/partials/platform/affiliates.php` — Added subquery to fetch `business_name` from restaurants via user_restaurants link, added "Business" column to the table between Name and Code

## 2026-03-20 — Twilio Pro SMS Webhook (Store Only)

**Prompt:** "Add a webhook for text messages that come in through Twilio numbers. Identify company from recipient number, customer from sending number. First step: just store the message. Call it twilio_pro.php, lookup professional_clients as sender and restaurant_phone_numbers as receiving numbers."

**Changes:**
- Created `html/api/sms/twilio_pro.php` — Simplified Twilio inbound SMS webhook that:
  - Identifies company by matching TO number against `restaurant_phone_numbers`
  - Identifies client by matching FROM number against `professional_clients`
  - Stores the message in `sms_message_log` with restaurant_id, client_id, phone_number_id
  - Returns empty TwiML (no AI reply yet)
  - Logs to `logs/pro-webhook.log`

## 2026-03-20 — Add Prospect Lookup to twilio_pro.php

**Prompt:** "We also need to look at the contact_phone column in the prospects table for the caller tied to the restaurant_id of the receiving phone number."

**Changes:**
- `html/api/sms/twilio_pro.php` — Added fallback lookup: if no `professional_clients` match, checks `prospects.contact_phone` where `restaurant_id` matches the receiving number's company. Stores `prospect_id` in the log.
- `docs/sql/channel_message_logs.sql` — Added `client_id` and `prospect_id` columns to `sms_message_log` CREATE TABLE
- Created `docs/sql/sms_message_log_add_columns.sql` — Migration to add `client_id` and `prospect_id` columns to existing `sms_message_log` tables

## 2026-03-20 — Update Text Agent Schema and Form

**Prompt:** "For text agents, the agent_id will automatically be the phone number. The table must contain a system_prompt column replacing Text Agent Greeting. We also need a custom_webhook column like the voice agent webhook."

**Changes:**
- `docs/sql/text_agent_tables.sql` — Replaced `text_agent_greeting` with `system_prompt`, added `custom_webhook` column, made `phone_number` NOT NULL
- Created `docs/sql/text_agent_prompts_update.sql` — Migration for existing databases (renames column, adds custom_webhook)
- `html/partials/settings/text-agents.php` — Removed Agent ID field (auto-set from phone), replaced "Text Agent Greeting" with "System Prompt" textarea, added "Custom Webhook" URL field, updated list table columns and JS
- `html/partials/settings/save-text-agent.php` — Auto-sets agent_id from phone_number, validates phone as required, saves system_prompt and custom_webhook

## 2026-03-20 — SMS Pro Context Webhook

**Prompt:** "Create a webhook like /api/retell/pro-webhook.php but for text messaging. Receives to_number and from_number, queries company info from restaurants, client info from professional_clients, and returns SMS history for conversation context."

**Changes:**
- Created `html/api/sms/pro-webhook.php` — SMS context webhook that:
  - Accepts to_number and from_number (POST or GET)
  - Looks up company via restaurant_phone_numbers, returns full business info from restaurants
  - Matches client via professional_clients, includes next appointment if found
  - Returns last 50 SMS messages between this client and company from sms_message_log
  - Returns JSON: { business, client, sms_history }
  - Logs to `logs/sms-pro-webhook.log`

## 2026-04-09 — Good News Table

**Prompt:** "I want to create a table that is good news the restaurant wants to give patrons when they register. We want to create a table that contains good news and is populated by a web search daily. Please create the table and only the table."

**Changes:**
- Created `docs/sql/good_news.sql` — table schema for storing positive news articles per restaurant, with fields for title, summary, source info, image, category, active flag, and fetch timestamp.

## 2026-09-21 — Move database to local PostgreSQL 17

**Prompts:**
- "I'm getting a 500 error logging in, please research."
- "Let's make the change to move the database to the local postgres server."
- "Please install Postgres17 and create the Postgres database for zozocal and install the schema."

**Root cause:** Login 500 was a DB connectivity failure — remote server 192.168.100.163:5432 was unreachable (ARP FAILED / no route to host), so `config/database.php` threw an uncaught exception (SQLSTATE[08006]).

**Actions:**
- Installed PostgreSQL 17 (server 17.11) locally via the official PGDG apt repo; cluster `17/main` online on 127.0.0.1:5432.
- Created role `zozocal` and database `zozocal` (owned by `zozocal`).
- Loaded schema from `docs/sql/pg_schema.sql` — 61 tables.
- Changed `config/database.php` DB_HOST from `192.168.100.163` to `127.0.0.1`.

**Verification:** PDO connection via app config succeeds (server_version 17.11, 61 tables); `login.php` returns HTTP 200.

**NOTE:** Local tables are EMPTY — data from the unreachable remote server was NOT migrated (could not be dumped while offline). Existing users/restaurants/reservations are not present until data is restored.

## 2026-09-21 — Server setup after fresh clone

**Prompt:** "We just cloned this project onto this server to continue development. Please research the codebase and install what is necessary to get the program working."

**Research findings:** PHP 8.3.6 (pdo_pgsql, mbstring, curl), Composer, Apache 2.4 (DocumentRoot already `/var/www/html`, php_module loaded) and PostgreSQL 17.10 were already installed. Missing: `vendor/`, the `zozocal` role/database, and write access to `logs/` for `www-data`. No `.htaccess` files exist, so `mod_rewrite` is not needed. `config/database.php` already targets 127.0.0.1:5432.

**Actions:**
- Wrote the plan to `tasks/todo.md`.
- Ran `composer install --no-dev` — 8 packages installed into `vendor/` (git-ignored).
- Created PostgreSQL role `zozocal` (password from `config/database.php`) and database `zozocal`; loaded `docs/sql/pg_schema.sql` — 61 tables.
- Set `logs/` to group `www-data`, mode 2775 (and existing `.log` files to 664) so Apache can write webhook/MCP logs.
- Fixed `html/test-db.php`: MySQL `SHOW TABLES` → PostgreSQL `information_schema.tables` query (one line).

**Verification:** PDO connection through the app config succeeds (server 17.10); `/login.php`, `/index.php`, `/register.php`, `/test-db.php` return HTTP 200; `/test-db.php` lists 61 tables; Apache error log clean.

**Known gaps (pre-existing, unchanged):** database has no data and no `nav_permissions` rows; `plans`, `invoice_payments`, `prepay_transactions`, `sms_messages` tables are absent from `pg_schema.sql` (their SQL files are MySQL-dialect); `RETELL_API_KEY` not set in Apache's environment; Google OAuth callback for this host not registered.

## 2026-09-21 — Plan: reduce the codebase to the restaurant reservation product

**Prompts:**
- "This project was my attempt to merge three separate sets of functionality together that I regret."
- "First, remove any outdated rules from Claude.md that are no longer necessary."
- "Next, our goal is to make this application a restaurant reservation system only, which was the first stage of the project before we merged in two other projects."
- "When a user registers with the application, they should only have the option to be running the restaurant reservation system."
- "Please make a plan to strip out the affiliate management portion of the application and the appointment system that was not related to restaurant reservations."
- "Ask me any questions necessary to make the plan."

**CLAUDE.md:** rule 2 referenced the `design/` folder as the design inspiration. The app was migrated to the Kobie admin template in Nov 2025 (`html/assets` is copied from `kobie/assets`; nothing references `design/`). Rewrote rule 2 to name `kobie/` as the protected template. All other rules are process rules that still apply.

**Research:** inventoried every professional, affiliate and shared file (full plan in `tasks/todo.md`). Key findings:
- A third merged product, SalesCoach Pro ("Drajeo"), is still in the tree and is dead code: `models/` (all but `User.php`), 18 orphan `html/partials/*` folders and 12 loose partials all call `currentOrgId()`, which is not defined anywhere, and their tables are not in `pg_schema.sql`.
- `html/partials/settings/retell-create-agent.php` wires new restaurant voice agents to the professional MCP server (`/api/mcp/pro.php`) with appointment tools — must be fixed before that server is deleted.
- `helpers/restaurant.php` gives super-admins only affiliate in-setup restaurants — must be fixed before `location_type` is dropped.
- Registration posts `location_type` (restaurant/professional/affiliate) into `restaurants.location_type`; `users.product_type` is written only by an unreferenced `switch-mode.php` and read by nothing.
- Billing tables are restaurant-scoped; only their affiliate commission/price fields belong to the affiliate product.
- The nav links to `/partials/settings/reservations.php`, which does not exist (pre-existing bug).

**Questions asked and answers:** remove SalesCoach too — yes; billing — keep, strip affiliate fields; database — rebuild from a restaurant-only schema, no migration script; API v1 / SMS — trim v1 to auth/todos, rewrite SMS sender lookup against `guests`.

**Status:** plan written to `tasks/todo.md` (phases 0–6), awaiting verification before any code is removed.

## 2026-09-21 — Strip-out Phase 0: safety fixes

**Prompt:** "Yes, please begin"

- `html/partials/settings/retell-create-agent.php`: new voice agents now attach the restaurant MCP server (`/api/mcp/server.php`, id `restaurant_mcp`) and enable the reservation tools (check_availability, make/lookup/confirm/cancel/modify_reservation, update_contact_preferences) instead of the professional appointment tools.
- `helpers/restaurant.php` `getUserRestaurants()`: super-admins now get all active restaurants; removed the affiliate-only branch and the affiliate_id lookup.
- Created a test account (smoke@example.com / Smoke Bistro, super-admin) and a logged-in smoke script to check pages after each phase. Login, app shell, dashboard, integrations, calendar and profile all return 200.

## 2026-09-21 — Strip-out Phase 1: delete dead SalesCoach Pro code

- Deleted `models/*` except `User.php` (used by password reset), 19 orphan `html/partials/*` folders (activity, agents, analytics, calendar, coaching-calendar, context, kanban, models, notifications, organizations, products, projects, prospects, scoring, sessions, subscription, tasks, team, teams), 12 loose `html/partials/*.php` pages, `html/partials/dashboard/{manager,rep}.php` and `dashboard/widgets/`, `helpers/functions.php` (only defined `orgScope()`), `docs/migrations/`, `docs/sql/plans_and_subscriptions.sql`, `old_requirements.md`, `html/app.php.backup`. 147 files, ~27k lines. No live code referenced any of them (all called the undefined `currentOrgId()`).
- Side fix: the Todos page was returning 500 with a MySQL leftover — `FIELD()` in the ORDER BY of `html/partials/todos/list.php` and `html/api/v1/todos.php`. Replaced with a `CASE` expression.
- Smoke test: app shell, dashboard, todos, reservations calendar, guests, staff users, platform users, billing invoices and forgot-password all return 200.

## 2026-09-21 — Strip-out Phase 2: remove professional scheduling

- Deleted `html/partials/professional/`, `html/pro-booking/`, the five `helpers/professional-*` / `send-professional-reminders.php` helpers, MCP `pro.php`, `pro-tools.php`, `sms.php`, `sms-tools.php`, Retell `pro-webhook.php`, SMS `pro-webhook.php`, `twilio_pro.php`, `text-agent-webhook.php`, the eight professional REST v1 endpoints (kept `_bootstrap.php`, `auth.php`, `todos.php`), `requirements.md`, `docs/mobile-api-spec.md`, `docs/mcp-pro-prompt-snippet.md`, `docs/pro-webhook-response.json`, `docs/sql/professional_scheduling.sql`.
- `helpers/openai-sms.php`, `helpers/openai-email.php`: removed the professional require and the location-type tool switch; the SMS and email agents now always use the restaurant MCP tools from `server-tools.php`. Dropped the `$locationType` parameter from `processInboundSMS()` / `processInboundEmail()`.
- `html/api/sms/webhook.php`: sender is now matched against `guests.phone` instead of `professional_clients`; message log rows store `guest_id` (column added to the local DB; schema file updated in Phase 5). `html/api/email/webhook.php` no longer reads `location_type`.
- `retell-create-agent.php`, `retell-assign-phone.php`, `voice-prompts.php`: Retell webhook URL now `/api/retell/restaurant-webhook.php` instead of the deleted `pro-webhook.php`.
- `sql/multi-language-relay.sql`: comment fixed (tables are used by the restaurant voice relay).
- Verified: helpers load in CLI, MCP `tools/list` returns the 7 reservation tools, all agent settings and message log pages return 200.
- Note for follow-up: the deleted professional MCP had todo tools (`list/create/update/complete_todo`) and `get_good_news`, which the restaurant MCP never had. They can be ported to `server-tools.php` if wanted.

## 2026-09-21 — Strip-out Phase 3: remove the affiliate system

- Deleted `html/partials/affiliate/` (23 files), the nine affiliate platform partials (`affiliates.php`, `affiliate-detail/form/commission-form/referral-form.php`, `save-affiliate*.php`, `update-commission-status.php`), `helpers/prospect-restaurant.php`, `html/partials/auth/switch-mode.php` (unreferenced; only writer of `users.product_type`), and the three prospect SQL files.
- `helpers/auth.php`: removed affiliate session vars from `login_user()`, `isAffiliate()`, `currentAffiliateId()` and the affiliate role note.
- `html/partials/dashboard/index.php`: removed the affiliate lookup, the "Become an Affiliate" button and the location-type wrapper around Create Reservation; billing cards show for the billing user only.
- Billing partials (6): access check is now billing user or super-admin; removed the commission card from `invoice-detail.php`.
- Platform partials: removed the affiliate role, badges and stat card (`users.php`, `platform-user-form.php`, `save-platform-user.php`), affiliate column (`subscriptions.php`), affiliate price (`products.php`, `product-form.php`, `save-product.php`, `subscription-form.php`), commission (`billing-settings.php`, `save-billing-settings.php`). `restaurants.php` now lists all restaurants (was affiliate in-setup only) and uses `CURRENT_DATE` instead of MySQL `CURDATE()`.
- `html/cron/generate-invoices.php`: commission math removed.
- Pre-existing gap fixed on the way: the billing tables/columns from `docs/sql/invoicing.sql` were still MySQL syntax and never in PostgreSQL, so billing pages and the invoice cron were failing. Rewrote `invoicing.sql` for PostgreSQL (restaurant billing columns, full `invoices` columns, `invoice_payments`, `prepay_transactions`, billing nav rows) and applied it locally; it will be folded into `pg_schema.sql` in Phase 5.
- Verified: dashboard, billing (invoices, prepay history, add prepay), platform users/restaurants/subscriptions/products/billing settings all 200; both crons run cleanly.

## 2026-09-21 — Strip-out Phase 4: registration and app shell restaurant-only

- `html/register.php`: removed both "Business Type" selects (email and Google sign-up paths); subtitle now "Set up your restaurant's reservation system"; "Company Name" → "Restaurant Name".
- `html/partials/auth/register.php`, `google-complete.php`: no `location_type` handling, no `affiliates` insert, no `is_affiliate` flag. Every signup creates a restaurant and makes the user its admin.
- `html/app.php`: removed the professional and affiliate nav sections, PIPELINE label, Affiliates platform item, Affiliate Settings menu item, `professional-modal-container`, and the `$isProfessionalMode` / `$isAffiliateMode` switches; footer is "Restaurant Reservation System". Dropped the `-r` suffix from the restaurant copies of `nav-todos`, `nav-sms-messages`, `nav-voice-calls`, `nav-email-messages` (the suffix only existed to avoid clashing with the professional section). Removed the "Reservation Rules" nav item, which linked to a page that never existed.
- `helpers/auth.php` `getPermittedNavItems()` no longer takes or filters on location type.
- Nav-permission admin pages: location-type field, filter and column removed; affiliate role option removed.
- `html/landing/zozocal/page.php`: removed the "Professional Service Scheduling" hero row.
- `html/partials/billing/save-prepay.php`: affiliate access term removed (missed in Phase 3).
- Verified: app shell, dashboard, nav permissions, billing, profile all 200; registered a second account through the real form (restaurant created, user is admin) and logged in as it.

## 2026-09-21 — Strip-out Phase 5: database

- `docs/sql/pg_schema.sql` rewritten restaurant-only: dropped the 6 `professional_*` and 6 affiliate/prospect tables with their indexes and triggers; dropped `restaurants.location_type`/`affiliate_id`, `users.product_type`/`is_affiliate`/`referred_by`/`referral_code`/`user_mode`, `nav_permissions.location_type`/`product_type` (unique key now `nav_item_id, user_role, restaurant_role`), `products.affiliate_price`, `landing_page_routes.affiliate_id`, and the `client_id`/`prospect_id` columns on `call_logs`, `sms_message_log`, `email_message_log`, `twilio_numbers`. Added `sms_message_log.guest_id`. `restaurants.status` now defaults to `active` (was `in-setup`, which showed a permanent warning banner for every new restaurant). Folded the billing schema in: restaurant invoice columns, full `invoices` definition, `invoice_payments`, `prepay_transactions`. 51 tables; sections renumbered.
- New `docs/sql/nav_permissions.sql` in PostgreSQL syntax, restaurant-only, one row per sidebar item/role (50 rows), covering the items that used to be seeded from `todos.sql`, `channel_message_logs.sql`, `restaurant_phone_numbers.sql` and `invoicing.sql`. Deleted `docs/sql/migrate_nav_permissions.sql`; removed the nav rows from `invoicing.sql`.
- Rebuilt the local database from the two files; re-registered the test super-admin through the form. The full sidebar renders (39 items) with no status banner. Every nav target returns 200; invoice crons, MCP `tools/list` (7 tools) and `/test-db.php` (51 tables) pass.
- Pre-existing PostgreSQL-migration bugs fixed while verifying: MySQL `CURDATE()`, `CURTIME()` and `DATE_SUB()` in the waitlist pages and `platform/restaurant-dashboard.php` (the waitlist page was returning 500).
- `database-documentation.md` needs no change: it documents only the original 17 restaurant tables.

## 2026-09-21 — Strip-out Phase 6: docs and wrap-up

- `README.md`: removed the "What should be removed" section; current-state paragraph says the removal is done; setup now loads `nav_permissions.sql` after the schema.
- `tech-stack.md`: MariaDB/MySQL/pdo_mysql references replaced with PostgreSQL 17 / pdo_pgsql.
- `docs/sql/`: deleted `channel_logs_client_id.sql` and `sms_message_log_add_columns.sql` (only added the removed columns); removed the obsolete location-type nav rows from `todos.sql`, `channel_message_logs.sql`, `restaurant_phone_numbers.sql` (now in `nav_permissions.sql`); removed the "Professional Scheduling" seed row from `prompt_templates.sql`; dropped `client_id`/`prospect_id` from the MySQL-era `channel_message_logs.sql` definition.
- Final verification: no affiliate/professional/prospect/location_type/product_type references left in PHP or SQL; all 33 sidebar pages and all public pages return 200; all tracked PHP lints clean.
- Review section written to `tasks/todo.md`, including the follow-ups (remaining MySQL-only SQL in five files, optional port of todo tools into the restaurant MCP, old MySQL migration files, `logs/` cleanup).

## 2026-09-25 — Admin test account lookup

**Prompt:** "What is the admin test email and password. If there is not one, please create."

- Searched the codebase and the live PostgreSQL `zozocal` database for an admin test login.
- Found an existing one already seeded: `smoke@example.com` (users.id 1, `role = super-admin`, `user_type = system_owner`, `is_active = 1`), linked to restaurant "Smoke Bistro" with the restaurant role `admin`. It is documented at tasks/todo.md:126.
- Verified the credentials rather than assuming: `password_verify()` against the stored hash passes, and a full HTTP POST to `/partials/auth/login.php` with a valid CSRF token returns `HX-Redirect: /app.php`.
- No new account created — none was needed. No code changes.

## 2026-09-25 — Plan: full API and MCP coverage for AI-agent administration

**Prompt:** "One of the primary features of this application is that in needs the capability to be administered by AI agents. To do this we need to ensure that all functionality is available via API and MCP surfaces.  Please research the current capabilities of the project and write a plan to cover any gaps."

- Surveyed `html/api/` (REST v1, guest MCP server, Retell, SMS, email webhooks), all `html/partials/` handlers, `helpers/auth.php` role guards and the `api_tokens` / `activity_log` schema.
- Found: REST covers only login + todos; MCP covers only 7 guest booking tools; ~55 POST handlers and ~40 views exist only as session/CSRF HTMX partials with inline logic.
- Found existing issues: guest MCP key lookup is not scoped to a restaurant and allows all calls when no key is set; REST login always uses the user's first restaurant; no revocable agent keys; no audit of API writes.
- Archived the finished restaurant-only plan to `tasks/restaurant-only-plan.md`; wrote the new plan to `tasks/todo.md` (operation registry shared by a REST router and a new admin MCP server, service extraction, API keys page, 8 phases). Awaiting approval of 4 decisions before any code changes.

## 2026-09-25 — Agent API Phase 0: foundations

**Prompt:** "Plan is approved.  Please proceed." (Decisions taken as recommended: extract shared service helpers; billing read-only for agents; separate admin MCP endpoint; phase order as written.)

- `api_tokens`: added `name`, `last_used_at`, `revoked_at` (schema file + local DB). New `nav-api-keys` nav permission (admin).
- `helpers/services/_base.php`: `ServiceError`, validation helpers, `svc_log()` for `activity_log`.
- `helpers/api/registry.php`: `api_op()` declares an operation once (role, method, path, JSON schema, handler); generates REST routing, MCP tools and OpenAPI; validates input; audits every write as an `api_call` activity row naming the key.
- `helpers/api/auth.php`: shared bearer-key auth (revoked/expired rejected, `last_used_at`, super-admin `X-Restaurant-Id`). **Bug fixed:** under mod_php a Bearer `Authorization` header never reaches `$_SERVER`, so the existing `/api/v1/todos.php` could never authenticate; now read via `getallheaders()`.
- `html/api/v1/index.php`: REST router using PATH_INFO (`/api/v1/index.php/todos/5`) because Apache has no mod_rewrite/AllowOverride; `/openapi.json`, `/operations`.
- `html/api/mcp/admin.php`: admin MCP server (initialize with version negotiation, ping, tools/list filtered by role, tools/call).
- `auth.php` login accepts optional `restaurant_id`; logout uses the shared header reader.
- Settings → API Keys page (`api-keys.php`, `save-api-key.php`) + sidebar item: create (shown once), list, revoke.
- Todos moved to `helpers/services/todos.php` + `helpers/api/ops/todos.php`; todo partials now call the service.
- Verified live: REST CRUD, PATCH merge, enum validation, MCP initialize/tools/call, 401 without key, revoke takes effect immediately, audit rows written.

## 2026-09-25 — Agent API Phase 1: guest MCP fixes

- `html/api/mcp/server.php`: the bearer key must equal one active restaurant's `mcp_api_key` (constant-time compare); no key or unknown key = 401 (was: any restaurant's key accepted, and open to all when no key set). The key binds the call to its restaurant: `restaurant_slug` defaults to it and a different slug is refused. Successful guest writes are logged to `activity_log` (`mcp_guest_call`). Uses the shared `api_bearer_token()` (the old `$_SERVER` read never saw the header under mod_php).
- `html/partials/settings/retell-create-agent.php`: generates the restaurant's MCP key when missing and passes it to Retell as an `Authorization` header, so newly created voice agents keep working. **Existing Retell agents set up without a key must be re-created or given the key header.**
- **Bug fixed:** `helpers/availability.php` used MySQL `TIME_TO_SEC`, so every availability check (booking page, voice, SMS, MCP) failed on PostgreSQL. Replaced with `EXTRACT(EPOCH FROM ...)`.
- Remaining MySQL-only SQL noted for later phases: `reports/data.php` (HOUR/DAYOFWEEK), `settings/notifications.php` and `platform/save-platform-user.php` (ON DUPLICATE KEY), `send-reminders.php` (TIMESTAMPDIFF), `waitlist/index.php` (NOW() - INTERVAL).

## 2026-09-25 — Agent API Phase 2: reservations (reference area)

- `helpers/services/reservations.php`: list/search, get (with history and allowed next statuses), availability, create (guest match/create, table auto-assign), update (only fields sent), status transitions (guest stats, notifications). Logic lifted from `save.php`, `update.php`, `status-update.php`, which now call the service; UI output and alert ids unchanged. Search now uses ILIKE (was case-sensitive LIKE on PostgreSQL).
- `helpers/api/ops/reservations.php`: `reservations_list`, `_get`, `_availability` (GET /availability), `_create`, `_update` (PATCH), `_set_status` (409 INVALID_TRANSITION on illegal moves).
- Verified over REST and through the HTMX handlers with a logged-in session; list/calendar/feed/detail pages return 200. Added test tables T1/T2 to the local DB.

## 2026-09-25 — Agent API: guests, events, insight; smoke test and docs

- Remaining areas were split across five parallel sub-agents, each confined to its own files; results reviewed and committed area by area.
- Guests (`helpers/services/guests.php`, ops `guests_list/_search/_get/_create/_update/_add_tag/_remove_tag`), events (`events_list/_get/_create/_update/_book`, 409 on capacity/closed), insight (`dashboard_summary`, `reports_data` (manager), `messages_email_log/_sms_log/_voice_log`, `activity_log_list` (manager)). `guests/save.php`, `events/save.php`, `events/save-booking.php`, `reports/data.php` now use the services.
- Bugs fixed: Reports page used MySQL `HOUR()`/`DAYOFWEEK()` and a non-existent `s.section_name`; guest tag filter used MySQL `FIND_IN_SET`. Tags now stored lower-case and de-duplicated; guest create/update and event cancel now write activity rows.
- `scripts/api-smoke.php` + `scripts/api-parity.php`: parity check (every partial POST handler mapped to operations or exempted with a reason), OpenAPI/REST/MCP agreement, role filtering and 403/401, every read operation through REST and MCP.
- `docs/api.md` (auth, conventions, errors, examples, connecting Claude, audit, how to add a feature); README and tech-stack updated.

## 2026-09-25 — Agent API: floor (sections, tables, waitlist)

- Services `helpers/services/sections.php`, `tables.php`, `waitlist.php`; 23 operations: `sections_*` (manager), `tables_list/_create/_update/_set_active` (manager), `tables_get`, `tables_floor_status`, `tables_seat_walkin`, `tables_set_meal_status`, `tables_complete`, `waitlist_list/_open_tables/_add/_set_status/_notify/_clear`, `waitlist_checkin_reservation`, `waitlist_seat_reservation`, `waitlist_notify_reservation`.
- 13 partial handlers now call the services; HTML and ids unchanged.
- Bugs fixed: `waitlist/clear.php` ignored a failed CSRF check and wrote a non-existent `activity_log.details` column (request died after clearing); `waitlist/notify-reservation.php` read Twilio settings nothing saves, so it could never send — now uses `twilioSend()` and logs to `notifications_log`. `waitlist_add` rejects a guest from another restaurant.
- Noted, unchanged: `tables.status = 'occupied'` is never reset; "Complete & Open Table" does not bump guest `visit_count` (reservation completion does).

## 2026-09-25 — Agent API: billing (read-only) and platform administration

- `helpers/services/billing.php`, `platform.php`; 37 operations. Billing: `billing_invoices_list/_get`, `billing_prepay_history` (restaurant billing user or super-admin only). No pay/prepay operations, per decision. Platform (super-admin, restaurant IDs explicit): restaurants, users (passwords secret, hashes never returned), products + features, subscriptions, account options, nav permissions, billing settings, `platform_run_invoice_job` (generate_invoices / mark_overdue). The two invoice crons now call the billing service.
- All 16 `html/partials/platform/` POST handlers use the service; HTML and ids unchanged.
- Bugs fixed: creating a product always failed (8 placeholders, 7 values); user/product/account-option toggles failed on PostgreSQL (`NOT` on SMALLINT); creating a restaurant inserted wrong `turn_times` columns and rolled back; `save-platform-user.php` MySQL `ON DUPLICATE KEY` → `ON CONFLICT`. New guards: valid timezone, 8+ char password, super-admin can't drop own role, duplicate nav rules refused.
- Verified: staff key sees no platform ops and gets 403 on REST and MCP; password absent from audit log; all platform/billing views 200.

## 2026-09-25 — Agent API: restaurant configuration

- `helpers/services/settings.php`, ops in `helpers/api/ops/settings.php` and `hours.php`; 26 operations: profile (admin), hours / special dates (+ reservations on a date) / turn times / meal percentages (manager), notifications incl. test SMS, email templates, integrations (admin). Secrets are write-only (returned as `••••` + last 4) and excluded from the audit log.
- 8 settings handlers now use the service; alert ids, messages and HX-Trigger headers unchanged.
- Bug fixed: `settings/notifications.php` used MySQL `ON DUPLICATE KEY` in three places, so every save failed on PostgreSQL. New validation: profile time zone, notifications `from_email`.

## 2026-09-25 — Agent API: team, phone numbers, AI agents; wrap-up

- `helpers/services/team.php`, `ai_agents.php`; 39 operations: `users_*`, `phone_numbers_*`, `voice_agents_*` (incl. Retell create/fetch/update prompt/assign phone/sync), `retell_unassigned_phones`, `retell_prompt_templates`, `text_agents_*`, `email_agents_*`. Retell failures return 502 `RETELL_ERROR`. 17 settings handlers now use the services.
- Bugs fixed: text agents could not be saved (added `system_prompt`, `custom_webhook` to `text_agent_prompts` in schema + local DB); cross-tenant prompt-variable wipe on agent delete/save; duplicate-agent check used MySQL error text. Last active admin can no longer be demoted/deactivated.
- `helpers/api/auth.php`: keys of users deactivated in the restaurant (`user_restaurants.is_active = 0`) are now refused.
- `retell-create-agent` (service): MCP entry now sends `id: restaurant_mcp` (Retell rejected it without) — untested against Retell.
- **Security finding:** `helpers/retell-auth.php` falls back to a hardcoded live Retell key; sub-agent UI tests reached the real Retell account (2 LLMs + 1 agent created, then deleted). Recommend removing the fallback and rotating the key.
- Final verification: `php scripts/api-smoke.php` all checks pass (145 operations; 69 POST handlers mapped, 9 exempt; REST = MCP = OpenAPI; staff 45 / manager 71 / admin 111 / super-admin 145); every GET partial returns 200 with no new PHP errors. Test API keys revoked, test guest-MCP key removed.
- `tasks/todo.md`: all items checked, Review section written.

## 2026-09-25 — Free a table's status when its party leaves

**Prompts:** "In your Left as it was section: Tables stay marked "occupied" forever once seated.: In the floorplan section a table can be freed with the "Open Table" button, what do you mean by your statement?" — then — "Make the fix."

- Explained: the floor plan derives table state from reservations, so "Open Table" works there; the separate `tables.status` column is set to `occupied` by waitlist seating and was never reset.
- Added `svc_table_release()` (`helpers/services/tables.php`): `occupied` → `available`, leaves `blocked` alone, skips if another party is still seated at the table. Called from "Open Table" (`svc_table_complete`, also the `tables_complete` API op) and from completing a seated reservation (`svc_reservation_set_status`, also `reservations_set_status`).
- Verified live: waitlist seat → occupied; Open Table via the UI handler → available; completed via API → available; blocked stays blocked.
- Correction: the waitlist page's available-tables query (`waitlist/index.php:94-102`) is dead code, so that page was not affected. The stale value showed on the dashboard occupied count, Tables list badge, edit form and API.

## 2026-09-25 — Plan: portable repo + install Skill

**Prompt:** "Great, next we need to make sure the project repo is set that I can move it to another server and have the correct handoff instruction so that it can be installed properly.  Please add a Claude Agent Skill to the project that claude code or another agent could use to install this repository on a new server easily."

- Researched install requirements (Apache/mod_php 8.3, PHP extensions, Composer, PostgreSQL 17 schema + nav seed, writable `logs/`, 4 cron jobs, first super-admin) and blockers: per-server values and secrets hardcoded in tracked files (`config/database.php`, `config/google-oauth.php`, Retell keys in `helpers/retell-auth.php` and `scripts/launch-agent.php`), `https://zozocal.com` hardcoded for Retell MCP URL, `logs/` tracked in git, no install/verify tooling, MySQL-era seed files that must not be loaded.
- Archived the agent-API plan to `tasks/agent-api-plan.md`; wrote the new plan to `tasks/todo.md`. Awaiting approval.

## 2026-09-25 — Portable config + install Skill

**Prompt:** "Yes" (plan approved)

- `config/app.php` + `config/local.example.php`; this server's values moved to git-ignored `config/local.php` (created by script, secrets never printed). `database.php`, `google-oauth.php`, `retell-auth.php` fallback, `launch-agent.php` read from config. Retell agent URLs use `APP_URL`.
- `logs/` untracked (`.gitkeep`, ignored).
- New `scripts/check-install.php`, `scripts/create-super-admin.php`, `docs/deploy/apache-vhost.conf`, `docs/deploy/crontab`, `docs/sql/README.md`, and the Skill `.claude/skills/install-zozocal/SKILL.md`. README Setup/Security rewritten.
- Fixed: `send-reminders.php` MySQL `TIMESTAMPDIFF` (hourly job failed on PostgreSQL); OpenAI key for SMS/email agents had no UI/API — added to Settings → Integrations and `integrations_update`.
- Verified: fresh-install dry run in throwaway `zozocal_installtest` (dropped afterwards); `check-install` 0 FAIL / 2 WARN (diagnostic pages exposed, crons not installed); `api-smoke` all pass; 71 pages 200; staged diff secret scan clean. This server answers as https://zozocal.com.

## 2026-09-25 — Git history cleared

**Prompt:** "Can you clear out git history?" (chosen: fresh start — replace all history with one commit)

- Full previous history (356 commits, last `cb0d5de`) backed up to `~/backups/ZozoCal-Restaurant-history-2026-09-25.bundle` on the server (mode 600; contains the old secrets).
- Before the new commit: `docs/claude-log/` session transcripts (contain secrets) are no longer tracked (`.gitignore`), and the smoke test account's password was removed from `tasks/restaurant-only-plan.md`. Tracked files scanned: no known secret values or key patterns remain.
- Replaced `main` with a single root commit of the current files and force-pushed to GitHub; local reflog and unreachable objects pruned.
- Old secrets must still be rotated: anyone with an earlier clone, a fork, or GitHub's cached views of old commit IDs may still have them. The ZozoCal-Professional and ZozoCal-Affiliate repositories were split from this history and contain the same secrets.

## 2026-09-28 — Plan: adopt ZozoCal into the MaluDB Business OS

**Prompt:** "Next I want to integrate the application in /home/maludb/ZozoCal-Restaurant repository as an application in the system."

- Branch `os-adoption`. Surveyed the code against the plugin's `os-adopt` survey (sign-in paths, guard, users/restaurants/memberships, API/MCP/webhooks/cron, keys, security gaps) and wrote it with the proposed decisions to `docs/os-adoption.md`.
- Twelve security gaps to fix first, the worst: invitation acceptance by email alone (account takeover), a restaurant admin overwriting another restaurant's user's password, the Retell signature check commented out, unsigned SMS and email webhooks, one restaurant's Retell key used for all.
- Archived the portable-install plan to `tasks/portable-install-plan.md`; the new plan is `tasks/todo.md`. Awaiting approval and four owner decisions.

## 2026-09-28 — Owner decisions

**Prompt:** (answers) roles "Keep its three"; publishing "app_roles on its MCP"; super-admin "Kernel super-admins"; billing "Leave as is".

- Recorded in `docs/os-adoption.md` §2 and `tasks/todo.md` Step 0. Catalog key `reservations`, `/srv/apps/reservations`, database `subello_reservations`, port 8182, business area Sales & Service taken as proposed.
