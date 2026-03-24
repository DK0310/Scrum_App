<?php

declare(strict_types=1);

final class BookingRepository
{
    private PDO $pdo;

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    private function ensureVehicleServiceTierColumnExists(): void
    {
        try {
            $this->pdo->exec("ALTER TABLE vehicles ADD COLUMN IF NOT EXISTS service_tier VARCHAR(20) DEFAULT 'standard'");
        } catch (Throwable $e) {
            // Ignore in environments without ALTER permissions.
        }
    }

    /**
     * Ensure schema columns exist for minicab bookings
     */
    public function ensureBookingColumnsExist(): void
    {
        $columns = [
            'service_type VARCHAR(50) DEFAULT \'local\'',
            'distance_km DECIMAL(10,2) DEFAULT NULL',
            'transfer_cost DECIMAL(10,2) DEFAULT NULL',
            'number_of_passengers INT DEFAULT 1',
            'ride_tier VARCHAR(50) DEFAULT NULL',
        ];

        foreach ($columns as $col) {
            try {
                $this->pdo->exec("ALTER TABLE bookings ADD COLUMN IF NOT EXISTS " . $col);
            } catch (PDOException $e) {
                // Column may already exist
            }
        }
    }

    /**
     * Find a vehicle matching the specified tier
     * @return array<string,mixed>|null
     */
    public function findVehicleForTier(string $rideTier, string $excludeRenterId): ?array
    {
        $this->ensureVehicleServiceTierColumnExists();

        $tierConditions = '';
        match ($rideTier) {
            'eco' => $tierConditions = "LOWER(COALESCE(v.service_tier, '')) = 'eco'",
            'standard' => $tierConditions = "LOWER(COALESCE(v.service_tier, '')) = 'standard'",
            'luxury' => $tierConditions = "LOWER(COALESCE(v.service_tier, '')) IN ('luxury', 'premium')",
            default => throw new Exception('Invalid ride tier')
        };

        $stmt = $this->pdo->prepare("
            SELECT v.id, v.owner_id, v.service_tier, 
                   v.category, v.status, v.brand, v.model, v.seats, v.owner_id as owner_id
            FROM vehicles v
            WHERE v.status = 'available' AND {$tierConditions} AND v.owner_id != ?
            ORDER BY RANDOM()
            LIMIT 1
        ");
        $stmt->execute([$excludeRenterId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    /**
     * Get vehicle details for booking creation
     * @return array<string,mixed>|null
     */
    public function getVehicleForBooking(string $vehicleId): ?array
    {
        $stmt = $this->pdo->prepare("
            SELECT id, owner_id, service_tier, 
                   category, status, brand, model
            FROM vehicles
            WHERE id = ?
        ");
        $stmt->execute([$vehicleId]);
        return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    /**
     * Create a booking record
     * @param array<string,mixed> $data
     * @return array<string,mixed> Returns booking with id and created_at
     */
    public function createBooking(array $data): array
    {
        $stmt = $this->pdo->prepare("
            INSERT INTO bookings (
                renter_id, vehicle_id, owner_id, booking_type, pickup_date, pickup_time, return_date,
                pickup_location, return_location, total_days, subtotal,
                discount_amount, total_amount, promo_code, special_requests, driver_requested,
                distance_km, transfer_cost, service_type, number_of_passengers, ride_tier, status
            ) VALUES (
                ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'pending'
            )
            RETURNING id, created_at
        ");

        $stmt->execute([
            $data['renter_id'],
            $data['vehicle_id'],
            $data['owner_id'],
            $data['booking_type'],
            $data['pickup_date'],
            $data['pickup_time'] ?? null,
            $data['return_date'] ?? null,
            $data['pickup_location'],
            $data['return_location'] ?? null,
            $data['total_days'],
            $data['subtotal'],
            $data['discount_amount'],
            $data['total_amount'],
            $data['promo_code'] ?? null,
            $data['special_requests'] ?? '',
            $data['driver_requested'] ?? 't',
            $data['distance_km'] ?? null,
            $data['transfer_cost'] ?? null,
            $data['service_type'] ?? null,
            $data['number_of_passengers'] ?? 1,
            $data['ride_tier'] ?? null,
        ]);

        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    /**
     * Create active trip record for minicab bookings
     */
    public function createActiveTrip(
        string $bookingId,
        string $userId,
        string $vehicleId,
        ?float $pickupLat,
        ?float $pickupLng,
        ?float $destLat,
        ?float $destLng
    ): bool {
        try {
            $stmt = $this->pdo->prepare("
                INSERT INTO active_trips (booking_id, user_id, driver_id, vehicle_id, status, 
                                        pickup_lat, pickup_lng, destination_lat, destination_lng, 
                                        created_at, updated_at)
                VALUES (?, ?, NULL, ?, 'searching_driver', ?, ?, ?, ?, NOW(), NOW())
            ");
            return $stmt->execute([
                $bookingId,
                $userId,
                $vehicleId,
                $pickupLat,
                $pickupLng,
                $destLat,
                $destLng,
            ]);
        } catch (PDOException $e) {
            return false;
        }
    }

    /**
     * Get booking details by ID
     * @return array<string,mixed>|null
     */
    public function getById(string $bookingId): ?array
    {
        $stmt = $this->pdo->prepare("
            SELECT * FROM bookings WHERE id = ?
        ");
        $stmt->execute([$bookingId]);
        return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    /**
     * Get user's bookings (renter)
     * @return array<int,array<string,mixed>>
     */
    public function getUserBookings(string $userId, int $limit = 50, int $offset = 0): array
    {
        $stmt = $this->pdo->prepare("
            SELECT * FROM bookings
            WHERE renter_id = ?
            ORDER BY created_at DESC
            LIMIT ? OFFSET ?
        ");
        $stmt->execute([$userId, $limit, $offset]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Get owner's bookings (vehicle owner)
     * @return array<int,array<string,mixed>>
     */
    public function getOwnerBookings(string $ownerId, int $limit = 50, int $offset = 0): array
    {
        $stmt = $this->pdo->prepare("
            SELECT * FROM bookings
            WHERE owner_id = ?
            ORDER BY created_at DESC
            LIMIT ? OFFSET ?
        ");
        $stmt->execute([$ownerId, $limit, $offset]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Confirm booking (move from pending to confirmed)
     */
    public function confirmBooking(string $bookingId): bool
    {
        $stmt = $this->pdo->prepare("
            UPDATE bookings
            SET status = 'confirmed'
            WHERE id = ? AND status = 'pending'
        ");
        return $stmt->execute([$bookingId]) && $stmt->rowCount() > 0;
    }

    /**
     * Mark booking as completed
     */
    public function completeBooking(string $bookingId): bool
    {
        $stmt = $this->pdo->prepare("
            UPDATE bookings
            SET status = 'completed'
            WHERE id = ?
        ");
        return $stmt->execute([$bookingId]) && $stmt->rowCount() > 0;
    }

    /**
     * Cancel booking
     */
    public function cancelBooking(string $bookingId, ?string $reason = null): bool
    {
        $stmt = $this->pdo->prepare("
            UPDATE bookings
            SET status = 'cancelled', cancellation_reason = ?
            WHERE id = ?
        ");
        return $stmt->execute([$reason, $bookingId]) && $stmt->rowCount() > 0;
    }

    /**
     * Update booking status
     */
    public function updateStatus(string $bookingId, string $status): bool
    {
        $stmt = $this->pdo->prepare("
            UPDATE bookings
            SET status = ?
            WHERE id = ?
        ");
        return $stmt->execute([$status, $bookingId]) && $stmt->rowCount() > 0;
    }

    /**
     * Assign vehicle and owner for a booking.
     */
    public function assignVehicleToBooking(string $bookingId, string $vehicleId, string $ownerId, float $pricePerDay): bool
    {
        $stmt = $this->pdo->prepare("
            UPDATE bookings
            SET vehicle_id = ?, owner_id = ?, price_per_day = ?
            WHERE id = ?
        ");
        return $stmt->execute([$vehicleId, $ownerId, $pricePerDay, $bookingId]) && $stmt->rowCount() > 0;
    }

    /**
     * Get bookings by status
     * @return array<int,array<string,mixed>>
     */
    public function getByStatus(string $status, int $limit = 100): array
    {
        $stmt = $this->pdo->prepare("
            SELECT * FROM bookings
            WHERE status = ?
            ORDER BY created_at DESC
            LIMIT ?
        ");
        $stmt->execute([$status, $limit]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Check if user owns or rented the booking
     */
    public function userHasAccessToBooking(string $bookingId, string $userId): bool
    {
        $stmt = $this->pdo->prepare("
            SELECT 1 FROM bookings
            WHERE id = ? AND (renter_id = ? OR owner_id = ?)
        ");
        $stmt->execute([$bookingId, $userId, $userId]);
        return $stmt->fetch() !== false;
    }

    /**
     * Get booking invoice data
     * @return array<string,mixed>|null
     */
    public function getInvoiceData(string $bookingId): ?array
    {
        $stmt = $this->pdo->prepare("
            SELECT b.*, 
                   u.full_name as renter_name, u.email as renter_email,
                   v.brand, v.model, v.license_plate, v.year
            FROM bookings b
            JOIN users u ON b.renter_id = u.id
            LEFT JOIN vehicles v ON b.vehicle_id = v.id
            WHERE b.id = ?
        ");
        $stmt->execute([$bookingId]);
        return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    /**
     * Mark vehicle as rented when booking is confirmed/created
     */
    public function markVehicleRented(string $vehicleId): bool
    {
        $stmt = $this->pdo->prepare("
            UPDATE vehicles
            SET status = 'rented'
            WHERE id = ? AND status = 'available'
        ");
        return $stmt->execute([$vehicleId]) && $stmt->rowCount() > 0;
    }

    /**
     * Mark vehicle as available when booking is completed/cancelled
     */
    public function markVehicleAvailable(string $vehicleId): bool
    {
        $stmt = $this->pdo->prepare("
            UPDATE vehicles
            SET status = 'available'
            WHERE id = ?
        ");
        return $stmt->execute([$vehicleId]) && $stmt->rowCount() > 0;
    }

    /**
     * Get booking with related vehicle and user info
     * @return array<string,mixed>|null
     */
    public function getWithDetails(string $bookingId): ?array
    {
        $stmt = $this->pdo->prepare("
            SELECT b.*, 
                   u.full_name as renter_name, u.email as renter_email, u.phone as renter_phone,
                   v.brand, v.model, v.license_plate, v.year, v.thumbnail_url,
                   owner.full_name as owner_name, owner.email as owner_email
            FROM bookings b
            JOIN users u ON b.renter_id = u.id
            LEFT JOIN vehicles v ON b.vehicle_id = v.id
            LEFT JOIN users owner ON b.owner_id = owner.id
            WHERE b.id = ?
        ");
        $stmt->execute([$bookingId]);
        return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    /**
     * List all bookings with limited detail
     * @return array<int,array<string,mixed>>
     */
    public function listAll(int $limit = 50, int $offset = 0): array
    {
        $stmt = $this->pdo->prepare("
            SELECT 
                b.id, b.renter_id, b.driver_id, b.status, b.booking_type,
                b.pickup_location, b.pickup_date, b.service_type,
                b.total_amount, b.number_of_passengers, b.ride_tier,
                b.created_at, b.accepted_by_driver_at,
                u.full_name as user_name, u.phone as user_phone,
                d.full_name as driver_name, d.phone as driver_phone
            FROM bookings b
            JOIN users u ON b.renter_id = u.id
            LEFT JOIN users d ON b.driver_id = d.id
            ORDER BY b.created_at DESC
            LIMIT ? OFFSET ?
        ");
        $stmt->execute([$limit, $offset]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Get booking with user and driver details
     * @return array<string,mixed>|null
     */
    public function getBookingWithUserAndDriver(string $bookingId): ?array
    {
        $stmt = $this->pdo->prepare("
            SELECT 
                b.id, b.renter_id, b.owner_id, b.driver_id, b.vehicle_id, b.status, b.booking_type,
                b.pickup_location, b.return_location, b.pickup_date, b.return_date,
                b.service_type, b.total_amount, b.number_of_passengers,
                b.ride_tier, b.special_requests,
                b.created_at, b.accepted_by_driver_at, b.ride_completed_at,
                u.full_name as user_name, u.phone as user_phone, u.email,
                d.full_name as driver_name, d.phone as driver_phone,
                v.brand, v.model, v.license_plate, v.year
            FROM bookings b
            JOIN users u ON b.renter_id = u.id
            LEFT JOIN users d ON b.driver_id = d.id
            LEFT JOIN vehicles v ON b.vehicle_id = v.id
            WHERE b.id = ?
        ");
        $stmt->execute([$bookingId]);
        return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    /**
     * List bookings for a user (as renter or owner)
     * @return array<int,array<string,mixed>>
     */
    public function listUserBookings(string $userId): array
    {
        $stmt = $this->pdo->prepare("
            SELECT b.id, b.renter_id, b.owner_id, b.vehicle_id, b.booking_type,
                   b.pickup_date, b.pickup_time, b.return_date, b.pickup_location, b.return_location,
                   b.airport_name, b.total_days, b.price_per_day, b.subtotal,
                   b.discount_amount, b.total_amount, b.promo_code, b.status,
                   b.special_requests, b.driver_requested, b.created_at,
                   b.confirmed_at, b.completed_at, b.cancelled_at,
                   b.distance_km, b.transfer_cost, b.service_type,
                   b.number_of_passengers, b.ride_tier,
                   v.brand, v.model, v.year, v.category, v.license_plate,
                   u_renter.full_name AS renter_name,
                   u_renter.email AS renter_email,
                   p.method AS payment_method,
                   p.status AS payment_status,
                   rev.id AS review_id,
                   rev.rating AS review_rating
            FROM bookings b
            LEFT JOIN vehicles v ON b.vehicle_id = v.id
            LEFT JOIN users u_renter ON b.renter_id = u_renter.id
            LEFT JOIN payments p ON p.booking_id = b.id
            LEFT JOIN reviews rev ON rev.booking_id = b.id AND rev.user_id = ?
            WHERE b.renter_id = ? OR b.owner_id = ?
            ORDER BY b.created_at DESC
        ");
        $stmt->execute([$userId, $userId, $userId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Clear assigned vehicle from a booking (used when pending booking is cancelled/rejected)
     */
    public function unassignVehicleFromBooking(string $bookingId): bool
    {
        $stmt = $this->pdo->prepare(" 
            UPDATE bookings
            SET vehicle_id = NULL, owner_id = NULL, price_per_day = NULL
            WHERE id = ?
        ");
        return $stmt->execute([$bookingId]);
    }

    /**
     * Update booking status
     */
    public function updateBookingStatus(string $bookingId, string $newStatus): bool
    {
        $extraSql = '';
        if ($newStatus === 'confirmed') $extraSql = ', confirmed_at = NOW()';
        if ($newStatus === 'completed') $extraSql = ', completed_at = NOW()';
        if ($newStatus === 'cancelled') $extraSql = ', cancelled_at = NOW()';

        $stmt = $this->pdo->prepare("UPDATE bookings SET status = ?::booking_status" . $extraSql . " WHERE id = ?");
        return $stmt->execute([$newStatus, $bookingId]) && $stmt->rowCount() > 0;
    }

    /**
     * Mark payment as paid
     */
    public function markPaymentAsPaid(string $bookingId): bool
    {
        $stmt = $this->pdo->prepare("UPDATE payments SET status = 'paid'::payment_status, paid_at = NOW() WHERE booking_id = ?");
        return $stmt->execute([$bookingId]) && $stmt->rowCount() > 0;
    }

    /**
     * Update vehicle status
     */
    public function updateVehicleStatus(string $vehicleId, string $status): bool
    {
        $statusCast = match ($status) {
            'available', 'rented', 'assigned' => true,
            default => false
        };

        if (!$statusCast) {
            return false;
        }

        $stmt = $this->pdo->prepare("UPDATE vehicles SET status = ?::vehicle_status WHERE id = ?");
        return $stmt->execute([$status, $vehicleId]);
    }

    /**
     * Increment vehicle stats
     */
    public function incrementVehicleStats(string $vehicleId, string $stat): bool
    {
        if ($stat === 'bookings') {
            $stmt = $this->pdo->prepare("UPDATE vehicles SET total_bookings = total_bookings + 1 WHERE id = ?");
        } else {
            return false;
        }

        return $stmt->execute([$vehicleId]);
    }

    /**
     * Update vehicle rating stats
     */
    public function updateVehicleRating(string $vehicleId, float $avgRating, int $totalReviews): bool
    {
        $stmt = $this->pdo->prepare("UPDATE vehicles SET avg_rating = ?, total_reviews = ? WHERE id = ?");
        return $stmt->execute([$avgRating, $totalReviews, $vehicleId]);
    }

    /**
     * Delete a booking (admin only)
     * @return bool True if deleted, false if not found
     */
    public function deleteBooking(string $bookingId): bool
    {
        $stmt = $this->pdo->prepare("DELETE FROM bookings WHERE id = ?");
        $stmt->execute([$bookingId]);
        return $stmt->rowCount() > 0;
    }

    /**
     * List all bookings with user and vehicle details (admin only)
     * @return array<int,array<string,mixed>>
     */
    public function listAllBookingsForAdmin(): array
    {
        $stmt = $this->pdo->prepare("
            SELECT b.id, b.renter_id, b.vehicle_id, b.owner_id, b.booking_type,
                   b.pickup_date, b.return_date, b.pickup_location, b.return_location,
                   b.total_days, b.price_per_day, b.subtotal, b.discount_amount,
                   b.total_amount, b.promo_code, b.status, b.special_requests,
                   b.created_at,
                   u.full_name AS renter_name, u.email AS renter_email,
                   v.brand, v.model, v.year, v.license_plate,
                   ow.full_name AS owner_name, ow.email AS owner_email
            FROM bookings b
            LEFT JOIN users u ON b.renter_id = u.id
            LEFT JOIN vehicles v ON b.vehicle_id = v.id
            LEFT JOIN users ow ON b.owner_id = ow.id
            ORDER BY b.created_at DESC
        ");
        $stmt->execute([]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Check if user already reviewed this booking
     */
    public function userHasReviewed(string $bookingId, string $userId): bool
    {
        $stmt = $this->pdo->prepare("SELECT 1 FROM reviews WHERE booking_id = ? AND user_id = ? LIMIT 1");
        $stmt->execute([$bookingId, $userId]);
        return (bool)$stmt->fetch();
    }

    /**
     * Get review stats for a vehicle
     */
    public function getVehicleReviewStats(string $vehicleId): array
    {
        $stmt = $this->pdo->prepare("SELECT ROUND(AVG(rating)::numeric, 1) as avg_rating, COUNT(*) as total FROM reviews WHERE vehicle_id = ?");
        $stmt->execute([$vehicleId]);
        return (array)$stmt->fetch(PDO::FETCH_ASSOC) ?? ['avg_rating' => null, 'total' => 0];
    }

    /**
     * Insert a review
     */
    public function insertReview(string $bookingId, string $vehicleId, string $userId, int $rating, string $comment): bool
    {
        $stmt = $this->pdo->prepare("
            INSERT INTO reviews (booking_id, vehicle_id, user_id, rating, comment)
            VALUES (?, ?, ?, ?, ?)
        ");
        return $stmt->execute([$bookingId, $vehicleId, $userId, $rating, $comment]);
    }

    /**
     * Check if user can access booking (is renter or owner)
     */
    public function canUserAccessBooking(string $bookingId, string $userId): bool
    {
        $stmt = $this->pdo->prepare("SELECT 1 FROM bookings WHERE id = ? AND (renter_id = ? OR owner_id = ?) LIMIT 1");
        $stmt->execute([$bookingId, $userId, $userId]);
        return (bool)$stmt->fetch();
    }

    /**
     * Get booking basic info by ID
     */
    public function getBookingInfo(string $bookingId): ?array
    {
        $stmt = $this->pdo->prepare("SELECT id, renter_id, vehicle_id, status FROM bookings WHERE id = ? LIMIT 1");
        $stmt->execute([$bookingId]);
        return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    /**
     * Get user info by ID
     */
    public function getUserInfo(string $userId): ?array
    {
        $stmt = $this->pdo->prepare("SELECT email, full_name, phone, address FROM users WHERE id = ? LIMIT 1");
        $stmt->execute([$userId]);
        return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    /**
     * Get vehicle status by ID
     */
    public function getVehicleStatus(string $vehicleId): ?string
    {
        $stmt = $this->pdo->prepare("SELECT status FROM vehicles WHERE id = ? LIMIT 1");
        $stmt->execute([$vehicleId]);
        return $stmt->fetchColumn();
    }

    /**
     * Get vehicle full info by ID
     */
    public function getVehicleInfo(string $vehicleId): ?array
    {
        $stmt = $this->pdo->prepare("SELECT brand, model, year, license_plate FROM vehicles WHERE id = ? LIMIT 1");
        $stmt->execute([$vehicleId]);
        return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    /**
     * Get payment method for booking
     */
    public function getPaymentMethod(string $bookingId): ?string
    {
        $stmt = $this->pdo->prepare("SELECT method FROM payments WHERE booking_id = ? LIMIT 1");
        $stmt->execute([$bookingId]);
        return $stmt->fetchColumn();
    }

    /**
     * Get booking full info
     */
    public function getBookingFullInfo(string $bookingId): ?array
    {
        $stmt = $this->pdo->prepare("SELECT subtotal, discount_amount, total_amount, pickup_location, return_location, vehicle_id FROM bookings WHERE id = ? LIMIT 1");
        $stmt->execute([$bookingId]);
        return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    /**
     * Get user address by ID
     */
    public function getUserAddress(string $userId): ?string
    {
        $stmt = $this->pdo->prepare("SELECT address FROM users WHERE id = ? LIMIT 1");
        $stmt->execute([$userId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ? (string)($row['address'] ?? '') : null;
    }

    /**
     * Create payment record
     */
    public function createPayment(string $bookingId, string $userId, float $amount, string $method): bool
    {
        $stmt = $this->pdo->prepare("
            INSERT INTO payments (booking_id, user_id, amount, method, status)
            VALUES (?, ?, ?, ?, 'pending')
        ");
        return $stmt->execute([$bookingId, $userId, $amount, $method]);
    }

    /**
     * Persist gateway transaction data for an existing booking payment row
     */
    public function attachPaymentTransaction(string $bookingId, string $transactionId, array $paymentDetails = []): bool
    {
        $detailsJson = json_encode($paymentDetails);
        $stmt = $this->pdo->prepare("
            UPDATE payments
            SET transaction_id = ?, payment_details = ?::jsonb
            WHERE booking_id = ?
        ");
        return $stmt->execute([$transactionId, $detailsJson ?: '{}', $bookingId]) && $stmt->rowCount() > 0;
    }

    /**
     * Load payment row by PayPal order/transaction ID
     * @return array<string,mixed>|null
     */
    public function getPaymentByTransactionId(string $transactionId): ?array
    {
        $stmt = $this->pdo->prepare("SELECT * FROM payments WHERE transaction_id = ? LIMIT 1");
        $stmt->execute([$transactionId]);
        return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    /**
     * Load payment row by booking ID
     * @return array<string,mixed>|null
     */
    public function getPaymentByBookingId(string $bookingId): ?array
    {
        $stmt = $this->pdo->prepare("SELECT * FROM payments WHERE booking_id = ? LIMIT 1");
        $stmt->execute([$bookingId]);
        return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    /**
     * Mark payment as paid/failed and optionally persist gateway response
     */
    public function updatePaymentByTransactionId(string $transactionId, string $status, array $paymentDetails = [], bool $setPaidAt = false): bool
    {
        if (!in_array($status, ['pending', 'paid', 'refunded', 'failed'], true)) {
            return false;
        }

        $detailsJson = json_encode($paymentDetails);
        $sql = "UPDATE payments SET status = ?::payment_status, payment_details = ?::jsonb";
        if ($setPaidAt) {
            $sql .= ", paid_at = NOW()";
        }
        $sql .= " WHERE transaction_id = ?";

        $stmt = $this->pdo->prepare($sql);
        return $stmt->execute([$status, $detailsJson ?: '{}', $transactionId]) && $stmt->rowCount() > 0;
    }

    /**
     * Update payment by booking ID and optionally persist gateway response.
     */
    public function updatePaymentByBookingId(string $bookingId, string $status, array $paymentDetails = [], bool $setPaidAt = false): bool
    {
        if (!in_array($status, ['pending', 'paid', 'refunded', 'failed'], true)) {
            return false;
        }

        $detailsJson = json_encode($paymentDetails);
        $sql = "UPDATE payments SET status = ?::payment_status, payment_details = ?::jsonb";
        if ($setPaidAt) {
            $sql .= ", paid_at = NOW()";
        }
        $sql .= " WHERE booking_id = ?";

        $stmt = $this->pdo->prepare($sql);
        return $stmt->execute([$status, $detailsJson ?: '{}', $bookingId]) && $stmt->rowCount() > 0;
    }

    /**
     * Remove an unpaid PayPal booking after checkout cancellation.
     * Only allows deleting renter-owned, pending bookings with non-paid PayPal payment state.
     */
    public function removeUnpaidPaypalBooking(string $bookingId, string $userId): bool
    {
        try {
            $this->pdo->beginTransaction();

            $stmt = $this->pdo->prepare("SELECT id, status, renter_id FROM bookings WHERE id = ? FOR UPDATE");
            $stmt->execute([$bookingId]);
            $booking = $stmt->fetch(PDO::FETCH_ASSOC);
            if (!$booking) {
                $this->pdo->rollBack();
                return false;
            }

            if ((string)($booking['renter_id'] ?? '') !== $userId) {
                $this->pdo->rollBack();
                return false;
            }

            if ((string)($booking['status'] ?? '') !== 'pending') {
                $this->pdo->rollBack();
                return false;
            }

            $stmt = $this->pdo->prepare("SELECT id, method, status FROM payments WHERE booking_id = ? FOR UPDATE");
            $stmt->execute([$bookingId]);
            $payment = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$payment || (string)($payment['method'] ?? '') !== 'paypal') {
                $this->pdo->rollBack();
                return false;
            }

            if ((string)($payment['status'] ?? '') === 'paid') {
                $this->pdo->rollBack();
                return false;
            }

            try {
                $stmt = $this->pdo->prepare("DELETE FROM active_trips WHERE booking_id = ?");
                $stmt->execute([$bookingId]);
            } catch (Throwable $e) {
                // Optional table in some environments.
            }

            try {
                $stmt = $this->pdo->prepare("DELETE FROM driver_notifications WHERE booking_id = ?");
                $stmt->execute([$bookingId]);
            } catch (Throwable $e) {
                // Optional table in some environments.
            }

            $stmt = $this->pdo->prepare("DELETE FROM payments WHERE booking_id = ?");
            $stmt->execute([$bookingId]);

            $stmt = $this->pdo->prepare("DELETE FROM bookings WHERE id = ?");
            $stmt->execute([$bookingId]);

            if ($stmt->rowCount() < 1) {
                $this->pdo->rollBack();
                return false;
            }

            $this->pdo->commit();
            return true;
        } catch (Throwable $e) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            return false;
        }
    }

    /**
     * Get active drivers for minicab
     * @return array<int,array<string,mixed>>
     */
    public function getActiveDrivers(int $limit = 20): array
    {
        $stmt = $this->pdo->prepare("SELECT id FROM users WHERE role = 'driver' AND is_active = true LIMIT ?");
        $stmt->execute([$limit]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Create driver notification
     */
    public function createDriverNotification(string $driverId, string $bookingId, string $title, string $message, string $notificationType = 'minicab_request'): bool
    {
        $stmt = $this->pdo->prepare("
            INSERT INTO driver_notifications (driver_id, booking_id, title, message, notification_type, is_read, created_at)
            VALUES (?, ?, ?, ?, ?, false, NOW())
        ");
        return $stmt->execute([$driverId, $bookingId, $title, $message, $notificationType]);
    }

    /**
     * Check how many active bookings exist for a vehicle
     */
    public function countActiveBookingsByVehicleId(string $vehicleId): int
    {
        $stmt = $this->pdo->prepare("SELECT COUNT(*) FROM bookings WHERE vehicle_id = ? AND status IN ('pending', 'confirmed', 'in_progress')");
        $stmt->execute([$vehicleId]);
        return (int) $stmt->fetchColumn();
    }

    /**
     * Check how many active bookings exist for a renter
     */
    public function countActiveBookingsByRenterId(string $renterId): int
    {
        $stmt = $this->pdo->prepare("SELECT COUNT(*) FROM bookings WHERE renter_id = ? AND status IN ('pending', 'confirmed', 'in_progress')");
        $stmt->execute([$renterId]);
        return (int) $stmt->fetchColumn();
    }

    /**
     * Get reviews for a vehicle with user details and stats
     * @return array<int,array<string,mixed>>
     */
    public function getReviewsWithDetailsAndStats(string $vehicleId, int $limit = 50): array
    {
        $stmt = $this->pdo->prepare("
            SELECT 
                r.id, r.rating, r.content, r.created_at,
                u.full_name, u.avatar_url,
                v.brand, v.model, v.year,
                b.pickup_location, b.return_location, b.booking_type
            FROM reviews r
            JOIN users u ON r.user_id = u.id
            JOIN vehicles v ON r.vehicle_id = v.id
            LEFT JOIN bookings b ON r.booking_id = b.id
            WHERE r.vehicle_id = ?
            ORDER BY r.created_at DESC
            LIMIT ?
        ");
        $stmt->execute([$vehicleId, $limit]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Get complete review statistics for a vehicle
     * @return array<string,mixed>
     */
    public function getReviewStatsComplete(string $vehicleId): array
    {
        $stmt = $this->pdo->prepare("
            SELECT 
                COUNT(*) as total,
                ROUND(AVG(rating)::numeric, 1) as avg_rating,
                COUNT(*) FILTER (WHERE rating = 5) as stars_5,
                COUNT(*) FILTER (WHERE rating = 4) as stars_4,
                COUNT(*) FILTER (WHERE rating = 3) as stars_3,
                COUNT(*) FILTER (WHERE rating = 2) as stars_2,
                COUNT(*) FILTER (WHERE rating = 1) as stars_1
            FROM reviews
            WHERE vehicle_id = ?
        ");
        $stmt->execute([$vehicleId]);
        return $stmt->fetch(PDO::FETCH_ASSOC) ?: [];
    }
}
