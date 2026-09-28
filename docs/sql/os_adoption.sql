-- ZozoCal — changes made while adopting the application into the MaluDB Business OS (2026-09).
-- Load after pg_schema.sql and nav_permissions.sql, on a new install and on an existing one:
-- every statement is safe to run again.

-- Invitations carry a one-time code (security fix S1): accepting one needs the code the admin was shown,
-- not just the email address. Only a SHA-256 of the code is kept.
ALTER TABLE users ADD COLUMN IF NOT EXISTS invite_code_hash char(64);
ALTER TABLE users ADD COLUMN IF NOT EXISTS invite_expires_at timestamp;
