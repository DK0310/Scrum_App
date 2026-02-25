-- =====================================================
-- DriveNow - Drop All Tables & Types
-- Run this BEFORE re-running schema.sql
-- Order: drop dependent tables first (FK constraints)
-- =====================================================

-- Drop triggers first
DROP TRIGGER IF EXISTS trg_users_updated_at ON users;
DROP TRIGGER IF EXISTS trg_vehicles_updated_at ON vehicles;
DROP TRIGGER IF EXISTS trg_bookings_updated_at ON bookings;
DROP TRIGGER IF EXISTS trg_reviews_updated_at ON reviews;
DROP TRIGGER IF EXISTS trg_posts_updated_at ON community_posts;

-- Drop trigger function
DROP FUNCTION IF EXISTS update_updated_at();

-- Drop views (none needed — n8n manages chat history via n8n_chat_histories)

-- Drop tables (order matters — drop child tables before parent)
-- NOTE: n8n_chat_histories is managed by n8n, do NOT drop it here
DROP TABLE IF EXISTS gps_tracking CASCADE;
DROP TABLE IF EXISTS trip_enquiries CASCADE;
DROP TABLE IF EXISTS memberships CASCADE;
DROP TABLE IF EXISTS notifications CASCADE;
DROP TABLE IF EXISTS favorites CASCADE;
DROP TABLE IF EXISTS community_likes CASCADE;
DROP TABLE IF EXISTS community_comments CASCADE;
DROP TABLE IF EXISTS community_posts CASCADE;
DROP TABLE IF EXISTS reviews CASCADE;
DROP TABLE IF EXISTS payments CASCADE;
DROP TABLE IF EXISTS bookings CASCADE;
DROP TABLE IF EXISTS vehicle_images CASCADE;
DROP TABLE IF EXISTS vehicles CASCADE;
DROP TABLE IF EXISTS hero_slides CASCADE;
DROP TABLE IF EXISTS auth_sessions CASCADE;
DROP TABLE IF EXISTS promotions CASCADE;
DROP TABLE IF EXISTS users CASCADE;

-- Drop ENUM types
DROP TYPE IF EXISTS notification_type;
DROP TYPE IF EXISTS membership_tier;
DROP TYPE IF EXISTS vehicle_status;
DROP TYPE IF EXISTS payment_method;
DROP TYPE IF EXISTS payment_status;
DROP TYPE IF EXISTS booking_status;
DROP TYPE IF EXISTS auth_provider;
DROP TYPE IF EXISTS user_role;

-- =====================================================
-- DONE! All tables & types dropped.
-- Now you can run schema.sql to recreate everything.
-- =====================================================
