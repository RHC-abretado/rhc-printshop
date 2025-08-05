<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Only Super Admins may access
if (
    !isset($_SESSION['logged_in'], $_SESSION['role'])
    || $_SESSION['logged_in'] !== true
    || $_SESSION['role'] !== 'Super Admin'
) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Unauthorized access.']);
    exit;
}

require_once 'assets/database.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Invalid request method.']);
    exit;
}

$action = $_POST['action'] ?? '';

// Handle different actions
try {
    switch ($action) {
        case 'add':
            // Validate input
            $departmentName = trim($_POST['department_name'] ?? '');
            $locationCode = trim($_POST['location_code'] ?? '');
            
            if (empty($departmentName) || empty($locationCode)) {
                echo json_encode(['success' => false, 'message' => 'Department name and location code are required.']);
                exit;
            }
            
            // Check for duplicate codes
            $checkStmt = $pdo->prepare("SELECT COUNT(*) FROM location_codes WHERE code = :code");
            $checkStmt->execute([':code' => $locationCode]);
            if ($checkStmt->fetchColumn() > 0) {
                echo json_encode(['success' => false, 'message' => 'This location code already exists.']);
                exit;
            }
            
            // Insert the new code
            $insertStmt = $pdo->prepare("INSERT INTO location_codes (department_name, code) VALUES (:dept, :code)");
            $insertStmt->execute([
                ':dept' => $departmentName,
                ':code' => $locationCode
            ]);
            
            // Log the action
            $logStmt = $pdo->prepare("INSERT INTO activity_log (username, event, details) VALUES (:user, 'add_location_code', :details)");
            $logStmt->execute([
                ':user' => $_SESSION['username'],
                ':details' => "Added location code: {$locationCode} for {$departmentName}"
            ]);
            
            echo json_encode(['success' => true, 'message' => 'Location code added successfully.']);
            break;
            
        case 'edit':
            // Validate input
            $id = (int)($_POST['id'] ?? 0);
            $departmentName = trim($_POST['department_name'] ?? '');
            $locationCode = trim($_POST['location_code'] ?? '');
            
            if ($id <= 0 || empty($departmentName) || empty($locationCode)) {
                echo json_encode(['success' => false, 'message' => 'Invalid input data.']);
                exit;
            }
            
            // Check if the code exists
            $checkStmt = $pdo->prepare("SELECT COUNT(*) FROM location_codes WHERE id = :id");
            $checkStmt->execute([':id' => $id]);
            if ($checkStmt->fetchColumn() == 0) {
                echo json_encode(['success' => false, 'message' => 'Location code not found.']);
                exit;
            }
            
            // Check for duplicate codes (excluding the current one)
            $checkStmt = $pdo->prepare("SELECT COUNT(*) FROM location_codes WHERE code = :code AND id != :id");
            $checkStmt->execute([':code' => $locationCode, ':id' => $id]);
            if ($checkStmt->fetchColumn() > 0) {
                echo json_encode(['success' => false, 'message' => 'This location code already exists.']);
                exit;
            }
            
            // Update the code
            $updateStmt = $pdo->prepare("UPDATE location_codes SET department_name = :dept, code = :code WHERE id = :id");
            $updateStmt->execute([
                ':dept' => $departmentName,
                ':code' => $locationCode,
                ':id' => $id
            ]);
            
            // Log the action
            $logStmt = $pdo->prepare("INSERT INTO activity_log (username, event, details) VALUES (:user, 'update_location_code', :details)");
            $logStmt->execute([
                ':user' => $_SESSION['username'],
                ':details' => "Updated location code ID {$id}: {$locationCode} for {$departmentName}"
            ]);
            
            echo json_encode(['success' => true, 'message' => 'Location code updated successfully.']);
            break;
            
        case 'delete':
            // Validate input
            $id = (int)($_POST['id'] ?? 0);
            
            if ($id <= 0) {
                echo json_encode(['success' => false, 'message' => 'Invalid location code ID.']);
                exit;
            }
            
            // Get the code details for logging
            $getStmt = $pdo->prepare("SELECT department_name, code FROM location_codes WHERE id = :id");
            $getStmt->execute([':id' => $id]);
            $codeDetails = $getStmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$codeDetails) {
                echo json_encode(['success' => false, 'message' => 'Location code not found.']);
                exit;
            }
            
            // Delete the code
            $deleteStmt = $pdo->prepare("DELETE FROM location_codes WHERE id = :id");
            $deleteStmt->execute([':id' => $id]);
            
            // Log the action
            $logStmt = $pdo->prepare("INSERT INTO activity_log (username, event, details) VALUES (:user, 'delete_location_code', :details)");
            $logStmt->execute([
                ':user' => $_SESSION['username'],
                ':details' => "Deleted location code: {$codeDetails['code']} for {$codeDetails['department_name']}"
            ]);
            
            echo json_encode(['success' => true, 'message' => 'Location code deleted successfully.']);
            break;
            
        default:
            echo json_encode(['success' => false, 'message' => 'Invalid action.']);
    }
} catch (PDOException $e) {
    echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
}