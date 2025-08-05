<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Only Super Admins may access this
if (empty($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true || $_SESSION['role'] !== 'Super Admin') {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Unauthorized access.']);
    exit;
}

require_once 'assets/database.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] === 'GET' && $_GET['action'] === 'get') {
    // Get current settings
    try {
        $stmt = $pdo->prepare("
            SELECT target_username, enabled 
            FROM auto_assign_settings 
            WHERE user_id = (SELECT id FROM users WHERE username = :username) 
            LIMIT 1
        ");
        $stmt->execute([':username' => $_SESSION['username']]);
        $settings = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$settings) {
            $settings = ['target_username' => '', 'enabled' => 0];
        } else {
            // Ensure enabled is returned as integer
            $settings['enabled'] = (int)$settings['enabled'];
            $settings['target_username'] = $settings['target_username'] ?: '';
        }
        
        echo json_encode(['success' => true, 'settings' => $settings]);
    } catch (PDOException $e) {
        echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
    }
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $input = json_decode(file_get_contents('php://input'), true);
    $enabled = (int)($input['enabled'] ?? 0);
    $targetUsername = trim($input['target_username'] ?? '');
    
    // Only require target username when enabling
    if ($enabled && empty($targetUsername)) {
        echo json_encode(['success' => false, 'message' => 'Target username is required when auto-assignment is enabled.']);
        exit;
    }
    
    try {
        // Get current user ID
        $userStmt = $pdo->prepare("SELECT id FROM users WHERE username = :username");
        $userStmt->execute([':username' => $_SESSION['username']]);
        $userId = $userStmt->fetchColumn();
        
        if (!$userId) {
            echo json_encode(['success' => false, 'message' => 'User not found.']);
            exit;
        }
        
        // Validate target user exists (only when enabling)
if ($enabled && !empty($targetUsername)) {
    $targetStmt = $pdo->prepare("SELECT COUNT(*) FROM users WHERE username = :username");
    $targetStmt->execute([':username' => $targetUsername]);
    if ($targetStmt->fetchColumn() == 0) {
        echo json_encode(['success' => false, 'message' => 'Target user not found.']);
        exit;
    }
}
        
        // When disabling, clear the target username
        if (!$enabled) {
            $targetUsername = '';
        }
        
        // Check if record exists
        $checkStmt = $pdo->prepare("SELECT id FROM auto_assign_settings WHERE user_id = :user_id");
        $checkStmt->execute([':user_id' => $userId]);
        $existingRecord = $checkStmt->fetchColumn();
        
        if ($existingRecord) {
            // Update existing record
            $stmt = $pdo->prepare("
                UPDATE auto_assign_settings 
                SET target_username = :target_username, 
                    enabled = :enabled, 
                    updated_at = CURRENT_TIMESTAMP 
                WHERE user_id = :user_id
            ");
        } else {
            // Insert new record
            $stmt = $pdo->prepare("
                INSERT INTO auto_assign_settings (user_id, target_username, enabled) 
                VALUES (:user_id, :target_username, :enabled)
            ");
        }
        
        $stmt->execute([
            ':user_id' => $userId,
            ':target_username' => $targetUsername,
            ':enabled' => $enabled
        ]);
        
        // Log the action
        $details = $enabled 
            ? "Enabled auto-assignment to {$targetUsername}"
            : "Disabled auto-assignment";
            
        $logStmt = $pdo->prepare("
            INSERT INTO activity_log (username, event, details) 
            VALUES (:user, 'auto_assign_setting', :details)
        ");
        $logStmt->execute([
            ':user' => $_SESSION['username'],
            ':details' => $details
        ]);
        
        echo json_encode([
            'success' => true, 
            'message' => 'Settings saved successfully.',
            'enabled' => $enabled,
            'target_username' => $targetUsername
        ]);
        
    } catch (PDOException $e) {
        error_log("Auto-assign settings error: " . $e->getMessage());
        echo json_encode(['success' => false, 'message' => 'Database error occurred.']);
    }
}
?>