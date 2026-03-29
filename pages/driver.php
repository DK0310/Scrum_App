<?php
declare(strict_types=1);

session_start();
require_once __DIR__ . '/../config/env.php';
require_once __DIR__ . '/../Database/db.php';
require_once __DIR__ . '/../sql/UserRepository.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: /index.php');
    exit;
}

$userId = (string)$_SESSION['user_id'];
$userRepo = new UserRepository($pdo);
$user = $userRepo->findById($userId);
if (!$user || ($user['role'] ?? '') !== 'driver') {
    header('Location: /index.php');
    exit;
}

$title = 'Driver Dashboard - Private Hire';
$currentPage = 'driver';
$isLoggedIn = true;
$userRole = 'driver';
$currentUser = $user['full_name'] ?? $user['email'] ?? 'Driver';

require __DIR__ . '/../templates/driver.html.php';
