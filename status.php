<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}


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

$ticket = null;
$error = '';
$ticketNumber = trim($_GET['ticket'] ?? '');
$token = trim($_GET['token'] ?? '');

// Require both ticket number and valid token
if (empty($ticketNumber) || empty($token) || !preg_match('/^[a-f0-9]{32}$/', $token)) {
    $username = $_SESSION['username'] ?? 'guest';
    $userAgent = $_SERVER['HTTP_USER_AGENT'] ?? 'Unknown';
    $ipAddress = getRealIpAddr();
    $location = getLocationFromIP($ipAddress);
    $details = "Missing parameters for status.php\nTicket: {$ticketNumber}\nToken: {$token}\nIP: {$ipAddress}\nLocation: {$location}\nUser-Agent: {$userAgent}";

    try {
        $log = $pdo->prepare(
            "INSERT INTO activity_log (username, event, details)
             VALUES (:u, 'status_token_missing', :d)"
        );
        $log->execute([
            ':u' => $username,
            ':d' => $details
        ]);
    } catch (PDOException $e) {
        // Silently fail if logging doesn't work
    }

    header('Location: index.php');
    exit;
}
require_once 'header.php';
// Process the ticket parameter
try {
    // Log the status check (use guest if not logged in) with IP, location, and user agent
    $username = $_SESSION['username'] ?? 'guest';
    $userAgent = $_SERVER['HTTP_USER_AGENT'] ?? 'Unknown';
    $ipAddress = getRealIpAddr();
    $location = getLocationFromIP($ipAddress);
    $details = "Ticket: {$ticketNumber}\nToken: {$token}\nIP: {$ipAddress}\nLocation: {$location}\nUser-Agent: {$userAgent}";

    $log = $pdo->prepare(
        "INSERT INTO activity_log (username, event, details)
         VALUES (:u, 'check_status', :d)"
    );
    $log->execute([
        ':u' => $username,
        ':d' => $details
    ]);

    // Fetch the ticket
    $stmt = $pdo->prepare("
        SELECT *
        FROM job_tickets
        WHERE ticket_number = :tn AND check_token = :token
        LIMIT 1
    ");
    $stmt->execute([':tn' => $ticketNumber, ':token' => $token]);
    $ticket = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$ticket) {
        $error = 'Ticket not found.';
        $details = "Ticket: {$ticketNumber}\nToken: {$token}\nIP: {$ipAddress}\nLocation: {$location}\nUser-Agent: {$userAgent}";
        $log = $pdo->prepare(
            "INSERT INTO activity_log (username, event, details) VALUES (:u, 'status_token_mismatch', :d)"
        );
        $log->execute([
            ':u' => $username,
            ':d' => $details
        ]);
    }

} catch (PDOException $e) {
    $error = 'Database error occurred.';
    error_log('Status check error: ' . $e->getMessage());
}
?>

<div class="container py-5">
    <h1>Ticket Status</h1>
    
    <?php if ($ticketNumber && $ticket): ?>
        <!-- Display Ticket Information -->
        <div class="card">
            <div class="card-header">
                <h4 class="mb-0">Ticket #<?= htmlspecialchars($ticket['ticket_number']) ?></h4>
            </div>
            <div class="card-body">
                <div class="row">
                    <div class="col-md-6">
                        <p><strong>Status:</strong> 
                            <span class="badge bg-<?php 
                                switch($ticket['ticket_status']) {
                                    case 'New': echo 'secondary'; break;
                                    case 'Processing': echo 'warning'; break;
                                    case 'Complete': echo 'success'; break;
                                    case 'Canceled': echo 'danger'; break;
                                    default: echo 'secondary';
                                }
                            ?>"><?= htmlspecialchars($ticket['ticket_status']) ?></span>
                        </p>
                        <p><strong>Job Title:</strong> <?= htmlspecialchars($ticket['job_title']) ?></p>
                        <p><strong>Request Date:</strong> <?= htmlspecialchars(toLA($ticket['created_at'], 'm/d/Y')) ?></p>
                        <p><strong>Due Date:</strong> <?= htmlspecialchars(date('m/d/Y', strtotime($ticket['date_wanted']))) ?></p>
                    </div>
                    <div class="col-md-6">
                        <p><strong>Requester:</strong> <?= htmlspecialchars($ticket['first_name'] . ' ' . $ticket['last_name']) ?></p>
                        <p><strong>Department:</strong> <?= htmlspecialchars($ticket['department_name']) ?></p>
                        <?php if (!empty($ticket['assigned_to'])): ?>
                            <p><strong>Assigned To:</strong> <?= htmlspecialchars($ticket['assigned_to']) ?></p>
                        <?php endif; ?>
                        <?php if ($ticket['ticket_status'] === 'Complete' && !empty($ticket['completed_at'])): ?>
                            <p><strong>Completed On:</strong> <?= htmlspecialchars(toLA($ticket['completed_at'], 'm/d/Y H:i:s')) ?></p>
                        <?php endif; ?>
                    </div>
                </div>
                
                <?php if (!empty($ticket['description'])): ?>
                    <hr>
                    <p><strong>Instructions/Notes:</strong></p>
                    <p><?= nl2br(htmlspecialchars($ticket['description'])) ?></p>
                <?php endif; ?>
                
                <div class="mt-4">
                    <p class="text-muted">
                        <small>
                            For questions about your order, please contact the printshop at 
                            <a href="mailto:printing@riohondo.edu">printing@riohondo.edu</a> or call (562) 908-3445.
                        </small>
                    </p>
                </div>
            </div>
        </div>
        
    <?php elseif ($ticketNumber && $error): ?>
        <!-- Error Message -->
        <div class="alert alert-danger">
            <h4 class="alert-heading">Ticket Not Found</h4>
            <p>The ticket number "<?= htmlspecialchars($ticketNumber) ?>" was not found in our system.</p>
            <hr>
            <p class="mb-0">Please check the ticket number and try again, or contact the printshop at <a href="mailto:printing@riohondo.edu">printing@riohondo.edu</a>.</p>
        </div>
        <?php endif; ?>
    
</div>

<?php require_once 'footer.php'; ?>