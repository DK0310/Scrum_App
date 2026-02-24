<?php

// Load environment variables
require_once __DIR__ . '/../config/env.php';

$dsn = "pgsql:host=" . EnvLoader::get('DB_HOST') . 
       ";port=" . EnvLoader::get('DB_PORT') . 
       ";dbname=" . EnvLoader::get('DB_NAME') . 
       ";sslmode=" . EnvLoader::get('DB_SSL_MODE');
$user = EnvLoader::get('DB_USER');
$password = EnvLoader::get('DB_PASSWORD');

try {
    $pdo = new PDO($dsn, $user, $password, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_PERSISTENT => false
    ]);
} catch (PDOException $e) {
    // Nếu gọi từ API thì trả JSON, không echo HTML
    $isApi = strpos($_SERVER['SCRIPT_NAME'] ?? '', '/api/') !== false;
    if ($isApi) {
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'message' => 'DB connection failed: ' . $e->getMessage()]);
        exit;
    }
    die("❌ Connection failed: " . $e->getMessage());
}

