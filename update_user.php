<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
if (!isset($_SESSION['logged_in'], $_SESSION['role']) || $_SESSION['role'] !== 'Super Admin') {
    header("Location: login.php");
    exit;
}

require_once 'assets/database.php';
$protectedUsers = require __DIR__ . '/config/protected_users.php';

if (empty($_GET['id'])) {
    die("No user specified.");
}
$userId = (int)$_GET['id'];
$message = '';

try {
    // use the shared $pdo from assets/database.php
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    // Fetch existing data
    $stmt = $pdo->prepare("\n      SELECT username, email, role, first_name, last_name, protected\n        FROM users\n       WHERE id = :id\n    ");
    $stmt->execute([':id' => $userId]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$user) {
        die("User not found.");
    }
    $isProtected = (int)$user['protected'] === 1 || in_array($user['username'], $protectedUsers, true);

    // Handle form submission
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        if ($isProtected) {
            $message = '<div class="alert alert-danger">Protected user cannot be modified.</div>';
        } else {
            $username  = trim($_POST['username'] ?? '');
            $email     = trim($_POST['email'] ?? '');
            $role      = trim($_POST['role'] ?? '');
            $firstName = trim($_POST['first_name'] ?? '');
            $lastName  = trim($_POST['last_name']  ?? '');
            $password  = $_POST['password'] ?? '';

            // Build SQL
            $params = [
                ':username'   => $username,
                ':email'      => $email,
                ':role'       => $role,
                ':first_name' => $firstName,
                ':last_name'  => $lastName,
                ':id'         => $userId
            ];
            if ($password !== '') {
                $params[':password_hash'] = password_hash($password, PASSWORD_DEFAULT);
                $sql = "UPDATE users
                           SET username        = :username,
                               email           = :email,
                               role            = :role,
                               first_name      = :first_name,
                               last_name       = :last_name,
                               password_hash   = :password_hash
                         WHERE id = :id";
            } else {
                $sql = "UPDATE users
                           SET username     = :username,
                               email        = :email,
                               role         = :role,
                               first_name   = :first_name,
                               last_name    = :last_name
                         WHERE id = :id";
            }

            // Perform update
            $pdo->prepare($sql)->execute($params);

            // ── NEW: log the user update ───────────────────────────────
            $actor   = $_SESSION['username'];
            $details = "Edited user ID {$userId} ({$username})";
            $log     = $pdo->prepare("\n          INSERT INTO activity_log (username, event, details)\n          VALUES (:actor, 'update_user', :details)\n        ");
            $log->execute([
              ':actor'   => $actor,
              ':details' => $details,
            ]);

            $message = '<div class="alert alert-success">User updated successfully.</div>';

            // Refresh user data after update
            $stmt = $pdo->prepare("\n              SELECT username, email, role, first_name, last_name, protected\n                FROM users\n               WHERE id = :id\n            ");
            $stmt->execute([':id' => $userId]);
            $user = $stmt->fetch(PDO::FETCH_ASSOC);
            $isProtected = (int)$user['protected'] === 1 || in_array($user['username'], $protectedUsers, true);
        }
    }

} catch (PDOException $e) {
    die("DB Error: " . htmlspecialchars($e->getMessage()));
}
?>
<?php require_once 'header.php'; ?>

<div class="container py-5">
  <h1>Edit User</h1>
  <?= $message ?>
  <form method="POST" class="row g-3">
    <div class="col-md-4">
      <label for="username" class="form-label">Username</label>
      <input type="text" name="username" id="username" class="form-control"
             value="<?= htmlspecialchars($user['username']) ?>" required>
    </div>
    <div class="col-md-4">
      <label for="email" class="form-label">Email</label>
      <input type="email" name="email" id="email" class="form-control"
             value="<?= htmlspecialchars($user['email']) ?>" required>
    </div>
    <div class="col-md-4">
      <label for="role" class="form-label">Role</label>
      <select name="role" id="role" class="form-select" required>
        <option value="Admin"      <?= $user['role']==='Admin'      ? 'selected':'' ?>>Admin</option>
        <option value="Manager"    <?= $user['role']==='Manager'    ? 'selected':'' ?>>Manager</option>
        <option value="Super Admin"<?= $user['role']==='Super Admin'? 'selected':'' ?>>Super Admin</option>
        <option value="StaffUser"  <?= $user['role']==='StaffUser'  ? 'selected':'' ?>>Staff User</option>
      </select>
    </div>
    <div class="col-md-4">
      <label for="first_name" class="form-label">First Name</label>
      <input type="text" name="first_name" id="first_name" class="form-control"
             value="<?= htmlspecialchars($user['first_name']) ?>" required>
    </div>
    <div class="col-md-4">
      <label for="last_name" class="form-label">Last Name</label>
      <input type="text" name="last_name" id="last_name" class="form-control"
             value="<?= htmlspecialchars($user['last_name']) ?>" required>
    </div>
    <div class="col-md-4">
      <label for="password" class="form-label">New Password</label>
      <input type="password" name="password" id="password" class="form-control">
      <small class="text-muted">Leave blank to keep current password.</small>
    </div>
    <div class="col-12">
      <button type="submit" class="btn btn-primary">Update User</button>
    </div>
  </form>
</div>

<?php require_once 'footer.php'; ?>
