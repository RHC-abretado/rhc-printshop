<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once 'header.php';

// Retrieve the token from GET parameters
$token = $_GET['token'] ?? '';
if (empty($token)) {
    die("Invalid or missing token.");
}

try {

    // Fetch the reset request from the password_resets table
    $stmt = $pdo->prepare("SELECT * FROM password_resets WHERE token = :token LIMIT 1");
    $stmt->execute([':token' => $token]);
    $resetInfo = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$resetInfo) {
        die("Invalid token.");
    }

    // Check if the token has expired
    if (strtotime($resetInfo['expires']) < time()) {
        die("This password reset token has expired.");
    }
} catch (PDOException $e) {
    die("Database error: " . htmlspecialchars($e->getMessage()));
}

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $password = trim($_POST['password'] ?? '');
    $confirm  = trim($_POST['confirm'] ?? '');

    if (empty($password) || empty($confirm)) {
        $error = "Please fill in both password fields.";
    } elseif ($password !== $confirm) {
        $error = "Passwords do not match.";
    } else {
        // Everything looks good – hash the new password
        $passwordHash = password_hash($password, PASSWORD_DEFAULT);

        try {
            // Update the user's password (using user_id from the reset record)
            $stmt = $pdo->prepare("UPDATE users SET password_hash = :password_hash WHERE id = :user_id");
            $stmt->execute([
                ':password_hash' => $passwordHash,
                ':user_id'       => $resetInfo['user_id']
            ]);

            // Invalidate the token by removing it from the table
            $stmt = $pdo->prepare("DELETE FROM password_resets WHERE token = :token");
            $stmt->execute([':token' => $token]);

            $success = "Your password has been reset successfully.";
        } catch (PDOException $e) {
            $error = "Database error: " . htmlspecialchars($e->getMessage());
        }
    }
}

?>

<div class="container py-5">
  <h1>Reset Password</h1>
  <?php if (!empty($error)): ?>
      <div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
  <?php elseif (!empty($success)): ?>
      <div class="alert alert-success"><?= htmlspecialchars($success) ?></div>
      <a href="login.php" class="btn btn-primary">Login</a>
  <?php else: ?>
      <form method="POST" class="row g-3">
          <div class="col-md-6">
              <label for="password" class="form-label">New Password</label>
              <input type="password" name="password" id="password" class="form-control" required>
          </div>
          <div class="col-md-6">
              <label for="confirm" class="form-label">Confirm Password</label>
              <input type="password" name="confirm" id="confirm" class="form-control" required>
          </div>
          <div class="col-12">
              <button type="submit" class="btn btn-primary">Reset Password</button>
          </div>
      </form>
  <?php endif; ?>
</div>

<?php require_once 'footer.php'; ?>
