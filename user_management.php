<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

//  only Super Admins
if (
    !isset($_SESSION['logged_in'], $_SESSION['role'])
    || $_SESSION['logged_in'] !== true
    || $_SESSION['role'] !== 'Super Admin'
) {
    header("Location: login.php");
    exit;
}

require_once 'assets/database.php';
$protectedUsers = require __DIR__ . '/config/protected_users.php';

$message = '';
// ———————————————
// 1) CREATE USER
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['username'])) {
    $first = trim($_POST['first_name'] ?? '');
    $last  = trim($_POST['last_name']  ?? '');
    $user  = trim($_POST['username']);
    $pass  = $_POST['password'];
    $email = trim($_POST['email']);
    $role  = trim($_POST['role'] ?? 'Admin');

    if ($first===''||$last===''||$user===''||$pass===''||$email==='') {
        $message = '<div class="alert alert-danger">All fields are required.</div>';
    } else {
        try {
            // check duplicate username
            $chk = $pdo->prepare("SELECT COUNT(*) FROM users WHERE username=:u");
            $chk->execute([':u'=>$user]);
            if ($chk->fetchColumn()>0) {
                $message = '<div class="alert alert-danger">Username already exists.</div>';
            } else {
                $hash = password_hash($pass, PASSWORD_DEFAULT);
                $ins  = "INSERT INTO users
                         (first_name,last_name,username,password_hash,email,role,created_at)
                         VALUES
                         (:f,:l,:u,:h,:e,:r,NOW())";
                $i    = $pdo->prepare($ins);
                $i->execute([
                  ':f'=>$first,':l'=>$last,
                  ':u'=>$user, ':h'=>$hash,
                  ':e'=>$email,':r'=>$role
                ]);
                $message = '<div class="alert alert-success">User created.</div>';
            }
        } catch(PDOException $e) {
            $message = '<div class="alert alert-danger">DB Error: '
                     . htmlspecialchars($e->getMessage())
                     . '</div>';
        }
    }
}

// ———————————————
// 2) FETCH USERS
try {
    $stmt  = $pdo->query("
      SELECT id, first_name, last_name, username, email, role
        FROM users
       WHERE protected = 0
       ORDER BY created_at DESC
    ");
    $users = $stmt->fetchAll(PDO::FETCH_ASSOC);
    $users = array_filter(
        $users,
        fn($u) => !in_array($u['username'], $protectedUsers, true)
    );
} catch(PDOException $e) {
    die("DB Error: " . htmlspecialchars($e->getMessage()));
}
// helper to rename “Admin” → “User”
function displayRole(string $r): string {
    return $r==='Admin' ? 'User' : $r;
}

require_once 'header.php';
?>

<a href="settings.php" class="btn btn-secondary mb-3"><i class="bi bi-arrow-left"></i> Back to Settings</a>
<h1>User Management</h1>

<?= $message ?>

<!-- CREATE USER -->
<div class="card mb-4">
  <div class="card-header">Create New User</div>
  <div class="card-body">
    <form method="POST" class="row g-3">
      <div class="col-md-3">
        <label class="form-label" for="first_name">First Name</label>
        <input class="form-control" id="first_name" name="first_name" required>
      </div>
      <div class="col-md-3">
        <label class="form-label" for="last_name">Last Name</label>
        <input class="form-control" id="last_name" name="last_name" required>
      </div>
      <div class="col-md-3">
        <label class="form-label" for="username">Username</label>
        <input class="form-control" id="username" name="username" required>
      </div>
      <div class="col-md-3">
        <label class="form-label" for="password">Password</label>
        <input type="password" class="form-control" id="password" name="password" required>
      </div>
      <div class="col-md-4">
        <label class="form-label" for="email">Email</label>
        <input type="email" class="form-control" id="email" name="email" required>
      </div>
      <div class="col-md-4">
        <label class="form-label" for="role">Role</label>
        <select class="form-select" id="role" name="role">
          <option value="Admin">User</option>
          <option value="Manager">Manager</option>
          <option value="Super Admin">Super Admin</option>
          <option value="StaffUser">Staff User</option>
        </select>
      </div>
      <div class="col-12">
        <button class="btn btn-success">Create User</button>
      </div>
    </form>
  </div>
</div>

<!-- USERS GRID -->
<div class="card mb-4">
  <div class="card-header">Existing Users</div>
  <div class="card-body">
    <?php if ($users): ?>
      <div class="row row-cols-1 row-cols-sm-2 row-cols-md-3 row-cols-lg-4 g-4">
        <?php foreach($users as $u): ?>
          <div class="col">
            <div class="card h-100 shadow-sm border-0">
              <div class="card-body d-flex flex-column">
                <h5 class="card-title">
                  <?= htmlspecialchars($u['first_name'] . ' ' . $u['last_name']) ?>
                </h5>
                <p class="card-text small text-muted mb-4">
                  <i class="bi bi-person me-1"></i><?= htmlspecialchars($u['username']) ?><br>
                  <i class="bi bi-envelope me-1"></i><?= htmlspecialchars($u['email']) ?><br>
                  <i class="bi bi-shield-lock me-1"></i><?= htmlspecialchars(displayRole($u['role'])) ?>
                </p>
                <div class="mt-auto">
                  <a href="update_user.php?id=<?= $u['id'] ?>"
                     class="btn btn-sm btn-outline-primary me-1">
                    <i class="bi bi-pencil"></i>
                  </a>
                  <a href="delete_user.php?id=<?= $u['id'] ?>"
                     class="btn btn-sm btn-outline-danger"
                     onclick="return confirm('Delete <?= htmlspecialchars($u['username']) ?>?');">
                    <i class="bi bi-trash"></i>
                  </a>
                </div>
              </div>
            </div>
          </div>
        <?php endforeach; ?>
      </div>
    <?php else: ?>
      <p class="text-muted">No users found.</p>
    <?php endif; ?>
  </div>
</div>

<?php require_once 'footer.php'; ?>
