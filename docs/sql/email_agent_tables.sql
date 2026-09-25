-- =============================================
-- Email Agent Tables
-- Agent config, LLM prompts, conversations, and messages
-- for OpenAI GPT-4.1 powered email agents
-- =============================================

-- Email agent configuration per restaurant
CREATE TABLE IF NOT EXISTS email_agent_prompts (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    restaurant_id INT UNSIGNED NOT NULL,
    agent_id VARCHAR(255) NOT NULL,
    email_address VARCHAR(255) NULL,
    description TEXT NULL,
    email_agent_greeting TEXT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uk_email_agent (restaurant_id, agent_id),
    INDEX idx_restaurant (restaurant_id),
    FOREIGN KEY (restaurant_id) REFERENCES restaurants(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Custom variables per email agent
CREATE TABLE IF NOT EXISTS email_agent_prompt_details (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    email_agent_prompt_id INT UNSIGNED NOT NULL,
    variable_name VARCHAR(255) NOT NULL,
    variable_value TEXT NULL,
    is_active TINYINT(1) DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_prompt (email_agent_prompt_id),
    FOREIGN KEY (email_agent_prompt_id) REFERENCES email_agent_prompts(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- System prompts for OpenAI email processing
CREATE TABLE IF NOT EXISTS email_prompts (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    restaurant_id INT UNSIGNED NOT NULL,
    email_agent_prompt_id INT UNSIGNED NULL,
    name VARCHAR(255) NOT NULL DEFAULT 'Default',
    system_prompt TEXT NOT NULL,
    model VARCHAR(100) NOT NULL DEFAULT 'gpt-4.1',
    temperature DECIMAL(3,2) NOT NULL DEFAULT 0.7,
    max_tokens INT UNSIGNED NOT NULL DEFAULT 2048,
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_restaurant (restaurant_id),
    INDEX idx_email_agent (email_agent_prompt_id),
    FOREIGN KEY (restaurant_id) REFERENCES restaurants(id) ON DELETE CASCADE,
    FOREIGN KEY (email_agent_prompt_id) REFERENCES email_agent_prompts(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Email conversation threads
CREATE TABLE IF NOT EXISTS email_conversations (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    restaurant_id INT UNSIGNED NOT NULL,
    email_prompt_id INT UNSIGNED NULL,
    from_email VARCHAR(255) NOT NULL,
    to_email VARCHAR(255) NOT NULL,
    subject VARCHAR(500) NULL,
    status ENUM('active','closed') NOT NULL DEFAULT 'active',
    last_message_at TIMESTAMP NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_restaurant (restaurant_id),
    INDEX idx_from_email (from_email),
    INDEX idx_to_email (to_email),
    INDEX idx_lookup (to_email, from_email, status),
    FOREIGN KEY (restaurant_id) REFERENCES restaurants(id) ON DELETE CASCADE,
    FOREIGN KEY (email_prompt_id) REFERENCES email_prompts(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Individual messages in email conversations
CREATE TABLE IF NOT EXISTS email_messages (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    email_conversation_id INT UNSIGNED NOT NULL,
    role ENUM('system','user','assistant','tool') NOT NULL,
    content TEXT NULL,
    tool_call_id VARCHAR(255) NULL,
    tool_name VARCHAR(255) NULL,
    tool_args TEXT NULL,
    tokens_used INT UNSIGNED NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_conversation (email_conversation_id),
    INDEX idx_role (role),
    FOREIGN KEY (email_conversation_id) REFERENCES email_conversations(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
