<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

header('Content-Type: application/json');

if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
    echo json_encode(['success' => false]);
    exit;
}

require_once 'assets/database.php';

try {
    // Update the user's last_tickets_view timestamp
    $stmt = $pdo->prepare("
        UPDATE users 
        SET last_tickets_view = NOW() 
        WHERE username = :username
    ");
    $stmt->execute([':username' => $_SESSION['username']]);
    
    echo json_encode(['success' => true]);
} catch (PDOException $e) {
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}