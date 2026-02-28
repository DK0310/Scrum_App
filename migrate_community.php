<?php
require_once __DIR__ . '/config/env.php';
require_once __DIR__ . '/Database/db.php';

echo "Connected OK!\n";

try {
    $pdo->exec("ALTER TABLE community_posts ADD COLUMN IF NOT EXISTS image_data BYTEA");
    echo "Added image_data column\n";
    
    $pdo->exec("ALTER TABLE community_posts ADD COLUMN IF NOT EXISTS image_mime VARCHAR(50)");
    echo "Added image_mime column\n";

    // Verify
    $stmt = $pdo->query("SELECT column_name, data_type FROM information_schema.columns WHERE table_name = 'community_posts' ORDER BY ordinal_position");
    echo "\nColumns in community_posts:\n";
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        echo "  - " . $row['column_name'] . " (" . $row['data_type'] . ")\n";
    }
    echo "\nDone!\n";
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
