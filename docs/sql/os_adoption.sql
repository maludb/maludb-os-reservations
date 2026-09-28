-- ZozoCal — changes made while adopting the application into the MaluDB Business OS (2026-09).
-- Load after pg_schema.sql and nav_permissions.sql, on a new install and on an existing one:
-- every statement is safe to run again.

-- Invitations carry a one-time code (security fix S1): accepting one needs the code the admin was shown,
-- not just the email address. Only a SHA-256 of the code is kept.
ALTER TABLE users ADD COLUMN IF NOT EXISTS invite_code_hash char(64);
ALTER TABLE users ADD COLUMN IF NOT EXISTS invite_expires_at timestamp;

-- ---------------------------------------------------------------------------------------------------------------
-- Sign-on from the Business OS kernel (docs/os-adoption.md). Nothing here changes a standalone install's behaviour:
-- it is read only while OS_ENABLED is on.

-- The kernel's ids, beside ZozoCal's own: a user is one kernel member, a restaurant one kernel scope (a site).
ALTER TABLE users ADD COLUMN IF NOT EXISTS os_member_id bigint;
CREATE UNIQUE INDEX IF NOT EXISTS users_os_member_id_key ON users (os_member_id);
ALTER TABLE restaurants ADD COLUMN IF NOT EXISTS os_scope_id bigint;
CREATE UNIQUE INDEX IF NOT EXISTS restaurants_os_scope_id_key ON restaurants (os_scope_id);

-- Who made a membership: 'local' (ZozoCal's own screens) or 'os' (the kernel's grant; replaced whole on every
-- sign-on and directory sync). While OS_ENABLED is on only 'os' rows admit anyone.
ALTER TABLE user_restaurants ADD COLUMN IF NOT EXISTS source varchar(5) NOT NULL DEFAULT 'local';
DO $$ BEGIN
    ALTER TABLE user_restaurants ADD CONSTRAINT user_restaurants_source_check CHECK (source IN ('local', 'os'));
EXCEPTION WHEN duplicate_object THEN NULL; END $$;

-- A hand-off token is single use: its nonce is kept until the token would have expired.
CREATE TABLE IF NOT EXISTS sso_nonces (
    nonce       text PRIMARY KEY,
    member_id   bigint NOT NULL,
    expires_at  timestamptz NOT NULL,
    created_at  timestamptz NOT NULL DEFAULT now()
);
CREATE INDEX IF NOT EXISTS sso_nonces_expires_idx ON sso_nonces (expires_at);

-- Every session opened through the kernel, so a sign-out notice or a revocation ends them all.
CREATE TABLE IF NOT EXISTS member_sessions (
    session_hash  text PRIMARY KEY,              -- sha256 of the PHP session id
    member_id     bigint NOT NULL,               -- the kernel's member id
    created_at    timestamptz NOT NULL DEFAULT now(),
    last_seen_at  timestamptz NOT NULL DEFAULT now(),
    ended_at      timestamptz,
    ended_by      text CHECK (ended_by IN ('member', 'kernel', 'expired', 'directory'))
);
CREATE INDEX IF NOT EXISTS member_sessions_member_idx ON member_sessions (member_id) WHERE ended_at IS NULL;

-- The kernel's change feed cursor, one row.
CREATE TABLE IF NOT EXISTS directory_sync_state (
    id           smallint PRIMARY KEY DEFAULT 1 CHECK (id = 1),
    next_cursor  text,
    full_at      timestamptz,
    last_run_at  timestamptz,
    last_error   text,
    updated_at   timestamptz NOT NULL DEFAULT now()
);
INSERT INTO directory_sync_state (id) VALUES (1) ON CONFLICT DO NOTHING;

-- The roles ZozoCal publishes to the kernel (app_roles on /api/mcp/admin.php) and the rights each gives —
-- in ZozoCal's own words, matching what its guards already allow (requireAdmin, requireManager, requireAuth).
CREATE TABLE IF NOT EXISTS app_rights (
    right_key    text PRIMARY KEY,
    description  text NOT NULL,
    sort_order   integer NOT NULL
);
CREATE TABLE IF NOT EXISTS app_roles (
    role_key     text PRIMARY KEY,
    name         text NOT NULL,
    description  text NOT NULL,
    capability   text NOT NULL CHECK (capability IN ('read', 'write', 'admin')),
    is_admin     boolean NOT NULL DEFAULT false,
    rights       text[] NOT NULL,
    sort_order   integer NOT NULL
);
INSERT INTO app_rights (right_key, description, sort_order) VALUES
    ('reservations.work',   'See, make, change and seat reservations, clear tables, look up guests and messages', 1),
    ('restaurant.setup',    'Set up the restaurant: tables, sections, hours, turn times, booking rules, AI agents, reports', 2),
    ('restaurant.admin',    'Everything in the restaurant, including its integrations, API keys and billing details', 3)
ON CONFLICT (right_key) DO UPDATE SET description = EXCLUDED.description, sort_order = EXCLUDED.sort_order;
INSERT INTO app_roles (role_key, name, description, capability, is_admin, rights, sort_order) VALUES
    ('admin',   'Restaurant admin', 'Runs the restaurant in ZozoCal: all setup, integrations and keys.', 'admin', true,
        ARRAY['reservations.work', 'restaurant.setup', 'restaurant.admin'], 1),
    ('manager', 'Manager', 'Runs service and the restaurant''s setup.', 'write', false,
        ARRAY['reservations.work', 'restaurant.setup'], 2),
    ('user',    'Staff', 'Works the floor: reservations, seating and tables.', 'write', false,
        ARRAY['reservations.work'], 3)
ON CONFLICT (role_key) DO UPDATE SET name = EXCLUDED.name, description = EXCLUDED.description, capability = EXCLUDED.capability,
    is_admin = EXCLUDED.is_admin, rights = EXCLUDED.rights, sort_order = EXCLUDED.sort_order;
