<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
    header('Location: login.php'); exit;
}
if (isset($_SESSION['role']) && $_SESSION['role'] === 'StaffUser') {
    header('Location: index.php'); exit;
}
// analytics.php
require_once 'header.php';

try {
    // 1) Global summary metrics
    $total      = (int)$pdo->query("SELECT COUNT(*) FROM job_tickets")->fetchColumn();
    $processing = (int)$pdo->query("SELECT COUNT(*) FROM job_tickets WHERE ticket_status='Processing'")->fetchColumn();
    $complete   = (int)$pdo->query("SELECT COUNT(*) FROM job_tickets WHERE ticket_status='Complete'")->fetchColumn();
    $canceled   = (int)$pdo->query("SELECT COUNT(*) FROM job_tickets WHERE ticket_status='Canceled'")->fetchColumn();
    $hold       = (int)$pdo->query("SELECT COUNT(*) FROM job_tickets WHERE ticket_status='Hold'")->fetchColumn();
    $unassigned = (int)$pdo->query("SELECT COUNT(*) FROM job_tickets WHERE assigned_to IS NULL OR assigned_to=''")
                   ->fetchColumn();

// 1) generate month labels in a DST‐safe way
$months = $counts = [];
$tz     = new DateTimeZone('America/Los_Angeles');
$start  = new DateTimeImmutable('first day of this month', $tz);

for ($i = 11; $i >= 0; $i--) {
    $m = $start->modify("-{$i} months")->format('Y-m');
    $months[]    = $m;
    $counts[$m]  = 0;
}

// 2) pull your real counts
$offset = date('P'); // e.g. "-07:00"
$stmt = $pdo->prepare("
  SELECT DATE_FORMAT(
           CONVERT_TZ(created_at, '+00:00', :offset),
           '%Y-%m'
         ) AS m,
         COUNT(*) AS cnt
    FROM job_tickets
   WHERE created_at >= DATE_SUB(CURDATE(), INTERVAL 11 MONTH)
   GROUP BY m
");
$stmt->execute([':offset' => $offset]);
foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $r) {
    if (isset($counts[$r['m']])) {
        $counts[$r['m']] = (int)$r['cnt'];
    }
}

$monthCounts = array_values($counts);


    // 3a) Avg pages per ticket
    $avgPages  = $pdo->query("
      SELECT AVG(pages_in_original * number_of_sets) FROM job_tickets
    ")->fetchColumn();
    $avgPages  = $avgPages ? round($avgPages, 1) : 0;

    // 3b) Most requested attributes
    function topOne($pdo, $col) {
      $sql = "SELECT `$col`, COUNT(*) AS c FROM job_tickets 
              GROUP BY `$col` ORDER BY c DESC LIMIT 1";
      $r = $pdo->query($sql)->fetch(PDO::FETCH_ASSOC);
      return $r ? htmlspecialchars($r[$col]) : '—';
    }
    $topColor  = topOne($pdo, 'paper_color');
    $topType   = topOne($pdo, 'page_type');
    $topSize   = topOne($pdo, 'paper_size');

    // 3c) Avg completion time (hours)
    $avgSeconds = $pdo->query("
      SELECT AVG(TIMESTAMPDIFF(SECOND, created_at, completed_at))
      FROM job_tickets
      WHERE ticket_status='Complete' AND completed_at IS NOT NULL
    ")->fetchColumn();
    $avgHours = $avgSeconds ? round($avgSeconds / 3600, 1) : 0;

    // 4) By department (top 5)
    $deptLabels = $deptCounts = [];
    $stmt = $pdo->query("
      SELECT location_code, COUNT(*) AS cnt
      FROM job_tickets
      GROUP BY location_code
      ORDER BY cnt DESC
      LIMIT 5
    ");
    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $r) {
        $deptLabels[] = htmlspecialchars($r['location_code']);
        $deptCounts[] = (int)$r['cnt'];
    }

    // 5) Admin/Manager users for dropdown
        // 5) All users who have tickets (distinct assigned_to)
    $users = $pdo
      ->query("
        SELECT DISTINCT assigned_to
        FROM job_tickets
        WHERE assigned_to IS NOT NULL
          AND assigned_to <> ''
        ORDER BY assigned_to
      ")
      ->fetchAll(PDO::FETCH_COLUMN);

}
catch(PDOException $e){
    echo "<div class='alert alert-danger'>DB Error: ".htmlspecialchars($e->getMessage())."</div>";
    require_once 'footer.php';
    exit;
}
?>


  <h1>Analytics</h1>

  <!-- Summary Cards -->
  <div class="row g-3 mb-4">
    <?php foreach([
      'Total'=>$total,
      'Processing'=>$processing,
      'Complete'=>$complete,
      'Canceled'=>$canceled,
      'Hold'=>$hold,
      'Unassigned'=>$unassigned,
    ] as $label=>$value): ?>
      <div class="col-6 col-md-2">
        <div class="card text-center">
          <div class="card-body">
            <h6 class="card-title"><?= $label ?></h6>
            <h3><?= $value ?></h3>
          </div>
        </div>
      </div>
    <?php endforeach; ?>
  </div>

  <!-- 12‑Month Line Chart -->
  <div class="card mb-4">
    <div class="card-header">Submissions (Last 12 Months)</div>
    <div class="card-body">
      <canvas id="lineChart"></canvas>
    </div>
  </div>

  <!-- New Metric Cards -->
  <div class="row g-3 mb-4">
    <div class="col-md-3">
      <div class="card text-center">
        <div class="card-body">
          <h6>Avg Pages Per Ticket</h6>
          <h3><?= $avgPages ?></h3>
        </div>
      </div>
    </div>
    <div class="col-md-3">
      <div class="card text-center">
        <div class="card-body">
          <h6>Top Paper Color</h6>
          <p class="mt-2"><?= $topColor ?></p>
        </div>
      </div>
    </div>
    <div class="col-md-3">
      <div class="card text-center">
        <div class="card-body">
          <h6>Top Page Type</h6>
          <p class="mt-2"><?= $topType ?></p>
        </div>
      </div>
    </div>
    <div class="col-md-3">
      <div class="card text-center">
        <div class="card-body">
          <h6>Top Paper Size</h6>
          <p class="mt-2"><?= $topSize ?></p>
        </div>
      </div>
    </div>
  </div>

  <!-- Avg Completion Time & Dept Chart -->
  <div class="row g-3 mb-5">
    <div class="col-md-3">
      <div class="card text-center">
        <div class="card-body">
          <h6>Avg Completion (hrs)</h6>
          <h3><?= $avgHours ?></h3>
        </div>
      </div>
    </div>
    <div class="col-md-9">
      <div class="card">
        <div class="card-header">Top 5 by Department</div>
        <div class="card-body">
          <canvas id="deptChart"></canvas>
        </div>
      </div>
    </div>
  </div>

  <!-- Per‑User Stats -->
  <div class="card">
    <div class="card-header">Per‑User Stats</div>
    <div class="card-body row align-items-center">
      <div class="col-md-4">
        <select id="userSelect" class="form-select">
          <option value="">— Select User —</option>
          <?php foreach($users as $u): ?>
            <option><?= htmlspecialchars($u) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="col-md-8" id="userStats"></div>
    </div>
  </div>


<!-- Chart.js -->
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
  // Line chart
  new Chart(document.getElementById('lineChart'), {
    type: 'line',
    data: {
      labels: <?= json_encode($months) ?>,
      datasets:[{
        label:'Tickets',
        data:<?= json_encode($monthCounts) ?>,
        fill:true, tension:0.3,
        borderColor:'rgba(54,162,235,1)',
        backgroundColor:'rgba(54,162,235,0.2)'
      }]
    },
    options:{ scales:{ y:{ beginAtZero:true } } }
  });

  // Dept bar chart
  new Chart(document.getElementById('deptChart'), {
    type:'bar',
    data:{
      labels:<?= json_encode($deptLabels) ?>,
      datasets:[{
        label:'Submissions',
        data:<?= json_encode($deptCounts) ?>,
        borderWidth:1
      }]
    },
    options:{
      indexAxis:'y',
      scales:{ x:{ beginAtZero:true } }
    }
  });

  // Per‑user stats AJAX (same as before)
  document.getElementById('userSelect').addEventListener('change', function(){
    const u = this.value, out = document.getElementById('userStats');
    if (!u) { out.innerHTML = ''; return; }
    fetch(`get_user_stats.php?user=${encodeURIComponent(u)}`)
      .then(r => r.json())
      .then(d => {
  if (d.error) return out.innerHTML = `<div class="text-danger">${d.error}</div>`;
  out.innerHTML = `
    <div class="row text-center mb-3">
      <div class="col-12">
        <strong>Avg Turnaround:</strong> ${d.avg_turnaround}
      </div>
    </div>
    <div class="row text-center">
      ${['total','processing','complete','canceled'].map(k=>`
        <div class="col-6 col-md-3 mb-3">
          <div class="card">
            <div class="card-body">
              <h6 class="text-capitalize">${k.replace('_',' ')}</h6>
              <h4>${d[k]}</h4>
            </div>
          </div>
        </div>
      `).join('')}
    </div>`;
})

      .catch(()=> out.innerHTML = `<div class="text-danger">Error loading</div>`);
  });
</script>

<?php require_once 'footer.php'; ?>
