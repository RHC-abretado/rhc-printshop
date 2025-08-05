<?php
// update_notes.php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
header('Content-Type: application/json');

// Ensure user is logged in and has sufficient privileges (Admin or higher)
if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit;
}
$allowedRoles = ['Admin', 'Manager', 'Super Admin'];
if (!in_array($_SESSION['role'] ?? '', $allowedRoles, true)) {
    http_response_code(403);
    echo json_encode(['success' => false, 'error' => 'Forbidden']);
    exit;
}

require_once __DIR__ . '/assets/database.php';

$data = json_decode(file_get_contents('php://input'), true);

if (!isset($data['ticket_id'], $data['admin_notes'])) {
    echo json_encode(['success' => false, 'error' => 'Missing parameters.']);
    exit;
}

$ticketId   = (int)$data['ticket_id'];
$adminNotes = trim($data['admin_notes']);

try {
    $pdo = new PDO("mysql:host=$dbHost;dbname=$dbName;charset=utf8", $dbUser, $dbPass);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    // Update the admin_notes
    $stmt = $pdo->prepare("UPDATE job_tickets SET admin_notes = :notes WHERE id = :id");
    $stmt->execute([
        ':notes' => $adminNotes,
        ':id'    => $ticketId
    ]);

    if ($stmt->rowCount() >= 0) {
        echo json_encode(['success' => true]);
    } else {
        echo json_encode(['success' => false, 'error' => 'No rows updated.']);
    }

} catch (PDOException $e) {
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
