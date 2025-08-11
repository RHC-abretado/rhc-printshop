<?php
if (session_status() === PHP_SESSION_NONE) session_start();

// Handle AJAX request for logging ticket views
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    header('Content-Type: application/json');
    
    if (empty($_SESSION['logged_in'])) {
        http_response_code(401);
        echo json_encode(['success' => false, 'error' => 'Not logged in']);
        exit;
    }
    
    require_once __DIR__ . '/assets/database.php';
    
    $data = json_decode(file_get_contents('php://input'), true);
    $ticketNumber = trim($data['ticket_number'] ?? '');
    
    if (empty($ticketNumber)) {
        echo json_encode(['success' => false, 'error' => 'Missing ticket number']);
        exit;
    }
    
    try {
        $stmt = $pdo->prepare("
            INSERT INTO activity_log (username, event, details)
            VALUES (:username, 'view_ticket', :details)
        ");
        
        $stmt->execute([
            ':username' => $_SESSION['username'],
            ':details' => "Viewed ticket #{$ticketNumber}"
        ]);
        
        echo json_encode(['success' => true]);
        exit;
    } catch (PDOException $e) {
        echo json_encode(['success' => false, 'error' => 'Database error']);
        exit;
    }
}

// Continue with existing GET request logic for displaying the page
if (empty($_SESSION['logged_in'])) {
  header('Location: login.php');
  exit;
}

// Only logged-in users except StaffUser may access activity logs
if (isset($_SESSION['role']) && $_SESSION['role'] === 'StaffUser') {
    header('Location: index.php');
    exit;
}

require 'header.php';

// Purge legacy mismatch entries from the activity log
$pdo->exec("DELETE FROM activity_log WHERE event IN ('status_token_mismatch','check_status_token_mismatch')");
?>

<div class="container-fluid">
  <a href="settings.php" class="btn btn-secondary mb-3"><i class="bi bi-arrow-left"></i> Back to Settings</a>
  <h1>Activity History</h1>

  <!-- ── FILTER FORM ─────────────────────────────────────────────── -->
  <form class="row g-3 mb-4" method="GET">
    <div class="col-md-3">
      <label for="userFilter" class="form-label">User</label>
      <input type="text" id="userFilter" name="username_filter" class="form-control"
             value="<?= htmlspecialchars($_GET['username_filter'] ?? '') ?>">
    </div>
    <div class="col-md-3">
      <label for="eventFilter" class="form-label">Event</label>
      <select id="eventFilter" name="event" class="form-select">
        <option value="">— All —</option>
        <?php 
       $all = [
  'login','logout',
  'submit_ticket','status_change','assign_ticket','view_ticket','update_cost',
  'create_user','check_status','update_user','delete_ticket','delete_user',
    'password_reset_request','password_reset',
  'add_pricing_item','update_pricing_item','delete_pricing_item',
  'add_pricing_category','edit_pricing_category','delete_pricing_category',
  'edit_pricing_headers'
];
        foreach ($all as $e): ?>
          <option value="<?= $e ?>"
            <?= ($_GET['event'] ?? '') === $e ? 'selected' : '' ?>>
            <?= ucfirst(str_replace('_',' ',$e)) ?>
          </option>
        <?php endforeach; ?>
      </select>
    </div>
    <div class="col-md-3">
      <label for="dateFrom" class="form-label">From</label>
      <input type="date" id="dateFrom" name="from" class="form-control"
             value="<?= htmlspecialchars($_GET['from'] ?? '') ?>">
    </div>
    <div class="col-md-3">
      <label for="dateTo" class="form-label">To</label>
      <input type="date" id="dateTo" name="to" class="form-control"
             value="<?= htmlspecialchars($_GET['to'] ?? '') ?>">
    </div>
    <div class="col-12">
      <button type="submit" class="btn btn-primary">Filter</button>
       <a
        href="<?= htmlspecialchars($_SERVER['PHP_SELF'], ENT_QUOTES) ?>"
        class="btn btn-secondary"
      >Reset</a>
    </div>
  </form>
  <!-- ────────────────────────────────────────────────────────────── -->

  <?php

// build dynamic WHERE clauses
$conds  = [];
$params = [];

// always exclude mismatched status checks from results
$conds[] = "event NOT IN ('status_token_mismatch','check_status_token_mismatch')";

// user filter
if (!empty($_GET['username_filter'])) {
  $conds[]         = 'username LIKE :user';
  $params[':user'] = '%'.$_GET['username_filter'].'%';
}

// event filter
if (!empty($_GET['event'])) {
  $conds[]          = 'event = :event';
  $params[':event'] = $_GET['event'];
}

// date‐from filter: local midnight → UTC
if (!empty($_GET['from'])) {
  $dtFrom = new DateTime($_GET['from'] . ' 00:00:00',
                        new DateTimeZone('America/Los_Angeles'));
  $dtFrom->setTimezone(new DateTimeZone('UTC'));

  $conds[]         = 'event_time >= :from';
  $params[':from'] = $dtFrom->format('Y-m-d H:i:s');
}

// date‐to filter: local 23:59:59 → UTC
if (!empty($_GET['to'])) {
  $dtTo = new DateTime($_GET['to'] . ' 23:59:59',
                      new DateTimeZone('America/Los_Angeles'));
  $dtTo->setTimezone(new DateTimeZone('UTC'));

  $conds[]        = 'event_time <= :to';
  $params[':to']  = $dtTo->format('Y-m-d H:i:s');
}

// now build WHERE clause
$where = $conds
  ? 'WHERE ' . implode(' AND ', $conds)
  : '';

// determine if any filter is active
$hasFilter = ! empty($_GET['username_filter'])
           || ! empty($_GET['event'])
           || ! empty($_GET['from'])
           || ! empty($_GET['to']);

// apply default limit only when there is no filter
$limitSQL = $hasFilter ? '' : 'LIMIT 50';

$sql = "
  SELECT event_time, username, event, details
  FROM activity_log
  {$where}
  ORDER BY event_time DESC
  {$limitSQL}
";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$logs = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

  <?php if ($hasFilter): ?>
    <div class="alert alert-info">
      <i class="bi bi-info-circle"></i> 
      Showing <strong><?= count($logs) ?></strong> records matching your filter criteria.
    </div>
  <?php endif; ?>

  <div class="table-responsive">
    <table class="table table-striped">
      <thead>
        <tr>
          <th>Time</th><th>User</th><th>Event</th><th>Details</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($logs as $l): ?>
        <tr>
          <td>
            <?= htmlspecialchars( toLA($l['event_time'] ?? '') ) ?>
          </td>
          <td>
            <?= htmlspecialchars( $l['username'] ?? '' ) ?>
          </td>
          <td>
            <?= htmlspecialchars( ucfirst(str_replace('_',' ', $l['event'] ?? '')) ) ?>
          </td>
          <td>
            <?= nl2br(htmlspecialchars( $l['details'] ?? '' )) ?>
          </td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</div>

<?php require 'footer.php'; ?>
