<?php

declare(strict_types=1);

final class StaffBookingRepository
{
    private PDO $pdo;

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    /**
     * Create a phone booking by staff (for customers without account)
     * @param string $renterId The renter/customer ID
     * @param string $vehicleId The vehicle ID
     * @param array<string,mixed> $data Booking data (customer_name, customer_phone, customer_email, pickup_date, return_date, etc.)
     * @return array<string,mixed> Returns booking with id, status, created_at, total_amount, booking_ref
     */
    public function createPhoneBooking(string $renterId, string $vehicleId, array $data): array
    {
        // Ensure schema columns exist
        try {
            $this->pdo->exec("ALTER TABLE bookings ADD COLUMN IF NOT EXISTS phone_customer_name VARCHAR(255)");
            $this->pdo->exec("ALTER TABLE bookings ADD COLUMN IF NOT EXISTS phone_customer_phone VARCHAR(20)");
            $this->pdo->exec("ALTER TABLE bookings ADD COLUMN IF NOT EXISTS phone_customer_email VARCHAR(255)");
            $this->pdo->exec("ALTER TABLE bookings ADD COLUMN IF NOT EXISTS created_by_staff_id UUID");
            $this->pdo->exec("ALTER TABLE bookings ADD COLUMN IF NOT EXISTS booking_ref VARCHAR(50)");
            // Call-center minicab requests should allow null daily price.
            $this->pdo->exec("ALTER TABLE bookings ALTER COLUMN price_per_day DROP NOT NULL");
        } catch (PDOException $e) {
            // Columns may already exist
        }

        // Build booking reference
        $bookingRef = $this->generateBookingReference();

        // Start transaction for consistency
        $this->pdo->beginTransaction();

        try {
            // Verify vehicle exists and is available
            $vehicleStmt = $this->pdo->prepare("SELECT owner_id, status FROM vehicles WHERE id = ? FOR UPDATE");
            $vehicleStmt->execute([$vehicleId]);
            $vehicle = $vehicleStmt->fetch(PDO::FETCH_ASSOC);
            if (!$vehicle) {
                throw new PDOException('Vehicle not found');
            }
            if (($vehicle['status'] ?? '') !== 'available') {
                throw new PDOException('Vehicle is not available');
            }

            // Check for overlapping bookings
            $pickupDate = $data['pickup_date'] ?? null;
            $returnDate = $data['return_date'] ?? null;
            $newEndDate = $returnDate ?: $pickupDate;
            
            $conflictStmt = $this->pdo->prepare("
                SELECT COUNT(*)
                FROM bookings
                WHERE vehicle_id = ?
                  AND status IN ('pending', 'confirmed', 'in_progress')
                  AND pickup_date <= ?
                  AND (return_date IS NULL OR return_date >= ?)
            ");
            $conflictStmt->execute([$vehicleId, $newEndDate, $pickupDate]);
            $conflicts = (int)$conflictStmt->fetchColumn();
            if ($conflicts > 0) {
                throw new PDOException('Vehicle already has an overlapping booking for selected dates');
            }

            // Insert booking
            $stmt = $this->pdo->prepare("
                INSERT INTO bookings (
                    renter_id, owner_id, vehicle_id, booking_type, status,
                    pickup_date, return_date, pickup_location, return_location,
                    total_days, subtotal, discount_amount, total_amount,
                    special_requests, driver_requested, phone_customer_name,
                    phone_customer_phone, phone_customer_email, created_by_staff_id,
                    booking_ref, created_at, updated_at
                ) VALUES (
                    ?, ?, ?, ?, ?,
                    ?, ?, ?, ?,
                    ?, ?, ?, ?,
                    ?, ?, ?, ?, ?, ?,
                    ?, NOW(), NOW()
                )
                RETURNING id, status, created_at, total_amount
            ");

            $initialStatus = $data['initial_status'] ?? 'pending';
            $bookingType = (string)($data['booking_type'] ?? 'with-driver');
            $stmt->execute([
                $renterId,
                $vehicle['owner_id'],
                $vehicleId,
                $bookingType,
                $initialStatus,
                $pickupDate,
                $returnDate,
                $data['pickup_location'],
                $data['return_location'] ?? null,
                $data['days'] ?? 1,
                $data['subtotal'] ?? 0,
                $data['discount_amount'] ?? 0,
                $data['total_amount'] ?? 0,
                $data['special_requests'] ?? '',
                true,
                $data['customer_name'],
                $data['customer_phone'],
                $data['customer_email'],
                $data['staff_id'],
                $bookingRef
            ]);

            $booking = $stmt->fetch(PDO::FETCH_ASSOC);
            if (!$booking) {
                throw new PDOException('Failed to create booking');
            }

            // Create payment row
            $paymentStmt = $this->pdo->prepare("
                INSERT INTO payments (booking_id, user_id, amount, method, status, created_at)
                VALUES (?, ?, ?, ?, 'pending', NOW())
            ");
            $paymentMethod = $data['payment_method'] ?? 'cash';
            $paymentStmt->execute([
                $booking['id'],
                $renterId,
                $booking['total_amount'],
                $paymentMethod
            ]);

            // Keep vehicle state unchanged while request is pending.
            // Control Staff will mark it rented when moving booking to in_progress.

            $this->pdo->commit();

            // Add booking_ref to response
            $booking['booking_ref'] = $bookingRef;

            return $booking;
        } catch (Exception $e) {
            $this->pdo->rollBack();
            throw $e;
        }
    }

    /**
     * Generate unique booking reference
     */
    private function generateBookingReference(): string
    {
        $date = gmdate('Ymd');
        $rand = strtoupper(substr(bin2hex(random_bytes(3)), 0, 6));
        return 'PH-' . $date . '-' . $rand;
    }

    /**
     * Get all orders (bookings) with customer details
     * @return array<int,array<string,mixed>>
     */
    public function listOrders(int $limit = 100, int $offset = 0): array
    {
        $stmt = $this->pdo->prepare("
            SELECT 
                b.id, b.booking_type, b.pickup_date, b.return_date,
                b.pickup_location, b.return_location, b.total_amount,
                b.status, b.created_at,
                COALESCE(u.full_name, b.phone_customer_name) as customer_name,
                COALESCE(u.email, b.phone_customer_email) as customer_email,
                COALESCE(u.phone, b.phone_customer_phone) as customer_phone,
                v.brand, v.model, v.license_plate, v.thumbnail_url,
                staff.full_name as staff_name
            FROM bookings b
            LEFT JOIN users u ON b.renter_id = u.id
            LEFT JOIN vehicles v ON b.vehicle_id = v.id
            LEFT JOIN users staff ON b.created_by_staff_id = staff.id
            ORDER BY b.created_at DESC
            LIMIT ? OFFSET ?
        ");
        $stmt->execute([$limit, $offset]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Get staff's created bookings
     * @return array<int,array<string,mixed>>
     */
    public function getStaffBookings(string $staffId, int $limit = 50, int $offset = 0): array
    {
        $stmt = $this->pdo->prepare("
            SELECT 
                b.id, b.booking_type, b.pickup_date, b.return_date,
                b.pickup_location, b.return_location, b.total_amount,
                b.status, b.created_at,
                COALESCE(u.full_name, b.phone_customer_name) as customer_name,
                COALESCE(u.email, b.phone_customer_email) as customer_email,
                COALESCE(u.phone, b.phone_customer_phone) as customer_phone,
                v.brand, v.model, v.license_plate
            FROM bookings b
            LEFT JOIN users u ON b.renter_id = u.id
            LEFT JOIN vehicles v ON b.vehicle_id = v.id
            WHERE b.created_by_staff_id = ?
            ORDER BY b.created_at DESC
            LIMIT ? OFFSET ?
        ");
        $stmt->execute([$staffId, $limit, $offset]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Get booking with full details
     * @return array<string,mixed>|null
     */
    public function getOrderDetails(string $bookingId): ?array
    {
        $stmt = $this->pdo->prepare("
            SELECT 
                b.*,
                COALESCE(u.full_name, b.phone_customer_name) as customer_name,
                COALESCE(u.email, b.phone_customer_email) as customer_email,
                COALESCE(u.phone, b.phone_customer_phone) as customer_phone,
                v.brand, v.model, v.license_plate, v.year, v.thumbnail_url,
                staff.full_name as staff_name
            FROM bookings b
            LEFT JOIN users u ON b.renter_id = u.id
            LEFT JOIN vehicles v ON b.vehicle_id = v.id
            LEFT JOIN users staff ON b.created_by_staff_id = staff.id
            WHERE b.id = ?
        ");
        $stmt->execute([$bookingId]);
        return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    /**
     * Update booking status by staff
     */
    public function updateBookingStatus(string $bookingId, string $status, ?string $notes = null): bool
    {
        $stmt = $this->pdo->prepare("
            UPDATE bookings
            SET status = ?, special_requests = CASE 
                WHEN ? IS NOT NULL THEN special_requests || '\n[STAFF NOTE] ' || ? 
                ELSE special_requests 
            END
            WHERE id = ?
        ");
        return $stmt->execute([$status, $notes, $notes, $bookingId]) && $stmt->rowCount() > 0;
    }

    /**
     * Assign driver to booking
     */
    public function assignDriver(string $bookingId, string $driverId): bool
    {
        $stmt = $this->pdo->prepare("
            UPDATE bookings
            SET driver_id = ?
            WHERE id = ?
        ");
        return $stmt->execute([$driverId, $bookingId]) && $stmt->rowCount() > 0;
    }

    /**
     * Mark vehicle as rented when booking is confirmed
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
     * Mark vehicle as available when booking is completed
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
     * Get vehicle fleet for staff
     * @return array<int,array<string,mixed>>
     */
    public function getFleet(int $limit = 50): array
    {
        $stmt = $this->pdo->prepare("
            SELECT id, brand, model, license_plate, status, seats, 
                   thumbnail_url, category
            FROM vehicles
            ORDER BY brand ASC, model ASC
            LIMIT ?
        ");
        $stmt->execute([$limit]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Get fleet stats (available/rented/total counts)
     * @return array<string,mixed>
     */
    public function getFleetStats(): array
    {
        $stmt = $this->pdo->query("
            SELECT 
                COUNT(*) as total_vehicles,
                SUM(CASE WHEN status = 'available' THEN 1 ELSE 0 END) as available_count,
                SUM(CASE WHEN status = 'rented' THEN 1 ELSE 0 END) as rented_count,
                SUM(CASE WHEN status = 'maintenance' THEN 1 ELSE 0 END) as maintenance_count
            FROM vehicles
        ");
        return (array) $stmt->fetch(PDO::FETCH_ASSOC);
    }

    /**
     * Get booking stats (by status)
     * @return array<string,mixed>
     */
    public function getBookingStats(): array
    {
        $stmt = $this->pdo->query("
            SELECT 
                COUNT(*) as total_bookings,
                SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END) as pending_count,
                SUM(CASE WHEN status = 'confirmed' THEN 1 ELSE 0 END) as confirmed_count,
                SUM(CASE WHEN status = 'completed' THEN 1 ELSE 0 END) as completed_count,
                SUM(CASE WHEN status = 'cancelled' THEN 1 ELSE 0 END) as cancelled_count
            FROM bookings
        ");
        return (array) $stmt->fetch(PDO::FETCH_ASSOC);
    }
}
