# Restaurant Reservation System — Software Requirements Document

**Prepared by:** Kinetic Seas  
**Version:** 1.0 Draft  
**Date:** March 2026  
**Development Tool:** Claude Code (Opus 4.6)

---

## Table of Contents

1. [Introduction](#1-introduction)
2. [User Roles and Permissions](#2-user-roles-and-permissions)
3. [Functional Requirements](#3-functional-requirements)
4. [Database Design Overview](#4-database-design-overview)
5. [User Interface Requirements](#5-user-interface-requirements)
6. [Non-Functional Requirements](#6-non-functional-requirements)
7. [Phased Delivery Plan](#7-phased-delivery-plan)
8. [Glossary](#8-glossary)

---

## 1. Introduction

This document defines the software requirements for a web-based Restaurant Reservation System. The system enables restaurant operators to manage table reservations, walk-ins, and guest relationships through an intuitive staff-facing interface, while providing guests with a streamlined online booking experience.

### 1.1 Purpose

The system replaces manual reservation books and phone-only booking with a centralized digital platform that reduces no-shows, optimizes table utilization, and improves the guest experience.

### 1.2 Scope

The application is a multi-tenant SaaS platform that supports multiple restaurants. Each restaurant operates independently with its own tables, sections, guests, reservations, operating hours, settings, and staff. Staff users can belong to multiple restaurants with different roles. The platform covers the full reservation lifecycle: online booking by guests (via restaurant-specific URLs), staff-side reservation and table management, waitlist handling, guest profile tracking, notifications, and operational reporting.

### 1.3 Technology Stack

| Component | Technology | Version |
|-----------|-----------|---------|
| Operating System | Ubuntu Linux | 22.04+ LTS |
| Web Server | Apache | 2.4+ |
| Backend Language | PHP | 8.2+ |
| Database | MySQL / MariaDB | 8.0+ / 10.11+ |
| Frontend Framework | Bootstrap | 5.3.3 |
| Dynamic Interactions | HTMX | 2.0.8 |
| Icons | Bootstrap Icons | 1.11+ |
| Optional Reactivity | Alpine.js | 3.x |
| Development Tool | Claude Code | Opus 4.6 |

### 1.4 Development Methodology

Development follows the CLAUDE.md workflow: plan in tasks/todo.md, verify with stakeholder, execute incrementally with activity logging, push to git after each successful change, and maintain simplicity throughout.

---

## 2. User Roles and Permissions

The system supports platform-level and per-restaurant roles.

### 2.1 Platform Roles

| Role | Description | Key Permissions |
|------|-------------|-----------------|
| Platform Admin | SaaS platform operator | Create/manage restaurants, manage all users, access any restaurant |

### 2.2 Per-Restaurant Roles

Users are assigned roles per restaurant via the `user_restaurants` table. A user can have different roles at different restaurants.

| Role | Description | Key Permissions |
|------|-------------|-----------------|
| Owner | Restaurant owner/operator | Full access to restaurant features, settings, and user management |
| Manager | Restaurant manager | Reservations, tables, reports, guest management |
| Host/Hostess | Front-of-house staff | Reservations, walk-ins, waitlist, seating, guest check-in |

### 2.3 Guest Access

| Role | Description | Key Permissions |
|------|-------------|-----------------|
| Guest | Restaurant customer | Online booking via restaurant-specific URL, view/modify own reservations |

---

## 3. Functional Requirements

### 3.1 Online Reservation Booking (Guest-Facing)

A public-facing booking widget allows guests to create reservations without requiring an account.

#### 3.1.1 Booking Flow

1. Guest selects date, time, and party size
2. System displays available time slots based on table inventory and turn times
3. Guest selects a time slot
4. Guest enters contact information (name, phone, email)
5. Guest optionally adds special requests (allergies, celebrations, seating preference)
6. System confirms reservation and sends confirmation email/SMS
7. Guest receives a confirmation code for lookup and modification

#### 3.1.2 Booking Rules

- Configurable advance booking window (e.g., 1 hour to 90 days)
- Configurable time slot intervals (e.g., 15, 30, or 60 minutes)
- Maximum party size limit for online booking (larger parties require phone call)
- Configurable cutoff time for same-day reservations
- Blackout dates and special hours for holidays and events
- Optional: require credit card hold or deposit for parties above a threshold

#### 3.1.3 Guest Self-Service

- Look up reservation by confirmation code and last name
- Modify reservation (date, time, party size) subject to availability
- Cancel reservation with configurable cancellation policy
- View cancellation policy and any deposit terms

### 3.2 Staff Reservation Management

Staff can create, view, edit, and cancel reservations through the admin interface.

#### 3.2.1 Reservation CRUD

- Create reservation on behalf of guest (phone/walk-up)
- Search reservations by date, guest name, phone, email, or confirmation code
- Edit any reservation details including assigned table
- Cancel reservation with reason tracking
- Mark reservation status: Confirmed, Seated, Completed, No-Show, Cancelled
- Add internal notes visible only to staff

#### 3.2.2 Calendar and Timeline Views

- Daily calendar view showing all reservations in timeline format
- Weekly overview with reservation count per day
- Color-coded status indicators (confirmed, seated, completed, no-show)
- Filter by section, table, or party size
- Drag-and-drop to reassign time slots (stretch goal)

#### 3.2.3 Reservation Status Workflow

Each reservation progresses through a defined status lifecycle:

**Pending** → **Confirmed** → **Seated** → **Completed**

Alternative terminal states: No-Show, Cancelled (by guest or staff).

### 3.3 Table Management

Manage the restaurant's physical table inventory and seating capacity.

#### 3.3.1 Table Configuration

- Define tables with: number/name, minimum seats, maximum seats, section/zone
- Sections (e.g., Main Dining, Patio, Bar, Private Room) with independent settings
- Table status: Available, Occupied, Reserved, Blocked/Maintenance
- Combinable tables (e.g., tables 5 and 6 can merge for parties of 8+)
- Table priority/preference ordering for auto-assignment

#### 3.3.2 Floor Plan View

- Visual grid or simple layout showing all tables by section
- Real-time status indicators with color coding
- Click table to view current reservation or assign walk-in
- Show estimated turn time remaining for occupied tables

#### 3.3.3 Turn Time Management

Turn time is the estimated total dining duration, used to calculate availability.

- Default turn times configurable by party size (e.g., 2-top = 75 min, 6-top = 120 min)
- Override turn time per reservation
- Different turn times for meal periods (lunch vs. dinner)
- Buffer time between seatings (configurable, e.g., 15 minutes for cleanup)

### 3.4 Waitlist Management

Handle walk-in guests when no tables are immediately available.

- Add guest to waitlist with: name, party size, phone number, arrival time
- Estimated wait time calculated from current table occupancy and turn times
- Automatic SMS notification when table is ready (optional)
- Waitlist queue display with position and wait time
- Remove from waitlist: seated, left, or no-response
- Track waitlist conversion rate (added vs. seated)

### 3.5 Guest Management (CRM)

Build and maintain guest profiles for personalized service.

#### 3.5.1 Guest Profiles

- Auto-created on first reservation (matched by email or phone)
- Contact information: name, email, phone, address (optional)
- Preferences: seating, dietary restrictions, allergies, favorite server
- Tags: VIP, regular, reviewer, difficult, etc.
- Visit history: all past reservations with dates, party sizes, spend (if tracked)
- Notes: free-text staff notes per guest
- Total visit count and no-show count

#### 3.5.2 Guest Communication

- Confirmation email/SMS on booking
- Reminder email/SMS (configurable: 24 hours and/or 2 hours before)
- Cancellation confirmation
- Waitlist notification when table ready
- Optional: post-visit thank you or feedback request

### 3.6 Walk-In Management

Support walk-in guests with immediate seating or waitlist placement.

- Quick-seat: assign walk-in directly to available table
- Record walk-in details (name, party size) for tracking
- Auto-create guest profile for walk-ins
- If no tables available, offer to add to waitlist
- Track walk-in vs. reservation ratio for analytics

### 3.7 Availability Engine

The core logic that determines available time slots for booking.

#### 3.7.1 Availability Calculation

1. Load all tables for the requested date
2. For each time slot, check existing reservations and estimated turn times
3. Subtract buffer time between seatings
4. Match party size to appropriate table sizes (no 2-top at an 8-top unless necessary)
5. Apply overbooking protection rules if configured
6. Return available slots with table assignments

#### 3.7.2 Overbooking Protection

- Configurable maximum covers per time slot
- Configurable maximum percentage of tables bookable online (hold some for walk-ins)
- Warning alerts when approaching capacity
- Automatic slot blocking when capacity reached

### 3.8 Operating Hours and Special Dates

#### 3.8.1 Regular Hours

- Configurable hours per day of week
- Multiple service periods per day (e.g., Lunch 11:00–14:00, Dinner 17:00–22:00)
- First and last seating times per service period
- Section-specific hours (e.g., Patio closes at 20:00)

#### 3.8.2 Special Dates

- Holiday hours overrides
- Blackout dates (no reservations accepted)
- Special event dates with custom capacity or seating rules
- Seasonal schedule changes

### 3.9 Notifications

| Notification | Channel | Trigger |
|-------------|---------|---------|
| Booking Confirmation | Email + SMS | Reservation created |
| Booking Reminder | Email + SMS | 24h and/or 2h before reservation |
| Modification Confirmation | Email | Reservation modified by guest or staff |
| Cancellation Confirmation | Email | Reservation cancelled |
| Waitlist Ready | SMS | Table becomes available for waitlisted guest |
| No-Show Follow-Up | Email (optional) | Guest marked as no-show |
| Staff Alert | Dashboard + Email | New booking, cancellation, or modification |

Email templates should be customizable by the administrator. SMS integration will use a configurable provider API (e.g., Twilio, Vonage).

### 3.10 Reporting and Analytics

Dashboard and exportable reports for operational insights.

#### 3.10.1 Dashboard Metrics

- Today's reservations count and covers
- Current occupancy vs. capacity
- Upcoming reservations (next 2 hours)
- Waitlist status
- No-show count (today and trailing 30 days)

#### 3.10.2 Reports

- Reservation volume by day/week/month
- Covers by service period
- No-show rate and trends
- Average party size
- Table utilization rate by section
- Peak time heatmap (busiest hours by day of week)
- Walk-in vs. reservation ratio
- Guest frequency (new vs. returning)
- Cancellation rate and timing patterns

### 3.11 Administration and Settings

#### 3.11.1 Restaurant Profile

- Restaurant name, address, phone, email, website, logo
- Timezone setting
- Currency for deposits (if applicable)

#### 3.11.2 Reservation Settings

- Time slot interval (15, 30, or 60 minutes)
- Advance booking window (min and max days ahead)
- Same-day booking cutoff time
- Maximum online party size
- Default turn times by party size
- Buffer time between seatings
- Cancellation policy text and rules
- Deposit requirements and amounts

#### 3.11.3 User Management

- Create, edit, deactivate staff accounts
- Assign roles (Super Admin, Manager, Host)
- Password reset and session management
- Activity audit log

#### 3.11.4 Notification Settings

- Enable/disable each notification type
- Configure reminder timing
- Edit email templates
- SMS provider API configuration

---

## 4. Database Design Overview

The following tables represent the core data model. All tables use InnoDB, UTF8MB4, and follow the naming conventions in the tech stack documentation.

| Table | Purpose | Key Fields |
|-------|---------|------------|
| restaurants | Tenant entity | id, name, slug, phone, email, address, timezone, is_active |
| users | Staff accounts (platform) | id, name, email, password_hash, is_platform_admin, active |
| user_restaurants | Staff-restaurant roles | id, user_id, restaurant_id, role, is_active |
| settings | Per-restaurant config | id, restaurant_id, key, value, category |
| sections | Dining areas | id, restaurant_id, name, active, display_order, open_time, close_time |
| tables | Physical tables | id, restaurant_id, name, section_id, min_seats, max_seats, status, sort_order |
| combinable_tables | Table merge rules | id, table_id, combines_with_table_id, combined_max_seats |
| guests | Guest profiles (per restaurant) | id, restaurant_id, first_name, last_name, email, phone, tags, notes, visit_count, noshow_count |
| reservations | All reservations | id, restaurant_id, guest_id, table_id, date, time, party_size, status, confirmation_code, source, special_requests, turn_time, created_by, notes |
| reservation_tables | Multi-table seating | id, reservation_id, table_id |
| waitlist | Waitlist queue | id, restaurant_id, guest_id, party_size, estimated_wait, status, notified_at, seated_at |
| operating_hours | Weekly schedule | id, restaurant_id, day_of_week, service_name, open_time, close_time, first_seating, last_seating |
| special_dates | Holidays/blackouts | id, restaurant_id, date, type, label, custom_hours |
| turn_times | Duration by party size | id, restaurant_id, min_party, max_party, service_period, duration_minutes, buffer_minutes |
| notifications_log | Sent notifications | id, restaurant_id, reservation_id, type, channel, sent_at, status |
| activity_log | Audit trail | id, restaurant_id, user_id, action, entity_type, entity_id, details, created_at |
| email_templates | Notification templates | id, restaurant_id, template_key, subject, body_html, body_text, placeholders |

See `database-documentation.md` for full schema details, and `restaurant_reservations.sql` for the executable DDL with seed data.

---

## 5. User Interface Requirements

### 5.1 Design System

The UI follows the Kinetic Seas custom Bootstrap 5 design system with the established color palette, card styles, gradient buttons, and fixed sidebar navigation. All views are responsive and mobile-friendly.

### 5.2 Staff Interface Pages

| Page | Description |
|------|-------------|
| Dashboard | Today's overview: reservation count, covers, occupancy, upcoming arrivals, alerts |
| Reservations Calendar | Daily/weekly view of all reservations with timeline visualization |
| Reservation Detail | Full reservation details with status controls, table assignment, notes |
| New Reservation | Form to create reservation with real-time availability check |
| Floor Plan | Visual table layout by section with real-time status |
| Waitlist | Active waitlist queue with estimated times and notification controls |
| Guest List | Searchable guest directory with profiles and visit history |
| Guest Detail | Individual guest profile, preferences, tags, and reservation history |
| Reports | Analytics dashboard with charts and exportable data |
| Table Setup | CRUD for tables, sections, and combinable table rules |
| Operating Hours | Weekly schedule and special dates management |
| Settings | Restaurant profile, reservation rules, notification config, user management |

### 5.3 Guest-Facing Pages

| Page | Description |
|------|-------------|
| Booking Widget | Date/time/party size selector with available slots |
| Booking Form | Contact info and special requests entry |
| Confirmation | Booking summary with confirmation code |
| Lookup/Modify | Find and modify existing reservation by code + last name |
| Cancel | Cancellation with policy display and confirmation |

### 5.4 HTMX Integration Patterns

Key HTMX patterns to be used throughout the application:

- **Availability check:** hx-get triggered on date/time/party size change, targeting the time slot grid
- **Reservation status update:** hx-put with hx-swap to update status badges inline
- **Table assignment:** hx-post from floor plan to assign or reassign tables
- **Waitlist updates:** hx-get polling or SSE for real-time waitlist position
- **Search:** hx-get with hx-trigger="keyup changed delay:300ms" for guest and reservation search
- **Modal forms:** hx-get to load form partials into Bootstrap modals
- **Inline editing:** hx-put for quick edits to reservation notes and guest tags

---

## 6. Non-Functional Requirements

### 6.1 Security

- Authentication via PHP sessions with secure cookie settings
- Password hashing with password_hash() using PASSWORD_DEFAULT
- CSRF token protection on all forms
- Prepared statements (PDO) for all database queries
- Input validation and htmlspecialchars() for XSS prevention
- Role-based access control on all staff endpoints
- Rate limiting on public booking endpoints to prevent abuse
- Confirmation codes generated with cryptographically secure random bytes

### 6.2 Performance

- Availability calculation response under 500ms
- Page load under 2 seconds on standard connection
- Database indexes on: reservations(date, status), guests(email, phone), tables(section_id)
- OpCache enabled in production
- CDN delivery for Bootstrap, HTMX, and icons

### 6.3 Reliability

- Double-booking prevention via database transactions with row locking
- Graceful error handling with user-friendly messages
- Activity audit log for all reservation changes
- Database backups (daily recommended)

### 6.4 Multi-Tenant Architecture

The system uses a shared database, shared schema multi-tenant model:
- Each restaurant is an independent tenant identified by `restaurant_id`
- All restaurant-scoped tables include a `restaurant_id` foreign key
- Data isolation is enforced at the query level — all queries must filter by `restaurant_id`
- Users exist at the platform level and are assigned to restaurants with per-restaurant roles
- Public booking pages use restaurant slugs for tenant identification
- Settings, operating hours, turn times, and email templates are per-restaurant
- Platform admins can create and manage restaurants across the system

---

## 7. Phased Delivery Plan

Development is organized into incremental phases following the CLAUDE.md workflow.

### Phase 1: Foundation

1. Project scaffolding (directory structure, config, database connection, auth)
2. User authentication and role-based access
3. Database schema creation and seed data
4. Admin layout with sidebar navigation
5. Restaurant settings and profile management

### Phase 2: Table and Schedule Management

1. Sections CRUD
2. Tables CRUD with section assignment
3. Operating hours configuration
4. Special dates and blackout management
5. Turn time configuration by party size

### Phase 3: Reservation Core

1. Availability engine (calculate open slots)
2. Staff reservation creation with availability check
3. Reservation calendar and timeline views
4. Reservation status workflow and management
5. Floor plan view with real-time table status

### Phase 4: Guest Experience

1. Public booking widget and flow
2. Guest self-service (lookup, modify, cancel)
3. Guest profile management (CRM)
4. Walk-in and waitlist management
5. Email and SMS notifications
6. Dashboard and reporting

---

## 8. Glossary

| Term | Definition |
|------|-----------|
| Covers | Total number of guests (sum of party sizes) for a given period |
| Turn Time | Estimated total dining duration from seating to table clearance |
| Buffer Time | Minutes reserved between seatings for table cleanup and reset |
| Service Period | A defined meal period (e.g., Lunch, Dinner, Brunch) |
| Blackout Date | A date on which online reservations are not accepted |
| Confirmation Code | Unique alphanumeric code given to guest for reservation lookup |
| Walk-In | A guest arriving without a reservation |
| No-Show | A guest who fails to arrive for their confirmed reservation |
| Overbooking | Accepting more reservations than available tables to account for cancellations |
| Combinable Tables | Adjacent tables that can be merged to seat larger parties |
