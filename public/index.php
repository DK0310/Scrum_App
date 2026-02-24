<?php
session_start();
$title = "Scrum Project";

// Include database connection
require_once '../Database/db.php';

// Include n8n connector
require_once '../api/n8n.php';

// Khởi tạo N8N connector
$n8n = new N8NConnector('http://localhost:5678');

// Test kết nối n8n
$n8nConnected = $n8n->testConnection();

// Kiểm tra đăng nhập
$isLoggedIn = isset($_SESSION['logged_in']) && $_SESSION['logged_in'];
$currentUser = $isLoggedIn ? $_SESSION['username'] : null;

try {
    // Có thể thêm logic ở đây
    
} catch (Exception $e) {
    echo "Error loading template: " . $e->getMessage();
}

ob_start();
$output = ob_get_clean();
include '../templates/menu.html.php';
?>