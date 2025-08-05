<?php
// update_cost.php
header('Content-Type: application/json');

require_once __DIR__ . '/assets/database.php';

$data = json_decode(file_get_contents('php://input'), true);

if (!isset($data['ticket_id'], $data['total_cost'])) {
    echo json_encode(['success' => false, 'error' => 'Missing parameters.']);
    exit;
}

$ticketId  = (int)$data['ticket_id'];
$totalCost = (float)$data['total_cost'];

try {
    // Fetch existing cost and ticket number for logging
    $infoStmt = $pdo->prepare("SELECT total_cost, ticket_number FROM job_tickets WHERE id = :id");
    $infoStmt->execute([':id' => $ticketId]);
    $ticketInfo = $infoStmt->fetch(PDO::FETCH_ASSOC);

    if (!$ticketInfo) {
        echo json_encode(['success' => false, 'error' => 'Ticket not found.']);
        exit;
    }

    $oldCost      = (float)$ticketInfo['total_cost'];
    $ticketNumber = $ticketInfo['ticket_number'];
    
    // Update the total_cost
    $stmt = $pdo->prepare("UPDATE job_tickets SET total_cost = :cost WHERE id = :id");
    $stmt->execute([
        ':cost' => $totalCost,
        ':id'   => $ticketId
    ]);

    if ($stmt->rowCount() >= 0) {
        $log = $pdo->prepare("INSERT INTO activity_log (username, event, details) VALUES (:u, 'update_cost', :d)");
        $oldFormatted = number_format($oldCost, 2, '.', '');
        $newFormatted = number_format($totalCost, 2, '.', '');
        $details = "Updated cost from {$oldFormatted} to {$newFormatted} on Ticket #{$ticketNumber}";
        $log->execute([
            ':u' => $_SESSION['username'],
            ':d' => $details
        ]);
        echo json_encode(['success' => true]);
    } else {
        echo json_encode(['success' => false, 'error' => 'No rows updated.']);
    }

} catch (PDOException $e) {
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}