<?php
/**
 * Community Page - Backend
 * All data loaded dynamically via API calls from the template
 */
session_start();
$title = "DriveNow - Community";
$currentPage = 'community';

require_once '../config/env.php';
require_once '../Database/db.php';
require_once '../api/n8n.php';

$isLoggedIn = isset($_SESSION['logged_in']) && $_SESSION['logged_in'];
$currentUser = $isLoggedIn ? $_SESSION['username'] : null;
$userId = $_SESSION['user_id'] ?? null;
$userRole = $_SESSION['role'] ?? 'renter';

include '../templates/community.html.php';
?>
