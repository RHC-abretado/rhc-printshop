<?php
// Temporary auth.php that doesn't enforce authentication
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Set timezone
date_default_timezone_set('America/Los_Angeles');

// Check maintenance mode (only if not already in maintenance page)
if (basename($_SERVER['PHP_SELF']) !== 'maintenance.php') {
    try {
        // Include database connection
        require_once __DIR__ . '/assets/database.php';
        
        // Check if maintenance mode is enabled
        $stmt = $pdo->query("SELECT maintenance_mode FROM email_settings WHERE id = 1 LIMIT 1");
        $settings = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($settings && $settings['maintenance_mode'] == 1) {
            // Allow Super Admins to bypass maintenance mode
            $isAdmin = isset($_SESSION['logged_in']) 
                      && $_SESSION['logged_in'] === true 
                      && $_SESSION['role'] === 'Super Admin';
            
            if (!$isAdmin) {
                // Set flag to allow access to maintenance page
                $_SESSION['maintenance_redirect'] = true;
                
                // Redirect to maintenance page
                header('Location: maintenance.php');
                exit;
            }
        }
    } catch (Exception $e) {
        // If there's a database error, continue normally to avoid breaking the site
        error_log('Maintenance mode check failed: ' . $e->getMessage());
    }
}

// Continue without authentication checks
?>