<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Load database connection
$pdo = require __DIR__ . '/assets/database.php';
$protectedUsers = require __DIR__ . '/config/protected_users.php';

// Verify the current user is allowed to impersonate
if (
    empty($_SESSION['logged_in']) ||
    empty($_SESSION['username']) ||
    !in_array($_SESSION['username'], $protectedUsers, true)
) {
    header('Location: login.php');
    exit;
}

// Accept target username via GET or POST
$target = $_GET['target'] ?? $_POST['target'] ?? '';
$target = trim($target);

if ($target === '' || in_array($target, $protectedUsers, true)) {
    header('Location: user_management.php');
    exit;
}

try {
    $stmt = $pdo->prepare('SELECT username, role FROM users WHERE username = :u LIMIT 1');
    $stmt->execute([':u' => $target]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$user) {
        header('Location: user_management.php');
        exit;
    }

    // Save the original developer credentials if not already stored
    if (empty($_SESSION['original_user'])) {
        $_SESSION['original_user'] = [
            'username' => $_SESSION['username'],
            'role' => $_SESSION['role'] ?? ''
        ];
    }

    // Copy target credentials into session
    $_SESSION['username'] = $user['username'];
    $_SESSION['role'] = $user['role'];
    $_SESSION['logged_in'] = true;

    header('Location: index.php');
    exit;
} catch (PDOException $e) {
    header('Location: user_management.php');
    exit;
}
