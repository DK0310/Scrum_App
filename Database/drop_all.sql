-- =====================================================
-- DriveNow - Drop All Tables & Types
-- Run this BEFORE re-running schema.sql
-- Order: drop dependent tables first (FK constraints)
-- =====================================================

-- Drop triggers (only for tables being dropped; keep users & vehicles triggers)
-- KEEP: trg_users_updated_at (users table kept)
-- KEEP: trg_vehicles_updated_at (vehicles table kept)
DROP TRIGGER IF EXISTS trg_bookings_updated_at ON bookings;
DROP TRIGGER IF EXISTS trg_reviews_updated_at ON reviews;
DROP TRIGGER IF EXISTS trg_posts_updated_at ON community_posts;

-- Keep trigger function (still needed by users & vehicles)
-- DROP FUNCTION IF EXISTS update_updated_at();

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
-- KEEP: vehicle_images, vehicles, users, hero_slides (do NOT drop)
DROP TABLE IF EXISTS auth_sessions CASCADE;
DROP TABLE IF EXISTS promotions CASCADE;

-- Drop ENUM types (only those NOT used by kept tables: users, vehicles, vehicle_images)
DROP TYPE IF EXISTS notification_type;
-- KEEP: membership_tier  (used by users.membership)
-- KEEP: vehicle_status   (used by vehicles.status)
DROP TYPE IF EXISTS payment_method;
DROP TYPE IF EXISTS payment_status;
DROP TYPE IF EXISTS booking_status;
-- KEEP: auth_provider    (used by users.auth_provider)
-- KEEP: user_role        (used by users.role)

-- =====================================================
-- DONE! All tables & types dropped.
-- Now you can run schema.sql to recreate everything.
-- =====================================================
