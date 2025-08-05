<?php
// cleanup_activity_cron.php - Daily cleanup of activity log entries older than 30 days

// Security: Only allow execution from command line (cron jobs), not web requests
if (php_sapi_name() !== 'cli') {
    http_response_code(403);
    die('Access denied.');
}

// Set timezone
date_default_timezone_set('America/Los_Angeles');

// Include database connection
require_once __DIR__ . '/assets/database.php';

try {
    // Delete activity log entries older than 30 days
    $stmt = $pdo->prepare("DELETE FROM activity_log WHERE event_time < DATE_SUB(NOW(), INTERVAL 30 DAY)");
    $stmt->execute();
    $deletedRows = $stmt->rowCount();
    
    // Log the cleanup result
    $timestamp = date('Y-m-d H:i:s');
    $message = "[{$timestamp}] Activity log cleanup: Deleted {$deletedRows} records older than 30 days\n";
    
    // Write to a log file
    file_put_contents(__DIR__ . '/cleanup.log', $message, FILE_APPEND | LOCK_EX);
    
    // Output for cron
    echo $message;
    
} catch (PDOException $e) {
    $timestamp = date('Y-m-d H:i:s');
    $errorMessage = "[{$timestamp}] Activity log cleanup FAILED: " . $e->getMessage() . "\n";
    
    // Log the error
    file_put_contents(__DIR__ . '/cleanup.log', $errorMessage, FILE_APPEND | LOCK_EX);
    error_log($errorMessage);
    
    // Output error for cron
    echo $errorMessage;
    exit(1);
}
?>