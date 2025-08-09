<?php
date_default_timezone_set('America/Los_Angeles');
require __DIR__ . '/libs/phpmailer/src/Exception.php';
require __DIR__ . '/libs/phpmailer/src/PHPMailer.php';
require __DIR__ . '/libs/phpmailer/src/SMTP.php';

require __DIR__ . '/helpers/encryption.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

header('Content-Type: application/json; charset=utf-8');

$response = [
  'success' => false,
  'message' => '',
  'ticketNumber' => null,
];

function renderError(string $msg) {
  global $response;
  $response['message'] = $msg;
  echo json_encode($response);
  exit;
}

if (!isset($_POST['form_token']) || !isset($_SESSION['form_token'])) {
    renderError('Invalid request. No token present.');
}

if (!hash_equals($_SESSION['form_token'], $_POST['form_token'])) {
    renderError('Invalid request. Token mismatch.');
}

// Once validated, remove the token
unset($_SESSION['form_token']);

// == STEP 1: Generate a custom TICKET NUMBER in the format YYYYMMxxx ==
/*
   Example:
   "202504007" is composed of:
       2025 = year
       04   = month
       007  = sequence number in that month
*/
require_once 'assets/database.php';


// == STEP 2: Grab form inputs ==
$firstName      = trim($_POST['first_name'] ?? '');
$lastName       = trim($_POST['last_name'] ?? '');
$departmentName = trim($_POST['department_name'] ?? '');
$email          = trim($_POST['email'] ?? '');
$phone          = trim($_POST['phone'] ?? '');

// Generate or reuse persistent token for requestor email
$emailToken = '';
if (!empty($email)) {
    try {
        $tokenStmt = $pdo->prepare("SELECT token FROM requestor_token WHERE email = :email");
        $tokenStmt->execute([':email' => $email]);
        $emailToken = $tokenStmt->fetchColumn();

        if (!$emailToken) {
            $emailToken = bin2hex(random_bytes(16));
            $insertToken = $pdo->prepare(
                "INSERT INTO requestor_token (email, token, created_at)
                 VALUES (:email, :token, NOW())"
            );
            $insertToken->execute([':email' => $email, ':token' => $emailToken]);
        }
    } catch (PDOException $e) {
        // Fail silently if token logic fails
        $emailToken = '';
    }
}

// LOCATION CODE
$locationSelect = trim($_POST['location_code_select'] ?? '');
$otherLoc       = trim($_POST['other_location_code'] ?? '');
$locationCode   = ($locationSelect === 'Other (00000)') ? $otherLoc : $locationSelect;

// DATE WANTED
// Validate date_wanted (2 or 3 business days from now, depending on time of day)
$dateWantedRaw = trim($_POST['date_wanted'] ?? '');
$today = new DateTime();
$currentHour = (int)$today->format('G'); // 24-hour format without leading zeros
$isAfterNoon = $currentHour >= 12;
$minDate = getDateAfterBusinessDays($today, $isAfterNoon ? 3 : 2);

if (new DateTime($dateWantedRaw) < $minDate) {
    $errorMsg = $isAfterNoon 
        ? 'Please select a valid date. Orders placed after 12pm require 3 business days processing time.' 
        : 'Please select a valid date. Orders require at least 2 business days processing time.';
    renderError($errorMsg);
}

// Check if the selected date is a weekend
$selectedDate = new DateTime($dateWantedRaw);
$dayOfWeek = (int)$selectedDate->format('w'); // 0 (Sunday) to 6 (Saturday)
if ($dayOfWeek === 0 || $dayOfWeek === 6) {
    renderError('Weekend dates are not available for order pickup/delivery.');
}

// Create DateTime in LA timezone to avoid timezone conversion issues
$dateWantedObj = new DateTime($dateWantedRaw . ' 12:00:00', new DateTimeZone('America/Los_Angeles'));
$dateWanted = $dateWantedObj->format('Y-m-d H:i:s');

// Helper function to calculate a date after adding a specific number of business days
function getDateAfterBusinessDays($startDate, $businessDays) {
    $date = new DateTime($startDate->format('Y-m-d'));
    $remainingDays = $businessDays;
    
    while ($remainingDays > 0) {
        $date->modify('+1 day');
        
        // Skip weekends (0 = Sunday, 6 = Saturday)
        $dayOfWeek = (int)$date->format('w');
        if ($dayOfWeek !== 0 && $dayOfWeek !== 6) {
            $remainingDays--;
        }
    }
    
    return $date;
}

// DELIVERY METHOD
$deliveryMethod = $_POST['delivery_method'] ?? '';

// TITLE, DESCRIPTION, etc.
$jobTitle       = trim($_POST['job_title'] ?? '');
$description    = trim($_POST['description'] ?? '');
$pagesOriginal  = isset($_POST['pages_in_original']) ? (int) $_POST['pages_in_original'] : 0;

// NUMBER OF SETS
$numberOfSets   = trim($_POST['number_of_sets'] ?? '0');

// ESTIMATED COST
$estimatedCost  = floatval($_POST['estimated_cost'] ?? 0);

// PAGE LAYOUT
$pageLayout     = trim($_POST['page_layout'] ?? '');

// PRINT COPIES IN (handle "Other")
$printCopiesIn    = trim($_POST['print_copies_in'] ?? '');
$otherPrintCopies = trim($_POST['other_print_copies'] ?? '');
if ($printCopiesIn === 'Other') {
    $printCopiesIn = $otherPrintCopies;
}

// PAGE TYPE / WEIGHT (handle "Other")
$pageType       = trim($_POST['page_type'] ?? '');
$otherPageType  = trim($_POST['other_page_type'] ?? '');
if ($pageType === 'Other') {
    $pageType = $otherPageType;
}

// PAPER COLOR (handle "Other")
$paperColorSelect = trim($_POST['paper_color_select'] ?? '');
$otherPaperColor  = trim($_POST['other_paper_color'] ?? '');
$paperColor       = ($paperColorSelect === 'Other') ? $otherPaperColor : $paperColorSelect;

// Astro Bright / Color Requirement
$colorRequirement = trim($_POST['color_requirement'] ?? '');

// PAPER SIZE (handle "Other")
$paperSizeSelect  = trim($_POST['paper_size_select'] ?? '');
$otherPaperSize   = trim($_POST['other_paper_size'] ?? '');
$paperSize        = ($paperSizeSelect === 'Other') ? $otherPaperSize : $paperSizeSelect;

// CUT PAPER (radio)
// Retrieve the radio group value; default to "None" if not set.
$cutPaper = $_POST['cut_paper'] ?? 'None';

// OTHER OPTIONS (checkbox array)
$otherOptionsArr = $_POST['other_options'] ?? [];
$otherOptions    = implode(', ', $otherOptionsArr);

// --- Process Conditional Fields Only If Their Option is Selected ---

// For Staple: process staple location only if "Staple" is in other_options
if (in_array('Staple', $otherOptionsArr)) {
    $stapleLocationInput = trim($_POST['staple_location'] ?? '');
    $otherStapleLoc      = trim($_POST['other_staple_location'] ?? '');
    $stapleLocation      = ($stapleLocationInput === 'Other') ? $otherStapleLoc : $stapleLocationInput;
} else {
    $stapleLocation = '';
}

// For Fold: process fold type only if "Fold" is in other_options
if (in_array('Fold', $otherOptionsArr)) {
    $foldTypeInput = trim($_POST['fold_type'] ?? '');
    $otherFoldType = trim($_POST['other_fold_type'] ?? '');
    $foldType      = ($foldTypeInput === 'Other') ? $otherFoldType : $foldTypeInput;
} else {
    $foldType = '';
}

// For Binding: process binding type only if "Binding" is in other_options
if (in_array('Binding', $otherOptionsArr)) {
    $bindingTypeInput = trim($_POST['binding_type'] ?? '');
    $otherBindingType = trim($_POST['other_binding_type'] ?? '');
    $bindingType      = ($bindingTypeInput === 'Other') ? $otherBindingType : $bindingTypeInput;
} else {
    $bindingType = '';
}

// For Cut Paper: process cut paper only if "Cut Paper" checkbox is checked
// Otherwise, force its value to "None" regardless of the radio group value.
if (!in_array('Cut Paper', $otherOptionsArr)) {
    $cutPaper = '';
}

// == STEP 3: Securely handle file upload(s) ==
$filePaths    = [];
$allowedExt   = ['doc','docx','pdf','png','jpg','jpeg','ppt','pptx','pub'];
$maxTotalSize = 100 * 1024 * 1024; // 100 MB

if (!empty($_FILES['job_file']['name'][0])) {
    // 1) Only up to 5 files
    $fileCount = min(5, count($_FILES['job_file']['name']));
    // 2) Check total size
    $totalSize = 0;
    for ($i = 0; $i < $fileCount; $i++) {
        if ($_FILES['job_file']['error'][$i] === UPLOAD_ERR_OK) {
            $totalSize += $_FILES['job_file']['size'][$i];
        }
    }
    if ($totalSize > $maxTotalSize) {
        renderError('Error: Total upload size exceeds 100MB.');
    }

    // 3) Ensure upload directory exists
    $uploadDir = __DIR__ . '/uploads/';
    if (!is_dir($uploadDir)) {
        mkdir($uploadDir, 0755, true);
    }

    // 4) Build a “slug” from the job title
    $slug = strtolower(preg_replace('/[^a-zA-Z0-9]+/', '-', trim($jobTitle)));
    $slug = trim($slug, '-');

    // 5) Process each file
    for ($i = 0; $i < $fileCount; $i++) {
        if ($_FILES['job_file']['error'][$i] !== UPLOAD_ERR_OK) {
            continue;
        }

        // 5a) Validate extension
        $origName = $_FILES['job_file']['name'][$i];
        $ext      = strtolower(pathinfo($origName, PATHINFO_EXTENSION));
        if (!in_array($ext, $allowedExt, true)) {
            continue;
        }

        // 5b) Build a unique filename: YYYYMMDD‑slug.ext, with “-2”, “-3”, etc. if needed
        $date     = date('Ymd');
        $baseName = "{$date}-{$slug}";
        $filename = "{$baseName}.{$ext}";
        $counter  = 1;
        while (file_exists($uploadDir . $filename)) {
            $filename = "{$baseName}-" . $counter++ . ".{$ext}";
        }

        // 5c) Move into place
        $targetPath = $uploadDir . $filename;
        if (move_uploaded_file($_FILES['job_file']['tmp_name'][$i], $targetPath)) {
            $filePaths[] = 'uploads/' . $filename;
        }
    }
}

//  Join for database
$filePath = implode(',', $filePaths);  // e.g. "uploads/20250416-marketing-poster.jpg,uploads/20250416-marketing-poster-1.pdf"

//  Generate your ticket number sequence using *this* $pdo: ==
$prefix = date('Ym');
$sqlSeq = "
  SELECT MAX(SUBSTRING(ticket_number,7,3)) AS last_seq
    FROM job_tickets
   WHERE SUBSTRING(ticket_number,1,6)=:prefix
";
$stmtSeq = $pdo->prepare($sqlSeq);
$stmtSeq->execute([':prefix' => $prefix]);
$lastSeq = $stmtSeq->fetchColumn() ?: '000';
$ticketNumber = $prefix . str_pad((int)$lastSeq + 1, 3, '0', STR_PAD_LEFT);
// Generate unique token for status checks
$checkToken = bin2hex(random_bytes(16));

// == STEP 4: Insert into database (including ticket_number) ==
try {
    // Build the SQL. Note: We have removed the separator_color field.
    $sql = "INSERT INTO job_tickets (
                ticket_number,
                check_token,
                first_name, last_name, department_name, email, phone, location_code,
                date_wanted, delivery_method, job_title, description, pages_in_original,
                file_path, number_of_sets, page_layout, print_copies_in,
                page_type, paper_color, paper_size, cut_paper,
                other_options,
                other_location_code,
                other_print_copies,
                other_page_type,
                other_paper_color,
                color_requirement,
                other_paper_size,
                staple_location,
                fold_type,
                binding_type,
                total_cost,
                created_at
            ) VALUES (
                :ticket_number,
                :check_token,
                :first_name, :last_name, :department_name, :email, :phone, :location_code,
                :date_wanted, :delivery_method, :job_title, :description, :pages_in_original,
                :file_path, :number_of_sets, :page_layout, :print_copies_in,
                :page_type, :paper_color, :paper_size, :cut_paper,
                :other_options,
                :other_location_code,
                :other_print_copies,
                :other_page_type,
                :other_paper_color,
                :color_requirement,
                :other_paper_size,
                :staple_location,
                :fold_type,
                :binding_type,
                :total_cost,
                NOW()
            )";

    $stmt = $pdo->prepare($sql);
    $stmt->execute([
        ':ticket_number'       => $ticketNumber,
        ':check_token'         => $checkToken,
        ':first_name'          => $firstName,
        ':last_name'           => $lastName,
        ':department_name'     => $departmentName,
        ':email'               => $email,
        ':phone'               => $phone,
        ':location_code'       => $locationCode,
        ':date_wanted'         => $dateWanted,
        ':delivery_method'     => $deliveryMethod,
        ':job_title'           => $jobTitle,
        ':description'         => $description,
        ':pages_in_original'   => $pagesOriginal,
        ':file_path'           => $filePath,
        ':number_of_sets'      => $numberOfSets,
        ':page_layout'         => $pageLayout,
        ':print_copies_in'     => $printCopiesIn,
        ':page_type'           => $pageType,
        ':paper_color'         => $paperColor,
        ':paper_size'          => $paperSize,
        ':cut_paper'           => $cutPaper,
        ':other_options'       => $otherOptions,
        ':other_location_code' => $otherLoc,
        ':other_print_copies'  => $otherPrintCopies,
        ':other_page_type'     => $otherPageType,
        ':other_paper_color'   => $otherPaperColor,
        ':color_requirement'   => $colorRequirement,
        ':other_paper_size'    => $otherPaperSize,
        ':staple_location'     => $stapleLocation,
        ':fold_type'           => $foldType,
        ':binding_type'        => $bindingType,
        ':total_cost'          => $estimatedCost
    ]);
    
        // ── NEW: log the submission ─────────────────────────────────────────────
    $actor = $_SESSION['username'] ?? trim($firstName . ' ' . $lastName);
    $log   = $pdo->prepare("
      INSERT INTO activity_log (username, event, details)
      VALUES (:user,'submit_ticket',:details)
    ");
    $log->execute([
      ':user'    => $actor,
      ':details' => "Ticket #{$ticketNumber}"
    ]);
    
    // === EMAIL NOTIFICATION SECTION ===
    function getEmailSettings(PDO $pdo) {
        $sql = "SELECT * FROM email_settings LIMIT 1";
        $stmt = $pdo->query($sql);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }
    
function sendTicketNotification($ticketNumber, $jobTitle, $createdAt, $settings) {
    // --- 1) Build the message body exactly as before ---
    $message = sprintf(
        $settings['body_template'],
        $ticketNumber,
        $jobTitle,
        $createdAt
    );


    // --- 2) If SMTP is configured, decrypt password and use PHPMailer ---
    if (!empty($settings['smtp_host'])) {
        // decrypt the stored, encrypted password
        $smtpPassword = smtp_decrypt($settings['smtp_password']);

        $mail = new \PHPMailer\PHPMailer\PHPMailer(true);
        try {
            // Server settings
            $mail->CharSet  = 'UTF-8';
            $mail->Encoding = 'base64'; 
            $mail->isSMTP();
            $mail->isHTML(true);
            $mail->Host       = $settings['smtp_host'];
            $mail->Port       = (int)$settings['smtp_port'];
            if (!empty($settings['smtp_secure'])) {
                $mail->SMTPSecure = $settings['smtp_secure']; // 'ssl' or 'tls'
            }
            if (!empty($settings['smtp_username'])) {
                $mail->SMTPAuth   = true;
                $mail->Username   = $settings['smtp_username'];
                $mail->Password   = $smtpPassword;
            }

            // Recipients & content
            $mail->setFrom($settings['email_from'], 'RHC Printshop');
            // split on commas, trim whitespace
$tos = array_map('trim', explode(',', $settings['email_to']));

// add each valid email address
foreach ($tos as $addr) {
    if (filter_var($addr, FILTER_VALIDATE_EMAIL)) {
        $mail->addAddress($addr);
    }
}

            $mail->Subject = $settings['subject_line'];
            $mail->Body    = $message;
            $mail->AltBody = strip_tags($message);

            $mail->send();
        } catch (\PHPMailer\PHPMailer\Exception $e) {
            error_log("Mailer Error: {$mail->ErrorInfo}");
        }

    // --- 3) Otherwise fall back to PHP mail() exactly as before ---
    } else {
        $fromName  = "RHC Printshop";
        $fromEmail = $settings['email_from'];
        $toEmail   = $settings['email_to'];
        $subject   = $settings['subject_line'];

        $headers  = "MIME-Version: 1.0\r\n"
                  . "Content-Type: text/plain; charset=UTF-8\r\n"
                  . "From: \"{$fromName}\" <{$fromEmail}>\r\n"
                  . "Reply-To: {$fromEmail}\r\n"
                  . "Return-Path: {$fromEmail}\r\n"
                  . "X-Mailer: PHP/" . phpversion() . "\r\n";

                   $tos = array_map('trim', explode(',', $settings['email_to']));
            
            $valid = array_filter($tos, function($addr) {
                return filter_var($addr, FILTER_VALIDATE_EMAIL) !== false;
            });
            
            $toHeader = implode(',', $valid);
            
            mail(
              $toHeader,
              $subject,
              $message,
              $headers,
              "-f{$fromEmail}"
            );


    }

}

function sendRequesterNotification($ticketData, $settings) {
   // Skip if no requester email
if (empty($ticketData['email'])) {
    return;
}

// Build comprehensive HTML message
$historyUrl = 'https://printing.riohondo.edu/my_requests.php?email=' . urlencode($ticketData['email']) . '&token=' . $ticketData['email_token'];
$html = "
<div style='font-family: Arial, sans-serif; max-width: 700px; margin: 0 auto; background: #f8f9fa; padding: 20px;'>
    <div style='background: white; padding: 20px; border-radius: 8px; box-shadow: 0 2px 4px rgba(0,0,0,0.1);'>
        <h2 style='color: #2a6491; margin-top: 0;'>Print Request Confirmation</h2>
        <p>Your print request has been successfully submitted to RHC Printshop.</p>
        
        <div style='background: #e3f2fd; padding: 15px; border-radius: 5px; margin: 20px 0;'>
            <h3 style='margin-top: 0; color: #1976d2;'>Ticket Information</h3>
            <p><strong>Ticket Number:</strong> {$ticketData['ticket_number']}
               <a href='https://printing.riohondo.edu/status.php?ticket={$ticketData['ticket_number']}&token={$ticketData['check_token']}'
                  style='color: #1976d2;'>(check status)</a></p>
            <p><strong>Submitted:</strong> " . date('m/d/Y g:i A') . "</p>
        </div>

        <table style='width: 100%; border-collapse: collapse; margin: 20px 0;'>
            <tr style='background: #f5f5f5;'>
                <td colspan='2' style='padding: 10px; font-weight: bold; border: 1px solid #ddd;'>
                    Contact Information
                </td>
            </tr>
            <tr><td style='padding: 8px; border: 1px solid #ddd; width: 30%; font-weight: bold;'>Name:</td>
                <td style='padding: 8px; border: 1px solid #ddd;'>{$ticketData['first_name']} {$ticketData['last_name']}</td></tr>
            <tr><td style='padding: 8px; border: 1px solid #ddd; font-weight: bold;'>Department:</td>
                <td style='padding: 8px; border: 1px solid #ddd;'>{$ticketData['department_name']}</td></tr>
            <tr><td style='padding: 8px; border: 1px solid #ddd; font-weight: bold;'>Email:</td>
                <td style='padding: 8px; border: 1px solid #ddd;'>{$ticketData['email']}</td></tr>";

if (!empty($ticketData['phone'])) {
    $html .= "<tr><td style='padding: 8px; border: 1px solid #ddd; font-weight: bold;'>Phone:</td>
                 <td style='padding: 8px; border: 1px solid #ddd;'>{$ticketData['phone']}</td></tr>";
}

$html .= "
            <tr style='background: #f5f5f5;'>
                <td colspan='2' style='padding: 10px; font-weight: bold; border: 1px solid #ddd;'>
                    Job Details
                </td>
            </tr>
            <tr><td style='padding: 8px; border: 1px solid #ddd; font-weight: bold;'>Job Title:</td>
                <td style='padding: 8px; border: 1px solid #ddd;'>{$ticketData['job_title']}</td></tr>";

if (!empty($ticketData['description'])) {
    $html .= "<tr><td style='padding: 8px; border: 1px solid #ddd; font-weight: bold;'>Instructions/Notes:</td>
                 <td style='padding: 8px; border: 1px solid #ddd;'>" . nl2br(htmlspecialchars($ticketData['description'])) . "</td></tr>";
}

$html .= "
            <tr><td style='padding: 8px; border: 1px solid #ddd; font-weight: bold;'>Date Wanted:</td>
                <td style='padding: 8px; border: 1px solid #ddd;'>" . date('m/d/Y', strtotime($ticketData['date_wanted'])) . "</td></tr>
            <tr><td style='padding: 8px; border: 1px solid #ddd; font-weight: bold;'>Delivery Method:</td>
                <td style='padding: 8px; border: 1px solid #ddd;'>{$ticketData['delivery_method']}</td></tr>
            <tr><td style='padding: 8px; border: 1px solid #ddd; font-weight: bold;'>Location Code:</td>
                <td style='padding: 8px; border: 1px solid #ddd;'>{$ticketData['location_code']}</td></tr>

            <tr style='background: #f5f5f5;'>
                <td colspan='2' style='padding: 10px; font-weight: bold; border: 1px solid #ddd;'>
                    Print Specifications
                </td>
            </tr>
            <tr><td style='padding: 8px; border: 1px solid #ddd; font-weight: bold;'>Pages in Original:</td>
                <td style='padding: 8px; border: 1px solid #ddd;'>{$ticketData['pages_in_original']}</td></tr>
            <tr><td style='padding: 8px; border: 1px solid #ddd; font-weight: bold;'>Number of Sets:</td>
                <td style='padding: 8px; border: 1px solid #ddd;'>{$ticketData['number_of_sets']}</td></tr>
            <tr><td style='padding: 8px; border: 1px solid #ddd; font-weight: bold;'>Page Layout:</td>
                <td style='padding: 8px; border: 1px solid #ddd;'>{$ticketData['page_layout']}</td></tr>
            <tr><td style='padding: 8px; border: 1px solid #ddd; font-weight: bold;'>Print Copies In:</td>
                <td style='padding: 8px; border: 1px solid #ddd;'>{$ticketData['print_copies_in']}</td></tr>
            <tr><td style='padding: 8px; border: 1px solid #ddd; font-weight: bold;'>Page Type:</td>
                <td style='padding: 8px; border: 1px solid #ddd;'>{$ticketData['page_type']}</td></tr>
            <tr><td style='padding: 8px; border: 1px solid #ddd; font-weight: bold;'>Paper Color:</td>
                <td style='padding: 8px; border: 1px solid #ddd;'>{$ticketData['paper_color']}</td></tr>
            <tr><td style='padding: 8px; border: 1px solid #ddd; font-weight: bold;'>Paper Size:</td>
                <td style='padding: 8px; border: 1px solid #ddd;'>{$ticketData['paper_size']}</td></tr>";

if (!empty($ticketData['other_options'])) {
    $html .= "<tr><td style='padding: 8px; border: 1px solid #ddd; font-weight: bold;'>Additional Options:</td>
                 <td style='padding: 8px; border: 1px solid #ddd;'>{$ticketData['other_options']}</td></tr>";
}

if (!empty($ticketData['cut_paper'])) {
    $html .= "<tr><td style='padding: 8px; border: 1px solid #ddd; font-weight: bold;'>Cut Paper:</td>
                 <td style='padding: 8px; border: 1px solid #ddd;'>{$ticketData['cut_paper']}</td></tr>";
}

if (!empty($ticketData['staple_location'])) {
    $html .= "<tr><td style='padding: 8px; border: 1px solid #ddd; font-weight: bold;'>Staple Location:</td>
                 <td style='padding: 8px; border: 1px solid #ddd;'>{$ticketData['staple_location']}</td></tr>";
}

if (!empty($ticketData['fold_type'])) {
    $html .= "<tr><td style='padding: 8px; border: 1px solid #ddd; font-weight: bold;'>Fold Type:</td>
                 <td style='padding: 8px; border: 1px solid #ddd;'>{$ticketData['fold_type']}</td></tr>";
}

if (!empty($ticketData['binding_type'])) {
    $html .= "<tr><td style='padding: 8px; border: 1px solid #ddd; font-weight: bold;'>Binding Type:</td>
                 <td style='padding: 8px; border: 1px solid #ddd;'>{$ticketData['binding_type']}</td></tr>";
}

if (!empty($ticketData['color_requirement'])) {
    $html .= "<tr><td style='padding: 8px; border: 1px solid #ddd; font-weight: bold;'>Color Requirement:</td>
                 <td style='padding: 8px; border: 1px solid #ddd;'>{$ticketData['color_requirement']}</td></tr>";
}

if (!empty($ticketData['total_cost']) && $ticketData['total_cost'] > 0) {
    $html .= "<tr><td style='padding: 8px; border: 1px solid #ddd; font-weight: bold;'>Estimated Cost:</td>
                 <td style='padding: 8px; border: 1px solid #ddd;'>$" . number_format($ticketData['total_cost'], 2) . "</td></tr>";
}

$html .= "
        </table>

        <p style='margin-top: 20px;'><a href='{$historyUrl}' style='color: #1976d2;'>View all your requests</a></p>

        <p style='margin-top: 30px;'>Please save this ticket number for your records. You will receive another email
        when your project begins processing and when it is complete.</p>
        <p>If you have any questions, please email <a href='mailto:printing@riohondo.edu' style='color: #2a6491;'>printing@riohondo.edu</a>.</p>
        <p style='font-size: 12px; color: #666; margin-bottom: 0;'>This is an automated message, please do not reply to this email.</p>
    </div>
</div>";

// Build comprehensive plain text message
$plain = "=================================================
PRINT REQUEST CONFIRMATION
=================================================

Your print request has been successfully submitted to RHC Printshop.

TICKET INFORMATION:
Ticket Number: {$ticketData['ticket_number']}
Status Link: https://printing.riohondo.edu/status.php?ticket={$ticketData['ticket_number']}&token={$ticketData['check_token']}
History Link: {$historyUrl}
Submitted: " . date('m/d/Y g:i A') . "

CONTACT INFORMATION:
Name: {$ticketData['first_name']} {$ticketData['last_name']}
Department: {$ticketData['department_name']}
Email: {$ticketData['email']}";

if (!empty($ticketData['phone'])) {
    $plain .= "\nPhone: {$ticketData['phone']}";
}

$plain .= "

JOB DETAILS:
Job Title: {$ticketData['job_title']}";

if (!empty($ticketData['description'])) {
    $plain .= "\nInstructions/Notes: {$ticketData['description']}";
}

$plain .= "
Date Wanted: " . date('m/d/Y', strtotime($ticketData['date_wanted'])) . "
Delivery Method: {$ticketData['delivery_method']}
Location Code: {$ticketData['location_code']}

PRINT SPECIFICATIONS:
Pages in Original: {$ticketData['pages_in_original']}
Number of Sets: {$ticketData['number_of_sets']}
Page Layout: {$ticketData['page_layout']}
Print Copies In: {$ticketData['print_copies_in']}
Page Type: {$ticketData['page_type']}
Paper Color: {$ticketData['paper_color']}
Paper Size: {$ticketData['paper_size']}";

if (!empty($ticketData['other_options'])) {
    $plain .= "\nAdditional Options: {$ticketData['other_options']}";
}

if (!empty($ticketData['cut_paper'])) {
    $plain .= "\nCut Paper: {$ticketData['cut_paper']}";
}

if (!empty($ticketData['staple_location'])) {
    $plain .= "\nStaple Location: {$ticketData['staple_location']}";
}

if (!empty($ticketData['fold_type'])) {
    $plain .= "\nFold Type: {$ticketData['fold_type']}";
}

if (!empty($ticketData['binding_type'])) {
    $plain .= "\nBinding Type: {$ticketData['binding_type']}";
}

if (!empty($ticketData['color_requirement'])) {
    $plain .= "\nColor Requirement: {$ticketData['color_requirement']}";
}

if (!empty($ticketData['total_cost']) && $ticketData['total_cost'] > 0) {
    $plain .= "\nEstimated Cost: $" . number_format($ticketData['total_cost'], 2);
}

$plain .= "

Please save this ticket number for your records. You will receive another email 
when your project begins processing and when it is complete.

If you have any questions, please email printing@riohondo.edu.

This is an automated message, please do not reply to this email.
=================================================";

// Send the email using existing SMTP logic
if (!empty($settings['smtp_host'])) {
    $smtpPassword = smtp_decrypt($settings['smtp_password']);
    $mail = new \PHPMailer\PHPMailer\PHPMailer(true);
    try {
        $mail->CharSet  = 'UTF-8';
        $mail->Encoding = 'base64'; 
        $mail->isSMTP();
        $mail->isHTML(true);
        $mail->Host       = $settings['smtp_host'];
        $mail->Port       = (int)$settings['smtp_port'];
        if (!empty($settings['smtp_secure'])) {
            $mail->SMTPSecure = $settings['smtp_secure'];
        }
        if (!empty($settings['smtp_username'])) {
            $mail->SMTPAuth   = true;
            $mail->Username   = $settings['smtp_username'];
            $mail->Password   = $smtpPassword;
        }

        $mail->setFrom($settings['email_from'], 'RHC Printshop');
        $mail->addAddress($ticketData['email']);
        $mail->Subject = "Print Request Confirmation - Ticket #{$ticketData['ticket_number']}";
        $mail->Body    = $html;
        $mail->AltBody = $plain;

        $mail->send();
    } catch (\PHPMailer\PHPMailer\Exception $e) {
        error_log("Requester Email Error: {$mail->ErrorInfo}");
    }
} else {
    $fromName  = "RHC Printshop";
    $fromEmail = $settings['email_from'];
    $subject   = "Print Request Confirmation - Ticket #{$ticketData['ticket_number']}";

    $headers  = "MIME-Version: 1.0\r\n"
              . "Content-Type: text/html; charset=UTF-8\r\n"
              . "From: \"{$fromName}\" <{$fromEmail}>\r\n"
              . "Reply-To: {$fromEmail}\r\n"
              . "Return-Path: {$fromEmail}\r\n"
              . "X-Mailer: PHP/" . phpversion() . "\r\n";

    mail($ticketData['email'], $subject, $html, $headers, "-f{$fromEmail}");
}
}

// Call the existing notification for staff
$settings = getEmailSettings($pdo);
if ($settings) {
    sendTicketNotification($ticketNumber, $jobTitle, date("m-d-Y H:i:s"), $settings);
    // Prepare complete ticket data for email
$ticketData = [
    'ticket_number' => $ticketNumber,
    'check_token' => $checkToken,
    'first_name' => $firstName,
    'last_name' => $lastName,
    'department_name' => $departmentName,
    'email' => $email,
    'phone' => $phone,
    'location_code' => $locationCode,
    'date_wanted' => $dateWanted,
    'delivery_method' => $deliveryMethod,
    'job_title' => $jobTitle,
    'description' => $description,
    'pages_in_original' => $pagesOriginal,
    'number_of_sets' => $numberOfSets,
    'page_layout' => $pageLayout,
    'print_copies_in' => $printCopiesIn,
    'page_type' => $pageType,
    'paper_color' => $paperColor,
    'paper_size' => $paperSize,
    'other_options' => $otherOptions,
    'cut_paper' => $cutPaper,
    'staple_location' => $stapleLocation,
    'fold_type' => $foldType,
    'binding_type' => $bindingType,
    'color_requirement' => $colorRequirement,
    'total_cost' => $estimatedCost,
    'email_token' => $emailToken
];

sendRequesterNotification($ticketData, $settings);
}

// Auto-assignment logic
try {
    $autoAssignStmt = $pdo->prepare("
        SELECT target_username 
        FROM auto_assign_settings 
        WHERE enabled = 1 
        LIMIT 1
    ");
    $autoAssignStmt->execute();
    $autoAssign = $autoAssignStmt->fetch(PDO::FETCH_ASSOC);
    
    if ($autoAssign && !empty($autoAssign['target_username'])) {
        // Auto-assign the ticket
        $assignStmt = $pdo->prepare("
            UPDATE job_tickets 
            SET assigned_to = :assigned_to 
            WHERE ticket_number = :ticket_number
        ");
        $assignStmt->execute([
            ':assigned_to' => $autoAssign['target_username'],
            ':ticket_number' => $ticketNumber
        ]);
        
        // Log the auto-assignment
        $autoLogStmt = $pdo->prepare("
            INSERT INTO activity_log (username, event, details) 
            VALUES ('system', 'auto_assign_ticket', :details)
        ");
        $autoLogStmt->execute([
            ':details' => "Auto-assigned ticket #{$ticketNumber} to {$autoAssign['target_username']}"
        ]);
    }
} catch (PDOException $e) {
    // Silently fail auto-assignment, don't break ticket creation
    error_log("Auto-assignment failed: " . $e->getMessage());
}

// on successful insert:
$response['success']      = true;
$response['ticketNumber'] = $ticketNumber;
$statusUrl = 'status.php?ticket=' . $ticketNumber . '&token=' . $checkToken;
$response['message']      = 'Your order has been placed and Ticket number is #' . $ticketNumber . '. <br/>You can check the status at <a href="' . $statusUrl . '">' . $statusUrl . '</a>.<br/>Please keep this ticket number for your records. You will receive an email once your project is assigned and processing.';
echo json_encode($response);
exit;

} 

catch (PDOException $e) {
  // catches *any* DB error (GENERATE, INSERT, email-settings fetch, etc.)
  renderError("Database error: " . $e->getMessage());
}
?>