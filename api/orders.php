<?php
/**
 * My Orders Page & API - Private Hire
 * - Page view: renders orders template
 * - API mode: handles order-specific actions for Orders screen
 */

session_start();

// Parse action first to determine if this is a page view or API request
$input = json_decode(file_get_contents('php://input'), true);
if (!is_array($input)) {
    $input = $_POST;
}
$action = $input['action'] ?? $_GET['action'] ?? '';

// ===== PAGE VIEW MODE (no action) =====
if (empty($action)) {
    $title = 'Private Hire - My Orders';
    $currentPage = 'orders';

    require_once __DIR__ . '/../config/env.php';
    require_once __DIR__ . '/../Database/db.php';

    $isLoggedIn = isset($_SESSION['logged_in']) && $_SESSION['logged_in'];
    $currentUser = $isLoggedIn ? ($_SESSION['full_name'] ?? $_SESSION['username'] ?? $_SESSION['email'] ?? null) : null;
    $userRole = $_SESSION['role'] ?? 'user';

    if (!$isLoggedIn) {
        $_SESSION['login_flash'] = [
            'type' => 'error',
            'message' => 'Please sign in to view your orders.'
        ];
        header('Location: /');
        exit;
    }

    require __DIR__ . '/../templates/orders.html.php';
    exit;
}

// ===== API MODE =====
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if (($_SERVER['REQUEST_METHOD'] ?? '') === 'OPTIONS') {
    exit(0);
}

require_once __DIR__ . '/../Database/db.php';
require_once __DIR__ . '/../sql/BookingRepository.php';
require_once __DIR__ . '/../sql/VehicleRepository.php';
require_once __DIR__ . '/../sql/VehicleImageRepository.php';
require_once __DIR__ . '/notification-helpers.php';
require_once __DIR__ . '/../lib/payments/PayPalGateway.php';

$bookingRepo = new BookingRepository($pdo);
$vehicleRepo = new VehicleRepository($pdo);
$vehicleImageRepo = new VehicleImageRepository($pdo);
$paypalGateway = new PayPalGateway();

function requireAuthOrders(): void {
    if (!isset($_SESSION['logged_in']) || !$_SESSION['logged_in']) {
        echo json_encode(['success' => false, 'message' => 'Authentication required.', 'require_login' => true]);
        exit;
    }
}

function parseOrderPickupDateTime(array $booking): ?DateTimeImmutable {
    $pickupDateRaw = trim((string)($booking['pickup_date'] ?? ''));
    if ($pickupDateRaw === '') {
        return null;
    }

    $pickupTimeRaw = trim((string)($booking['pickup_time'] ?? ''));
    $candidates = [];

    if ($pickupTimeRaw !== '') {
        $timeFormats = ['H:i:s', 'H:i', 'g:ia', 'g:iA', 'h:ia', 'h:iA', 'h:i a', 'h:i A'];
        foreach ($timeFormats as $timeFmt) {
            $candidates[] = ['Y-m-d ' . $timeFmt, $pickupDateRaw . ' ' . $pickupTimeRaw];
            $candidates[] = ['Y-m-d\\TH:i:s', $pickupDateRaw . 'T' . $pickupTimeRaw];
        }
    }

    $candidates[] = ['Y-m-d H:i:s', $pickupDateRaw];
    $candidates[] = ['Y-m-d\\TH:i:s', $pickupDateRaw];
    $candidates[] = ['Y-m-d\\TH:i', $pickupDateRaw];
    $candidates[] = ['Y-m-d', $pickupDateRaw];

    foreach ($candidates as $candidate) {
        [$format, $value] = $candidate;
        $dt = DateTimeImmutable::createFromFormat($format, $value);
        if ($dt instanceof DateTimeImmutable) {
            return $dt;
        }
    }

    try {
        return new DateTimeImmutable($pickupDateRaw);
    } catch (Exception $e) {
        return null;
    }
}

function hasAtLeastHoursBeforePickupOrders(array $booking, int $hours): bool {
    $pickupAt = parseOrderPickupDateTime($booking);
    if (!$pickupAt) {
        return false;
    }
    $now = new DateTimeImmutable('now');
    $secondsRemaining = $pickupAt->getTimestamp() - $now->getTimestamp();
    return $secondsRemaining >= ($hours * 3600);
}

function buildCutoffErrorMessageOrders(int $hours): string {
    return 'Booking cannot be modified or cancelled within ' . $hours . ' hours before pickup.';
}

function countAvailableVehiclesForTierAndSeats(PDO $pdo, string $tier, int $seats): int {
    $normalizedTier = strtolower(trim($tier));
    if ($normalizedTier === 'premium') {
        $normalizedTier = 'luxury';
    }

    $stmt = $pdo->prepare(
        "SELECT COUNT(*)
         FROM vehicles
         WHERE status = 'available'
           AND seats >= ?
           AND LOWER(CASE WHEN service_tier = 'premium' THEN 'luxury' ELSE service_tier END) = ?"
    );
    $stmt->execute([$seats, $normalizedTier]);
    return (int)$stmt->fetchColumn();
}

if ($action === 'my-orders') {
    requireAuthOrders();
    $userId = $_SESSION['user_id'];

    try {
        $orders = $bookingRepo->listUserBookings($userId);

        foreach ($orders as &$order) {
            $order['is_renter'] = ($order['renter_id'] === $userId);
            $order['is_owner'] = ($order['owner_id'] === $userId);

            $hideVehicleForRenter = (
                $order['is_renter']
                && (($order['booking_type'] ?? '') === 'minicab')
                && in_array((string)($order['status'] ?? ''), ['pending', 'confirmed'], true)
            );

            if ($hideVehicleForRenter) {
                $order['brand'] = null;
                $order['model'] = null;
                $order['year'] = null;
                $order['category'] = null;
                $order['thumbnail_url'] = '';
            }

            if (!$hideVehicleForRenter) {
                try {
                    $imgs = $vehicleImageRepo->listByVehicleId($order['vehicle_id']);
                    if ($imgs) {
                        $img = $imgs[0];
                        if (!empty($img['storage_path'])) {
                            require_once __DIR__ . '/supabase-storage.php';
                            $storageHelper = new SupabaseStorage();
                            $order['thumbnail_url'] = $storageHelper->getPublicUrl($img['storage_path']);
                        } elseif ($img['image_data']) {
                            $imgData = is_resource($img['image_data']) ? stream_get_contents($img['image_data']) : $img['image_data'];
                            $order['thumbnail_url'] = 'data:' . $img['mime_type'] . ';base64,' . base64_encode($imgData);
                        } else {
                            $order['thumbnail_url'] = '';
                        }
                    } else {
                        $order['thumbnail_url'] = '';
                    }
                } catch (Exception $e) {
                    $order['thumbnail_url'] = '';
                }
            }
        }

        echo json_encode(['success' => true, 'orders' => $orders]);
    } catch (PDOException $e) {
        echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
    }
    exit;
}

if ($action === 'modify-booking') {
    requireAuthOrders();

    $bookingId = trim((string)($input['booking_id'] ?? ''));
    if ($bookingId === '') {
        echo json_encode(['success' => false, 'message' => 'Booking ID is required.']);
        exit;
    }

    $allowedKeys = ['pickup_location', 'return_location', 'ride_tier', 'number_of_passengers'];
    $updates = [];
    foreach ($allowedKeys as $key) {
        if (array_key_exists($key, $input)) {
            $updates[$key] = $input[$key];
        }
    }

    if (empty($updates)) {
        echo json_encode(['success' => false, 'message' => 'No editable fields provided.']);
        exit;
    }

    foreach ($input as $key => $_value) {
        if (in_array($key, ['action', 'booking_id'], true)) {
            continue;
        }
        if (!in_array($key, $allowedKeys, true)) {
            echo json_encode(['success' => false, 'message' => 'Only pickup location, destination, service tier, and seats can be modified.']);
            exit;
        }
    }

    try {
        $booking = $bookingRepo->getBookingForCustomerEdit($bookingId);
        if (!$booking) {
            echo json_encode(['success' => false, 'message' => 'Booking not found.']);
            exit;
        }

        $userId = (string)($_SESSION['user_id'] ?? '');
        if ((string)($booking['renter_id'] ?? '') !== $userId) {
            echo json_encode(['success' => false, 'message' => 'You are not allowed to modify this booking.']);
            exit;
        }

        if ((string)($booking['status'] ?? '') !== 'pending') {
            echo json_encode(['success' => false, 'message' => 'Only pending bookings can be modified.']);
            exit;
        }

        if (!hasAtLeastHoursBeforePickupOrders($booking, 24)) {
            echo json_encode(['success' => false, 'message' => buildCutoffErrorMessageOrders(24)]);
            exit;
        }

        if (array_key_exists('pickup_location', $updates)) {
            $updates['pickup_location'] = trim((string)$updates['pickup_location']);
            if ($updates['pickup_location'] === '') {
                echo json_encode(['success' => false, 'message' => 'Pickup location is required.']);
                exit;
            }
        }

        if (array_key_exists('return_location', $updates)) {
            $updates['return_location'] = trim((string)$updates['return_location']);
            if ($updates['return_location'] === '') {
                echo json_encode(['success' => false, 'message' => 'Destination is required.']);
                exit;
            }
        }

        if (array_key_exists('ride_tier', $updates)) {
            $updates['ride_tier'] = strtolower(trim((string)$updates['ride_tier']));
            if (!in_array($updates['ride_tier'], ['eco', 'standard', 'luxury'], true)) {
                echo json_encode(['success' => false, 'message' => 'Invalid service tier.']);
                exit;
            }
        }

        if (array_key_exists('number_of_passengers', $updates)) {
            $updates['number_of_passengers'] = (int)$updates['number_of_passengers'];
            if (!in_array($updates['number_of_passengers'], [4, 7], true)) {
                echo json_encode(['success' => false, 'message' => 'Seats must be 4 or 7.']);
                exit;
            }
        }

        $effectiveTier = strtolower(trim((string)($updates['ride_tier'] ?? $booking['ride_tier'] ?? '')));
        $effectiveSeats = (int)($updates['number_of_passengers'] ?? $booking['number_of_passengers'] ?? 0);
        if (in_array($effectiveTier, ['eco', 'standard', 'luxury', 'premium'], true) && in_array($effectiveSeats, [4, 7], true)) {
            $availableCount = countAvailableVehiclesForTierAndSeats($pdo, $effectiveTier, $effectiveSeats);
            if ($availableCount <= 0) {
                echo json_encode([
                    'success' => false,
                    'message' => 'No available vehicles for selected service tier and seats. Please choose another option.',
                ]);
                exit;
            }
        }

        $updated = $bookingRepo->updateCustomerEditableFields($bookingId, $updates);
        if (!$updated) {
            echo json_encode(['success' => false, 'message' => 'No changes were saved.']);
            exit;
        }

        $latest = $bookingRepo->getBookingWithUserAndDriver($bookingId);
        echo json_encode([
            'success' => true,
            'message' => 'Booking updated successfully.',
            'booking' => [
                'id' => $latest['id'] ?? $bookingId,
                'pickup_location' => $latest['pickup_location'] ?? ($updates['pickup_location'] ?? null),
                'return_location' => $latest['return_location'] ?? ($updates['return_location'] ?? null),
                'ride_tier' => $latest['ride_tier'] ?? ($updates['ride_tier'] ?? null),
                'number_of_passengers' => $latest['number_of_passengers'] ?? ($updates['number_of_passengers'] ?? null),
            ]
        ]);
    } catch (PDOException $e) {
        echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
    }
    exit;
}

if ($action === 'update-status') {
    requireAuthOrders();
    $userId = $_SESSION['user_id'];
    $bookingId = $input['booking_id'] ?? '';
    $newStatus = $input['status'] ?? '';

    if (empty($bookingId) || empty($newStatus)) {
        echo json_encode(['success' => false, 'message' => 'Booking ID and status are required.']);
        exit;
    }

    $validStatuses = ['confirmed', 'in_progress', 'completed', 'cancelled'];
    if (!in_array($newStatus, $validStatuses, true)) {
        echo json_encode(['success' => false, 'message' => 'Invalid status.']);
        exit;
    }

    try {
        $booking = $bookingRepo->getBookingWithUserAndDriver($bookingId);
        if (!$booking) {
            echo json_encode(['success' => false, 'message' => 'Booking not found.']);
            exit;
        }

        $currentUserId = (string)($userId ?? '');
        $bookingOwnerId = (string)($booking['owner_id'] ?? '');
        $bookingRenterId = (string)($booking['renter_id'] ?? '');

        $isOwner = ($bookingOwnerId !== '' && $bookingOwnerId === $currentUserId);
        $isRenter = ($bookingRenterId !== '' && $bookingRenterId === $currentUserId);
        $isAdmin = (($_SESSION['role'] ?? '') === 'admin');

        $allowed = false;
        $currentStatus = strtolower(trim((string)($booking['status'] ?? '')));

        if ($newStatus === 'confirmed' && $currentStatus === 'pending' && ($isOwner || $isAdmin)) {
            $allowed = true;
        } elseif ($newStatus === 'in_progress' && $currentStatus === 'confirmed' && ($isOwner || $isAdmin)) {
            $allowed = true;
        } elseif ($newStatus === 'completed' && $currentStatus === 'in_progress' && ($isOwner || $isAdmin)) {
            $allowed = true;
        } elseif ($newStatus === 'cancelled' && in_array($currentStatus, ['pending', 'confirmed'], true) && ($isOwner || $isRenter || $isAdmin)) {
            $allowed = true;
        }

        if (!$allowed) {
            echo json_encode(['success' => false, 'message' => 'You are not allowed to perform this action.']);
            exit;
        }

        if ($newStatus === 'cancelled' && $isRenter && !$isOwner && !$isAdmin) {
            if (!hasAtLeastHoursBeforePickupOrders($booking, 24)) {
                echo json_encode(['success' => false, 'message' => buildCutoffErrorMessageOrders(24)]);
                exit;
            }
        }

        if ($newStatus === 'cancelled') {
            $payment = $bookingRepo->getPaymentByBookingId($bookingId);
            if ($payment) {
                $paymentMethod = strtolower(trim((string)($payment['method'] ?? '')));
                $paymentStatus = strtolower(trim((string)($payment['status'] ?? '')));

                if ($paymentMethod === 'paypal' && $paymentStatus === 'paid') {
                    $paymentDetails = $payment['payment_details'] ?? [];
                    if (is_string($paymentDetails)) {
                        $decoded = json_decode($paymentDetails, true);
                        $paymentDetails = is_array($decoded) ? $decoded : [];
                    }
                    if (!is_array($paymentDetails)) {
                        $paymentDetails = [];
                    }

                    $captureId = trim((string)($paymentDetails['capture_id'] ?? ''));
                    if ($captureId === '') {
                        echo json_encode([
                            'success' => false,
                            'message' => 'Unable to auto-refund PayPal payment because capture ID is missing. Please contact support.',
                        ]);
                        exit;
                    }

                    $refund = $paypalGateway->refundCapture(
                        $captureId,
                        isset($payment['amount']) ? (float)$payment['amount'] : null,
                        'GBP'
                    );

                    if (empty($refund['success'])) {
                        echo json_encode([
                            'success' => false,
                            'message' => $refund['message'] ?? 'PayPal refund failed. Cancellation was not completed.',
                        ]);
                        exit;
                    }

                    $bookingRepo->updatePaymentByBookingId($bookingId, 'refunded', [
                        'provider' => 'paypal',
                        'stage' => 'refund',
                        'capture_id' => $captureId,
                        'refund_id' => $refund['refund_id'] ?? null,
                        'refund_status' => $refund['status'] ?? null,
                        'response' => $refund['raw'] ?? [],
                        'mock' => !empty($refund['mock']),
                        'refunded_at' => gmdate('c'),
                    ]);
                }
            }
        }

        $bookingStatusUpdated = $bookingRepo->updateBookingStatus($bookingId, $newStatus);
        if (!$bookingStatusUpdated) {
            echo json_encode(['success' => false, 'message' => 'Failed to update booking status.']);
            exit;
        }

        $vehicleId = $booking['vehicle_id'];
        $updatedVehicleStatus = null;

        if ($newStatus === 'confirmed') {
            $bookingRepo->updateVehicleStatus($vehicleId, 'rented');
            $updatedVehicleStatus = 'rented';
            $bookingRepo->incrementVehicleStats($vehicleId, 'bookings');
        } elseif ($newStatus === 'in_progress') {
            $bookingRepo->updateVehicleStatus($vehicleId, 'rented');
            $updatedVehicleStatus = 'rented';
        } elseif ($newStatus === 'completed') {
            $vehicleUpdateSuccess = $bookingRepo->updateVehicleStatus($vehicleId, 'available');
            if (!$vehicleUpdateSuccess) {
                echo json_encode(['success' => false, 'message' => 'Failed to update vehicle status to available.']);
                exit;
            }
            $updatedVehicleStatus = 'available';
        } elseif ($newStatus === 'cancelled') {
            if (!empty($vehicleId)) {
                $vehicleStatus = $bookingRepo->getVehicleStatus($vehicleId);
                if ($vehicleStatus === 'rented') {
                    $bookingRepo->updateVehicleStatus($vehicleId, 'available');
                    $updatedVehicleStatus = 'available';
                } else {
                    $updatedVehicleStatus = $vehicleStatus;
                }
            }
        }

        if ($newStatus === 'completed') {
            $bookingRepo->markPaymentAsPaid($bookingId);
        }

        $renterId = $booking['renter_id'];
        $ownerId = $booking['owner_id'];

        if ($newStatus === 'confirmed') {
            createNotification($pdo, $renterId, 'booking', '✅ Booking Confirmed', 'Your booking has been confirmed by the owner. Get ready for pickup!');
            createNotification($pdo, $ownerId, 'booking', '✅ Booking Confirmed', 'You confirmed a booking.');
        } elseif ($newStatus === 'in_progress') {
            createNotification($pdo, $renterId, 'booking', '🚗 Trip Started', 'Your trip has started. Drive safely!');
            createNotification($pdo, $ownerId, 'alert', '🚗 Vehicle Delivered', 'Your vehicle has been delivered to the renter.');
        } elseif ($newStatus === 'completed') {
            createNotification($pdo, $renterId, 'payment', '🎉 Trip Completed', 'Your trip is complete. Payment has been processed. Thank you!');
            createNotification($pdo, $ownerId, 'payment', '💰 Payment Received', 'Your trip is completed. Payment has been received.');
        } elseif ($newStatus === 'cancelled') {
            createNotification($pdo, $renterId, 'alert', '❌ Booking Cancelled', 'Your booking has been cancelled' . ($isOwner ? ' by the owner.' : '.'));
            if (!$isOwner) {
                createNotification($pdo, $ownerId, 'alert', '❌ Booking Cancelled', 'A booking has been cancelled' . ($isRenter ? ' by the renter.' : '.'));
            }
        }

        echo json_encode([
            'success' => true,
            'message' => 'Booking status updated to ' . $newStatus . '.',
            'booking_id' => $bookingId,
            'new_status' => $newStatus,
            'vehicle_id' => $vehicleId,
            'vehicle_status' => $updatedVehicleStatus,
        ]);
    } catch (PDOException $e) {
        echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
    }
    exit;
}

if ($action === 'submit-review') {
    requireAuthOrders();
    $userId = $_SESSION['user_id'];
    $bookingId = trim((string)($input['booking_id'] ?? ''));
    $rating = (int)($input['rating'] ?? 0);
    $content = trim((string)($input['content'] ?? ''));

    if ($bookingId === '' || $rating < 1 || $rating > 5 || $content === '') {
        echo json_encode(['success' => false, 'message' => 'Booking ID, rating (1-5), and review content are required.']);
        exit;
    }

    try {
        $booking = $bookingRepo->getBookingInfo($bookingId);
        if (!$booking) {
            echo json_encode(['success' => false, 'message' => 'Booking not found.']);
            exit;
        }
        if ((string)$booking['renter_id'] !== (string)$userId) {
            echo json_encode(['success' => false, 'message' => 'Only the renter can review this booking.']);
            exit;
        }
        if ((string)$booking['status'] !== 'completed') {
            echo json_encode(['success' => false, 'message' => 'You can only review completed orders.']);
            exit;
        }
        if ($bookingRepo->userHasReviewed($bookingId, $userId)) {
            echo json_encode(['success' => false, 'message' => 'You have already reviewed this booking.']);
            exit;
        }

        $bookingRepo->insertReview($bookingId, $booking['vehicle_id'], $userId, $rating, $content);
        $avgData = $bookingRepo->getVehicleReviewStats($booking['vehicle_id']);
        if ($avgData['avg_rating'] !== null) {
            $bookingRepo->updateVehicleRating($booking['vehicle_id'], (float)$avgData['avg_rating'], (int)$avgData['total']);
        }

        echo json_encode(['success' => true, 'message' => 'Review submitted successfully! Thank you for your feedback.']);
    } catch (PDOException $e) {
        echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
    }
    exit;
}

echo json_encode(['success' => false, 'message' => 'Unknown action: ' . $action]);
exit;