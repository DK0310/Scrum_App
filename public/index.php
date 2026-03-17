<?php
session_start();
$title = "DriveNow - Premium Car Rental Platform";
$currentPage = 'home';

// Include environment loader
require_once '../config/env.php';

// Include database connection
require_once '../Database/db.php';

// Include n8n connector
require_once '../api/n8n.php';

// Initialize N8N connector
$n8n = new N8NConnector(\EnvLoader::get('N8N_BASE_URL', 'http://localhost:5678'));

// Test n8n connection
$n8nConnected = $n8n->testConnection();

// Check login status
$isLoggedIn = isset($_SESSION['logged_in']) && $_SESSION['logged_in'];
$userRole = $_SESSION['role'] ?? 'user';

// Only redirect driver to driver dashboard
// Admin, Staff, User can all see the home page (index.php)
if ($isLoggedIn && $userRole === 'driver') {
    header('Location: driver.php');
    exit;
}

$currentUser = $isLoggedIn ? ($_SESSION['full_name'] ?? $_SESSION['username'] ?? 'User') : null;

// ===== ROUTE HANDLING =====
$requestPath = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
$requestPath = str_replace('/public', '', $requestPath);

// /auth route - Auth page
if ($requestPath === '/auth' || $requestPath === '/auth/') {
    $title = "Sign In / Sign Up - DriveNow";
    include '../templates/auth.html.php';
    exit;
}

// Load template
include '../templates/index.html.php';
?>