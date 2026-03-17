<?php
/**
 * Reviews Page - Backend
 * Handles customer reviews and ratings
 */
session_start();
$title = "DriveNow - Customer Reviews";
$currentPage = 'reviews';

require_once '../config/env.php';
require_once '../Database/db.php';

$isLoggedIn = isset($_SESSION['logged_in']) && $_SESSION['logged_in'];
$currentUser = $isLoggedIn ? $_SESSION['username'] : null;
$userRole = $_SESSION['role'] ?? 'user';

include '../templates/reviews.html.php';
?>
