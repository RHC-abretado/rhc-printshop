<?php
define('PRINTSHOP_ROOT', __DIR__);

// 1) Load your encryption helper
require_once PRINTSHOP_ROOT . '/helpers/encryption.php';

// 2) Load PHPMailer's classes (relative to the printshop folder)
require_once PRINTSHOP_ROOT . '/libs/phpmailer/src/Exception.php';
require_once PRINTSHOP_ROOT . '/libs/phpmailer/src/PHPMailer.php';
require_once PRINTSHOP_ROOT . '/libs/phpmailer/src/SMTP.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Only Super Admins may access
if (
    !isset($_SESSION['logged_in'], $_SESSION['role'])
    || $_SESSION['logged_in'] !== true
    || $_SESSION['role'] !== 'Super Admin'
) {
    header("Location: login.php");
    exit;
}

require_once 'header.php';

$deleteMessage = '';
$delTicket      = null;
$delTicketNumber = '';

// --- Handle the fetch / delete workflow ---
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Fetch step
    if (isset($_POST['fetch_ticket'])) {
        $delTicketNumber = trim($_POST['del_ticket_number'] ?? '');
        if ($delTicketNumber === '') {
            $deleteMessage = '<div class="alert alert-danger">Please enter a ticket number.</div>';
        } else {
            $stmt = $pdo->prepare("SELECT * FROM job_tickets WHERE ticket_number = :tn LIMIT 1");
            $stmt->execute([':tn' => $delTicketNumber]);
            $delTicket = $stmt->fetch(PDO::FETCH_ASSOC);
            if (!$delTicket) {
                $deleteMessage = '<div class="alert alert-danger">Ticket not found.</div>';
            }
        }
    }
    // Confirm delete step
    elseif (isset($_POST['confirm_delete'])) {
        $delTicketNumber = trim($_POST['del_ticket_number'] ?? '');
        $confirm         = isset($_POST['confirm_delete_checkbox']);
        if (!$confirm) {
            $deleteMessage = '<div class="alert alert-danger">Please check "Confirm deletion" to proceed.</div>';
            // re–fetch so we can re–display details
            $stmt       = $pdo->prepare("SELECT * FROM job_tickets WHERE ticket_number = :tn LIMIT 1");
            $stmt->execute([':tn' => $delTicketNumber]);
            $delTicket = $stmt->fetch(PDO::FETCH_ASSOC);
        } else {
            // grab file paths
            $stmt = $pdo->prepare("SELECT file_path FROM job_tickets WHERE ticket_number = :tn LIMIT 1");
            $stmt->execute([':tn' => $delTicketNumber]);
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            // delete row
            $delStmt = $pdo->prepare("DELETE FROM job_tickets WHERE ticket_number = :tn");
            $delStmt->execute([':tn' => $delTicketNumber]);
            // remove uploads
            if (!empty($row['file_path'])) {
                $uploadDir = __DIR__ . '/uploads/';
                foreach (explode(',', $row['file_path']) as $p) {
                    $p = trim($p);
                    $full = $uploadDir . basename($p);
                    if (file_exists($full)) {
                        unlink($full);
                    }
                }
            }
            $deleteMessage = '<div class="alert alert-success">'
                           . "Ticket #".htmlspecialchars($delTicketNumber)." deleted successfully."
                           . '</div>';
            $delTicket = null;
            $delTicketNumber = '';
        }
    }
}

    // Calculate cost metrics
try {
    // Overall total cost from all tickets
    $overallTotal = $pdo->query("
        SELECT COALESCE(SUM(total_cost), 0) as total 
        FROM job_tickets 
        WHERE total_cost IS NOT NULL AND total_cost > 0
    ")->fetchColumn();
    
    // Current month total cost
    $currentMonthTotal = $pdo->query("
        SELECT COALESCE(SUM(total_cost), 0) as total 
        FROM job_tickets 
        WHERE total_cost IS NOT NULL 
        AND total_cost > 0
        AND MONTH(created_at) = MONTH(NOW()) 
        AND YEAR(created_at) = YEAR(NOW())
    ")->fetchColumn();
    
    // Format the values
    $overallTotalFormatted = number_format((float)$overallTotal, 2);
    $currentMonthFormatted = number_format((float)$currentMonthTotal, 2);
    
} catch (PDOException $e) {
    $overallTotalFormatted = '0.00';
    $currentMonthFormatted = '0.00';
}

// Calculate revenue history for modal
try {
    // Get last 12 months of revenue data
    $revenueHistory = $pdo->query("
        SELECT 
            DATE_FORMAT(created_at, '%Y-%m') as month_year,
            DATE_FORMAT(created_at, '%M %Y') as month_name,
            COALESCE(SUM(total_cost), 0) as monthly_total,
            COUNT(*) as ticket_count
        FROM job_tickets 
        WHERE total_cost IS NOT NULL 
        AND total_cost > 0
        AND created_at >= DATE_SUB(NOW(), INTERVAL 12 MONTH)
        GROUP BY DATE_FORMAT(created_at, '%Y-%m')
        ORDER BY month_year DESC
    ")->fetchAll(PDO::FETCH_ASSOC);
    
    // Calculate quarterly data
    $quarterlyData = $pdo->query("
        SELECT 
            CONCAT('Q', QUARTER(created_at), ' ', YEAR(created_at)) as quarter,
            COALESCE(SUM(total_cost), 0) as quarterly_total,
            COUNT(*) as ticket_count
        FROM job_tickets 
        WHERE total_cost IS NOT NULL 
        AND total_cost > 0
        AND created_at >= DATE_SUB(NOW(), INTERVAL 12 MONTH)
        GROUP BY YEAR(created_at), QUARTER(created_at)
        ORDER BY YEAR(created_at) DESC, QUARTER(created_at) DESC
    ")->fetchAll(PDO::FETCH_ASSOC);
    
} catch (PDOException $e) {
    $revenueHistory = [];
    $quarterlyData = [];
}

// --- Fetch current email settings ---
$message = '';
try {
    $stmt     = $pdo->query("SELECT * FROM email_settings WHERE id = 1 LIMIT 1");
    $settings = $stmt->fetch(PDO::FETCH_ASSOC) ?: [];
} catch (PDOException $e) {
    $settings = [];
    $message  = '<div class="alert alert-danger">DB Error: '.htmlspecialchars($e->getMessage()).'</div>';
}

// If user clicked “Send Test Email”
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['test_smtp'])) {
    // re‑fetch the latest settings
    $stmt     = $pdo->query("SELECT * FROM email_settings WHERE id = 1");
    $settings = $stmt->fetch(PDO::FETCH_ASSOC) ?: [];
    
    try {
        // decrypt password
        $smtpPass = smtp_decrypt($settings['smtp_password']);

        // configure PHPMailer
        $mail = new \PHPMailer\PHPMailer\PHPMailer(true);
        $mail->CharSet  = 'UTF-8';
        $mail->Encoding = 'base64'; 
        $mail->isSMTP();
        $mail->isHTML(true);
        $mail->Host       = $settings['smtp_host'];
        $mail->Port       = (int)$settings['smtp_port'];
        if ($settings['smtp_secure']) {
            $mail->SMTPSecure = $settings['smtp_secure'];
        }
        if ($settings['smtp_username']) {
            $mail->SMTPAuth   = true;
            $mail->Username   = $settings['smtp_username'];
            $mail->Password   = $smtpPass;
        }

        // build a minimal test message
        $mail->setFrom($settings['email_from'], 'SMTP Test');
        $mail->addAddress($settings['email_to']);
        $mail->Subject = 'PHPMailer SMTP Settings Test';
        $mail->Body    = "If you’re reading this, your SMTP settings are correct!";
        $mail->AltBody = strip_tags ($message);

        // actually send
        $mail->send();

        $testMessage = '<div class="alert alert-success mt-3">'
                     . '✅ Test email sent successfully to '
                     . htmlspecialchars($settings['email_to'])
                     . '.</div>';
    } catch (\PHPMailer\PHPMailer\Exception $e) {
        $testMessage = '<div class="alert alert-danger mt-3">'
                     . '❌ SMTP test failed: '
                     . htmlspecialchars($e->getMessage())
                     . '</div>';
    }
}

// Handle Cache Clear form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['clear_cache'])) {
    try {
        // Increment the cache version to force browser refresh
        $u = $pdo->prepare("
            UPDATE email_settings
               SET cache_version = cache_version + 1
             WHERE id = 1
        ");
        $u->execute();
        
        // Update in-memory so UI reflects the change
        $settings['cache_version'] = ($settings['cache_version'] ?? 1) + 1;
        
        $cacheMessage = '<div class="alert alert-success">Cache cleared successfully! Users will now see the latest version.</div>';
    } catch (PDOException $e) {
        $cacheMessage = '<div class="alert alert-danger">DB Error: '
                      . htmlspecialchars($e->getMessage())
                      . '</div>';
    }
}

// Handle Debug form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_debug'])) {
    $debugMode = isset($_POST['debug_mode']) ? 1 : 0;

    try {
        $u = $pdo->prepare("
            UPDATE email_settings
               SET debug_mode = :debug_mode
             WHERE id = 1
        ");
        $u->execute([':debug_mode' => $debugMode]);
        // update in‐memory so UI matches
        $settings['debug_mode'] = $debugMode;
        $debugMessage = '<div class="alert alert-success">Debug mode updated.</div>';
    } catch (PDOException $e) {
        $debugMessage = '<div class="alert alert-danger">DB Error: '
                      . htmlspecialchars($e->getMessage())
                      . '</div>';
    }
}

// Handle Maintenance Mode form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_maintenance'])) {
    $maintenanceMode = isset($_POST['maintenance_mode']) ? 1 : 0;
    $maintenanceTime = trim($_POST['maintenance_time'] ?? '15-30 minutes');

    try {
        $u = $pdo->prepare("
            UPDATE email_settings
               SET maintenance_mode = :maintenance_mode,
                   maintenance_time = :maintenance_time
             WHERE id = 1
        ");
        $u->execute([
            ':maintenance_mode' => $maintenanceMode,
            ':maintenance_time' => $maintenanceTime
        ]);
        // update in‐memory so UI matches
        $settings['maintenance_mode'] = $maintenanceMode;
        $settings['maintenance_time'] = $maintenanceTime;
        $maintenanceMessage = '<div class="alert alert-success">Maintenance mode settings updated.</div>';
    } catch (PDOException $e) {
        $maintenanceMessage = '<div class="alert alert-danger">DB Error: '
                      . htmlspecialchars($e->getMessage())
                      . '</div>';
    }
}

// Handle email‐settings form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_email'])) {
    // core email fields
    $email_from    = trim($_POST['email_from']    ?? '');
    $email_to      = trim($_POST['email_to']      ?? '');
    $subject_line  = trim($_POST['subject_line']  ?? '');
    $body_template = trim($_POST['body_template'] ?? '');

    // new SMTP fields
    $smtp_host     = trim($_POST['smtp_host']     ?? '');
    $smtp_port     = (int)  ($_POST['smtp_port']   ?? 0);
    $smtp_secure   = trim($_POST['smtp_secure']   ?? '');
    $smtp_username = trim($_POST['smtp_username'] ?? '');
    $raw_password  = trim($_POST['smtp_password'] ?? '');

    // encrypt only if the user entered something
    if ($raw_password !== '') {
        $encrypted_password = smtp_encrypt($raw_password);
    } else {
        // keep the old encrypted password if they left this blank
        $encrypted_password = $settings['smtp_password'] ?? '';
    }
    

    $notify = isset($_POST['notify_on_complete']) ? 1 : 0;
    $notifyOnProcessing = isset($_POST['notify_on_processing']) ? 1 : 0;
    $notifyOnAssignment = isset($_POST['notify_on_assignment']) ? 1 : 0;
    $notifyOnHold = isset($_POST['notify_on_hold']) ? 1 : 0;


    try {
        $sql = "UPDATE email_settings 
                SET email_from    = :email_from,
                    email_to      = :email_to,
                    subject_line  = :subject_line,
                    body_template = :body_template,
                    smtp_host     = :smtp_host,
                    smtp_port     = :smtp_port,
                    smtp_secure   = :smtp_secure,
                    smtp_username = :smtp_username,
                    smtp_password = :smtp_password,
                    notify_on_complete    = :notify_on_complete,
                    notify_on_processing = :notify_on_processing,
                    notify_on_assignment = :notify_on_assignment,
                     notify_on_hold = :notify_on_hold
                WHERE id = 1";
        $s = $pdo->prepare($sql);
        $s->execute([
            ':email_from'    => $email_from,
            ':email_to'      => $email_to,
            ':subject_line'  => $subject_line,
            ':body_template' => $body_template,
            ':smtp_host'     => $smtp_host,
            ':smtp_port'     => $smtp_port,
            ':smtp_secure'   => $smtp_secure,
            ':smtp_username' => $smtp_username,
            ':smtp_password' => $encrypted_password,
            ':notify_on_complete'   => $notify,
            ':notify_on_processing' => $notifyOnProcessing,
            ':notify_on_assignment' => $notifyOnAssignment,
            ':notify_on_hold' => $notifyOnHold
        ]);

        $message = '<div class="alert alert-success">Email settings updated successfully.</div>';

        // refresh settings so the form shows the latest values
        $settings = [
            'email_from'     => $email_from,
            'email_to'       => $email_to,
            'subject_line'   => $subject_line,
            'body_template'  => $body_template,
            'smtp_host'      => $smtp_host,
            'smtp_port'      => $smtp_port,
            'smtp_secure'    => $smtp_secure,
            'smtp_username'  => $smtp_username,
            // leave out raw password from echoing back
            'smtp_password'  => $encrypted_password,
            'notify_on_complete' => $notify,
            'notify_on_processing' => $notifyOnProcessing,
            'notify_on_assignment' => $notifyOnAssignment,
            'notify_on_hold' => $notifyOnHold
        ];
    } catch (PDOException $e) {
        $message = '<div class="alert alert-danger">DB Error: '.htmlspecialchars($e->getMessage()).'</div>';
    }

}

?>
<!-- Add this just before the closing </head> tag in your header.php file 
     or at the top of the settings.php file before the HTML output begins -->
<style>
.nav-tabs .nav-link {
  color: #495057;
  background-color: #fff;
  border-color: #dee2e6 #dee2e6 #fff;
}

.nav-tabs .nav-link.active {
  color: #495057;
  background-color: #fff;
  border-color: #dee2e6 #dee2e6 #fff;
  font-weight: bold;
}

.nav-tabs .nav-link:hover, 
.nav-tabs .nav-link:focus {
  border-color: #e9ecef #e9ecef #dee2e6;
}
</style>
<h1>Administration & Settings</h1>

<!-- Responsive Navigation: Tabs for desktop, dropdown for mobile -->
<div class="mb-4">
  <!-- Desktop Navigation (Tabs) - Hidden on mobile -->
  <ul class="nav nav-tabs d-none d-md-flex" id="settingsTabs" role="tablist">
    <li class="nav-item" role="presentation">
      <button class="nav-link active" id="dashboard-tab" data-bs-toggle="tab" data-bs-target="#dashboard" type="button" role="tab" aria-controls="dashboard" aria-selected="true">
        <i class="bi bi-speedometer2"></i> Dashboard
      </button>
    </li>
    <li class="nav-item" role="presentation">
      <button class="nav-link" id="location-codes-tab" data-bs-toggle="tab" data-bs-target="#location-codes" type="button" role="tab" aria-controls="location-codes" aria-selected="false">
        <i class="bi bi-geo-alt"></i> Location Codes
      </button>
    </li>
    <li class="nav-item" role="presentation">
      <button class="nav-link" id="email-tab" data-bs-toggle="tab" data-bs-target="#email" type="button" role="tab" aria-controls="email" aria-selected="false">
        <i class="bi bi-envelope"></i> Email Settings
      </button>
    </li>
    <li class="nav-item" role="presentation">
      <button class="nav-link" id="user-tab" data-bs-toggle="tab" data-bs-target="#user" type="button" role="tab" aria-controls="user" aria-selected="false">
        <i class="bi bi-people"></i> User Management
      </button>
    </li>
    <li class="nav-item" role="presentation">
      <button class="nav-link" id="data-tab" data-bs-toggle="tab" data-bs-target="#data" type="button" role="tab" aria-controls="data" aria-selected="false">
        <i class="bi bi-database"></i> Data Management
      </button>
    </li>
    <li class="nav-item" role="presentation">
      <button class="nav-link" id="system-tab" data-bs-toggle="tab" data-bs-target="#system" type="button" role="tab" aria-controls="system" aria-selected="false">
        <i class="bi bi-gear"></i> System Settings
      </button>
    </li>
  </ul>

  <!-- Mobile Navigation (Dropdown) - Hidden on desktop -->
  <div class="dropdown d-md-none">
    <button class="btn btn-outline-primary dropdown-toggle w-100" type="button" id="settingsDropdown" data-bs-toggle="dropdown" aria-expanded="false">
      <i class="bi bi-speedometer2"></i> Dashboard
    </button>
    <ul class="dropdown-menu w-100" aria-labelledby="settingsDropdown">
      <li><a class="dropdown-item" href="#" data-target="#dashboard" data-icon="bi-speedometer2">
        <i class="bi bi-speedometer2"></i> Dashboard
      </a></li>
      <li><a class="dropdown-item" href="#" data-target="#location-codes" data-icon="bi-geo-alt">
        <i class="bi bi-geo-alt"></i> Location Codes
      </a></li>
      <li><a class="dropdown-item" href="#" data-target="#email" data-icon="bi-envelope">
        <i class="bi bi-envelope"></i> Email Settings
      </a></li>
      <li><a class="dropdown-item" href="#" data-target="#user" data-icon="bi-people">
        <i class="bi bi-people"></i> User Management
      </a></li>
      <li><a class="dropdown-item" href="#" data-target="#data" data-icon="bi-database">
        <i class="bi bi-database"></i> Data Management
      </a></li>
      <li><a class="dropdown-item" href="#" data-target="#system" data-icon="bi-gear">
        <i class="bi bi-gear"></i> System Settings
      </a></li>
    </ul>
  </div>
</div>

<div class="tab-content" id="settingsTabsContent">
  <!-- Dashboard Tab -->
  <div class="tab-pane fade show active" id="dashboard" role="tabpanel" aria-labelledby="dashboard-tab">
<!-- Revenue Overview Section -->
<div class="alert alert-info mb-4" role="region" aria-labelledby="revenue-heading">
  <h5 id="revenue-heading" class="mb-3">
    <i class="bi bi-graph-up"></i> Revenue Overview
    <button type="button" class="btn btn-outline-primary btn-sm ms-2" data-bs-toggle="modal" data-bs-target="#revenueHistoryModal">
      <i class="bi bi-clock-history"></i> View History
    </button>
  </h5>
  <div class="row">
    <div class="col-md-6">
      <strong>Overall Total:</strong> 
      <span class="text-success fs-4 fw-bold">$<?= $overallTotalFormatted ?></span>
      <small class="text-muted ms-2">from all tickets</small>
    </div>
    <div class="col-md-6">
      <strong><?= date('F Y') ?>:</strong> 
      <span class="text-primary fs-4 fw-bold">$<?= $currentMonthFormatted ?></span>
      <small class="text-muted ms-2">current month</small>
    </div>
  </div>
</div>
    
    
    <div class="row">

      <div class="col-md-6 col-lg-4 mb-4">
        <div class="card h-100 border-success">
          <div class="card-header bg-success text-white">
            <i class="bi bi-people"></i> User Management
          </div>
          <div class="card-body">
            <h5 class="card-title">Manage Staff Accounts</h5>
            <p class="card-text">Create, edit, and manage system users and their permissions.</p>
            <a href="user_management.php" class="btn btn-outline-success">
              <i class="bi bi-person-plus"></i> Manage Users
            </a>
          </div>
        </div>
      </div>
      
      <div class="col-md-6 col-lg-4 mb-4">
        <div class="card h-100 border-info">
          <div class="card-header bg-info text-white">
            <i class="bi bi-bar-chart"></i> Analytics
          </div>
          <div class="card-body">
            <h5 class="card-title">Performance Reports</h5>
            <p class="card-text">View analytics and performance reports for the printshop.</p>
            <a href="analytics.php" class="btn btn-outline-info">
              <i class="bi bi-graph-up"></i> View Analytics
            </a>
          </div>
        </div>
      </div>
      
      <div class="col-md-6 col-lg-4 mb-4">
        <div class="card h-100 border-warning">
          <div class="card-header bg-warning text-dark">
            <i class="bi bi-file-earmark-spreadsheet"></i> Data Export
          </div>
          <div class="card-body">
            <h5 class="card-title">Export System Data</h5>
            <p class="card-text">Generate reports and export data for advanced analysis.</p>
            <a href="export.php" class="btn btn-outline-warning">
              <i class="bi bi-download"></i> Export Data
            </a>
          </div>
        </div>
      </div>
      
      <div class="col-md-6 col-lg-4 mb-4">
        <div class="card h-100 border-danger">
          <div class="card-header bg-danger text-white">
            <i class="bi bi-trash"></i> Data Cleanup
          </div>
          <div class="card-body">
            <h5 class="card-title">Manage Storage</h5>
            <p class="card-text">Delete tickets and manage file storage for efficient system operation.</p>
            <div class="d-flex gap-2">
              <a href="delete_tickets.php" class="btn btn-outline-danger">
                <i class="bi bi-trash"></i> Delete Tickets
              </a>
              <a href="cleanup_uploads.php" class="btn btn-outline-danger">
                <i class="bi bi-trash"></i> Manage Storage
              </a>
            </div>
          </div>
        </div>
      </div>
      
      <div class="col-md-6 col-lg-4 mb-4">
  <div class="card h-100 border-secondary">
    <div class="card-header bg-secondary text-white">
      <i class="bi bi-file-text"></i> System Log
    </div>
    <div class="card-body">
      <h5 class="card-title">System Logs</h5>
      <p class="card-text">View system logs and activity records for troubleshooting.</p>
      <button class="btn btn-outline-secondary" type="button" id="viewLogsBtn">
        <i class="bi bi-journal-text"></i> View Logs
      </button>
    </div>
  </div>
</div>
    </div>
  </div>
  
  <!-- Location Codes Management Tab -->
<div class="tab-pane fade" id="location-codes" role="tabpanel" aria-labelledby="location-codes-tab">
  <div class="card mb-4">
    <div class="card-header d-flex justify-content-between align-items-center">
      <span><i class="bi bi-geo-alt"></i> Manage Location Codes</span>
      <button class="btn btn-success btn-sm" data-bs-toggle="modal" data-bs-target="#addLocationModal">
        <i class="bi bi-plus-circle"></i> Add New Code
      </button>
    </div>
    <div class="card-body">
      <div class="table-responsive">
        <table class="table table-striped table-hover" id="locationCodesTable">
          <thead>
            <tr>
              <th>Department Name</th>
              <th>Location Code</th>
              <th>Actions</th>
            </tr>
          </thead>
          <tbody>
            <?php
            // Fetch location codes from database
            try {
                $stmt = $pdo->query("SELECT * FROM location_codes ORDER BY department_name ASC");
                $locationCodes = $stmt->fetchAll(PDO::FETCH_ASSOC);
                
                if (count($locationCodes) > 0) {
                    foreach ($locationCodes as $code) {
                        echo '<tr data-id="' . $code['id'] . '">';
                        echo '<td>' . htmlspecialchars($code['department_name']) . '</td>';
                        echo '<td>' . htmlspecialchars($code['code']) . '</td>';
                        echo '<td>
                                <button class="btn btn-sm btn-primary edit-code" data-id="' . $code['id'] . '"
                                data-department="' . htmlspecialchars($code['department_name']) . '"
                                data-code="' . htmlspecialchars($code['code']) . '">
                                  <i class="bi bi-pencil"></i>
                                </button>
                                <button class="btn btn-sm btn-danger delete-code" data-id="' . $code['id'] . '"
                                data-department="' . htmlspecialchars($code['department_name']) . '">
                                  <i class="bi bi-trash"></i>
                                </button>
                              </td>';
                        echo '</tr>';
                    }
                } else {
                    echo '<tr><td colspan="3" class="text-center">No location codes found.</td></tr>';
                }
            } catch (PDOException $e) {
                echo '<tr><td colspan="3" class="text-center text-danger">Error fetching location codes: ' . htmlspecialchars($e->getMessage()) . '</td></tr>';
            }
            ?>
          </tbody>
        </table>
      </div>
    </div>
  </div>
</div>
  
  <!-- System Settings Tab -->
  <div class="tab-pane fade" id="system" role="tabpanel" aria-labelledby="system-tab">
      
      <div class="card mb-4">
      <div class="card-header">
        <i class="bi bi-robot"></i> Auto-Assignment Settings
      </div>
      <div class="card-body">
        <div id="autoAssignMessage"></div>
        
        
        <form id="autoAssignForm" class="row g-3">
          <div class="col-md-6">
            <div class="form-check form-switch">
              <input class="form-check-input" type="checkbox" id="autoAssignEnabled">
              <label class="form-check-label" for="autoAssignEnabled">
                <strong>Enable Auto-Assignment</strong>
              </label>
            </div>
            <small class="text-muted">When enabled, new tickets will be automatically assigned</small>
          </div>
          
          <div class="col-md-6" id="targetUserContainer" style="display: none;">
            <label for="targetUser" class="form-label">Assign New Tickets To:</label>
            <select class="form-select" id="targetUser">
              <option value="">Select User...</option>
              <?php
              // Get users 
              try {
                  $stmt = $pdo->prepare("
                    SELECT username, CONCAT(first_name, ' ', last_name) as full_name 
                    FROM users 
                    
                    ORDER BY first_name
                  ");
                  $stmt->execute();
                  while($row = $stmt->fetch(PDO::FETCH_ASSOC)){
                      echo '<option value="' . htmlspecialchars($row['username']) . '">' 
                         . htmlspecialchars($row['full_name']) . ' (' . htmlspecialchars($row['username']) . ')</option>';
                  }
              } catch (PDOException $e) {
                  echo '<option value="">Error loading users</option>';
              }
              ?>
            </select>
            <small class="text-muted">Select any user to receive auto-assigned tickets</small>
          </div>
          
          <div class="col-12">
            <button type="submit" class="btn btn-primary">
              <i class="bi bi-save"></i> Save Auto-Assignment Settings
            </button>
          </div>
        </form>
        
        <!-- Current Status Display -->
        <div class="mt-4 pt-3 border-top">
          <h6>Current Status</h6>
          <div id="currentStatus" class="alert alert-info">
            <i class="bi bi-info-circle"></i> Loading current settings...
          </div>
        </div>
      </div>
    </div>
     
    
    <div class="card mb-4">
      <div class="card-header">
        <i class="bi bi-activity"></i> System Status
      </div>
      <div class="card-body">
        <div class="row">
          <div class="col-md-6">
            <h5>Server Information</h5>
            <table class="table table-bordered">
              <tr>
                <th>PHP Version</th>
                <td><?= phpversion() ?></td>
              </tr>
              <tr>
                <th>Server Software</th>
                <td><?= $_SERVER['SERVER_SOFTWARE'] ?? 'Unknown' ?></td>
              </tr>
              <tr>
                <th>Database Type</th>
                <td>MySQL</td>
              </tr>
              <tr>
                <th>System Time</th>
                <td><?= date('Y-m-d H:i:s') ?> (<?= date_default_timezone_get() ?>)</td>
              </tr>
            </table>
          </div>
          <div class="col-md-6">
  <h5>File System</h5>
  <?php
  $uploadDir = __DIR__ . '/uploads/';
  
  // Count uploads
  $uploadCount = 0;
  $uploadSize = 0;
  if (is_dir($uploadDir)) {
      $files = new RecursiveIteratorIterator(
          new RecursiveDirectoryIterator($uploadDir, RecursiveDirectoryIterator::SKIP_DOTS)
      );
      foreach ($files as $file) {
          $uploadCount++;
          $uploadSize += $file->getSize();
      }
  }
  
  // Calculate size of the entire printshop application directory
  $appDirSize = 0;
  try {
      $appDir = new RecursiveIteratorIterator(
          new RecursiveDirectoryIterator(__DIR__, RecursiveDirectoryIterator::SKIP_DOTS)
      );
      foreach ($appDir as $file) {
          $appDirSize += $file->getSize();
      }
  } catch (Exception $e) {
      // Silently fail if we can't access some directories
  }
  
  // Convert to MB for display
  $uploadSizeMB = round($uploadSize / 1024 / 1024, 2);
  $appDirSizeMB = round($appDirSize / 1024 / 1024, 2);
  
  // Total allocation from cPanel (manually set)
  $totalAllocation = 16600; // 16.6 GB in MB
  $usedFromCPanel = 60.98; // MB as reported by cPanel
  $usedPercent = round(($usedFromCPanel / $totalAllocation) * 100, 2);
  ?>
  <table class="table table-bordered">
    <tr>
      <th>cPanel Storage</th>
      <td>
        <?= number_format($usedFromCPanel, 2) ?> MB used of 
        <?= number_format($totalAllocation, 2) ?> MB total
        (<?= $usedPercent ?>%)
        <div class="progress mt-1">
          <div class="progress-bar" role="progressbar" style="width: <?= $usedPercent ?>%;" 
               aria-valuenow="<?= $usedPercent ?>" aria-valuemin="0" aria-valuemax="100">
            <?= $usedPercent ?>%
          </div>
        </div>
      </td>
    </tr>
    <tr>
      <th>Upload Files</th>
      <td><?= $uploadCount ?> files (<?= number_format($uploadSizeMB, 2) ?> MB)</td>
    </tr>
    <tr>
      <th>Application Size</th>
      <td><?= number_format($appDirSizeMB, 2) ?> MB</td>
    </tr>
  </table>
</div>
        </div>
      </div>
    </div> 
      
      <!-- CACHE SETTINGS CARD -->
<div class="card mt-4 mb-4">
  <div class="card-header">Cache Management</div>
  <div class="card-body">
    <?= $cacheMessage ?? '' ?>
    <p>If users are seeing outdated content, clearing the cache will force all browsers to reload the latest CSS and JavaScript files.</p>
    <form method="POST" class="d-inline">
      <button type="submit" name="clear_cache" class="btn btn-warning" 
              onclick="return confirm('This will force all users to reload the page resources. Continue?')">
        <i class="bi bi-arrow-clockwise"></i> Clear Cache
      </button>
    </form>
    <small class="text-muted d-block mt-2">
      Current cache version: <?= htmlspecialchars($settings['cache_version'] ?? 1) ?>
    </small>
  </div>
</div>
    <div class="card mb-4">
      <div class="card-header d-flex justify-content-between align-items-center">
        <span><i class="bi bi-tools"></i> Debug Settings</span>
        <button class="btn btn-sm btn-outline-secondary" type="button" data-bs-toggle="collapse" data-bs-target="#debugSettingsCollapse">
          <i class="bi bi-arrows-expand"></i>
        </button>
      </div>
      <div class="card-body collapse show" id="debugSettingsCollapse">
        <?= $debugMessage ?? '' ?>
        <form method="POST" class="row g-3">
          <div class="col-12">
            <div class="form-check">
              <input class="form-check-input" type="checkbox" name="debug_mode" id="debug_mode" value="1"
                     <?= !empty($settings['debug_mode']) ? 'checked' : '' ?>>
              <label class="form-check-label" for="debug_mode">
                Show debug log on this page
              </label>
            </div>
          </div>
          <div class="col-12">
            <button type="submit" name="update_debug" class="btn btn-primary">
              Update Debug Settings
            </button>
          </div>
        </form>
      </div>
    </div>
    
    <?php if (!empty($settings['debug_mode'])): ?>
      <div class="card mb-4">
        <div class="card-header d-flex justify-content-between align-items-center">
          <span><i class="bi bi-journal-text"></i> Debug Log (last 50 lines)</span>
          <button class="btn btn-sm btn-outline-secondary" type="button" data-bs-toggle="collapse" data-bs-target="#debugLogCollapse">
            <i class="bi bi-arrows-expand"></i>
          </button>
        </div>
        <div class="card-body collapse show" id="debugLogCollapse">
          <?php
            $logFile = __DIR__ . '/error_log';
            if (is_readable($logFile)) {
                $lines = file($logFile, FILE_IGNORE_NEW_LINES|FILE_SKIP_EMPTY_LINES);
                $tail  = array_slice($lines, -50);
                echo '<pre style="max-height:400px;overflow:auto;background:#f8f9fa;padding:1rem;">'
                    . htmlspecialchars(implode("\n", $tail))
                    . '</pre>';
            } else {
                echo '<p class="text-muted">No log file or errors found.</p>';
            }
          ?>
        </div>
      </div>
    <?php endif; ?>
    <!-- MAINTENANCE MODE SETTINGS CARD -->
<div class="card mb-4">
  <div class="card-header">Maintenance Mode</div>
  <div class="card-body">
    <?= $maintenanceMessage ?? '' ?>
    <p>Enable maintenance mode to show a maintenance page to all visitors while you perform updates. Super Admins can still access the site.</p>
    <form method="POST" class="row g-3">
      <div class="col-12">
        <div class="form-check form-switch">
          <input class="form-check-input" type="checkbox" name="maintenance_mode" id="maintenance_mode" value="1"
                 <?= !empty($settings['maintenance_mode']) ? 'checked' : '' ?>>
          <label class="form-check-label" for="maintenance_mode">
            <strong>Enable Maintenance Mode</strong>
          </label>
        </div>
        <small class="text-muted">When enabled, visitors will see a maintenance page. You will still have full access as Super Admin.</small>
      </div>
      
      <div class="col-md-6">
        <label for="maintenance_time" class="form-label">Estimated Completion Time</label>
        <select name="maintenance_time" id="maintenance_time" class="form-select">
          <?php 
          $timeOptions = [
              '5-10 minutes' => '5-10 minutes',
              '15-30 minutes' => '15-30 minutes', 
              '30-60 minutes' => '30-60 minutes',
              '1-2 hours' => '1-2 hours',
              '2-4 hours' => '2-4 hours',
              'Several hours' => 'Several hours',
              'End of business day' => 'End of business day',
              'Tomorrow morning' => 'Tomorrow morning'
          ];
          $selectedTime = $settings['maintenance_time'] ?? '15-30 minutes';
          foreach ($timeOptions as $value => $label): ?>
            <option value="<?= htmlspecialchars($value) ?>" 
                    <?= $selectedTime === $value ? 'selected' : '' ?>>
              <?= htmlspecialchars($label) ?>
            </option>
          <?php endforeach; ?>
        </select>
      </div>
      
      <div class="col-12">
        <button type="submit" name="update_maintenance" class="btn btn-warning">
          <i class="bi bi-tools"></i> Update Maintenance Settings
        </button>
      </div>
    </form>
    
    <?php if (!empty($settings['maintenance_mode'])): ?>
      <div class="alert alert-warning mt-3">
        <i class="bi bi-exclamation-triangle"></i> 
        <strong>Maintenance mode is currently ACTIVE.</strong> 
        Visitors are seeing the maintenance page with estimated completion: 
        <strong><?= htmlspecialchars($settings['maintenance_time'] ?? '15-30 minutes') ?></strong>
      </div>
    <?php endif; ?>
  </div>
</div>
    
  </div>


  <!-- Email Settings Tab -->
  <div class="tab-pane fade" id="email" role="tabpanel" aria-labelledby="email-tab">
    <div class="card">
      <div class="card-header d-flex justify-content-between align-items-center">
        <span><i class="bi bi-envelope"></i> Email Notification Settings</span>
        <button class="btn btn-sm btn-outline-secondary" type="button" data-bs-toggle="collapse" data-bs-target="#emailSettingsCollapse">
          <i class="bi bi-arrows-expand"></i>
        </button>
      </div>
      <div class="card-body collapse show" id="emailSettingsCollapse">
        <?= $message ?>
        <form method="POST" class="row g-3">
          <div class="col-md-6">
            <label for="email_from" class="form-label">From (Email Address)</label>
            <input type="email" name="email_from" id="email_from" class="form-control"
                   value="<?= htmlspecialchars($settings['email_from']??'') ?>" required>
          </div>
          
          <div class="col-md-6">
            <label for="email_to" class="form-label">To (Email Address)</label>
            <input type="text" name="email_to" id="email_to" class="form-control"
                   placeholder="e.g. alice@domain.com, bob@domain.com"
                   value="<?= htmlspecialchars($settings['email_to'] ?? '') ?>" required>
          </div>
          
          <div class="col-md-6">
            <label for="subject_line" class="form-label">Subject Line</label>
            <input type="text" name="subject_line" id="subject_line" class="form-control"
                   value="<?= htmlspecialchars($settings['subject_line']??'') ?>" required>
          </div>
          
          <div class="col-md-6">
            <div class="d-flex flex-column h-100">
              <div class="form-check mb-2">
                <input class="form-check-input" type="checkbox" name="notify_on_processing" 
                       id="notify_on_processing" value="1" 
                       <?= !empty($settings['notify_on_processing']) ? 'checked' : '' ?>>
                <label class="form-check-label" for="notify_on_processing">
                  Send email when ticket is marked <strong>Processing</strong>
                </label>
              </div>
              
              <div class="form-check">
                <input class="form-check-input" type="checkbox" name="notify_on_complete" 
                       id="notify_on_complete" value="1"
                       <?= !empty($settings['notify_on_complete']) ? 'checked' : '' ?>>
                <label class="form-check-label" for="notify_on_complete">
                  Send email when ticket is marked <strong>Complete</strong>
                </label>
                
              </div>
              <div class="form-check">
  <input class="form-check-input" type="checkbox" name="notify_on_assignment" 
         id="notify_on_assignment" value="1"
         <?= !empty($settings['notify_on_assignment']) ? 'checked' : '' ?>>
  <label class="form-check-label" for="notify_on_assignment">
    Send email when ticket is <strong>Assigned</strong>
  </label>
</div>
<div class="form-check">
  <input class="form-check-input" type="checkbox" name="notify_on_hold" 
         id="notify_on_hold" value="1"
         <?= !empty($settings['notify_on_hold']) ? 'checked' : '' ?>>
  <label class="form-check-label" for="notify_on_hold">
    Send email when ticket is placed on <strong>Hold</strong>
  </label>
</div>
            </div>
            
          </div>
         
          <div class="col-md-12">
            <label for="body_template" class="form-label">Email Body Template</label>
            <textarea name="body_template" id="body_template" class="form-control" rows="5" required><?= htmlspecialchars($settings['body_template']??'') ?></textarea>
            <small class="text-muted">Use placeholders like <code>%s</code> for Ticket #, Job Title, Created At.</small>
            
          </div>
          
          <h5 class="mt-4 mb-3">SMTP Configuration</h5>
          
          <div class="col-md-6">
            <label for="smtp_host" class="form-label">SMTP Host</label>
            <input type="text" name="smtp_host" id="smtp_host" class="form-control"
                   value="<?= htmlspecialchars($settings['smtp_host'] ?? '') ?>">
          </div>
          
          <div class="col-md-2">
            <label for="smtp_port" class="form-label">Port</label>
            <input type="number" name="smtp_port" id="smtp_port" class="form-control"
                   value="<?= htmlspecialchars($settings['smtp_port'] ?? '') ?>">
          </div>
          
          <div class="col-md-4">
            <label for="smtp_secure" class="form-label">Encryption</label>
            <select name="smtp_secure" id="smtp_secure" class="form-select">
              <option value=""     <?= empty($settings['smtp_secure']) ? 'selected':'' ?>>None</option>
              <option value="ssl"  <?= ($settings['smtp_secure']=='ssl') ? 'selected':'' ?>>SSL</option>
              <option value="tls"  <?= ($settings['smtp_secure']=='tls') ? 'selected':'' ?>>TLS</option>
            </select>
          </div>
          
          <div class="col-md-6">
            <label for="smtp_username" class="form-label">SMTP Username</label>
            <input type="text" name="smtp_username" id="smtp_username" class="form-control"
                   value="<?= htmlspecialchars($settings['smtp_username'] ?? '') ?>">
          </div>
          
          <div class="col-md-6">
            <label for="smtp_password" class="form-label">SMTP Password</label>
            <input type="password" name="smtp_password" id="smtp_password" class="form-control">
            <small class="text-muted">Leave blank to keep current password.</small>
          </div>

          <div class="col-12">
            <button type="submit" name="update_email" class="btn btn-success">
              <i class="bi bi-save"></i> Update Email Settings
            </button>
            
            <?php if (!empty($settings['smtp_host'])): ?>
              <button type="submit" name="test_smtp" class="btn btn-outline-primary ms-2">
                <i class="bi bi-envelope-check"></i> Send Test Email
              </button>
            <?php endif; ?>
          </div>
        </form>
        
        <?= $testMessage ?? '' ?>
      </div>
    </div>
  </div>
  
  <!-- User Management Tab -->
  <div class="tab-pane fade" id="user" role="tabpanel" aria-labelledby="user-tab">
    <div class="row">
      <div class="col-md-6 mb-4">
        <div class="card h-100">
          <div class="card-header">
            <i class="bi bi-person-plus"></i> Create New User
          </div>
          <div class="card-body">
            <p>Set up accounts for printshop administrators and staff members.</p>
            <a href="create_user.php" class="btn btn-success">
              <i class="bi bi-person-plus"></i> Create User
            </a>
          </div>
        </div>
      </div>
      
      <div class="col-md-6 mb-4">
        <div class="card h-100">
          <div class="card-header">
            <i class="bi bi-people"></i> Manage Existing Users
          </div>
          <div class="card-body">
            <p>Edit permissions, reset passwords, and manage user accounts.</p>
            <a href="user_management.php" class="btn btn-primary">
              <i class="bi bi-people-fill"></i> View All Users
            </a>
          </div>
        </div>
      </div>
    </div>
    
    <?php

    // Optional: Show some user stats directly on this tab
    try {
        $userStats = $pdo->query("
            SELECT role, COUNT(*) as count 
            FROM users 
            GROUP BY role 
            ORDER BY count DESC
        ")->fetchAll(PDO::FETCH_ASSOC);
        
        $totalUsers = $pdo->query("SELECT COUNT(*) FROM users")->fetchColumn();
        
        if ($userStats && $totalUsers): 
    ?>
    <div class="card">
      <div class="card-header">
        <i class="bi bi-bar-chart"></i> User Statistics
      </div>
      <div class="card-body">
        <div class="row">
          <div class="col-md-6">
            <h5>User Counts by Role</h5>
            <table class="table table-striped">
              <thead>
                <tr>
                  <th>Role</th>
                  <th>Count</th>
                  <th>Percentage</th>
                </tr>
              </thead>
              <tbody>
                <?php foreach($userStats as $stat): ?>
                <tr>
                  <td><?= htmlspecialchars($stat['role']) ?></td>
                  <td><?= $stat['count'] ?></td>
                  <td><?= round(($stat['count'] / $totalUsers) * 100, 1) ?>%</td>
                </tr>
                <?php endforeach; ?>
              </tbody>
              <tfoot>
                <tr class="fw-bold">
                  <td>Total Users</td>
                  <td><?= $totalUsers ?></td>
                  <td>100%</td>
                </tr>
              </tfoot>
            </table>
          </div>
          <div class="col-md-6">
            <canvas id="userRolesChart" height="250"></canvas>
          </div>
        </div>
      </div>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script>
    document.addEventListener('DOMContentLoaded', function() {
      const ctx = document.getElementById('userRolesChart').getContext('2d');
      new Chart(ctx, {
        type: 'pie',
        data: {
          labels: [<?= implode(', ', array_map(function($s) { 
                    return '"'.htmlspecialchars($s['role']).'"'; 
                  }, $userStats)) ?>],
          datasets: [{
            data: [<?= implode(', ', array_map(function($s) { 
                    return $s['count']; 
                  }, $userStats)) ?>],
            backgroundColor: [
              'rgba(54, 162, 235, 0.7)',
              'rgba(255, 99, 132, 0.7)',
              'rgba(255, 206, 86, 0.7)',
              'rgba(75, 192, 192, 0.7)',
              'rgba(153, 102, 255, 0.7)'
            ]
          }]
        },
        options: {
          responsive: true,
          plugins: {
            legend: {
              position: 'bottom',
            },
            title: {
              display: true,
              text: 'User Distribution by Role'
            }
          }
        }
      });
    });
    </script>
    <?php 
        endif;
    } catch (PDOException $e) {
        // Silently fail - stats are optional
    }
    ?>
  </div>
  
  <!-- Data Management Tab -->
  <div class="tab-pane fade" id="data" role="tabpanel" aria-labelledby="data-tab">
    <div class="row">
      <div class="col-md-6 mb-4">
        <div class="card h-100">
          <div class="card-header bg-warning text-dark">
            <i class="bi bi-file-earmark-spreadsheet"></i> Data Export
          </div>
          <div class="card-body">
            <h5 class="card-title">Export System Data</h5>
            <p class="card-text">Generate CSV reports filtered by date range and status for external analysis.</p>
            <a href="export.php" class="btn btn-warning">
              <i class="bi bi-download"></i> Export Data
            </a>
          </div>
        </div>
      </div>
      
      <div class="col-md-6 mb-4">
        <div class="card h-100">
          <div class="card-header bg-danger text-white">
            <i class="bi bi-trash"></i> Delete Tickets
          </div>
          <div class="card-body">
            <h5 class="card-title">Remove Ticket Data</h5>
            <p class="card-text">Search and delete specific tickets from the system.</p>
            <a href="delete_tickets.php" class="btn btn-danger">
              <i class="bi bi-trash"></i> Manage Tickets
            </a>
          </div>
        </div>
      </div>
      
      <div class="col-md-6 mb-4">
        <div class="card h-100">
          <div class="card-header bg-info text-white">
            <i class="bi bi-bar-chart"></i> Analytics Dashboard
          </div>
          <div class="card-body">
            <h5 class="card-title">System Analytics</h5>
            <p class="card-text">View detailed reports and statistics on system usage.</p>
            <a href="analytics.php" class="btn btn-info">
              <i class="bi bi-graph-up"></i> View Analytics
            </a>
          </div>
        </div>
      </div>
      
      <div class="col-md-6 mb-4">
        <div class="card h-100">
          <div class="card-header bg-secondary text-white">
            <i class="bi bi-archive"></i> Storage Management
          </div>
          <div class="card-body">
            <h5 class="card-title">Manage Upload Storage</h5>
            <p class="card-text">Clean up file uploads from previous semesters to free space.</p>
            <a href="cleanup_uploads.php" class="btn btn-secondary">
              <i class="bi bi-trash"></i> Manage Storage
            </a>
          </div>
        </div>
      </div>
    </div>
    
    <div class="card">
      <div class="card-header">
        <i class="bi bi-database"></i> Database Status
      </div>
      <div class="card-body">
        <?php
        // Quick statistics about the database
        try {
            $stats = [
                'Tickets' => $pdo->query("SELECT COUNT(*) FROM job_tickets")->fetchColumn(),
                'Users' => $pdo->query("SELECT COUNT(*) FROM users")->fetchColumn(),
                'Activities' => $pdo->query("SELECT COUNT(*) FROM activity_log")->fetchColumn()
            ];
            
            $ticketsByStatus = $pdo->query("
                SELECT ticket_status, COUNT(*) as count 
                FROM job_tickets 
                GROUP BY ticket_status
            ")->fetchAll(PDO::FETCH_KEY_PAIR);
        ?>
        <div class="row">
          <div class="col-md-6">
            <h5>Database Overview</h5>
            <table class="table table-bordered">
              <?php foreach($stats as $label => $count): ?>
              <tr>
                <th><?= $label ?></th>
                <td><?= number_format($count) ?></td>
              </tr>
              <?php endforeach; ?>
            </table>
          </div>
          <div class="col-md-6">
            <h5>Tickets by Status</h5>
            <table class="table table-bordered">
              <?php foreach($ticketsByStatus as $status => $count): ?>
              <tr>
                <th><?= htmlspecialchars($status) ?></th>
                <td><?= number_format($count) ?></td>
              </tr>
              <?php endforeach; ?>
            </table>
          </div>
        </div>
        <?php
        } catch (PDOException $e) {
            echo '<div class="alert alert-warning">Error retrieving database statistics.</div>';
        }
        ?>
      </div>
    </div>
  </div>
</div>

<!-- Revenue History Modal -->
<div class="modal fade" id="revenueHistoryModal" tabindex="-1" aria-labelledby="revenueHistoryModalLabel" aria-hidden="true">
  <div class="modal-dialog modal-lg">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title" id="revenueHistoryModalLabel">
          <i class="bi bi-graph-up"></i> Revenue History
        </h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body">
        <!-- Summary Stats -->
        <div class="row mb-4">
          <div class="col-md-4">
            <div class="card text-center">
              <div class="card-body">
                <h6 class="text-muted">Overall Total</h6>
                <h4 class="text-success">$<?= $overallTotalFormatted ?></h4>
              </div>
            </div>
          </div>
          <div class="col-md-4">
            <div class="card text-center">
              <div class="card-body">
                <h6 class="text-muted">This Month</h6>
                <h4 class="text-primary">$<?= $currentMonthFormatted ?></h4>
              </div>
            </div>
          </div>
          <div class="col-md-4">
            <div class="card text-center">
              <div class="card-body">
                <h6 class="text-muted">12 Month Total</h6>
                <h4 class="text-info">
                  $<?= number_format(array_sum(array_column($revenueHistory, 'monthly_total')), 2) ?>
                </h4>
              </div>
            </div>
          </div>
        </div>

        <!-- Tabs for Monthly and Quarterly -->
        <ul class="nav nav-tabs mb-3" id="revenueTabs" role="tablist">
          <li class="nav-item" role="presentation">
            <button class="nav-link active" id="monthly-tab" data-bs-toggle="tab" data-bs-target="#monthly" type="button" role="tab">
              Monthly Breakdown
            </button>
          </li>
          <li class="nav-item" role="presentation">
            <button class="nav-link" id="quarterly-tab" data-bs-toggle="tab" data-bs-target="#quarterly" type="button" role="tab">
              Quarterly Summary
            </button>
          </li>
        </ul>

        <div class="tab-content" id="revenueTabsContent">
          <!-- Monthly Tab -->
          <div class="tab-pane fade show active" id="monthly" role="tabpanel">
            <div class="table-responsive">
              <table class="table table-striped table-hover">
                <thead>
                  <tr>
                    <th>Month</th>
                    <th>Revenue</th>
                    <th>Tickets</th>
                    <th>Avg per Ticket</th>
                  </tr>
                </thead>
                <tbody>
                  <?php foreach ($revenueHistory as $month): ?>
                  <tr>
                    <td><?= htmlspecialchars($month['month_name']) ?></td>
                    <td class="text-success fw-bold">$<?= number_format($month['monthly_total'], 2) ?></td>
                    <td><?= number_format($month['ticket_count']) ?></td>
                    <td>$<?= $month['ticket_count'] > 0 ? number_format($month['monthly_total'] / $month['ticket_count'], 2) : '0.00' ?></td>
                  </tr>
                  <?php endforeach; ?>
                  <?php if (empty($revenueHistory)): ?>
                  <tr>
                    <td colspan="4" class="text-center text-muted">No revenue data available</td>
                  </tr>
                  <?php endif; ?>
                </tbody>
              </table>
            </div>
          </div>

          <!-- Quarterly Tab -->
          <div class="tab-pane fade" id="quarterly" role="tabpanel">
            <div class="table-responsive">
              <table class="table table-striped table-hover">
                <thead>
                  <tr>
                    <th>Quarter</th>
                    <th>Revenue</th>
                    <th>Tickets</th>
                    <th>Avg per Ticket</th>
                  </tr>
                </thead>
                <tbody>
                  <?php foreach ($quarterlyData as $quarter): ?>
                  <tr>
                    <td><?= htmlspecialchars($quarter['quarter']) ?></td>
                    <td class="text-success fw-bold">$<?= number_format($quarter['quarterly_total'], 2) ?></td>
                    <td><?= number_format($quarter['ticket_count']) ?></td>
                    <td>$<?= $quarter['ticket_count'] > 0 ? number_format($quarter['quarterly_total'] / $quarter['ticket_count'], 2) : '0.00' ?></td>
                  </tr>
                  <?php endforeach; ?>
                  <?php if (empty($quarterlyData)): ?>
                  <tr>
                    <td colspan="4" class="text-center text-muted">No quarterly data available</td>
                  </tr>
                  <?php endif; ?>
                </tbody>
              </table>
            </div>
          </div>
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
        
      </div>
    </div>
  </div>
</div>

<!-- Add Location Code Modal -->
<div class="modal fade" id="addLocationModal" tabindex="-1" aria-labelledby="addLocationModalLabel" aria-hidden="true">
  <div class="modal-dialog">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title" id="addLocationModalLabel">Add New Location Code</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <form id="addLocationForm" action="manage_locations.php" method="post">
        <div class="modal-body">
          <input type="hidden" name="action" value="add">
          <div class="mb-3">
            <label for="department_name" class="form-label">Department Name</label>
            <input type="text" class="form-control" id="department_name" name="department_name" required>
          </div>
          <div class="mb-3">
            <label for="location_code" class="form-label">Location Code</label>
            <input type="text" class="form-control" id="location_code" name="location_code" required>
            <small class="text-muted">Format: Department Name (00000)</small>
          </div>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
          <button type="submit" class="btn btn-success">Add Location Code</button>
        </div>
      </form>
    </div>
  </div>
</div>

<!-- Edit Location Code Modal -->
<div class="modal fade" id="editLocationModal" tabindex="-1" aria-labelledby="editLocationModalLabel" aria-hidden="true">
  <div class="modal-dialog">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title" id="editLocationModalLabel">Edit Location Code</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <form id="editLocationForm" action="manage_locations.php" method="post">
        <div class="modal-body">
          <input type="hidden" name="action" value="edit">
          <input type="hidden" id="edit_id" name="id">
          <div class="mb-3">
            <label for="edit_department_name" class="form-label">Department Name</label>
            <input type="text" class="form-control" id="edit_department_name" name="department_name" required>
          </div>
          <div class="mb-3">
            <label for="edit_location_code" class="form-label">Location Code</label>
            <input type="text" class="form-control" id="edit_location_code" name="location_code" required>
            <small class="text-muted">Format: Department Name (00000)</small>
          </div>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
          <button type="submit" class="btn btn-primary">Update Location Code</button>
        </div>
      </form>
    </div>
  </div>
</div>

<!-- Delete Location Code Modal -->
<div class="modal fade" id="deleteLocationModal" tabindex="-1" aria-labelledby="deleteLocationModalLabel" aria-hidden="true">
  <div class="modal-dialog">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title" id="deleteLocationModalLabel">Delete Location Code</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <form id="deleteLocationForm" action="manage_locations.php" method="post">
        <div class="modal-body">
          <input type="hidden" name="action" value="delete">
          <input type="hidden" id="delete_id" name="id">
          <p>Are you sure you want to delete the location code for:</p>
          <p class="fw-bold" id="delete_department_name"></p>
          <div class="alert alert-warning">
            <i class="bi bi-exclamation-triangle"></i> This action cannot be undone. Any tickets using this code will need to be reassigned.
          </div>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
          <button type="submit" class="btn btn-danger">Delete Location Code</button>
        </div>
      </form>
    </div>
  </div>
</div>

<script>
// JavaScript to handle both tab and dropdown navigation with persistence
document.addEventListener('DOMContentLoaded', function() {
  const dropdownButton = document.getElementById('settingsDropdown');
  const dropdownItems = document.querySelectorAll('.dropdown-item[data-target]');
  const tabButtons = document.querySelectorAll('#settingsTabs button[data-bs-target]');
  const tabPanes = document.querySelectorAll('#settingsTabsContent .tab-pane');
  
  // Get the active section from localStorage
  const activeSection = localStorage.getItem('settingsActiveSection') || '#dashboard';
  
  // Function to show a specific section (for dropdown)
  function showSection(target, title, icon) {
    // Hide all tab panes
    tabPanes.forEach(pane => {
      pane.classList.remove('show', 'active');
    });
    
    // Show selected tab pane
    const targetPane = document.querySelector(target);
    if (targetPane) {
      targetPane.classList.add('show', 'active');
    }
    
    // Update dropdown button text and icon (mobile only)
    if (dropdownButton) {
      dropdownButton.innerHTML = `<i class="${icon}"></i> ${title}`;
    }
    
    // Store selection
    localStorage.setItem('settingsActiveSection', target);
  }
  
  // Handle desktop tabs (existing Bootstrap tab functionality)
  if (tabButtons.length > 0) {
    // Set initial active tab on desktop
    const initialTab = document.querySelector(`#settingsTabs button[data-bs-target="${activeSection}"]`);
    if (initialTab) {
      const tab = new bootstrap.Tab(initialTab);
      tab.show();
    }
    
    // Store the active tab when a tab is clicked (desktop)
    tabButtons.forEach(button => {
      button.addEventListener('shown.bs.tab', function (event) {
        localStorage.setItem('settingsActiveSection', event.target.getAttribute('data-bs-target'));
        
        // Also update mobile dropdown button if it exists
        if (dropdownButton) {
          const target = event.target.getAttribute('data-bs-target');
          const dropdownItem = document.querySelector(`[data-target="${target}"]`);
          if (dropdownItem) {
            const title = dropdownItem.textContent.trim();
            const icon = dropdownItem.getAttribute('data-icon');
            dropdownButton.innerHTML = `<i class="${icon}"></i> ${title}`;
          }
        }
      });
    });
  }
  
  // Handle mobile dropdown
  if (dropdownItems.length > 0) {
    // Set initial section for mobile
    const initialItem = document.querySelector(`[data-target="${activeSection}"]`);
    if (initialItem) {
      const title = initialItem.textContent.trim();
      const icon = initialItem.getAttribute('data-icon');
      showSection(activeSection, title, icon);
    }
    
    // Handle dropdown item clicks (mobile)
    dropdownItems.forEach(item => {
      item.addEventListener('click', function(e) {
        e.preventDefault();
        const target = this.getAttribute('data-target');
        const title = this.textContent.trim();
        const icon = this.getAttribute('data-icon');
        showSection(target, title, icon);
      });
    });
  }
});

// Handle the View Logs button click
document.getElementById('viewLogsBtn').addEventListener('click', function() {
  const systemTarget = '#system';
  
  // Check if we're on desktop (tabs visible) or mobile (dropdown visible)
  const tabsVisible = window.getComputedStyle(document.getElementById('settingsTabs')).display !== 'none';
  
  if (tabsVisible) {
    // Desktop: Use tab functionality
    const systemTab = document.querySelector('#system-tab');
    if (systemTab) {
      const tab = new bootstrap.Tab(systemTab);
      tab.show();
    }
  } else {
    // Mobile: Use dropdown functionality
    const systemDropdownItem = document.querySelector('[data-target="#system"]');
    if (systemDropdownItem) {
      const title = systemDropdownItem.textContent.trim();
      const icon = systemDropdownItem.getAttribute('data-icon');
      
      // Show system section
      const tabPanes = document.querySelectorAll('#settingsTabsContent .tab-pane');
      tabPanes.forEach(pane => {
        pane.classList.remove('show', 'active');
      });
      
      const systemPane = document.querySelector('#system');
      if (systemPane) {
        systemPane.classList.add('show', 'active');
      }
      
      // Update dropdown button
      const dropdownButton = document.getElementById('settingsDropdown');
      if (dropdownButton) {
        dropdownButton.innerHTML = `<i class="${icon}"></i> ${title}`;
      }
    }
  }
  
  // Store selection and handle debug log
  localStorage.setItem('settingsActiveSection', systemTarget);
  
  const debugLogSection = document.getElementById('debugLogCollapse');
  if (debugLogSection) {
    const bsCollapse = new bootstrap.Collapse(debugLogSection, { toggle: false });
    bsCollapse.show();
    
    setTimeout(function() {
      debugLogSection.scrollIntoView({ behavior: 'smooth' });
    }, 350);
  }
});

// Location code management
document.addEventListener('DOMContentLoaded', function() {
  // Edit location code button
  document.querySelectorAll('.edit-code').forEach(button => {
    button.addEventListener('click', function() {
      const id = this.getAttribute('data-id');
      const department = this.getAttribute('data-department');
      const code = this.getAttribute('data-code');
      
      document.getElementById('edit_id').value = id;
      document.getElementById('edit_department_name').value = department;
      document.getElementById('edit_location_code').value = code;
      
      const editModal = new bootstrap.Modal(document.getElementById('editLocationModal'));
      editModal.show();
    });
  });
  
  // Delete location code button
  document.querySelectorAll('.delete-code').forEach(button => {
    button.addEventListener('click', function() {
      const id = this.getAttribute('data-id');
      const department = this.getAttribute('data-department');
      
      document.getElementById('delete_id').value = id;
      document.getElementById('delete_department_name').textContent = department;
      
      const deleteModal = new bootstrap.Modal(document.getElementById('deleteLocationModal'));
      deleteModal.show();
    });
  });
  
  // Form submissions with AJAX
  ['addLocationForm', 'editLocationForm', 'deleteLocationForm'].forEach(formId => {
    const form = document.getElementById(formId);
    if (form) {
      form.addEventListener('submit', function(e) {
        e.preventDefault();
        
        const formData = new FormData(this);
        
        fetch('manage_locations.php', {
          method: 'POST',
          body: formData
        })
        .then(response => response.json())
        .then(data => {
          if (data.success) {
            // Hide the modal
            const modalId = formId === 'addLocationForm' ? 'addLocationModal' :
                            formId === 'editLocationForm' ? 'editLocationModal' : 'deleteLocationModal';
            const modal = bootstrap.Modal.getInstance(document.getElementById(modalId));
            modal.hide();
            
            // Show success message and reload
            Swal.fire({
              title: 'Success!',
              text: data.message,
              icon: 'success',
              confirmButtonText: 'OK'
            }).then(() => {
              location.reload();
            });
          } else {
            // Show error message
            Swal.fire({
              title: 'Error',
              text: data.message,
              icon: 'error',
              confirmButtonText: 'OK'
            });
          }
        })
        .catch(error => {
          console.error('Error:', error);
          Swal.fire({
            title: 'Error',
            text: 'An unexpected error occurred. Please try again.',
            icon: 'error',
            confirmButtonText: 'OK'
          });
        });
      });
    }
  });
});

// Auto-Assignment functionality
const autoAssignEnabled = document.getElementById('autoAssignEnabled');
const targetUserContainer = document.getElementById('targetUserContainer');
const targetUser = document.getElementById('targetUser');
const autoAssignForm = document.getElementById('autoAssignForm');
const autoAssignMessageDiv = document.getElementById('autoAssignMessage');
const currentStatusDiv = document.getElementById('currentStatus');

if (autoAssignEnabled && autoAssignForm) {
  // Load current settings on page load
  loadAutoAssignSettings();

  // Toggle target user dropdown
  autoAssignEnabled.addEventListener('change', function() {
    if (this.checked) {
      targetUserContainer.style.display = 'block';
    } else {
      targetUserContainer.style.display = 'none';
      targetUser.value = '';
    }
    updateCurrentStatus();
  });

  // Update status when target user changes
  targetUser.addEventListener('change', updateCurrentStatus);

  // Save settings
autoAssignForm.addEventListener('submit', function(e) {
  e.preventDefault();
  
  // Only require target user when enabling
  if (autoAssignEnabled.checked && !targetUser.value) {
    showAutoAssignMessage('Please select a target user when auto-assignment is enabled.', 'danger');
    return;
  }

  const formData = {
    enabled: autoAssignEnabled.checked ? 1 : 0,
    target_username: autoAssignEnabled.checked ? targetUser.value : ''
  };

  // Show loading state
  const submitBtn = autoAssignForm.querySelector('button[type="submit"]');
  const originalText = submitBtn.innerHTML;
  submitBtn.innerHTML = '<i class="bi bi-hourglass-split"></i> Saving...';
  submitBtn.disabled = true;

  fetch('manage_auto_assign.php', {
    method: 'POST',
    headers: {
      'Content-Type': 'application/json'
    },
    body: JSON.stringify(formData)
  })
  .then(response => response.json())
  .then(data => {
    if (data.success) {
      showAutoAssignMessage('Auto-assignment settings saved successfully!', 'success');
      updateCurrentStatus();
    } else {
      showAutoAssignMessage('Error: ' + data.message, 'danger');
    }
  })
  .catch(error => {
    console.error('Save error:', error);
    showAutoAssignMessage('Error saving settings. Please try again.', 'danger');
  })
  .finally(() => {
    // Restore button state
    submitBtn.innerHTML = originalText;
    submitBtn.disabled = false;
  });
});
  function loadAutoAssignSettings() {
  fetch('manage_auto_assign.php?action=get')
    .then(response => response.json())
    .then(data => {
      if (data.success) {
        // Convert to boolean properly - database stores as int
        const isEnabled = parseInt(data.settings.enabled) === 1;
        const targetUsername = data.settings.target_username || '';
        
        // Set the checkbox state
        autoAssignEnabled.checked = isEnabled;
        
        // Set the target user dropdown
        targetUser.value = targetUsername;
        
        // Show/hide the target user container based on enabled state
        if (isEnabled) {
          targetUserContainer.style.display = 'block';
        } else {
          targetUserContainer.style.display = 'none';
        }
        
        // Update the current status display
        updateCurrentStatus();
        
       
      } else {
        currentStatusDiv.innerHTML = '<i class="bi bi-exclamation-triangle"></i> Error loading settings';
        currentStatusDiv.className = 'alert alert-warning';
      }
    })
    .catch(error => {
      console.error('Error loading auto-assign settings:', error);
      currentStatusDiv.innerHTML = '<i class="bi bi-exclamation-triangle"></i> Error loading settings';
      currentStatusDiv.className = 'alert alert-warning';
    });
}

  function updateCurrentStatus() {
    if (autoAssignEnabled.checked && targetUser.value) {
      const selectedOption = targetUser.options[targetUser.selectedIndex];
      currentStatusDiv.innerHTML = `<i class="bi bi-check-circle"></i> <strong>Auto-assignment is ENABLED</strong><br>New tickets will be automatically assigned to: <strong>${selectedOption.text}</strong>`;
      currentStatusDiv.className = 'alert alert-success';
    } else if (autoAssignEnabled.checked && !targetUser.value) {
      currentStatusDiv.innerHTML = '<i class="bi bi-exclamation-triangle"></i> <strong>Auto-assignment is ENABLED but no target user selected</strong><br>Please select a target user and save settings.';
      currentStatusDiv.className = 'alert alert-warning';
    } else {
      currentStatusDiv.innerHTML = '<i class="bi bi-x-circle"></i> <strong>Auto-assignment is DISABLED</strong><br>New tickets will not be automatically assigned.';
      currentStatusDiv.className = 'alert alert-info';
    }
  }

  function showAutoAssignMessage(message, type) {
    autoAssignMessageDiv.innerHTML = `<div class="alert alert-${type} alert-dismissible fade show" role="alert">
      ${message}
      <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>`;
  }
}
</script>

<?php require_once 'footer.php'; ?>