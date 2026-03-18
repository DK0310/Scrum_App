-- =====================================================
-- Migration: Update Roles to 5-Role System + Add Trip Features
-- Date: March 14, 2026
-- Purpose: Convert from 3-role (renter/owner/admin) to 5-role (user/driver/callcenterstaff/controlstaff/admin) system
--          and add ride-hailing features (active trips, passenger count, etc.)
-- =====================================================

-- =====================================================
-- Step 1: Create NEW enum type for 5 roles
-- =====================================================
DO $$ BEGIN
    CREATE TYPE user_role_v2 AS ENUM ('user', 'driver', 'callcenterstaff', 'controlstaff', 'admin');
EXCEPTION WHEN duplicate_object THEN NULL;
END $$;

-- =====================================================
-- Step 2: Add new columns to users table
-- =====================================================
ALTER TABLE users ADD COLUMN IF NOT EXISTS assigned_vehicle_id UUID REFERENCES vehicles(id) ON DELETE SET NULL;
ALTER TABLE users ADD COLUMN IF NOT EXISTS assigned_vehicle_assigned_at TIMESTAMPTZ;

-- =====================================================
-- Step 3: Add columns to bookings table for ride-hailing
-- =====================================================
ALTER TABLE bookings ADD COLUMN IF NOT EXISTS number_of_passengers INT DEFAULT 1 CHECK (number_of_passengers >= 1);
ALTER TABLE bookings ADD COLUMN IF NOT EXISTS recommended_tier VARCHAR(50); -- 'eco', 'standard', 'premium'
ALTER TABLE bookings ADD COLUMN IF NOT EXISTS ride_tier VARCHAR(50); -- selected tier by user
ALTER TABLE bookings ADD COLUMN IF NOT EXISTS driver_id UUID REFERENCES users(id) ON DELETE SET NULL;
ALTER TABLE bookings ADD COLUMN IF NOT EXISTS accepted_by_driver_at TIMESTAMPTZ;
ALTER TABLE bookings ADD COLUMN IF NOT EXISTS ride_started_at TIMESTAMPTZ;
ALTER TABLE bookings ADD COLUMN IF NOT EXISTS driver_arrived_at TIMESTAMPTZ;
ALTER TABLE bookings ADD COLUMN IF NOT EXISTS ride_completed_at TIMESTAMPTZ;

-- =====================================================
-- Step 4: Create ACTIVE_TRIPS table (real-time trip tracking)
-- =====================================================
CREATE TABLE IF NOT EXISTS active_trips (
    id                  UUID PRIMARY KEY DEFAULT uuid_generate_v4(),
    booking_id          UUID NOT NULL UNIQUE REFERENCES bookings(id) ON DELETE CASCADE,
    
    -- Relations
    user_id             UUID NOT NULL REFERENCES users(id) ON DELETE CASCADE,
    driver_id           UUID NOT NULL REFERENCES users(id) ON DELETE CASCADE,
    vehicle_id          UUID NOT NULL REFERENCES vehicles(id) ON DELETE CASCADE,
    
    -- Trip status (minicab flow)
    status              VARCHAR(50) NOT NULL DEFAULT 'searching_driver',  -- 'searching_driver', 'driver_accepted', 'driver_arriving', 'driver_arrived', 'journey_started', 'completed'
    
    -- Locations
    pickup_lat          DECIMAL(10, 7),
    pickup_lng          DECIMAL(10, 7),
    destination_lat     DECIMAL(10, 7),
    destination_lng     DECIMAL(10, 7),
    
    -- Driver location (simulated GPS)
    driver_lat          DECIMAL(10, 7),
    driver_lng          DECIMAL(10, 7),
    driver_heading      DECIMAL(5, 2),  -- direction in degrees
    
    -- Timestamps
    created_at          TIMESTAMPTZ NOT NULL DEFAULT NOW(),
    driver_accepted_at  TIMESTAMPTZ,
    journey_started_at  TIMESTAMPTZ,
    completed_at        TIMESTAMPTZ,
    updated_at          TIMESTAMPTZ NOT NULL DEFAULT NOW()
);

CREATE INDEX IF NOT EXISTS idx_active_trips_booking ON active_trips(booking_id);
CREATE INDEX IF NOT EXISTS idx_active_trips_user ON active_trips(user_id);
CREATE INDEX IF NOT EXISTS idx_active_trips_driver ON active_trips(driver_id);
CREATE INDEX IF NOT EXISTS idx_active_trips_status ON active_trips(status);

-- =====================================================
-- Step 5: Create DRIVER_NOTIFICATIONS table
-- =====================================================
CREATE TABLE IF NOT EXISTS driver_notifications (
    id                  UUID PRIMARY KEY DEFAULT uuid_generate_v4(),
    driver_id           UUID NOT NULL REFERENCES users(id) ON DELETE CASCADE,
    booking_id          UUID NOT NULL REFERENCES bookings(id) ON DELETE CASCADE,
    active_trip_id      UUID REFERENCES active_trips(id) ON DELETE CASCADE,
    
    -- Notification info
    title               VARCHAR(255) NOT NULL,
    message             TEXT NOT NULL,
    notification_type   VARCHAR(50) NOT NULL, -- 'trip_offer', 'trip_accepted_by_user', 'user_cancelled', etc.
    
    -- Status
    is_read             BOOLEAN NOT NULL DEFAULT FALSE,
    dismissed_at        TIMESTAMPTZ,
    
    -- Timestamps
    created_at          TIMESTAMPTZ NOT NULL DEFAULT NOW()
);

CREATE INDEX IF NOT EXISTS idx_driver_notif_driver ON driver_notifications(driver_id, is_read);
CREATE INDEX IF NOT EXISTS idx_driver_notif_booking ON driver_notifications(booking_id);

-- =====================================================
-- Step 6: Create VEHICLE_ASSIGNMENTS table (Staff assigning vehicles to drivers)
-- =====================================================
CREATE TABLE IF NOT EXISTS vehicle_assignments (
    id                  UUID PRIMARY KEY DEFAULT uuid_generate_v4(),
    staff_id            UUID NOT NULL REFERENCES users(id) ON DELETE CASCADE,
    driver_id           UUID NOT NULL REFERENCES users(id) ON DELETE CASCADE,
    vehicle_id          UUID NOT NULL REFERENCES vehicles(id) ON DELETE CASCADE,
    
    -- Assignment details
    assigned_at         TIMESTAMPTZ NOT NULL DEFAULT NOW(),
    assigned_date       DATE NOT NULL, -- which date the vehicle is assigned for
    unassigned_at       TIMESTAMPTZ,
    
    -- Notification sent to driver
    notification_sent_at TIMESTAMPTZ,
    
    created_at          TIMESTAMPTZ NOT NULL DEFAULT NOW()
);

CREATE INDEX IF NOT EXISTS idx_assignments_driver ON vehicle_assignments(driver_id, assigned_date);
CREATE INDEX IF NOT EXISTS idx_assignments_vehicle ON vehicle_assignments(vehicle_id, assigned_date);
CREATE INDEX IF NOT EXISTS idx_assignments_staff ON vehicle_assignments(staff_id);

-- =====================================================
-- Step 7: Update trigger for active_trips updated_at
-- =====================================================
DROP TRIGGER IF EXISTS trg_active_trips_updated_at ON active_trips;
CREATE TRIGGER trg_active_trips_updated_at
    BEFORE UPDATE ON active_trips FOR EACH ROW EXECUTE FUNCTION update_updated_at();

-- =====================================================
-- Step 8: DATA MIGRATION (convert existing users)
-- =====================================================
-- NOTE: This is a placeholder. You should manually review and update these mappings:
-- - Current 'renter' users → 'user'
-- - Current 'owner' users → 'controlstaff' (or split manually to callcenterstaff/controlstaff)
-- - Current 'admin' users → keep as 'admin'
--
-- DO THIS MANUALLY via SQL UPDATE after reviewing user roles!
-- Example (DO NOT RUN AUTOMATICALLY):
-- UPDATE users SET role = 'user'::user_role_v2 WHERE role = 'renter'::user_role;
-- UPDATE users SET role = 'controlstaff'::user_role_v2 WHERE role = 'owner'::user_role;
-- UPDATE users SET role = 'admin'::user_role_v2 WHERE role = 'admin'::user_role;

-- =====================================================
-- Step 9: Comments for reference
-- =====================================================
COMMENT ON TABLE active_trips IS 'Real-time tracking of active minicab rides with GPS simulation';
COMMENT ON TABLE driver_notifications IS 'Notifications sent to drivers about new trip offers and updates';
COMMENT ON TABLE vehicle_assignments IS 'Staff assignments of vehicles to drivers for a specific date';

-- =====================================================
-- DONE! 🚗
-- =====================================================
