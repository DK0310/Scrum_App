<?php
// Simulate the exact API call that the JavaScript makes
require_once 'Database/db.php';
require_once 'config/env.php';
require_once 'sql/UserRepository.php';
require_once 'sql/BookingRepository.php';
require_once 'sql/VehicleRepository.php';

$userRepo = new UserRepository($pdo);
$bookingRepo = new BookingRepository($pdo);
$vehicleRepo = new VehicleRepository($pdo);

// Simulate JSON POST body
$bodyJson = [
    'action' => 'update_order_status',
    'booking_id' => '8c691109-533f-44ca-a3c0-d27de6f4a1a1',
    'status' => 'in_progress'
];

function control_normalize_booking_status(string $status): string
{
    $s = strtolower(trim($status));
    $s = str_replace('-', '_', $s);
    if ($s === 'done') {
        return 'completed';
    }
    return $s;
}

function control_status_rank(string $status): int
{
    $normalized = control_normalize_booking_status($status);
    return match ($normalized) {
        'pending' => 1,
        'confirmed' => 2, // included for reference but Control Staff workflow uses only 1,3,4
        'in_progress' => 3,
        'completed' => 4,
        default => 0,
    };
}

/**
 * Check if a status transition is valid for Control Staff workflow
 * Control Staff only uses: pending -> in_progress -> completed (1 -> 3 -> 4)
 * Does NOT go through confirmed (rank 2)
 */
function control_is_valid_status_transition(int $fromRank, int $toRank): bool
{
    // Control Staff workflow: 1 (pending) -> 3 (in_progress) -> 4 (completed)
    if ($fromRank === 1 && $toRank === 3) return true;  // pending -> in_progress
    if ($fromRank === 3 && $toRank === 4) return true;  // in_progress -> completed
    
    // For future: if confirmed status is used in other workflows, it could be:
    // pending (1) -> confirmed (2) -> in_progress (3) -> completed (4)
    // But for now, Control Staff skips confirmed
    
    return false;
}

function control_resolve_db_booking_status($pdo, string $canonicalStatus): string
{
    $canonical = control_normalize_booking_status($canonicalStatus);
    $stmt = $pdo->query("
        SELECT e.enumlabel
        FROM pg_type t
        JOIN pg_enum e ON t.oid = e.enumtypid
        WHERE t.typname = 'booking_status'
        ORDER BY e.enumsortorder
    ");
    $labels = $stmt ? $stmt->fetchAll(PDO::FETCH_COLUMN) : [];
    $labels = array_map(static fn($v) => (string)$v, $labels);

    if (empty($labels)) {
        return $canonical;
    }

    $candidates = match ($canonical) {
        'pending' => ['pending'],
        'in_progress' => ['in_progress', 'in-progress'],
        'completed' => ['completed', 'done'],
        default => [$canonical],
    };

    foreach ($candidates as $candidate) {
        if (in_array($candidate, $labels, true)) {
            return $candidate;
        }
    }

    throw new Exception('Status is not supported by current booking_status enum');
}

// Execute the logic
try {
    $bookingId = trim((string)($bodyJson['booking_id'] ?? ''));
    $newStatus = trim((string)($bodyJson['status'] ?? ''));

    echo "Test API call: action=update_order_status, booking_id=$bookingId, status=$newStatus\n";

    if ($bookingId === '' || $newStatus === '') {
        throw new Exception('Missing booking_id or status');
    }

    $requestedStatus = control_normalize_booking_status($newStatus);
    echo "Requested status (normalized): $requestedStatus\n";

    $booking = $bookingRepo->getById($bookingId);
    if (!$booking) {
        throw new Exception('Booking not found');
    }

    $currentStatus = control_normalize_booking_status((string)($booking['status'] ?? 'pending'));
    echo "Current status: $currentStatus\n";

    if ($currentStatus === $requestedStatus) {
        echo "Status unchanged - no action needed\n";
        exit;
    }

    $currentRank = control_status_rank($currentStatus);
    $requestedRank = control_status_rank($requestedStatus);
    echo "Current rank: $currentRank, Requested rank: $requestedRank\n";
    
    if ($currentRank > 0 && $requestedRank > 0 && !control_is_valid_status_transition($currentRank, $requestedRank)) {
        throw new Exception('Invalid transition from ' . $currentStatus . ' to ' . $requestedStatus . '. Control Staff workflow: pending → in_progress → completed.');
    }

    $dbStatus = control_resolve_db_booking_status($pdo, $requestedStatus);
    echo "DB status: $dbStatus\n";

    // This is where vehicle assignment happens
    $effectiveVehicleId = (string)($booking['vehicle_id'] ?? '');

    if ($requestedStatus === 'in_progress') {
        $rideTier = strtolower(trim((string)($booking['ride_tier'] ?? 'standard')));
        if (!in_array($rideTier, ['eco', 'standard', 'premium'], true)) {
            $rideTier = 'standard';
        }

        $renterId = (string)($booking['renter_id'] ?? '');
        if ($renterId === '') {
            throw new Exception('Booking is missing renter information');
        }

        echo "Finding vehicle for tier: $rideTier, renter: $renterId\n";
        $assignedVehicle = $bookingRepo->findVehicleForTier($rideTier, $renterId);
        if (!$assignedVehicle) {
            throw new Exception('No available vehicle found for the selected ride tier');
        }

        $assignedVehicleId = (string)($assignedVehicle['id'] ?? '');
        $assignedOwnerId = (string)($assignedVehicle['owner_id'] ?? '');
        echo "Assigning vehicle: $assignedVehicleId to owner: $assignedOwnerId\n";

        if ($assignedVehicleId === '' || $assignedOwnerId === '') {
            throw new Exception('Unable to assign vehicle for this order');
        }

        $assigned = $bookingRepo->assignVehicleToBooking(
            $bookingId,
            $assignedVehicleId,
            $assignedOwnerId,
            (float)($assignedVehicle['price_per_day'] ?? 0)
        );
        if (!$assigned) {
            throw new Exception('Unable to assign vehicle to order');
        }

        $effectiveVehicleId = $assignedVehicleId;
        echo "✓ Vehicle assigned successfully\n";
    }

    $ok = $bookingRepo->updateStatus($bookingId, $dbStatus);
    if (!$ok) {
        throw new Exception('Unable to update status');
    }

    echo "✓ Status updated successfully\n";

    echo "\n✅ API call simulation: SUCCESS\n";

} catch (Exception $e) {
    echo "\n❌ API call simulation: ERROR - " . $e->getMessage() . "\n";
}
?>
