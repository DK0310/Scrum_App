<?php
/**
 * Staff Dashboard - Main Controller
 * Handles: Session check, access control, template loading
 * Business logic: api/staff.php
 * UI Template: templates/staff.html.php
 */

session_start();

// Access control: only staff and admin
if (($_SESSION['role'] ?? 'user') !== 'staff' && ($_SESSION['role'] ?? 'user') !== 'admin') {
    header('Location: index.php');
    exit;
}

require_once __DIR__ . '/../Database/db.php';

// Set variables for template
$userRole = $_SESSION['role'] ?? 'user';
$currentUser = $_SESSION['full_name'] ?? $_SESSION['username'] ?? 'User';
$isLoggedIn = isset($_SESSION['user_id']);

// Load template
include __DIR__ . '/../templates/staff.html.php';
?>
