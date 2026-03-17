<?php

declare(strict_types=1);

final class TripRepository
{
    private PDO $pdo;

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    /**
     * Get available trips (searching for driver or waiting for pickup)
     * @return array<int,array<string,mixed>>
     */
    public function getAvailableTrips(string $excludeDriverId = '', int $limit = 50): array
    {
        $query = "
            SELECT 
                at.id, at.booking_id, at.status, at.pickup_lat, at.pickup_lng,
                at.destination_lat, at.destination_lng, at.created_at,
                b.number_of_passengers, b.ride_tier, b.pickup_location, b.special_requests,
                u.full_name as user_name, u.phone as user_phone,
                v.brand, v.model, v.year, v.license_plate
            FROM active_trips at
            JOIN bookings b ON at.booking_id = b.id
            JOIN users u ON at.user_id = u.id
            LEFT JOIN vehicles v ON at.vehicle_id = v.id
            WHERE at.status IN ('searching_driver', 'driver_accepted')
        ";

        $params = [];
        if (!empty($excludeDriverId)) {
            $query .= " AND (at.driver_id IS NULL OR at.driver_id != ?)";
            $params[] = $excludeDriverId;
        }

        $query .= " ORDER BY at.created_at DESC LIMIT ?";
        $params[] = $limit;

        $stmt = $this->pdo->prepare($query);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Get trip details
     * @return array<string,mixed>|null
     */
    public function getTripById(string $tripId): ?array
    {
        $stmt = $this->pdo->prepare("
            SELECT 
                at.id, at.booking_id, at.user_id, at.driver_id, at.vehicle_id,
                at.status, at.pickup_lat, at.pickup_lng,
                at.destination_lat, at.destination_lng, at.created_at, at.updated_at,
                b.*, 
                u.full_name as user_name, u.phone as user_phone, u.email as user_email,
                driver.full_name as driver_name, driver.phone as driver_phone,
                v.brand, v.model, v.license_plate
            FROM active_trips at
            JOIN bookings b ON at.booking_id = b.id
            JOIN users u ON at.user_id = u.id
            LEFT JOIN users driver ON at.driver_id = driver.id
            LEFT JOIN vehicles v ON at.vehicle_id = v.id
            WHERE at.id = ?
        ");
        $stmt->execute([$tripId]);
        return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    /**
     * Accept a trip (driver accepts to pick up passenger)
     */
    public function acceptTrip(string $tripId, string $driverId, string $vehicleId): bool
    {
        $stmt = $this->pdo->prepare("
            UPDATE active_trips
            SET driver_id = ?, vehicle_id = ?, status = 'driver_accepted', updated_at = NOW()
            WHERE id = ? AND status = 'searching_driver'
        ");
        return $stmt->execute([$driverId, $vehicleId, $tripId]) && $stmt->rowCount() > 0;
    }

    /**
     * Update trip status
     */
    public function updateStatus(string $tripId, string $status): bool
    {
        $stmt = $this->pdo->prepare("
            UPDATE active_trips
            SET status = ?, updated_at = NOW()
            WHERE id = ?
        ");
        return $stmt->execute([$status, $tripId]) && $stmt->rowCount() > 0;
    }

    /**
     * Update driver's current location during trip
     */
    public function updateDriverLocation(string $tripId, float $lat, float $lng): bool
    {
        $stmt = $this->pdo->prepare("
            UPDATE active_trips
            SET driver_lat = ?, driver_lng = ?, updated_at = NOW()
            WHERE id = ?
        ");
        return $stmt->execute([$lat, $lng, $tripId]) && $stmt->rowCount() > 0;
    }

    /**
     * Get driver's active trips (trips assigned to driver)
     * @return array<int,array<string,mixed>>
     */
    public function getDriverActiveTrips(string $driverId): array
    {
        $stmt = $this->pdo->prepare("
            SELECT 
                at.id, at.booking_id, at.status, at.pickup_lat, at.pickup_lng,
                at.destination_lat, at.destination_lng, at.driver_lat, at.driver_lng,
                b.pickup_location, b.return_location, b.special_requests,
                u.full_name as user_name, u.phone as user_phone,
                v.brand, v.model, v.license_plate
            FROM active_trips at
            JOIN bookings b ON at.booking_id = b.id
            JOIN users u ON at.user_id = u.id
            LEFT JOIN vehicles v ON at.vehicle_id = v.id
            WHERE at.driver_id = ? AND at.status IN ('driver_accepted', 'arrived_pickup', 'on_trip')
            ORDER BY at.created_at DESC
        ");
        $stmt->execute([$driverId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Get user's active trips (trips user is on)
     * @return array<int,array<string,mixed>>
     */
    public function getUserActiveTrips(string $userId): array
    {
        $stmt = $this->pdo->prepare("
            SELECT 
                at.id, at.booking_id, at.status, at.pickup_lat, at.pickup_lng,
                at.destination_lat, at.destination_lng, at.driver_lat, at.driver_lng,
                at.created_at,
                b.pickup_location, b.return_location,
                driver.full_name as driver_name, driver.phone as driver_phone,
                v.brand, v.model, v.license_plate
            FROM active_trips at
            JOIN bookings b ON at.booking_id = b.id
            LEFT JOIN users driver ON at.driver_id = driver.id
            LEFT JOIN vehicles v ON at.vehicle_id = v.id
            WHERE at.user_id = ? AND at.status IN ('searching_driver', 'driver_accepted', 'arrived_pickup', 'on_trip')
            ORDER BY at.created_at DESC
        ");
        $stmt->execute([$userId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Complete a trip (arrival at destination)
     */
    public function completeTrip(string $tripId): bool
    {
        $stmt = $this->pdo->prepare("
            UPDATE active_trips
            SET status = 'completed', updated_at = NOW()
            WHERE id = ?
        ");
        return $stmt->execute([$tripId]) && $stmt->rowCount() > 0;
    }

    /**
     * Cancel a trip
     */
    public function cancelTrip(string $tripId, ?string $reason = null): bool
    {
        $stmt = $this->pdo->prepare("
            UPDATE active_trips
            SET status = 'cancelled', cancellation_reason = ?, updated_at = NOW()
            WHERE id = ?
        ");
        return $stmt->execute([$reason, $tripId]) && $stmt->rowCount() > 0;
    }

    /**
     * Get trip history for driver
     * @return array<int,array<string,mixed>>
     */
    public function getDriverTripHistory(string $driverId, int $limit = 50, int $offset = 0): array
    {
        $stmt = $this->pdo->prepare("
            SELECT 
                at.id, at.booking_id, at.status, at.created_at, at.updated_at,
                b.pickup_location, b.return_location, b.total_amount,
                u.full_name as user_name,
                v.brand, v.model
            FROM active_trips at
            JOIN bookings b ON at.booking_id = b.id
            JOIN users u ON at.user_id = u.id
            LEFT JOIN vehicles v ON at.vehicle_id = v.id
            WHERE at.driver_id = ? AND at.status IN ('completed', 'cancelled')
            ORDER BY at.updated_at DESC
            LIMIT ? OFFSET ?
        ");
        $stmt->execute([$driverId, $limit, $offset]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Get driver stats (completed trips, rating, earnings)
     * @return array<string,mixed>
     */
    public function getDriverStats(string $driverId): array
    {
        $stmt = $this->pdo->prepare("
            SELECT 
                COUNT(*) as total_trips,
                SUM(CASE WHEN status = 'completed' THEN 1 ELSE 0 END) as completed_trips,
                SUM(CASE WHEN status = 'cancelled' THEN 1 ELSE 0 END) as cancelled_trips,
                COALESCE(AVG(CASE WHEN rating IS NOT NULL THEN rating ELSE NULL END), 0) as avg_rating
            FROM active_trips
            WHERE driver_id = ?
        ");
        $stmt->execute([$driverId]);
        return (array) $stmt->fetch(PDO::FETCH_ASSOC);
    }

    /**
     * Get system trip stats
     * @return array<string,mixed>
     */
    public function getSystemStats(): array
    {
        $stmt = $this->pdo->query("
            SELECT 
                COUNT(*) as total_trips,
                SUM(CASE WHEN status = 'searching_driver' THEN 1 ELSE 0 END) as waiting_for_driver,
                SUM(CASE WHEN status = 'driver_accepted' THEN 1 ELSE 0 END) as driver_accepted,
                SUM(CASE WHEN status = 'on_trip' THEN 1 ELSE 0 END) as on_trip,
                SUM(CASE WHEN status = 'completed' THEN 1 ELSE 0 END) as completed,
                SUM(CASE WHEN status = 'cancelled' THEN 1 ELSE 0 END) as cancelled
            FROM active_trips
        ");
        return (array) $stmt->fetch(PDO::FETCH_ASSOC);
    }

    /**
     * Add rating to completed trip
     */
    public function rateTrip(string $tripId, int $rating, ?string $comment = null): bool
    {
        if ($rating < 1 || $rating > 5) {
            return false;
        }

        $stmt = $this->pdo->prepare("
            UPDATE active_trips
            SET rating = ?, review_comment = ?
            WHERE id = ? AND status = 'completed'
        ");
        return $stmt->execute([$rating, $comment, $tripId]) && $stmt->rowCount() > 0;
    }

    /**
     * Get driver's trips with optional status filtering
     * @return array<int,array<string,mixed>>
     */
    public function getDriverTrips(string $driverId, ?string $status = null, int $limit = 50): array
    {
        $query = "
            SELECT 
                at.id, at.booking_id, at.status, at.pickup_lat, at.pickup_lng,
                at.destination_lat, at.destination_lng, at.created_at,
                b.number_of_passengers, b.ride_tier, b.pickup_location, 
                b.special_requests, b.total_amount,
                u.id as user_id, u.full_name as user_name, u.phone as user_phone, u.avatar_url
            FROM active_trips at
            JOIN bookings b ON at.booking_id = b.id
            JOIN users u ON at.user_id = u.id
            WHERE at.driver_id = ?
        ";

        $params = [$driverId];

        if ($status === 'current') {
            $query .= " AND at.status NOT IN ('completed', 'cancelled')";
        } elseif ($status === 'completed') {
            $query .= " AND at.status = 'completed'";
        }

        $query .= " ORDER BY at.created_at DESC LIMIT ?";
        $params[] = $limit;

        $stmt = $this->pdo->prepare($query);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Mark driver as arrived at pickup location
     */
    public function markDriverArrived(string $tripId, string $driverId): bool
    {
        $stmt = $this->pdo->prepare("
            SELECT booking_id, pickup_lat, pickup_lng FROM active_trips WHERE id = ? AND driver_id = ?
        ");
        $stmt->execute([$tripId, $driverId]);
        $trip = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$trip) {
            return false;
        }

        // Update active_trips status
        $updateStmt = $this->pdo->prepare("
            UPDATE active_trips
            SET status = 'driver_arrived', driver_arrived_at = NOW(), 
                driver_lat = ?, driver_lng = ?, updated_at = NOW()
            WHERE id = ?
        ");
        $updateStmt->execute([$trip['pickup_lat'], $trip['pickup_lng'], $tripId]);

        // Update booking
        $bookingStmt = $this->pdo->prepare("UPDATE bookings SET driver_arrived_at = NOW() WHERE id = ?");
        $bookingStmt->execute([$trip['booking_id']]);

        // Notify user
        $notificationStmt = $this->pdo->prepare("
            INSERT INTO notifications (user_id, type, title, message, is_read, created_at)
            SELECT at.user_id, 'booking', 'Driver Arrived', 
                   'Your driver has arrived at the pickup location!',
                   false, NOW()
            FROM active_trips at
            WHERE at.id = ?
        ");
        $notificationStmt->execute([$tripId]);

        return $updateStmt->rowCount() > 0;
    }

    /**
     * Start journey (driver picked up passenger and started moving)
     */
    public function startJourney(string $tripId, string $driverId): bool
    {
        $stmt = $this->pdo->prepare("
            SELECT booking_id FROM active_trips WHERE id = ? AND driver_id = ?
        ");
        $stmt->execute([$tripId, $driverId]);
        $trip = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$trip) {
            return false;
        }

        // Update active_trips
        $updateStmt = $this->pdo->prepare("
            UPDATE active_trips
            SET status = 'journey_started', journey_started_at = NOW(), updated_at = NOW()
            WHERE id = ?
        ");
        $updateStmt->execute([$tripId]);

        // Update booking
        $bookingStmt = $this->pdo->prepare("UPDATE bookings SET ride_started_at = NOW() WHERE id = ?");
        $bookingStmt->execute([$trip['booking_id']]);

        // Notify user
        $notificationStmt = $this->pdo->prepare("
            INSERT INTO notifications (user_id, type, title, message, is_read, created_at)
            SELECT at.user_id, 'booking', 'Journey Started', 
                   'Your journey has started! Your driver is on the way.',
                   false, NOW()
            FROM active_trips at
            WHERE at.id = ?
        ");
        $notificationStmt->execute([$tripId]);

        return $updateStmt->rowCount() > 0;
    }

    /**
     * Complete trip (driver arrived at destination)
     * Enhanced version that also updates booking and notifies user
     */
    public function completeTripWithNotification(string $tripId, string $driverId): bool
    {
        $stmt = $this->pdo->prepare("
            SELECT booking_id, user_id FROM active_trips WHERE id = ? AND driver_id = ?
        ");
        $stmt->execute([$tripId, $driverId]);
        $trip = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$trip) {
            return false;
        }

        // Update active_trips
        $updateStmt = $this->pdo->prepare("
            UPDATE active_trips
            SET status = 'completed', completed_at = NOW(), updated_at = NOW()
            WHERE id = ?
        ");
        $updateStmt->execute([$tripId]);

        // Update booking
        $bookingStmt = $this->pdo->prepare("
            UPDATE bookings
            SET status = 'completed', ride_completed_at = NOW()
            WHERE id = ?
        ");
        $bookingStmt->execute([$trip['booking_id']]);

        // Notify user
        $notificationStmt = $this->pdo->prepare("
            INSERT INTO notifications (user_id, type, title, message, is_read, created_at)
            VALUES (?, 'booking', 'Trip Completed', 
                   'Thank you for your trip! We hope you had a great experience. Please rate your driver.',
                   false, NOW())
        ");
        $notificationStmt->execute([$trip['user_id']]);

        return $updateStmt->rowCount() > 0;
    }

    /**
     * Get active trip by booking ID
     */
    public function getByBookingId(string $bookingId): ?array
    {
        $stmt = $this->pdo->prepare("SELECT id FROM active_trips WHERE booking_id = ? LIMIT 1");
        $stmt->execute([$bookingId]);
        return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    }
}
