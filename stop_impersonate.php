<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$pdo = require __DIR__ . '/assets/database.php';

// Ensure an impersonation is active
if (empty($_SESSION['original_user'])) {
    header('Location: login.php');
    exit;
}

// Restore original user credentials
$_SESSION['username'] = $_SESSION['original_user']['username'];
$_SESSION['role'] = $_SESSION['original_user']['role'];
unset($_SESSION['original_user']);
$_SESSION['logged_in'] = true;

header('Location: index.php');
exit;
