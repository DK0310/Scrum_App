<?php
/**
 * Admin Dashboard - DriveNow
 * Manages hero slides, promotions, vehicles, bookings
 */
session_start();

// Admin gate
if (!isset($_SESSION['logged_in']) || !$_SESSION['logged_in'] || ($_SESSION['role'] ?? '') !== 'admin') {
    header('Location: index.php');
    exit;
}

$title = 'Admin Dashboard - DriveNow';
$currentPage = 'admin';
$isLoggedIn = true;
$currentUser = $_SESSION['full_name'] ?? 'Admin';
$userRole = $_SESSION['role'] ?? 'admin';

include __DIR__ . '/../templates/admin.html.php';
