-- Retell Voice Agent API Integration
-- Add columns to restaurant_prompts for full Retell LLM + Agent management
-- Run this migration on the restaurant_reservations database

ALTER TABLE restaurant_prompts
    ADD COLUMN retell_llm_id VARCHAR(64) NULL DEFAULT NULL AFTER agent_id,
    ADD COLUMN voice_id VARCHAR(64) NULL DEFAULT NULL AFTER retell_llm_id,
    ADD COLUMN voice_model VARCHAR(64) NULL DEFAULT NULL AFTER voice_id,
    ADD COLUMN model VARCHAR(64) NULL DEFAULT 'gpt-4.1' AFTER voice_model,
    ADD COLUMN agent_prompt TEXT NULL DEFAULT NULL AFTER model,
    ADD COLUMN begin_message TEXT NULL DEFAULT NULL AFTER agent_prompt,
    ADD COLUMN transfer_number VARCHAR(20) NULL DEFAULT NULL AFTER begin_message,
    ADD COLUMN agent_swap_id VARCHAR(64) NULL DEFAULT NULL AFTER transfer_number,
    ADD COLUMN webhook_url VARCHAR(512) NULL DEFAULT NULL AFTER agent_swap_id,
    ADD COLUMN inbound_webhook_url VARCHAR(512) NULL DEFAULT NULL AFTER webhook_url,
    ADD COLUMN mcp_url VARCHAR(512) NULL DEFAULT NULL AFTER inbound_webhook_url,
    ADD COLUMN mcp_headers TEXT NULL DEFAULT NULL AFTER mcp_url,
    ADD COLUMN is_synced TINYINT(1) NOT NULL DEFAULT 0 AFTER mcp_headers;
