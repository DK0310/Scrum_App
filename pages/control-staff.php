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

if (!in_array($normalizedRole, ['admin', 'controlstaff', 'staff'], true)) {
    http_response_code(403);
    header('Location: /index.php');
    exit;
}

$title = 'Control Staff - Private Hire';
$currentPage = 'control-staff';
$isLoggedIn = true;
$currentUser = $_SESSION['full_name'] ?? $_SESSION['username'] ?? $_SESSION['email'] ?? 'Control Staff';

require __DIR__ . '/../templates/ControlStaff.html.php';
