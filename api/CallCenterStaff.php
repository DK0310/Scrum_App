<?php
/**
 * Call Center Staff Page & API - Private Hire
 * Role scope:
 * - Create booking requests for customers
 * - Cancel/delete own booking requests
 */

require_once __DIR__ . '/bootstrap.php';

$bodyJson = api_init(['allow_origin' => '*']);

require_once __DIR__ . '/../Database/db.php';
require_once __DIR__ . '/../config/env.php';
require_once __DIR__ . '/../sql/UserRepository.php';
require_once __DIR__ . '/../sql/VehicleRepository.php';
require_once __DIR__ . '/../sql/BookingRepository.php';
require_once __DIR__ . '/../sql/StaffBookingRepository.php';
require_once __DIR__ . '/../sql/AuthRepository.php';
require_once __DIR__ . '/../vendor/autoload.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception as MailException;

$userRepo = new UserRepository($pdo);
$vehicleRepo = new VehicleRepository($pdo);
$bookingRepo = new BookingRepository($pdo);
$staffBookingRepo = new StaffBookingRepository($pdo);
$authRepo = new AuthRepository($pdo);

function cc_decode_payment_details($raw): array
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

function cc_effective_payment_method(array $payment): string
{
    $method = strtolower(trim((string)($payment['method'] ?? '')));
    $details = cc_decode_payment_details($payment['payment_details'] ?? []);
    $original = strtolower(trim((string)($details['original_method'] ?? '')));
    return $original !== '' ? $original : $method;
}

function cc_refund_account_balance(UserRepository $userRepo, BookingRepository $bookingRepo, string $bookingId, array $booking, array $payment): bool
{
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

    $details = cc_decode_payment_details($payment['payment_details'] ?? []);
    $details['provider'] = 'account_balance';
    $details['stage'] = 'refund';
    $details['refunded_amount'] = round($amount, 2);
    $details['refunded_at'] = gmdate('c');

    $bookingRepo->updatePaymentByBookingId($bookingId, 'refunded', $details);
    return true;
}

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

function cc_is_callcenterstaff_only(string $role): bool
{
    return cc_normalize_role($role) === 'callcenterstaff';
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

$action = api_action($bodyJson);

if ($action === '') {
    api_json([
        'success' => false,
        'message' => 'Page controller moved to /call-center-staff.php.',
        'moved_to' => '/call-center-staff.php'
    ]);
    exit;
}

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

    if ($action === 'create_customer_account') {
        if (!cc_is_callcenterstaff_only($userRole)) {
            throw new Exception('Only Call Center Staff can create customer accounts.');
        }

        $payload = is_array($bodyJson) ? $bodyJson : $_POST;
        $username = trim((string)($payload['username'] ?? ''));
        $email = strtolower(trim((string)($payload['email'] ?? '')));
        $phone = trim((string)($payload['phone'] ?? ''));
        $dob = trim((string)($payload['dob'] ?? ''));
        $defaultPassword = '123456';

        if ($username === '' || $email === '' || $phone === '' || $dob === '') {
            throw new Exception('Username, email, phone number, and DOB are required.');
        }

        if (!$authRepo->isValidEmail($email)) {
            throw new Exception('Invalid email format.');
        }

        $phoneDigits = preg_replace('/\D+/', '', $phone);
        if ($phoneDigits === null || strlen($phoneDigits) < 10) {
            throw new Exception('Phone number must contain at least 10 digits.');
        }

        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $dob)) {
            throw new Exception('DOB must be in YYYY-MM-DD format.');
        }

        if (!$authRepo->isAdult($dob)) {
            throw new Exception('Customer must be at least 18 years old.');
        }

        if ($userRepo->usernameExists($username)) {
            throw new Exception('Username already exists.');
        }

        if ($authRepo->emailExists($email)) {
            throw new Exception('Email already registered.');
        }

        if ($authRepo->phoneExists($phone)) {
            throw new Exception('Phone number already registered.');
        }

        $userRepo->ensureCreatedByStaffColumnExists();

        $passwordHash = password_hash($defaultPassword, PASSWORD_BCRYPT);
        $newUser = $userRepo->createCustomerByStaff($username, $email, $phone, $dob, $passwordHash, $userId);
        if (!$newUser) {
            throw new Exception('Unable to create customer account.');
        }

        $emailSent = false;
        $emailWarning = '';
        try {
            $mail = new PHPMailer(true);
            $mail->isSMTP();
            $mail->Host = \EnvLoader::get('SMTP_HOST', 'smtp.gmail.com');
            $mail->SMTPAuth = true;
            $mail->Username = \EnvLoader::get('SMTP_USERNAME');
            $mail->Password = \EnvLoader::get('SMTP_PASSWORD');
            $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
            $mail->Port = 587;

            $fromEmail = \EnvLoader::get('SMTP_FROM_EMAIL');
            $fromName = \EnvLoader::get('SMTP_FROM_NAME', 'PrivateHire');
            if (!$fromEmail) {
                throw new Exception('SMTP_FROM_EMAIL is missing.');
            }

            $mail->setFrom($fromEmail, $fromName);
            $mail->addAddress($email, $username);
            $mail->Subject = 'PrivateHire - Your Customer Account Credentials';
            $mail->isHTML(true);
            $mail->Body = "
                <h2>Welcome to PrivateHire</h2>
                <p>Your customer account has been created by our call center staff.</p>
                <p><strong>Username:</strong> " . htmlspecialchars($username, ENT_QUOTES, 'UTF-8') . "</p>
                <p><strong>Email:</strong> " . htmlspecialchars($email, ENT_QUOTES, 'UTF-8') . "</p>
                <p><strong>Temporary Password:</strong> <span style='font-family:monospace;letter-spacing:1px;'>123456</span></p>
                <p>Please sign in and verify your email before booking minicab services.</p>
            ";

            $mail->send();
            $emailSent = true;
        } catch (MailException $e) {
            $emailWarning = 'Account created, but credential email could not be sent.';
            error_log('CallCenterStaff create_customer_account mail error: ' . $e->getMessage());
        } catch (Exception $e) {
            $emailWarning = 'Account created, but credential email could not be sent.';
            error_log('CallCenterStaff create_customer_account mail config error: ' . $e->getMessage());
        }

        echo json_encode([
            'success' => true,
            'message' => $emailSent ? 'Customer account created successfully.' : 'Customer account created successfully (email pending).',
            'email_sent' => $emailSent,
            'warning' => $emailWarning,
            'account' => [
                'id' => $newUser['id'] ?? null,
                'username' => $newUser['full_name'] ?? $username,
                'email' => $newUser['email'] ?? $email,
                'phone' => $newUser['phone'] ?? $phone,
                'dob' => $newUser['date_of_birth'] ?? $dob,
                'default_password' => $defaultPassword,
            ],
        ]);
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
        if ($rideTier === 'luxury') {
            $rideTier = 'premium';
        }
        if (!in_array($rideTier, ['eco', 'standard', 'premium'], true)) {
            throw new Exception('Invalid ride tier. Use eco, standard, or premium.');
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
            4 => ['eco' => 2.50, 'standard' => 3.00, 'premium' => 4.00],
            7 => ['eco' => 3.00, 'standard' => 3.50, 'premium' => 5.00],
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

        $payment = $bookingRepo->getPaymentByBookingId($bookingId);
        if ($payment) {
            $paymentMethod = cc_effective_payment_method($payment);
            $paymentStatus = strtolower(trim((string)($payment['status'] ?? '')));
            if ($paymentMethod === 'account_balance' && $paymentStatus === 'paid') {
                $okRefund = cc_refund_account_balance($userRepo, $bookingRepo, $bookingId, $booking, $payment);
                if (!$okRefund) {
                    throw new Exception('Unable to refund account balance for this request');
                }
            }
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
