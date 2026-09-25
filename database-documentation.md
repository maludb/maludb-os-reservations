# Restaurant Reservation System — Database Documentation

**Version:** 2.0 (Multi-Tenant SaaS)
**Engine:** InnoDB | **Character Set:** utf8mb4 | **Collation:** utf8mb4_unicode_ci
**Compatible:** MySQL 8.0+ / MariaDB 10.11+

---

## Multi-Tenant Architecture

The database uses a **shared database, shared schema** multi-tenant model. Each restaurant is an independent tenant identified by `restaurant_id`. All restaurant-scoped tables include a `restaurant_id` foreign key to ensure data isolation. Users exist at the platform level and are assigned to restaurants via `user_restaurants` with per-restaurant roles.

---

## Entity Relationship Overview

```
                    ┌─────────────────┐
                    │   restaurants   │
                    │  (tenant root)  │
                    └────────┬────────┘
                             │
            ┌────────────────┼────────────────────┐
            │                │                    │
            ▼                ▼                    ▼
  ┌──────────────────┐  ┌──────────┐     ┌──────────────┐
  │ user_restaurants │  │ settings │     │   sections   │
  │  (role per rest) │  └──────────┘     └──────┬───────┘
  └────────┬─────────┘                          │
           │                              ┌─────┴──────┐
           ▼                              │   tables   │
    ┌──────────┐                          └─────┬──────┘
    │  users   │                                │
    │(platform)│                          ┌─────┴──────┐
    └──────────┘                          │combinable  │
                                          │  _tables   │
                                          └────────────┘

  ┌──────────┐       ┌──────────────┐       ┌──────────┐
  │  users   │       │ reservations │       │  guests  │
  │ (staff)  │──────>│ restaurant_id│<──────│  (CRM)   │
  └──────────┘       │  guest_id    │       └──────────┘
    created_by       │  table_id    │            │
                     │  status      │            │
                     └──────┬───────┘            │
                            │                    │
                   ┌────────┴────────┐           │
                   │                 │           │
            ┌──────┴──────┐  ┌──────┴───────┐   │
            │ reservation │  │ notifications│   │
            │   _tables   │  │    _log      │<──┘
            └─────────────┘  └──────────────┘

  ┌────────────────┐  ┌──────────────┐  ┌──────────────┐
  │ operating_hours│  │ special_dates│  │  turn_times  │
  │ restaurant_id  │  │ restaurant_id│  │ restaurant_id│
  └────────────────┘  └──────────────┘  └──────────────┘

  ┌──────────┐  ┌──────────────┐  ┌────────────────┐
  │ waitlist │  │ activity_log │  │ email_templates│
  │rest._id  │  │ restaurant_id│  │ restaurant_id  │
  └──────────┘  └──────────────┘  └────────────────┘
```

---

## Tables

### 1. `restaurants`

Central tenant entity. Each restaurant operates independently with its own data, settings, and staff.

| Column | Type | Nullable | Description |
|--------|------|----------|-------------|
| id | INT UNSIGNED PK | No | Auto-increment |
| name | VARCHAR(200) | No | Display name |
| slug | VARCHAR(100) | No | URL-safe identifier for public booking links |
| phone | VARCHAR(20) | Yes | |
| email | VARCHAR(255) | Yes | |
| address_line1 | VARCHAR(255) | Yes | |
| address_line2 | VARCHAR(255) | Yes | |
| city | VARCHAR(100) | Yes | |
| state | VARCHAR(50) | Yes | |
| postal_code | VARCHAR(20) | Yes | |
| website | VARCHAR(255) | Yes | |
| timezone | VARCHAR(50) | No | Default: America/Chicago |
| is_active | TINYINT(1) | No | |
| created_at | TIMESTAMP | No | |
| updated_at | TIMESTAMP | No | Auto-updated |

**Keys:** `UNIQUE(slug)`, `INDEX(is_active)`

---

### 2. `users`

Staff accounts at the platform level. Roles are assigned per-restaurant via `user_restaurants`. Guests do not have user accounts.

| Column | Type | Nullable | Description |
|--------|------|----------|-------------|
| id | INT UNSIGNED PK | No | Auto-increment |
| first_name | VARCHAR(100) | No | |
| last_name | VARCHAR(100) | No | |
| email | VARCHAR(255) | No | Unique, used for login |
| password_hash | VARCHAR(255) | No | bcrypt via password_hash() |
| is_platform_admin | TINYINT(1) | No | Platform-wide admin access (manage restaurants) |
| phone | VARCHAR(20) | Yes | |
| is_active | TINYINT(1) | No | Soft-deactivate instead of delete |
| last_login_at | TIMESTAMP | Yes | |
| created_at | TIMESTAMP | No | |
| updated_at | TIMESTAMP | No | Auto-updated |

**Keys:** `UNIQUE(email)`, `INDEX(is_active)`

**Default Account:** `admin@restaurant.com` / `changeme123` (platform admin, owner of default restaurant)

---

### 3. `user_restaurants`

Junction table assigning users to restaurants with per-restaurant roles.

| Column | Type | Nullable | Description |
|--------|------|----------|-------------|
| id | INT UNSIGNED PK | No | Auto-increment |
| user_id | INT UNSIGNED FK | No | References `users.id` |
| restaurant_id | INT UNSIGNED FK | No | References `restaurants.id` |
| role | ENUM | No | `owner`, `manager`, `host` |
| is_active | TINYINT(1) | No | Active membership in this restaurant |
| created_at | TIMESTAMP | No | |
| updated_at | TIMESTAMP | No | Auto-updated |

**Keys:** `UNIQUE(user_id, restaurant_id)`, `INDEX(restaurant_id)`, `INDEX(role)`
**FK:** `user_id` → `users.id` (CASCADE), `restaurant_id` → `restaurants.id` (CASCADE)

**Roles:**

| Role | Description |
|------|-------------|
| owner | Full access to restaurant settings, users, and all features |
| manager | Reservations, tables, reports, guest management |
| host | Reservations, walk-ins, waitlist, seating, guest check-in |

---

### 4. `settings`

Key-value store for per-restaurant configuration. Grouped by category for admin UI organization.

| Column | Type | Nullable | Description |
|--------|------|----------|-------------|
| id | INT UNSIGNED PK | No | Auto-increment |
| restaurant_id | INT UNSIGNED FK | No | References `restaurants.id` |
| setting_key | VARCHAR(100) | No | Identifier (unique per restaurant) |
| setting_value | TEXT | Yes | Configuration value |
| category | VARCHAR(50) | No | Group: reservations, notifications |
| description | VARCHAR(255) | Yes | Human-readable description |
| created_at | TIMESTAMP | No | |
| updated_at | TIMESTAMP | No | Auto-updated |

**Keys:** `UNIQUE(restaurant_id, setting_key)`, `INDEX(restaurant_id, category)`

**Seed Categories:**
- `reservations` — Slot interval, booking window, party limits, cancellation policy, deposits
- `notifications` — Reminder timing, SMS provider config

**Note:** Restaurant profile fields (name, phone, address, etc.) are stored directly on the `restaurants` table, not in settings.

---

### 5. `sections`

Dining areas/zones that contain tables. Each section can have independent hours. Scoped per restaurant.

| Column | Type | Nullable | Description |
|--------|------|----------|-------------|
| id | INT UNSIGNED PK | No | Auto-increment |
| restaurant_id | INT UNSIGNED FK | No | References `restaurants.id` |
| name | VARCHAR(100) | No | Unique per restaurant |
| description | VARCHAR(255) | Yes | |
| display_order | INT UNSIGNED | No | Sort order in UI |
| is_active | TINYINT(1) | No | |
| open_time | TIME | Yes | Override section-specific open time |
| close_time | TIME | Yes | Override section-specific close time |
| created_at | TIMESTAMP | No | |
| updated_at | TIMESTAMP | No | Auto-updated |

**Keys:** `UNIQUE(restaurant_id, name)`, `INDEX(restaurant_id, is_active, display_order)`

**Seed Data:** Main Dining, Patio, Bar, Private Room (per restaurant)

---

### 6. `tables`

Physical table inventory. Each table belongs to a section and is scoped per restaurant.

| Column | Type | Nullable | Description |
|--------|------|----------|-------------|
| id | INT UNSIGNED PK | No | Auto-increment |
| restaurant_id | INT UNSIGNED FK | No | References `restaurants.id` |
| table_name | VARCHAR(50) | No | Display name, unique per restaurant |
| section_id | INT UNSIGNED FK | No | References `sections.id` |
| min_seats | TINYINT UNSIGNED | No | Minimum party size |
| max_seats | TINYINT UNSIGNED | No | Maximum capacity |
| status | ENUM | No | `available`, `occupied`, `reserved`, `blocked` |
| sort_order | INT UNSIGNED | No | Display order within section |
| is_active | TINYINT(1) | No | |
| notes | VARCHAR(255) | Yes | e.g., "Near kitchen", "Wheelchair accessible" |
| created_at | TIMESTAMP | No | |
| updated_at | TIMESTAMP | No | Auto-updated |

**Keys:** `UNIQUE(restaurant_id, table_name)`, `INDEX(section_id)`, `INDEX(restaurant_id, status)`, `INDEX(restaurant_id, min_seats, max_seats)`
**FK:** `restaurant_id` → `restaurants.id`, `section_id` → `sections.id` (RESTRICT on delete)

---

### 7. `combinable_tables`

Defines which tables can be merged for larger parties. Inherits restaurant scope from the referenced tables.

| Column | Type | Nullable | Description |
|--------|------|----------|-------------|
| id | INT UNSIGNED PK | No | Auto-increment |
| table_id | INT UNSIGNED FK | No | First table |
| combines_with_table_id | INT UNSIGNED FK | No | Second table |
| combined_max_seats | TINYINT UNSIGNED | No | Capacity when combined |
| created_at | TIMESTAMP | No | |

**Keys:** `UNIQUE(table_id, combines_with_table_id)`
**FK:** Both reference `tables.id` (CASCADE on delete)

---

### 8. `guests`

Guest profiles for CRM. Auto-created on first reservation, matched by email or phone within the same restaurant. Scoped per restaurant.

| Column | Type | Nullable | Description |
|--------|------|----------|-------------|
| id | INT UNSIGNED PK | No | Auto-increment |
| restaurant_id | INT UNSIGNED FK | No | References `restaurants.id` |
| first_name | VARCHAR(100) | No | |
| last_name | VARCHAR(100) | No | |
| email | VARCHAR(255) | Yes | |
| phone | VARCHAR(20) | Yes | |
| address_line1 | VARCHAR(255) | Yes | Optional address fields |
| address_line2 | VARCHAR(255) | Yes | |
| city | VARCHAR(100) | Yes | |
| state | VARCHAR(50) | Yes | |
| postal_code | VARCHAR(20) | Yes | |
| tags | VARCHAR(500) | Yes | Comma-separated: vip, regular, reviewer |
| dietary_restrictions | VARCHAR(500) | Yes | |
| allergies | VARCHAR(500) | Yes | |
| seating_preference | VARCHAR(100) | Yes | booth, window, quiet, etc. |
| favorite_server | VARCHAR(100) | Yes | |
| notes | TEXT | Yes | Free-text staff notes |
| visit_count | INT UNSIGNED | No | Incremented on completion |
| noshow_count | INT UNSIGNED | No | Incremented on no-show |
| last_visit_at | DATE | Yes | |
| is_active | TINYINT(1) | No | |
| created_at | TIMESTAMP | No | |
| updated_at | TIMESTAMP | No | Auto-updated |

**Keys:** `INDEX(restaurant_id)`, `INDEX(restaurant_id, email)`, `INDEX(restaurant_id, phone)`, `INDEX(restaurant_id, last_name, first_name)`, `INDEX(tags(100))`

**Note:** Email and phone are not unique because guests may share contact info (families, assistants). A guest who visits multiple restaurants will have separate profiles per restaurant. Matching logic is handled in application code within restaurant scope.

---

### 9. `reservations`

Core reservation records. Central to the entire system. Scoped per restaurant.

| Column | Type | Nullable | Description |
|--------|------|----------|-------------|
| id | INT UNSIGNED PK | No | Auto-increment |
| restaurant_id | INT UNSIGNED FK | No | References `restaurants.id` |
| guest_id | INT UNSIGNED FK | No | References `guests.id` |
| table_id | INT UNSIGNED FK | Yes | NULL until table assigned |
| reservation_date | DATE | No | |
| reservation_time | TIME | No | |
| party_size | TINYINT UNSIGNED | No | |
| status | ENUM | No | See status workflow below |
| confirmation_code | VARCHAR(20) | No | Unique globally |
| source | ENUM | No | `online`, `phone`, `walk_in`, `staff` |
| special_requests | TEXT | Yes | Guest-visible notes |
| turn_time_minutes | SMALLINT UNSIGNED | Yes | Override; NULL = use default from turn_times |
| cancellation_reason | VARCHAR(255) | Yes | |
| cancelled_at | TIMESTAMP | Yes | |
| seated_at | TIMESTAMP | Yes | |
| completed_at | TIMESTAMP | Yes | |
| internal_notes | TEXT | Yes | Staff-only notes |
| created_by | INT UNSIGNED FK | Yes | NULL = guest self-service |
| created_at | TIMESTAMP | No | |
| updated_at | TIMESTAMP | No | Auto-updated |

**Keys:** `UNIQUE(confirmation_code)`, `INDEX(restaurant_id, reservation_date, reservation_time)`, `INDEX(restaurant_id, reservation_date, status)`, `INDEX(guest_id)`, `INDEX(table_id, reservation_date)`
**FK:** `restaurant_id` → `restaurants.id`, `guest_id` → `guests.id`, `table_id` → `tables.id`, `created_by` → `users.id`

#### Status Workflow

```
pending → confirmed → seated → completed
                 ↘               ↗ (rare)
              cancelled     no_show
```

| Status | Description |
|--------|-------------|
| pending | Just created, awaiting confirmation |
| confirmed | Confirmed by staff or auto-confirmed |
| seated | Guest has been seated |
| completed | Dining finished, table cleared |
| no_show | Guest did not arrive |
| cancelled | Cancelled by guest or staff |

---

### 10. `reservation_tables`

Junction table for multi-table (combined) seating. The primary `reservations.table_id` holds the main table; this table tracks additional tables when tables are combined. Inherits restaurant scope from the referenced reservation.

| Column | Type | Nullable | Description |
|--------|------|----------|-------------|
| id | INT UNSIGNED PK | No | Auto-increment |
| reservation_id | INT UNSIGNED FK | No | |
| table_id | INT UNSIGNED FK | No | |
| created_at | TIMESTAMP | No | |

**Keys:** `UNIQUE(reservation_id, table_id)`
**FK:** Both CASCADE on delete

---

### 11. `waitlist`

Walk-in waitlist queue management. Scoped per restaurant.

| Column | Type | Nullable | Description |
|--------|------|----------|-------------|
| id | INT UNSIGNED PK | No | Auto-increment |
| restaurant_id | INT UNSIGNED FK | No | References `restaurants.id` |
| guest_id | INT UNSIGNED FK | Yes | NULL for unnamed walk-ins |
| guest_name | VARCHAR(200) | Yes | Quick name when no guest profile |
| party_size | TINYINT UNSIGNED | No | |
| phone | VARCHAR(20) | Yes | For SMS notification |
| estimated_wait_minutes | SMALLINT UNSIGNED | Yes | Calculated estimate |
| status | ENUM | No | `waiting`, `notified`, `seated`, `left`, `no_response` |
| queue_position | INT UNSIGNED | No | Position in queue |
| seating_preference | VARCHAR(100) | Yes | |
| notes | VARCHAR(500) | Yes | |
| notified_at | TIMESTAMP | Yes | When SMS was sent |
| seated_at | TIMESTAMP | Yes | |
| reservation_id | INT UNSIGNED FK | Yes | Linked if converted to reservation |
| added_by | INT UNSIGNED FK | Yes | Staff member |
| created_at | TIMESTAMP | No | |
| updated_at | TIMESTAMP | No | Auto-updated |

**Keys:** `INDEX(restaurant_id, status)`, `INDEX(restaurant_id, status, queue_position)`

---

### 12. `operating_hours`

Weekly schedule with support for multiple service periods per day. Scoped per restaurant.

| Column | Type | Nullable | Description |
|--------|------|----------|-------------|
| id | INT UNSIGNED PK | No | Auto-increment |
| restaurant_id | INT UNSIGNED FK | No | References `restaurants.id` |
| day_of_week | TINYINT UNSIGNED | No | 0=Sunday through 6=Saturday |
| service_name | VARCHAR(50) | No | Lunch, Dinner, Brunch, etc. |
| open_time | TIME | No | Kitchen/restaurant opens |
| close_time | TIME | No | Restaurant closes |
| first_seating | TIME | No | Earliest reservation slot |
| last_seating | TIME | No | Latest reservation slot |
| is_active | TINYINT(1) | No | |
| created_at | TIMESTAMP | No | |
| updated_at | TIMESTAMP | No | Auto-updated |

**Keys:** `UNIQUE(restaurant_id, day_of_week, service_name)`, `INDEX(restaurant_id, day_of_week)`

**Seed Data:** Mon–Sat lunch/dinner, Sat–Sun brunch/dinner (per restaurant)

---

### 13. `special_dates`

Holiday overrides, blackout dates, and special events. Scoped per restaurant.

| Column | Type | Nullable | Description |
|--------|------|----------|-------------|
| id | INT UNSIGNED PK | No | Auto-increment |
| restaurant_id | INT UNSIGNED FK | No | References `restaurants.id` |
| special_date | DATE | No | |
| date_type | ENUM | No | `holiday`, `blackout`, `event`, `modified_hours` |
| label | VARCHAR(100) | No | Display name |
| is_closed | TINYINT(1) | No | If true, no reservations accepted |
| custom_open_time | TIME | Yes | Override operating hours |
| custom_close_time | TIME | Yes | |
| custom_first_seating | TIME | Yes | |
| custom_last_seating | TIME | Yes | |
| max_covers | INT UNSIGNED | Yes | Override capacity for this date |
| notes | TEXT | Yes | |
| created_at | TIMESTAMP | No | |
| updated_at | TIMESTAMP | No | Auto-updated |

**Keys:** `UNIQUE(restaurant_id, special_date, date_type)`, `INDEX(restaurant_id, special_date)`

---

### 14. `turn_times`

Configurable dining duration by party size and service period. Scoped per restaurant.

| Column | Type | Nullable | Description |
|--------|------|----------|-------------|
| id | INT UNSIGNED PK | No | Auto-increment |
| restaurant_id | INT UNSIGNED FK | No | References `restaurants.id` |
| min_party_size | TINYINT UNSIGNED | No | |
| max_party_size | TINYINT UNSIGNED | No | |
| service_period | VARCHAR(50) | No | "all" or specific period name |
| duration_minutes | SMALLINT UNSIGNED | No | Expected dining time |
| buffer_minutes | SMALLINT UNSIGNED | No | Cleanup time between seatings |
| created_at | TIMESTAMP | No | |
| updated_at | TIMESTAMP | No | Auto-updated |

**Keys:** `UNIQUE(restaurant_id, min_party_size, max_party_size, service_period)`

**Seed Data (per restaurant):**

| Party Size | Duration | Buffer |
|-----------|----------|--------|
| 1–2 | 75 min | 15 min |
| 3–4 | 90 min | 15 min |
| 5–6 | 105 min | 15 min |
| 7–8 | 120 min | 15 min |
| 9–20 | 150 min | 20 min |

---

### 15. `notifications_log`

Tracks all sent notifications for auditing and troubleshooting. Scoped per restaurant.

| Column | Type | Nullable | Description |
|--------|------|----------|-------------|
| id | INT UNSIGNED PK | No | Auto-increment |
| restaurant_id | INT UNSIGNED FK | No | References `restaurants.id` |
| reservation_id | INT UNSIGNED FK | Yes | |
| waitlist_id | INT UNSIGNED FK | Yes | |
| guest_id | INT UNSIGNED FK | Yes | |
| notification_type | ENUM | No | confirmation, reminder, modification, cancellation, waitlist_ready, noshow_followup, staff_alert, thank_you |
| channel | ENUM | No | `email`, `sms` |
| recipient | VARCHAR(255) | No | Email or phone |
| subject | VARCHAR(255) | Yes | Email subject |
| body | TEXT | Yes | Message content |
| status | ENUM | No | `pending`, `sent`, `failed`, `bounced` |
| provider_response | TEXT | Yes | Raw API response |
| sent_at | TIMESTAMP | Yes | |
| created_at | TIMESTAMP | No | |

---

### 16. `activity_log`

Audit trail for all system actions. Stores before/after state as JSON. Scoped per restaurant (NULL for platform-level actions).

| Column | Type | Nullable | Description |
|--------|------|----------|-------------|
| id | INT UNSIGNED PK | No | Auto-increment |
| restaurant_id | INT UNSIGNED FK | Yes | NULL for platform-level actions |
| user_id | INT UNSIGNED FK | Yes | NULL for guest/system actions |
| action | VARCHAR(50) | No | create, update, delete, status_change, login |
| entity_type | VARCHAR(50) | No | reservation, guest, table, setting, restaurant, etc. |
| entity_id | INT UNSIGNED | Yes | |
| description | VARCHAR(500) | Yes | Human-readable summary |
| old_value | TEXT | Yes | JSON of previous state |
| new_value | TEXT | Yes | JSON of new state |
| ip_address | VARCHAR(45) | Yes | IPv4 or IPv6 |
| created_at | TIMESTAMP | No | |

**Keys:** `INDEX(restaurant_id)`, `INDEX(user_id)`, `INDEX(entity_type, entity_id)`, `INDEX(action)`, `INDEX(created_at)`

---

### 17. `email_templates`

Customizable email templates with placeholder support. Scoped per restaurant so each restaurant can customize their messaging.

| Column | Type | Nullable | Description |
|--------|------|----------|-------------|
| id | INT UNSIGNED PK | No | Auto-increment |
| restaurant_id | INT UNSIGNED FK | No | References `restaurants.id` |
| template_key | VARCHAR(50) | No | Key unique per restaurant: confirmation, reminder, cancellation, waitlist_ready |
| subject | VARCHAR(255) | No | Supports `{{placeholders}}` |
| body_html | TEXT | No | HTML version |
| body_text | TEXT | Yes | Plain text fallback |
| placeholders | TEXT | Yes | JSON array of available variables |
| is_active | TINYINT(1) | No | |
| created_at | TIMESTAMP | No | |
| updated_at | TIMESTAMP | No | Auto-updated |

**Keys:** `UNIQUE(restaurant_id, template_key)`

**Available Placeholders:** `guest_name`, `date`, `time`, `party_size`, `confirmation_code`, `modify_url`, `restaurant_name`, `restaurant_phone`, `booking_url`

---

## Availability Engine Query Pattern

The availability calculation joins several tables to determine open slots. All queries are scoped by `restaurant_id` to ensure tenant isolation:

```sql
-- For a given restaurant, date, time range, and party size:
-- 1. Get applicable operating hours (or special_date override) for this restaurant
-- 2. Get turn time for party size for this restaurant
-- 3. Find tables that fit the party within this restaurant
-- 4. Check existing reservations that overlap the time window within this restaurant

SELECT t.id, t.table_name, t.max_seats, s.name AS section_name
FROM tables t
JOIN sections s ON t.section_id = s.id
WHERE t.restaurant_id = :restaurant_id
  AND t.is_active = 1
  AND s.is_active = 1
  AND t.max_seats >= :party_size
  AND t.min_seats <= :party_size
  AND t.id NOT IN (
      SELECT r.table_id
      FROM reservations r
      WHERE r.restaurant_id = :restaurant_id
        AND r.reservation_date = :date
        AND r.status IN ('pending', 'confirmed', 'seated')
        AND r.table_id IS NOT NULL
        AND TIME_TO_SEC(r.reservation_time) < TIME_TO_SEC(:requested_time) + (:turn_time * 60)
        AND TIME_TO_SEC(r.reservation_time) + (COALESCE(r.turn_time_minutes, :default_turn) * 60) > TIME_TO_SEC(:requested_time)
  )
ORDER BY t.max_seats ASC, t.sort_order ASC
LIMIT 1;
```

This finds the smallest available table that fits the party within the specified restaurant, avoiding oversized table assignments.

---

## Key Design Decisions

1. **Multi-tenant via restaurant_id** — All restaurant-scoped tables include a `restaurant_id` FK. Data isolation is enforced at the query level (all queries must include `WHERE restaurant_id = ?`). Users exist at the platform level and are assigned to restaurants via `user_restaurants`.

2. **Per-restaurant roles** — Roles (owner, manager, host) are defined in `user_restaurants`, not on the `users` table. A user can be an owner of one restaurant and a host at another. Platform-level admin access is via `users.is_platform_admin`.

3. **Restaurant profile on restaurants table** — Name, address, phone, etc. are stored directly on the `restaurants` table (not in settings). The `settings` table holds operational configuration only.

4. **Guests scoped per restaurant** — Each restaurant has its own guest profiles. A guest who visits multiple restaurants will have separate profiles. This keeps CRM data isolated per tenant.

5. **Guests table has no unique constraint on email/phone** — Family members and assistants may share contact info. Deduplication handled in application code within restaurant scope.

6. **Reservations.table_id is nullable** — Tables can be assigned later. Online bookings may not have a table until the host assigns one.

7. **reservation_tables junction table** — Supports combined seating when multiple tables are merged for large parties.

8. **Settings as key-value per restaurant** — Simple, flexible per-restaurant configuration without schema changes for each new setting.

9. **Soft deletes via is_active** — Users, tables, sections, and user_restaurants are deactivated, never deleted, to preserve referential integrity.

10. **Turn time as override** — Each reservation can override the default turn time, allowing flexibility for special occasions.

11. **Activity log stores JSON diffs** — The old_value/new_value columns capture before/after state for complete auditability. `restaurant_id` is nullable for platform-level actions.

12. **Confirmation codes use random_bytes** — Cryptographically secure generation prevents guessing. Codes are globally unique.

13. **Public booking via slug** — Each restaurant has a unique `slug` used in public booking URLs (e.g., `/booking/?restaurant=joes-bistro`).
