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
$emailToken = '';
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
    } else {
        if (!empty($ticket['email'])) {
            $etStmt = $pdo->prepare("SELECT token FROM requestor_token WHERE email = :email");
            $etStmt->execute([':email' => $ticket['email']]);
            $emailToken = $etStmt->fetchColumn() ?: '';
        }
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
                  <table class="table table-sm table-borderless mb-0">
                      <tbody>
                          <tr>
                              <th scope="row">Status</th>
                              <td>
                                  <span class="badge bg-<?php
                                      switch($ticket['ticket_status']) {
                                          case 'New': echo 'secondary'; break;
                                          case 'Processing': echo 'warning'; break;
                                          case 'Complete': echo 'success'; break;
                                          case 'Canceled': echo 'danger'; break;
                                          default: echo 'secondary';
                                      }
                                  ?>"><?= htmlspecialchars($ticket['ticket_status']) ?></span>
                              </td>
                          </tr>
                          <tr>
                              <th scope="row">Job Title</th>
                              <td><?= htmlspecialchars($ticket['job_title']) ?></td>
                          </tr>
                          <tr>
                              <th scope="row">Request Date</th>
                              <td><?= htmlspecialchars(toLA($ticket['created_at'], 'm/d/Y')) ?></td>
                          </tr>
                          <tr>
                              <th scope="row">Due Date</th>
                              <td><?= htmlspecialchars(date('m/d/Y', strtotime($ticket['date_wanted']))) ?></td>
                          </tr>
                      </tbody>
                  </table>

                  <div class="accordion mt-4" id="ticketDetails">
                      <div class="accordion-item">
                          <h2 class="accordion-header" id="headingDetails">
                              <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#collapseDetails" aria-expanded="false" aria-controls="collapseDetails">
                                  View Ticket Details
                              </button>
                          </h2>
                          <div id="collapseDetails" class="accordion-collapse collapse" aria-labelledby="headingDetails" data-bs-parent="#ticketDetails">
                              <div class="accordion-body">
                                  <dl class="row mb-0">
                                      <dt class="col-sm-3">Requester</dt>
                                      <dd class="col-sm-9"><?= htmlspecialchars($ticket['first_name'] . ' ' . $ticket['last_name']) ?></dd>
                                      <dt class="col-sm-3">Department</dt>
                                      <dd class="col-sm-9"><?= htmlspecialchars($ticket['department_name']) ?></dd>
                                      <?php if (!empty($ticket['assigned_to'])): ?>
                                          <dt class="col-sm-3">Assigned To</dt>
                                          <dd class="col-sm-9"><?= htmlspecialchars($ticket['assigned_to']) ?></dd>
                                      <?php endif; ?>
                                      <?php if ($ticket['ticket_status'] === 'Complete' && !empty($ticket['completed_at'])): ?>
                                          <dt class="col-sm-3">Completed On</dt>
                                          <dd class="col-sm-9"><?= htmlspecialchars(toLA($ticket['completed_at'], 'm/d/Y H:i:s')) ?></dd>
                                      <?php endif; ?>
                                      <?php if (!empty($ticket['description'])): ?>
                                          <dt class="col-sm-3">Instructions/Notes</dt>
                                          <dd class="col-sm-9"><?= nl2br(htmlspecialchars($ticket['description'])) ?></dd>
                                      <?php endif; ?>
                                  </dl>
                                  <div class="mt-3">
                                      <p class="text-muted mb-2">
                                          <small>
                                              For questions about your order, please contact the printshop at
                                              <a href="mailto:printing@riohondo.edu">printing@riohondo.edu</a> or call (562) 908-3445.
                                          </small>
                                      </p>
                                      <?php if ($emailToken): ?>
                                          <p class="mb-0"><a href="my_requests.php?email=<?= urlencode($ticket['email']) ?>&token=<?= htmlspecialchars($emailToken) ?>">View all your requests</a></p>
                                      <?php endif; ?>
                                  </div>
                              </div>
                          </div>
                      </div>
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