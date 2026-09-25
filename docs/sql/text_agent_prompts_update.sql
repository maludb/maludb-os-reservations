-- Update text_agent_prompts: replace text_agent_greeting with system_prompt,
-- add custom_webhook, make phone_number NOT NULL, auto-set agent_id from phone_number.
-- Run this on existing databases.

ALTER TABLE text_agent_prompts
    CHANGE COLUMN text_agent_greeting system_prompt TEXT NULL,
    ADD COLUMN custom_webhook VARCHAR(500) NULL COMMENT 'URL to fetch company/client context' AFTER system_prompt,
    MODIFY COLUMN phone_number VARCHAR(20) NOT NULL;

-- Backfill agent_id from phone_number for existing rows
UPDATE text_agent_prompts SET agent_id = phone_number WHERE phone_number IS NOT NULL AND phone_number != '';
