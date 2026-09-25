# Restaurant Reservation System — Design Document

**Date:** 2026-03-05
**Status:** Approved (Updated for Multi-Tenant SaaS)
**Approach:** Incremental HTMX Partial Replacement

---

## Summary

Migrate the existing SalesCoach Pro (Drajeo) application to a multi-tenant Restaurant Reservation SaaS platform. Full replacement of SalesCoach content; reuse of the app shell, HTMX infrastructure, authentication, CSRF, session management, and Bootstrap 5 Kobie theme.

## Key Decisions

1. **Full replacement** — Remove all SalesCoach partials, routes, and JS. Keep shell + infrastructure.
2. **New database** — Use `restaurant_reservations` database per the SQL schema. Update `database.php`.
3. **Multi-tenant SaaS** — Shared database with `restaurant_id` on all tenant-scoped tables. Users assigned to restaurants via `user_restaurants` junction table with per-restaurant roles (owner, manager, host). Platform admins can manage all restaurants.
4. **All 4 phases + platform management** — Build prompts cover Foundation through Guest Experience, plus Prompt 21 for platform restaurant management.
5. **Full notifications** — Real SMTP email and Twilio SMS integration (not stubs). Per-restaurant settings and templates.
6. **HTMX partial pattern** — Same pattern as existing app: sidebar nav loads partials into `#page-content`.
7. **Public booking via slug** — Each restaurant has a unique slug for public booking URLs (e.g., `/booking/?restaurant=joes-bistro`).

## Architecture

### Multi-Tenant Model
- **Shared database, shared schema** — All restaurants share the same database and tables
- **Tenant isolation via restaurant_id** — Every query includes `WHERE restaurant_id = ?`
- **Platform-level users** — Users exist at the platform level; roles assigned per restaurant via `user_restaurants`
- **Restaurant switcher** — Staff with access to multiple restaurants can switch between them in the UI

### Reused Components
- `html/app.php` — Main shell (sidebar, header, footer, HTMX JS). Adapted for per-restaurant roles and restaurant switcher.
- `html/login.php` — Login page. Simplified for staff-only login.
- `helpers/auth.php` — Auth functions. Updated for per-restaurant roles (owner, manager, host) and platform admin.
- `helpers/csrf.php` — CSRF protection. No changes needed.
- `helpers/session.php` — Session management. No changes needed.
- `config/database.php` — PDO singleton. Updated to point to `restaurant_reservations` DB.
- `html/assets/` — All Bootstrap 5 / Kobie CSS and JS assets.

### New Components
- `helpers/restaurant.php` — Restaurant context management (get/set current restaurant, user-restaurant queries).
- `html/partials/` — All new partials organized by feature area. All queries scoped by restaurant_id.
- `html/partials/platform/` — Platform admin pages for managing restaurants.
- `helpers/` — New helpers for availability engine, notifications, etc.
- `html/booking/` — Public-facing guest booking pages (no auth required, uses restaurant slug).

### Directory Structure (New Partials)
```
html/partials/
  dashboard/index.php          — Staff dashboard (scoped by restaurant_id)
  reservations/
    calendar.php               — Daily/weekly calendar view
    list.php                   — Reservation list/search
    create-form.php            — New reservation form
    detail.php                 — Single reservation detail
    status-update.php          — HTMX status change endpoint
  tables/
    list.php                   — Table management CRUD
    form.php                   — Add/edit table form
    floor-plan.php             — Visual floor plan
  sections/
    list.php                   — Section management
    form.php                   — Add/edit section form
  guests/
    list.php                   — Guest directory
    detail.php                 — Guest profile
    form.php                   — Add/edit guest
  waitlist/
    index.php                  — Active waitlist queue
    add-form.php               — Add to waitlist
  settings/
    profile.php                — Restaurant profile (edits restaurants table)
    reservations.php           — Reservation rules (per-restaurant settings)
    hours.php                  — Operating hours
    special-dates.php          — Special dates/blackouts
    turn-times.php             — Turn time config
    users.php                  — Staff user management (user_restaurants for this restaurant)
    notifications.php          — Notification config (per-restaurant settings)
    email-templates.php        — Email template editor (per-restaurant templates)
  platform/
    restaurants.php            — Platform admin: manage all restaurants
    restaurant-form.php        — Add/edit restaurant
    save-restaurant.php        — POST handler
  reports/
    index.php                  — Analytics dashboard
html/booking/
    index.php                  — Public booking widget (uses restaurant slug)
    confirm.php                — Booking confirmation
    lookup.php                 — Guest self-service lookup (uses restaurant slug)
    modify.php                 — Modify reservation
    cancel.php                 — Cancel reservation
```

### Tech Stack
- PHP 8.2+ / Apache / MySQL 8.0+
- Bootstrap 5.3.3 (Kobie theme)
- HTMX 2.0.8
- Bootstrap Icons 1.11+
- Alpine.js 3.x (optional reactivity)
- jQuery (existing, for Kobie theme compatibility)

## Phase Breakdown

### Phase 1: Foundation (Prompts 1-5)
Database with multi-tenant schema, config, auth with per-restaurant roles, app shell with restaurant switcher, settings.

### Phase 2: Table & Schedule Management (Prompts 6-9)
Sections, tables, operating hours, turn times, special dates — all scoped per restaurant.

### Phase 3: Reservation Core (Prompts 10-14)
Availability engine, reservation CRUD, calendar, status workflow, floor plan — all scoped per restaurant.

### Phase 4: Guest Experience (Prompts 15-21)
Public booking (via restaurant slug), guest self-service, CRM, waitlist, notifications (per-restaurant settings/templates), dashboard/reports, platform restaurant management.
