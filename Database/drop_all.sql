-- =====================================================
-- DriveNow - Drop All Tables & Types
-- Run this BEFORE re-running schema.sql
-- Order: drop dependent tables first (FK constraints)
-- =====================================================

-- Drop all triggers (guard table existence to avoid 42P01)
DO $$
BEGIN
	IF to_regclass('public.users') IS NOT NULL THEN
		EXECUTE 'DROP TRIGGER IF EXISTS trg_users_updated_at ON users';
	END IF;

	IF to_regclass('public.vehicles') IS NOT NULL THEN
		EXECUTE 'DROP TRIGGER IF EXISTS trg_vehicles_updated_at ON vehicles';
	END IF;

	IF to_regclass('public.bookings') IS NOT NULL THEN
		EXECUTE 'DROP TRIGGER IF EXISTS trg_bookings_updated_at ON bookings';
	END IF;

	IF to_regclass('public.reviews') IS NOT NULL THEN
		EXECUTE 'DROP TRIGGER IF EXISTS trg_reviews_updated_at ON reviews';
	END IF;

	IF to_regclass('public.community_posts') IS NOT NULL THEN
		EXECUTE 'DROP TRIGGER IF EXISTS trg_posts_updated_at ON community_posts';
	END IF;

	IF to_regclass('public.active_trips') IS NOT NULL THEN
		EXECUTE 'DROP TRIGGER IF EXISTS trg_active_trips_updated_at ON active_trips';
	END IF;
END $$;

-- Drop trigger function (with CASCADE for dependent objects)
DROP FUNCTION IF EXISTS update_updated_at() CASCADE;

-- Drop all tables (order matters — drop child tables before parent)
-- NOTE: n8n_chat_histories is managed by n8n, do NOT drop it
DROP TABLE IF EXISTS gps_tracking CASCADE;
DROP TABLE IF EXISTS trip_enquiries CASCADE;
DROP TABLE IF EXISTS active_trips CASCADE;
DROP TABLE IF EXISTS memberships CASCADE;
DROP TABLE IF EXISTS notifications CASCADE;
DROP TABLE IF EXISTS favorites CASCADE;
DROP TABLE IF EXISTS community_likes CASCADE;
DROP TABLE IF EXISTS community_comments CASCADE;
DROP TABLE IF EXISTS community_posts CASCADE;
DROP TABLE IF EXISTS vehicle_assignments CASCADE;
DROP TABLE IF EXISTS reviews CASCADE;
DROP TABLE IF EXISTS payments CASCADE;
DROP TABLE IF EXISTS bookings CASCADE;
DROP TABLE IF EXISTS vehicle_images CASCADE;
DROP TABLE IF EXISTS vehicles CASCADE;
DROP TABLE IF EXISTS auth_sessions CASCADE;
DROP TABLE IF EXISTS promotions CASCADE;
DROP TABLE IF EXISTS hero_slides CASCADE;
DROP TABLE IF EXISTS users CASCADE;

-- Drop ALL ENUM types
DROP TYPE IF EXISTS notification_type;
DROP TYPE IF EXISTS payment_method;
DROP TYPE IF EXISTS payment_status;
DROP TYPE IF EXISTS booking_status;
DROP TYPE IF EXISTS auth_provider;
DROP TYPE IF EXISTS user_role;
DROP TYPE IF EXISTS user_role_v2;
DROP TYPE IF EXISTS vehicle_status;
DROP TYPE IF EXISTS membership_tier;

-- =====================================================
-- DONE! All tables & types dropped.
-- Now you can run schema.sql to recreate everything.
-- =====================================================
