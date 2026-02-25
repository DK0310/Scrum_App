<?php
/**
 * GPS Tracking Page - Backend
 * Handles GPS tracking for car owners
 */
session_start();
$title = "DriveNow - GPS Tracking";
$currentPage = 'gps';

require_once '../config/env.php';
require_once '../Database/db.php';
require_once '../api/n8n.php';

$isLoggedIn = isset($_SESSION['logged_in']) && $_SESSION['logged_in'];
$currentUser = $isLoggedIn ? $_SESSION['username'] : null;
$userRole = $_SESSION['role'] ?? 'renter';

// Require login for GPS tracking
if (!$isLoggedIn) {
    header('Location: login.php?redirect=gps.php');
    exit;
}

include '../templates/gps.html.php';
?>
