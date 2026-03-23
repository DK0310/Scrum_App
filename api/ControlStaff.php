<?php
/**
 * Control Staff Page & API - Private Hire
 * Role scope:
 * - Review booking requests and approve workflow status
 * - Manage fleet vehicles (add/edit/delete)
 */

session_start();

require_once __DIR__ . '/../Database/db.php';
require_once __DIR__ . '/../config/env.php';
require_once __DIR__ . '/../sql/UserRepository.php';
require_once __DIR__ . '/../sql/BookingRepository.php';
require_once __DIR__ . '/../sql/VehicleRepository.php';

$userRepo = new UserRepository($pdo);
$bookingRepo = new BookingRepository($pdo);
$vehicleRepo = new VehicleRepository($pdo);

function ensure_vehicle_service_tier_column(PDO $pdo): void
{
    try {
        $pdo->exec("ALTER TABLE vehicles ADD COLUMN IF NOT EXISTS service_tier VARCHAR(20) DEFAULT 'standard'");
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

$rawBody = file_get_contents('php://input');
$bodyJson = null;
if (is_string($rawBody) && trim($rawBody) !== '') {
    $bodyJson = json_decode($rawBody, true);
}
if (!is_array($bodyJson)) {
    $bodyJson = $_POST ?? [];
}
$action = $_GET['action'] ?? $_POST['action'] ?? ($bodyJson['action'] ?? '');

// Page mode
if ($action === '') {
    $title = 'Control Staff - Private Hire';
    $currentPage = 'control-staff';
    $isLoggedIn = true;
    $currentUser = $_SESSION['full_name'] ?? $_SESSION['username'] ?? $_SESSION['email'] ?? 'Control Staff';

    require __DIR__ . '/../templates/ControlStaff.html.php';
    exit;
}

header('Content-Type: application/json');

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

        $allowed = ['pending', 'in_progress', 'completed'];
        if (!in_array($requestedStatus, $allowed, true)) {
            throw new Exception('Invalid status. Use: pending, in_progress, completed');
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

        // Assign a random vehicle by tier only when order starts (in_progress)
        $effectiveVehicleId = (string)($booking['vehicle_id'] ?? '');

        if ($requestedStatus === 'in_progress') {
            $rideTier = strtolower(trim((string)($booking['ride_tier'] ?? 'standard')));
            if (!in_array($rideTier, ['eco', 'standard', 'premium'], true)) {
                $rideTier = 'standard';
            }

            $renterId = (string)($booking['renter_id'] ?? '');
            if ($renterId === '') {
                throw new Exception('Booking is missing renter information');
            }

            $assignedVehicle = $bookingRepo->findVehicleForTier($rideTier, $renterId);
            if (!$assignedVehicle) {
                throw new Exception('No available vehicle found for the selected ride tier');
            }

            $assignedVehicleId = (string)($assignedVehicle['id'] ?? '');
            $assignedOwnerId = (string)($assignedVehicle['owner_id'] ?? '');
            if ($assignedVehicleId === '' || $assignedOwnerId === '') {
                throw new Exception('Unable to assign vehicle for this order');
            }

            $assigned = $bookingRepo->assignVehicleToBooking(
                $bookingId,
                $assignedVehicleId,
                $assignedOwnerId,
                (float)($assignedVehicle['price_per_day'] ?? 0)
            );
            if (!$assigned) {
                throw new Exception('Unable to assign vehicle to order');
            }

            $effectiveVehicleId = $assignedVehicleId;
        }

        $ok = $bookingRepo->updateStatus($bookingId, $dbStatus);
        if (!$ok) {
            throw new Exception('Unable to update status');
        }

        $vehicleId = $effectiveVehicleId;
        if ($vehicleId !== '') {
            if ($requestedStatus === 'in_progress') {
                $bookingRepo->markVehicleRented($vehicleId);
            }
            if ($requestedStatus === 'completed') {
                $bookingRepo->markVehicleAvailable($vehicleId);
            }
        }

        if ($requestedStatus === 'in_progress' && $currentStatus === 'pending') {
            try {
                require_once __DIR__ . '/../invoice/invoice_mpdf.php';
                require_once __DIR__ . '/../invoice/mailer.php';

                $invoiceData = $bookingRepo->getInvoiceData($bookingId);
                if ($invoiceData && !empty($invoiceData['renter_email'])) {
                    $invoiceBooking = [
                        'id' => $invoiceData['id'] ?? $bookingId,
                        'created_at' => $invoiceData['created_at'] ?? null,
                        'booking_type' => $invoiceData['booking_type'] ?? 'minicab',
                        'pickup_location' => $invoiceData['pickup_location'] ?? '',
                        'return_location' => $invoiceData['return_location'] ?? '',
                        'pickup_date' => $invoiceData['pickup_date'] ?? '',
                        'return_date' => $invoiceData['return_date'] ?? null,
                        'total_days' => $invoiceData['total_days'] ?? 1,
                        'price_per_day' => $invoiceData['price_per_day'] ?? 0,
                        'subtotal' => $invoiceData['subtotal'] ?? 0,
                        'discount_amount' => $invoiceData['discount_amount'] ?? 0,
                        'total_amount' => $invoiceData['total_amount'] ?? 0,
                        'payment_method' => $invoiceData['payment_method'] ?? 'cash',
                        'renter_name' => $invoiceData['renter_name'] ?? '',
                        'renter_email' => $invoiceData['renter_email'] ?? '',
                        'vehicle_brand' => $invoiceData['brand'] ?? '',
                        'vehicle_model' => $invoiceData['model'] ?? '',
                        'vehicle_license_plate' => $invoiceData['license_plate'] ?? '',
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

        $cancelledDbStatus = control_resolve_db_booking_status($pdo, 'cancelled');
        $ok = $bookingRepo->updateStatus($bookingId, $cancelledDbStatus);
        if (!$ok) {
            throw new Exception('Unable to reject order');
        }

        $bookingRepo->unassignVehicleFromBooking($bookingId);

        $vehicleId = (string)($booking['vehicle_id'] ?? '');
        if ($vehicleId !== '') {
            $bookingRepo->markVehicleAvailable($vehicleId);
        }

        echo json_encode(['success' => true, 'message' => 'Order rejected']);
        exit;
    }

    if ($action === 'get_vehicles') {
        ensure_vehicle_service_tier_column($pdo);
        $vehicles = $vehicleRepo->listAll();
        echo json_encode(['success' => true, 'vehicles' => $vehicles]);
        exit;
    }

    if ($action === 'add_vehicle') {
        ensure_vehicle_service_tier_column($pdo);
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

        $vehicleId = $vehicleRepo->create([
            'brand' => $brand,
            'model' => $model,
            'year' => $year,
            'license_plate' => $licensePlate,
            'category' => trim((string)($payload['category'] ?? 'sedan')),
            'seats' => (int)($payload['seats'] ?? 5),
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
        $payload = is_array($bodyJson) ? $bodyJson : $_POST;

        $vehicleId = trim((string)($payload['vehicle_id'] ?? ''));
        if ($vehicleId === '') {
            throw new Exception('Missing vehicle_id');
        }

        $vehicle = $vehicleRepo->getById($vehicleId);
        if (!$vehicle) {
            throw new Exception('Vehicle not found');
        }

        $fields = [];
        $editable = [
            'brand', 'model', 'year', 'license_plate', 'category', 'transmission', 'fuel_type',
            'seats', 'color', 'engine_size', 'consumption', 'price_per_day', 'price_per_week',
            'price_per_month', 'location_city', 'location_address', 'status', 'service_tier'
        ];

        foreach ($editable as $f) {
            if (array_key_exists($f, $payload)) {
                $fields[$f] = $payload[$f];
            }
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
