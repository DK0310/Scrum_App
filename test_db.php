<?php
require_once __DIR__ . '/config/env.php';
require_once __DIR__ . '/Database/db.php';
echo "DB Connected OK!\n";
echo "Server: " . $pdo->getAttribute(PDO::ATTR_SERVER_VERSION) . "\n";

// Quick test query
$stmt = $pdo->query("SELECT COUNT(*) FROM users");
echo "Users count: " . $stmt->fetchColumn() . "\n";

$stmt2 = $pdo->query("SELECT COUNT(*) FROM vehicles WHERE status = 'available'");
echo "Available vehicles: " . $stmt2->fetchColumn() . "\n";

echo "All good!\n";
