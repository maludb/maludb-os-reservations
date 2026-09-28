# Plan — Make the repository portable, with an install Skill for agents

Goal: the repo can be cloned onto a new server and installed correctly by a person or by Claude
Code / another agent following one Skill, either as a **fresh install** or as a **move with data**
from the current server.

(The previous plan, the agent API, is complete and archived in `tasks/agent-api-plan.md`.)

## What research found

**What an install needs today:**
- Ubuntu + Apache 2.4 (mod_php 8.3) with the document root at `html/`.
- PHP 8.3 with `pdo_pgsql`, `curl`, `mbstring`, `xml` and `json`, plus Composer (`vendor/` is not committed).
- PostgreSQL 17, loaded from `docs/sql/pg_schema.sql` and then `docs/sql/nav_permissions.sql`.
- `logs/` writable by `www-data`.
- 4 cron jobs:
  - `html/cron/generate-invoices.php` (daily)
  - `html/cron/mark-overdue.php` (daily)
  - `helpers/send-reminders.php` (hourly)
  - `scripts/process-voice-messages.php` (every minute)
- A first super-admin. Today you register through the web form and then promote the account with SQL by hand.
- External service keys (Retell, Twilio, MailerSend, OpenAI) are stored per restaurant in the `settings` table. They move with the database and aren't part of the server config.

**What blocks a clean move:**
1. **Per-server values are committed.** These are hardcoded in tracked files:
   - `config/database.php`: database host, name, user and password
   - `config/google-oauth.php`: Google client ID and secret
   - `helpers/retell-auth.php`: a Retell key fallback
   - `scripts/launch-agent.php`: a Retell key and agent ID

   A new server would have to edit tracked files, and the secrets keep getting copied along.
2. **The domain is hardcoded.** `helpers/services/ai_agents.php` points every new Retell voice agent at `https://zozocal.com`, whatever the server's domain.
3. **`logs/` is tracked in git.** It holds 7 files, including tokens, as the README warns. They travel with every clone and show up as changes on every run.
4. **No instructions or checks.** The README "Setup" is 4 lines, and there is no crontab, no Apache config, no way to create the first admin, and nothing that verifies an install.
5. **Old seed files would break an install.** `docs/sql/prompt_templates.sql` and `landing_page_routes.sql` are still MySQL syntax and aren't needed (both tables are empty here). An installer must not load them.

## Proposed approach (keep it small)

- **One git-ignored config file per server:** `config/local.php`, copied from a committed `config/local.example.php`. It holds the DB connection, Google OAuth, the public base URL and the optional Retell fallback key and agent. Environment variables with the same names override it. The existing config files read from it, so no other code changes.
- **Two small CLI scripts:**
  - `scripts/check-install.php`: PASS/FAIL preflight and verification.
  - `scripts/create-super-admin.php`: creates the first platform admin, optionally with a restaurant, or promotes an existing user.
- **Deployment templates** in `docs/deploy/`: Apache vhost and crontab.
- **The Skill:** `.claude/skills/install-zozocal/SKILL.md`. Claude Code finds it automatically in the repo, and any agent can read it as a runbook. It covers:
  - fresh install and move-with-data (`pg_dump`/`pg_restore`)
  - which steps need the human (DNS, TLS, secrets, Google/Retell/Twilio consoles)
  - what never to do without asking (drop an existing database, overwrite `config/local.php`)
  - the verification steps at the end

## Tasks

- [x] `config/local.example.php` (committed) + `config/local.php` for this server (git-ignored, holds today's values so nothing changes here)
- [x] `config/database.php`, `config/google-oauth.php` read from local config / env; remove the committed values
- [x] Retell fallback key (`helpers/retell-auth.php`) and `scripts/launch-agent.php` key/agent read from config (same behaviour, no key in code)
- [x] `helpers/services/ai_agents.php`: Retell MCP base URL from `app_url` in config instead of `https://zozocal.com`
- [x] Stop tracking `logs/` (keep the folder with `.gitkeep`; ignore `logs/*`)
- [x] `scripts/check-install.php`: PHP version/extensions, vendor, config, DB connection, table count, nav rows, logs writable by the web user, a super-admin exists, HTTP reachability of `/login.php` and `/api/v1/index.php/openapi.json`
- [x] `scripts/create-super-admin.php` (email, name, password prompt or `--password-stdin`, optional `--restaurant`)
- [x] `docs/deploy/apache-vhost.conf`, `docs/deploy/crontab`
- [x] `.claude/skills/install-zozocal/SKILL.md`: prerequisites → clone → Composer → database → config → permissions → Apache/HTTPS → cron → first admin → verify (`check-install.php`, `api-smoke.php`) → post-install hand-off (Google redirect URIs, Retell/Twilio webhooks to the new domain, per-restaurant keys, MCP keys), plus a "move with data" path
- [x] README "Setup" replaced by a short pointer to the Skill; label the MySQL-era SQL files as not for install
- [x] Test on this server: `check-install.php` passes; app, login and API still work with the config moved; dry-run the fresh-install steps into a throwaway database (`zozocal_installtest`), then drop that database

## Not in scope (tell me if you want them)

- Rotating the secrets that are already in git history, and cleaning that history. Moving them out of tracked files stops new copies, but the old values stay in history until you rotate them.
- A container or Docker image, and provisioning the server itself (DNS, firewall, TLS certificates). The Skill lists these as steps for a person to do.

## Review

**Result:** the repo installs on a new server from `.claude/skills/install-zozocal/SKILL.md`, with no edits to tracked files.

### Changes
- **Per-server config:** `config/app.php` (`app_config()`: environment variable → `config/local.php` → default; `app_url()`), committed template `config/local.example.php`, and git-ignored `config/local.php` holding this server's existing values. Nothing changed on this server. `config/database.php` and `config/google-oauth.php` read from it.
- **Secrets out of tracked code:** DB password, Google OAuth ID/secret, the Retell fallback key (`helpers/retell-auth.php`) and the `scripts/launch-agent.php` key/agent all come from config. The staged-change secret scan was clean.
- **Domain no longer hardcoded:** new Retell agents use `APP_URL` for their webhook and MCP URLs (was `https://zozocal.com`).
- **`logs/` untracked** (kept with `.gitkeep`; `logs/*` ignored).
- **`scripts/check-install.php`:** PASS/WARN/FAIL for PHP and extensions, vendor, config, DB, schema version, nav seed, super-admin, `logs/` writable by the web user, exposed diagnostic pages, cron, and HTTP (login, OpenAPI, admin MCP).
- **`scripts/create-super-admin.php`:** creates or promotes a platform admin, optionally with a first restaurant (defaults: section, turn times, hours). Hidden password prompt or `--password-stdin`.
- **`docs/deploy/apache-vhost.conf`** and **`docs/deploy/crontab`** (4 jobs, run as www-data).
- **The Skill:** ground rules (ask before destructive steps, never print secrets, human-only steps), inputs, 10 install steps with checks, the external-service hand-off, the move-with-data path (stop old crons first, `pg_dump`/`pg_restore`), troubleshooting, and the report format.
- `README.md` Setup and Security note rewritten; `docs/sql/README.md` says to load only `pg_schema.sql` + `nav_permissions.sql`.

### Found and fixed on the way
- `helpers/send-reminders.php` used MySQL `TIMESTAMPDIFF`, so the hourly reminder job failed on PostgreSQL. All 4 cron jobs now run cleanly.
- The OpenAI key used by the SMS and email agents could not be entered anywhere (no UI, no API). Added an "OpenAI API Key" field to Settings → Integrations and to the `integrations_update` operation (write-only, masked).

### Verified
- A fresh-install dry run into a throwaway database (`zozocal_installtest`) worked: schema and nav seed loaded, admin and restaurant created, promotion worked, password verified, and `check-install` passed. The database was then dropped.
- On this server: `check-install.php` shows 0 FAIL and 2 WARN; `api-smoke.php` passes all checks; 71 pages return 200 with no new PHP errors.

### Needs your attention
1. `check-install` warnings on this server:
   - `html/info.php`, `test-db.php` and `test-email.php` are publicly reachable (phpinfo and DB details).
   - No cron jobs are installed, so invoices, reminders and voice messages never run automatically. Install with `sudo crontab -u www-data docs/deploy/crontab`.
2. **This server serves `https://zozocal.com`** (the HTTP checks reached it), so every change in this project has been live as it was pushed and deployed here.
3. Rotate the secrets that remain in git history, as the README notes.
