<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once __DIR__ . '/assets/database.php';
require_once 'header.php';

$message = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email'] ?? '');

    try {
        // 1) Look up the user
        $stmt = $pdo->prepare("SELECT id, username FROM users WHERE email = :email LIMIT 1");
        $stmt->execute([':email' => $email]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($user) {
            // 2) Generate reset token + expiration
            $token   = bin2hex(random_bytes(16));
            $expires = date("Y-m-d H:i:s", time() + 3600);

            // 3) Insert into password_resets
            $ins = $pdo->prepare("
              INSERT INTO password_resets (user_id, token, expires)
              VALUES (:uid, :token, :expires)
            ");
            $ins->execute([
                ':uid'     => $user['id'],
                ':token'   => $token,
                ':expires' => $expires,
            ]);

            // 4) Log the request in activity_log
            $log = $pdo->prepare("
              INSERT INTO activity_log (username, event, details)
              VALUES (:u, 'password_reset_request', :d)
            ");
            $details = "Requested reset for {$email}";
            $log->execute([
                ':u' => $user['username'],
                ':d' => $details,
            ]);

            // 5) Build reset URL dynamically
            $protocol = (!empty($_SERVER['HTTPS']) && strtolower($_SERVER['HTTPS']) !== 'off')
                      || $_SERVER['SERVER_PORT'] == 443
                      ? 'https://' : 'http://';
            $domain = $_SERVER['HTTP_HOST'];
            $folder = rtrim(dirname($_SERVER['SCRIPT_NAME']), '/\\');
            $resetUrl = "{$protocol}{$domain}{$folder}/reset_password.php?token=" . urlencode($token);

            // 6) Send email
            $stmtSettings = $pdo->query("SELECT email_from FROM email_settings LIMIT 1");
            $settings     = $stmtSettings->fetch(PDO::FETCH_ASSOC);
            $fromEmail    = $settings['email_from'] ?? 'no-reply@' . $domain;
            $subject      = "Your Password Reset Request";
            $body         = "Hello,\n\nWe received a password reset request for your account. "
                          . "Click or paste this link:\n\n{$resetUrl}\n\n"
                          . "If you didn't request this, please ignore this email.\n\nThanks.";
            $headers      = "From: \"RHC Printshop\" <{$fromEmail}>\r\n"
                          . "X-Mailer: PHP/" . phpversion();

            mail($email, $subject, $body, $headers);
        }

        // Always show success message (to avoid email enumeration)
        $message = '<div class="alert alert-success">'
                 . 'If that email is in our system, you will receive a reset link.'
                 . '</div>';

    } catch (PDOException $e) {
        $message = '<div class="alert alert-danger">'
                 . 'Error: ' . htmlspecialchars($e->getMessage())
                 . '</div>';
    }
}
?>

<div class="container py-5">
  <h1>Forgot Password</h1>
  <?= $message ?>
  <form method="POST" class="row g-3">
    <div class="col-md-6">
      <label for="email" class="form-label">Email Address</label>
      <input type="email" name="email" id="email"
             class="form-control" required>
    </div>
    <div class="col-12">
      <button type="submit" class="btn btn-primary">
        Send Reset Link
      </button>
    </div>
  </form>
</div>

<?php require_once 'footer.php'; ?>
