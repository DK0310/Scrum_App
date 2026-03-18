<?php
/**
 * My Vehicles Page - Backend (Owner Only)
 * Dashboard for car owners to manage their fleet
 */
session_start();
$title = "DriveNow - My Vehicles";
$currentPage = 'my-vehicles';

require_once '../config/env.php';
require_once '../Database/db.php';

$isLoggedIn = isset($_SESSION['logged_in']) && $_SESSION['logged_in'];
$currentUser = $isLoggedIn ? $_SESSION['username'] : null;
$userRole = $_SESSION['role'] ?? 'user';

// Require login
if (!$isLoggedIn) {
    header('Location: /auth?mode=login&redirect=my-vehicles.php');
    exit;
}

// Require staff or admin role (vehicle management)
if ($userRole !== 'staff' && $userRole !== 'admin') {
    header('Location: index.php');
    exit;
}

include '../templates/my-vehicles.html.php';
?>