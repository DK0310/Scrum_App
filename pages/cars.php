<?php
session_start();
$title = 'Private Hire - Browse Cars';
$currentPage = 'cars';

require_once __DIR__ . '/../config/env.php';
require_once __DIR__ . '/../Database/db.php';

$isLoggedIn = isset($_SESSION['logged_in']) && $_SESSION['logged_in'];
$currentUser = $isLoggedIn ? ($_SESSION['full_name'] ?? $_SESSION['username'] ?? $_SESSION['email'] ?? null) : null;
$userRole = $_SESSION['role'] ?? 'user';

require __DIR__ . '/../templates/cars.html.php';
