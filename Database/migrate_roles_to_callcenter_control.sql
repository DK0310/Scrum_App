-- =====================================================
-- Migration: Split legacy staff role into callcenterstaff/controlstaff
-- Target roles: user, driver, callcenterstaff, controlstaff, admin
-- Safe for Supabase PostgreSQL
-- =====================================================

BEGIN;

-- 1) Create a new enum type with target roles
DO $$
BEGIN
    IF NOT EXISTS (
        SELECT 1
        FROM pg_type
        WHERE typname = 'user_role_v2_new'
    ) THEN
        CREATE TYPE user_role_v2_new AS ENUM (
            'user',
            'driver',
            'callcenterstaff',
            'controlstaff',
            'admin'
        );
    END IF;
END $$;

-- 2) Drop default before type conversion
ALTER TABLE users ALTER COLUMN role DROP DEFAULT;

-- 3) Convert role values
-- Mapping rules:
-- - staff / control_staff / controlstaff -> controlstaff
-- - call_center_staff / callcenterstaff -> callcenterstaff
-- - renter -> user
-- - owner -> controlstaff
ALTER TABLE users
ALTER COLUMN role TYPE user_role_v2_new
USING (
    CASE lower(replace(replace(replace(role::text, '-', ''), '_', ''), ' ', ''))
        WHEN 'user' THEN 'user'
        WHEN 'driver' THEN 'driver'
        WHEN 'admin' THEN 'admin'
        WHEN 'callcenterstaff' THEN 'callcenterstaff'
        WHEN 'controlstaff' THEN 'controlstaff'
        WHEN 'staff' THEN 'controlstaff'
        WHEN 'renter' THEN 'user'
        WHEN 'owner' THEN 'controlstaff'
        ELSE 'user'
    END
)::user_role_v2_new;

-- 4) Replace old enum type with new enum type name used by schema
DO $$
BEGIN
    IF EXISTS (SELECT 1 FROM pg_type WHERE typname = 'user_role_v2') THEN
        DROP TYPE user_role_v2;
    END IF;
END $$;

ALTER TYPE user_role_v2_new RENAME TO user_role_v2;

-- 5) Restore default
ALTER TABLE users
ALTER COLUMN role SET DEFAULT 'user'::user_role_v2;

COMMIT;
