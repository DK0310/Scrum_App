<?php
/**
 * Cars Page - Backend
 * Handles car listing, filtering, and search
 */
session_start();
$title = "DriveNow - Browse Cars";
$currentPage = 'cars';

// Include dependencies
require_once '../config/env.php';
require_once '../Database/db.php';
require_once '../api/n8n.php';

// Check login status
$isLoggedIn = isset($_SESSION['logged_in']) && $_SESSION['logged_in'];
$currentUser = $isLoggedIn ? $_SESSION['username'] : null;
$userRole = $_SESSION['role'] ?? 'renter';

// Cars are loaded dynamically from database via vehicles API (JavaScript fetch)
// No server-side car data needed — the template uses client-side API calls

// Load template
include '../templates/cars.html.php';
?>
