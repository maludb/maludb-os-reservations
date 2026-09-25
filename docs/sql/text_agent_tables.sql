-- =============================================
-- Text Agent Tables
-- Stores text/SMS agent configurations per restaurant
-- Mirrors voice agent structure (restaurant_prompts)
-- =============================================

CREATE TABLE IF NOT EXISTS text_agent_prompts (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    restaurant_id INT UNSIGNED NOT NULL,
    agent_id VARCHAR(255) NOT NULL COMMENT 'Auto-set from phone_number',
    phone_number VARCHAR(20) NOT NULL,
    description TEXT NULL,
    system_prompt TEXT NULL,
    custom_webhook VARCHAR(500) NULL COMMENT 'URL to fetch company/client context',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uk_text_agent (restaurant_id, agent_id),
    INDEX idx_restaurant (restaurant_id),
    FOREIGN KEY (restaurant_id) REFERENCES restaurants(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS text_agent_prompt_details (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    text_agent_prompt_id INT UNSIGNED NOT NULL,
    variable_name VARCHAR(255) NOT NULL,
    variable_value TEXT NULL,
    is_active TINYINT(1) DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_prompt (text_agent_prompt_id),
    FOREIGN KEY (text_agent_prompt_id) REFERENCES text_agent_prompts(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
