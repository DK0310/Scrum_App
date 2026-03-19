<?php
require_once 'Database/db.php';
require_once 'sql/VehicleRepository.php';

$vehicleRepo = new VehicleRepository($pdo);
$allVehicles = $vehicleRepo->listAll();

echo "Total vehicles: " . count($allVehicles) . "\n";
echo "Vehicles with service_tier:\n";
foreach ($allVehicles as $v) {
    echo sprintf(
        "Vehicle %s: brand=%s, model=%s, tier=%s, status=%s\n",
        $v['id'],
        $v['brand'],
        $v['model'],
        $v['service_tier'] ?? 'N/A',
        $v['status'] ?? 'N/A'
    );
}

// Try finding a standard tier vehicle
$stmt = $pdo->prepare("
    SELECT id, brand, model, service_tier, status, owner_id, price_per_day
    FROM vehicles
    WHERE service_tier = 'standard' AND status = 'available'
    LIMIT 1
");
$stmt->execute();
$vehicle = $stmt->fetch(PDO::FETCH_ASSOC);
if ($vehicle) {
    echo "\nFound available standard vehicle:\n";
    var_dump($vehicle);
} else {
    echo "\nNo available standard vehicle found\n";
}
?>
