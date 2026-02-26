<?php
/**
 * Bookings API - DriveNow
 * Handles booking creation, promo validation, user's saved promos
 */

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit(0);
}

session_start();
require_once __DIR__ . '/../Database/db.php';

$input = json_decode(file_get_contents('php://input'), true);
$action = $input['action'] ?? $_GET['action'] ?? '';

if (empty($action)) {
    echo json_encode(['success' => false, 'message' => 'Action is required.']);
    exit;
}

// Helper: require login
function requireAuth() {
    if (!isset($_SESSION['logged_in']) || !$_SESSION['logged_in']) {
        echo json_encode(['success' => false, 'message' => 'Authentication required.', 'require_login' => true]);
        exit;
    }
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
        $stmt = $pdo->prepare("
            SELECT id, code, title, description, discount_type, discount_value, 
                   min_booking_days, max_uses, total_used, starts_at, expires_at, is_active
            FROM promotions
            WHERE UPPER(code) = ? AND is_active = true
        ");
        $stmt->execute([$code]);
        $promo = $stmt->fetch(PDO::FETCH_ASSOC);

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
        $stmt = $pdo->query("
            SELECT code, title, description, discount_type, discount_value, 
                   min_booking_days, expires_at
            FROM promotions
            WHERE is_active = true 
              AND (expires_at IS NULL OR expires_at > NOW())
              AND (max_uses IS NULL OR total_used < max_uses)
            ORDER BY discount_value DESC
        ");
        $promos = $stmt->fetchAll(PDO::FETCH_ASSOC);

        foreach ($promos as &$p) {
            $p['discount_value'] = (float)$p['discount_value'];
            $p['min_booking_days'] = (int)$p['min_booking_days'];
        }

        echo json_encode(['success' => true, 'promos' => $promos]);
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
    $bookingType = $input['booking_type'] ?? 'self-drive';
    $pickupDate = $input['pickup_date'] ?? '';
    $returnDate = $input['return_date'] ?? null;
    $pickupLocation = $input['pickup_location'] ?? '';
    $returnLocation = $input['return_location'] ?? '';
    $airportName = $input['airport_name'] ?? null;
    $specialRequests = $input['special_requests'] ?? '';
    $promoCode = $input['promo_code'] ?? '';
    $paymentMethod = $input['payment_method'] ?? 'cash';

    // Validate required fields
    if (empty($vehicleId) || empty($pickupDate) || empty($pickupLocation)) {
        echo json_encode(['success' => false, 'message' => 'Vehicle, pickup date, and pickup location are required.']);
        exit;
    }

    // Validate booking type
    if (!in_array($bookingType, ['self-drive', 'with-driver', 'airport'])) {
        echo json_encode(['success' => false, 'message' => 'Invalid booking type.']);
        exit;
    }

    // For self-drive and with-driver, return_date is required
    if (in_array($bookingType, ['self-drive', 'with-driver']) && empty($returnDate)) {
        echo json_encode(['success' => false, 'message' => 'Return date is required for this booking type.']);
        exit;
    }

    // For airport, airport_name is required
    if ($bookingType === 'airport' && empty($airportName)) {
        echo json_encode(['success' => false, 'message' => 'Airport name is required for airport transfer.']);
        exit;
    }

    // Validate payment method
    $validMethods = ['cash', 'bank_transfer', 'credit_card', 'paypal'];
    if (!in_array($paymentMethod, $validMethods)) {
        echo json_encode(['success' => false, 'message' => 'Invalid payment method.']);
        exit;
    }

    try {
        // Get vehicle info
        $stmt = $pdo->prepare("SELECT id, owner_id, price_per_day, price_per_week, price_per_month, status FROM vehicles WHERE id = ?");
        $stmt->execute([$vehicleId]);
        $vehicle = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$vehicle) {
            echo json_encode(['success' => false, 'message' => 'Vehicle not found.']);
            exit;
        }

        if ($vehicle['status'] !== 'available') {
            echo json_encode(['success' => false, 'message' => 'This vehicle is not currently available.']);
            exit;
        }

        if ($vehicle['owner_id'] === $renterId) {
            echo json_encode(['success' => false, 'message' => 'You cannot book your own vehicle.']);
            exit;
        }

        // Calculate pricing
        $pricePerDay = (float)$vehicle['price_per_day'];
        $totalDays = 1;
        $subtotal = $pricePerDay;

        if ($bookingType === 'airport') {
            $totalDays = 1;
            $subtotal = $pricePerDay;
        } else {
            $d1 = new DateTime($pickupDate);
            $d2 = new DateTime($returnDate);
            $diff = $d1->diff($d2);
            $totalDays = max(1, $diff->days);

            // Smart pricing: use weekly/monthly rate if applicable
            if ($totalDays >= 30 && $vehicle['price_per_month']) {
                $months = floor($totalDays / 30);
                $remainingDays = $totalDays % 30;
                $subtotal = ($months * (float)$vehicle['price_per_month']) + ($remainingDays * $pricePerDay);
            } elseif ($totalDays >= 7 && $vehicle['price_per_week']) {
                $weeks = floor($totalDays / 7);
                $remainingDays = $totalDays % 7;
                $subtotal = ($weeks * (float)$vehicle['price_per_week']) + ($remainingDays * $pricePerDay);
            } else {
                $subtotal = $totalDays * $pricePerDay;
            }
        }

        // Apply promo code
        $discountAmount = 0;
        $appliedPromo = null;
        if (!empty($promoCode)) {
            $stmt = $pdo->prepare("
                SELECT * FROM promotions
                WHERE UPPER(code) = ? AND is_active = true
                  AND (expires_at IS NULL OR expires_at > NOW())
                  AND (max_uses IS NULL OR total_used < max_uses)
            ");
            $stmt->execute([strtoupper($promoCode)]);
            $promo = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($promo) {
                if ($totalDays >= (int)$promo['min_booking_days']) {
                    if ($promo['discount_type'] === 'percentage') {
                        $discountAmount = round($subtotal * (float)$promo['discount_value'] / 100, 2);
                    } else {
                        $discountAmount = min((float)$promo['discount_value'], $subtotal);
                    }
                    $appliedPromo = $promo['code'];

                    // Increment usage count
                    $pdo->prepare("UPDATE promotions SET total_used = total_used + 1 WHERE id = ?")->execute([$promo['id']]);
                }
            }
        }

        $totalAmount = max(0, $subtotal - $discountAmount);

        // Create booking
        $stmt = $pdo->prepare("
            INSERT INTO bookings (renter_id, vehicle_id, owner_id, booking_type, pickup_date, return_date, 
                pickup_location, return_location, airport_name, total_days, price_per_day, subtotal, 
                discount_amount, total_amount, promo_code, special_requests, driver_requested, status)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'pending')
            RETURNING id, created_at
        ");
        $stmt->execute([
            $renterId,
            $vehicleId,
            $vehicle['owner_id'],
            $bookingType,
            $pickupDate,
            $returnDate,
            $pickupLocation,
            $returnLocation,
            $airportName,
            $totalDays,
            $pricePerDay,
            $subtotal,
            $discountAmount,
            $totalAmount,
            $appliedPromo,
            $specialRequests,
            $bookingType === 'with-driver'
        ]);
        $booking = $stmt->fetch(PDO::FETCH_ASSOC);

        // Create payment record
        $pdo->prepare("
            INSERT INTO payments (booking_id, user_id, amount, method, status)
            VALUES (?, ?, ?, ?::payment_method, 'pending')
        ")->execute([
            $booking['id'],
            $renterId,
            $totalAmount,
            $paymentMethod
        ]);

        echo json_encode([
            'success' => true,
            'message' => 'Booking created successfully! You will receive a confirmation email shortly.',
            'booking' => [
                'id' => $booking['id'],
                'total_days' => $totalDays,
                'subtotal' => $subtotal,
                'discount' => $discountAmount,
                'total' => $totalAmount,
                'promo_applied' => $appliedPromo,
                'payment_method' => $paymentMethod,
                'status' => 'pending'
            ]
        ]);
    } catch (PDOException $e) {
        echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
    }
    exit;
}

echo json_encode(['success' => false, 'message' => 'Unknown action: ' . $action]);
