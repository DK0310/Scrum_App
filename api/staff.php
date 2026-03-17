<?php
/**
 * Staff API Endpoints
 * For staff to manage drivers, vehicles, and view orders
 */

header('Content-Type: application/json');
require_once __DIR__ . '/../config/env.php';
require_once __DIR__ . '/../Database/db.php';

// Repository includes
require_once __DIR__ . '/../lib/repositories/UserRepository.php';
require_once __DIR__ . '/../lib/repositories/VehicleRepository.php';
require_once __DIR__ . '/../lib/repositories/VehicleImageRepository.php';
require_once __DIR__ . '/../lib/repositories/BookingRepository.php';
require_once __DIR__ . '/../lib/repositories/StaffBookingRepository.php';
require_once __DIR__ . '/../lib/repositories/NotificationRepository.php';

session_start();

// Initialize repositories
$userRepo = new UserRepository($pdo);
$vehicleRepo = new VehicleRepository($pdo);
$vehicleImageRepo = new VehicleImageRepository($pdo);
$bookingRepo = new BookingRepository($pdo);
$staffBookingRepo = new StaffBookingRepository($pdo);
$notificationRepo = new NotificationRepository($pdo);

// Helpers
function requireString($value, $fieldName) {
    $v = is_string($value) ? trim($value) : '';
    if ($v === '') {
        throw new Exception('Missing ' . $fieldName);
    }
    return $v;
}

function requireDateString($value, $fieldName) {
    $v = is_string($value) ? trim($value) : '';
    if ($v === '') {
        throw new Exception('Missing ' . $fieldName);
    }
    return $v;
}

function staffBuildBookingReference(): string {
    // Human-friendly ref: PH-YYYYMMDD-XXXX
    $date = gmdate('Ymd');
    $rand = strtoupper(substr(bin2hex(random_bytes(3)), 0, 6));
    return 'PH-' . $date . '-' . $rand;
}

function staffAppendAuditNote(string $specialRequests, string $bookingRef, string $customerName, string $customerPhone, string $customerEmail, string $staffId): string {
    $lines = [];
    $lines[] = "[PHONE BOOKING] Ref: {$bookingRef}";
    $lines[] = "Customer: {$customerName} | {$customerPhone}" . ($customerEmail ? " | {$customerEmail}" : "");
    $lines[] = "CreatedByStaffId: {$staffId}";

    $body = trim($specialRequests);
    return trim(implode("\n", $lines) . "\n" . $body);
}

// Check if logged in and is staff
if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Not logged in']);
    exit;
}

$userId = $_SESSION['user_id'];

// Accept action from GET/POST or JSON body
$rawBody = file_get_contents('php://input');
$bodyJson = null;
if (is_string($rawBody) && trim($rawBody) !== '') {
    $bodyJson = json_decode($rawBody, true);
}
$action = $_GET['action'] ?? $_POST['action'] ?? ($bodyJson['action'] ?? null);

// Verify user is staff
$userRole = $userRepo->getUserRole($userId);
if (!$userRole || !in_array($userRole, ['staff', 'admin'], true)) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Not a staff/admin account']);
    exit;
}

// ===== GET ALL AVAILABLE DRIVERS =====
if ($action === 'get_drivers') {
    try {
        $onlineOnly = $_GET['online_only'] ?? false;

        // Use UserRepository to get drivers
        $drivers = $userRepo->getDriversWithVehicles();

        echo json_encode(['success' => true, 'drivers' => $drivers]);
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
}

// ===== GET USER DETAILS (read-only) =====
else if ($action === 'get_user') {
    try {
        $userId_target = $_GET['user_id'] ?? null;

        if (!$userId_target) {
            echo json_encode(['success' => false, 'message' => 'Missing user_id']);
            exit;
        }

        // Use UserRepository to get user
        $user = $userRepo->findById($userId_target);

        if ($user) {
            echo json_encode(['success' => true, 'user' => $user]);
        } else {
            echo json_encode(['success' => false, 'message' => 'User not found']);
        }
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
}

// ===== ASSIGN VEHICLE TO DRIVER =====
else if ($action === 'assign_vehicle') {
    try {
        $driverId = $_POST['driver_id'] ?? null;
        $vehicleId = $_POST['vehicle_id'] ?? null;
        $assignedDate = $_POST['assigned_date'] ?? date('Y-m-d');

        if (!$driverId || !$vehicleId) {
            echo json_encode(['success' => false, 'message' => 'Missing driver_id or vehicle_id']);
            exit;
        }

        // Check if driver exists
        $driver = $userRepo->getById($driverId);
        if (!$driver || $driver['role'] !== 'driver') {
            echo json_encode(['success' => false, 'message' => 'Driver not found']);
            exit;
        }

        // Check if vehicle exists and is available
        $vehicle = $vehicleRepo->getById($vehicleId);
        if (!$vehicle) {
            echo json_encode(['success' => false, 'message' => 'Vehicle not found']);
            exit;
        }

        if (($vehicle['status'] ?? '') !== 'available') {
            echo json_encode(['success' => false, 'message' => 'Vehicle is not available']);
            exit;
        }

        // Assign vehicle via repository
        $vehicleRepo->assignToDriver($userId, $driverId, $vehicleId, $assignedDate);

        // Send notification to driver
        $notificationRepo->create([
            'user_id' => $driverId,
            'type' => 'system',
            'title' => 'Vehicle Assignment',
            'message' => 'A vehicle has been assigned to you for today. Check your profile for details.',
            'is_read' => false
        ]);

        echo json_encode(['success' => true, 'message' => 'Vehicle assigned to driver']);
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
}

// ===== UNASSIGN VEHICLE FROM DRIVER =====
else if ($action === 'unassign_vehicle') {
    try {
        $driverId = $_POST['driver_id'] ?? null;

        if (!$driverId) {
            echo json_encode(['success' => false, 'message' => 'Missing driver_id']);
            exit;
        }

        // Unassign vehicle via repository
        $vehicleRepo->unassignFromDriver($driverId);

        echo json_encode(['success' => true, 'message' => 'Vehicle unassigned']);
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
}

// ===== GET ALL AVAILABLE VEHICLES =====
else if ($action === 'get_vehicles') {
    try {
        $status = $_GET['status'] ?? 'all';

        // Use VehicleRepository to get vehicles
        $vehicles = $vehicleRepo->listAll();
        
        if ($status === 'available') {
            $vehicles = array_filter($vehicles, fn($v) => ($v['status'] ?? '') === 'available');
        }

        // Attach thumbnail (first image public URL)
        try {
            require_once __DIR__ . '/supabase-storage.php';
            $storage = new SupabaseStorage();
            foreach ($vehicles as &$v) {
                $images = $vehicleImageRepo->listByVehicleId($v['id']);
                $v['thumbnail_url'] = !empty($images) ? $images[0]['public_url'] ?? null : null;
            }
        } catch (Exception $ignore) {
            foreach ($vehicles as &$v) {
                $v['thumbnail_url'] = null;
            }
        }

        echo json_encode(['success' => true, 'vehicles' => $vehicles]);
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
}

// ===== ADD NEW VEHICLE =====
else if ($action === 'add_vehicle') {
    try {
        $brand = $_POST['brand'] ?? null;
        $model = $_POST['model'] ?? null;
        $year = $_POST['year'] ?? null;
        $licensePlate = $_POST['license_plate'] ?? null;
        $category = $_POST['category'] ?? 'sedan';
        $seats = $_POST['seats'] ?? 5;
        $color = $_POST['color'] ?? null;
        $pricePerDay = $_POST['price_per_day'] ?? null;
        $pricePerWeek = $_POST['price_per_week'] ?? null;
        $pricePerMonth = $_POST['price_per_month'] ?? null;
        $city = $_POST['location_city'] ?? null;
        $address = $_POST['location_address'] ?? null;
        $transmission = $_POST['transmission'] ?? 'automatic';
        $fuelType = $_POST['fuel_type'] ?? 'petrol';

        // Optional specs
        $engineSize = $_POST['engine_size'] ?? null;
        $consumption = $_POST['consumption'] ?? null;
        $featuresJson = $_POST['features_json'] ?? '[]';

        $features = [];
        if (is_string($featuresJson) && trim($featuresJson) !== '') {
            $decoded = json_decode($featuresJson, true);
            if (is_array($decoded)) {
                $features = array_values(array_filter(array_map('strval', $decoded), fn($v) => trim($v) !== ''));
            }
        }

        // Validation
        if (!$brand || !$model || !$year || !$licensePlate || !$pricePerDay) {
            echo json_encode(['success' => false, 'message' => 'Missing required fields']);
            exit;
        }

        // Create vehicle via repository
        $vehicleId = $vehicleRepo->create([
            'brand' => $brand,
            'model' => $model,
            'year' => $year,
            'license_plate' => $licensePlate,
            'category' => $category,
            'seats' => $seats,
            'color' => $color,
            'price_per_day' => $pricePerDay,
            'price_per_week' => $pricePerWeek,
            'price_per_month' => $pricePerMonth,
            'location_city' => $city,
            'location_address' => $address,
            'transmission' => $transmission,
            'fuel_type' => $fuelType,
            'engine_size' => $engineSize,
            'consumption' => $consumption,
            'features' => $features
        ]);

        echo json_encode(['success' => true, 'message' => 'Vehicle added', 'vehicle_id' => $vehicleId]);
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
}

// ===== VIEW ALL ORDERS (read-only) =====
else if ($action === 'get_orders') {
    try {
        $status = $_GET['status'] ?? null;
        $limit = $_GET['limit'] ?? 50;

        // Use BookingRepository to get orders
        $orders = $bookingRepo->listAll($limit);
        
        // Filter by status if provided
        if ($status) {
            $orders = array_filter($orders, fn($o) => $o['status'] === $status);
        }

        echo json_encode(['success' => true, 'orders' => $orders]);
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
}

// ===== GET ORDER DETAILS (read-only) =====
else if ($action === 'get_order') {
    try {
        $orderId = $_GET['order_id'] ?? null;

        if (!$orderId) {
            echo json_encode(['success' => false, 'message' => 'Missing order_id']);
            exit;
        }

        // Use BookingRepository to get order
        $order = $bookingRepo->getBookingWithUserAndDriver($orderId);

        if ($order) {
            echo json_encode(['success' => true, 'order' => $order]);
        } else {
            echo json_encode(['success' => false, 'message' => 'Order not found']);
        }
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
}

// ===== SEARCH CUSTOMERS (dropdown/autocomplete) =====
else if ($action === 'search_customers') {
    try {
        $q = trim($_GET['q'] ?? '');
        if ($q === '') {
            echo json_encode(['success' => true, 'customers' => []]);
            exit;
        }

        // Use UserRepository to search customers
        $customers = $userRepo->searchByQuery($q, 'user', 20);

        echo json_encode(['success' => true, 'customers' => $customers]);
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
}

// ===== STAFF: BOOKING BY REQUEST (staff books for customer by phone) =====
else if ($action === 'booking_by_request') {
    try {
        // Reuse decoded JSON body if already read above
        $payload = is_array($bodyJson) ? $bodyJson : $_POST;

        // (AC2) Staff can select an existing customer OR enter manual customer details
        $selectedCustomerId = $payload['customer_id'] ?? null;

        $customerName  = $payload['customer_name'] ?? '';
        $customerPhone = $payload['customer_phone'] ?? '';
        $customerEmail = $payload['customer_email'] ?? '';

        if ($selectedCustomerId) {
            $customer = $userRepo->findById($selectedCustomerId);
            if (!$customer) {
                throw new Exception('Customer not found');
            }
            $customerName = $customer['full_name'] ?? '';
            $customerEmail = $customer['email'] ?? '';
            $customerPhone = $customer['phone'] ?? '';
        }

        $customerName = requireString($customerName, 'customer_name');
        $customerPhone = requireString($customerPhone, 'customer_phone');
        $customerEmail = is_string($customerEmail) ? trim($customerEmail) : '';
        $customerEmail = requireString($customerEmail, 'customer_email');

        $vehicleId = requireString(($payload['vehicle_id'] ?? ''), 'vehicle_id');
        $pickupDate = requireDateString(($payload['pickup_date'] ?? ''), 'pickup_date');
        $returnDate = requireDateString(($payload['return_date'] ?? ''), 'return_date');
        $pickupLocation = requireString(($payload['pickup_location'] ?? ''), 'pickup_location');
        $returnLocation = is_string($payload['return_location'] ?? null) ? trim((string)$payload['return_location']) : '';
        $specialRequests = is_string($payload['special_requests'] ?? null) ? (string)$payload['special_requests'] : '';

        // (AC5) Initial status rule
        $initialStatus = $payload['initial_status'] ?? 'pending'; // pending | confirmed
        if (!in_array($initialStatus, ['pending', 'confirmed'], true)) {
            $initialStatus = 'pending';
        }

        $paymentMethod = $payload['payment_method'] ?? 'cash';
        $validMethods = ['cash', 'bank_transfer', 'credit_card', 'paypal'];
        if (!in_array($paymentMethod, $validMethods, true)) {
            throw new Exception('Invalid payment_method');
        }

        // booking period validation
        $start = strtotime($pickupDate);
        $end = strtotime($returnDate);
        if (!$start || !$end || $end < $start) {
            throw new Exception('Invalid pickup_date/return_date');
        }
        $days = max(1, (int)ceil(($end - $start) / 86400));

        // Use StaffBookingRepository for staff booking creation
        $booking = $staffBookingRepo->createPhoneBooking(
            $selectedCustomerId ?: $userId,
            $vehicleId,
            [
                'customer_name' => $customerName,
                'customer_phone' => $customerPhone,
                'customer_email' => $customerEmail,
                'pickup_date' => $pickupDate,
                'return_date' => $returnDate,
                'pickup_location' => $pickupLocation,
                'return_location' => $returnLocation,
                'special_requests' => $specialRequests,
                'initial_status' => $initialStatus,
                'payment_method' => $paymentMethod,
                'days' => $days,
                'staff_id' => $userId
            ]
        );

        // Send invoice email (must have customer_email)
        try {
            require_once __DIR__ . '/../lib/invoice_mpdf.php';
            require_once __DIR__ . '/../lib/mailer.php';

            $bookingRef = $booking['booking_ref'] ?? 'REF-' . $booking['id'];
            $vehicle = $vehicleRepo->getById($vehicleId);
            $pricePerDay = (float)($vehicle['price_per_day'] ?? 0);
            $subtotal = $days * $pricePerDay;

            $invoiceBooking = [
                'id' => $booking['id'],
                'created_at' => $booking['created_at'],
                'booking_type' => 'with-driver',
                'pickup_location' => $pickupLocation,
                'return_location' => $returnLocation,
                'pickup_date' => $pickupDate,
                'return_date' => $returnDate,
                'total_days' => $days,
                'price_per_day' => $pricePerDay,
                'subtotal' => $subtotal,
                'discount_amount' => 0,
                'total_amount' => $booking['total_amount'],
                'promo_code' => null,
                'payment_method' => $paymentMethod,

                // customer
                'renter_name' => $customerName,
                'renter_email' => $customerEmail,
                'renter_phone' => $customerPhone,

                // vehicle
                'vehicle_brand' => $vehicle['brand'] ?? '',
                'vehicle_model' => $vehicle['model'] ?? '',
                'vehicle_license_plate' => $vehicle['license_plate'] ?? null,

                // extra note
                'booking_ref' => $bookingRef
            ];

            $pdf = privatehire_generate_invoice_pdf_local($invoiceBooking);

            $subject = 'Invoice - ' . $bookingRef;
            $body = "Hello {$customerName},\n\nYour booking has been created successfully. Please find your invoice attached.\n\nBooking reference: {$bookingRef}\n\nThank you.";

            privatehire_send_mail(
                $customerEmail,
                $customerName,
                $subject,
                nl2br(htmlspecialchars($body, ENT_QUOTES, 'UTF-8')),
                [
                    [
                        'name' => "invoice-{$bookingRef}.pdf",
                        'type' => 'application/pdf',
                        'data' => $pdf
                    ]
                ]
            );
        } catch (Exception $mailErr) {
            // Do not fail booking if mail fails, but log for staff
            error_log('Staff booking invoice email failed: ' . $mailErr->getMessage());
        }

        $vehicle = $vehicleRepo->getById($vehicleId);
        echo json_encode([
            'success' => true,
            'message' => 'Booking created by staff request',
            'booking' => [
                'id' => $booking['id'],
                'created_at' => $booking['created_at'],
                'booking_ref' => $booking['booking_ref'] ?? 'REF-' . $booking['id'],
                'status' => $initialStatus,
                'vehicle' => trim(($vehicle['brand'] ?? '') . ' ' . ($vehicle['model'] ?? '')),
                'total_amount' => $booking['total_amount'],
                'days' => $days,
                'invoice_sent_to' => $customerEmail
            ]
        ]);
    } catch (Exception $e) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
}

else {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Unknown action']);
}
