<?php
/**
 * Driver API Endpoints
 * For drivers to view available trips, accept rides, track locations, etc.
 */

header('Content-Type: application/json');
require_once __DIR__ . '/../config/env.php';
require_once __DIR__ . '/../Database/db.php';

// Repository includes
require_once __DIR__ . '/../lib/repositories/UserRepository.php';
require_once __DIR__ . '/../lib/repositories/TripRepository.php';
require_once __DIR__ . '/../lib/repositories/VehicleRepository.php';
require_once __DIR__ . '/../lib/repositories/NotificationRepository.php';

session_start();

// Initialize repositories
$userRepo = new UserRepository($pdo);
$tripRepo = new TripRepository($pdo);
$vehicleRepo = new VehicleRepository($pdo);
$notificationRepo = new NotificationRepository($pdo);

// Check if logged in and is driver
if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Not logged in']);
    exit;
}

$userId = $_SESSION['user_id'];
$action = $_GET['action'] ?? $_POST['action'] ?? null;

// Verify user is a driver
$user = $userRepo->findById($userId);
if (!$user || $user['role'] !== 'driver') {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Not a driver account']);
    exit;
}

// ===== GET AVAILABLE TRIPS (nearby drivers can accept) =====
if ($action === 'get_available_trips') {
    try {
        // Get all available trips using TripRepository
        $trips = $tripRepo->getAvailableTrips($userId, 20);

        echo json_encode(['success' => true, 'trips' => $trips]);
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
}

// ===== ACCEPT A TRIP =====
else if ($action === 'accept_trip') {
    try {
        $bookingId = $_POST['booking_id'] ?? null;
        if (!$bookingId) {
            echo json_encode(['success' => false, 'message' => 'Missing booking_id']);
            exit;
        }

        // Get the active trip
        $trip = $tripRepo->getByBookingId($bookingId);

        if (!$trip) {
            echo json_encode(['success' => false, 'message' => 'Trip not found']);
            exit;
        }

        // Get assigned vehicle for driver
        $vehicle = $vehicleRepo->getAssignedVehicleForDriver($userId);
        $vehicleId = $vehicle['id'] ?? null;

        // Use TripRepository to accept trip
        $result = $tripRepo->acceptTrip($trip['id'], $userId, $vehicleId);
        
        if ($result) {
            echo json_encode(['success' => true, 'message' => 'Trip accepted']);
        } else {
            echo json_encode(['success' => false, 'message' => 'Failed to accept trip']);
        }
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
}

// ===== GET DRIVER'S ACTIVE TRIPS =====
else if ($action === 'get_my_trips') {
    try {
        $status = $_GET['status'] ?? null; // 'all', 'current', 'completed'

        // Use TripRepository to get driver's trips
        $trips = $tripRepo->getDriverTrips($userId, $status, 50);

        echo json_encode(['success' => true, 'trips' => $trips]);
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
}

// ===== UPDATE DRIVER LOCATION (GPS simulation) =====
else if ($action === 'update_location') {
    try {
        $tripId = $_POST['trip_id'] ?? null;
        $lat = $_POST['lat'] ?? null;
        $lng = $_POST['lng'] ?? null;

        if (!$tripId || $lat === null || $lng === null) {
            echo json_encode(['success' => false, 'message' => 'Missing required fields']);
            exit;
        }

        // Use TripRepository to update location
        $result = $tripRepo->updateDriverLocation($tripId, (float)$lat, (float)$lng);
        
        echo json_encode(['success' => true, 'message' => 'Location updated']);
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
}

// ===== DRIVER ARRIVED AT PICKUP =====
else if ($action === 'driver_arrived') {
    try {
        $tripId = $_POST['trip_id'] ?? null;

        if (!$tripId) {
            echo json_encode(['success' => false, 'message' => 'Missing trip_id']);
            exit;
        }

        // Use TripRepository to mark driver as arrived
        $result = $tripRepo->markDriverArrived($tripId, $userId);
        
        if ($result) {
            echo json_encode(['success' => true, 'message' => 'Driver arrived status updated']);
        } else {
            echo json_encode(['success' => false, 'message' => 'Failed to update arrival status']);
        }
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
}

// ===== START JOURNEY =====
else if ($action === 'start_journey') {
    try {
        $tripId = $_POST['trip_id'] ?? null;

        if (!$tripId) {
            echo json_encode(['success' => false, 'message' => 'Missing trip_id']);
            exit;
        }

        // Use TripRepository to start journey
        $result = $tripRepo->startJourney($tripId, $userId);
        
        if ($result) {
            echo json_encode(['success' => true, 'message' => 'Journey started']);
        } else {
            echo json_encode(['success' => false, 'message' => 'Failed to start journey']);
        }
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
}

// ===== FINISH JOURNEY =====
else if ($action === 'finish_journey') {
    try {
        $tripId = $_POST['trip_id'] ?? null;

        if (!$tripId) {
            echo json_encode(['success' => false, 'message' => 'Missing trip_id']);
            exit;
        }

        // Use TripRepository to complete trip with notifications
        $result = $tripRepo->completeTripWithNotification($tripId, $userId);
        
        if ($result) {
            echo json_encode(['success' => true, 'message' => 'Journey completed']);
        } else {
            echo json_encode(['success' => false, 'message' => 'Failed to complete journey']);
        }
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
}

// ===== GET ASSIGNED VEHICLE =====
else if ($action === 'get_assigned_vehicle') {
    try {
        // Get vehicle assigned to this driver using VehicleRepository
        $vehicle = $vehicleRepo->getAssignedVehicleForDriver($userId);

        if ($vehicle) {
            echo json_encode(['success' => true, 'vehicle' => $vehicle]);
        } else {
            echo json_encode(['success' => false, 'message' => 'No vehicle assigned for today']);
        }
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
}

// ===== GET DRIVER NOTIFICATIONS =====
else if ($action === 'get_notifications') {
    try {
        $unreadOnly = $_GET['unread_only'] ?? false;

        // Use NotificationRepository to get notifications
        $notifications = $notificationRepo->getForDriver($userId, $unreadOnly, 50);

        echo json_encode(['success' => true, 'notifications' => $notifications]);
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
}

// ===== MARK NOTIFICATION AS READ =====
else if ($action === 'mark_notification_read') {
    try {
        $notificationId = $_POST['notification_id'] ?? null;

        if (!$notificationId) {
            echo json_encode(['success' => false, 'message' => 'Missing notification_id']);
            exit;
        }

        // Use NotificationRepository to mark as read
        $result = $notificationRepo->markAsRead($notificationId, $userId);

        echo json_encode(['success' => true, 'message' => 'Notification marked as read']);
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
}

else {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Unknown action']);
}
