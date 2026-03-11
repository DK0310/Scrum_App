-- ================================================================
-- DriveNow: Migration from BYTEA to Supabase Storage
-- Run this in Supabase SQL Editor AFTER creating the "DriveNow" bucket
-- ================================================================

-- 1. Add storage_path column to vehicle_images
ALTER TABLE vehicle_images ADD COLUMN IF NOT EXISTS storage_path TEXT;

-- 2. Add storage_path column to hero_slides  
ALTER TABLE hero_slides ADD COLUMN IF NOT EXISTS storage_path TEXT;

-- 3. Add avatar_storage_path to users
ALTER TABLE users ADD COLUMN IF NOT EXISTS avatar_storage_path TEXT;

-- 4. Add image_storage_path to community_posts
ALTER TABLE community_posts ADD COLUMN IF NOT EXISTS image_storage_path TEXT;

-- ================================================================
-- IMPORTANT: After running this migration:
--
-- 1. All NEW uploads will go to Supabase Storage (bucket: DriveNow)
-- 2. OLD BYTEA data in existing rows is preserved but will NOT be used
--    for new uploads. The API code has fallback logic:
--    - If storage_path exists → use Supabase Storage public URL
--    - If only image_data exists → serve from BYTEA (legacy fallback)
--
-- 3. To fully remove BYTEA columns (OPTIONAL, after verifying all works):
--    ALTER TABLE vehicle_images DROP COLUMN IF EXISTS image_data;
--    ALTER TABLE hero_slides DROP COLUMN IF EXISTS image_data;
--    ALTER TABLE users DROP COLUMN IF EXISTS avatar_data;
--    ALTER TABLE users DROP COLUMN IF EXISTS avatar_mime;
--    ALTER TABLE community_posts DROP COLUMN IF EXISTS image_data;
--    ALTER TABLE community_posts DROP COLUMN IF EXISTS image_mime;
-- ================================================================
