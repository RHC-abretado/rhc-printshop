<?php
date_default_timezone_set('America/Los_Angeles');
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
    header('Location: login.php'); exit;
}
if (isset($_SESSION['role']) && $_SESSION['role'] === 'StaffUser') {
    header('Location: index.php'); exit;
}

// 1) Pull in just your DB connection (no HTML yet)
require_once 'assets/database.php';


// 2) Grab filter inputs
$fromDate = $_POST['from_date'] ?? '';
$toDate   = $_POST['to_date']   ?? '';
$status   = $_POST['status']    ?? 'All';
$action   = $_POST['action']    ?? '';
$useCompletionDate = isset($_POST['use_completion_date']);

// 3) Build your WHERE/clause and fetch $tickets
$tickets    = [];
$conditions = [];
$params     = [];

if ($action === 'Filter' || $action === 'Export CSV') {
    $dateField = $useCompletionDate ? 'completed_at' : 'created_at';
    
    // If using completion date, only include tickets that have been completed
    if ($useCompletionDate) {
        $conditions[] = "completed_at IS NOT NULL";
    }
    
    if ($fromDate && $toDate) {
        $conditions[]        = "$dateField BETWEEN :fromDate AND :toDate";
        $params[':fromDate'] = $fromDate . " 00:00:00";
        $params[':toDate']   = $toDate   . " 23:59:59";
    } elseif ($fromDate) {
        $conditions[]        = "$dateField >= :fromDate";
        $params[':fromDate'] = $fromDate . " 00:00:00";
    } elseif ($toDate) {
        $conditions[]        = "$dateField <= :toDate";
        $params[':toDate']   = $toDate   . " 23:59:59";
    }
    if ($status !== 'All') {
        $conditions[]      = "ticket_status = :status";
        $params[':status'] = $status;
    }

    $where = $conditions ? "WHERE " . implode(' AND ', $conditions) : "";
    $stmt  = $pdo->prepare("SELECT * FROM job_tickets $where ORDER BY created_at DESC");
    $stmt->execute($params);
    $tickets = $stmt->fetchAll(PDO::FETCH_ASSOC);
}

// 4) If they asked for CSV, send it now (before any HTML)
if ($action === 'Export CSV') {
    header('Content-Type: text/csv; charset=UTF-8');
    header('Content-Disposition: attachment; filename="tickets_export.csv"');
    $out = fopen('php://output','w');

    // CSV header row
    fputcsv($out, [
        'ticket_number','ticket_status','created_at','completed_at','date_wanted',
        'first_name','last_name','department_name','email','phone',
        'location_code','other_location_code','delivery_method',
        'job_title','description','pages_in_original','assigned_to',
        'number_of_sets','page_layout','print_copies_in','other_print_copies',
        'page_type','other_page_type','paper_color','other_paper_color',
        'color_requirement','paper_size','other_paper_size',
        'admin_notes','cut_paper','other_options','separator_color',
        'staple_location','fold_type','binding_type','uploaded_file','total_cost'
    ]);

    $protocol = ((!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS']!=='off')
                 || $_SERVER['SERVER_PORT']==443) ? 'https://' : 'http://';
    $domain = $_SERVER['HTTP_HOST'];

    foreach ($tickets as $row) {
        $created    = toLA($row['created_at'], 'm-d-Y H:i:s');
        $completed  = !empty($row['completed_at']) ? toLA($row['completed_at'], 'm-d-Y H:i:s') : '';
        $wanted     = date('m-d-Y', strtotime($row['date_wanted']));
        $uploaded = [];
        if (!empty($row['file_path'])) {
            foreach (explode(',', $row['file_path']) as $p) {
                $p = trim($p);
                if ($p) {
                    // adjust the path if needed
                    $uploaded[] = $protocol . $domain . '/print/' . $p;
                }
            }
        }

        fputcsv($out, [
            $row['ticket_number'],
            $row['ticket_status'],
            $created,
            $completed,
            $wanted,
            $row['first_name'],
            $row['last_name'],
            $row['department_name'],
            $row['email'],
            $row['phone'],
            $row['location_code'],
            $row['other_location_code'],
            $row['delivery_method'],
            $row['job_title'],
            $row['description'],
            $row['pages_in_original'],
            $row['assigned_to'],
            $row['number_of_sets'],
            $row['page_layout'],
            $row['print_copies_in'],
            $row['other_print_copies'],
            $row['page_type'],
            $row['other_page_type'],
            $row['paper_color'],
            $row['other_paper_color'],
            $row['color_requirement'],
            $row['paper_size'],
            $row['other_paper_size'],
            $row['admin_notes'],
            $row['cut_paper'],
            $row['other_options'],
            $row['separator_color'],
            $row['staple_location'],
            $row['fold_type'],
            $row['binding_type'],
            implode('; ', $uploaded),
            number_format((float)$row['total_cost'], 2)
        ]);
    }

    fclose($out);
    exit;
}

// 5) If not exporting, include your header + render the HTML/filter form/table
require_once 'header.php';
?>

<a href="settings.php" class="btn btn-secondary mb-3"><i class="bi bi-arrow-left"></i> Back to Settings</a>
<h1>Export Tickets</h1>
<div class="card">
  <div class="card-header">Filter &amp; Export</div>
  <div class="card-body">
    <form method="POST" class="row g-3 mb-3">
      <div class="col-md-3">
        <label for="from_date" class="form-label">From (<?= $useCompletionDate ? 'Completed' : 'Created' ?>):</label>
        <input type="date" id="from_date" name="from_date" class="form-control"
               value="<?=htmlspecialchars($fromDate)?>">
      </div>
      <div class="col-md-3">
        <label for="to_date" class="form-label">To (<?= $useCompletionDate ? 'Completed' : 'Created' ?>):</label>
        <input type="date" id="to_date" name="to_date" class="form-control"
               value="<?=htmlspecialchars($toDate)?>">
      </div>
      <div class="col-md-3">
  <label for="status" class="form-label">Status:</label>
  <select id="status" name="status" class="form-select">
    <?php foreach (['All','New','Processing','Complete','Canceled','Hold'] as $s): ?>
      <option value="<?=$s?>" <?=($status===$s?'selected':'')?>><?=$s?></option>
    <?php endforeach;?>
  </select>
</div>
<div class="col-md-3">
  <div class="form-check mt-4">
    <input class="form-check-input" type="checkbox" name="use_completion_date" id="use_completion_date" value="1" <?= isset($_POST['use_completion_date']) ? 'checked' : '' ?>>
    <label class="form-check-label" for="use_completion_date">
      Use Completion Date
    </label>
    <div class="form-text">Filter by completion date instead of creation date</div>
  </div>
</div>
      <div class="col-md-3 d-flex align-items-end">
        <button name="action" value="Filter"    class="btn btn-primary me-2"><i class="bi bi-search"></i> Filter</button>
        <button name="action" value="Export CSV" class="btn btn-success"><i class="bi bi-file-earmark-arrow-down"></i> Export CSV</button>
      </div>
    </form>

   <?php if (!empty($tickets)): ?>
      <div class="alert alert-info">
        <strong><?= count($tickets) ?></strong> ticket<?= count($tickets) !== 1 ? 's' : '' ?> found
        <?php if ($useCompletionDate): ?>
          (filtered by completion date)
        <?php else: ?>
          (filtered by creation date)
        <?php endif; ?>
      </div>
      <div class="table-responsive">
        <table class="table table-striped table-bordered align-middle">
          <thead class="table-light">
            <tr>
              <th>Ticket #</th>
              <th>Status</th>
              <th>Created At</th>
              <th>Completion Date</th>
              <th>Due Date</th>
              <th>First Name</th>
              <th>Last Name</th>
              <th>Email</th>
              <th>Title</th>
              <th>Assigned To</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($tickets as $r): ?>
<tr>
  <td><?=htmlspecialchars($r['ticket_number'] ?? '')?></td>
  <td><?=htmlspecialchars($r['ticket_status'] ?? '')?></td>
  <td><?= htmlspecialchars(toLA($r['created_at'], 'm-d-Y H:i:s')) ?></td>
  <td><?= htmlspecialchars(!empty($r['completed_at']) ? toLA($r['completed_at'], 'm-d-Y H:i:s') : '') ?></td>
  <td><?= htmlspecialchars(date('m-d-Y', strtotime($r['date_wanted']))) ?></td>
  <td><?=htmlspecialchars($r['first_name'] ?? '')?></td>
  <td><?=htmlspecialchars($r['last_name'] ?? '')?></td>
  <td><?=htmlspecialchars($r['email'] ?? '')?></td>
  <td><?=htmlspecialchars($r['job_title'] ?? '')?></td>
  <td><?=htmlspecialchars($r['assigned_to'] ?? '')?></td>
</tr>
<?php endforeach;?>
          </tbody>
        </table>
      </div>
    <?php elseif ($action==='Filter'): ?>
      <div class="alert alert-info">No tickets found for that filter.</div>
    <?php else: ?>
      <p class="text-muted">Use the filter above to preview before exporting.</p>
    <?php endif; ?>
  </div>
</div>

<?php require_once 'footer.php'; ?>