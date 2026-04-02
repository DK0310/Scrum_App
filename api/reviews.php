<?php
/**
 * Reviews API - Private Hire
 * Dedicated endpoint for journey ratings and written feedback.
 */

require_once __DIR__ . '/bootstrap.php';

$input = api_init(['allow_origin' => '*']);
$action = api_action($input);

require_once __DIR__ . '/../Database/db.php';
require_once __DIR__ . '/../sql/BookingRepository.php';
require_once __DIR__ . '/../sql/UserRepository.php';
require_once __DIR__ . '/notification-helpers.php';

$bookingRepo = new BookingRepository($pdo);
$userRepo = new UserRepository($pdo);

if ($action === 'get-reviews') {
    $limit = (int)($_GET['limit'] ?? ($input['limit'] ?? 50));
    $limit = max(1, min($limit, 100));
    $vehicleId = trim((string)($_GET['vehicle_id'] ?? ($input['vehicle_id'] ?? '')));

    try {   
        if ($vehicleId !== '') {
            $reviews = $bookingRepo->getReviewsWithDetailsAndStats($vehicleId, $limit);
            $stats = $bookingRepo->getReviewStatsComplete($vehicleId);
            echo json_encode(['success' => true, 'reviews' => $reviews, 'stats' => $stats]);
            exit;
        }

        $stmt = $pdo->prepare("\n            SELECT r.id, r.rating, r.comment AS content, r.comment, r.created_at,\n                   u.full_name, u.avatar_url,\n                   v.brand, v.model, v.year,\n                   b.pickup_location, b.return_location, b.booking_type\n            FROM reviews r\n            JOIN users u ON r.user_id = u.id\n            JOIN vehicles v ON r.vehicle_id = v.id\n            LEFT JOIN bookings b ON r.booking_id = b.id\n            ORDER BY r.created_at DESC\n            LIMIT ?\n        ");
        $stmt->execute([$limit]);
        $reviews = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $statsStmt = $pdo->query("\n            SELECT COUNT(*) as total,\n                   ROUND(AVG(rating)::numeric, 1) as avg_rating,\n                   COUNT(*) FILTER (WHERE rating = 5) as stars_5,\n                   COUNT(*) FILTER (WHERE rating = 4) as stars_4,\n                   COUNT(*) FILTER (WHERE rating = 3) as stars_3,\n                   COUNT(*) FILTER (WHERE rating = 2) as stars_2,\n                   COUNT(*) FILTER (WHERE rating = 1) as stars_1\n            FROM reviews\n        ");
        $stats = $statsStmt->fetch(PDO::FETCH_ASSOC) ?: [];

        echo json_encode(['success' => true, 'reviews' => $reviews, 'stats' => $stats]);
    } catch (PDOException $e) {
        echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
    }
    exit;
}

if ($action === 'submit-review') {
    api_require_auth();
    $userId = (string)($_SESSION['user_id'] ?? '');
    $bookingId = trim((string)($input['booking_id'] ?? ''));
    $rating = (int)($input['rating'] ?? 0);
    $comment = trim((string)($input['comment'] ?? $input['content'] ?? ''));

    if ($bookingId === '' || $rating < 1 || $rating > 5 || $comment === '') {
        echo json_encode(['success' => false, 'message' => 'Booking ID, rating (1-5), and feedback comment are required.']);
        exit;
    }

    try {
        $booking = $bookingRepo->getBookingInfo($bookingId);
        if (!$booking) {
            echo json_encode(['success' => false, 'message' => 'Booking not found.']);
            exit;
        }

        if ((string)$booking['renter_id'] !== $userId) {
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

        $bookingRepo->insertReview($bookingId, (string)$booking['vehicle_id'], $userId, $rating, $comment);
        $avgData = $bookingRepo->getVehicleReviewStats((string)$booking['vehicle_id']);
        if (($avgData['avg_rating'] ?? null) !== null) {
            $bookingRepo->updateVehicleRating((string)$booking['vehicle_id'], (float)$avgData['avg_rating'], (int)$avgData['total']);
        }

        // Loyalty points are awarded only after review submission.
        // Formula: points = distance_miles + amount_paid
        $earningStmt = $pdo->prepare("SELECT COALESCE(distance_km, 0) AS distance_km, COALESCE(total_amount, 0) AS total_amount FROM bookings WHERE id = ? LIMIT 1");
        $earningStmt->execute([$bookingId]);
        $earningRow = $earningStmt->fetch(PDO::FETCH_ASSOC) ?: ['distance_km' => 0, 'total_amount' => 0];

        $distanceKm = (float)($earningRow['distance_km'] ?? 0);
        $distanceMiles = $distanceKm * 0.621371;
        $amountPaid = (float)($earningRow['total_amount'] ?? 0);
        $earnedPoints = (int)max(0, round($distanceMiles + $amountPaid));

        $updatedUser = $userRepo->addLoyaltyPoints($userId, $earnedPoints);
        $currentPoints = (int)($updatedUser['loyalty_point'] ?? $userRepo->getLoyaltyPoint($userId));

        if ($earnedPoints > 0) {
            createNotification(
                $pdo,
                $userId,
                'promo',
                '🎁 Loyalty Points Earned',
                'You earned ' . $earnedPoints . ' loyalty points after completing your trip review.'
            );
        }

        echo json_encode([
            'success' => true,
            'message' => 'Review submitted successfully! Thank you for your feedback.',
            'earned_points' => $earnedPoints,
            'loyalty_point' => $currentPoints,
        ]);
    } catch (PDOException $e) {
        echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
    }
    exit;
}

echo json_encode(['success' => false, 'message' => 'Unknown action: ' . $action]);
