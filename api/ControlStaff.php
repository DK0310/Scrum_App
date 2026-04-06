<?php
/**
 * Control Staff Page & API - Private Hire
 * Role scope:
 * - Review booking requests and approve workflow status
 * - Manage fleet vehicles (add/edit/delete)
 */

require_once __DIR__ . '/bootstrap.php';

$bodyJson = api_init(['allow_origin' => '*']);

require_once __DIR__ . '/../Database/db.php';
require_once __DIR__ . '/../config/env.php';
require_once __DIR__ . '/../sql/UserRepository.php';
require_once __DIR__ . '/../sql/BookingRepository.php';
require_once __DIR__ . '/../sql/VehicleRepository.php';
require_once __DIR__ . '/notification-helpers.php';

$userRepo = new UserRepository($pdo);
$bookingRepo = new BookingRepository($pdo);
$vehicleRepo = new VehicleRepository($pdo);

function control_decode_payment_details($raw): array
{
    if (is_array($raw)) {
        return $raw;
    }
    if (is_string($raw) && $raw !== '') {
        $decoded = json_decode($raw, true);
        return is_array($decoded) ? $decoded : [];
    }
    return [];
}

function control_effective_payment_method(array $payment): string
{
    $method = strtolower(trim((string)($payment['method'] ?? '')));
    $details = control_decode_payment_details($payment['payment_details'] ?? []);
    $original = strtolower(trim((string)($details['original_method'] ?? '')));
    return $original !== '' ? $original : $method;
}

function control_refund_account_balance(UserRepository $userRepo, BookingRepository $bookingRepo, string $bookingId, array $booking, array $payment): bool
{
    global $pdo;

    $amount = isset($payment['amount']) ? (float)$payment['amount'] : 0.0;
    if ($amount <= 0) {
        return false;
    }

    $targetUserId = (string)($payment['user_id'] ?? $booking['renter_id'] ?? '');
    if ($targetUserId === '') {
        return false;
    }

    if (!$userRepo->addAccountBalance($targetUserId, $amount)) {
        return false;
    }

    $details = control_decode_payment_details($payment['payment_details'] ?? []);
    $details['provider'] = 'account_balance';
    $details['stage'] = 'refund';
    $details['refunded_amount'] = round($amount, 2);
    $details['refunded_at'] = gmdate('c');

    $bookingRepo->updatePaymentByBookingId($bookingId, 'refunded', $details);

    createNotification(
        $pdo,
        $targetUserId,
        'payment',
        '💷 Refund Processed',
        'A refund of £' . number_format($amount, 2) . ' has been returned to your account balance for booking #' . $bookingId . '.'
    );

    return true;
}

function ensure_vehicle_service_tier_column(PDO $pdo): void
{
    try {
        $pdo->exec("ALTER TABLE vehicles ADD COLUMN IF NOT EXISTS service_tier VARCHAR(20) DEFAULT 'standard'");
    } catch (Throwable $e) {
        // ignore
    }
}

function ensure_vehicle_capacity_column(PDO $pdo): void
{
    try {
        $pdo->exec("ALTER TABLE vehicles ADD COLUMN IF NOT EXISTS capacity INT NULL");
    } catch (Throwable $e) {
        // ignore
    }
}

function control_normalize_role(string $role): string
{
    $normalized = strtolower(trim($role));
    $normalized = str_replace(['-', ' ', '_'], '', $normalized);

    if ($normalized === 'controlstaff' || $normalized === 'staff') {
        return 'controlstaff';
    }

    if ($normalized === 'callcenterstaff') {
        return 'callcenterstaff';
    }

    return $normalized;
}

function control_is_authorized(string $role): bool
{
    $normalized = control_normalize_role($role);
    // Keep legacy staff role by mapping it to controlstaff in normalizer
    return in_array($normalized, ['admin', 'controlstaff'], true);
}

function control_normalize_booking_status(string $status): string
{
    $s = strtolower(trim($status));
    $s = str_replace('-', '_', $s);
    if ($s === 'canceled') {
        return 'cancelled';
    }
    if ($s === 'done') {
        return 'completed';
    }
    return $s;
}

function control_status_rank(string $status): int
{
    $normalized = control_normalize_booking_status($status);
    return match ($normalized) {
        'pending' => 1,
        'confirmed' => 2, // included for reference but Control Staff workflow uses only 1,3,4
        'in_progress' => 3,
        'completed' => 4,
        'cancelled' => 5,
        default => 0,
    };
}

/**
 * Check if a status transition is valid for Control Staff workflow
 * Control Staff only uses: pending -> in_progress -> completed (1 -> 3 -> 4)
 * Does NOT go through confirmed (rank 2)
 */
function control_is_valid_status_transition(int $fromRank, int $toRank): bool
{
    // Control Staff workflow: 1 (pending) -> 3 (in_progress) -> 4 (completed)
    if ($fromRank === 1 && $toRank === 3) return true;  // pending -> in_progress
    if ($fromRank === 3 && $toRank === 4) return true;  // in_progress -> completed
    
    // For future: if confirmed status is used in other workflows, it could be:
    // pending (1) -> confirmed (2) -> in_progress (3) -> completed (4)
    // But for now, Control Staff skips confirmed
    
    return false;
}

/**
 * Resolve canonical status to actual enum label in DB.
 * Supports older DBs that may still use "in-progress".
 */
function control_resolve_db_booking_status(PDO $pdo, string $canonicalStatus): string
{
    $canonical = control_normalize_booking_status($canonicalStatus);

    $stmt = $pdo->query("\n        SELECT e.enumlabel\n        FROM pg_type t\n        JOIN pg_enum e ON t.oid = e.enumtypid\n        WHERE t.typname = 'booking_status'\n        ORDER BY e.enumsortorder\n    ");
    $labels = $stmt ? $stmt->fetchAll(PDO::FETCH_COLUMN) : [];
    $labels = array_map(static fn($v) => (string)$v, $labels);

    if (empty($labels)) {
        return $canonical;
    }

    $candidates = match ($canonical) {
        'pending' => ['pending'],
        'in_progress' => ['in_progress', 'in-progress'],
        'completed' => ['completed', 'done'],
        default => [$canonical],
    };

    foreach ($candidates as $candidate) {
        if (in_array($candidate, $labels, true)) {
            return $candidate;
        }
    }

    throw new Exception('Status is not supported by current booking_status enum');
}

function control_driver_dispatch_status(array $driver): string
{
    $assignedVehicleId = trim((string)($driver['assigned_vehicle_id'] ?? ''));
    return $assignedVehicleId === '' ? 'pending' : 'dispatched';
}

function control_get_drivers_with_status(UserRepository $userRepo, string $statusFilter = 'all'): array
{
    $drivers = $userRepo->getDriversWithVehicles();

    $drivers = array_map(static function (array $driver): array {
        $status = control_driver_dispatch_status($driver);
        $driver['status'] = $status;
        return $driver;
    }, $drivers);

    if ($statusFilter === 'pending' || $statusFilter === 'dispatched') {
        $drivers = array_values(array_filter($drivers, static function (array $driver) use ($statusFilter): bool {
            return (($driver['status'] ?? 'pending') === $statusFilter);
        }));
    }

    return $drivers;
}

function control_get_available_vehicles(PDO $pdo): array
{
    $stmt = $pdo->query("\n        SELECT v.id, v.brand, v.model, v.license_plate, v.service_tier, v.status\n        FROM vehicles v\n        LEFT JOIN users u ON u.assigned_vehicle_id = v.id AND u.role = 'driver' AND u.is_active = true\n        WHERE v.status = 'available'\n          AND u.id IS NULL\n        ORDER BY v.brand ASC, v.model ASC\n    ");

    return $stmt ? $stmt->fetchAll(PDO::FETCH_ASSOC) : [];
}

/**
 * @return array<int,string>
 */
function control_get_in_progress_vehicle_ids(PDO $pdo): array
{
    $stmt = $pdo->query("\n        SELECT DISTINCT b.vehicle_id::text AS vehicle_id\n        FROM bookings b\n        WHERE b.vehicle_id IS NOT NULL\n          AND REPLACE(LOWER(b.status::text), '-', '_') = 'in_progress'\n    ");
    if (!$stmt) {
        return [];
    }
    $rows = $stmt->fetchAll(PDO::FETCH_COLUMN);
    return array_values(array_filter(array_map('strval', $rows)));
}

function control_vehicle_column_exists(PDO $pdo, string $columnName): bool
{
    $stmt = $pdo->prepare("\n        SELECT 1\n        FROM information_schema.columns\n        WHERE table_schema = current_schema()\n          AND table_name = 'vehicles'\n          AND column_name = ?\n        LIMIT 1\n    ");
    $stmt->execute([$columnName]);
    return (bool)$stmt->fetchColumn();
}

function control_pick_random_driver_vehicle(PDO $pdo): ?array
{
    $priceExpr = control_vehicle_column_exists($pdo, 'price_per_day')
        ? 'v.price_per_day AS price_per_day'
        : 'NULL::numeric AS price_per_day';

    $stmt = $pdo->query("\n        SELECT\n            u.id AS driver_id,\n            u.full_name AS driver_name,\n            u.assigned_vehicle_id AS vehicle_id,\n            v.owner_id AS vehicle_owner_id,\n            v.brand,\n            v.model,\n            v.license_plate,\n            v.service_tier,\n            {$priceExpr}\n        FROM users u\n        JOIN vehicles v ON v.id = u.assigned_vehicle_id\n        LEFT JOIN bookings b ON b.driver_id = u.id AND b.status IN ('pending', 'confirmed', 'in_progress')\n        WHERE u.role = 'driver'\n          AND u.is_active = true\n          AND u.assigned_vehicle_id IS NOT NULL\n          AND v.status = 'available'\n          AND b.id IS NULL\n        ORDER BY RANDOM()\n        LIMIT 1\n    ");

    $row = $stmt ? $stmt->fetch(PDO::FETCH_ASSOC) : false;
    return $row ?: null;
}

function control_vehicle_is_locked_in_progress(PDO $pdo, string $vehicleId): bool
{
    if ($vehicleId === '') {
        return false;
    }

    $stmt = $pdo->prepare("\n        SELECT 1\n        FROM bookings b\n        WHERE b.vehicle_id = ?\n          AND REPLACE(LOWER(b.status::text), '-', '_') = 'in_progress'\n        LIMIT 1\n    ");
    $stmt->execute([$vehicleId]);
    return (bool)$stmt->fetchColumn();
}

if (!isset($_SESSION['user_id'])) {
    header('Location: /index.php');
    exit;
}

$userId = (string)$_SESSION['user_id'];
$userRole = (string)($userRepo->getUserRole($userId) ?? ($_SESSION['role'] ?? ''));

if (!control_is_authorized($userRole)) {
    http_response_code(403);
    header('Location: /index.php');
    exit;
}

$action = api_action($bodyJson);

if ($action === '') {
    api_json([
        'success' => false,
        'message' => 'Page controller moved to /control-staff.php.',
        'moved_to' => '/control-staff.php'
    ]);
    exit;
}

try {
    if ($action === 'get_orders') {
        $limit = (int)($_GET['limit'] ?? 100);
        $limit = max(1, min($limit, 300));
        $status = trim((string)($_GET['status'] ?? ''));

        $orders = $bookingRepo->listAll($limit, 0);
        $orders = array_map(static function ($o) {
            $customerName = trim((string)($o['customer_name'] ?? ''));
            if ($customerName === '') {
                $customerName = trim((string)($o['user_name'] ?? ''));
            }
            if ($customerName === '') {
                $customerName = 'Customer';
            }
            $o['customer_name'] = $customerName;
            $o['status'] = control_normalize_booking_status((string)($o['status'] ?? 'pending'));
            return $o;
        }, $orders);
        if ($status !== '' && $status !== 'all') {
            $status = control_normalize_booking_status($status);
            $orders = array_values(array_filter($orders, static function ($o) use ($status) {
                return (control_normalize_booking_status((string)($o['status'] ?? '')) === $status);
            }));
        }

        echo json_encode(['success' => true, 'orders' => $orders]);
        exit;
    }

    if ($action === 'get_order') {
        $orderId = trim((string)($_GET['order_id'] ?? ''));
        if ($orderId === '') {
            throw new Exception('Missing order_id');
        }

        $order = $bookingRepo->getBookingWithUserAndDriver($orderId);
        if (!$order) {
            throw new Exception('Order not found');
        }

        $order['customer_name'] = trim((string)($order['customer_name'] ?? $order['user_name'] ?? 'Customer'));

        echo json_encode(['success' => true, 'order' => $order]);
        exit;
    }

    if ($action === 'update_order_status') {
        $bookingId = trim((string)($bodyJson['booking_id'] ?? $_POST['booking_id'] ?? ''));
        $newStatus = trim((string)($bodyJson['status'] ?? $_POST['status'] ?? ''));

        if ($bookingId === '' || $newStatus === '') {
            throw new Exception('Missing booking_id or status');
        }

        $requestedStatus = control_normalize_booking_status($newStatus);

        // Control Staff only confirms orders (pending -> in_progress).
        // Driver handles completion via advance_order_status.
        if ($requestedStatus !== 'in_progress') {
            throw new Exception('Control Staff can only confirm orders (pending → in_progress). Driver completes orders.');
        }

        $booking = $bookingRepo->getById($bookingId);
        if (!$booking) {
            throw new Exception('Booking not found');
        }

        $currentStatus = control_normalize_booking_status((string)($booking['status'] ?? 'pending'));
        if ($currentStatus === $requestedStatus) {
            echo json_encode(['success' => true, 'message' => 'Order status unchanged']);
            exit;
        }

        $currentRank = control_status_rank($currentStatus);
        $requestedRank = control_status_rank($requestedStatus);
        
        if ($currentRank > 0 && $requestedRank > 0 && !control_is_valid_status_transition($currentRank, $requestedRank)) {
            throw new Exception('Invalid transition from ' . $currentStatus . ' to ' . $requestedStatus . '. Control Staff workflow: pending → in_progress → completed.');
        }

        $dbStatus = control_resolve_db_booking_status($pdo, $requestedStatus);

        // Assign a random dispatched driver+vehicle when order starts (in_progress)
        $effectiveVehicleId = (string)($booking['vehicle_id'] ?? '');
        $effectiveDriverId = (string)($booking['driver_id'] ?? '');

        if ($requestedStatus === 'in_progress') {
            $renterId = (string)($booking['renter_id'] ?? '');
            if ($renterId === '') {
                throw new Exception('Booking is missing renter information');
            }

            $dispatch = control_pick_random_driver_vehicle($pdo);
            if (!$dispatch) {
                throw new Exception('No dispatched driver with available vehicle can be assigned right now');
            }

            $assignedVehicleId = (string)($dispatch['vehicle_id'] ?? '');
            $assignedOwnerId = (string)($dispatch['vehicle_owner_id'] ?? '');
            $assignedDriverId = (string)($dispatch['driver_id'] ?? '');
            $assignedPricePerDay = (float)($dispatch['price_per_day'] ?? 0);
            if ($assignedPricePerDay <= 0) {
                $tier = strtolower(trim((string)($dispatch['service_tier'] ?? 'standard')));
                $tierPriceMap = [
                    'eco' => 40.0,
                    'standard' => 80.0,
                    'luxury' => 150.0,
                ];
                $assignedPricePerDay = $tierPriceMap[$tier] ?? 80.0;
            }

            if ($assignedVehicleId === '' || $assignedOwnerId === '' || $assignedDriverId === '') {
                throw new Exception('Unable to assign driver and vehicle for this order');
            }

            $assigned = $bookingRepo->assignVehicleToBooking(
                $bookingId,
                $assignedVehicleId,
                $assignedOwnerId,
                $assignedPricePerDay
            );
            if (!$assigned) {
                throw new Exception('Unable to assign vehicle to order');
            }

            $driverStmt = $pdo->prepare("UPDATE bookings SET driver_id = ? WHERE id = ?");
            $driverStmt->execute([$assignedDriverId, $bookingId]);

            $tripIdStmt = $pdo->prepare("SELECT id FROM active_trips WHERE booking_id = ? LIMIT 1");
            $tripIdStmt->execute([$bookingId]);
            $activeTrip = $tripIdStmt->fetch(PDO::FETCH_ASSOC);

            if ($activeTrip && !empty($activeTrip['id'])) {
                $updateTripStmt = $pdo->prepare("\n                    UPDATE active_trips\n                    SET driver_id = ?,\n                        vehicle_id = ?,\n                        status = 'on_route',\n                        driver_accepted_at = COALESCE(driver_accepted_at, NOW()),\n                        updated_at = NOW()\n                    WHERE id = ?\n                ");
                $updateTripStmt->execute([$assignedDriverId, $assignedVehicleId, $activeTrip['id']]);
            } else {
                $insertTripStmt = $pdo->prepare("\n                    INSERT INTO active_trips (\n                        booking_id, user_id, driver_id, vehicle_id, status, created_at, updated_at, driver_accepted_at\n                    ) VALUES (?, ?, ?, ?, 'on_route', NOW(), NOW(), NOW())\n                ");
                $insertTripStmt->execute([$bookingId, $renterId, $assignedDriverId, $assignedVehicleId]);
            }

            $driverMessage = 'New order assigned. Pickup: ' . (string)($booking['pickup_location'] ?? '-')
                . ' | Destination: ' . (string)($booking['return_location'] ?? '-')
                . ' | Passenger count: ' . (string)($booking['number_of_passengers'] ?? '1');
            $bookingRepo->createDriverNotification(
                $assignedDriverId,
                $bookingId,
                'New Assigned Order',
                $driverMessage,
                'dispatch_assignment'
            );

            $effectiveVehicleId = $assignedVehicleId;
            $effectiveDriverId = $assignedDriverId;
        }

        $ok = $bookingRepo->updateStatus($bookingId, $dbStatus);
        if (!$ok) {
            throw new Exception('Unable to update status');
        }

        if ($effectiveDriverId !== '' && !($requestedStatus === 'in_progress' && $currentStatus === 'pending')) {
            $statusLabel = str_replace('_', ' ', $requestedStatus);
            $bookingRepo->createDriverNotification(
                $effectiveDriverId,
                $bookingId,
                'Order Status Updated',
                'Order #' . $bookingId . ' has been updated to ' . $statusLabel . ' by Control Staff.',
                'order_status_update'
            );
        }

        $vehicleId = $effectiveVehicleId;
        $isMinicabBooking = strtolower(trim((string)($booking['booking_type'] ?? ''))) === 'minicab';
        if ($vehicleId !== '') {
            if ($requestedStatus === 'in_progress') {
                if (!$isMinicabBooking) {
                    $bookingRepo->markVehicleRented($vehicleId);
                }
            }
        }

        if ($requestedStatus === 'in_progress' && $currentStatus === 'pending') {
            try {
                require_once __DIR__ . '/../Invoice/invoice_mpdf.php';
                require_once __DIR__ . '/../Invoice/mailer.php';

                $invoiceData = $bookingRepo->getInvoiceData($bookingId);
                if ($invoiceData && !empty($invoiceData['renter_email'])) {
                    $invoiceBooking = [
                        'id' => $invoiceData['id'] ?? $bookingId,
                        'created_at' => $invoiceData['created_at'] ?? null,
                        'booking_type' => $invoiceData['booking_type'] ?? 'minicab',
                        'pickup_location' => $invoiceData['pickup_location'] ?? '',
                        'return_location' => $invoiceData['return_location'] ?? '',
                        'pickup_date' => $invoiceData['pickup_date'] ?? '',
                        'pickup_time' => $invoiceData['pickup_time'] ?? '',
                        'return_date' => $invoiceData['return_date'] ?? null,
                        'total_days' => $invoiceData['total_days'] ?? 1,
                        'price_per_day' => $invoiceData['price_per_day'] ?? 0,
                        'subtotal' => $invoiceData['subtotal'] ?? 0,
                        'discount_amount' => $invoiceData['discount_amount'] ?? 0,
                        'promo_code' => $invoiceData['promo_code'] ?? '',
                        'total_amount' => $invoiceData['total_amount'] ?? 0,
                        'payment_method' => $invoiceData['payment_method'] ?? 'cash',
                        'renter_name' => $invoiceData['renter_name'] ?? '',
                        'renter_email' => $invoiceData['renter_email'] ?? '',
                        'vehicle_name' => trim(((string)($invoiceData['brand'] ?? '')) . ' ' . ((string)($invoiceData['model'] ?? ''))),
                        'license_plate' => $invoiceData['license_plate'] ?? '',
                    ];

                    $pdf = privatehire_generate_invoice_pdf_local($invoiceBooking);
                    $subject = 'Invoice - Booking #' . ($invoiceData['id'] ?? $bookingId);
                    $body = 'Hello ' . ($invoiceData['renter_name'] ?? 'Customer') . ",\n\nYour booking has been confirmed and is now in progress. Please find your invoice attached.\n\nThank you.";

                    privatehire_send_email(
                        (string)$invoiceData['renter_email'],
                        $subject,
                        nl2br(htmlspecialchars($body, ENT_QUOTES, 'UTF-8')),
                        [
                            'filename' => 'invoice-' . ($invoiceData['id'] ?? $bookingId) . '.pdf',
                            'mime' => 'application/pdf',
                            'content' => $pdf,
                        ]
                    );
                }
            } catch (Exception $mailErr) {
                error_log('ControlStaff in_progress invoice send failed: ' . $mailErr->getMessage());
            }

            $renterId = (string)($booking['renter_id'] ?? '');
            if ($renterId !== '') {
                createNotification(
                    $pdo,
                    $renterId,
                    'booking',
                    '🚗 Trip Confirmed By Staff',
                    'Your order has been confirmed by staff and your trip is now in progress.'
                );
            }
        }

        echo json_encode(['success' => true, 'message' => 'Order status updated']);
        exit;
    }

    if ($action === 'reject_order') {
        $bookingId = trim((string)($bodyJson['booking_id'] ?? $_POST['booking_id'] ?? ''));
        if ($bookingId === '') {
            throw new Exception('Missing booking_id');
        }

        $booking = $bookingRepo->getById($bookingId);
        if (!$booking) {
            throw new Exception('Order not found');
        }

        $currentStatus = control_normalize_booking_status((string)($booking['status'] ?? 'pending'));
        if ($currentStatus !== 'pending') {
            throw new Exception('Only pending orders can be rejected');
        }

        $payment = $bookingRepo->getPaymentByBookingId($bookingId);
        if ($payment) {
            $paymentMethod = control_effective_payment_method($payment);
            $paymentStatus = strtolower(trim((string)($payment['status'] ?? '')));
            if ($paymentMethod === 'account_balance' && $paymentStatus === 'paid') {
                $okRefund = control_refund_account_balance($userRepo, $bookingRepo, $bookingId, $booking, $payment);
                if (!$okRefund) {
                    throw new Exception('Unable to refund account balance for this order');
                }
            }
        }

        $cancelledDbStatus = control_resolve_db_booking_status($pdo, 'cancelled');
        $ok = $bookingRepo->updateStatus($bookingId, $cancelledDbStatus);
        if (!$ok) {
            throw new Exception('Unable to reject order');
        }

        $bookingRepo->unassignVehicleFromBooking($bookingId);

        $vehicleId = (string)($booking['vehicle_id'] ?? '');
        $isMinicabBooking = strtolower(trim((string)($booking['booking_type'] ?? ''))) === 'minicab';
        if ($vehicleId !== '' && !$isMinicabBooking) {
            $bookingRepo->markVehicleAvailable($vehicleId);
        }

        $renterId = (string)($booking['renter_id'] ?? '');
        if ($renterId !== '') {
            createNotification(
                $pdo,
                $renterId,
                'alert',
                '❌ Booking Rejected',
                'Your booking request was rejected by staff.'
            );
        }

        echo json_encode(['success' => true, 'message' => 'Order rejected']);
        exit;
    }

    if ($action === 'get_vehicles') {
        ensure_vehicle_service_tier_column($pdo);
        $vehicles = $vehicleRepo->listAll();

        $lockedVehicleIds = array_flip(control_get_in_progress_vehicle_ids($pdo));
        $vehicles = array_map(static function (array $vehicle) use ($lockedVehicleIds): array {
            $vid = (string)($vehicle['id'] ?? '');
            $locked = isset($lockedVehicleIds[$vid]);
            $vehicle['is_locked_in_progress'] = $locked;
            $vehicle['luggage_capacity_lbs'] = (isset($vehicle['capacity']) && is_numeric($vehicle['capacity']) && (int)$vehicle['capacity'] > 0)
                ? (int)$vehicle['capacity']
                : null;
            $vehicle['lock_reason'] = $locked
                ? 'Vehicle is currently assigned to an in-progress order. Complete that order before editing or deleting this vehicle.'
                : '';
            return $vehicle;
        }, $vehicles);

        echo json_encode(['success' => true, 'vehicles' => $vehicles]);
        exit;
    }

    if ($action === 'get_drivers') {
        $statusFilter = strtolower(trim((string)($_GET['status'] ?? 'all')));
        if (!in_array($statusFilter, ['all', 'pending', 'dispatched'], true)) {
            $statusFilter = 'all';
        }

        $drivers = control_get_drivers_with_status($userRepo, $statusFilter);
        $lockedVehicleIds = array_flip(control_get_in_progress_vehicle_ids($pdo));
        $drivers = array_map(static function (array $driver) use ($lockedVehicleIds): array {
            $assignedVehicleId = trim((string)($driver['assigned_vehicle_id'] ?? ''));
            $isLocked = $assignedVehicleId !== '' && isset($lockedVehicleIds[$assignedVehicleId]);
            $driver['can_unassign'] = !$isLocked;
            $driver['unassign_lock_reason'] = $isLocked
                ? 'Cannot unassign driver while the assigned vehicle is serving an in-progress order.'
                : '';
            $driver['service_state'] = $isLocked ? 'on_service' : 'free';
            return $driver;
        }, $drivers);

        echo json_encode(['success' => true, 'drivers' => $drivers]);
        exit;
    }

    if ($action === 'get_available_vehicles') {
        $vehicles = control_get_available_vehicles($pdo);
        echo json_encode(['success' => true, 'vehicles' => $vehicles]);
        exit;
    }

    if ($action === 'dispatch_driver') {
        $driverId = trim((string)($bodyJson['driver_id'] ?? $_POST['driver_id'] ?? ''));
        $vehicleId = trim((string)($bodyJson['vehicle_id'] ?? $_POST['vehicle_id'] ?? ''));

        if ($driverId === '' || $vehicleId === '') {
            throw new Exception('Missing driver_id or vehicle_id');
        }

        $driver = $userRepo->findById($driverId);
        if (!$driver || (string)($driver['role'] ?? '') !== 'driver' || !((bool)($driver['is_active'] ?? false))) {
            throw new Exception('Driver is not available for dispatch');
        }

        if (trim((string)($driver['assigned_vehicle_id'] ?? '')) !== '') {
            throw new Exception('Driver is already dispatched');
        }

        $vehicle = $vehicleRepo->getById($vehicleId);
        if (!$vehicle || (string)($vehicle['status'] ?? '') !== 'available') {
            throw new Exception('Vehicle is not available');
        }

        $assignedDriverStmt = $pdo->prepare("SELECT id FROM users WHERE assigned_vehicle_id = ? AND role = 'driver' AND is_active = true LIMIT 1");
        $assignedDriverStmt->execute([$vehicleId]);
        if ($assignedDriverStmt->fetch(PDO::FETCH_ASSOC)) {
            throw new Exception('Vehicle is already dispatched to another driver');
        }

        $ok = $vehicleRepo->assignToDriver($userId, $driverId, $vehicleId, date('Y-m-d'));
        if (!$ok) {
            throw new Exception('Unable to dispatch driver');
        }

        echo json_encode(['success' => true, 'message' => 'Driver dispatched successfully']);
        exit;
    }

    if ($action === 'unassign_driver') {
        $driverId = trim((string)($bodyJson['driver_id'] ?? $_POST['driver_id'] ?? ''));
        if ($driverId === '') {
            throw new Exception('Missing driver_id');
        }

        $driver = $userRepo->findById($driverId);
        if (!$driver || (string)($driver['role'] ?? '') !== 'driver') {
            throw new Exception('Driver not found');
        }

        $assignedVehicleId = trim((string)($driver['assigned_vehicle_id'] ?? ''));
        if ($assignedVehicleId === '') {
            throw new Exception('Driver is already pending');
        }

        if (control_vehicle_is_locked_in_progress($pdo, $assignedVehicleId)) {
            throw new Exception('Cannot unassign driver while the assigned vehicle is linked to an in-progress order');
        }

        $clearDriverStmt = $pdo->prepare("UPDATE users SET assigned_vehicle_id = NULL, assigned_vehicle_assigned_at = NULL WHERE id = ?");
        $clearDriverStmt->execute([$driverId]);

        $closeAssignmentStmt = $pdo->prepare("UPDATE vehicle_assignments SET unassigned_at = NOW() WHERE driver_id = ? AND unassigned_at IS NULL");
        $closeAssignmentStmt->execute([$driverId]);

        echo json_encode(['success' => true, 'message' => 'Driver unassigned successfully']);
        exit;
    }

    if ($action === 'add_vehicle') {
        ensure_vehicle_service_tier_column($pdo);
        ensure_vehicle_capacity_column($pdo);
        $payload = is_array($bodyJson) ? $bodyJson : $_POST;

        $brand = trim((string)($payload['brand'] ?? ''));
        $model = trim((string)($payload['model'] ?? ''));
        $year = (int)($payload['year'] ?? 0);
        $licensePlate = trim((string)($payload['license_plate'] ?? ''));
        $rawImageIds = $payload['image_ids'] ?? [];
        $imageIds = is_array($rawImageIds) ? array_values(array_filter(array_map('strval', $rawImageIds))) : [];
        $serviceTier = strtolower(trim((string)($payload['service_tier'] ?? 'standard')));
        if (!in_array($serviceTier, ['eco', 'standard', 'luxury'], true)) {
            throw new Exception('Invalid vehicle tier. Use eco, standard, or luxury');
        }

        $priceMap = ['eco' => 40.0, 'standard' => 80.0, 'luxury' => 150.0];
        $pricePerDay = isset($payload['price_per_day'])
            ? (float)$payload['price_per_day']
            : (float)$priceMap[$serviceTier];

        if ($brand === '' || $model === '' || $year < 1990 || $licensePlate === '' || $pricePerDay <= 0) {
            throw new Exception('Missing required fields for vehicle');
        }

        $seats = (int)($payload['seats'] ?? 5);
        if (!in_array($seats, [5, 7], true)) {
            throw new Exception('Seats must be 5 or 7.');
        }

        $luggageCapacityLbsRaw = $payload['luggage_capacity_lbs'] ?? ($payload['capacity'] ?? null);
        $luggageCapacityLbs = (is_numeric($luggageCapacityLbsRaw) && (int)$luggageCapacityLbsRaw > 0)
            ? (int)$luggageCapacityLbsRaw
            : null;

        $vehicleId = $vehicleRepo->create([
            'added_by_staff_id' => $userId,
            'brand' => $brand,
            'model' => $model,
            'year' => $year,
            'license_plate' => $licensePlate,
            'category' => trim((string)($payload['category'] ?? 'sedan')),
            'seats' => $seats,
            'capacity' => $luggageCapacityLbs,
            'color' => trim((string)($payload['color'] ?? '')),
            'price_per_day' => $pricePerDay,
            'service_tier' => $serviceTier,
            'price_per_week' => isset($payload['price_per_week']) ? (float)$payload['price_per_week'] : null,
            'price_per_month' => isset($payload['price_per_month']) ? (float)$payload['price_per_month'] : null,
            'location_city' => trim((string)($payload['location_city'] ?? '')),
            'location_address' => trim((string)($payload['location_address'] ?? '')),
            'transmission' => trim((string)($payload['transmission'] ?? 'automatic')),
            'fuel_type' => trim((string)($payload['fuel_type'] ?? 'petrol')),
            'engine_size' => trim((string)($payload['engine_size'] ?? '')),
            'consumption' => trim((string)($payload['consumption'] ?? '')),
        ]);

        if (!empty($imageIds)) {
            $vehicleRepo->linkImagesToVehicle($vehicleId, $imageIds);
        }

        echo json_encode(['success' => true, 'message' => 'Vehicle added', 'vehicle_id' => $vehicleId]);
        exit;
    }

    if ($action === 'edit_vehicle') {
        ensure_vehicle_service_tier_column($pdo);
        ensure_vehicle_capacity_column($pdo);
        $payload = is_array($bodyJson) ? $bodyJson : $_POST;

        $vehicleId = trim((string)($payload['vehicle_id'] ?? ''));
        if ($vehicleId === '') {
            throw new Exception('Missing vehicle_id');
        }

        $vehicle = $vehicleRepo->getById($vehicleId);
        if (!$vehicle) {
            throw new Exception('Vehicle not found');
        }

        if (control_vehicle_is_locked_in_progress($pdo, $vehicleId)) {
            throw new Exception('Cannot edit vehicle while it is linked to an in-progress order');
        }

        $fields = [];
        $editable = [
            'brand', 'model', 'year', 'license_plate', 'category', 'transmission', 'fuel_type',
            'seats', 'capacity', 'color', 'engine_size', 'consumption', 'price_per_day', 'price_per_week',
            'price_per_month', 'location_city', 'location_address', 'status', 'service_tier'
        ];

        foreach ($editable as $f) {
            if (array_key_exists($f, $payload)) {
                $fields[$f] = $payload[$f];
            }
        }

        if (array_key_exists('seats', $fields)) {
            $seats = (int)$fields['seats'];
            if (!in_array($seats, [5, 7], true)) {
                throw new Exception('Seats must be 5 or 7.');
            }
            $fields['seats'] = $seats;
        }

        if (array_key_exists('luggage_capacity_lbs', $payload)) {
            $raw = $payload['luggage_capacity_lbs'];
            $fields['capacity'] = (is_numeric($raw) && (int)$raw > 0) ? (int)$raw : null;
        }

        if (empty($fields)) {
            throw new Exception('No editable fields provided');
        }

        $updated = $vehicleRepo->updateVehicle($vehicleId, (string)$vehicle['owner_id'], $fields);

        echo json_encode(['success' => true, 'message' => 'Vehicle updated', 'vehicle' => $updated]);
        exit;
    }

    if ($action === 'delete_vehicle') {
        $vehicleId = trim((string)($bodyJson['vehicle_id'] ?? $_POST['vehicle_id'] ?? ''));
        if ($vehicleId === '') {
            throw new Exception('Missing vehicle_id');
        }

        if (control_vehicle_is_locked_in_progress($pdo, $vehicleId)) {
            throw new Exception('Cannot delete vehicle while it is linked to an in-progress order');
        }

        $ok = $vehicleRepo->deleteVehicleAdmin($vehicleId);
        if (!$ok) {
            throw new Exception('Unable to delete vehicle');
        }

        echo json_encode(['success' => true, 'message' => 'Vehicle deleted']);
        exit;
    }

    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Unknown action']);
} catch (Exception $e) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
