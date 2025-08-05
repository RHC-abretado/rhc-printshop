<?php
session_start();
// Only Super Admins may delete users
if (
    empty($_SESSION['logged_in'])
    || $_SESSION['logged_in'] !== true
    || ($_SESSION['role'] ?? '') !== 'Super Admin'
) {
    header("Location: login.php");
    exit;
}

require_once __DIR__ . '/assets/database.php';

if (empty($_GET['id'])) {
    die("No user specified.");
}
$userId = (int) $_GET['id'];

try {
    // 1) Look up the username so we can log it
    $fetch = $pdo->prepare("
      SELECT username 
        FROM users 
       WHERE id = :id 
       LIMIT 1
    ");
    $fetch->execute([':id' => $userId]);
    $row = $fetch->fetch(PDO::FETCH_ASSOC);

    // 2) Delete the user
    $del = $pdo->prepare("DELETE FROM users WHERE id = :id");
    $del->execute([':id' => $userId]);

    // 3) If deletion succeeded, write an activity_log entry
    if ($del->rowCount() && $row) {
        $usernameDeleted = $row['username'];
        $log = $pdo->prepare("
          INSERT INTO activity_log (username, event, details)
          VALUES (:actor, 'delete_user', :details)
        ");
        $details = "Deleted user '{$usernameDeleted}' (ID {$userId})";
        $log->execute([
            ':actor'   => $_SESSION['username'],
            ':details' => $details,
        ]);
    }

    // 4) Redirect back to user management
    header("Location: user_management.php");
    exit;

} catch (PDOException $e) {
    die("Database error: " . htmlspecialchars($e->getMessage()));
}
