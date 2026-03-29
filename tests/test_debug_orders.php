<?php
require_once __DIR__ . '/../Database/db.php';
require_once __DIR__ . '/../sql/BookingRepository.php';

$bookingRepo = new BookingRepository($pdo);
$orders = $bookingRepo->listAll(10, 0);

echo "Total orders: " . count($orders) . "\n";
foreach ($orders as $o) {
    echo sprintf(
        "Order %s: status=%s, customer=%s, vehicle_id=%s, ride_tier=%s\n",
        $o['id'],
        $o['status'],
        $o['customer_name'] ?? $o['user_name'] ?? 'N/A',
        $o['vehicle_id'] ?? 'NULL',
        $o['ride_tier'] ?? 'N/A'
    );
}
?>
