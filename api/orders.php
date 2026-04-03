<?php
/**
 * Orders API - Private Hire
 * JSON-only endpoint for order-related actions.
 */

require_once __DIR__ . '/bootstrap.php';

$input = api_init(['allow_origin' => '*']);
$action = api_action($input);

require_once __DIR__ . '/../Database/db.php';
require_once __DIR__ . '/../sql/BookingRepository.php';
require_once __DIR__ . '/../sql/UserRepository.php';
require_once __DIR__ . '/../sql/VehicleRepository.php';
require_once __DIR__ . '/../sql/VehicleImageRepository.php';
require_once __DIR__ . '/notification-helpers.php';

$bookingRepo = new BookingRepository($pdo);
$userRepo = new UserRepository($pdo);
$vehicleRepo = new VehicleRepository($pdo);
$vehicleImageRepo = new VehicleImageRepository($pdo);

function decodePaymentDetailsOrders($raw): array {
    if (is_array($raw)) {
        return $raw;
    }
    if (is_string($raw) && $raw !== '') {
        $decoded = json_decode($raw, true);
        return is_array($decoded) ? $decoded : [];
    }
    return [];
}

function effectivePaymentMethodOrders(array $payment): string {
    $method = strtolower(trim((string)($payment['method'] ?? '')));
    $details = decodePaymentDetailsOrders($payment['payment_details'] ?? []);
    $original = strtolower(trim((string)($details['original_method'] ?? '')));
    if ($original !== '') {
        return $original;
    }
    return $method;
}

function refundAccountBalanceOrders(UserRepository $userRepo, BookingRepository $bookingRepo, string $bookingId, array $booking, array $payment): bool {
    global $pdo;

    $amount = isset($payment['amount']) ? (float)$payment['amount'] : 0.0;
    if ($amount <= 0) {
        return false;
    }

    $targetUserId = (string)($payment['user_id'] ?? $booking['renter_id'] ?? '');
    if ($targetUserId === '') {
        return false;
    }

    $credited = $userRepo->addAccountBalance($targetUserId, $amount);
    if (!$credited) {
        return false;
    }

    $details = decodePaymentDetailsOrders($payment['payment_details'] ?? []);
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
        'A refund of £' . number_format($amount, 2) . ' has been credited to your account balance for booking #' . $bookingId . '.'
    );

    return true;
}

function isOnlinePaymentMethodOrders(string $method): bool {
    return in_array(strtolower(trim($method)), ['paypal', 'account_balance'], true);
}

function computeMinicabFareOrders(string $rideTier, int $seatCapacity, float $distanceKm): ?float {
    $tier = strtolower(trim($rideTier));
    $ratesPerMile = [
        4 => ['eco' => 2.00, 'standard' => 2.50, 'luxury' => 3.50],
        7 => ['eco' => 3.00, 'standard' => 3.50, 'luxury' => 4.50],
    ];
    if (!isset($ratesPerMile[$seatCapacity][$tier])) {
        return null;
    }

    if ($distanceKm <= 0) {
        return null;
    }

    $distanceMiles = $distanceKm * 0.621371;
    return round($distanceMiles * $ratesPerMile[$seatCapacity][$tier], 2);
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

function requireAuthOrders(): void {
    api_require_auth();
}

if ($action === 'my-orders') {
    api_require_auth();
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
                    $vehicleId = trim((string)($order['vehicle_id'] ?? ''));
                    if ($vehicleId !== '') {
                        $imgs = $vehicleImageRepo->listByVehicleId($vehicleId);
                        if ($imgs) {
                            $img = $imgs[0];
                            if (!empty($img['storage_path'])) {
                                require_once __DIR__ . '/supabase-storage.php';
                                $storageHelper = new SupabaseStorage();
                                $order['thumbnail_url'] = $storageHelper->getPublicUrl($img['storage_path']);
                            } elseif (!empty($img['image_data'])) {
                                $imgData = is_resource($img['image_data']) ? stream_get_contents($img['image_data']) : $img['image_data'];
                                $order['thumbnail_url'] = 'data:' . $img['mime_type'] . ';base64,' . base64_encode($imgData);
                            } else {
                                $order['thumbnail_url'] = '';
                            }
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

    $allowedKeys = [
        'pickup_location',
        'return_location',
        'service_type',
        'pickup_date',
        'pickup_time',
        'ride_tier',
        'number_of_passengers',
        'distance_km'
    ];
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
            echo json_encode(['success' => false, 'message' => 'Only pickup, destination, service type, pickup date/time, ride tier, and seats can be modified.']);
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

        $payment = $bookingRepo->getPaymentByBookingId($bookingId);
        $effectivePaymentMethod = '';
        if ($payment) {
            $effectivePaymentMethod = effectivePaymentMethodOrders($payment);
        } elseif (!empty($booking['payment_method'])) {
            $effectivePaymentMethod = strtolower(trim((string)$booking['payment_method']));
        }

        if ($effectivePaymentMethod === '' || $effectivePaymentMethod !== 'cash') {
            echo json_encode([
                'success' => false,
                'message' => 'Only cash bookings can be modified. For online payments, you can only cancel when pickup is more than 24 hours away.'
            ]);
            exit;
        }

        if (strtolower(trim((string)($booking['booking_type'] ?? ''))) !== 'minicab') {
            echo json_encode(['success' => false, 'message' => 'Only minicab bookings can be modified from My Orders.']);
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

        if (array_key_exists('service_type', $updates)) {
            $updates['service_type'] = strtolower(trim((string)$updates['service_type']));
            if (!in_array($updates['service_type'], ['local', 'long-distance', 'airport-transfer', 'hotel-transfer'], true)) {
                echo json_encode(['success' => false, 'message' => 'Invalid service type.']);
                exit;
            }
        }

        $pickupDateProvided = array_key_exists('pickup_date', $updates);
        $pickupTimeProvided = array_key_exists('pickup_time', $updates);
        if ($pickupDateProvided || $pickupTimeProvided) {
            $newPickupDate = trim((string)($updates['pickup_date'] ?? $booking['pickup_date'] ?? ''));
            $newPickupTime = trim((string)($updates['pickup_time'] ?? $booking['pickup_time'] ?? ''));

            if ($newPickupDate === '') {
                echo json_encode(['success' => false, 'message' => 'Pickup date is required.']);
                exit;
            }

            if ($newPickupTime === '') {
                $newPickupTime = '00:00';
            }

            $originalPickupDate = parseOrderPickupDateTime($booking);
            $newPickupAt = parseOrderPickupDateTime([
                'pickup_date' => $newPickupDate,
                'pickup_time' => $newPickupTime,
            ]);
            if (!$originalPickupDate || !$newPickupAt) {
                echo json_encode(['success' => false, 'message' => 'Invalid pickup date or time.']);
                exit;
            }

            $now = new DateTimeImmutable('now');
            if ($newPickupAt < $now) {
                echo json_encode(['success' => false, 'message' => 'Pickup date and time must be in the future.']);
                exit;
            }

            $maxPickupDate = $originalPickupDate->modify('+7 days');
            if ($newPickupAt > $maxPickupDate) {
                echo json_encode(['success' => false, 'message' => 'Pickup date can only be moved within 7 days from the original date.']);
                exit;
            }

            $updates['pickup_date'] = $newPickupAt->format('Y-m-d');
            $updates['pickup_time'] = $newPickupAt->format('H:i');
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

        $distanceChanged = array_key_exists('distance_km', $updates);
        $pickupChanged = array_key_exists('pickup_location', $updates);
        $destinationChanged = array_key_exists('return_location', $updates);
        if (($pickupChanged || $destinationChanged) && !$distanceChanged) {
            echo json_encode([
                'success' => false,
                'message' => 'Distance is required when changing pick-up or destination to recalculate fare.'
            ]);
            exit;
        }

        $effectiveDistanceKm = (float)($updates['distance_km'] ?? $booking['distance_km'] ?? 0);
        if ($effectiveDistanceKm > 0) {
            $newSubtotal = computeMinicabFareOrders($effectiveTier, $effectiveSeats, $effectiveDistanceKm);
            if ($newSubtotal === null) {
                echo json_encode(['success' => false, 'message' => 'Unable to recalculate fare for selected options.']);
                exit;
            }

            $oldSubtotal = (float)($booking['subtotal'] ?? 0);
            $oldTotal = (float)($booking['total_amount'] ?? $oldSubtotal);
            $fixedDiscount = max(0, $oldSubtotal - $oldTotal);
            $newTotal = max(0, $newSubtotal - $fixedDiscount);

            $updates['distance_km'] = round($effectiveDistanceKm, 2);
            $updates['subtotal'] = $newSubtotal;
            $updates['total_amount'] = $newTotal;
            $updates['transfer_cost'] = $newSubtotal;
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
                'service_type' => $latest['service_type'] ?? ($updates['service_type'] ?? null),
                'pickup_date' => $latest['pickup_date'] ?? ($updates['pickup_date'] ?? null),
                'pickup_time' => $latest['pickup_time'] ?? ($updates['pickup_time'] ?? null),
                'ride_tier' => $latest['ride_tier'] ?? ($updates['ride_tier'] ?? null),
                'number_of_passengers' => $latest['number_of_passengers'] ?? ($updates['number_of_passengers'] ?? null),
                'distance_km' => $latest['distance_km'] ?? ($updates['distance_km'] ?? null),
                'subtotal' => $latest['subtotal'] ?? ($updates['subtotal'] ?? null),
                'total_amount' => $latest['total_amount'] ?? ($updates['total_amount'] ?? null),
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
                $paymentMethod = effectivePaymentMethodOrders($payment);
                $paymentStatus = strtolower(trim((string)($payment['status'] ?? '')));

                if (isOnlinePaymentMethodOrders($paymentMethod) && $paymentStatus === 'paid') {
                    $refunded = refundAccountBalanceOrders($userRepo, $bookingRepo, $bookingId, $booking, $payment);
                    if (!$refunded) {
                        echo json_encode([
                            'success' => false,
                            'message' => 'Unable to refund to account balance. Cancellation was not completed.',
                        ]);
                        exit;
                    }
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

if ($action === 'submit-review' || $action === 'get-reviews') {
    echo json_encode([
        'success' => false,
        'message' => 'Review actions moved to /api/reviews.php.',
        'moved_to' => '/api/reviews.php',
        'action' => $action,
    ]);
    exit;
}

echo json_encode(['success' => false, 'message' => 'Unknown action: ' . $action]);
exit;