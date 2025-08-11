<?php

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!isset($pdo) || !($pdo instanceof PDO)) {
    $pdo = require_once __DIR__ . '/assets/database.php';
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
if (isset($_SESSION['rememberme_cleared'])) {
    // Skip token validation once if the cookie was recently cleared
    unset($_SESSION['rememberme_cleared']);
} elseif (isset($_SESSION['logged_in']) && isset($_COOKIE['rememberme'])) {
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
<?php
// Default meta tags with ability to override before including header
$metaDescription = $metaDescription ?? 'Río Hondo College Printshop dashboard for submitting and managing print requests.';
$metaKeywords = $metaKeywords ?? 'Río Hondo College, Printshop, printing services, print request, ticket management';
$metaAuthor = $metaAuthor ?? 'Río Hondo College';
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <meta name="robots" content="noindex, nofollow">
  <meta name="description" content="<?= htmlspecialchars($metaDescription) ?>">
  <meta name="keywords" content="<?= htmlspecialchars($metaKeywords) ?>">
  <meta name="author" content="<?= htmlspecialchars($metaAuthor) ?>">
<link rel="preconnect" href="https://cdn.jsdelivr.net" crossorigin>
  <link rel="preconnect" href="https://www.googletagmanager.com" crossorigin>
  <link rel="apple-touch-icon" sizes="180x180" href="./assets/apple-touch-icon.png">
    <link rel="icon" type="image/png" sizes="32x32" href="./assets/favicon-32x32.png">
    <link rel="icon" type="image/png" sizes="16x16" href="./assets/favicon-16x16.png">
     <link rel="manifest" href="./site.webmanifest">
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
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11" defer></script>
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

<script>
  document.addEventListener('DOMContentLoaded', function() {
    if ('serviceWorker' in navigator) {
      navigator.serviceWorker
        .register('assets/service-worker.js')
        .catch(function(err) {
          console.error('Service worker registration failed:', err);
        });
    }
  });
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
      <img class="logo" src="./assets/RioHondo-Seal-Light.webp" 
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
  <div class="container-fluid">
