<?php
require_once 'Database/db.php';
require_once 'sql/BookingRepository.php';

$bookingRepo = new BookingRepository($pdo);

// Test findVehicleForTier - this is what happens when Control Staff clicks "Start Trip"
$bookingId = '8c691109-533f-44ca-a3c0-d27de6f4a1a1';
$rideTier = 'standard';
$renterId = 'test-user'; // We'll find the real one first

$booking = $bookingRepo->getById($bookingId);
if (!$booking) {
    echo "Booking not found!\n";
    exit;
}

echo "Booking found. Renter ID: " . ($booking['renter_id'] ?? 'NULL') . "\n";
$renterId = $booking['renter_id'] ?? '';

// Test finding vehicle
$vehicle = $bookingRepo->findVehicleForTier($rideTier, $renterId);
if ($vehicle) {
    echo "Vehicle found: " . $vehicle['brand'] . " " . $vehicle['model'] . " (ID: " . $vehicle['id'] . ")\n";
    
    // Test assignment
    $assigned = $bookingRepo->assignVehicleToBooking(
        $bookingId,
        $vehicle['id'],
        $vehicle['owner_id'],
        $vehicle['price_per_day']
    );
    
    if ($assigned) {
        echo "SUCCESS: Vehicle assigned to booking\n";
    } else {
        echo "FAILED: Could not assign vehicle\n";
    }
} else {
    echo "ERROR: No available vehicle found for tier '$rideTier'\n";
}
?>
