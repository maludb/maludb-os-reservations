# ZozoCal-Restaurant

A multi-tenant restaurant reservation system. Restaurant staff manage table reservations,
walk-ins, the waitlist and guest relationships; guests book, change and cancel online, by SMS,
by email or by talking to an AI voice agent.

This is the original ZozoCal product and the one the codebase was first built for.

## Why this repository exists

ZozoCal began as a restaurant reservation system. It was later stretched into a generic scheduler
for professional services, and then into an affiliate system, all inside one codebase switched by
a `product_type` column. Those three products should not live together, so on 2026-09-21 the
combined codebase was split into three repositories:

| Repository | Product |
|------------|---------|
| **ZozoCal-Restaurant** (this one) | Restaurant reservation system |
| [ZozoCal-Professional](https://github.com/maludb-ed/ZozoCal-Professional) | Appointment scheduling for professional services |
| [ZozoCal-Affiliate](https://github.com/maludb-ed/ZozoCal-Affiliate) | Affiliate referral and prospect pipeline |

**Current state:** the professional scheduling and affiliate code, along with an older dead
SalesCoach Pro code island, were removed from this repository on 2026-09-21. Only the restaurant
reservation product remains. Every registration creates a restaurant.

## What belongs here

- Reservations, tables, floor sections, waitlist and guests — `html/partials/reservations/`,
  `tables/`, `sections/`, `waitlist/`, `guests/`, `calendar/`
- Public guest booking flow — `html/booking/`
- AI voice, SMS and email reservation agents — `html/api/retell/`, `html/api/sms/`,
  `html/api/email/`, `html/api/mcp/`
- Restaurant settings, team, reports, notifications, todo list and billing
- Agent API: every staff/admin function as REST (`html/api/v1/index.php`) and MCP (`html/api/mcp/admin.php`),
  generated from one operation registry (`helpers/api/`), with shared logic in `helpers/services/` — see `docs/api.md`
- Platform administration of restaurant accounts — `html/partials/platform/`
- Requirements — `restaurant-reservation-requirements.md`
- Sidebar visibility per role — `docs/sql/nav_permissions.sql` (loaded after the schema)

## Technology

- PHP on Apache (traditional LAMP layout — `html/` is the web root, everything above it is private)
- PostgreSQL 17 — full schema in `docs/sql/pg_schema.sql`
- HTMX partials under `html/partials/`, Bootstrap 5 (Kobie theme)
- Retell AI for voice agents; SMS and email agents backed by OpenAI

See `tech-stack.md` for the details.

## Setup

Follow the install Skill: [`.claude/skills/install-zozocal/SKILL.md`](.claude/skills/install-zozocal/SKILL.md).
Claude Code picks it up automatically in this repo; ask it to "install ZozoCal on this server". People can
follow it by hand. It covers a fresh install and moving an existing install with its data.

In short: Apache + PHP 8.3 (`pdo_pgsql`, `curl`, `mbstring`, `xml`), Composer, PostgreSQL 17 loaded from
`docs/sql/pg_schema.sql` then `docs/sql/nav_permissions.sql`, per-server settings in `config/local.php`
(copy `config/local.example.php`; git-ignored), `docs/deploy/apache-vhost.conf`, `docs/deploy/crontab`,
`php scripts/create-super-admin.php` for the first admin, and `php scripts/check-install.php` to verify.

## Security note

Per-server settings and secrets now live in `config/local.php` (git-ignored) or environment variables,
and `logs/` is no longer tracked. The repository **history** still contains old credentials (database
password, Google OAuth client secret, Retell API keys, tokens in `logs/`). Rotate them and clean the
history before this repository is ever made public.

## License

Apache License 2.0 — see `LICENSE`.
