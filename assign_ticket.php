<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Only allow if logged in and role Manager or Super Admin
if (
    empty($_SESSION['logged_in'])
    || $_SESSION['logged_in'] !== true
    || !in_array($_SESSION['role'], ['Manager', 'Super Admin'], true)
) {
    echo json_encode(['success' => false, 'error' => 'Not authorized.']);
    exit;
}

// Load PHPMailer and encryption helper for email notifications
require_once __DIR__ . '/helpers/encryption.php';
require_once __DIR__ . '/libs/phpmailer/src/Exception.php';
require_once __DIR__ . '/libs/phpmailer/src/PHPMailer.php';
require_once __DIR__ . '/libs/phpmailer/src/SMTP.php';

// bring in your one-and-only PDO (with PT timezone)
require_once __DIR__ . '/assets/database.php';

$data = json_decode(file_get_contents('php://input'), true);
$ticketId  = (int)($data['ticket_id']   ?? 0);
$assignedTo = trim($data['assigned_to'] ?? '');

if (!$ticketId || $assignedTo === '') {
    echo json_encode(['success' => false, 'error' => 'Missing ticket ID or assignee.']);
    exit;
}

try {
    // 1) Update the ticket
    $sql  = "UPDATE job_tickets SET assigned_to = :assigned_to WHERE id = :id";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([
        ':assigned_to' => $assignedTo,
        ':id'          => $ticketId,
    ]);

    if ($stmt->rowCount() > 0) {
        // 2) Fetch the actual ticket_number for logging
        $ticketStmt = $pdo->prepare("SELECT ticket_number FROM job_tickets WHERE id = :id");
        $ticketStmt->execute([':id' => $ticketId]);
        $ticketNumber = $ticketStmt->fetchColumn();

        // 3) Log the assignment with the correct ticket number
        $log = $pdo->prepare("
          INSERT INTO activity_log (username, event, details)
          VALUES (:u, 'assign_ticket', :d)
        ");
        $details = "Ticket #{$ticketNumber} assigned to {$assignedTo}";
        $log->execute([
            ':u' => $_SESSION['username'],
            ':d' => $details,
        ]);

        // 4) Send assignment notification email
        $settings = $pdo->query("SELECT * FROM email_settings WHERE id = 1")->fetch(PDO::FETCH_ASSOC);
        
        if ($settings && !empty($settings['notify_on_assignment'])) {
            // Get ticket and assignee details
            $ticketInfo = $pdo->prepare("
                SELECT jt.ticket_number, jt.job_title, jt.created_at, jt.email as requester_email,
                       jt.first_name as req_first, jt.last_name as req_last,
                       u.email as assignee_email, u.first_name, u.last_name
                FROM job_tickets jt
                LEFT JOIN users u ON u.username = :assigned_to
                WHERE jt.id = :id
            ");
            $ticketInfo->execute([':assigned_to' => $assignedTo, ':id' => $ticketId]);
            $ticket = $ticketInfo->fetch(PDO::FETCH_ASSOC);

            if ($ticket && !empty($ticket['assignee_email'])) {
                $assigneeName = trim("{$ticket['first_name']} {$ticket['last_name']}");
                $requesterName = trim("{$ticket['req_first']} {$ticket['req_last']}");
                
                // Convert UTC created_at to PT
                $dt = new DateTime($ticket['created_at'], new DateTimeZone('UTC'));
                $dt->setTimezone(new DateTimeZone('America/Los_Angeles'));
                $submitted = $dt->format('m-d-Y H:i:s');

                try {
                    $mail = new \PHPMailer\PHPMailer\PHPMailer(true);
                    $mail->CharSet = 'UTF-8';
                    $mail->Encoding = 'base64';
                    $mail->isSMTP();
                    $mail->Host = $settings['smtp_host'];
                    $mail->Port = (int)$settings['smtp_port'];
                    
                    if (!empty($settings['smtp_secure'])) {
                        $mail->SMTPSecure = $settings['smtp_secure'];
                    }
                    if (!empty($settings['smtp_username'])) {
                        $mail->SMTPAuth = true;
                        $mail->Username = $settings['smtp_username'];
                        $mail->Password = smtp_decrypt($settings['smtp_password']);
                    }

                    $mail->setFrom($settings['email_from'], 'RHC Printshop');
                    $mail->addAddress($ticket['assignee_email']);
                    $mail->isHTML(true);

                    $mail->Subject = "New Ticket Assigned: #{$ticket['ticket_number']}";
                    $mail->Body = "
                        <p>Hello {$assigneeName},</p>
                        <p>A new printshop ticket has been assigned to you:</p>
                        <p>
                          <strong>Ticket #</strong>: {$ticket['ticket_number']}<br>
                          <strong>Title</strong>: {$ticket['job_title']}<br>
                          <strong>Requested by</strong>: {$requesterName}<br>
                          <strong>Submitted on</strong>: {$submitted}
                        </p>
                        <p>Please log in to the printshop system to review the details.</p>
                        <p>Questions? Email printing@riohondo.edu</p>
                    ";
                    $mail->AltBody = strip_tags($mail->Body);
                    $mail->send();
                } catch (\PHPMailer\PHPMailer\Exception $e) {
                    error_log("Assignment notification failed: " . $e->getMessage());
                }
            }
        }

        echo json_encode(['success' => true]);
    } else {
        echo json_encode(['success' => false, 'error' => 'Ticket not updated.']);
    }
} catch (PDOException $e) {
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}