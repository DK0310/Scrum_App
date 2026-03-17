<?php
/**
 * Driver Dashboard - Main Controller
 * Handles: Session check, access control, template loading
 * Business logic: api/driver.php (optional, can be added later)
 * UI Template: templates/driver.html.php
 */

session_start();

// Access control: only driver
if (($_SESSION['role'] ?? 'user') !== 'driver') {
    header('Location: index.php');
    exit;
}

require_once __DIR__ . '/../Database/db.php';

// Set variables for template
$userRole = $_SESSION['role'] ?? 'user';
$currentUser = $_SESSION['full_name'] ?? $_SESSION['username'] ?? 'User';
$isLoggedIn = isset($_SESSION['user_id']);

// Load template
include __DIR__ . '/../templates/driver.html.php';
?>
