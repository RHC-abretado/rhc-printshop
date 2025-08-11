<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once 'assets/database.php';

// ─── Cloudflare Turnstile keys ────────────────────────────
$turnstileSiteKey = getenv('TURNSTILE_SITEKEY')
    ?: $_ENV['TURNSTILE_SITEKEY'] ?? $_SERVER['TURNSTILE_SITEKEY'] ?? '';
$turnstileSecretKey = getenv('TURNSTILE_SECRET')
    ?: $_ENV['TURNSTILE_SECRET'] ?? $_SERVER['TURNSTILE_SECRET'] ?? '';
// ──────────────────────────────────────────────────────────


// 2) Auto-login via remember-me (NEW SYSTEM)
if (empty($_SESSION['logged_in']) && !empty($_COOKIE['rememberme'])) {
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
            
            // Update last used timestamp
            $updateStmt = $pdo->prepare("
                UPDATE user_remember_tokens 
                SET last_used_at = NOW(), session_id = :session_id 
                WHERE token = :token
            ");
            $updateStmt->execute([
                ':token' => $token,
                ':session_id' => session_id()
            ]);

            // Log the auto-login
            $log = $pdo->prepare("
              INSERT INTO activity_log (username, event)
              VALUES (:u,'login')
            ");
            $log->execute([':u' => $user['username']]);

            header('Location: index.php');
            exit;
        } else {
            // Token expired or invalid, remove cookie
            setcookie('rememberme','', time() - 3600, '/', '', true, true);
        }
    } catch (PDOException $e) {
        // Optionally log errors here
    }
}

// 3) If already signed in, redirect
if (!empty($_SESSION['logged_in'])) {
    header('Location: index.php');
    exit;
}

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // … Turnstile verification stays here …

    if (empty($error)) {
        $username = $_POST['username'] ?? '';
        $password = $_POST['password'] ?? '';
        $remember = isset($_POST['remember']);

        try {
            // **Use the same $pdo**—no new PDO here!
            $stmt = $pdo->prepare("
              SELECT * FROM users
               WHERE username = :uname
               LIMIT 1
            ");
            $stmt->execute([':uname' => $username]);
            $user = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($user && password_verify($password, $user['password_hash'])) {
                // successful login
                $_SESSION['logged_in'] = true;
                $_SESSION['username']  = $user['username'];
                $_SESSION['role']      = $user['role'];

                // log the login
                $log = $pdo->prepare("
                  INSERT INTO activity_log (username, event)
                  VALUES (:u,'login')
                ");
                $log->execute([':u' => $user['username']]);

                // remember-me (skip for staffuser)
if ($remember && strcasecmp($user['username'],'staffuser')!==0) {
    $newToken = bin2hex(random_bytes(16));
    $expires = date('Y-m-d H:i:s', time() + 14*24*60*60); // 14 days
    $deviceInfo = $_SERVER['HTTP_USER_AGENT'] ?? 'Unknown Device';
    $ipAddress = $_SERVER['REMOTE_ADDR'] ?? 'Unknown IP';

    // Insert new token with session ID
    $u = $pdo->prepare("
      INSERT INTO user_remember_tokens (user_id, token, expires_at, device_info, ip_address, last_used_at, session_id)
      VALUES (:user_id, :token, :expires_at, :device_info, :ip_address, NOW(), :session_id)
    ");
    $u->execute([
        ':user_id' => $user['id'],
        ':token' => $newToken,
        ':expires_at' => $expires,
        ':device_info' => $deviceInfo,
        ':ip_address' => $ipAddress,
        ':session_id' => session_id()  // Add this line
    ]);

    setcookie('rememberme',$newToken,
              time()+14*24*60*60,'/','',true,true);
} else {
    // User opted out of remember-me: clear any existing cookie and tokens
    setcookie('rememberme','', time()-3600, '/', '', true, true);
    $del = $pdo->prepare("DELETE FROM user_remember_tokens WHERE user_id = :uid");
    $del->execute([':uid' => $user['id']]);
    $_SESSION['rememberme_cleared'] = true;
}

                header('Location: index.php');
                exit;
            } else {
                $error = 'Invalid username or password.';
            }
        } catch (PDOException $e) {
            $error = 'DB Error: ' . htmlspecialchars($e->getMessage());
        }
    }
}


// Render form
require_once 'header.php';
?>
<script src="https://challenges.cloudflare.com/turnstile/v0/api.js" async defer></script>

<div class="d-flex justify-content-center align-items-center">
  <div class="card" style="width: 400px;">
    <div class="card-header text-center">Login Form</div>
    <div class="card-body">
      <?php if ($error): ?>
        <div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
      <?php endif; ?>
      <form method="POST" novalidate>
        <div class="mb-3">
          <label for="username" class="form-label">Username</label>
          <input type="text" name="username" id="username" class="form-control" required autofocus>
        </div>
        <div class="mb-3">
          <label for="password" class="form-label">Password</label>
          <input type="password" name="password" id="password" class="form-control" required>
        </div>
        <div class="form-check mb-3">
          <input class="form-check-input" type="checkbox" name="remember" id="remember">
          <label class="form-check-label" for="remember">Remember Me</label>
        </div>
        <div class="cf-turnstile mb-3" data-sitekey="<?= htmlspecialchars($turnstileSiteKey) ?>"></div>
        <div class="d-grid gap-2">
          <button type="submit" class="btn btn-primary">Login</button>
          <a href="forgot_password.php" class="btn btn-link">Forgot Password?</a>
        </div>
      </form>
    </div>
  </div>
</div>

<?php require_once 'footer.php'; ?>

