<?php
/**
 * Bookings API - Private Hire
 * JSON-only endpoint for booking actions.
 */

require_once __DIR__ . '/../Database/db.php';
require_once __DIR__ . '/../config/env.php';
require_once __DIR__ . '/bootstrap.php';

$input = api_init();
$action = api_action($input);

if (empty($action)) {
    header('Content-Type: application/json');
    echo json_encode([
        'success' => false,
        'message' => 'Page controller moved to /booking.php.',
        'moved_to' => '/booking.php'
    ]);
    exit;
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

        if (array_key_exists('total_days', $input)) {
            $totalDays = max(1, (int)($input['total_days'] ?? 1));
            $minDays = max(1, (int)($promo['min_booking_days'] ?? 1));
            if ($totalDays < $minDays) {
                echo json_encode([
                    'success' => false,
                    'message' => 'This promo requires at least ' . $minDays . ' booking day(s).'
                ]);
                exit;
            }
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
    $renter = $userRepo->findById((string)$renterId);
    $emailVerifiedRaw = $renter['email_verified'] ?? false;
    $emailVerified = in_array(strtolower((string)$emailVerifiedRaw), ['1', 'true', 't', 'yes', 'y'], true);
    if (!$emailVerified) {
        echo json_encode([
            'success' => false,
            'message' => 'Please verify your email before booking a minicab.'
        ]);
        exit;
    }

    $vehicleId = $input['vehicle_id'] ?? '';
    $bookingType = $input['booking_type'] ?? 'with-driver';
    $pickupDate = $input['pickup_date'] ?? '';
    $pickupTime = $input['pickup_time'] ?? ''; // e.g., "08:00AM", "12:00PM"
    $returnDate = $input['return_date'] ?? null;
    $pickupLocation = $input['pickup_location'] ?? '';
    $returnLocation = $input['return_location'] ?? '';
    $specialRequests = $input['special_requests'] ?? '';
    $promoCode = strtoupper(trim((string)($input['promo_code'] ?? '')));
    $paymentMethod = $input['payment_method'] ?? 'cash';
    $distanceKm = isset($input['distance_km']) ? floatval($input['distance_km']) : null;
    $pickupCoords = isset($input['pickup_coords']) && is_array($input['pickup_coords']) ? $input['pickup_coords'] : [];
    $destinationCoords = isset($input['destination_coords']) && is_array($input['destination_coords']) ? $input['destination_coords'] : [];
    $rideTier = strtolower(trim((string)($input['ride_tier'] ?? ''))); // 'eco', 'standard', 'luxury'
    $seatCapacity = (int)($input['seat_capacity'] ?? $input['number_of_passengers'] ?? 4);
    $frontendRideFare = isset($input['ride_fare']) ? floatval($input['ride_fare']) : null;
    $serviceType = strtolower(trim((string)($input['service_type'] ?? 'local'))); // 'local', 'long-distance', 'airport-transfer', 'hotel-transfer', 'daily-hire'
    $rideTiming = strtolower(trim((string)($input['ride_timing'] ?? '')));
    $requestWindow = null;

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
        if (!in_array($serviceType, ['local', 'long-distance', 'airport-transfer', 'hotel-transfer', 'daily-hire'], true)) {
            echo json_encode(['success' => false, 'message' => 'Please select a valid service type.']);
            exit;
        }
        if ($serviceType !== 'daily-hire' && empty($returnLocation)) {
            echo json_encode(['success' => false, 'message' => 'Destination location is required for minicab booking.']);
            exit;
        }

        $requestWindow = $bookingRepo->buildMinicabRequestWindow($pickupDate, $pickupTime, $distanceKm, $serviceType);
        if (!$requestWindow) {
            echo json_encode(['success' => false, 'message' => 'Invalid scheduled pickup date/time.']);
            exit;
        }

        $nowUtc = new DateTimeImmutable('now', new DateTimeZone('UTC'));
        if ($requestWindow['pickup_at'] <= $nowUtc) {
            echo json_encode(['success' => false, 'message' => 'Scheduled pickup time must be in the future.']);
            exit;
        }
    }

    // Validate payment method
    $validMethods = ['cash', 'bank_transfer', 'credit_card', 'paypal', 'account_balance'];
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
            $dailyHireRates = [
                4 => ['eco' => 180.00, 'standard' => 220.00, 'luxury' => 300.00],
                7 => ['eco' => 220.00, 'standard' => 270.00, 'luxury' => 400.00],
            ];
            $ratePerMile = $tierRates[$seatCapacity][$rideTier] ?? 0.0;
            $dailyHirePrice = $dailyHireRates[$seatCapacity][$rideTier] ?? 0.0;

            if (!$requestWindow) {
                $requestWindow = $bookingRepo->buildMinicabRequestWindow($pickupDate, $pickupTime, $distanceKm, $serviceType);
            }
            if (!$requestWindow) {
                echo json_encode(['success' => false, 'message' => 'Unable to validate booking schedule. Please choose date and time again.']);
                exit;
            }

            // Find a random available vehicle matching tier/seat and requested time window.
            $vehicle = $bookingRepo->findVehicleForTier(
                $rideTier,
                $renterId,
                $seatCapacity,
                $requestWindow['window_start']->format('Y-m-d H:i:sP'),
                $requestWindow['window_end']->format('Y-m-d H:i:sP'),
                $requestWindow['pickup_at']->format('Y-m-d H:i:sP'),
                $serviceType
            );

            if (!$vehicle) {
                echo json_encode(['success' => false, 'message' => 'No available vehicles found for the ' . ucfirst($rideTier) . ' tier. Please try another tier.']);
                exit;
            }

            $vehicleId = $vehicle['id'];

            if ($serviceType === 'daily-hire') {
                if ($dailyHirePrice <= 0) {
                    echo json_encode(['success' => false, 'message' => 'Unable to calculate Daily Hire fare for selected tier and seats.']);
                    exit;
                }

                $subtotal = (float)$dailyHirePrice;
                $distanceKm = null;
            } else {
                if ($ratePerMile <= 0) {
                    echo json_encode(['success' => false, 'message' => 'Unable to calculate fare for selected tier and seats.']);
                    exit;
                }

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
            }

            $totalDays = 1; // single trip
            $returnDate = null;

        }

        // Apply promo code
        $discountAmount = 0;
        $appliedPromo = null;
        if (!empty($promoCode)) {
            $promo = $promotionRepo->findByCode($promoCode);

            if (!$promo) {
                echo json_encode(['success' => false, 'message' => 'Invalid or expired promo code.']);
                exit;
            }

            if ($promo['expires_at'] && strtotime((string)$promo['expires_at']) < time()) {
                echo json_encode(['success' => false, 'message' => 'This promo code has expired.']);
                exit;
            }

            if ($promo['max_uses'] && (int)$promo['total_used'] >= (int)$promo['max_uses']) {
                echo json_encode(['success' => false, 'message' => 'This promo code has reached its usage limit.']);
                exit;
            }

            $minDays = max(1, (int)($promo['min_booking_days'] ?? 1));
            if ($totalDays < $minDays) {
                echo json_encode(['success' => false, 'message' => 'This promo requires at least ' . $minDays . ' booking day(s).']);
                exit;
            }

            if ($promo['discount_type'] === 'percentage') {
                $discountAmount = round($subtotal * (float)$promo['discount_value'] / 100, 2);
            } else {
                $discountAmount = min((float)$promo['discount_value'], $subtotal);
            }
            $appliedPromo = $promo['code'];

            // Increment usage count
            $promotionRepo->incrementUsageCount($promo['id']);
        }

        $totalAmount = max(0, $subtotal - $discountAmount);

        if ($paymentMethod === 'account_balance') {
            $currentBalance = $userRepo->getAccountBalance((string)$renterId);
            if ($currentBalance < $totalAmount) {
                echo json_encode([
                    'success' => false,
                    'message' => 'Insufficient account balance. Please top up and try again.'
                ]);
                exit;
            }
        }

        // Ensure booking columns exist
        $bookingRepo->ensureBookingColumnsExist();

        // Create booking via repository
        $numberOfPassengers = ($bookingType === 'minicab') ? $seatCapacity : (int)($input['number_of_passengers'] ?? 1);
        
        // Ensure pickup_date is converted to UTC before storage
        $pickupDateForDB = $pickupDate;
        if ($bookingType === 'minicab' && $requestWindow) {
            // pickup_date column is date-based in current schema.
            $pickupDateForDB = $requestWindow['pickup_at']->format('Y-m-d');
        } elseif (!empty($pickupDate)) {
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
            'payment_method' => $paymentMethod,
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
        $paymentMethodForDb = $paymentMethod === 'account_balance' ? 'bank_transfer' : $paymentMethod;
        $bookingRepo->createPayment($booking['id'], $renterId, $totalAmount, $paymentMethodForDb);

        if ($paymentMethod === 'account_balance') {
            $bookingRepo->updatePaymentByBookingId(
                (string)$booking['id'],
                'pending',
                [
                    'original_method' => 'account_balance',
                    'provider' => 'account_balance',
                    'stage' => 'authorize',
                    'authorized_amount' => round((float)$totalAmount, 2),
                    'authorized_at' => gmdate('c'),
                ]
            );
        }

        if ($paymentMethod === 'account_balance') {
            $deducted = $userRepo->deductAccountBalance((string)$renterId, (float)$totalAmount);
            if (!$deducted) {
                echo json_encode([
                    'success' => false,
                    'message' => 'Insufficient account balance at payment time. Please retry.'
                ]);
                exit;
            }
            $bookingRepo->updatePaymentByBookingId(
                (string)$booking['id'],
                'paid',
                [
                    'original_method' => 'account_balance',
                    'provider' => 'account_balance',
                    'stage' => 'charge',
                    'charged_amount' => round((float)$totalAmount, 2),
                    'charged_at' => gmdate('c'),
                ],
                true
            );
        }

        $paypalMeta = null;
        if ($paymentMethod === 'paypal') {
            $baseUrl = getAppBaseUrl();
            $returnUrl = $baseUrl . '/booking.php?mode=minicab&paypal=return&booking_id=' . urlencode((string)$booking['id']);
            $cancelUrl = $baseUrl . '/booking.php?mode=minicab&paypal=cancel&booking_id=' . urlencode((string)$booking['id']);

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
$movedOrderActions = ['my-orders', 'modify-booking', 'update-status', 'submit-review', 'get-reviews'];
if (in_array($action, $movedOrderActions, true)) {
    $targetApi = ($action === 'get-reviews' || $action === 'submit-review') ? '/api/reviews.php' : '/api/orders.php';
    echo json_encode([
        'success' => false,
        'message' => 'This action has moved to ' . $targetApi . '.',
        'moved_to' => $targetApi,
        'action' => $action,
    ]);
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
            require_once __DIR__ . '/../Invoice/invoice_mpdf.php';
            require_once __DIR__ . '/../Invoice/mailer.php';

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
                    'pickup_time' => $bRow['pickup_time'] ?? '',
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

