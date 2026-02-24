<?php

$dsn = "pgsql:host=aws-1-ap-southeast-1.pooler.supabase.com;port=6543;dbname=postgres;sslmode=require";
$user = "postgres.zydpdyoinxnrlsqkeobd";
$password = "Khangkhang0310@";

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
