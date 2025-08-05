<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

header('Content-Type: application/json');

if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
    echo json_encode(['count' => 0]);
    exit;
}

require_once 'assets/database.php';

try {
    $count = 0;
    
    // Get user's last view time
    $userStmt = $pdo->prepare("SELECT last_tickets_view FROM users WHERE username = :username");
    $userStmt->execute([':username' => $_SESSION['username']]);
    $lastView = $userStmt->fetchColumn();
    
    if (in_array($_SESSION['role'], ['Manager', 'Super Admin'], true)) {
        // Managers & Super Admins see count of tickets created since their last view
        if ($lastView) {
            $stmt = $pdo->prepare("
                SELECT COUNT(*) 
                FROM job_tickets 
                WHERE created_at > :last_view 
                AND ticket_status IN ('New', 'Processing', 'Hold')
            ");
            $stmt->execute([':last_view' => $lastView]);
        } else {
            // First time viewing - show all New/Processing tickets
            $stmt = $pdo->query("
                SELECT COUNT(*) 
                FROM job_tickets 
                WHERE ticket_status IN ('New', 'Processing')
            ");
        }
        $count = $stmt->fetchColumn();
    } else {
        // Regular users see count of tickets assigned to them since their last view
        if ($lastView) {
            $stmt = $pdo->prepare("
                SELECT COUNT(*) 
                FROM job_tickets 
                WHERE assigned_to = :user 
                AND (created_at > :last_view OR 
                     (assigned_to = :user2 AND created_at > :last_view2))
                AND ticket_status IN ('New', 'Processing', 'Hold')
            ");
            $stmt->execute([
                ':user' => $_SESSION['username'],
                ':user2' => $_SESSION['username'],
                ':last_view' => $lastView,
                ':last_view2' => $lastView
            ]);
        } else {
            // First time viewing - show all assigned tickets
            $stmt = $pdo->prepare("
                SELECT COUNT(*) 
                FROM job_tickets 
                WHERE assigned_to = :user 
                AND ticket_status IN ('New', 'Processing')
            ");
            $stmt->execute([':user' => $_SESSION['username']]);
        }
        $count = $stmt->fetchColumn();
    }
    
    echo json_encode(['count' => (int)$count]);
} catch (PDOException $e) {
    echo json_encode(['count' => 0]);
}