<?php
require_once __DIR__ . '/config/env.php';
require_once __DIR__ . '/Database/db.php';

echo "Connected OK\n";

// Check bookings
$stmt = $pdo->query("SELECT id, renter_id, owner_id, status, created_at FROM bookings ORDER BY created_at DESC LIMIT 5");
$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
echo "Bookings found: " . count($rows) . "\n";
foreach ($rows as $r) {
    echo "  ID=" . substr($r['id'],0,8) . " renter=" . substr($r['renter_id'],0,8) . " owner=" . substr($r['owner_id'],0,8) . " status=" . $r['status'] . " created=" . $r['created_at'] . "\n";
}

// Check users
$stmt2 = $pdo->query("SELECT id, full_name, role FROM users LIMIT 10");
$users = $stmt2->fetchAll(PDO::FETCH_ASSOC);
echo "\nUsers:\n";
foreach ($users as $u) {
    echo "  ID=" . substr($u['id'],0,8) . " name=" . $u['full_name'] . " role=" . $u['role'] . "\n";
}
