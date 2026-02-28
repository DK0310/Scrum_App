<?php
header('Content-Type: text/plain');
require_once __DIR__ . '/../config/env.php';
require_once __DIR__ . '/../Database/db.php';

echo "Connected OK!\n";

try {
    $pdo->exec("ALTER TABLE bookings ADD COLUMN IF NOT EXISTS distance_km DECIMAL(10,2) DEFAULT NULL");
    echo "Added distance_km column\n";
    
    $pdo->exec("ALTER TABLE bookings ADD COLUMN IF NOT EXISTS transfer_cost DECIMAL(10,2) DEFAULT NULL");
    echo "Added transfer_cost column\n";

    // Verify
    $stmt = $pdo->query("SELECT column_name, data_type FROM information_schema.columns WHERE table_name = 'bookings' AND column_name IN ('distance_km', 'transfer_cost') ORDER BY ordinal_position");
    echo "\nNew columns in bookings:\n";
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        echo "  - " . $row['column_name'] . " (" . $row['data_type'] . ")\n";
    }
    echo "\nDone!\n";
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
