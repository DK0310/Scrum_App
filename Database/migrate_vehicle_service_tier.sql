-- Add stable vehicle service tier support (eco/standard/luxury)
-- Safe to run multiple times.

ALTER TABLE vehicles
    ADD COLUMN IF NOT EXISTS service_tier VARCHAR(20) DEFAULT 'standard';

-- Backfill existing rows based on known category labels first, then pricing heuristic.
UPDATE vehicles
SET service_tier = CASE
    WHEN LOWER(COALESCE(category, '')) = 'eco' THEN 'eco'
    WHEN LOWER(COALESCE(category, '')) IN ('luxury', 'premium') THEN 'luxury'
    WHEN COALESCE(price_per_day, 0) <= 40 THEN 'eco'
    WHEN COALESCE(price_per_day, 0) > 100 THEN 'luxury'
    ELSE 'standard'
END
WHERE service_tier IS NULL
   OR TRIM(service_tier) = '';

DO $$
BEGIN
    IF NOT EXISTS (
        SELECT 1
        FROM pg_constraint
        WHERE conname = 'vehicles_service_tier_check'
    ) THEN
        ALTER TABLE vehicles
            ADD CONSTRAINT vehicles_service_tier_check
            CHECK (service_tier IN ('eco', 'standard', 'luxury'));
    END IF;
END $$;

ALTER TABLE vehicles
    ALTER COLUMN service_tier SET DEFAULT 'standard';

UPDATE vehicles
SET service_tier = 'standard'
WHERE service_tier IS NULL;

ALTER TABLE vehicles
    ALTER COLUMN service_tier SET NOT NULL;

CREATE INDEX IF NOT EXISTS idx_vehicles_service_tier ON vehicles(service_tier);
