<?php
/**
 * Profile Page - Backend
 * View and edit user profile information
 */
session_start();
$title = "DriveNow - My Profile";
$currentPage = 'profile';

require_once '../config/env.php';
require_once '../Database/db.php';

$isLoggedIn = isset($_SESSION['logged_in']) && $_SESSION['logged_in'];
$currentUser = $isLoggedIn ? ($_SESSION['full_name'] ?? $_SESSION['username'] ?? null) : null;
$userRole = $_SESSION['role'] ?? 'user';

// Require login
if (!$isLoggedIn) {
    header('Location: /auth?mode=login&redirect=profile.php');
    exit;
}

include '../templates/profile.html.php';
?>
