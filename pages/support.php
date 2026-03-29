<?php
session_start();
require_once __DIR__ . '/../config/env.php';
require_once __DIR__ . '/../Database/db.php';

$isLoggedIn = isset($_SESSION['logged_in']) && $_SESSION['logged_in'];
$userRole = $_SESSION['role'] ?? 'user';
$userId = $_SESSION['user_id'] ?? null;
$currentUser = $isLoggedIn ? ($_SESSION['full_name'] ?? $_SESSION['username'] ?? 'User') : null;
$title = 'Customer Support - Private Hire';
$currentPage = 'support';

require __DIR__ . '/../templates/support.html.php';
