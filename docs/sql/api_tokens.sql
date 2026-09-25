-- API tokens table for mobile app / REST API authentication
CREATE TABLE IF NOT EXISTS api_tokens (
    id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id     INT UNSIGNED NOT NULL,
    restaurant_id INT UNSIGNED NOT NULL,
    token_hash  VARCHAR(64)  NOT NULL COMMENT 'SHA-256 hash of the bearer token',
    device_name VARCHAR(100) DEFAULT NULL COMMENT 'e.g. iPhone 15, iPad Pro',
    expires_at  DATETIME     NOT NULL,
    created_at  TIMESTAMP    DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uq_token_hash (token_hash),
    KEY idx_user (user_id),
    KEY idx_expires (expires_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
