<?php
/**
 * Run chat_memory.sql on Supabase
 */
require_once __DIR__ . '/config/env.php';
require_once __DIR__ . '/Database/db.php';

echo "Connected to DB OK!\n";
echo "Running chat_memory.sql...\n\n";

$sql = file_get_contents(__DIR__ . '/Database/chat_memory.sql');

// Split by semicolons but keep the SQL intact
// Execute each statement separately
$statements = array_filter(
    array_map('trim', explode(';', $sql)),
    fn($s) => !empty($s) && !str_starts_with(trim($s), '--')
);

$success = 0;
$errors = 0;

foreach ($statements as $stmt) {
    $stmt = trim($stmt);
    if (empty($stmt) || $stmt === '') continue;
    
    // Skip pure comment lines
    $lines = array_filter(explode("\n", $stmt), fn($l) => !str_starts_with(trim($l), '--') && trim($l) !== '');
    if (empty($lines)) continue;
    
    $preview = substr(implode(' ', array_slice($lines, 0, 1)), 0, 80);
    
    try {
        $pdo->exec($stmt);
        echo "  OK: {$preview}...\n";
        $success++;
    } catch (PDOException $e) {
        echo "  ERR: {$preview}...\n";
        echo "       " . $e->getMessage() . "\n";
        $errors++;
    }
}

echo "\nDone! {$success} statements OK, {$errors} errors.\n";

// Verify tables exist
echo "\nVerifying tables:\n";
$tables = ['chat_sessions', 'chat_messages', 'chat_memory'];
foreach ($tables as $t) {
    try {
        $pdo->query("SELECT COUNT(*) FROM {$t}");
        echo "  {$t}: EXISTS\n";
    } catch (PDOException $e) {
        echo "  {$t}: MISSING!\n";
    }
}
