<?php
/**
 * My Orders Page - Backend
 * View booking history and manage order status
 */
session_start();
$title = "DriveNow - My Orders";
$currentPage = 'orders';

require_once '../config/env.php';
require_once '../Database/db.php';

$isLoggedIn = isset($_SESSION['logged_in']) && $_SESSION['logged_in'];
$currentUser = $isLoggedIn ? ($_SESSION['full_name'] ?? $_SESSION['username'] ?? null) : null;
$userRole = $_SESSION['role'] ?? 'user';

// Require login
if (!$isLoggedIn) {
    header('Location: /auth?mode=login&redirect=orders.php');
    exit;
}

include '../templates/orders.html.php';
?>
