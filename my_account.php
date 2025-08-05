<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// 1) Must be logged in
if (empty($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
    header('Location: login.php');
    exit;
}

// 2) StaffUser may not access My Account
if (($_SESSION['role'] ?? '') === 'StaffUser') {
    header('Location: index.php');
    exit;
}

require_once __DIR__ . '/assets/database.php';

try {
    // 3) Load current user
    $stmt = $pdo->prepare("SELECT * FROM users WHERE username = :username LIMIT 1");
    $stmt->execute([':username' => $_SESSION['username']]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$user) {
        throw new Exception("User not found.");
    }
} catch (Exception $e) {
    die("Error: " . htmlspecialchars($e->getMessage()));
}

$error   = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && !isset($_POST['delete_token']) && !isset($_POST['clear_all_tokens'])) {
    $newEmail        = trim($_POST['email'] ?? '');
    $newPassword     = trim($_POST['new_password'] ?? '');
    $confirmPassword = trim($_POST['confirm_password'] ?? '');

    // 4) Validation
    if ($newEmail === '') {
        $error = "Email cannot be empty.";
    } elseif (!filter_var($newEmail, FILTER_VALIDATE_EMAIL)) {
        $error = "Invalid email address.";
    } elseif ($newPassword !== '' && $newPassword !== $confirmPassword) {
        $error = "Passwords do not match.";
    } else {
        try {
            // 5) Determine fields to update
            $updates = [];
            $params  = [':id' => $user['id']];
            if ($newEmail !== $user['email']) {
                $updates[]           = "email = :email";
                $params[':email']    = $newEmail;
            }
            if ($newPassword !== '') {
                $updates[]                = "password_hash = :password_hash";
                $params[':password_hash'] = password_hash($newPassword, PASSWORD_DEFAULT);
            }

            if (!empty($updates)) {
                $sql = "UPDATE users SET " . implode(', ', $updates) . " WHERE id = :id";
                $u   = $pdo->prepare($sql);
                $u->execute($params);

                // 6) Log the activity
                $details = [];
                if (isset($params[':email'])) {
                    $details[] = "Email → {$newEmail}";
                }
                if (isset($params[':password_hash'])) {
                    $details[] = "Password changed";
                }
                $log = $pdo->prepare("
                  INSERT INTO activity_log (username, event, details)
                  VALUES (:actor, 'update_account', :details)
                ");
                $log->execute([
                    ':actor'   => $_SESSION['username'],
                    ':details' => implode('; ', $details),
                ]);

                $success = "Account updated successfully.";

                // 7) Refresh $user with latest data
                $stmt = $pdo->prepare("SELECT * FROM users WHERE id = :id");
                $stmt->execute([':id' => $user['id']]);
                $user = $stmt->fetch(PDO::FETCH_ASSOC);
            } else {
                $error = "No changes to save.";
            }

        } catch (PDOException $e) {
            $error = "Database error: " . htmlspecialchars($e->getMessage());
        }
    }
}

// Handle individual token deletion
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_token'])) {
    $tokenId = (int)$_POST['delete_token'];
    try {
        // First, get the token info
        $stmt = $pdo->prepare("SELECT session_id, token FROM user_remember_tokens WHERE id = :id AND user_id = :user_id");
        $stmt->execute([':id' => $tokenId, ':user_id' => $user['id']]);
        $tokenData = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($tokenData) {
            // Check multiple ways if this is the current device
            $isCurrentSession = !empty($tokenData['session_id']) && ($tokenData['session_id'] === session_id());
            $isCurrentToken = (isset($_COOKIE['rememberme']) && $_COOKIE['rememberme'] === $tokenData['token']);
            
            // Delete the token
            $deleteStmt = $pdo->prepare("DELETE FROM user_remember_tokens WHERE id = :id AND user_id = :user_id");
            $deleteStmt->execute([':id' => $tokenId, ':user_id' => $user['id']]);
            
            if ($deleteStmt->rowCount() > 0) {
                // Log the activity
                $log = $pdo->prepare("
                  INSERT INTO activity_log (username, event, details)
                  VALUES (:actor, 'delete_device_token', :details)
                ");
                $log->execute([
                    ':actor'   => $_SESSION['username'],
                    ':details' => "Deleted device token ID {$tokenId}",
                ]);
                
                // If this is the current session/token, log out immediately
                if ($isCurrentSession || $isCurrentToken) {
                    // Clear session
                    $_SESSION = [];
                    session_destroy();
                    
                    // Clear remember me cookie
                    setcookie('rememberme','', time() - 3600, '/', '', true, true);
                    
                    // Redirect to login
                    header('Location: login.php?message=' . urlencode('You have been logged out.'));
                    exit;
                }
                
                $success = "Device logged out successfully. That device will need to log in again.";
            } else {
                $error = "Device not found or already logged out.";
            }
        } else {
            $error = "Device not found.";
        }
        
    } catch (PDOException $e) {
        $error = "Database error: " . htmlspecialchars($e->getMessage());
    }
}

// Handle clearing all tokens
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['clear_all_tokens'])) {
    try {
        // Check if current user has a token that will be deleted
        $checkStmt = $pdo->prepare("
            SELECT COUNT(*) FROM user_remember_tokens 
            WHERE user_id = :user_id 
            AND (session_id = :session_id OR token = :current_token)
        ");
        $currentToken = $_COOKIE['rememberme'] ?? '';
        $checkStmt->execute([
            ':user_id' => $user['id'],
            ':session_id' => session_id(),
            ':current_token' => $currentToken
        ]);
        $willLogoutSelf = $checkStmt->fetchColumn() > 0;
        
        // Delete all tokens
        $stmt = $pdo->prepare("DELETE FROM user_remember_tokens WHERE user_id = :user_id");
        $stmt->execute([':user_id' => $user['id']]);
        $deletedCount = $stmt->rowCount();
        
        // Log the activity
        $log = $pdo->prepare("
          INSERT INTO activity_log (username, event, details)
          VALUES (:actor, 'clear_all_tokens', :details)
        ");
        $log->execute([
            ':actor'   => $_SESSION['username'],
            ':details' => "Cleared all {$deletedCount} remember me tokens",
        ]);
        
        // If current user should be logged out, do it
        if ($willLogoutSelf) {
            // Clear session
            $_SESSION = [];
            session_destroy();
            
            // Clear remember me cookie
            setcookie('rememberme','', time() - 3600, '/', '', true, true);
            
            // Redirect to login
            header('Location: login.php?message=' . urlencode('All devices logged out.'));
            exit;
        }
        
        $success = "Successfully logged out all {$deletedCount} devices.";
        
    } catch (PDOException $e) {
        $error = "Database error: " . htmlspecialchars($e->getMessage());
    }
}

// Fetch active tokens for this user
try {
    $tokensStmt = $pdo->prepare("
        SELECT id, device_info, ip_address, created_at, last_used_at, expires_at, token
        FROM user_remember_tokens 
        WHERE user_id = :user_id 
        AND expires_at > NOW()
        ORDER BY last_used_at DESC
    ");
    $tokensStmt->execute([':user_id' => $user['id']]);
    $activeTokens = $tokensStmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $activeTokens = [];
}

// Helper function to parse device info
function parseDeviceInfo($userAgent) {
    if (stripos($userAgent, 'Mobile') !== false || stripos($userAgent, 'Android') !== false) {
        if (stripos($userAgent, 'iPhone') !== false) return 'iPhone';
        if (stripos($userAgent, 'iPad') !== false) return 'iPad';
        if (stripos($userAgent, 'Android') !== false) return 'Android Device';
        return 'Mobile Device';
    }
    if (stripos($userAgent, 'Windows') !== false) return 'Windows PC';
    if (stripos($userAgent, 'Macintosh') !== false) return 'Mac';
    if (stripos($userAgent, 'Linux') !== false) return 'Linux PC';
    return 'Unknown Device';
}

require_once 'header.php';
?>
<div class="container py-5">
  <h1>My Account</h1>

  <?php if ($error): ?>
    <div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
  <?php elseif ($success): ?>
    <div class="alert alert-success"><?= htmlspecialchars($success) ?></div>
  <?php endif; ?>

  <form method="POST" class="row g-3">
    <div class="col-md-6">
      <label class="form-label">Username</label>
      <input type="text" class="form-control"
             value="<?= htmlspecialchars($user['username']) ?>"
             readonly>
    </div>
    <div class="col-md-6">
      <label for="email" class="form-label">
        Email Address <span class="text-danger">*</span>
      </label>
      <input type="email" name="email" id="email" class="form-control"
             value="<?= htmlspecialchars($user['email']) ?>" required>
    </div>
    <div class="col-md-6">
      <label for="new_password" class="form-label">New Password</label>
      <input type="password" name="new_password" id="new_password"
             class="form-control">
      <small class="text-muted">
        Leave blank to keep your current password.
      </small>
    </div>
    <div class="col-md-6">
      <label for="confirm_password" class="form-label">
        Confirm New Password
      </label>
      <input type="password" name="confirm_password"
             id="confirm_password" class="form-control">
    </div>
    <div class="col-12">
      <button type="submit" class="btn btn-primary">
        Update Account
      </button>
    </div>
  </form>
 
  
<!-- Device Management Section -->
  <div class="card mt-4">
    <div class="card-header">
      <h5 class="mb-0">
        <i class="bi bi-devices"></i> Active Devices 
        <span class="badge bg-secondary"><?= count($activeTokens) ?></span>
      </h5>
    </div>
    <div class="card-body">
      <?php if (empty($activeTokens)): ?>
        <p class="text-muted">No devices with "Remember Me" enabled.</p>
      <?php else: ?>
        <p class="text-muted mb-3">
          These are devices where you've enabled "Remember Me". You can log out individual devices or all devices at once.
        </p>
        
        <div class="table-responsive">
          <table class="table table-sm">
            <thead>
              <tr>
                <th>Device</th>
                <th>IP Address</th>
                <th>Last Used</th>
                <th>Expires</th>
                <th>Current</th>
                <th>Action</th>
              </tr>
            </thead>
            <tbody>
              <?php foreach ($activeTokens as $token): 
                $isCurrent = isset($_COOKIE['rememberme']) && $_COOKIE['rememberme'] === $token['token'];
              ?>
              <tr>
                <td>
                  <i class="bi bi-<?= $isCurrent ? 'laptop' : 'device-hdd' ?>"></i>
                  <?= htmlspecialchars(parseDeviceInfo($token['device_info'])) ?>
                </td>
                <td><small class="text-muted"><?= htmlspecialchars($token['ip_address']) ?></small></td>
                <td><small><?= htmlspecialchars(toLA($token['last_used_at'], 'm/d/Y H:i')) ?></small></td>
                <td><small><?= htmlspecialchars(toLA($token['expires_at'], 'm/d/Y')) ?></small></td>
                <td>
                  <?php if ($isCurrent): ?>
                    <span class="badge bg-success">This Device</span>
                  <?php endif; ?>
                </td>
                <td>
                  <form method="POST" class="d-inline" onsubmit="return confirm('Log out this device?')">
                    <input type="hidden" name="delete_token" value="<?= $token['id'] ?>">
                    <button type="submit" class="btn btn-outline-danger btn-sm">
                      <i class="bi bi-box-arrow-right"></i> Log Out
                    </button>
                  </form>
                </td>
              </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
        
        <div class="mt-3">
          <form method="POST" class="d-inline" onsubmit="return confirm('Are you sure you want to log out ALL devices? You will need to log in again on all devices.');">
            <input type="hidden" name="clear_all_tokens" value="1">
            <button type="submit" class="btn btn-warning">
              <i class="bi bi-shield-exclamation"></i> Log Out All Devices
            </button>
          </form>
        </div>
      <?php endif; ?>
    </div>
  </div>
</div>
<?php require_once 'footer.php'; ?>
