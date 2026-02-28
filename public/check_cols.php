<?php
header('Content-Type: text/plain');
require_once __DIR__ . '/../config/env.php';
require_once __DIR__ . '/../Database/db.php';

// First ensure columns exist
$pdo->exec("ALTER TABLE bookings ADD COLUMN IF NOT EXISTS distance_km DECIMAL(10,2) DEFAULT NULL");
$pdo->exec("ALTER TABLE bookings ADD COLUMN IF NOT EXISTS transfer_cost DECIMAL(10,2) DEFAULT NULL");

$stmt = $pdo->query("SELECT column_name, data_type FROM information_schema.columns WHERE table_name = 'bookings' AND column_name IN ('distance_km', 'transfer_cost') ORDER BY ordinal_position");
$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
echo "Columns found: " . count($rows) . "\n";
foreach ($rows as $r) {
    echo "  - " . $r['column_name'] . " (" . $r['data_type'] . ")\n";
}
echo "OK\n";
