<?php

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once 'assets/database.php';

try {
    // Create a new PDO connection
    $pdo = new PDO("mysql:host=$dbHost;dbname=$dbName;charset=utf8", $dbUser, $dbPass);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    die("Database error: " . $e->getMessage());
}

// Fetch cache version for cache busting
try {
    $stmt = $pdo->query("SELECT cache_version FROM email_settings WHERE id = 1 LIMIT 1");
    $cacheSettings = $stmt->fetch(PDO::FETCH_ASSOC);
    $settings['cache_version'] = $cacheSettings['cache_version'] ?? 1;
} catch (PDOException $e) {
    $settings['cache_version'] = 1; // fallback
}

// Auto-login using the "Remember Me" cookie if the user is not logged in
if (!isset($_SESSION['logged_in']) && isset($_COOKIE['rememberme'])) {
    $token = $_COOKIE['rememberme'];
    try {
        $stmt = $pdo->prepare("
            SELECT u.*, rt.expires_at 
            FROM users u 
            INNER JOIN user_remember_tokens rt ON u.id = rt.user_id 
            WHERE rt.token = :token 
            AND rt.expires_at > NOW() 
            LIMIT 1
        ");
        $stmt->execute([':token' => $token]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($user) {
            $_SESSION['logged_in'] = true;
            $_SESSION['username'] = $user['username'];
            $_SESSION['role'] = $user['role'];
            
            // Update last used timestamp AND link this session to the token
            $updateStmt = $pdo->prepare("
                UPDATE user_remember_tokens 
                SET last_used_at = NOW(), session_id = :session_id 
                WHERE token = :token
            ");
            $updateStmt->execute([
                ':token' => $token,
                ':session_id' => session_id()  // Add this line
            ]);
        } else {
            // Token expired or invalid, remove cookie
            setcookie('rememberme','', time() - 3600, '/', '', true, true);
        }
    } catch (PDOException $e) {
        // Optionally log errors here
    }
}

// Check if current remember_me token is still valid (for cross-device logout)
if (isset($_SESSION['logged_in']) && isset($_COOKIE['rememberme'])) {
    try {
        $tokenCheck = $pdo->prepare("
            SELECT COUNT(*) FROM user_remember_tokens 
            WHERE token = :token AND expires_at > NOW()
        ");
        $tokenCheck->execute([':token' => $_COOKIE['rememberme']]);
        $tokenExists = $tokenCheck->fetchColumn();
        
        // If token was deleted from another device, log out this session
        if (!$tokenExists) {
            $_SESSION = [];
            session_destroy();
            setcookie('rememberme','', time() - 3600, '/', '', true, true);
            
            // Redirect to login with message
            if (!headers_sent()) {
                header('Location: login.php?message=' . urlencode('You have been logged out from another device.'));
                exit;
            }
        }
    } catch (PDOException $e) {
        // Log error if needed
    }
}

// Generate a CSRF token if we don't already have one
if (empty($_SESSION['form_token'])) {
    $_SESSION['form_token'] = bin2hex(random_bytes(32));
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <meta name="robots" content="noindex, nofollow">
  <link rel="apple-touch-icon" sizes="180x180" href="./assets/apple-touch-icon.png">
    <link rel="icon" type="image/png" sizes="32x32" href="./assets/favicon-32x32.png">
    <link rel="icon" type="image/png" sizes="16x16" href="./assets/favicon-16x16.png">
    <link rel="manifest" href="./assets/site.webmanifest">
  <title><?php 
  $pageTitle = 'Printshop Dashboard';
  if (basename($_SERVER['PHP_SELF']) === 'newticket.php') $pageTitle = 'Submit Print Request';
  elseif (basename($_SERVER['PHP_SELF']) === 'viewtickets.php') $pageTitle = 'View Tickets';
  elseif (basename($_SERVER['PHP_SELF']) === 'settings.php') $pageTitle = 'Settings';
  elseif (basename($_SERVER['PHP_SELF']) === 'analytics.php') $pageTitle = 'Analytics';
  echo $pageTitle; 
?> - Río Hondo College</title>
  <!-- Bootstrap 5 CSS -->
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
  <!-- Bootstrap Icons -->
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.3/font/bootstrap-icons.css">

<!-- Your consolidated CSS with cache busting -->
  <?php
  $cacheVersion = $settings['cache_version'] ?? 1;
  ?>
  <link href="assets/css/style.css?v=<?= $cacheVersion ?>" rel="stylesheet">
  
  <!-- load SweetAlert2 -->
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script>
// override the native alert() globally
window.alert = function(msg) {
  Swal.fire({
    text: msg,
    icon: 'info',
    confirmButtonText: 'OK'
  });
};

// (optionally) override confirm() and prompt() too:
window.confirm = function(msg) {
  return Swal.fire({
    text: msg,
    icon: 'question',
    showCancelButton: true,
    confirmButtonText: 'Yes',
    cancelButtonText: 'No'
  }).then(result => result.isConfirmed);
};

window.prompt = function(msg, defaultVal = '') {
  return Swal.fire({
    title: msg,
    input: 'text',
    inputValue: defaultVal,
    showCancelButton: true
  }).then(result => result.isConfirmed ? result.value : null);
};
</script>

<script>
// Function to update ticket notification badges
window.updateTicketBadges = function() {
    // Only run if user is logged in and has access to View Tickets
    <?php if (!empty($_SESSION['logged_in']) && $_SESSION['role'] !== 'StaffUser'): ?>
    fetch('get_ticket_count.php')
        .then(response => response.json())
        .then(data => {
            const count = data.count || 0;
            const badges = document.querySelectorAll('#ticket-badge-desktop, #ticket-badge-mobile');
            
            badges.forEach(badge => {
                badge.textContent = count;
                if (count > 0) {
                    badge.classList.remove('zero');
                } else {
                    badge.classList.add('zero');
                }
            });
        })
        .catch(error => {
            console.error('Error fetching ticket count:', error);
        });
    <?php endif; ?>
};

// Update badges when page loads
document.addEventListener('DOMContentLoaded', updateTicketBadges);

// Update badges every 30 seconds
setInterval(updateTicketBadges, 30000);
</script>

<!-- Google tag (gtag.js) -->
<script async src="https://www.googletagmanager.com/gtag/js?id=G-VC0R4BEX9B"></script>
<script>
  window.dataLayer = window.dataLayer || [];
  function gtag(){dataLayer.push(arguments);}
  gtag('js', new Date());

  gtag('config', 'G-VC0R4BEX9B');
</script>

</head>

<body class="bg-light">
<a href="#main-content" class="visually-hidden-focusable">Skip to main content</a>
<!-- Offcanvas Sidebar for Small Screens -->
<div class="offcanvas offcanvas-start" tabindex="-1" id="offcanvasSidebar" aria-labelledby="offcanvasSidebarLabel">
  <div class="offcanvas-header bg-dark text-white">
    <h5 id="offcanvasSidebarLabel">Menu</h5>
    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="offcanvas" aria-label="Close"></button>
  </div>
  <div class="offcanvas-body p-0">
          <?php if (!empty($_SESSION['username'])): ?>
  <span class="d-inline-flex align-items-center" style="padding-left:13px;padding-top:5px;">
    <i class="bi bi-person-circle me-1"></i>
    <strong>Welcome, 
      <?php if ($_SESSION['role'] === 'StaffUser'): ?>
        <?= htmlspecialchars($_SESSION['username']) ?>
      <?php else: ?>
        <a href="my_account.php"><?= htmlspecialchars($_SESSION['username']) ?></a>
      <?php endif; ?>
    </strong>
  </span>
  <hr>
<?php endif; ?>

<ul class="nav nav-pills flex-column">
  <!-- Always show Dashboard -->
  <li class="nav-item">
    <a href="index.php" class="nav-link">
      <i class="bi bi-speedometer2" aria-hidden="true"></i> Homepage
    </a>
  </li>

  <!-- Always show Submit Ticket and Pricing (NO LOGIN REQUIRED) -->
  <li class="nav-item">
    <a href="newticket.php" class="nav-link">
      <i class="bi bi-plus-circle" aria-hidden="true"></i> Print Request
    </a>
  </li>
  
<li class="nav-item">
  <a href="#" class="nav-link" data-bs-toggle="modal" data-bs-target="#checkStatusModal">
    <i class="bi bi-search" aria-hidden="true"></i> Check Status
  </a>
</li>
  
  <li class="nav-item">
    <a href="pricing.php" class="nav-link">
      <i class="bi bi-coin" aria-hidden="true"></i> Pricing
    </a>
  </li>

  <?php if (!empty($_SESSION['logged_in'])): ?>
    <!-- LOGGED IN: show role‐specific links -->
    <?php if ($_SESSION['role'] !== 'StaffUser'): ?>
      <li class="nav-item">
        <a href="viewtickets.php" class="nav-link">
          <i class="bi bi-envelope" aria-hidden="true"></i> View Tickets
          <span id="ticket-badge-mobile" class="notification-badge zero">0</span>
        </a>
      </li>

      <?php if ($_SESSION['role'] === 'Super Admin'): ?>
        <li class="nav-item">
          <a href="settings.php" class="nav-link">
            <i class="bi bi-gear" aria-hidden="true"></i> Settings
          </a>
        </li>
      <?php endif; ?>
    <?php endif; ?>

    <!-- Always show Logout when logged in -->
    <li class="nav-item">
      <a href="logout.php" class="nav-link">
        <i class="bi bi-box-arrow-right" aria-hidden="true"></i> Logout
      </a>
    </li>
  <?php endif; ?>
</ul>

  </div>
</div>

<!-- Header for Small Screens: Hamburger Button -->
<nav class="navbar navbar-dark bg-dark d-md-none">
  <div class="container-fluid">
    <span class="navbar-brand mb-0 h1"><a href="index.php" style="color:white;text-decoration:none;">RHC Printshop</a></span>
    <button class="btn btn-outline-light" type="button" data-bs-toggle="offcanvas" data-bs-target="#offcanvasSidebar" aria-controls="offcanvasSidebar" aria-label="Menu">
  <i class="bi bi-list" aria-hidden="true"></i>
</button>
  </div>
</nav>

<div class="d-flex">
  <!-- Sidebar for Larger Screens -->
  <nav class="sidebar d-none d-md-block p-3">
    <a href="index.php" class="navbar-brand">
      <img class="logo" src="https://www.riohondo.edu/wp-content/uploads/2024/12/RioHondo-Seal-Light.png" 
           alt="Río Hondo College Printshop">
    </a>
    <hr>
    <?php if (!empty($_SESSION['username'])): ?>
  <span class="d-inline-flex align-items-center" style="padding-left:13px;padding-top:5px;">
    <i class="bi bi-person-circle me-1"></i>
    <strong>Welcome, 
      <?php if ($_SESSION['role'] === 'StaffUser'): ?>
        <?= htmlspecialchars($_SESSION['username']) ?>
      <?php else: ?>
        <a href="my_account.php"><?= htmlspecialchars($_SESSION['username']) ?></a>
      <?php endif; ?>
    </strong>
  </span>
  <hr>
<?php endif; ?>

<ul class="nav nav-pills flex-column">
  <!-- Always show Dashboard -->
  <li class="nav-item">
    <a href="index.php" class="nav-link">
      <i class="bi bi-speedometer2" aria-hidden="true"></i> Homepage
    </a>
  </li>

  <!-- Always show Submit Ticket and Pricing (NO LOGIN REQUIRED) -->
  <li class="nav-item">
    <a href="newticket.php" class="nav-link">
      <i class="bi bi-plus-circle" aria-hidden="true"></i> Print Request
    </a>
  </li>
  
  <li class="nav-item">
  <a href="#" class="nav-link" data-bs-toggle="modal" data-bs-target="#checkStatusModal">
    <i class="bi bi-search" aria-hidden="true"></i> Check Status
  </a>
</li>
  
  <li class="nav-item">
    <a href="pricing.php" class="nav-link">
      <i class="bi bi-coin" aria-hidden="true"></i> Pricing
    </a>
  </li>

  <?php if (!empty($_SESSION['logged_in'])): ?>
    <!-- LOGGED IN: show role‐specific links -->
    <?php if ($_SESSION['role'] !== 'StaffUser'): ?>
      <li class="nav-item">
        <a href="viewtickets.php" class="nav-link">
          <i class="bi bi-envelope" aria-hidden="true"></i> View Tickets
          <span id="ticket-badge-desktop" class="notification-badge zero">0</span>
        </a>
      </li>

      <?php if ($_SESSION['role'] === 'Super Admin'): ?>
        <li class="nav-item">
          <a href="settings.php" class="nav-link">
            <i class="bi bi-gear" aria-hidden="true"></i> Settings
          </a>
        </li>
      <?php endif; ?>
    <?php endif; ?>

    <!-- Always show Logout when logged in -->
    <li class="nav-item">
      <a href="logout.php" class="nav-link">
        <i class="bi bi-box-arrow-right" aria-hidden="true"></i> Logout
      </a>
    </li>
  <?php endif; ?>
</ul>

  </nav>

  <!-- Main Content Area -->
<main id="main-content" class="content flex-grow-1 p-3">
     <!-- Check Status Modal -->
<div class="modal fade" id="checkStatusModal" tabindex="-1" aria-labelledby="checkStatusModalLabel" aria-hidden="true">
  <div class="modal-dialog" role="document">
    <div class="modal-content">
      <div class="modal-header">
        <p class="modal-title" id="checkStatusModalLabel">Check Ticket Status</p>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close">
          <span class="visually-hidden">Close</span>
        </button>
      </div>
      <div class="modal-body">
  <form id="checkStatusForm" action="check_status.php" method="get" class="row g-3">
    <div class="col-12">
  <label for="ticket_number" class="form-label">Ticket Number:</label>
  <input type="text" class="form-control" name="ticket_number" id="ticket_number" required aria-describedby="ticketNumberHelp" pattern="[0-9]{9,15}" title="Please enter a valid ticket number">
  <div id="ticketNumberHelp" class="form-text">Enter your ticket number to check its status</div>
  <div class="invalid-feedback">Please enter a valid ticket number</div>
  <input type="hidden" name="check_status_submit" value="1">
</div>
    <div class="col-12">
      <label for="captcha_answer" class="form-label">What year did Río Hondo College officially open for instruction? (Anti-spam)</label>
      <input type="number" class="form-control" name="captcha_answer" id="captcha_answer" required placeholder="YYYY">
      <div class="form-text">Hint: The district was created in 1960</div>
    </div>
    <!-- Honeypot field (hidden from users, visible to bots) -->
    <div style="position: absolute; left: -9999px;">
      <input type="text" name="website" tabindex="-1" autocomplete="off">
    </div>
    <div class="col-12">
      <button type="submit" class="btn btn-primary w-100">Check Status</button>
    </div>
  </form>
        <div id="ticketInfoContainer" class="mt-3"></div>
      </div>
    </div>
  </div>
</div>
<script>
// Wait for the DOM to be fully loaded
document.addEventListener('DOMContentLoaded', function() {
// Restrict ticket number input to numbers only
const ticketInput = document.getElementById('ticket_number');
if (ticketInput) {
  // Set minimum length attribute
  ticketInput.setAttribute('minlength', '9');
  ticketInput.setAttribute('maxlength', '15');
  
  ticketInput.addEventListener('input', function(e) {
    // Remove any non-digit characters
    this.value = this.value.replace(/[^0-9]/g, '');
    
    // Visual feedback for length validation
    const submitBtn = document.querySelector('#checkStatusForm button[type="submit"]');
    if (this.value.length >= 9) {
      this.classList.remove('is-invalid');
      this.classList.add('is-valid');
      if (submitBtn) submitBtn.disabled = false;
    } else if (this.value.length > 0) {
    // Only show invalid state if user has typed something
    this.classList.remove('is-valid');
    this.classList.add('is-invalid');
    if (submitBtn) submitBtn.disabled = true;
  } else {
    // Field is empty, remove all validation classes
    this.classList.remove('is-valid', 'is-invalid');
    if (submitBtn) submitBtn.disabled = true;
  }
});
  
  ticketInput.addEventListener('keydown', function(e) {
    // Allow: backspace, delete, tab, escape, enter
    if ([8, 9, 27, 13, 46].indexOf(e.keyCode) !== -1 ||
        // Allow: Ctrl+A, Ctrl+C, Ctrl+V, Ctrl+X
        (e.keyCode === 65 && e.ctrlKey === true) ||
        (e.keyCode === 67 && e.ctrlKey === true) ||
        (e.keyCode === 86 && e.ctrlKey === true) ||
        (e.keyCode === 88 && e.ctrlKey === true)) {
      return;
    }
    // Ensure that it's a number and stop the keypress
    if ((e.shiftKey || (e.keyCode < 48 || e.keyCode > 57)) && (e.keyCode < 96 || e.keyCode > 105)) {
      e.preventDefault();
    }
  });
  
  ticketInput.addEventListener('paste', function(e) {
    // Handle paste events
    e.preventDefault();
    let paste = (e.clipboardData || window.clipboardData).getData('text');
    // Only allow numbers
    paste = paste.replace(/[^0-9]/g, '');
    this.value = paste;
    
    // Trigger input validation
    ticketInput.dispatchEvent(new Event('input'));
  });
  

}
  // Get the form element
  const checkStatusForm = document.getElementById('checkStatusForm');
  // Only proceed if the form exists on the page
  if (checkStatusForm) {
    checkStatusForm.addEventListener('submit', function(e) {
  e.preventDefault();
  
  // Use FormData to capture all form fields (including honeypot)
  const formData = new FormData(checkStatusForm);
  
  // Convert FormData to URLSearchParams for GET request
  const params = new URLSearchParams(formData);
  
  // Show loading indicator
  document.getElementById('ticketInfoContainer').innerHTML = 
    '<div class="d-flex justify-content-center"><div class="spinner-border text-primary" role="status">' +
    '<span class="visually-hidden">Loading...</span></div></div>';
  
  fetch('check_status.php?' + params.toString())
    .then(response => {
      if (!response.ok) {
        if (response.status === 403) {
          throw new Error('Access forbidden');
        }
        throw new Error('Network response was not ok. Status: ' + response.status);
      }
      return response.text();
    })
    .then(html => {
      document.getElementById('ticketInfoContainer').innerHTML = html;
    })
    .catch(error => {
      console.error('Error:', error);
      document.getElementById('ticketInfoContainer')
        .innerHTML = '<div class="alert alert-danger">Error loading ticket information.</div>';
    });
});
  }
});
</script>
  <div class="container-fluid">
    

