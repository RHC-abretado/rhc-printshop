<?php
// update_status.php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
header('Content-Type: application/json');

// Verify authentication and authorization (Admin or higher)
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
require_once __DIR__ . '/helpers/encryption.php';
require_once __DIR__ . '/libs/phpmailer/src/Exception.php';
require_once __DIR__ . '/libs/phpmailer/src/PHPMailer.php';
require_once __DIR__ . '/libs/phpmailer/src/SMTP.php';

/**
 * Fetch SMTP settings (and toggles) from your DB.
 */
function getEmailSettings(PDO $pdo): array {
    return $pdo
        ->query("SELECT * FROM email_settings WHERE id = 1")
        ->fetch(PDO::FETCH_ASSOC) ?: [];
}

$data = json_decode(file_get_contents('php://input'), true);
if (empty($data['ticket_id']) || empty($data['ticket_status'])) {
    echo json_encode(['success'=>false,'error'=>'Missing parameters.']);
    exit;
}

$ticketId  = (int)$data['ticket_id'];
$newStatus = trim($data['ticket_status']);
$allowed   = ['New','Processing','Complete','Canceled','Hold'];
if (!in_array($newStatus, $allowed, true)) {
    echo json_encode(['success'=>false,'error'=>'Invalid status.']);
    exit;
}

try {
    $pdo = new PDO(
        "mysql:host=$dbHost;dbname=$dbName;charset=utf8",
        $dbUser,
        $dbPass,
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
    );

    // Build UPDATE statement + params
    if ($newStatus === 'Processing') {
        $sql = "
            UPDATE job_tickets
               SET ticket_status = :st,
                   assigned_to   = :asgt,
                   completed_at  = NULL
             WHERE id = :id
        ";
        $params = [
            ':st'   => $newStatus,
            ':asgt' => $_SESSION['username'] ?? '',
            ':id'   => $ticketId,
        ];
    } elseif ($newStatus === 'Complete') {
    $sql = "
        UPDATE job_tickets
           SET ticket_status = :st,
               completed_at  = UTC_TIMESTAMP()   -- <──── here
         WHERE id = :id
    ";
    $params = [
        ':st' => $newStatus,
        ':id' => $ticketId,
    ];
}
 else {
        // 'New' or 'Canceled'
        $sql = "
            UPDATE job_tickets
               SET ticket_status = :st,
                   completed_at  = NULL
             WHERE id = :id
        ";
        $params = [
            ':st' => $newStatus,
            ':id' => $ticketId,
        ];
    }

    // Execute update
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    
// ── NEW: log the status change with the actual ticket_number ──────
if ($stmt->rowCount() > 0) {
    // fetch the updated ticket_number
    $info = $pdo->prepare("SELECT ticket_number FROM job_tickets WHERE id = :id");
    $info->execute([':id' => $ticketId]);
    $tn = $info->fetchColumn();

    $log = $pdo->prepare("
      INSERT INTO activity_log (username, event, details)
      VALUES (:u, 'status_change', :details)
    ");
// Check if user is properly logged in before logging
if (empty($_SESSION['username'])) {
    // Don't log if no valid session
    echo json_encode(['success' => false, 'error' => 'Session expired']);
    exit;
}

$log->execute([
  ':u'       => $_SESSION['username'],
  ':details' => "Ticket #{$tn} → {$newStatus}"
]);
}



    // If changed to Processing or Complete, send notification
    if ($stmt->rowCount() > 0 && in_array($newStatus, ['Processing','Complete','Hold'], true)) {
        $info = $pdo->prepare("
            SELECT 
              jt.ticket_number,
              jt.job_title,
              jt.email,
              jt.created_at,
              jt.assigned_to,
              u.first_name,
              u.last_name
            FROM job_tickets jt
            LEFT JOIN users u
              ON u.username = jt.assigned_to
            WHERE jt.id = :id
        ");
        $info->execute([':id' => $ticketId]);
        $ticket = $info->fetch(PDO::FETCH_ASSOC);

        if ($ticket && !empty($ticket['email'])) {
            $smtp     = getEmailSettings($pdo);
            $smtpPass = smtp_decrypt($smtp['smtp_password']);

            $tn   = htmlspecialchars($ticket['ticket_number'], ENT_QUOTES, 'UTF-8');
            $title = htmlspecialchars($ticket['job_title'], ENT_QUOTES, 'UTF-8');

            // Convert stored UTC created_at to PT
            $dt = new DateTime($ticket['created_at'], new DateTimeZone('UTC'));
            $dt->setTimezone(new DateTimeZone('America/Los_Angeles'));
            $submitted = $dt->format('m-d-Y H:i:s');

            // Determine assigned-to display name
            if (!empty($ticket['first_name']) || !empty($ticket['last_name'])) {
                $assignedToName = htmlspecialchars(
                    trim("{$ticket['first_name']} {$ticket['last_name']}"),
                    ENT_QUOTES, 'UTF-8'
                );
            } else {
                $assignedToName = htmlspecialchars(
                    $ticket['assigned_to'],
                    ENT_QUOTES, 'UTF-8'
                );
            }

            $toggleKey = $newStatus === 'Processing' ? 'notify_on_processing' :
            ($newStatus === 'Complete' ? 'notify_on_complete' : 'notify_on_hold');

            if (!empty($smtp[$toggleKey])) {
                try {
                    $mail = new \PHPMailer\PHPMailer\PHPMailer(true);
                    $mail->CharSet    = 'UTF-8';
                    $mail->Encoding   = 'base64';
                    $mail->isSMTP();
                    $mail->Host       = $smtp['smtp_host'];
                    $mail->Port       = (int)$smtp['smtp_port'];
                    if (!empty($smtp['smtp_secure'])) {
                        $mail->SMTPSecure = $smtp['smtp_secure'];
                    }
                    if (!empty($smtp['smtp_username'])) {
                        $mail->SMTPAuth = true;
                        $mail->Username = $smtp['smtp_username'];
                        $mail->Password = $smtpPass;
                    }

                    $mail->setFrom($smtp['email_from'], 'RHC Printshop');
                    $mail->addAddress($ticket['email']);
                    $mail->isHTML(true);

                    if ($newStatus === 'Processing') {
    $mail->Subject = "Your Printshop Ticket #{$tn} is Processing";
    $body = "
        <p>Your ticket is now being processed by <strong>{$assignedToName}</strong>:</p>
        <p>
          <strong>Ticket #</strong>: {$tn}<br>
          <strong>Title</strong>: {$title}<br>
          <strong>Submitted on</strong>: {$submitted}
        </p>
        <p>You will receive another notification when it's complete.</p>
    ";
} elseif ($newStatus === 'Complete') {
    $mail->Subject = "Your Printshop Ticket #{$tn} is Complete";
    $body = "
        <p>Your ticket has been marked <strong>Complete</strong>:</p>
        <p>
          <strong>Ticket #</strong>: {$tn}<br>
          <strong>Title</strong>: {$title}<br>
          <strong>Submitted on</strong>: {$submitted}
        </p>
        <p>Thank you for using Río Hondo Printshop!</p>
    ";
} else { // Hold
    $mail->Subject = "Your Printshop Ticket #{$tn} is on Hold";
    $body = "
        <p>Your ticket has been placed on <strong>Hold</strong>:</p>
        <p>
          <strong>Ticket #</strong>: {$tn}<br>
          <strong>Title</strong>: {$title}<br>
          <strong>Submitted on</strong>: {$submitted}
        </p>
        <p>Your ticket is temporarily paused. You will receive another notification when processing resumes.</p>
        <p>If you have questions, please email printing@riohondo.edu</p>
    ";
}

                    $body .= "<p>Questions? Email printing@riohondo.edu</p>";

                    $mail->Body    = $body;
                    $mail->AltBody = strip_tags($body);
                    $mail->send();
                } catch (\PHPMailer\PHPMailer\Exception $e) {
                    error_log("Ticket-{$newStatus} email failed: " . $e->getMessage());
                }
            }
        }
    }

    echo json_encode([
        'success' => $stmt->rowCount() > 0,
        'refresh' => (in_array($newStatus, ['Processing', 'Complete'], true) &&
        $stmt->rowCount() > 0),
        'error'   => $stmt->rowCount() > 0 ? null : 'No update performed.'
    ]);

} catch (PDOException $e) {
    echo json_encode(['success'=>false,'error'=>$e->getMessage()]);
}
