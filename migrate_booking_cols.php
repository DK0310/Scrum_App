<?php
require_once __DIR__ . '/config/env.php';
require_once __DIR__ . '/Database/db.php';

echo "Connected OK!\n";

try {
    $pdo->exec("ALTER TABLE bookings ADD COLUMN IF NOT EXISTS distance_km DECIMAL(10,2) DEFAULT NULL");
    echo "Added distance_km column\n";
    
    $pdo->exec("ALTER TABLE bookings ADD COLUMN IF NOT EXISTS transfer_cost DECIMAL(10,2) DEFAULT NULL");
    echo "Added transfer_cost column\n";

    echo "\nDone!\n";
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
