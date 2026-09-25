-- Multi-Language Voice Agent Relay — Database Changes
-- Multi-language voice relay: adds language columns to restaurant_prompts and the voice_messages queue

-- 1. Add language fields to restaurant_prompts
ALTER TABLE restaurant_prompts
  ADD COLUMN language_code VARCHAR(10) NOT NULL DEFAULT 'en' AFTER phone_number,
  ADD COLUMN language_name VARCHAR(50) NOT NULL DEFAULT 'English' AFTER language_code,
  ADD COLUMN is_primary_language TINYINT(1) NOT NULL DEFAULT 0 AFTER language_name;

-- 2. Create voice_messages relay queue
CREATE TABLE voice_messages (
  id                   INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  restaurant_id        INT UNSIGNED NOT NULL,
  call_log_id          INT UNSIGNED     NULL COMMENT 'source inbound call',
  source_prompt_id     INT UNSIGNED     NULL COMMENT 'restaurant_prompts.id that received message',
  target_prompt_id     INT UNSIGNED     NULL COMMENT 'restaurant_prompts.id that will deliver',
  from_language        VARCHAR(10)  NOT NULL,
  to_language          VARCHAR(10)  NOT NULL,
  message_text         TEXT         NOT NULL COMMENT 'original transcript/summary',
  recipient_phone      VARCHAR(20)  NOT NULL COMMENT 'who gets the outbound call',
  recipient_type       ENUM('business_staff','customer') NOT NULL DEFAULT 'business_staff',
  status               ENUM('pending','in_progress','delivered','failed') NOT NULL DEFAULT 'pending',
  delivery_call_id     VARCHAR(100)     NULL COMMENT 'retell call_id of outbound delivery',
  delivery_call_log_id INT UNSIGNED     NULL COMMENT 'call_logs.id for delivery call',
  error_message        VARCHAR(500)     NULL,
  retry_count          TINYINT UNSIGNED NOT NULL DEFAULT 0,
  created_at           TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
  delivered_at         TIMESTAMP        NULL,
  INDEX idx_vm_restaurant (restaurant_id),
  INDEX idx_vm_status (status),
  INDEX idx_vm_source (call_log_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 3. Enable multi-language for a specific business (replace <restaurant_id> with actual ID)
-- INSERT INTO account_options (restaurant_id, option_key, option_value, enabled, notes)
-- VALUES (<restaurant_id>, 'multi_language_enabled', '1', 1, 'Enable multi-language voice relay');
