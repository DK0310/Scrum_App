<?php
session_start();

require_once __DIR__ . '/../Database/db.php';
require_once __DIR__ . '/../config/env.php';
require_once __DIR__ . '/../sql/UserRepository.php';

$userRepo = new UserRepository($pdo);

if (!isset($_SESSION['user_id'])) {
    header('Location: /index.php');
    exit;
}

$userId = (string)$_SESSION['user_id'];
$normalizedRole = strtolower(str_replace(['-', ' ', '_'], '', (string)($userRepo->getUserRole($userId) ?? ($_SESSION['role'] ?? ''))));

if (!in_array($normalizedRole, ['admin', 'callcenterstaff'], true)) {
    http_response_code(403);
    header('Location: /index.php');
    exit;
}

$title = 'Call Center Staff - Private Hire';
$currentPage = 'call-center-staff';
$isLoggedIn = true;
$currentUser = $_SESSION['full_name'] ?? $_SESSION['username'] ?? $_SESSION['email'] ?? 'Call Center Staff';
$canCreateAccount = ($normalizedRole === 'callcenterstaff');

require __DIR__ . '/../templates/CallCenterStaff.html.php';
