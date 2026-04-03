<?php
/**
 * Driver Dashboard & API - Private Hire
 * - Page view: /api/driver.php (renders template)
 * - API: /api/driver.php?action=xxx (returns JSON)
 */

declare(strict_types=1);

session_start();
require_once __DIR__ . '/../config/env.php';
require_once __DIR__ . '/../Database/db.php';
require_once __DIR__ . '/../sql/UserRepository.php';
require_once __DIR__ . '/../sql/VehicleRepository.php';
require_once __DIR__ . '/../sql/VehicleImageRepository.php';
require_once __DIR__ . '/../sql/BookingRepository.php';
require_once __DIR__ . '/../sql/TripRepository.php';
require_once __DIR__ . '/../sql/NotificationRepository.php';
require_once __DIR__ . '/notification-helpers.php';
require_once __DIR__ . '/../Invoice/mailer.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: /index.php');
    exit;
}

$userId = (string)$_SESSION['user_id'];
$userRepo = new UserRepository($pdo);
$vehicleRepo = new VehicleRepository($pdo);
$vehicleImageRepo = new VehicleImageRepository($pdo);
$bookingRepo = new BookingRepository($pdo);
$tripRepo = new TripRepository($pdo);
$notificationRepo = new NotificationRepository($pdo);

$user = $userRepo->findById($userId);
if (!$user || ($user['role'] ?? '') !== 'driver') {
    header('Location: /index.php');
    exit;
}

$title = 'Driver Dashboard - Private Hire';
$currentPage = 'driver';
$isLoggedIn = true;
$userRole = 'driver';
$currentUser = $user['full_name'] ?? $user['email'] ?? 'Driver';

require_once __DIR__ . '/bootstrap.php';

$body = api_init(['allow_origin' => '*']);
$action = trim(api_action($body));

function driver_json(array $payload, int $status = 200): void
{
    http_response_code($status);
    header('Content-Type: application/json');
    echo json_encode($payload);
    exit;
}

function driver_canonical_status(array $row): string
{
    $tripStatus = strtolower(trim((string)($row['trip_status'] ?? '')));
    if (in_array($tripStatus, ['on_route', 'driver_accepted', 'driver_arrived', 'driver_arriving'], true)) {
        return 'on_route';
    }
    if (in_array($tripStatus, ['on_trip', 'journey_started'], true)) {
        return 'on_trip';
    }
    if ($tripStatus === 'completed') {
        return 'completed';
    }

    $bookingStatus = strtolower(trim((string)($row['booking_status'] ?? '')));
    if ($bookingStatus === 'completed') {
        return 'completed';
    }

    if (!empty($row['ride_started_at'])) {
        return 'on_trip';
    }

    return 'on_route';
}

function driver_next_status(string $current): ?string
{
    return match ($current) {
        'on_route' => 'on_trip',
        'on_trip' => 'completed',
        default => null,
    };
}

function driver_booking_has_column(PDO $pdo, string $columnName): bool
{
    static $cache = null;
    if (!is_array($cache)) {
        $cache = [];
        try {
            $stmt = $pdo->query("SELECT column_name FROM information_schema.columns WHERE table_schema = current_schema() AND table_name = 'bookings'");
            $cols = $stmt ? $stmt->fetchAll(PDO::FETCH_COLUMN) : [];
            foreach ($cols as $col) {
                $cache[(string)$col] = true;
            }
        } catch (Throwable $e) {
            $cache = [];
        }
    }

    return isset($cache[$columnName]);
}

function driver_build_vehicle_name(array $booking): string
{
    $parts = [];
    if (!empty($booking['brand'])) {
        $parts[] = (string)$booking['brand'];
    }
    if (!empty($booking['model'])) {
        $parts[] = (string)$booking['model'];
    }
    if (!empty($booking['year'])) {
        $parts[] = (string)$booking['year'];
    }

    $name = trim(implode(' ', $parts));
    return $name !== '' ? $name : 'Assigned vehicle';
}

function driver_get_vehicle_image_url(VehicleImageRepository $vehicleImageRepo, string $vehicleId): string
{
    $vehicleId = trim($vehicleId);
    if ($vehicleId === '') {
        return '';
    }

    try {
        $images = $vehicleImageRepo->listByVehicleId($vehicleId);
        if (!$images) {
            return '';
        }

        $first = $images[0];
        $storagePath = trim((string)($first['storage_path'] ?? ''));
        if ($storagePath === '') {
            return '';
        }

        require_once __DIR__ . '/supabase-storage.php';
        $storage = new SupabaseStorage();
        return (string)$storage->getPublicUrl($storagePath);
    } catch (Throwable $e) {
        return '';
    }
}

function driver_send_dispatch_email(array $booking, string $driverNameFallback = '', string $driverPhoneFallback = ''): void
{
    $toEmail = trim((string)($booking['email'] ?? ''));
    if ($toEmail === '' || !filter_var($toEmail, FILTER_VALIDATE_EMAIL)) {
        throw new RuntimeException('Customer email is missing or invalid.');
    }

    $customerName = trim((string)($booking['user_name'] ?? 'Customer'));
    $driverName = trim((string)($booking['driver_name'] ?? $driverNameFallback));
    $driverPhone = trim((string)($booking['driver_phone'] ?? $driverPhoneFallback));
    $pickup = trim((string)($booking['pickup_location'] ?? 'your pickup point'));
    $vehicleName = driver_build_vehicle_name($booking);
    $licensePlate = trim((string)($booking['license_plate'] ?? 'N/A'));

    if ($driverName === '') {
        $driverName = 'Your driver';
    }
    if ($driverPhone === '') {
        $driverPhone = 'Not provided';
    }

    $safeCustomerName = htmlspecialchars($customerName, ENT_QUOTES, 'UTF-8');
    $safeDriverName = htmlspecialchars($driverName, ENT_QUOTES, 'UTF-8');
    $safeDriverPhone = htmlspecialchars($driverPhone, ENT_QUOTES, 'UTF-8');
    $safeVehicleName = htmlspecialchars($vehicleName, ENT_QUOTES, 'UTF-8');
    $safeLicensePlate = htmlspecialchars($licensePlate, ENT_QUOTES, 'UTF-8');
    $safePickup = htmlspecialchars($pickup, ENT_QUOTES, 'UTF-8');

    $subject = 'PrivateHire - Driver is on the way to pickup';
    $htmlBody = "
        <div style='font-family:Inter,Arial,sans-serif;max-width:560px;margin:0 auto;padding:24px;'>
            <h2 style='color:#0f766e;margin-bottom:8px;'>Your trip has started</h2>
            <p style='color:#374151;'>Hi {$safeCustomerName},</p>
            <p style='color:#374151;'>Your driver has started the trip and is moving quickly to your pickup location. Please wait a few minutes.</p>
            <div style='margin:18px 0;padding:16px;background:#f8fafc;border:1px solid #d1fae5;border-radius:10px;'>
                <p style='margin:0 0 8px 0;color:#111827;'><strong>Driver:</strong> {$safeDriverName}</p>
                <p style='margin:0 0 8px 0;color:#111827;'><strong>Phone:</strong> {$safeDriverPhone}</p>
                <p style='margin:0 0 8px 0;color:#111827;'><strong>Vehicle:</strong> {$safeVehicleName}</p>
                <p style='margin:0 0 8px 0;color:#111827;'><strong>License Plate:</strong> {$safeLicensePlate}</p>
                <p style='margin:0;color:#111827;'><strong>Pickup:</strong> {$safePickup}</p>
            </div>
            <p style='color:#6b7280;font-size:13px;'>Thank you for choosing PrivateHire.</p>
        </div>
    ";

    privatehire_send_email($toEmail, $subject, $htmlBody);
}

if ($action === '') {
    driver_json([
        'success' => false,
        'message' => 'Page controller moved to /driver.php.',
        'moved_to' => '/driver.php'
    ], 400);
}

try {
    if ($action === 'get_assigned_vehicle') {
        $vehicle = $vehicleRepo->getAssignedVehicleForDriver($userId);
        if (!$vehicle) {
            driver_json(['success' => true, 'vehicle' => null]);
        }

        $vehicleId = (string)($vehicle['id'] ?? '');
        $vehicleImageUrl = driver_get_vehicle_image_url($vehicleImageRepo, $vehicleId);

        driver_json([
            'success' => true,
            'vehicle' => [
                'id' => $vehicle['id'] ?? null,
                'name' => trim((string)(($vehicle['brand'] ?? '') . ' ' . ($vehicle['model'] ?? '') . ' ' . ($vehicle['year'] ?? ''))),
                'license_plate' => $vehicle['license_plate'] ?? '',
                'brand' => $vehicle['brand'] ?? '',
                'model' => $vehicle['model'] ?? '',
                'year' => $vehicle['year'] ?? '',
                'color' => $vehicle['color'] ?? '',
                'seats' => $vehicle['seats'] ?? null,
                'fuel_type' => $vehicle['fuel_type'] ?? '',
                'service_tier' => $vehicle['service_tier'] ?? '',
                'tier' => $vehicle['service_tier'] ?? '',
                'assigned_date' => $vehicle['assigned_date'] ?? null,
                'image_url' => $vehicleImageUrl,
            ]
        ]);
    }

    if ($action === 'get_current_orders' || $action === 'get_past_orders') {
        $assignedVehicle = $vehicleRepo->getAssignedVehicleForDriver($userId);
        $assignedVehicleId = (string)($assignedVehicle['id'] ?? '');

        $params = [':driver_id' => $userId];
        $vehicleScope = '';
        if ($assignedVehicleId !== '') {
            $vehicleScope = ' OR (b.driver_id IS NULL AND b.vehicle_id = :assigned_vehicle_id)';
            $params[':assigned_vehicle_id'] = $assignedVehicleId;
        }

        $where = $action === 'get_current_orders'
            ? "(COALESCE(at.status, '') <> 'completed' AND b.status <> 'completed')"
            : "(COALESCE(at.status, '') = 'completed' OR b.status = 'completed')";

        $pickupTimeExpr = driver_booking_has_column($pdo, 'pickup_time')
            ? 'b.pickup_time'
            : 'NULL::text AS pickup_time';

        $sql = "
            SELECT
                b.id AS booking_id,
                b.renter_id,
                b.status AS booking_status,
                b.pickup_location,
                b.return_location,
                b.pickup_date,
                {$pickupTimeExpr},
                b.total_amount,
                b.ride_started_at,
                b.ride_completed_at,
                u.full_name AS passenger_name,
                u.avatar_url AS passenger_avatar,
                at.id AS trip_id,
                at.status AS trip_status
            FROM bookings b
            JOIN users u ON u.id = b.renter_id
            LEFT JOIN active_trips at ON at.booking_id = b.id
            WHERE (b.driver_id = :driver_id {$vehicleScope})
              AND {$where}
            ORDER BY
                CASE WHEN :action = 'get_current_orders' THEN b.pickup_date END ASC,
                CASE WHEN :action = 'get_past_orders' THEN COALESCE(b.ride_completed_at, b.pickup_date) END DESC,
                b.created_at DESC
            LIMIT 100
        ";

        $stmt = $pdo->prepare($sql);
        $stmt->bindValue(':driver_id', $params[':driver_id']);
        if (isset($params[':assigned_vehicle_id'])) {
            $stmt->bindValue(':assigned_vehicle_id', $params[':assigned_vehicle_id']);
        }
        $stmt->bindValue(':action', $action);
        $stmt->execute();

        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
        $orders = [];
        foreach ($rows as $row) {
            $canonicalStatus = driver_canonical_status($row);
            if ($action === 'get_current_orders' && $canonicalStatus === 'completed') {
                continue;
            }
            if ($action === 'get_past_orders' && $canonicalStatus !== 'completed') {
                continue;
            }

            $orders[] = [
                'booking_id' => $row['booking_id'],
                'passenger_id' => $row['renter_id'] ?? null,
                'trip_id' => $row['trip_id'] ?? null,
                'passenger_name' => $row['passenger_name'] ?? 'Passenger',
                'passenger_avatar' => $row['passenger_avatar'] ?? '',
                'pickup_location' => $row['pickup_location'] ?? '',
                'destination' => $row['return_location'] ?? '',
                'pickup_date' => $row['pickup_date'] ?? null,
                'pickup_time' => $row['pickup_time'] ?? null,
                'price' => (float)($row['total_amount'] ?? 0),
                'status' => $canonicalStatus,
                'next_status' => driver_next_status($canonicalStatus),
            ];
        }

        driver_json(['success' => true, 'orders' => $orders]);
    }

    if ($action === 'get_passenger_orders') {
        $passengerId = trim((string)($_GET['passenger_id'] ?? $body['passenger_id'] ?? $_POST['passenger_id'] ?? ''));
        if ($passengerId === '') {
            driver_json(['success' => false, 'message' => 'Missing passenger_id'], 400);
        }

        $pickupTimeExpr = driver_booking_has_column($pdo, 'pickup_time')
            ? 'b.pickup_time'
            : 'NULL::text AS pickup_time';

        $stmt = $pdo->prepare("\n            SELECT\n                b.id AS booking_id,\n                b.renter_id,\n                b.status AS booking_status,\n                b.pickup_location,\n                b.return_location,\n                b.pickup_date,\n                {$pickupTimeExpr},\n                b.total_amount,\n                b.ride_started_at,\n                b.ride_completed_at,\n                u.full_name AS passenger_name,\n                u.avatar_url AS passenger_avatar,\n                at.id AS trip_id,\n                at.status AS trip_status\n            FROM bookings b\n            JOIN users u ON u.id = b.renter_id\n            LEFT JOIN active_trips at ON at.booking_id = b.id\n            WHERE b.renter_id = :passenger_id\n              AND b.driver_id = :driver_id\n            ORDER BY COALESCE(b.ride_completed_at, b.pickup_date) DESC, b.created_at DESC\n            LIMIT 50\n        ");
        $stmt->execute([
            ':passenger_id' => $passengerId,
            ':driver_id' => $userId,
        ]);

        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
        $current = [];
        $history = [];

        foreach ($rows as $row) {
            $canonicalStatus = driver_canonical_status($row);
            $entry = [
                'booking_id' => $row['booking_id'],
                'passenger_id' => $row['renter_id'] ?? null,
                'trip_id' => $row['trip_id'] ?? null,
                'passenger_name' => $row['passenger_name'] ?? 'Passenger',
                'passenger_avatar' => $row['passenger_avatar'] ?? '',
                'pickup_location' => $row['pickup_location'] ?? '',
                'destination' => $row['return_location'] ?? '',
                'pickup_date' => $row['pickup_date'] ?? null,
                'pickup_time' => $row['pickup_time'] ?? null,
                'price' => (float)($row['total_amount'] ?? 0),
                'status' => $canonicalStatus,
                'next_status' => driver_next_status($canonicalStatus),
            ];

            if ($canonicalStatus === 'completed') {
                $history[] = $entry;
            } else {
                $current[] = $entry;
            }
        }

        driver_json([
            'success' => true,
            'current_orders' => $current,
            'history_orders' => $history,
        ]);
    }

    if ($action === 'advance_order_status') {
        $bookingId = trim((string)($body['booking_id'] ?? $_POST['booking_id'] ?? ''));
        $targetStatus = strtolower(trim((string)($body['target_status'] ?? $_POST['target_status'] ?? '')));
        if ($bookingId === '') {
            driver_json(['success' => false, 'message' => 'Missing booking_id'], 400);
        }

        $assignedVehicle = $vehicleRepo->getAssignedVehicleForDriver($userId);
        $assignedVehicleId = (string)($assignedVehicle['id'] ?? '');

        $params = [':booking_id' => $bookingId, ':driver_id' => $userId];
        $vehicleScope = '';
        if ($assignedVehicleId !== '') {
            $vehicleScope = ' OR (b.driver_id IS NULL AND b.vehicle_id = :assigned_vehicle_id)';
            $params[':assigned_vehicle_id'] = $assignedVehicleId;
        }

        $sql = "
            SELECT b.id AS booking_id, b.status AS booking_status, b.vehicle_id, b.driver_id,
                   b.ride_started_at, b.ride_completed_at, at.id AS trip_id, at.status AS trip_status
            FROM bookings b
            LEFT JOIN active_trips at ON at.booking_id = b.id
            WHERE b.id = :booking_id AND (b.driver_id = :driver_id {$vehicleScope})
            LIMIT 1
        ";

        $stmt = $pdo->prepare($sql);
        $stmt->bindValue(':booking_id', $bookingId);
        $stmt->bindValue(':driver_id', $userId);
        if (isset($params[':assigned_vehicle_id'])) {
            $stmt->bindValue(':assigned_vehicle_id', $params[':assigned_vehicle_id']);
        }
        $stmt->execute();
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$row) {
            driver_json(['success' => false, 'message' => 'Order not found or not assigned to this driver'], 404);
        }

        $currentStatus = driver_canonical_status($row);
        $nextStatus = driver_next_status($currentStatus);
        if ($nextStatus === null) {
            driver_json(['success' => false, 'message' => 'Order is already completed'], 409);
        }

        if ($targetStatus !== '' && $targetStatus !== $nextStatus) {
            if ($targetStatus === $currentStatus) {
                driver_json(['success' => false, 'message' => 'Duplicate action is not allowed'], 409);
            }
            driver_json(['success' => false, 'message' => 'Invalid status transition'], 409);
        }

        // Enforce one active started trip per driver at a time.
        if ($nextStatus === 'on_trip') {
            $lockSql = "
                SELECT b.id
                FROM bookings b
                LEFT JOIN active_trips at ON at.booking_id = b.id
                WHERE b.id <> :booking_id
                  AND b.driver_id = :driver_id
                  AND (
                    b.status = 'in_progress'
                    OR COALESCE(at.status, '') IN ('on_trip', 'journey_started')
                  )
                  AND COALESCE(b.ride_completed_at::text, '') = ''
                LIMIT 1
            ";

            $lockStmt = $pdo->prepare($lockSql);
            $lockStmt->execute([
                ':booking_id' => $bookingId,
                ':driver_id' => $userId,
            ]);
            $conflict = $lockStmt->fetch(PDO::FETCH_ASSOC);
            if ($conflict) {
                driver_json([
                    'success' => false,
                    'message' => 'You already have a trip in progress. Complete it before starting another trip.'
                ], 409);
            }
        }

        $shouldSendDispatchEmail = false;
        $pdo->beginTransaction();
        try {
            if ($nextStatus === 'on_trip') {
            $shouldSendDispatchEmail = empty($row['ride_started_at']);
                $bookingStmt = $pdo->prepare("UPDATE bookings SET status = 'in_progress', driver_id = :driver_id, ride_started_at = COALESCE(ride_started_at, NOW()) WHERE id = :booking_id");
                $bookingStmt->execute([':driver_id' => $userId, ':booking_id' => $bookingId]);

                if (!empty($row['trip_id'])) {
                    $tripStmt = $pdo->prepare("UPDATE active_trips SET status = 'on_trip', driver_id = :driver_id, journey_started_at = COALESCE(journey_started_at, NOW()), updated_at = NOW() WHERE id = :trip_id");
                    $tripStmt->execute([':driver_id' => $userId, ':trip_id' => $row['trip_id']]);
                }
            }

            if ($nextStatus === 'completed') {
                $bookingStmt = $pdo->prepare("UPDATE bookings SET status = 'completed', driver_id = :driver_id, ride_completed_at = COALESCE(ride_completed_at, NOW()) WHERE id = :booking_id");
                $bookingStmt->execute([':driver_id' => $userId, ':booking_id' => $bookingId]);

                if (!empty($row['trip_id'])) {
                    $tripStmt = $pdo->prepare("UPDATE active_trips SET status = 'completed', driver_id = :driver_id, completed_at = COALESCE(completed_at, NOW()), updated_at = NOW() WHERE id = :trip_id");
                    $tripStmt->execute([':driver_id' => $userId, ':trip_id' => $row['trip_id']]);
                }

                $vehicleId = (string)($row['vehicle_id'] ?? '');
                if ($vehicleId !== '') {
                    $bookingRepo->markVehicleAvailable($vehicleId);
                }
            }

            $pdo->commit();
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            throw $e;
        }

        if ($nextStatus === 'on_trip' && $shouldSendDispatchEmail) {
            try {
                $bookingDetails = $bookingRepo->getBookingWithUserAndDriver($bookingId);
                if ($bookingDetails) {
                    driver_send_dispatch_email(
                        $bookingDetails,
                        (string)($user['full_name'] ?? ''),
                        (string)($user['phone'] ?? '')
                    );

                    $notificationRepo->create([
                        'user_id' => (string)($bookingDetails['renter_id'] ?? ''),
                        'type' => 'dispatch',
                        'title' => '🚗 Driver On Route',
                        'message' => 'Your driver is heading to pickup. Please be ready.',
                        'booking_id' => $bookingId,
                        'data' => [
                            'driver_name' => $bookingDetails['driver_name'] ?? ($user['full_name'] ?? ''),
                            'driver_phone' => $bookingDetails['driver_phone'] ?? ($user['phone'] ?? ''),
                            'vehicle' => driver_build_vehicle_name($bookingDetails),
                            'license_plate' => $bookingDetails['license_plate'] ?? '',
                        ],
                    ]);

                    $renterId = (string)($bookingDetails['renter_id'] ?? '');
                    if ($renterId !== '') {
                        createNotification(
                            $pdo,
                            $renterId,
                            'booking',
                            '🚗 Trip Started',
                            'Your driver has started the trip and is heading to your pickup point.'
                        );
                    }
                }
            } catch (Throwable $mailError) {
                error_log('US17 dispatch email error [booking_id=' . $bookingId . ']: ' . $mailError->getMessage());
            }
        }

        driver_json([
            'success' => true,
            'message' => 'Order status updated',
            'status' => $nextStatus,
        ]);
    }

    if ($action === 'get_notifications') {
        $unreadOnly = filter_var($_GET['unread_only'] ?? false, FILTER_VALIDATE_BOOLEAN);
        $notifications = $notificationRepo->getForDriver($userId, $unreadOnly, 50);
        driver_json(['success' => true, 'notifications' => $notifications]);
    }

    if ($action === 'mark_notification_read') {
        $notificationId = trim((string)($body['notification_id'] ?? $_POST['notification_id'] ?? ''));
        if ($notificationId === '') {
            driver_json(['success' => false, 'message' => 'Missing notification_id'], 400);
        }
        $notificationRepo->markAsRead($notificationId, $userId);
        driver_json(['success' => true, 'message' => 'Notification marked as read']);
    }

    driver_json(['success' => false, 'message' => 'Unknown action: ' . $action], 400);
} catch (Throwable $e) {
    driver_json(['success' => false, 'message' => $e->getMessage()], 500);
}
