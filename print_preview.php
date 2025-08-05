<?php
/**
 * print_preview.php
 * Displays a single ticket in a print-friendly layout and triggers the print dialog.
 */

// Start the session and check if logged_in is true
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
    // Not logged in, redirect to login
    header('Location: login.php');
    exit;
}
// 1. Validate and grab the ticket number from the query string
if (!isset($_GET['ticket'])) {
    die("Error: No ticket number specified.");
}
$ticketNumber = trim($_GET['ticket']);

// 2. Database connection
require_once 'assets/database.php';

// 3. Fetch the ticket record by ticket_number
$stmt = $pdo->prepare("SELECT * FROM job_tickets WHERE ticket_number = :ticket_number LIMIT 1");
$stmt->execute([':ticket_number' => $ticketNumber]);
$ticket = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$ticket) {
    die("Ticket not found or invalid ticket number.");
}

$reqDate  = toLA($ticket['created_at'],  'm/d/Y');
$dueDate  = toLA($ticket['date_wanted'], 'm/d/Y');
$compDate = !empty($ticket['completed_at'])
          ? toLA($ticket['completed_at'], 'm/d/Y H:i:s')
          : null;
$displayNumber = htmlspecialchars($ticket['ticket_number']);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Print Ticket #<?= htmlspecialchars($ticketNumber) ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="assets/css/style.css" rel="stylesheet">
</head>
<body onload="setTimeout(function(){ window.print(); }, 1000);">
<div class="container print-width">
    <h2 class="my-3">Print Ticket #<?= htmlspecialchars($ticketNumber) ?></h2>
      <p>
    Request Date: <?= htmlspecialchars($reqDate) ?><br>
    Due Date: <?= htmlspecialchars($dueDate) ?>
    <?php if ($compDate): ?><br>
      Completed On: <?= htmlspecialchars($compDate) ?>
    <?php endif; ?>
  </p>

    <table class="table table-bordered">
        <tbody>
            <?php
            // Define an associative array mapping keys to labels. (Add any additional keys as needed.)
            $fields = [
                'ticket_number'       => 'Ticket Number',
                'first_name'          => 'First Name',
                'last_name'           => 'Last Name',
                'department_name'     => 'Department Name',
                'email'               => 'Email',
                'phone'               => 'Phone',
                'location_code'       => 'Location Code',
                'other_location_code' => 'Other Location Code',
                'delivery_method'     => 'Delivery Method',
                'job_title'           => 'Job Title',
                'description'         => 'Description',
                'pages_in_original'   => 'Pages in Original',
                
                'number_of_sets'      => 'Number of Sets',
                'page_layout'         => 'Page Layout',
                'print_copies_in'     => 'Print Copies In',
                'other_print_copies'  => 'Other Print Copies',
                'page_type'           => 'Page Type / Weight',
                'other_page_type'     => 'Other Page Type',
                'paper_color'         => 'Paper Color',
                'other_paper_color'   => 'Other Paper Color',
                'color_requirement'   => 'Color Requirement',
                'paper_size'          => 'Paper Size',
                'other_paper_size'    => 'Other Paper Size',
                'other_options'       => 'Other Options',
                'cut_paper'           => 'Cut Paper',
                'separator_color'     => 'Separator Color',
                'staple_location'     => 'Staple Location',
                'fold_type'           => 'Fold Type',
                'binding_type'        => 'Binding Type',
                'assigned_to'         => 'Assigned To',
                'admin_notes'               => 'Notes',
                'total_cost' => 'Total Cost',
                
            ];

            // Loop through each field and output a row only if the value is not empty.
            foreach ($fields as $key => $label) {
    $val = $ticket[$key] ?? '';
    if ($val === '' || $val === null) continue;

    // format Completed On with helper
    if ($key === 'completed_at') {
        $val = toLA($val, 'm/d/Y H:i:s');
    } elseif ($key === 'notes') { 
        $val = nl2br(htmlspecialchars($val));
    } elseif ($key === 'total_cost') {
        $val = '$' . number_format((float)$val, 2);
    } else {
        $val = htmlspecialchars($val);
    }
    echo "<tr><th style='width:30%'>{$label}</th><td>{$val}</td></tr>";
}

            
            ?>
        </tbody>
    </table>
</div>
</body>
</html>
