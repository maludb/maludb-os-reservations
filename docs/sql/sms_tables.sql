-- =============================================
-- SMS Agent Infrastructure Tables
-- System prompts, conversations, and message history
-- for OpenAI GPT-4.1 powered SMS agents
-- =============================================

-- System prompts sent to OpenAI for SMS processing
CREATE TABLE IF NOT EXISTS sms_prompts (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    restaurant_id INT UNSIGNED NOT NULL,
    text_agent_prompt_id INT UNSIGNED NULL,
    name VARCHAR(255) NOT NULL DEFAULT 'Default',
    system_prompt TEXT NOT NULL,
    model VARCHAR(100) NOT NULL DEFAULT 'gpt-4.1',
    temperature DECIMAL(3,2) NOT NULL DEFAULT 0.7,
    max_tokens INT UNSIGNED NOT NULL DEFAULT 1024,
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_restaurant (restaurant_id),
    INDEX idx_text_agent (text_agent_prompt_id),
    FOREIGN KEY (restaurant_id) REFERENCES restaurants(id) ON DELETE CASCADE,
    FOREIGN KEY (text_agent_prompt_id) REFERENCES text_agent_prompts(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Conversation threads between a phone number and a text agent
CREATE TABLE IF NOT EXISTS sms_conversations (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    restaurant_id INT UNSIGNED NOT NULL,
    sms_prompt_id INT UNSIGNED NULL,
    from_number VARCHAR(20) NOT NULL,
    to_number VARCHAR(20) NOT NULL,
    status ENUM('active','closed') NOT NULL DEFAULT 'active',
    last_message_at TIMESTAMP NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_restaurant (restaurant_id),
    INDEX idx_from_number (from_number),
    INDEX idx_to_number (to_number),
    INDEX idx_lookup (to_number, from_number, status),
    FOREIGN KEY (restaurant_id) REFERENCES restaurants(id) ON DELETE CASCADE,
    FOREIGN KEY (sms_prompt_id) REFERENCES sms_prompts(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Individual messages in a conversation (user, assistant, and tool messages)
CREATE TABLE IF NOT EXISTS sms_messages (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    sms_conversation_id INT UNSIGNED NOT NULL,
    role ENUM('system','user','assistant','tool') NOT NULL,
    content TEXT NULL,
    tool_call_id VARCHAR(255) NULL,
    tool_name VARCHAR(255) NULL,
    tool_args TEXT NULL,
    tokens_used INT UNSIGNED NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_conversation (sms_conversation_id),
    INDEX idx_role (role),
    FOREIGN KEY (sms_conversation_id) REFERENCES sms_conversations(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
