-- Restaurant Phone Numbers table
-- Allows a company to have multiple phone numbers, each with a default language.

CREATE TABLE IF NOT EXISTS restaurant_phone_numbers (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    restaurant_id INT UNSIGNED NOT NULL,
    phone_number VARCHAR(20) NOT NULL,
    label VARCHAR(100) NULL COMMENT 'e.g. Main Line, Reservations, Spanish Line',
    language_code VARCHAR(10) NOT NULL DEFAULT 'en',
    language_name VARCHAR(50) NOT NULL DEFAULT 'English',
    is_primary TINYINT(1) NOT NULL DEFAULT 0,
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_rpn_restaurant (restaurant_id),
    INDEX idx_rpn_phone (phone_number)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Sidebar row for nav-phone-numbers lives in docs/sql/nav_permissions.sql
