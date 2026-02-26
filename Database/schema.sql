-- =====================================================
-- DriveNow - Supabase PostgreSQL Database Schema
-- Created: Feb 25, 2026
-- =====================================================

-- Enable required extensions
CREATE EXTENSION IF NOT EXISTS "uuid-ossp";
CREATE EXTENSION IF NOT EXISTS "pgcrypto";

-- =====================================================
-- 1. ENUM TYPES
-- =====================================================

-- User roles: renter (thuê xe) vs owner (cho thuê xe) vs admin
DO $$ BEGIN CREATE TYPE user_role AS ENUM ('renter', 'owner', 'admin'); EXCEPTION WHEN duplicate_object THEN NULL; END $$;

-- Auth providers
DO $$ BEGIN CREATE TYPE auth_provider AS ENUM ('google', 'phone', 'faceid', 'email'); EXCEPTION WHEN duplicate_object THEN NULL; END $$;

-- Booking status
CREATE TYPE booking_status AS ENUM ('pending', 'confirmed', 'in_progress', 'completed', 'cancelled');

-- Payment status
CREATE TYPE payment_status AS ENUM ('pending', 'paid', 'refunded', 'failed');

-- Payment method
CREATE TYPE payment_method AS ENUM ('cash', 'bank_transfer', 'credit_card', 'paypal');

-- Vehicle status
DO $$ BEGIN CREATE TYPE vehicle_status AS ENUM ('available', 'rented', 'maintenance', 'inactive'); EXCEPTION WHEN duplicate_object THEN NULL; END $$;

-- Membership tier
DO $$ BEGIN CREATE TYPE membership_tier AS ENUM ('free', 'basic', 'premium', 'corporate'); EXCEPTION WHEN duplicate_object THEN NULL; END $$;

-- Notification type
CREATE TYPE notification_type AS ENUM ('booking', 'payment', 'promo', 'system', 'alert');

-- =====================================================
-- 2. USERS TABLE
-- =====================================================
CREATE TABLE IF NOT EXISTS users (
    id              UUID PRIMARY KEY DEFAULT uuid_generate_v4(),
    
    -- Auth info
    email           VARCHAR(255) UNIQUE,
    phone           VARCHAR(20) UNIQUE,
    auth_provider   auth_provider NOT NULL DEFAULT 'google',
    google_id       VARCHAR(255) UNIQUE,
    password_hash   VARCHAR(255),              -- optional, for future email/password
    
    -- Role
    role            user_role NOT NULL DEFAULT 'renter',
    
    -- Profile (required fields filled after first login)
    full_name       VARCHAR(255),
    date_of_birth   DATE,
    avatar_url      TEXT,
    
    -- Profile (optional fields)
    address         TEXT,
    city            VARCHAR(100),
    country         VARCHAR(100),
    driving_license VARCHAR(50),
    license_expiry  DATE,
    id_card_number  VARCHAR(50),
    bio             TEXT,
    
    -- Face ID (optional, user enables later)
    face_descriptor JSONB,
    faceid_enabled  BOOLEAN NOT NULL DEFAULT FALSE,
    
    -- Profile completion
    profile_completed BOOLEAN NOT NULL DEFAULT FALSE,
    
    -- Membership
    membership      membership_tier NOT NULL DEFAULT 'free',
    
    -- Status
    is_active       BOOLEAN NOT NULL DEFAULT TRUE,
    email_verified  BOOLEAN NOT NULL DEFAULT FALSE,
    phone_verified  BOOLEAN NOT NULL DEFAULT FALSE,
    
    -- Timestamps
    created_at      TIMESTAMPTZ NOT NULL DEFAULT NOW(),
    updated_at      TIMESTAMPTZ NOT NULL DEFAULT NOW(),
    last_login_at   TIMESTAMPTZ
);

-- Index for fast lookups
CREATE INDEX IF NOT EXISTS idx_users_email ON users(email);
CREATE INDEX IF NOT EXISTS idx_users_phone ON users(phone);
CREATE INDEX IF NOT EXISTS idx_users_google_id ON users(google_id);
CREATE INDEX IF NOT EXISTS idx_users_role ON users(role);

-- =====================================================
-- 3. AUTH SESSIONS TABLE
-- =====================================================
CREATE TABLE auth_sessions (
    id              UUID PRIMARY KEY DEFAULT uuid_generate_v4(),
    user_id         UUID NOT NULL REFERENCES users(id) ON DELETE CASCADE,
    session_token   VARCHAR(255) UNIQUE NOT NULL,
    device_info     TEXT,
    ip_address      VARCHAR(45),
    expires_at      TIMESTAMPTZ NOT NULL,
    created_at      TIMESTAMPTZ NOT NULL DEFAULT NOW()
);

CREATE INDEX idx_sessions_user ON auth_sessions(user_id);
CREATE INDEX idx_sessions_token ON auth_sessions(session_token);

-- =====================================================
-- 4. VEHICLES TABLE (for owners)
-- =====================================================
CREATE TABLE IF NOT EXISTS vehicles (
    id              UUID PRIMARY KEY DEFAULT uuid_generate_v4(),
    owner_id        UUID NOT NULL REFERENCES users(id) ON DELETE CASCADE,
    
    -- Basic info
    brand           VARCHAR(100) NOT NULL,
    model           VARCHAR(100) NOT NULL,
    year            INT NOT NULL,
    license_plate   VARCHAR(20) UNIQUE NOT NULL,
    
    -- Details
    category        VARCHAR(50) NOT NULL,       -- sedan, suv, luxury, electric, van, sports
    transmission    VARCHAR(20) NOT NULL DEFAULT 'automatic', -- automatic, manual
    fuel_type       VARCHAR(20) NOT NULL DEFAULT 'petrol',    -- petrol, diesel, electric, hybrid
    seats           INT NOT NULL DEFAULT 5,
    color           VARCHAR(30),
    
    -- Specs
    engine_size     VARCHAR(20),
    consumption     VARCHAR(30),                -- e.g., "7L/100km" or "358mi"
    features        TEXT[],                     -- GPS, A/C, Premium audio, etc.
    
    -- Images (stored in vehicle_images table as BYTEA)
    thumbnail_id    UUID,                       -- FK to vehicle_images.id (set after insert)
    
    -- Pricing
    price_per_day   DECIMAL(10, 2) NOT NULL,
    price_per_week  DECIMAL(10, 2),
    price_per_month DECIMAL(10, 2),
    
    -- Location
    location_city   VARCHAR(100),
    location_address TEXT,
    latitude        DECIMAL(10, 7),
    longitude       DECIMAL(10, 7),
    
    -- Status & GPS
    status          vehicle_status NOT NULL DEFAULT 'available',
    gps_enabled     BOOLEAN NOT NULL DEFAULT FALSE,
    
    -- Ratings
    avg_rating      DECIMAL(2, 1) DEFAULT 0,
    total_reviews   INT DEFAULT 0,
    total_bookings  INT DEFAULT 0,
    
    -- Timestamps
    created_at      TIMESTAMPTZ NOT NULL DEFAULT NOW(),
    updated_at      TIMESTAMPTZ NOT NULL DEFAULT NOW()
);

CREATE INDEX IF NOT EXISTS idx_vehicles_owner ON vehicles(owner_id);
CREATE INDEX IF NOT EXISTS idx_vehicles_category ON vehicles(category);
CREATE INDEX IF NOT EXISTS idx_vehicles_status ON vehicles(status);
CREATE INDEX IF NOT EXISTS idx_vehicles_price ON vehicles(price_per_day);
CREATE INDEX IF NOT EXISTS idx_vehicles_location ON vehicles(location_city);

-- =====================================================
-- 4b. VEHICLE IMAGES TABLE (BLOB storage)
-- =====================================================
CREATE TABLE IF NOT EXISTS vehicle_images (
    id              UUID PRIMARY KEY DEFAULT uuid_generate_v4(),
    vehicle_id      UUID REFERENCES vehicles(id) ON DELETE CASCADE,  -- nullable: images uploaded before vehicle is created
    image_data      BYTEA NOT NULL,             -- actual image binary data
    mime_type       VARCHAR(50) NOT NULL,        -- image/jpeg, image/png, image/webp, image/gif
    file_name       VARCHAR(255),               -- original file name
    file_size       INT NOT NULL,               -- size in bytes
    is_thumbnail    BOOLEAN NOT NULL DEFAULT FALSE,
    sort_order      INT NOT NULL DEFAULT 0,
    created_at      TIMESTAMPTZ NOT NULL DEFAULT NOW()
);

CREATE INDEX IF NOT EXISTS idx_vehicle_images_vehicle ON vehicle_images(vehicle_id);
CREATE INDEX IF NOT EXISTS idx_vehicle_images_thumbnail ON vehicle_images(vehicle_id, is_thumbnail);

-- Add FK for thumbnail_id after vehicle_images table exists
DO $$ BEGIN
    ALTER TABLE vehicles ADD CONSTRAINT fk_vehicles_thumbnail
        FOREIGN KEY (thumbnail_id) REFERENCES vehicle_images(id) ON DELETE SET NULL;
EXCEPTION WHEN duplicate_object THEN NULL;
END $$;

-- =====================================================
-- 5. BOOKINGS TABLE
-- =====================================================
CREATE TABLE bookings (
    id              UUID PRIMARY KEY DEFAULT uuid_generate_v4(),
    
    -- Relations
    renter_id       UUID NOT NULL REFERENCES users(id) ON DELETE CASCADE,
    vehicle_id      UUID NOT NULL REFERENCES vehicles(id) ON DELETE CASCADE,
    owner_id        UUID NOT NULL REFERENCES users(id) ON DELETE CASCADE,
    
    -- Booking details
    booking_type    VARCHAR(30) NOT NULL DEFAULT 'self-drive', -- self-drive, with-driver, airport
    pickup_date     DATE NOT NULL,
    return_date     DATE,                                      -- NULL for airport transfer
    pickup_location TEXT,
    return_location TEXT,
    airport_name    VARCHAR(255),                               -- for airport transfer type
    
    -- Pricing
    total_days      INT NOT NULL,
    price_per_day   DECIMAL(10, 2) NOT NULL,
    subtotal        DECIMAL(10, 2) NOT NULL,
    discount_amount DECIMAL(10, 2) DEFAULT 0,
    total_amount    DECIMAL(10, 2) NOT NULL,
    promo_code      VARCHAR(50),
    
    -- Status
    status          booking_status NOT NULL DEFAULT 'pending',
    
    -- Extra
    special_requests TEXT,
    driver_requested BOOLEAN DEFAULT FALSE,
    
    -- Timestamps
    created_at      TIMESTAMPTZ NOT NULL DEFAULT NOW(),
    updated_at      TIMESTAMPTZ NOT NULL DEFAULT NOW(),
    confirmed_at    TIMESTAMPTZ,
    completed_at    TIMESTAMPTZ,
    cancelled_at    TIMESTAMPTZ
);

CREATE INDEX idx_bookings_renter ON bookings(renter_id);
CREATE INDEX idx_bookings_vehicle ON bookings(vehicle_id);
CREATE INDEX idx_bookings_owner ON bookings(owner_id);
CREATE INDEX idx_bookings_status ON bookings(status);
CREATE INDEX idx_bookings_dates ON bookings(pickup_date, return_date);

-- =====================================================
-- 6. PAYMENTS TABLE
-- =====================================================
CREATE TABLE payments (
    id              UUID PRIMARY KEY DEFAULT uuid_generate_v4(),
    booking_id      UUID NOT NULL REFERENCES bookings(id) ON DELETE CASCADE,
    user_id         UUID NOT NULL REFERENCES users(id) ON DELETE CASCADE,
    
    amount          DECIMAL(10, 2) NOT NULL,
    method          payment_method NOT NULL,
    status          payment_status NOT NULL DEFAULT 'pending',
    
    transaction_id  VARCHAR(255),
    payment_details JSONB,                     -- store provider-specific data
    
    paid_at         TIMESTAMPTZ,
    created_at      TIMESTAMPTZ NOT NULL DEFAULT NOW()
);

CREATE INDEX idx_payments_booking ON payments(booking_id);
CREATE INDEX idx_payments_user ON payments(user_id);
CREATE INDEX idx_payments_status ON payments(status);

-- =====================================================
-- 7. PROMOTIONS TABLE
-- =====================================================
CREATE TABLE promotions (
    id              UUID PRIMARY KEY DEFAULT uuid_generate_v4(),
    code            VARCHAR(50) UNIQUE NOT NULL,
    title           VARCHAR(255) NOT NULL,
    description     TEXT,
    
    -- Discount
    discount_type   VARCHAR(20) NOT NULL,       -- 'percentage' or 'fixed'
    discount_value  DECIMAL(10, 2) NOT NULL,    -- 20 (for 20%) or 50 (for $50)
    
    -- Validity
    min_booking_days INT DEFAULT 1,
    max_uses        INT,
    total_used      INT DEFAULT 0,
    
    -- Active period
    starts_at       TIMESTAMPTZ NOT NULL DEFAULT NOW(),
    expires_at      TIMESTAMPTZ,
    
    is_active       BOOLEAN NOT NULL DEFAULT TRUE,
    created_at      TIMESTAMPTZ NOT NULL DEFAULT NOW()
);

CREATE INDEX idx_promos_code ON promotions(code);
CREATE INDEX idx_promos_active ON promotions(is_active, starts_at, expires_at);

-- =====================================================
-- 7b. HERO SLIDES TABLE (admin-managed hero images)
-- =====================================================
CREATE TABLE IF NOT EXISTS hero_slides (
    id              UUID PRIMARY KEY DEFAULT uuid_generate_v4(),
    image_data      BYTEA NOT NULL,             -- image binary (BYTEA like vehicle_images)
    mime_type       VARCHAR(50) NOT NULL,        -- image/jpeg, image/png, image/webp
    file_name       VARCHAR(255),
    file_size       INT NOT NULL,
    title           VARCHAR(255),               -- optional overlay title
    subtitle        TEXT,                        -- optional overlay subtitle
    link_url        TEXT,                        -- optional CTA link
    sort_order      INT NOT NULL DEFAULT 0,
    is_active       BOOLEAN NOT NULL DEFAULT TRUE,
    created_by      UUID REFERENCES users(id) ON DELETE SET NULL, -- admin who uploaded
    created_at      TIMESTAMPTZ NOT NULL DEFAULT NOW()
);

CREATE INDEX IF NOT EXISTS idx_hero_slides_active ON hero_slides(is_active, sort_order);

-- =====================================================
-- 8. REVIEWS TABLE
-- =====================================================
CREATE TABLE reviews (
    id              UUID PRIMARY KEY DEFAULT uuid_generate_v4(),
    
    -- Relations
    user_id         UUID NOT NULL REFERENCES users(id) ON DELETE CASCADE,
    vehicle_id      UUID NOT NULL REFERENCES vehicles(id) ON DELETE CASCADE,
    booking_id      UUID REFERENCES bookings(id) ON DELETE SET NULL,
    
    -- Review content
    rating          INT NOT NULL CHECK (rating >= 1 AND rating <= 5),
    title           VARCHAR(255),
    content         TEXT NOT NULL,
    
    -- Timestamps
    created_at      TIMESTAMPTZ NOT NULL DEFAULT NOW(),
    updated_at      TIMESTAMPTZ NOT NULL DEFAULT NOW()
);

CREATE INDEX idx_reviews_vehicle ON reviews(vehicle_id);
CREATE INDEX idx_reviews_user ON reviews(user_id);
CREATE INDEX idx_reviews_rating ON reviews(rating);

-- =====================================================
-- 9. COMMUNITY POSTS TABLE
-- =====================================================
CREATE TABLE community_posts (
    id              UUID PRIMARY KEY DEFAULT uuid_generate_v4(),
    user_id         UUID NOT NULL REFERENCES users(id) ON DELETE CASCADE,
    
    title           VARCHAR(255) NOT NULL,
    content         TEXT NOT NULL,
    category        VARCHAR(50),                -- 'road_trip', 'car_review', 'tips', 'question'
    image_url       TEXT,
    
    likes_count     INT DEFAULT 0,
    comments_count  INT DEFAULT 0,
    
    created_at      TIMESTAMPTZ NOT NULL DEFAULT NOW(),
    updated_at      TIMESTAMPTZ NOT NULL DEFAULT NOW()
);

CREATE INDEX idx_posts_user ON community_posts(user_id);
CREATE INDEX idx_posts_category ON community_posts(category);
CREATE INDEX idx_posts_created ON community_posts(created_at DESC);

-- =====================================================
-- 10. COMMUNITY COMMENTS TABLE
-- =====================================================
CREATE TABLE community_comments (
    id              UUID PRIMARY KEY DEFAULT uuid_generate_v4(),
    post_id         UUID NOT NULL REFERENCES community_posts(id) ON DELETE CASCADE,
    user_id         UUID NOT NULL REFERENCES users(id) ON DELETE CASCADE,
    
    content         TEXT NOT NULL,
    
    created_at      TIMESTAMPTZ NOT NULL DEFAULT NOW()
);

CREATE INDEX idx_comments_post ON community_comments(post_id);

-- =====================================================
-- 11. COMMUNITY LIKES TABLE
-- =====================================================
CREATE TABLE community_likes (
    id              UUID PRIMARY KEY DEFAULT uuid_generate_v4(),
    post_id         UUID NOT NULL REFERENCES community_posts(id) ON DELETE CASCADE,
    user_id         UUID NOT NULL REFERENCES users(id) ON DELETE CASCADE,
    created_at      TIMESTAMPTZ NOT NULL DEFAULT NOW(),
    
    UNIQUE(post_id, user_id)  -- prevent duplicate likes
);

CREATE INDEX idx_likes_post ON community_likes(post_id);

-- =====================================================
-- 12. FAVORITES TABLE
-- =====================================================
CREATE TABLE favorites (
    id              UUID PRIMARY KEY DEFAULT uuid_generate_v4(),
    user_id         UUID NOT NULL REFERENCES users(id) ON DELETE CASCADE,
    vehicle_id      UUID NOT NULL REFERENCES vehicles(id) ON DELETE CASCADE,
    created_at      TIMESTAMPTZ NOT NULL DEFAULT NOW(),
    
    UNIQUE(user_id, vehicle_id)
);

CREATE INDEX idx_favorites_user ON favorites(user_id);

-- =====================================================
-- 13. NOTIFICATIONS TABLE
-- =====================================================
CREATE TABLE notifications (
    id              UUID PRIMARY KEY DEFAULT uuid_generate_v4(),
    user_id         UUID NOT NULL REFERENCES users(id) ON DELETE CASCADE,
    
    type            notification_type NOT NULL DEFAULT 'system',
    title           VARCHAR(255) NOT NULL,
    message         TEXT NOT NULL,
    
    is_read         BOOLEAN NOT NULL DEFAULT FALSE,
    
    created_at      TIMESTAMPTZ NOT NULL DEFAULT NOW()
);

CREATE INDEX idx_notif_user ON notifications(user_id, is_read);
CREATE INDEX idx_notif_created ON notifications(created_at DESC);

-- =====================================================
-- 14. MEMBERSHIPS TABLE (subscription history)
-- =====================================================
CREATE TABLE memberships (
    id              UUID PRIMARY KEY DEFAULT uuid_generate_v4(),
    user_id         UUID NOT NULL REFERENCES users(id) ON DELETE CASCADE,
    
    tier            membership_tier NOT NULL,
    price           DECIMAL(10, 2) NOT NULL,
    
    starts_at       TIMESTAMPTZ NOT NULL DEFAULT NOW(),
    expires_at      TIMESTAMPTZ NOT NULL,
    is_active       BOOLEAN NOT NULL DEFAULT TRUE,
    auto_renew      BOOLEAN NOT NULL DEFAULT TRUE,
    
    created_at      TIMESTAMPTZ NOT NULL DEFAULT NOW()
);

CREATE INDEX idx_memberships_user ON memberships(user_id);

-- =====================================================
-- 15. GPS TRACKING TABLE
-- =====================================================
CREATE TABLE gps_tracking (
    id              UUID PRIMARY KEY DEFAULT uuid_generate_v4(),
    vehicle_id      UUID NOT NULL REFERENCES vehicles(id) ON DELETE CASCADE,
    booking_id      UUID REFERENCES bookings(id) ON DELETE SET NULL,
    
    latitude        DECIMAL(10, 7) NOT NULL,
    longitude       DECIMAL(10, 7) NOT NULL,
    speed           DECIMAL(6, 2),              -- km/h
    heading         DECIMAL(5, 2),              -- direction in degrees
    
    recorded_at     TIMESTAMPTZ NOT NULL DEFAULT NOW()
);

CREATE INDEX idx_gps_vehicle ON gps_tracking(vehicle_id, recorded_at DESC);
CREATE INDEX idx_gps_booking ON gps_tracking(booking_id);

-- =====================================================
-- 16. TRIP ENQUIRIES TABLE
-- =====================================================
CREATE TABLE trip_enquiries (
    id              UUID PRIMARY KEY DEFAULT uuid_generate_v4(),
    user_id         UUID REFERENCES users(id) ON DELETE SET NULL,
    
    name            VARCHAR(255) NOT NULL,
    email           VARCHAR(255) NOT NULL,
    phone           VARCHAR(20),
    
    trip_details    TEXT NOT NULL,
    preferred_contact VARCHAR(20) DEFAULT 'email', -- email, phone, whatsapp
    
    status          VARCHAR(20) DEFAULT 'new',     -- new, in_progress, resolved
    admin_notes     TEXT,
    
    created_at      TIMESTAMPTZ NOT NULL DEFAULT NOW(),
    resolved_at     TIMESTAMPTZ
);

CREATE INDEX idx_enquiries_status ON trip_enquiries(status);

-- =====================================================
-- 17. UPDATED_AT TRIGGER FUNCTION
-- =====================================================
CREATE OR REPLACE FUNCTION update_updated_at()
RETURNS TRIGGER AS $$
BEGIN
    NEW.updated_at = NOW();
    RETURN NEW;
END;
$$ LANGUAGE plpgsql;

-- Apply updated_at trigger to all relevant tables
DROP TRIGGER IF EXISTS trg_users_updated_at ON users;
CREATE TRIGGER trg_users_updated_at
    BEFORE UPDATE ON users FOR EACH ROW EXECUTE FUNCTION update_updated_at();

DROP TRIGGER IF EXISTS trg_vehicles_updated_at ON vehicles;
CREATE TRIGGER trg_vehicles_updated_at
    BEFORE UPDATE ON vehicles FOR EACH ROW EXECUTE FUNCTION update_updated_at();

CREATE TRIGGER trg_bookings_updated_at
    BEFORE UPDATE ON bookings FOR EACH ROW EXECUTE FUNCTION update_updated_at();

CREATE TRIGGER trg_reviews_updated_at
    BEFORE UPDATE ON reviews FOR EACH ROW EXECUTE FUNCTION update_updated_at();

CREATE TRIGGER trg_posts_updated_at
    BEFORE UPDATE ON community_posts FOR EACH ROW EXECUTE FUNCTION update_updated_at();

-- =====================================================
-- 18. SEED DATA - DEFAULT PROMOTIONS
-- =====================================================
INSERT INTO promotions (code, title, description, discount_type, discount_value, min_booking_days, expires_at) VALUES
('WEEKEND20', 'Weekend Special', 'Book any car for the weekend and save 20%. Valid until March 31.', 'percentage', 20, 2, '2026-03-31 23:59:59+00'),
('FIRST50', 'First Ride Bonus', 'New users get $50 off their first booking. Sign up today!', 'fixed', 50, 1, '2026-12-31 23:59:59+00'),
('LONGTERM30', 'Long-term Rental', 'Book for 30+ days and get 30% discount. Perfect for corporate use.', 'percentage', 30, 30, '2026-12-31 23:59:59+00'),
('SUMMER25', 'Summer Road Trip', 'Hit the road this summer with 25% off any SUV rental. Limited time.', 'percentage', 25, 3, '2026-08-31 23:59:59+00'),
('EV15', 'Go Electric', 'Get 15% off all electric vehicle rentals. Save the planet, save money.', 'percentage', 15, 1, '2026-12-31 23:59:59+00'),
('REFER20', 'Refer a Friend', 'Refer a friend and both get $20 off your next booking.', 'fixed', 20, 1, NULL);

-- =====================================================
-- 19. ROW LEVEL SECURITY (Supabase)
-- =====================================================

-- Enable RLS on all tables
ALTER TABLE users ENABLE ROW LEVEL SECURITY;
ALTER TABLE auth_sessions ENABLE ROW LEVEL SECURITY;
ALTER TABLE vehicles ENABLE ROW LEVEL SECURITY;
ALTER TABLE bookings ENABLE ROW LEVEL SECURITY;
ALTER TABLE payments ENABLE ROW LEVEL SECURITY;
ALTER TABLE reviews ENABLE ROW LEVEL SECURITY;
ALTER TABLE community_posts ENABLE ROW LEVEL SECURITY;
ALTER TABLE community_comments ENABLE ROW LEVEL SECURITY;
ALTER TABLE community_likes ENABLE ROW LEVEL SECURITY;
ALTER TABLE favorites ENABLE ROW LEVEL SECURITY;
ALTER TABLE notifications ENABLE ROW LEVEL SECURITY;
ALTER TABLE memberships ENABLE ROW LEVEL SECURITY;
ALTER TABLE gps_tracking ENABLE ROW LEVEL SECURITY;
ALTER TABLE trip_enquiries ENABLE ROW LEVEL SECURITY;
ALTER TABLE promotions ENABLE ROW LEVEL SECURITY;

-- Public read for vehicles & promotions
DROP POLICY IF EXISTS "Vehicles are viewable by everyone" ON vehicles;
CREATE POLICY "Vehicles are viewable by everyone"
    ON vehicles FOR SELECT USING (status = 'available');

DROP POLICY IF EXISTS "Promotions are viewable by everyone" ON promotions;
CREATE POLICY "Promotions are viewable by everyone"
    ON promotions FOR SELECT USING (is_active = TRUE);

-- Community posts are public
DROP POLICY IF EXISTS "Community posts are viewable by everyone" ON community_posts;
CREATE POLICY "Community posts are viewable by everyone"
    ON community_posts FOR SELECT USING (TRUE);

-- Reviews are public
DROP POLICY IF EXISTS "Reviews are viewable by everyone" ON reviews;
CREATE POLICY "Reviews are viewable by everyone"
    ON reviews FOR SELECT USING (TRUE);

-- =====================================================
-- DONE! 🚗 DriveNow Database Ready
-- =====================================================
