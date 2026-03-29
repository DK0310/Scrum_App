<?php
/**
 * Bookings Page & API - Private Hire
 * Route controller for booking page view + API endpoints for booking actions
 * If no action param → render booking page
 * If action param → handle API request
 */

session_start();
require_once __DIR__ . '/../Database/db.php';
require_once __DIR__ . '/../config/env.php';

// Parse action first to determine if this is a page view or API request
$input = json_decode(file_get_contents('php://input'), true);
if (!is_array($input)) {
    $input = $_POST;
}
$action = $input['action'] ?? $_GET['action'] ?? '';

// ===== PAGE VIEW MODE (no action) =====
if (empty($action)) {
    // Render booking page (not API)
    $title = 'Private Hire - Book Your Ride';
    $currentPage = 'booking';

    $isLoggedIn = isset($_SESSION['logged_in']) && $_SESSION['logged_in'];
    $currentUser = $isLoggedIn ? ($_SESSION['full_name'] ?? $_SESSION['username'] ?? null) : null;
    $currentEmail = $isLoggedIn ? ($_SESSION['email'] ?? '') : '';
    $userRole = $_SESSION['role'] ?? 'user';

    // Require login to book
    if (!$isLoggedIn) {
        $_SESSION['login_flash'] = [
            'type' => 'error',
            'message' => 'Please sign in to continue booking.'
        ];
        header('Location: /');
        exit;
    }

    // Get query params for booking mode
    $carId = $_GET['car_id'] ?? '';
    $promoCode = $_GET['promo'] ?? '';
    $bookingMode = $_GET['mode'] ?? '';

    require __DIR__ . '/../templates/booking.html.php';
    exit;
}

// ===== API MODE (action parameter exists) =====
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit(0);
}

// Include repositories
require_once __DIR__ . '/../sql/BookingRepository.php';
require_once __DIR__ . '/../sql/PromotionRepository.php';
require_once __DIR__ . '/../sql/VehicleRepository.php';
require_once __DIR__ . '/../sql/UserRepository.php';
require_once __DIR__ . '/../lib/payments/PayPalGateway.php';

// Initialize repositories
$bookingRepo = new BookingRepository($pdo);
$promotionRepo = new PromotionRepository($pdo);
$vehicleRepo = new VehicleRepository($pdo);
$userRepo = new UserRepository($pdo);
$paypalGateway = new PayPalGateway();

// Include notification helper functions
require_once __DIR__ . '/notification-helpers.php';

// Helper: require login
function requireAuth() {
    if (!isset($_SESSION['logged_in']) || !$_SESSION['logged_in']) {
        echo json_encode(['success' => false, 'message' => 'Authentication required.', 'require_login' => true]);
        exit;
    }
}

function getAppBaseUrl(): string {
    $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
    $host = $_SERVER['HTTP_HOST'] ?? 'localhost:8000';
    return $scheme . '://' . $host;
}

// ==========================================================
// VALIDATE PROMO CODE
// ==========================================================
if ($action === 'validate-promo') {
    $code = strtoupper(trim($input['code'] ?? ''));
    if (empty($code)) {
        echo json_encode(['success' => false, 'message' => 'Promo code is required.']);
        exit;
    }

    try {
        $promo = $promotionRepo->findByCode($code);

        if (!$promo) {
            echo json_encode(['success' => false, 'message' => 'Invalid or expired promo code.']);
            exit;
        }

        // Check expiry
        if ($promo['expires_at'] && strtotime($promo['expires_at']) < time()) {
            echo json_encode(['success' => false, 'message' => 'This promo code has expired.']);
            exit;
        }

        // Check usage limit
        if ($promo['max_uses'] && (int)$promo['total_used'] >= (int)$promo['max_uses']) {
            echo json_encode(['success' => false, 'message' => 'This promo code has reached its usage limit.']);
            exit;
        }

        echo json_encode([
            'success' => true,
            'promo' => [
                'code' => $promo['code'],
                'title' => $promo['title'],
                'description' => $promo['description'],
                'discount_type' => $promo['discount_type'],
                'discount_value' => (float)$promo['discount_value'],
                'min_booking_days' => (int)$promo['min_booking_days']
            ]
        ]);
    } catch (PDOException $e) {
        echo json_encode(['success' => false, 'message' => 'Database error.']);
    }
    exit;
}

// ==========================================================
// LIST ACTIVE PROMOTIONS (for promo wallet)
// ==========================================================
if ($action === 'active-promos') {
    try {
        $promos = $promotionRepo->listAll();
        
        // Filter for active, non-expired, not max-used
        $promos = array_filter($promos, function($p) {
            if (!$p['is_active']) return false;
            if ($p['expires_at'] && strtotime($p['expires_at']) < time()) return false;
            if ($p['max_uses'] && (int)$p['total_used'] >= (int)$p['max_uses']) return false;
            return true;
        });

        foreach ($promos as &$p) {
            $p['discount_value'] = (float)$p['discount_value'];
            $p['min_booking_days'] = (int)$p['min_booking_days'];
        }

        // Sort by discount value
        usort($promos, fn($a, $b) => (float)$b['discount_value'] - (float)$a['discount_value']);
        
        echo json_encode(['success' => true, 'promos' => array_values($promos)]);
    } catch (PDOException $e) {
        echo json_encode(['success' => false, 'message' => 'Database error.']);
    }
    exit;
}

// ==========================================================
// CREATE BOOKING
// ==========================================================
if ($action === 'create') {
    requireAuth();

    $renterId = $_SESSION['user_id'];
    $vehicleId = $input['vehicle_id'] ?? '';
    $bookingType = $input['booking_type'] ?? 'with-driver';
    $pickupDate = $input['pickup_date'] ?? '';
    $pickupTime = $input['pickup_time'] ?? ''; // e.g., "08:00AM", "12:00PM"
    $returnDate = $input['return_date'] ?? null;
    $pickupLocation = $input['pickup_location'] ?? '';
    $returnLocation = $input['return_location'] ?? '';
    $specialRequests = $input['special_requests'] ?? '';
    $promoCode = $input['promo_code'] ?? '';
    $paymentMethod = $input['payment_method'] ?? 'cash';
    $distanceKm = isset($input['distance_km']) ? floatval($input['distance_km']) : null;
    $pickupCoords = isset($input['pickup_coords']) && is_array($input['pickup_coords']) ? $input['pickup_coords'] : [];
    $destinationCoords = isset($input['destination_coords']) && is_array($input['destination_coords']) ? $input['destination_coords'] : [];
    $rideTier = strtolower(trim((string)($input['ride_tier'] ?? ''))); // 'eco', 'standard', 'luxury'
    $seatCapacity = (int)($input['seat_capacity'] ?? $input['number_of_passengers'] ?? 4);
    $frontendRideFare = isset($input['ride_fare']) ? floatval($input['ride_fare']) : null;
    $serviceType = $input['service_type'] ?? 'local'; // 'local', 'long-distance', 'airport-transfer', 'hotel-transfer'
    $rideTiming = strtolower(trim((string)($input['ride_timing'] ?? '')));

    // Single workflow policy: minicab + schedule only
    if ($bookingType !== 'minicab') {
        echo json_encode(['success' => false, 'message' => 'Only scheduled minicab booking is supported.']);
        exit;
    }
    if ($rideTiming !== 'schedule') {
        echo json_encode(['success' => false, 'message' => 'Minicab bookings must be scheduled in advance.']);
        exit;
    }

    // Validate required fields
    if (empty($pickupDate) || empty($pickupLocation)) {
        echo json_encode(['success' => false, 'message' => 'Pickup date and pickup location are required.']);
        exit;
    }

    // For minicab, ride_tier is required
    if ($bookingType === 'minicab') {
        if (empty($rideTier) || !in_array($rideTier, ['eco', 'standard', 'luxury'])) {
            echo json_encode(['success' => false, 'message' => 'Please select a valid ride tier (eco, standard, luxury).']);
            exit;
        }
        if (!in_array($seatCapacity, [4, 7], true)) {
            echo json_encode(['success' => false, 'message' => 'Please select a valid seat capacity (4 or 7 seats).']);
            exit;
        }
        if (empty($returnLocation)) {
            echo json_encode(['success' => false, 'message' => 'Destination location is required for minicab booking.']);
            exit;
        }

        try {
            $pickupAt = new DateTime($pickupDate);
            $now = new DateTime('now');
            if ($pickupAt <= $now) {
                echo json_encode(['success' => false, 'message' => 'Scheduled pickup time must be in the future.']);
                exit;
            }
        } catch (Exception $e) {
            echo json_encode(['success' => false, 'message' => 'Invalid scheduled pickup date/time.']);
            exit;
        }
    }

    // Validate payment method
    $validMethods = ['cash', 'bank_transfer', 'credit_card', 'paypal'];
    if (!in_array($paymentMethod, $validMethods)) {
        echo json_encode(['success' => false, 'message' => 'Invalid payment method.']);
        exit;
    }

    try {
        $vehicle = null;
        $totalDays = 1;
        $subtotal = 0;

        if ($bookingType === 'minicab') {
            // ==== MINICAB: Auto-assign vehicle based on tier ====
            // Online booking rates in £/mile by seat capacity
            $tierRates = [
                4 => ['eco' => 2.00, 'standard' => 2.50, 'luxury' => 3.50],
                7 => ['eco' => 3.00, 'standard' => 3.50, 'luxury' => 4.50],
            ];
            $ratePerMile = $tierRates[$seatCapacity][$rideTier] ?? 0.0;
            if ($ratePerMile <= 0) {
                echo json_encode(['success' => false, 'message' => 'Unable to calculate fare for selected tier and seats.']);
                exit;
            }

            // Find a random available vehicle matching the tier (exclude own vehicles)
            $vehicle = $bookingRepo->findVehicleForTier($rideTier, $renterId);

            if (!$vehicle) {
                echo json_encode(['success' => false, 'message' => 'No available vehicles found for the ' . ucfirst($rideTier) . ' tier. Please try another tier.']);
                exit;
            }

            $vehicleId = $vehicle['id'];

            // Calculate fare based on distance × rate per mile (convert km to miles: 1 km = 0.621371 miles)
            if ($distanceKm !== null && $distanceKm > 0) {
                $distanceMiles = $distanceKm * 0.621371;
                $subtotal = round($distanceMiles * $ratePerMile, 2);
            } elseif ($frontendRideFare !== null && $frontendRideFare > 0) {
                $subtotal = $frontendRideFare;
            } else {
                echo json_encode(['success' => false, 'message' => 'Unable to calculate fare. Distance information is required.']);
                exit;
            }
            $totalDays = 1; // single trip
            $returnDate = null;

        }

        // Apply promo code
        $discountAmount = 0;
        $appliedPromo = null;
        if (!empty($promoCode)) {
            $promo = $promotionRepo->findByCode($promoCode);

            if ($promo) {
                $minDays = (int)$promo['min_booking_days'];
                // For minicab (single trip), always treat as 1 day
                if ($totalDays >= $minDays || $minDays <= 1) {
                    if ($promo['discount_type'] === 'percentage') {
                        $discountAmount = round($subtotal * (float)$promo['discount_value'] / 100, 2);
                    } else {
                        $discountAmount = min((float)$promo['discount_value'], $subtotal);
                    }
                    $appliedPromo = $promo['code'];

                    // Increment usage count
                    $promotionRepo->incrementUsageCount($promo['id']);
                }
            }
        }

        $totalAmount = max(0, $subtotal - $discountAmount);

        // Ensure booking columns exist
        $bookingRepo->ensureBookingColumnsExist();

        // Create booking via repository
        $numberOfPassengers = ($bookingType === 'minicab') ? $seatCapacity : (int)($input['number_of_passengers'] ?? 1);
        
        // Ensure pickup_date is converted to UTC before storage
        $pickupDateForDB = $pickupDate;
        if (!empty($pickupDate)) {
            try {
                $dt = new DateTime($pickupDate);
                $dt->setTimeZone(new DateTimeZone('UTC'));
                $pickupDateForDB = $dt->format('Y-m-d H:i:s');
            } catch (Exception $e) {
                $pickupDateForDB = $pickupDate;
            }
        }
        
        $booking = $bookingRepo->createBooking([
            'renter_id' => $renterId,
            'vehicle_id' => $vehicleId,
            'owner_id' => $vehicle['owner_id'],
            'booking_type' => $bookingType,
            'pickup_date' => $pickupDateForDB,
            'pickup_time' => $pickupTime,
            'return_date' => $returnDate,
            'pickup_location' => $pickupLocation,
            'return_location' => $returnLocation,
            'total_days' => $totalDays,
            'subtotal' => $subtotal,
            'discount_amount' => $discountAmount,
            'total_amount' => $totalAmount,
            'promo_code' => $appliedPromo,
            'special_requests' => $specialRequests,
            'driver_requested' => 't',
            'distance_km' => $distanceKm,
            'transfer_cost' => ($bookingType === 'minicab') ? $subtotal : null,
            'service_type' => ($bookingType === 'minicab') ? $serviceType : null,
            'number_of_passengers' => $numberOfPassengers,
            'ride_tier' => ($bookingType === 'minicab') ? $rideTier : null,
        ]);

        // For minicab bookings, create an active_trip record for real-time tracking
        if ($bookingType === 'minicab') {
            $bookingRepo->createActiveTrip(
                $booking['id'],
                $renterId,
                $vehicleId,
                isset($pickupCoords['lat']) ? (float)$pickupCoords['lat'] : null,
                isset($pickupCoords['lng']) ? (float)$pickupCoords['lng'] : null,
                isset($destinationCoords['lat']) ? (float)$destinationCoords['lat'] : null,
                isset($destinationCoords['lng']) ? (float)$destinationCoords['lng'] : null
            );
        }

        // Create payment record
        $bookingRepo->createPayment($booking['id'], $renterId, $totalAmount, $paymentMethod);

        $paypalMeta = null;
        if ($paymentMethod === 'paypal') {
            $baseUrl = getAppBaseUrl();
            $returnUrl = $baseUrl . '/api/bookings.php?paypal=return&booking_id=' . urlencode((string)$booking['id']);
            $cancelUrl = $baseUrl . '/api/bookings.php?paypal=cancel&booking_id=' . urlencode((string)$booking['id']);

            $paypalOrder = $paypalGateway->createOrder((float)$totalAmount, 'GBP', (string)$booking['id'], $returnUrl, $cancelUrl);
            if (empty($paypalOrder['success'])) {
                $bookingRepo->removeUnpaidPaypalBooking((string)$booking['id'], (string)$renterId);
                echo json_encode([
                    'success' => false,
                    'message' => $paypalOrder['message'] ?? 'Unable to initialize PayPal checkout.'
                ]);
                exit;
            }

            $bookingRepo->attachPaymentTransaction(
                (string)$booking['id'],
                (string)$paypalOrder['order_id'],
                [
                    'provider' => 'paypal',
                    'stage' => 'create_order',
                    'response' => $paypalOrder['raw'] ?? [],
                    'mock' => !empty($paypalOrder['mock']),
                ]
            );

            $paypalMeta = [
                'order_id' => $paypalOrder['order_id'],
                'approval_url' => $paypalOrder['approval_url'],
                'mock' => !empty($paypalOrder['mock']),
            ];
        }

        // --- Notifications ---
        $vehicleName = ($vehicle ? $vehicle['brand'] . ' ' . $vehicle['model'] : 'Vehicle');

        // Notify renter: booking created
        $tierLabel = $rideTier ? ' (' . ucfirst($rideTier) . ' tier)' : '';
        $serviceLabel = ($bookingType === 'minicab' && $serviceType) ? ' — ' . str_replace('-', ' ', ucfirst($serviceType)) : '';
        createNotification($pdo, $renterId, 'booking',
            '📋 Booking Created',
            "Your {$bookingType}{$tierLabel}{$serviceLabel} booking has been submitted. Pickup: {$pickupDate}. Total: \${$totalAmount}. Waiting for driver confirmation."
        );

        // For minicab, notify all available drivers
        if ($bookingType === 'minicab') {
            $drivers = $bookingRepo->getActiveDrivers(20);

            foreach ($drivers as $driver) {
                $bookingRepo->createDriverNotification(
                    $driver['id'],
                    $booking['id'],
                    "🚗 New {$rideTier} Minicab Booking",
                    "Pick up at {$pickupLocation}. Destination: {$returnLocation}. Passengers: " . ($input['number_of_passengers'] ?? 1),
                    'minicab_request'
                );
            }
        } else {
            // Notify owner: new booking request
            createNotification($pdo, $vehicle['owner_id'], 'booking',
                '🆕 New Booking Request',
                "You have a new {$bookingType} booking request for your {$vehicleName}. Pickup: {$pickupDate}. Amount: \${$totalAmount}."
            );
        }

        // === Mark vehicle as rented (with-driver only) ===
        if ($bookingType === 'with-driver' && !empty($vehicleId)) {
            $bookingRepo->markVehicleRented($vehicleId);
        }

        echo json_encode([
            'success' => true,
            'message' => 'Booking created successfully!',
            'booking' => [
                'id' => $booking['id'],
                'total_days' => $totalDays,
                'subtotal' => $subtotal,
                'discount' => $discountAmount,
                'total' => $totalAmount,
                'promo_applied' => $appliedPromo,
                'payment_method' => $paymentMethod,
                'status' => 'pending',
                'vehicle_name' => ($bookingType === 'minicab') ? null : $vehicleName,
                'ride_tier' => $rideTier,
                'service_type' => ($bookingType === 'minicab') ? $serviceType : null
            ],
            'paypal' => $paypalMeta
        ]);
    } catch (PDOException $e) {
        echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
    } catch (Throwable $e) {
        echo json_encode(['success' => false, 'message' => 'Payment initialization failed: ' . $e->getMessage()]);
    }
    exit;
}

if ($action === 'paypal-capture') {
    requireAuth();

    $orderId = trim((string)($input['order_id'] ?? ''));
    $payerId = trim((string)($input['payer_id'] ?? ''));
    if ($orderId === '') {
        echo json_encode(['success' => false, 'message' => 'PayPal order ID is required.']);
        exit;
    }

    $payment = $bookingRepo->getPaymentByTransactionId($orderId);
    if (!$payment) {
        echo json_encode(['success' => false, 'message' => 'Payment record not found for this PayPal order.']);
        exit;
    }

    if ((string)$payment['user_id'] !== (string)($_SESSION['user_id'] ?? '')) {
        echo json_encode(['success' => false, 'message' => 'You are not allowed to capture this payment.']);
        exit;
    }

    if ((string)$payment['status'] === 'paid') {
        $bookingData = $bookingRepo->getBookingWithUserAndDriver((string)$payment['booking_id']);
        echo json_encode([
            'success' => true,
            'message' => 'Payment already captured.',
            'booking_id' => $payment['booking_id'],
            'booking' => [
                'booking_type' => $bookingData['booking_type'] ?? 'minicab',
                'total_days' => (int)($bookingData['total_days'] ?? 1),
                'subtotal' => (float)($bookingData['subtotal'] ?? $payment['amount'] ?? 0),
                'discount' => (float)($bookingData['discount_amount'] ?? 0),
                'total' => (float)($bookingData['total_amount'] ?? $payment['amount'] ?? 0),
                'promo_applied' => $bookingData['promo_code'] ?? null,
                'payment_method' => $payment['method'] ?? 'paypal',
                'distance_km' => isset($bookingData['distance_km']) ? (float)$bookingData['distance_km'] : null,
                'ride_tier' => $bookingData['ride_tier'] ?? null,
            ],
        ]);
        exit;
    }

    try {
        $capture = $paypalGateway->captureOrder($orderId);
        if (empty($capture['success'])) {
            $bookingRepo->updatePaymentByTransactionId($orderId, 'failed', [
                'provider' => 'paypal',
                'stage' => 'capture',
                'payer_id' => $payerId,
                'response' => $capture['raw'] ?? [],
                'error' => $capture['message'] ?? 'capture_failed',
            ]);
            echo json_encode(['success' => false, 'message' => $capture['message'] ?? 'PayPal capture failed.']);
            exit;
        }

        $bookingRepo->updatePaymentByTransactionId($orderId, 'paid', [
            'provider' => 'paypal',
            'stage' => 'capture',
            'payer_id' => $payerId,
            'capture_id' => $capture['capture_id'] ?? null,
            'response' => $capture['raw'] ?? [],
            'mock' => !empty($capture['mock']),
        ], true);

        $bookingData = $bookingRepo->getBookingWithUserAndDriver((string)$payment['booking_id']);

        echo json_encode([
            'success' => true,
            'message' => 'PayPal payment captured successfully.',
            'booking_id' => $payment['booking_id'],
            'booking' => [
                'booking_type' => $bookingData['booking_type'] ?? 'minicab',
                'total_days' => (int)($bookingData['total_days'] ?? 1),
                'subtotal' => (float)($bookingData['subtotal'] ?? $payment['amount'] ?? 0),
                'discount' => (float)($bookingData['discount_amount'] ?? 0),
                'total' => (float)($bookingData['total_amount'] ?? $payment['amount'] ?? 0),
                'promo_applied' => $bookingData['promo_code'] ?? null,
                'payment_method' => $payment['method'] ?? 'paypal',
                'distance_km' => isset($bookingData['distance_km']) ? (float)$bookingData['distance_km'] : null,
                'ride_tier' => $bookingData['ride_tier'] ?? null,
            ],
        ]);
    } catch (Throwable $e) {
        echo json_encode(['success' => false, 'message' => 'PayPal capture error: ' . $e->getMessage()]);
    }
    exit;
}

if ($action === 'paypal-cancel') {
    requireAuth();

    $orderId = trim((string)($input['order_id'] ?? ''));
    if ($orderId === '') {
        echo json_encode(['success' => false, 'message' => 'PayPal order ID is required.']);
        exit;
    }

    $payment = $bookingRepo->getPaymentByTransactionId($orderId);
    if ($payment && (string)$payment['user_id'] === (string)($_SESSION['user_id'] ?? '')) {
        $bookingRepo->updatePaymentByTransactionId($orderId, 'failed', [
            'provider' => 'paypal',
            'stage' => 'cancel',
            'cancelled_at' => gmdate('c'),
        ]);

        $bookingId = (string)($payment['booking_id'] ?? '');
        if ($bookingId !== '') {
            $bookingRepo->removeUnpaidPaypalBooking($bookingId, (string)($_SESSION['user_id'] ?? ''));
        }
    }

    echo json_encode(['success' => true, 'message' => 'PayPal payment was cancelled and the pending order was removed.']);
    exit;
}

// ==========================================================
// ORDER ACTIONS MOVED TO /api/orders.php
// ==========================================================
$movedOrderActions = ['my-orders', 'modify-booking', 'update-status', 'submit-review'];
if (in_array($action, $movedOrderActions, true)) {
    echo json_encode([
        'success' => false,
        'message' => 'This action has moved to /api/orders.php.',
        'moved_to' => '/api/orders.php',
        'action' => $action,
    ]);
    exit;
}

// ==========================================================
// GET REVIEWS (public, for reviews page & homepage)
// ==========================================================
if ($action === 'get-reviews') {
    $limit = (int)($_GET['limit'] ?? $input['limit'] ?? 50);
    $vehicleId = $_GET['vehicle_id'] ?? $input['vehicle_id'] ?? '';

    try {
        $reviews = [];
        $stats = [];

        // Get reviews with details using repository
        if (!empty($vehicleId)) {
            $limit = max(1, min($limit, 100));
            $reviews = $bookingRepo->getReviewsWithDetailsAndStats($vehicleId, $limit);
            $stats = $bookingRepo->getReviewStatsComplete($vehicleId);
        }

        echo json_encode(['success' => true, 'reviews' => $reviews, 'stats' => $stats]);
    } catch (PDOException $e) {
        echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
    }
    exit;
}

// ==========================================================
// MARK PAYMENT AS PAID (Renter confirms) + SEND INVOICE
// ==========================================================
if ($action === 'confirm-payment') {
    requireAuth();
    $userId = $_SESSION['user_id'];
    $bookingId = $input['booking_id'] ?? '';

    if (empty($bookingId)) {
        echo json_encode(['success' => false, 'message' => 'Booking ID is required.']);
        exit;
    }

    try {
        // Verify booking belongs to renter via repository
        $booking = $bookingRepo->getBookingWithUserAndDriver($bookingId);

        if (!$booking) {
            echo json_encode(['success' => false, 'message' => 'Booking not found.']);
            exit;
        }

        if ((string)$booking['renter_id'] !== (string)$userId) {
            echo json_encode(['success' => false, 'message' => 'You are not allowed to confirm payment for this booking.']);
            exit;
        }

        // Mark payment as paid via repository
        $bookingRepo->markPaymentAsPaid($bookingId);

        // Send invoice (best-effort)
        try {
            require_once __DIR__ . '/../lib/invoice_mpdf.php';
            require_once __DIR__ . '/../lib/mailer.php';

            // Customer info
            $customerEmail = $booking['email'] ?? '';
            $customerName = $booking['user_name'] ?? '';
            $customerPhone = $booking['user_phone'] ?? '';
            $customerAddress = '';
            
            try {
                $userAddress = $bookingRepo->getUserAddress($userId);
                if ($userAddress) {
                    $customerAddress = (string)$userAddress;
                }
            } catch (Exception $ignore) {
                $customerAddress = '';
            }

            if (!empty($customerEmail) && filter_var($customerEmail, FILTER_VALIDATE_EMAIL)) {
                // Get booking details
                $bRow = $bookingRepo->getBookingFullInfo($bookingId) ?: [];

                // Vehicle info
                $vRow = $bookingRepo->getVehicleInfo($bRow['vehicle_id'] ?? '') ?: [];
                $vehicleName = trim(((string)($vRow['brand'] ?? '')) . ' ' . ((string)($vRow['model'] ?? '')) . (empty($vRow['year']) ? '' : (' ' . $vRow['year'])));

                // Payment method
                $paymentMethod = (string)($bookingRepo->getPaymentMethod($bookingId) ?? '');

                $invoiceBooking = [
                    'id' => $bookingId,
                    'subtotal' => $bRow['subtotal'] ?? '',
                    'discount_amount' => $bRow['discount_amount'] ?? '',
                    'total_amount' => $bRow['total_amount'] ?? '',
                    'payment_method' => $paymentMethod,
                    'payment_status' => 'paid',
                    'vehicle_name' => $vehicleName,
                    'license_plate' => $vRow['license_plate'] ?? '',
                    'pickup_location' => $bRow['pickup_location'] ?? '',
                    'return_location' => $bRow['return_location'] ?? '',
                    'pickup_date' => $bRow['pickup_date'] ?? '',
                    'customer_name' => $customerName,
                    'customer_email' => $customerEmail,
                    'customer_phone' => $customerPhone,
                    'customer_address' => $customerAddress,
                ];

                $pdf = privatehire_generate_invoice_pdf_local($invoiceBooking);

                $subject = 'Invoice for booking #' . $bookingId;
                $html = "<p>Dear " . htmlspecialchars($customerName ?: 'Customer') . ",</p>" .
                        "<p>Thank you for your payment. Please find your invoice attached.</p>" .
                        "<p><strong>Booking ID:</strong> " . htmlspecialchars((string)$bookingId) . "</p>" .
                        "<p>Regards,<br>Private Hire</p>";

                privatehire_send_email($customerEmail, $subject, $html, [
                    'content' => $pdf,
                    'filename' => 'invoice_' . $bookingId . '.pdf',
                    'mime' => 'application/pdf'
                ]);
            }
        } catch (Exception $e) {
            error_log('Invoice email failed (confirm-payment): ' . $e->getMessage());
        }

        echo json_encode(['success' => true, 'message' => 'Payment confirmed. Invoice email has been sent.']);
    } catch (PDOException $e) {
        echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
    }
    exit;
}

echo json_encode(['success' => false, 'message' => 'Unknown action: ' . $action]);

