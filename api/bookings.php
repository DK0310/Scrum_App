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

// Include notification helper functions
require_once __DIR__ . '/notification-helpers.php';

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
    $distanceKm = isset($input['distance_km']) ? floatval($input['distance_km']) : null;
    $frontendTransferCost = isset($input['transfer_cost']) ? floatval($input['transfer_cost']) : null;

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
        $stmt = $pdo->prepare("SELECT id, owner_id, price_per_day, price_per_week, price_per_month, category, status FROM vehicles WHERE id = ?");
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
            // Airport transfer: use distance-based cost sent from frontend
            if ($frontendTransferCost !== null && $frontendTransferCost > 0) {
                $subtotal = $frontendTransferCost;
            } elseif ($distanceKm !== null && $distanceKm > 0) {
                // Fallback: calculate based on category rate
                $category = strtolower($vehicle['category'] ?? 'sedan');
                $ratePerKm = 1; // default
                if (in_array($category, ['minibus', 'van', 'sport'])) $ratePerKm = 2;
                elseif ($category === 'luxury') $ratePerKm = 5;
                $subtotal = round($distanceKm * $ratePerKm, 2);
            } else {
                $subtotal = $pricePerDay; // fallback to daily rate
            }
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
                discount_amount, total_amount, promo_code, special_requests, driver_requested, distance_km, transfer_cost, status)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'pending')
            RETURNING id, created_at
        ");
        $driverRequested = ($bookingType === 'with-driver') ? 't' : 'f';
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
            $driverRequested,
            $distanceKm,
            ($bookingType === 'airport' && $frontendTransferCost !== null) ? $frontendTransferCost : null
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

        // --- Notifications ---
        // Get vehicle name for notification
        $vStmt = $pdo->prepare("SELECT brand, model FROM vehicles WHERE id = ?");
        $vStmt->execute([$vehicleId]);
        $vInfo = $vStmt->fetch(PDO::FETCH_ASSOC);
        $vehicleName = ($vInfo ? $vInfo['brand'] . ' ' . $vInfo['model'] : 'Vehicle');

        // Notify renter: booking created
        createNotification($pdo, $renterId, 'booking',
            '📋 Booking Created',
            "Your booking for {$vehicleName} has been submitted. Pickup: {$pickupDate}. Total: \${$totalAmount}. Waiting for owner confirmation."
        );

        // Notify owner: new booking request
        createNotification($pdo, $vehicle['owner_id'], 'booking',
            '🆕 New Booking Request',
            "You have a new booking request for your {$vehicleName}. Pickup: {$pickupDate}. Amount: \${$totalAmount}."
        );

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

// ==========================================================
// MY ORDERS - List bookings for current user (renter + owner)
// ==========================================================
if ($action === 'my-orders') {
    requireAuth();
    $userId = $_SESSION['user_id'];

    try {
        $stmt = $pdo->prepare("
            SELECT b.id, b.renter_id, b.owner_id, b.vehicle_id, b.booking_type,
                   b.pickup_date, b.return_date, b.pickup_location, b.return_location,
                   b.airport_name, b.total_days, b.price_per_day, b.subtotal,
                   b.discount_amount, b.total_amount, b.promo_code, b.status,
                   b.special_requests, b.driver_requested, b.created_at,
                   b.confirmed_at, b.completed_at, b.cancelled_at,
                   b.distance_km, b.transfer_cost,
                   v.brand, v.model, v.year, v.category,
                   u_renter.full_name AS renter_name,
                   u_renter.email AS renter_email,
                   p.method AS payment_method,
                   p.status AS payment_status
            FROM bookings b
            JOIN vehicles v ON b.vehicle_id = v.id
            LEFT JOIN users u_renter ON b.renter_id = u_renter.id
            LEFT JOIN payments p ON p.booking_id = b.id
            WHERE b.renter_id = ? OR b.owner_id = ?
            ORDER BY b.created_at DESC
        ");
        $stmt->execute([$userId, $userId]);
        $orders = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Get vehicle thumbnails & set role flags
        foreach ($orders as &$order) {
            // Set boolean flags by comparing IDs directly in PHP
            $order['is_renter'] = ($order['renter_id'] === $userId);
            $order['is_owner'] = ($order['owner_id'] === $userId);

            // Get vehicle thumbnail
            try {
                $imgStmt = $pdo->prepare("SELECT image_data, mime_type FROM vehicle_images WHERE vehicle_id = ? ORDER BY is_thumbnail DESC, created_at ASC LIMIT 1");
                $imgStmt->execute([$order['vehicle_id']]);
                $img = $imgStmt->fetch(PDO::FETCH_ASSOC);
                if ($img && $img['image_data']) {
                    $imgData = is_resource($img['image_data']) ? stream_get_contents($img['image_data']) : $img['image_data'];
                    $order['thumbnail_url'] = 'data:' . $img['mime_type'] . ';base64,' . base64_encode($imgData);
                } else {
                    $order['thumbnail_url'] = '';
                }
            } catch (Exception $e) {
                $order['thumbnail_url'] = '';
            }
        }

        echo json_encode(['success' => true, 'orders' => $orders]);
    } catch (PDOException $e) {
        echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
    }
    exit;
}

// ==========================================================
// UPDATE BOOKING STATUS (Owner: confirm, deliver, complete | Renter: cancel)
// ==========================================================
if ($action === 'update-status') {
    requireAuth();
    $userId = $_SESSION['user_id'];
    $bookingId = $input['booking_id'] ?? '';
    $newStatus = $input['status'] ?? '';

    if (empty($bookingId) || empty($newStatus)) {
        echo json_encode(['success' => false, 'message' => 'Booking ID and status are required.']);
        exit;
    }

    $validStatuses = ['confirmed', 'in_progress', 'completed', 'cancelled'];
    if (!in_array($newStatus, $validStatuses)) {
        echo json_encode(['success' => false, 'message' => 'Invalid status.']);
        exit;
    }

    try {
        // Get booking
        $stmt = $pdo->prepare("SELECT id, renter_id, owner_id, vehicle_id, status FROM bookings WHERE id = ?");
        $stmt->execute([$bookingId]);
        $booking = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$booking) {
            echo json_encode(['success' => false, 'message' => 'Booking not found.']);
            exit;
        }

        $isOwner = ($booking['owner_id'] === $userId);
        $isRenter = ($booking['renter_id'] === $userId);
        $isAdmin = (($_SESSION['role'] ?? '') === 'admin');

        // Permission & transition checks
        $allowed = false;
        $currentStatus = $booking['status'];

        if ($newStatus === 'confirmed' && $currentStatus === 'pending' && ($isOwner || $isAdmin)) {
            $allowed = true;
        } elseif ($newStatus === 'in_progress' && $currentStatus === 'confirmed' && ($isOwner || $isAdmin)) {
            $allowed = true;
        } elseif ($newStatus === 'completed' && $currentStatus === 'in_progress' && ($isOwner || $isAdmin)) {
            $allowed = true;
        } elseif ($newStatus === 'cancelled' && $currentStatus === 'pending' && ($isOwner || $isRenter || $isAdmin)) {
            $allowed = true;
        }

        if (!$allowed) {
            echo json_encode(['success' => false, 'message' => 'You are not allowed to perform this action.']);
            exit;
        }

        // Update status
        $extraSql = '';
        if ($newStatus === 'confirmed') $extraSql = ', confirmed_at = NOW()';
        if ($newStatus === 'completed') $extraSql = ', completed_at = NOW()';
        if ($newStatus === 'cancelled') $extraSql = ', cancelled_at = NOW()';

        $pdo->prepare("UPDATE bookings SET status = ?::booking_status" . $extraSql . " WHERE id = ?")->execute([$newStatus, $bookingId]);

        // Update vehicle status & stats based on booking transition
        $vehicleId = $booking['vehicle_id'];

        if ($newStatus === 'in_progress') {
            // Delivery started → vehicle is now rented, increment total_bookings
            $pdo->prepare("UPDATE vehicles SET status = 'rented'::vehicle_status, total_bookings = total_bookings + 1 WHERE id = ?")->execute([$vehicleId]);
        }

        if ($newStatus === 'completed') {
            // Order done → vehicle available again
            $pdo->prepare("UPDATE vehicles SET status = 'available'::vehicle_status WHERE id = ?")->execute([$vehicleId]);
        }

        if ($newStatus === 'cancelled') {
            // If vehicle was rented for this booking, make it available again
            $pdo->prepare("UPDATE vehicles SET status = 'available'::vehicle_status WHERE id = ? AND status = 'rented'::vehicle_status")->execute([$vehicleId]);
        }

        // Update payment status if completed
        if ($newStatus === 'completed') {
            $pdo->prepare("UPDATE payments SET status = 'paid'::payment_status WHERE booking_id = ?")->execute([$bookingId]);
        }
        if ($newStatus === 'cancelled') {
            $pdo->prepare("UPDATE payments SET status = 'failed'::payment_status WHERE booking_id = ?")->execute([$bookingId]);
        }

        // --- Notifications for status changes ---
        $vStmt = $pdo->prepare("SELECT v.brand, v.model FROM vehicles v WHERE v.id = ?");
        $vStmt->execute([$booking['vehicle_id']]);
        $vInfo = $vStmt->fetch(PDO::FETCH_ASSOC);
        $vehicleName = ($vInfo ? $vInfo['brand'] . ' ' . $vInfo['model'] : 'Vehicle');

        $renterId = $booking['renter_id'];
        $ownerId = $booking['owner_id'];

        if ($newStatus === 'confirmed') {
            createNotification($pdo, $renterId, 'booking',
                '✅ Booking Confirmed',
                "Your booking for {$vehicleName} has been confirmed by the owner. Get ready for pickup!"
            );
            createNotification($pdo, $ownerId, 'booking',
                '✅ Booking Confirmed',
                "You confirmed a booking for your {$vehicleName}."
            );
        } elseif ($newStatus === 'in_progress') {
            createNotification($pdo, $renterId, 'booking',
                '🚗 Trip Started',
                "Your trip with {$vehicleName} has started. Drive safely!"
            );
            createNotification($pdo, $ownerId, 'alert',
                '🚗 Vehicle Delivered',
                "Your {$vehicleName} has been delivered to the renter."
            );
        } elseif ($newStatus === 'completed') {
            createNotification($pdo, $renterId, 'payment',
                '🎉 Trip Completed',
                "Your trip with {$vehicleName} is complete. Payment has been processed. Thank you!"
            );
            createNotification($pdo, $ownerId, 'payment',
                '💰 Payment Received',
                "Your {$vehicleName} trip is completed. Payment has been received."
            );
        } elseif ($newStatus === 'cancelled') {
            $cancelledBy = $isOwner ? 'owner' : ($isRenter ? 'you' : 'admin');
            createNotification($pdo, $renterId, 'alert',
                '❌ Booking Cancelled',
                "Your booking for {$vehicleName} has been cancelled" . ($isOwner ? " by the owner." : ".")
            );
            if (!$isOwner) {
                createNotification($pdo, $ownerId, 'alert',
                    '❌ Booking Cancelled',
                    "A booking for your {$vehicleName} has been cancelled" . ($isRenter ? " by the renter." : ".")
                );
            }
        }

        echo json_encode(['success' => true, 'message' => 'Booking status updated to ' . $newStatus . '.']);
    } catch (PDOException $e) {
        echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
    }
    exit;
}

echo json_encode(['success' => false, 'message' => 'Unknown action: ' . $action]);
