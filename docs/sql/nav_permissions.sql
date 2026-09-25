-- ============================================================
-- Sidebar navigation permissions — seed data (PostgreSQL)
-- ============================================================
-- Each row grants visibility of one sidebar item (matched by the <li> id in
-- html/app.php) to a user_role (users.role) and/or restaurant_role
-- (user_restaurants.role). NULL means "any". See getPermittedNavItems() in
-- helpers/auth.php. Load after pg_schema.sql.

-- Always visible
INSERT INTO nav_permissions (nav_item_id, label, user_role, restaurant_role) VALUES
('nav-caption-spacer', 'Spacer', NULL, NULL),
('nav-logout', 'Logout', NULL, NULL);

-- SCHEDULING: all restaurant staff
INSERT INTO nav_permissions (nav_item_id, label, user_role, restaurant_role) VALUES
('nav-caption-scheduling', 'SCHEDULING caption', NULL, NULL),
('nav-dashboard', 'Dashboard', NULL, NULL),
('nav-reservations', 'Reservations', NULL, NULL),
('nav-floor-plan', 'Floor Plan', NULL, NULL),
('nav-waitlist', 'Waitlist', NULL, NULL),
('nav-events', 'Events', NULL, NULL),
('nav-todos', 'Todo List', NULL, NULL),
('nav-sms-messages', 'SMS Messages', NULL, NULL),
('nav-voice-calls', 'Voice Calls', NULL, NULL),
('nav-email-messages', 'Email Messages', NULL, NULL);

-- GUESTS: all restaurant staff
INSERT INTO nav_permissions (nav_item_id, label, user_role, restaurant_role) VALUES
('nav-caption-guests', 'GUESTS caption', NULL, NULL),
('nav-guests', 'Guest Directory', NULL, NULL);

-- MANAGEMENT: admins and managers
INSERT INTO nav_permissions (nav_item_id, label, user_role, restaurant_role) VALUES
('nav-caption-management', 'MANAGEMENT caption', NULL, 'admin'),
('nav-caption-management', 'MANAGEMENT caption', NULL, 'manager'),
('nav-table-setup', 'Table Setup', NULL, 'admin'),
('nav-table-setup', 'Table Setup', NULL, 'manager'),
('nav-sections', 'Sections', NULL, 'admin'),
('nav-sections', 'Sections', NULL, 'manager'),
('nav-hours', 'Operating Hours', NULL, 'admin'),
('nav-hours', 'Operating Hours', NULL, 'manager'),
('nav-turn-times', 'Turn Times', NULL, 'admin'),
('nav-turn-times', 'Turn Times', NULL, 'manager'),
('nav-special-dates', 'Special Dates', NULL, 'admin'),
('nav-special-dates', 'Special Dates', NULL, 'manager'),
('nav-reports', 'Reports', NULL, 'admin'),
('nav-reports', 'Reports', NULL, 'manager');

-- SETTINGS: admins
INSERT INTO nav_permissions (nav_item_id, label, user_role, restaurant_role) VALUES
('nav-caption-settings', 'SETTINGS caption', NULL, 'admin'),
('nav-profile', 'Business Profile', NULL, 'admin'),
('nav-notifications', 'Notifications', NULL, 'admin'),
('nav-staff', 'Staff Users', NULL, 'admin'),
('nav-integrations', 'Integrations', NULL, 'admin'),
('nav-api-keys', 'API Keys', NULL, 'admin'),
('nav-phone-numbers', 'Phone Numbers', NULL, 'admin'),
('nav-voice-prompts', 'Voice Prompts', NULL, 'admin'),
('nav-text-agents', 'Text Agents', NULL, 'admin'),
('nav-email-agents', 'Email Agents', NULL, 'admin');

-- BILLING: admins, managers and super-admins
INSERT INTO nav_permissions (nav_item_id, label, user_role, restaurant_role) VALUES
('nav-caption-billing', 'BILLING caption', NULL, 'admin'),
('nav-caption-billing', 'BILLING caption', NULL, 'manager'),
('nav-billing', 'Billing', NULL, 'admin'),
('nav-billing', 'Billing', NULL, 'manager'),
('nav-caption-billing', 'BILLING caption', 'super-admin', NULL),
('nav-billing', 'Billing', 'super-admin', NULL);

-- PLATFORM: super-admins
INSERT INTO nav_permissions (nav_item_id, label, user_role, restaurant_role) VALUES
('nav-caption-platform', 'PLATFORM caption', 'super-admin', NULL),
('nav-manage-restaurants', 'Manage Restaurants', 'super-admin', NULL),
('nav-platform-users', 'Users', 'super-admin', NULL),
('nav-products', 'Products & Pricing', 'super-admin', NULL),
('nav-subscriptions', 'Subscriptions', 'super-admin', NULL),
('nav-account-options', 'Account Options', 'super-admin', NULL),
('nav-platform-billing', 'Billing Config', 'super-admin', NULL);
