<?php
// 1) Start or resume the session
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// 2) Pull username before unsetting the session
$username = $_SESSION['username'] ?? null;

// 3) Bring in just the PDO (assets/database.php sets time_zone too)
require_once __DIR__ . '/assets/database.php';

// 4) Log the logout (only if we had a username)
if ($username) {
    $stmt = $pdo->prepare(
      "INSERT INTO activity_log (username, event) VALUES (:u, 'logout')"
    );
    $stmt->execute([':u' => $username]);
}

// 5) Wipe session data
$_SESSION = [];
session_destroy();

// 6) Remove the session cookie
if (ini_get("session.use_cookies")) {
    $p = session_get_cookie_params();
    setcookie(session_name(), '', time() - 42000,
      $p['path'], $p['domain'], $p['secure'], $p['httponly']
    );
}

// 6.5) Remove the remember me token from database (if using multiple device support)
if (!empty($_COOKIE['rememberme'])) {
    try {
        $stmt = $pdo->prepare("DELETE FROM user_remember_tokens WHERE token = :token");
        $stmt->execute([':token' => $_COOKIE['rememberme']]);
    } catch (PDOException $e) {
        // Log error if needed
        error_log("Failed to delete remember token: " . $e->getMessage());
    }
}

// 7) Remove the “remember me” cookie
setcookie('rememberme','', time() - 3600, '/', '', true, true);

// 8) Redirect out
header('Location: index.php');
exit;
