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
$currentUser = $isLoggedIn ? $_SESSION['username'] : null;
$userRole = $_SESSION['role'] ?? 'renter';

// Load template
include '../templates/index.html.php';
?>