<?php
// check_status.php

// 1) Only allow GET with the right flag
if ($_SERVER['REQUEST_METHOD'] !== 'GET' || !isset($_GET['check_status_submit'])) {
    http_response_code(403);
    exit('Forbidden');
}


// 2) Start session so we can record who did it (or default to guest)
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// 3) Bring in your single PDO (with PT timezone)
require_once __DIR__ . '/assets/database.php';

// Function to get real IP address
function getRealIpAddr() {
    if (!empty($_SERVER['HTTP_CLIENT_IP'])) {   // Check IP from shared internet
        $ip = $_SERVER['HTTP_CLIENT_IP'];
    } elseif (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {   // Check IP passed from proxy
        $ip = $_SERVER['HTTP_X_FORWARDED_FOR'];
    } else {
        $ip = $_SERVER['REMOTE_ADDR'];
    }
    return $ip;
}



// Function to get location from IP
function getLocationFromIP($ip) {
    // Skip for local/private IPs
    if ($ip === '127.0.0.1' || $ip === '::1' || strpos($ip, '192.168.') === 0 || strpos($ip, '10.') === 0) {
        return 'Local/Private Network';
    }
    
    try {
        // Try HTTPS first, then HTTP as fallback
        $urls = [
            "https://ip-api.com/json/{$ip}?fields=status,country,regionName,city",
            "http://ip-api.com/json/{$ip}?fields=status,country,regionName,city"
        ];
        
        foreach ($urls as $url) {
            // Try cURL first (more reliable)
            if (function_exists('curl_init')) {
                $ch = curl_init();
                curl_setopt($ch, CURLOPT_URL, $url);
                curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
                curl_setopt($ch, CURLOPT_TIMEOUT, 5);
                curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/5.0 (compatible; PrintshopBot/1.0)');
                curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
                curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false); // For HTTPS issues
                
                $response = curl_exec($ch);
                $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
                $error = curl_error($ch);
                curl_close($ch);
                
                if ($response !== false && $httpCode === 200) {
                    $data = json_decode($response, true);
                    if ($data && $data['status'] === 'success') {
                        return "{$data['city']}, {$data['regionName']}, {$data['country']}";
                    }
                } else if ($error) {
                    error_log("cURL error for $url: $error");
                }
            } else {
                // Fallback to file_get_contents
                $context = stream_context_create([
                    'http' => [
                        'timeout' => 5,
                        'user_agent' => 'Mozilla/5.0 (compatible; PrintshopBot/1.0)',
                        'ignore_errors' => true
                    ]
                ]);
                
                $response = @file_get_contents($url, false, $context);
                if ($response !== false) {
                    $data = json_decode($response, true);
                    if ($data && $data['status'] === 'success') {
                        return "{$data['city']}, {$data['regionName']}, {$data['country']}";
                    }
                }
            }
        }
        
        return 'Location service unavailable';
    } catch (Exception $e) {
        error_log("Location lookup exception: " . $e->getMessage());
        return 'Location lookup error: ' . $e->getMessage();
    }
}

// 4) Bot detection - check honeypot field
if (!empty($_GET['website'])) {
    // Bot detected - honeypot field was filled
    $username = $_SESSION['username'] ?? 'guest';
$userAgent = $_SERVER['HTTP_USER_AGENT'] ?? 'Unknown';
$ipAddress = getRealIpAddr();

// Block known bot IP ranges (cloud providers commonly used by bots)
$blockedRanges = [
    '135.232.', // Microsoft Azure
    '13.67.',   // Microsoft Azure
    '52.224.',  // Microsoft Azure
    '40.117.',  // Microsoft Azure
    '54.236.',  // Amazon AWS
    '3.238.',   // Amazon AWS
    '18.206.',  // Amazon AWS
];

foreach ($blockedRanges as $range) {
    if (strpos($ipAddress, $range) === 0) {
        echo '<div class="alert alert-danger">Access denied.</div>';
        exit;
    }
}
$location = getLocationFromIP($ipAddress);
$details = "BOT DETECTED - Honeypot filled\nTicket: " . trim($_GET['ticket_number'] ?? '') . "\nIP: {$ipAddress}\nLocation: {$location}\nUser-Agent: {$userAgent}";
    
    $log = $pdo->prepare(
      "INSERT INTO activity_log (username, event, details)
       VALUES (:u, 'bot_blocked', :d)"
    );
    $log->execute([
      ':u' => $username,
      ':d' => $details
    ]);
    
    // Return generic error to bot
    echo '<div class="alert alert-danger">Invalid request.</div>';
    exit;
}

// Validate input
if (empty($_GET['ticket_number'])) {
    echo '<div class="alert alert-warning">Ticket Number is required.</div>';
    exit;
}

$ticketNumber = trim($_GET['ticket_number']);


// Validate ticket format: YYYYMMXXX (year + month + sequence)
if (!preg_match('/^20[0-9]{2}(0[1-9]|1[0-2])[0-9]{3}$/', $ticketNumber)) {
    // Generic error message that doesn't reveal the format requirements
    echo '<div class="alert alert-danger">Invalid ticket number format.</div>';
    exit;
}

// College-specific captcha check
if (empty($_GET['captcha_answer']) || (int)$_GET['captcha_answer'] !== 1963) {
    echo '<div class="alert alert-danger">Please enter the correct year Río Hondo College opened for instruction.</div>';
    exit;
}

try {

// 5) Log the "check status" activity with IP, location, and user agent
$username = $_SESSION['username'] ?? 'guest';
$userAgent = $_SERVER['HTTP_USER_AGENT'] ?? 'Unknown';
$ipAddress = getRealIpAddr();
$location = getLocationFromIP($ipAddress);
$details = "Ticket: {$ticketNumber}\nIP: {$ipAddress}\nLocation: {$location}\nUser-Agent: {$userAgent}";

$log = $pdo->prepare(
  "INSERT INTO activity_log (username, event, details)
   VALUES (:u, 'check_status', :d)"
);
$log->execute([
  ':u' => $username,
  ':d' => $details
]);

    // 6) Fetch the ticket
    $stmt = $pdo->prepare("
      SELECT * 
        FROM job_tickets 
       WHERE ticket_number = :tn 
       LIMIT 1
    ");
    $stmt->execute([':tn' => $ticketNumber]);
    $ticket = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($ticket) {
        ?>
        <div class="card">
          <div class="card-header">
            Ticket #<?= htmlspecialchars($ticket['ticket_number']) ?> Status
          </div>
          <div class="card-body">
            <p><strong>Status:</strong> <?= htmlspecialchars($ticket['ticket_status']) ?></p>
            <p><strong>Requested by:</strong> <?= htmlspecialchars($ticket['first_name']) ?> <?= htmlspecialchars($ticket['last_name']) ?></p>
            <p><strong>Title:</strong> <?= htmlspecialchars($ticket['job_title']) ?></p>
            <p><strong>Instructions/Notes:</strong><br>
  <?= htmlspecialchars($ticket['description']) ?>
</p>
            <p><strong>Request Date:</strong>
              <?= htmlspecialchars(date('m/d/Y', strtotime($ticket['created_at']))) ?>
            </p>
            <p><strong>Assigned To:</strong> <?= htmlspecialchars($ticket['assigned_to']) ?></p>
          </div>
        </div>
        <?php
    } else {
        echo '<div class="alert alert-danger">Ticket not found.</div>';
    }

} catch (PDOException $e) {
    echo '<div class="alert alert-danger">'
       . 'Database error: ' . htmlspecialchars($e->getMessage())
       . '</div>';
    exit;
}
