-- ============================================================
-- Restaurant Reservation System - Database Schema (Multi-Tenant SaaS)
-- Version: 2.0
-- Database: MySQL 8.0+ / MariaDB 10.11+
-- Character Set: utf8mb4 / Collation: utf8mb4_unicode_ci
-- ============================================================

CREATE DATABASE IF NOT EXISTS restaurant_reservations
    CHARACTER SET utf8mb4
    COLLATE utf8mb4_unicode_ci;

USE restaurant_reservations;

-- ============================================================
-- 1. RESTAURANTS (Tenant Entity)
-- Each restaurant is an independent tenant in the SaaS platform
-- ============================================================
CREATE TABLE restaurants (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(200) NOT NULL,
    slug VARCHAR(100) NOT NULL COMMENT 'URL-safe identifier for public booking links',
    phone VARCHAR(20) NULL,
    email VARCHAR(255) NULL,
    address_line1 VARCHAR(255) NULL,
    address_line2 VARCHAR(255) NULL,
    city VARCHAR(100) NULL,
    state VARCHAR(50) NULL,
    postal_code VARCHAR(20) NULL,
    website VARCHAR(255) NULL,
    timezone VARCHAR(50) NOT NULL DEFAULT 'America/Chicago',
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uk_slug (slug),
    INDEX idx_active (is_active)
) ENGINE=InnoDB;

-- ============================================================
-- 2. USERS (Staff Accounts — Platform-Level)
-- Users exist at the platform level; roles are per-restaurant
-- ============================================================
CREATE TABLE users (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    first_name VARCHAR(100) NOT NULL,
    last_name VARCHAR(100) NOT NULL,
    email VARCHAR(255) NOT NULL,
    password_hash VARCHAR(255) NOT NULL,
    is_platform_admin TINYINT(1) NOT NULL DEFAULT 0 COMMENT 'Platform-wide admin access',
    phone VARCHAR(20) NULL,
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    last_login_at TIMESTAMP NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uk_email (email),
    INDEX idx_active (is_active)
) ENGINE=InnoDB;

-- ============================================================
-- 3. USER_RESTAURANTS (Staff-Restaurant Membership with Role)
-- A user can belong to multiple restaurants with different roles
-- ============================================================
CREATE TABLE user_restaurants (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id INT UNSIGNED NOT NULL,
    restaurant_id INT UNSIGNED NOT NULL,
    role ENUM('owner', 'manager', 'host') NOT NULL DEFAULT 'host',
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uk_user_restaurant (user_id, restaurant_id),
    INDEX idx_restaurant (restaurant_id),
    INDEX idx_role (role),
    CONSTRAINT fk_ur_user FOREIGN KEY (user_id)
        REFERENCES users(id) ON UPDATE CASCADE ON DELETE CASCADE,
    CONSTRAINT fk_ur_restaurant FOREIGN KEY (restaurant_id)
        REFERENCES restaurants(id) ON UPDATE CASCADE ON DELETE CASCADE
) ENGINE=InnoDB;

-- ============================================================
-- 4. SETTINGS
-- Key-value store for per-restaurant configuration
-- ============================================================
CREATE TABLE settings (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    restaurant_id INT UNSIGNED NOT NULL,
    setting_key VARCHAR(100) NOT NULL,
    setting_value TEXT NULL,
    category VARCHAR(50) NOT NULL DEFAULT 'general',
    description VARCHAR(255) NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uk_restaurant_setting (restaurant_id, setting_key),
    INDEX idx_category (restaurant_id, category),
    CONSTRAINT fk_settings_restaurant FOREIGN KEY (restaurant_id)
        REFERENCES restaurants(id) ON UPDATE CASCADE ON DELETE CASCADE
) ENGINE=InnoDB;

-- ============================================================
-- 5. SECTIONS (Dining Areas)
-- ============================================================
CREATE TABLE sections (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    restaurant_id INT UNSIGNED NOT NULL,
    name VARCHAR(100) NOT NULL,
    description VARCHAR(255) NULL,
    display_order INT UNSIGNED NOT NULL DEFAULT 0,
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    open_time TIME NULL COMMENT 'Section-specific open time override',
    close_time TIME NULL COMMENT 'Section-specific close time override',
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uk_restaurant_name (restaurant_id, name),
    INDEX idx_active_order (restaurant_id, is_active, display_order),
    CONSTRAINT fk_sections_restaurant FOREIGN KEY (restaurant_id)
        REFERENCES restaurants(id) ON UPDATE CASCADE ON DELETE CASCADE
) ENGINE=InnoDB;

-- ============================================================
-- 6. TABLES (Physical Tables)
-- ============================================================
CREATE TABLE tables (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    restaurant_id INT UNSIGNED NOT NULL,
    table_name VARCHAR(50) NOT NULL COMMENT 'Display name or number (e.g., "Table 1", "Patio A")',
    section_id INT UNSIGNED NOT NULL,
    min_seats TINYINT UNSIGNED NOT NULL DEFAULT 1,
    max_seats TINYINT UNSIGNED NOT NULL,
    status ENUM('available', 'occupied', 'reserved', 'blocked') NOT NULL DEFAULT 'available',
    sort_order INT UNSIGNED NOT NULL DEFAULT 0,
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    notes VARCHAR(255) NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uk_restaurant_table (restaurant_id, table_name),
    INDEX idx_section (section_id),
    INDEX idx_status (restaurant_id, status),
    INDEX idx_seats (restaurant_id, min_seats, max_seats),
    INDEX idx_active_section (restaurant_id, is_active, section_id, sort_order),
    CONSTRAINT fk_tables_restaurant FOREIGN KEY (restaurant_id)
        REFERENCES restaurants(id) ON UPDATE CASCADE ON DELETE CASCADE,
    CONSTRAINT fk_tables_section FOREIGN KEY (section_id)
        REFERENCES sections(id) ON UPDATE CASCADE ON DELETE RESTRICT
) ENGINE=InnoDB;

-- ============================================================
-- 7. COMBINABLE TABLES
-- Defines which tables can be merged for larger parties
-- ============================================================
CREATE TABLE combinable_tables (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    table_id INT UNSIGNED NOT NULL,
    combines_with_table_id INT UNSIGNED NOT NULL,
    combined_max_seats TINYINT UNSIGNED NOT NULL COMMENT 'Max seats when combined',
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uk_combination (table_id, combines_with_table_id),
    CONSTRAINT fk_combinable_table FOREIGN KEY (table_id)
        REFERENCES tables(id) ON UPDATE CASCADE ON DELETE CASCADE,
    CONSTRAINT fk_combinable_with FOREIGN KEY (combines_with_table_id)
        REFERENCES tables(id) ON UPDATE CASCADE ON DELETE CASCADE
) ENGINE=InnoDB;

-- ============================================================
-- 8. GUESTS (Customer Profiles / CRM)
-- Guests are scoped per restaurant
-- ============================================================
CREATE TABLE guests (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    restaurant_id INT UNSIGNED NOT NULL,
    first_name VARCHAR(100) NOT NULL,
    last_name VARCHAR(100) NOT NULL,
    email VARCHAR(255) NULL,
    phone VARCHAR(20) NULL,
    address_line1 VARCHAR(255) NULL,
    address_line2 VARCHAR(255) NULL,
    city VARCHAR(100) NULL,
    state VARCHAR(50) NULL,
    postal_code VARCHAR(20) NULL,
    tags VARCHAR(500) NULL COMMENT 'Comma-separated tags: vip, regular, reviewer, etc.',
    dietary_restrictions VARCHAR(500) NULL,
    allergies VARCHAR(500) NULL,
    seating_preference VARCHAR(100) NULL COMMENT 'e.g., booth, window, quiet',
    favorite_server VARCHAR(100) NULL,
    notes TEXT NULL COMMENT 'Free-text staff notes',
    visit_count INT UNSIGNED NOT NULL DEFAULT 0,
    noshow_count INT UNSIGNED NOT NULL DEFAULT 0,
    last_visit_at DATE NULL,
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_restaurant (restaurant_id),
    INDEX idx_email (restaurant_id, email),
    INDEX idx_phone (restaurant_id, phone),
    INDEX idx_name (restaurant_id, last_name, first_name),
    INDEX idx_tags (tags(100)),
    INDEX idx_last_visit (last_visit_at),
    CONSTRAINT fk_guests_restaurant FOREIGN KEY (restaurant_id)
        REFERENCES restaurants(id) ON UPDATE CASCADE ON DELETE CASCADE
) ENGINE=InnoDB;

-- ============================================================
-- 9. RESERVATIONS
-- ============================================================
CREATE TABLE reservations (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    restaurant_id INT UNSIGNED NOT NULL,
    guest_id INT UNSIGNED NOT NULL,
    table_id INT UNSIGNED NULL COMMENT 'NULL until table is assigned',
    reservation_date DATE NOT NULL,
    reservation_time TIME NOT NULL,
    party_size TINYINT UNSIGNED NOT NULL,
    status ENUM('pending', 'confirmed', 'seated', 'completed', 'no_show', 'cancelled') NOT NULL DEFAULT 'pending',
    confirmation_code VARCHAR(20) NOT NULL,
    source ENUM('online', 'phone', 'walk_in', 'staff') NOT NULL DEFAULT 'staff',
    special_requests TEXT NULL,
    turn_time_minutes SMALLINT UNSIGNED NULL COMMENT 'Override turn time; NULL = use default',
    cancellation_reason VARCHAR(255) NULL,
    cancelled_at TIMESTAMP NULL,
    seated_at TIMESTAMP NULL,
    completed_at TIMESTAMP NULL,
    internal_notes TEXT NULL COMMENT 'Staff-only notes',
    created_by INT UNSIGNED NULL COMMENT 'Staff user who created; NULL = guest self-service',
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uk_confirmation_code (confirmation_code),
    INDEX idx_restaurant_date (restaurant_id, reservation_date, reservation_time),
    INDEX idx_date_status (restaurant_id, reservation_date, status),
    INDEX idx_guest (guest_id),
    INDEX idx_table_date (table_id, reservation_date),
    INDEX idx_status (restaurant_id, status),
    INDEX idx_source (restaurant_id, source),
    INDEX idx_created_at (restaurant_id, created_at),
    CONSTRAINT fk_reservations_restaurant FOREIGN KEY (restaurant_id)
        REFERENCES restaurants(id) ON UPDATE CASCADE ON DELETE CASCADE,
    CONSTRAINT fk_reservations_guest FOREIGN KEY (guest_id)
        REFERENCES guests(id) ON UPDATE CASCADE ON DELETE RESTRICT,
    CONSTRAINT fk_reservations_table FOREIGN KEY (table_id)
        REFERENCES tables(id) ON UPDATE CASCADE ON DELETE SET NULL,
    CONSTRAINT fk_reservations_created_by FOREIGN KEY (created_by)
        REFERENCES users(id) ON UPDATE CASCADE ON DELETE SET NULL
) ENGINE=InnoDB;

-- ============================================================
-- 10. RESERVATION TABLES (For combined/multi-table seating)
-- ============================================================
CREATE TABLE reservation_tables (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    reservation_id INT UNSIGNED NOT NULL,
    table_id INT UNSIGNED NOT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uk_reservation_table (reservation_id, table_id),
    INDEX idx_table (table_id),
    CONSTRAINT fk_restables_reservation FOREIGN KEY (reservation_id)
        REFERENCES reservations(id) ON UPDATE CASCADE ON DELETE CASCADE,
    CONSTRAINT fk_restables_table FOREIGN KEY (table_id)
        REFERENCES tables(id) ON UPDATE CASCADE ON DELETE CASCADE
) ENGINE=InnoDB;

-- ============================================================
-- 11. WAITLIST
-- ============================================================
CREATE TABLE waitlist (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    restaurant_id INT UNSIGNED NOT NULL,
    guest_id INT UNSIGNED NULL COMMENT 'NULL for unnamed walk-ins',
    guest_name VARCHAR(200) NULL COMMENT 'Quick name for unnamed walk-ins',
    party_size TINYINT UNSIGNED NOT NULL,
    phone VARCHAR(20) NULL,
    estimated_wait_minutes SMALLINT UNSIGNED NULL,
    status ENUM('waiting', 'notified', 'seated', 'left', 'no_response') NOT NULL DEFAULT 'waiting',
    queue_position INT UNSIGNED NOT NULL DEFAULT 0,
    seating_preference VARCHAR(100) NULL,
    notes VARCHAR(500) NULL,
    notified_at TIMESTAMP NULL,
    seated_at TIMESTAMP NULL,
    reservation_id INT UNSIGNED NULL COMMENT 'Linked reservation if converted',
    added_by INT UNSIGNED NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_restaurant_status (restaurant_id, status),
    INDEX idx_queue (restaurant_id, status, queue_position),
    INDEX idx_date (restaurant_id, created_at),
    CONSTRAINT fk_waitlist_restaurant FOREIGN KEY (restaurant_id)
        REFERENCES restaurants(id) ON UPDATE CASCADE ON DELETE CASCADE,
    CONSTRAINT fk_waitlist_guest FOREIGN KEY (guest_id)
        REFERENCES guests(id) ON UPDATE CASCADE ON DELETE SET NULL,
    CONSTRAINT fk_waitlist_reservation FOREIGN KEY (reservation_id)
        REFERENCES reservations(id) ON UPDATE CASCADE ON DELETE SET NULL,
    CONSTRAINT fk_waitlist_added_by FOREIGN KEY (added_by)
        REFERENCES users(id) ON UPDATE CASCADE ON DELETE SET NULL
) ENGINE=InnoDB;

-- ============================================================
-- 12. OPERATING HOURS
-- ============================================================
CREATE TABLE operating_hours (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    restaurant_id INT UNSIGNED NOT NULL,
    day_of_week TINYINT UNSIGNED NOT NULL COMMENT '0=Sunday, 1=Monday, ..., 6=Saturday',
    service_name VARCHAR(50) NOT NULL COMMENT 'e.g., Lunch, Dinner, Brunch',
    open_time TIME NOT NULL,
    close_time TIME NOT NULL,
    first_seating TIME NOT NULL,
    last_seating TIME NOT NULL,
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uk_restaurant_day_service (restaurant_id, day_of_week, service_name),
    INDEX idx_restaurant_day (restaurant_id, day_of_week),
    CONSTRAINT fk_hours_restaurant FOREIGN KEY (restaurant_id)
        REFERENCES restaurants(id) ON UPDATE CASCADE ON DELETE CASCADE
) ENGINE=InnoDB;

-- ============================================================
-- 13. SPECIAL DATES (Holidays, Blackouts, Events)
-- ============================================================
CREATE TABLE special_dates (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    restaurant_id INT UNSIGNED NOT NULL,
    special_date DATE NOT NULL,
    date_type ENUM('holiday', 'blackout', 'event', 'modified_hours') NOT NULL,
    label VARCHAR(100) NOT NULL COMMENT 'e.g., Christmas Eve, Private Event',
    is_closed TINYINT(1) NOT NULL DEFAULT 0,
    custom_open_time TIME NULL,
    custom_close_time TIME NULL,
    custom_first_seating TIME NULL,
    custom_last_seating TIME NULL,
    max_covers INT UNSIGNED NULL COMMENT 'Override max covers for this date',
    notes TEXT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uk_restaurant_date_type (restaurant_id, special_date, date_type),
    INDEX idx_restaurant_date (restaurant_id, special_date),
    CONSTRAINT fk_special_restaurant FOREIGN KEY (restaurant_id)
        REFERENCES restaurants(id) ON UPDATE CASCADE ON DELETE CASCADE
) ENGINE=InnoDB;

-- ============================================================
-- 14. TURN TIMES (Dining Duration by Party Size)
-- ============================================================
CREATE TABLE turn_times (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    restaurant_id INT UNSIGNED NOT NULL,
    min_party_size TINYINT UNSIGNED NOT NULL,
    max_party_size TINYINT UNSIGNED NOT NULL,
    service_period VARCHAR(50) NOT NULL DEFAULT 'all' COMMENT '"all", "lunch", "dinner", etc.',
    duration_minutes SMALLINT UNSIGNED NOT NULL,
    buffer_minutes SMALLINT UNSIGNED NOT NULL DEFAULT 15,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uk_restaurant_party_service (restaurant_id, min_party_size, max_party_size, service_period),
    CONSTRAINT fk_turntimes_restaurant FOREIGN KEY (restaurant_id)
        REFERENCES restaurants(id) ON UPDATE CASCADE ON DELETE CASCADE
) ENGINE=InnoDB;

-- ============================================================
-- 15. NOTIFICATIONS LOG
-- ============================================================
CREATE TABLE notifications_log (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    restaurant_id INT UNSIGNED NOT NULL,
    reservation_id INT UNSIGNED NULL,
    waitlist_id INT UNSIGNED NULL,
    guest_id INT UNSIGNED NULL,
    notification_type ENUM('confirmation', 'reminder', 'modification', 'cancellation', 'waitlist_ready', 'noshow_followup', 'staff_alert', 'thank_you') NOT NULL,
    channel ENUM('email', 'sms') NOT NULL,
    recipient VARCHAR(255) NOT NULL COMMENT 'Email address or phone number',
    subject VARCHAR(255) NULL,
    body TEXT NULL,
    status ENUM('pending', 'sent', 'failed', 'bounced') NOT NULL DEFAULT 'pending',
    provider_response TEXT NULL COMMENT 'API response from email/SMS provider',
    sent_at TIMESTAMP NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_restaurant (restaurant_id),
    INDEX idx_reservation (reservation_id),
    INDEX idx_guest (guest_id),
    INDEX idx_type_status (restaurant_id, notification_type, status),
    INDEX idx_sent_at (restaurant_id, sent_at),
    CONSTRAINT fk_notif_restaurant FOREIGN KEY (restaurant_id)
        REFERENCES restaurants(id) ON UPDATE CASCADE ON DELETE CASCADE,
    CONSTRAINT fk_notif_reservation FOREIGN KEY (reservation_id)
        REFERENCES reservations(id) ON UPDATE CASCADE ON DELETE SET NULL,
    CONSTRAINT fk_notif_waitlist FOREIGN KEY (waitlist_id)
        REFERENCES waitlist(id) ON UPDATE CASCADE ON DELETE SET NULL,
    CONSTRAINT fk_notif_guest FOREIGN KEY (guest_id)
        REFERENCES guests(id) ON UPDATE CASCADE ON DELETE SET NULL
) ENGINE=InnoDB;

-- ============================================================
-- 16. ACTIVITY LOG (Audit Trail)
-- ============================================================
CREATE TABLE activity_log (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    restaurant_id INT UNSIGNED NULL COMMENT 'NULL for platform-level actions',
    user_id INT UNSIGNED NULL COMMENT 'NULL for guest/system actions',
    action VARCHAR(50) NOT NULL COMMENT 'e.g., create, update, delete, status_change, login',
    entity_type VARCHAR(50) NOT NULL COMMENT 'e.g., reservation, guest, table, setting, restaurant',
    entity_id INT UNSIGNED NULL,
    description VARCHAR(500) NULL,
    old_value TEXT NULL COMMENT 'JSON of previous state',
    new_value TEXT NULL COMMENT 'JSON of new state',
    ip_address VARCHAR(45) NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_restaurant (restaurant_id),
    INDEX idx_user (user_id),
    INDEX idx_entity (entity_type, entity_id),
    INDEX idx_action (action),
    INDEX idx_created (created_at),
    CONSTRAINT fk_activity_restaurant FOREIGN KEY (restaurant_id)
        REFERENCES restaurants(id) ON UPDATE CASCADE ON DELETE SET NULL,
    CONSTRAINT fk_activity_user FOREIGN KEY (user_id)
        REFERENCES users(id) ON UPDATE CASCADE ON DELETE SET NULL
) ENGINE=InnoDB;

-- ============================================================
-- 17. EMAIL TEMPLATES
-- ============================================================
CREATE TABLE email_templates (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    restaurant_id INT UNSIGNED NOT NULL,
    template_key VARCHAR(50) NOT NULL COMMENT 'e.g., confirmation, reminder, cancellation',
    subject VARCHAR(255) NOT NULL,
    body_html TEXT NOT NULL,
    body_text TEXT NULL COMMENT 'Plain text fallback',
    placeholders TEXT NULL COMMENT 'JSON list of available placeholder variables',
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uk_restaurant_template (restaurant_id, template_key),
    CONSTRAINT fk_templates_restaurant FOREIGN KEY (restaurant_id)
        REFERENCES restaurants(id) ON UPDATE CASCADE ON DELETE CASCADE
) ENGINE=InnoDB;

-- ============================================================
-- SEED DATA
-- ============================================================

-- Default restaurant
INSERT INTO restaurants (id, name, slug, timezone) VALUES
(1, 'My Restaurant', 'my-restaurant', 'America/Chicago');

-- Default settings (scoped to restaurant 1)
INSERT INTO settings (restaurant_id, setting_key, setting_value, category, description) VALUES
(1, 'time_slot_interval', '30', 'reservations', 'Booking time slot interval in minutes'),
(1, 'advance_booking_min_hours', '2', 'reservations', 'Minimum hours in advance for booking'),
(1, 'advance_booking_max_days', '60', 'reservations', 'Maximum days in advance for booking'),
(1, 'same_day_cutoff_time', '20:00', 'reservations', 'Latest time for same-day online booking'),
(1, 'max_online_party_size', '8', 'reservations', 'Max party size for online booking; larger requires phone'),
(1, 'default_buffer_minutes', '15', 'reservations', 'Default buffer between seatings'),
(1, 'max_covers_per_slot', '0', 'reservations', '0 = no limit; otherwise max covers per time slot'),
(1, 'online_table_hold_percent', '80', 'reservations', 'Percent of tables available for online booking'),
(1, 'cancellation_policy', 'Cancellations must be made at least 2 hours before the reservation time.', 'reservations', 'Cancellation policy text shown to guests'),
(1, 'deposit_required_party_size', '0', 'reservations', '0 = no deposit; otherwise min party size requiring deposit'),
(1, 'deposit_amount', '0.00', 'reservations', 'Deposit amount per person'),
(1, 'reminder_hours_before', '24', 'notifications', 'Hours before reservation to send reminder'),
(1, 'reminder_enabled', '1', 'notifications', 'Enable/disable reminder notifications'),
(1, 'confirmation_email_enabled', '1', 'notifications', 'Send confirmation email on booking'),
(1, 'confirmation_sms_enabled', '0', 'notifications', 'Send confirmation SMS on booking'),
(1, 'sms_provider', '', 'notifications', 'SMS provider: twilio, vonage, or empty for disabled'),
(1, 'sms_api_key', '', 'notifications', 'SMS provider API key'),
(1, 'sms_api_secret', '', 'notifications', 'SMS provider API secret'),
(1, 'sms_from_number', '', 'notifications', 'SMS sender phone number');

-- Default turn times (scoped to restaurant 1)
INSERT INTO turn_times (restaurant_id, min_party_size, max_party_size, service_period, duration_minutes, buffer_minutes) VALUES
(1, 1, 2, 'all', 75, 15),
(1, 3, 4, 'all', 90, 15),
(1, 5, 6, 'all', 105, 15),
(1, 7, 8, 'all', 120, 15),
(1, 9, 20, 'all', 150, 20);

-- Default operating hours (scoped to restaurant 1)
INSERT INTO operating_hours (restaurant_id, day_of_week, service_name, open_time, close_time, first_seating, last_seating, is_active) VALUES
-- Monday
(1, 1, 'Lunch',  '11:00', '14:30', '11:00', '13:30', 1),
(1, 1, 'Dinner', '17:00', '22:00', '17:00', '21:00', 1),
-- Tuesday
(1, 2, 'Lunch',  '11:00', '14:30', '11:00', '13:30', 1),
(1, 2, 'Dinner', '17:00', '22:00', '17:00', '21:00', 1),
-- Wednesday
(1, 3, 'Lunch',  '11:00', '14:30', '11:00', '13:30', 1),
(1, 3, 'Dinner', '17:00', '22:00', '17:00', '21:00', 1),
-- Thursday
(1, 4, 'Lunch',  '11:00', '14:30', '11:00', '13:30', 1),
(1, 4, 'Dinner', '17:00', '22:00', '17:00', '21:00', 1),
-- Friday
(1, 5, 'Lunch',  '11:00', '14:30', '11:00', '13:30', 1),
(1, 5, 'Dinner', '17:00', '23:00', '17:00', '22:00', 1),
-- Saturday
(1, 6, 'Brunch', '10:00', '14:30', '10:00', '13:30', 1),
(1, 6, 'Dinner', '17:00', '23:00', '17:00', '22:00', 1),
-- Sunday
(1, 0, 'Brunch', '10:00', '14:30', '10:00', '13:30', 1),
(1, 0, 'Dinner', '17:00', '21:00', '17:00', '20:00', 1);

-- Default sections (scoped to restaurant 1)
INSERT INTO sections (restaurant_id, name, description, display_order, is_active) VALUES
(1, 'Main Dining', 'Primary indoor dining room', 1, 1),
(1, 'Patio', 'Outdoor seating area', 2, 1),
(1, 'Bar', 'Bar seating and high-tops', 3, 1),
(1, 'Private Room', 'Private dining room for events', 4, 1);

-- Default email templates (scoped to restaurant 1)
INSERT INTO email_templates (restaurant_id, template_key, subject, body_html, body_text, placeholders) VALUES
(1, 'confirmation', 'Reservation Confirmed - {{restaurant_name}}',
 '<h2>Your reservation is confirmed!</h2><p>Dear {{guest_name}},</p><p>We have confirmed your reservation:</p><ul><li><strong>Date:</strong> {{date}}</li><li><strong>Time:</strong> {{time}}</li><li><strong>Party Size:</strong> {{party_size}}</li><li><strong>Confirmation Code:</strong> {{confirmation_code}}</li></ul><p>To modify or cancel, visit: {{modify_url}}</p><p>See you soon!<br>{{restaurant_name}}</p>',
 'Your reservation is confirmed!\n\nDear {{guest_name}},\n\nDate: {{date}}\nTime: {{time}}\nParty Size: {{party_size}}\nConfirmation Code: {{confirmation_code}}\n\nTo modify or cancel, visit: {{modify_url}}\n\nSee you soon!\n{{restaurant_name}}',
 '["guest_name","date","time","party_size","confirmation_code","modify_url","restaurant_name"]'),

(1, 'reminder', 'Reminder: Your Reservation Tomorrow - {{restaurant_name}}',
 '<h2>Reservation Reminder</h2><p>Dear {{guest_name}},</p><p>This is a reminder of your upcoming reservation:</p><ul><li><strong>Date:</strong> {{date}}</li><li><strong>Time:</strong> {{time}}</li><li><strong>Party Size:</strong> {{party_size}}</li></ul><p>Need to make changes? Use your confirmation code <strong>{{confirmation_code}}</strong> at {{modify_url}}</p><p>We look forward to seeing you!<br>{{restaurant_name}}</p>',
 'Reservation Reminder\n\nDear {{guest_name}},\n\nDate: {{date}}\nTime: {{time}}\nParty Size: {{party_size}}\nConfirmation Code: {{confirmation_code}}\n\nNeed to make changes? Visit: {{modify_url}}\n\nWe look forward to seeing you!\n{{restaurant_name}}',
 '["guest_name","date","time","party_size","confirmation_code","modify_url","restaurant_name"]'),

(1, 'cancellation', 'Reservation Cancelled - {{restaurant_name}}',
 '<h2>Reservation Cancelled</h2><p>Dear {{guest_name}},</p><p>Your reservation has been cancelled:</p><ul><li><strong>Date:</strong> {{date}}</li><li><strong>Time:</strong> {{time}}</li><li><strong>Party Size:</strong> {{party_size}}</li></ul><p>If this was a mistake, please contact us at {{restaurant_phone}} or book a new reservation at {{booking_url}}</p><p>{{restaurant_name}}</p>',
 'Reservation Cancelled\n\nDear {{guest_name}},\n\nYour reservation has been cancelled:\nDate: {{date}}\nTime: {{time}}\nParty Size: {{party_size}}\n\nIf this was a mistake, contact us at {{restaurant_phone}}\n\n{{restaurant_name}}',
 '["guest_name","date","time","party_size","restaurant_phone","booking_url","restaurant_name"]'),

(1, 'waitlist_ready', 'Your Table is Ready! - {{restaurant_name}}',
 '<h2>Your Table is Ready!</h2><p>Dear {{guest_name}},</p><p>Great news! A table is now available for your party of {{party_size}}.</p><p>Please return to the host stand within 10 minutes to be seated.</p><p>{{restaurant_name}}</p>',
 'Your Table is Ready!\n\nDear {{guest_name}},\n\nA table is now available for your party of {{party_size}}.\nPlease return to the host stand within 10 minutes.\n\n{{restaurant_name}}',
 '["guest_name","party_size","restaurant_name"]');

-- ============================================================
-- 17. EVENTS (Special Events with Reservations)
-- ============================================================
CREATE TABLE events (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    restaurant_id INT UNSIGNED NOT NULL,
    name VARCHAR(200) NOT NULL,
    description TEXT NULL,
    event_date DATE NOT NULL,
    start_time TIME NOT NULL,
    end_time TIME NULL,
    max_capacity INT UNSIGNED NOT NULL DEFAULT 50,
    price_per_person DECIMAL(8,2) NULL,
    status ENUM('draft', 'published', 'cancelled', 'completed') NOT NULL DEFAULT 'draft',
    is_public TINYINT(1) NOT NULL DEFAULT 1,
    notes TEXT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_restaurant (restaurant_id),
    INDEX idx_date (restaurant_id, event_date),
    INDEX idx_status (restaurant_id, status),
    CONSTRAINT fk_events_restaurant FOREIGN KEY (restaurant_id)
        REFERENCES restaurants(id) ON UPDATE CASCADE ON DELETE CASCADE
) ENGINE=InnoDB;

-- ============================================================
-- 18. CALL LOGS (Voice Call Transcripts & Recordings)
-- ============================================================
CREATE TABLE call_logs (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    restaurant_id INT UNSIGNED NULL,
    guest_id INT UNSIGNED NULL,
    retell_call_id VARCHAR(100) NULL,
    direction ENUM('inbound', 'outbound') NOT NULL DEFAULT 'inbound',
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
    CONSTRAINT fk_calllogs_restaurant FOREIGN KEY (restaurant_id)
        REFERENCES restaurants(id) ON UPDATE CASCADE ON DELETE SET NULL,
    CONSTRAINT fk_calllogs_guest FOREIGN KEY (guest_id)
        REFERENCES guests(id) ON UPDATE CASCADE ON DELETE SET NULL
) ENGINE=InnoDB;

-- Default platform admin (password: changeme123)
INSERT INTO users (id, first_name, last_name, email, password_hash, is_platform_admin, is_active) VALUES
(1, 'System', 'Admin', 'admin@restaurant.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 1, 1);

-- Assign admin as owner of default restaurant
INSERT INTO user_restaurants (user_id, restaurant_id, role) VALUES
(1, 1, 'owner');
