<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/assets/database.php';

$email = trim($_GET['email'] ?? '');
$token = trim($_GET['token'] ?? '');
$tickets = [];
$error = '';

if (!filter_var($email, FILTER_VALIDATE_EMAIL) || !preg_match('/^[a-f0-9]{32}$/', $token)) {
    $error = 'Invalid request.';
} else {
    try {
        $stmt = $pdo->prepare("SELECT token FROM requestor_token WHERE email = :email AND token = :token");
        $stmt->execute([':email' => $email, ':token' => $token]);
        if ($stmt->fetchColumn()) {
            $ticketStmt = $pdo->prepare("SELECT ticket_number, check_token, ticket_status, created_at, date_wanted, job_title FROM job_tickets WHERE email = :email ORDER BY created_at DESC");
            $ticketStmt->execute([':email' => $email]);
            $tickets = $ticketStmt->fetchAll(PDO::FETCH_ASSOC);
        } else {
            $error = 'Invalid email or token.';
        }
    } catch (PDOException $e) {
        $error = 'Database error occurred.';
    }
}

require_once 'header.php';
?>
<div class="container py-5">
    <h1>My Requests</h1>
    <?php if ($error): ?>
        <div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
    <?php elseif (empty($tickets)): ?>
        <p>No requests found.</p>
    <?php else: ?>
        <div class="table-responsive">
            <table class="table table-bordered table-striped align-middle">
                <thead class="table-light">
                    <tr>
                        <th>Ticket #</th>
                        <th>Status</th>
                        <th>Request Date</th>
                        <th>Due Date</th>
                        <th>Title</th>
                        <th>View</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($tickets as $t): ?>
                        <tr>
                            <td><?= htmlspecialchars($t['ticket_number']) ?></td>
                            <td><?= htmlspecialchars($t['ticket_status']) ?></td>
                            <td><?= htmlspecialchars(toLA($t['created_at'], 'm/d/Y')) ?></td>
                            <td><?= htmlspecialchars(date('m/d/Y', strtotime($t['date_wanted']))) ?></td>
                            <td><?= htmlspecialchars($t['job_title']) ?></td>
                            <td><a href="status.php?ticket=<?= urlencode($t['ticket_number']) ?>&token=<?= urlencode($t['check_token']) ?>">View</a></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</div>
<?php require_once 'footer.php'; ?>
