<?php
session_start();
$title = "Sign Up - DriveNow";
$currentPage = 'register';

// Redirect if already logged in
if (isset($_SESSION['logged_in']) && $_SESSION['logged_in']) {
    header('Location: index.php');
    exit;
}

require_once __DIR__ . '/../templates/register.html.php';

