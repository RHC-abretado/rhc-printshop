<?php
session_start();
if (empty($_SESSION['logged_in']) || $_SESSION['role'] !== 'Super Admin') {
    header("Location: login.php");
    exit;
}

// bring in your one-and-only PDO (with PT time zone already set)
require_once __DIR__ . '/assets/database.php';

$message = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username  = trim($_POST['username']   ?? '');
    $password  = $_POST['password']        ?? '';
    $email     = trim($_POST['email']      ?? '');
    $role      = trim($_POST['role']       ?? 'Admin');
    $firstName = trim($_POST['first_name'] ?? '');
    $lastName  = trim($_POST['last_name']  ?? '');

    if ($username === '' || $password === '' || $email === '' 
        || $firstName === '' || $lastName === ''
    ) {
        $message = '<div class="alert alert-danger">All fields are required.</div>';
    } else {
        try {
            // Check for existing username
            $check = $pdo->prepare("SELECT COUNT(*) FROM users WHERE username = :uname");
            $check->execute([':uname' => $username]);
            if ($check->fetchColumn() > 0) {
                $message = '<div class="alert alert-danger">Username already exists.</div>';
            } else {
                // Insert the new user
                $hash = password_hash($password, PASSWORD_DEFAULT);
                $ins  = $pdo->prepare("
                  INSERT INTO users
                    (username,password_hash,email,role,first_name,last_name,created_at)
                  VALUES
                    (:username,:hash,:email,:role,:fname,:lname,NOW())
                ");
                $ins->execute([
                  ':username' => $username,
                  ':hash'     => $hash,
                  ':email'    => $email,
                  ':role'     => $role,
                  ':fname'    => $firstName,
                  ':lname'    => $lastName,
                ]);

                if ($ins->rowCount() > 0) {
                    // Log the creation
                    $log = $pdo->prepare("
                      INSERT INTO activity_log (username, event, details)
                      VALUES (:u,'create_user',:d)
                    ");
                    $details = "New user '{$username}' with role '{$role}'";
                    $log->execute([
                      ':u' => $_SESSION['username'],
                      ':d' => $details
                    ]);

                    $message = '<div class="alert alert-success">
                                  User created successfully.
                                </div>';
                } else {
                    $message = '<div class="alert alert-danger">
                                  Failed to create user.
                                </div>';
                }
            }
        } catch (PDOException $e) {
            $message = '<div class="alert alert-danger">
                          DB Error: ' . htmlspecialchars($e->getMessage()) . '
                        </div>';
        }
    }
}
?>
<?php require_once 'header.php'; ?>

<div class="container py-5">
  <h1>Create New User</h1>
  <?= $message ?>
  <form method="POST" class="row g-3">
    <div class="col-md-4">
      <label for="username"    class="form-label">Username</label>
      <input type="text" name="username" id="username"
             class="form-control" required>
    </div>
    <div class="col-md-4">
      <label for="password"    class="form-label">Password</label>
      <input type="password" name="password" id="password"
             class="form-control" required>
    </div>
    <div class="col-md-4">
      <label for="email"       class="form-label">Email</label>
      <input type="email" name="email" id="email"
             class="form-control" required>
    </div>
    <div class="col-md-4">
      <label for="first_name"  class="form-label">First Name</label>
      <input type="text" name="first_name" id="first_name"
             class="form-control" required>
    </div>
    <div class="col-md-4">
      <label for="last_name"   class="form-label">Last Name</label>
      <input type="text" name="last_name" id="last_name"
             class="form-control" required>
    </div>
    <div class="col-md-4">
      <label for="role"        class="form-label">Role</label>
      <select name="role" id="role" class="form-select">
        <option value="Admin">Admin</option>
        <option value="Manager">Manager</option>
        <option value="Super Admin">Super Admin</option>
      </select>
    </div>
    <div class="col-12">
      <button type="submit" class="btn btn-success">Create User</button>
    </div>
  </form>
</div>

<?php require_once 'footer.php'; ?>
