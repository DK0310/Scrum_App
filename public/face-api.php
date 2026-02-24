<?php
// Proxy để truy cập face-auth API từ public folder
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit(0);
}

// Chuyển request đến file API thực
require_once __DIR__ . '/../api/face-auth.php';
