CREATE TABLE IF NOT EXISTS landing_page_routes (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    host VARCHAR(255) NOT NULL,
    canonical_url VARCHAR(255) NOT NULL,
    page_include VARCHAR(255) NOT NULL,
    description VARCHAR(255) NULL,
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uk_landing_host (host),
    INDEX idx_landing_active_host (is_active, host)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO landing_page_routes (host, canonical_url, page_include, description, is_active)
VALUES (
    'zozocal.com',
    'https://zozocal.com',
    'landing/zozocal/page.php',
    'ZozoCal primary marketing landing page',
    1
)
ON DUPLICATE KEY UPDATE
    canonical_url = VALUES(canonical_url),
    page_include = VALUES(page_include),
    description = VALUES(description),
    is_active = VALUES(is_active);

INSERT INTO landing_page_routes (host, canonical_url, page_include, description, is_active)
VALUES (
    'app.zelable.com',
    'https://app.zelable.com',
    'login.php',
    'Zelable app — direct to login',
    1
)
ON DUPLICATE KEY UPDATE
    canonical_url = VALUES(canonical_url),
    page_include = VALUES(page_include),
    description = VALUES(description),
    is_active = VALUES(is_active);
