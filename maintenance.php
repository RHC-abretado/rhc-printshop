<?php
// maintenance.php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Prevent direct access - only allow if redirected from auth.php
if (!isset($_SESSION['maintenance_redirect']) || $_SESSION['maintenance_redirect'] !== true) {
    header('HTTP/1.1 404 Not Found');
    exit('Page not found');
}

// Clear the redirect flag so it can't be reused
unset($_SESSION['maintenance_redirect']);

// Get maintenance time from database
try {
    require_once __DIR__ . '/assets/database.php';
    $stmt = $pdo->query("SELECT maintenance_time FROM email_settings WHERE id = 1 LIMIT 1");
    $settings = $stmt->fetch(PDO::FETCH_ASSOC);
    $maintenanceTime = $settings['maintenance_time'] ?? '15-30 minutes';
} catch (Exception $e) {
    $maintenanceTime = '15-30 minutes';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Site Maintenance - Río Hondo College Printshop</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.3/font/bootstrap-icons.css">
    <style>
        body {
            background: linear-gradient(135deg, #2a6491, #f9c900);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        .maintenance-card {
            background: rgba(255, 255, 255, 0.95);
            border-radius: 15px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.3);
            max-width: 500px;
            padding: 2rem;
            text-align: center;
        }
        .maintenance-icon {
            font-size: 4rem;
            color: #2a6491;
            margin-bottom: 1rem;
        }
        .spinner {
            border: 3px solid #f3f3f3;
            border-top: 3px solid #2a6491;
            border-radius: 50%;
            width: 30px;
            height: 30px;
            animation: spin 1s linear infinite;
            margin: 0 auto 1rem;
        }
        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }
    </style>
</head>
<body>
    <div class="maintenance-card">
        <i class="bi bi-tools maintenance-icon"></i>
        <h2 class="mb-3">We'll Be Right Back!</h2>
        <div class="spinner"></div>
        <p class="mb-3">The Río Hondo College Printshop system is currently undergoing scheduled maintenance to improve your experience.</p>
        <p class="mb-4">We apologize for any inconvenience and appreciate your patience.</p>
        <div class="alert alert-info">
            <i class="bi bi-clock"></i> Estimated completion time: <?= htmlspecialchars($maintenanceTime) ?>
        </div>
        <p class="mb-0">
            <small class="text-muted">
                For urgent printing needs, please contact us at 
                <a href="mailto:printing@riohondo.edu">printing@riohondo.edu</a>. 
                
            </small>
        </p>
    </div>
    
    <script>
        // Auto-refresh every 30 seconds
        setTimeout(function() {
            location.reload();
        }, 30000);
    </script>
</body>
</html>