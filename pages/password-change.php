<?php
session_start();
$title = 'Reset Password - Private Hire';
$currentPage = 'password-change';

$isLoggedIn = isset($_SESSION['logged_in']) && $_SESSION['logged_in'];
$userRole = $_SESSION['role'] ?? 'user';
$currentUser = $isLoggedIn ? ($_SESSION['full_name'] ?? $_SESSION['username'] ?? $_SESSION['email'] ?? 'User') : null;

require __DIR__ . '/../templates/password-change.html.php';
