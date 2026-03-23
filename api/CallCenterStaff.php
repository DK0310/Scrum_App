<?php
/**
 * Call Center Staff Page & API - Private Hire
 * Role scope:
 * - Create booking requests for customers
 * - Cancel/delete own booking requests
 */

session_start();

require_once __DIR__ . '/../Database/db.php';
require_once __DIR__ . '/../config/env.php';
require_once __DIR__ . '/../sql/UserRepository.php';
require_once __DIR__ . '/../sql/VehicleRepository.php';
require_once __DIR__ . '/../sql/BookingRepository.php';
require_once __DIR__ . '/../sql/StaffBookingRepository.php';

$userRepo = new UserRepository($pdo);
$vehicleRepo = new VehicleRepository($pdo);
$bookingRepo = new BookingRepository($pdo);
$staffBookingRepo = new StaffBookingRepository($pdo);

function cc_normalize_role(string $role): string
{
    $normalized = strtolower(trim($role));
    $normalized = str_replace(['-', ' ', '_'], '', $normalized);

    if ($normalized === 'callcenterstaff') {
        return 'callcenterstaff';
    }

    if ($normalized === 'controlstaff' || $normalized === 'staff') {
        return 'controlstaff';
    }

    return $normalized;
}

function cc_is_authorized(string $role): bool
{
    $normalized = cc_normalize_role($role);
    return in_array($normalized, ['admin', 'callcenterstaff'], true);
}

if (!isset($_SESSION['user_id'])) {
    header('Location: /index.php');
    exit;
}

$userId = (string)$_SESSION['user_id'];
$userRole = (string)($userRepo->getUserRole($userId) ?? ($_SESSION['role'] ?? ''));

if (!cc_is_authorized($userRole)) {
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
    $title = 'Call Center Staff - Private Hire';
    $currentPage = 'call-center-staff';
    $isLoggedIn = true;
    $currentUser = $_SESSION['full_name'] ?? $_SESSION['username'] ?? $_SESSION['email'] ?? 'Call Center Staff';

    require __DIR__ . '/../templates/CallCenterStaff.html.php';
    exit;
}

header('Content-Type: application/json');

try {
    if ($action === 'search_customers') {
        $q = trim((string)($_GET['q'] ?? ''));
        if ($q === '') {
            echo json_encode(['success' => true, 'customers' => []]);
            exit;
        }

        $customers = $userRepo->searchByQuery($q, 'user', 20);
        echo json_encode(['success' => true, 'customers' => $customers]);
        exit;
    }

    if ($action === 'get_vehicles') {
        $vehicles = $vehicleRepo->listAll();
        $available = array_values(array_filter($vehicles, static function ($v) {
            return (($v['status'] ?? '') === 'available');
        }));

        echo json_encode(['success' => true, 'vehicles' => $available]);
        exit;
    }

    if ($action === 'get_my_requests') {
        $limit = (int)($_GET['limit'] ?? 100);
        $limit = max(1, min($limit, 200));

        $requests = $staffBookingRepo->getStaffBookings($userId, $limit, 0);
        echo json_encode(['success' => true, 'requests' => $requests]);
        exit;
    }

    if ($action === 'booking_by_request') {
        $payload = is_array($bodyJson) ? $bodyJson : $_POST;

        $selectedCustomerId = $payload['customer_id'] ?? null;

        if (empty($selectedCustomerId)) {
            throw new Exception('Please select an existing customer account.');
        }

        $customerName = trim((string)($payload['customer_name'] ?? ''));
        $customerPhone = trim((string)($payload['customer_phone'] ?? ''));
        $customerEmail = trim((string)($payload['customer_email'] ?? ''));

        if ($selectedCustomerId) {
            $customer = $userRepo->findById((string)$selectedCustomerId);
            if (!$customer) {
                throw new Exception('Customer not found');
            }
            $customerName = trim((string)($customer['full_name'] ?? ''));
            $customerEmail = trim((string)($customer['email'] ?? ''));
            $customerPhone = trim((string)($customer['phone'] ?? ''));
        }

        if ($customerName === '' || $customerPhone === '' || $customerEmail === '') {
            throw new Exception('Missing customer information');
        }

        $rideTier = strtolower(trim((string)($payload['ride_tier'] ?? '')));
        if (!in_array($rideTier, ['eco', 'standard', 'luxury'], true)) {
            throw new Exception('Invalid ride tier. Use eco, standard, or luxury.');
        }

        $seatCapacity = (int)($payload['seat_capacity'] ?? 4);
        if (!in_array($seatCapacity, [4, 7], true)) {
            throw new Exception('Invalid seat capacity. Use 4 or 7 seats.');
        }

        $pickupDate = trim((string)($payload['pickup_date'] ?? ''));
        $pickupLocation = trim((string)($payload['pickup_location'] ?? ''));
        $returnLocation = trim((string)($payload['return_location'] ?? ''));
        $specialRequests = trim((string)($payload['special_requests'] ?? ''));
        $paymentMethod = trim((string)($payload['payment_method'] ?? 'cash'));
        $distanceKm = isset($payload['distance_km']) ? (float)$payload['distance_km'] : null;

        if ($pickupDate === '' || $pickupLocation === '' || $returnLocation === '') {
            throw new Exception('Missing required booking fields');
        }

        $start = strtotime($pickupDate);
        if (!$start || $start <= time()) {
            throw new Exception('Pickup date/time must be in the future');
        }

        $days = 1;

        $vehicle = $bookingRepo->findVehicleForTier($rideTier, (string)$selectedCustomerId);
        if (!$vehicle) {
            throw new Exception('No available vehicle found for selected tier');
        }

        $vehicleId = (string)$vehicle['id'];

        // Phone booking rates in £/mile by seat capacity + £2.00 booking fee
        $tierRates = [
            4 => ['eco' => 2.50, 'standard' => 3.00, 'luxury' => 4.00],
            7 => ['eco' => 3.00, 'standard' => 3.50, 'luxury' => 5.00],
        ];
        $bookingFee = 2.00;
        $ratePerMile = $tierRates[$seatCapacity][$rideTier] ?? null;
        if ($ratePerMile === null) {
            throw new Exception('Unable to calculate fare for selected tier and seat capacity.');
        }
        
        if ($distanceKm !== null && $distanceKm > 0) {
            // Convert km to miles and calculate with phone booking rates
            $distanceMiles = $distanceKm * 0.621371;
            $subtotal = round(($distanceMiles * $ratePerMile) + $bookingFee, 2);
        } else {
            $subtotal = $bookingFee;
        }

        // Call Center always creates minicab requests in pending status for Control Staff approval.
        $booking = $staffBookingRepo->createPhoneBooking(
            (string)$selectedCustomerId,
            $vehicleId,
            [
            'booking_type' => 'minicab',
                'customer_name' => $customerName,
                'customer_phone' => $customerPhone,
                'customer_email' => $customerEmail,
                'pickup_date' => $pickupDate,
            'return_date' => null,
                'pickup_location' => $pickupLocation,
                'return_location' => $returnLocation,
                'special_requests' => $specialRequests,
                'initial_status' => 'pending',
                'payment_method' => $paymentMethod,
                'days' => $days,
                'number_of_passengers' => $seatCapacity,
                'subtotal' => $subtotal,
                'discount_amount' => 0,
                'total_amount' => $subtotal,
                'staff_id' => $userId,
            ]
        );

        echo json_encode([
            'success' => true,
            'message' => 'Booking request created and sent to Control Staff.',
            'booking' => [
                'id' => $booking['id'] ?? '',
                'booking_ref' => $booking['booking_ref'] ?? ('REF-' . ($booking['id'] ?? '')),
                'status' => 'pending',
                'total_amount' => $subtotal,
                'ride_tier' => $rideTier,
                'seat_capacity' => $seatCapacity,
                'assigned_vehicle' => trim(((string)($vehicle['brand'] ?? '')) . ' ' . ((string)($vehicle['model'] ?? ''))),
            ],
        ]);
        exit;
    }

    if ($action === 'cancel_request') {
        $bookingId = trim((string)($bodyJson['booking_id'] ?? $_POST['booking_id'] ?? ''));
        if ($bookingId === '') {
            throw new Exception('Missing booking_id');
        }

        $booking = $bookingRepo->getById($bookingId);
        if (!$booking) {
            throw new Exception('Booking not found');
        }

        if ((string)($booking['created_by_staff_id'] ?? '') !== $userId) {
            throw new Exception('You can only cancel your own requests');
        }

        $currentStatus = (string)($booking['status'] ?? '');
        if (!in_array($currentStatus, ['pending', 'in_progress'], true)) {
            throw new Exception('Only pending or in-progress requests can be cancelled');
        }

        $ok = $bookingRepo->updateStatus($bookingId, 'cancelled');
        if (!$ok) {
            throw new Exception('Unable to cancel request');
        }

        if (!empty($booking['vehicle_id'])) {
            $bookingRepo->markVehicleAvailable((string)$booking['vehicle_id']);
        }

        echo json_encode(['success' => true, 'message' => 'Request cancelled']);
        exit;
    }

    if ($action === 'delete_request') {
        $bookingId = trim((string)($bodyJson['booking_id'] ?? $_POST['booking_id'] ?? ''));
        if ($bookingId === '') {
            throw new Exception('Missing booking_id');
        }

        $booking = $bookingRepo->getById($bookingId);
        if (!$booking) {
            throw new Exception('Booking not found');
        }

        if ((string)($booking['created_by_staff_id'] ?? '') !== $userId) {
            throw new Exception('You can only delete your own requests');
        }

        $currentStatus = (string)($booking['status'] ?? '');
        if (!in_array($currentStatus, ['pending', 'cancelled'], true)) {
            throw new Exception('Only pending/cancelled requests can be deleted');
        }

        if (!empty($booking['vehicle_id'])) {
            $bookingRepo->markVehicleAvailable((string)$booking['vehicle_id']);
        }

        $ok = $bookingRepo->deleteBooking($bookingId);
        if (!$ok) {
            throw new Exception('Unable to delete request');
        }

        echo json_encode(['success' => true, 'message' => 'Request deleted']);
        exit;
    }

    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Unknown action']);
} catch (Exception $e) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
