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
    $users = $pdo
      ->query(
        "
        SELECT DISTINCT assigned_to
        FROM job_tickets
        WHERE assigned_to IS NOT NULL
          AND assigned_to <> ''
        ORDER BY assigned_to
        "
      )
      ->fetchAll(PDO::FETCH_COLUMN);
} catch(PDOException $e){
    echo "<div class='alert alert-danger'>DB Error: ".htmlspecialchars($e->getMessage())."</div>";
    require_once 'footer.php';
    exit;
}
?>

<h1>Analytics</h1>

<div class="row mb-4">
  <div class="col-md-4">
    <select id="userSelect" class="form-select">
      <option value="">— Select User —</option>
      <?php foreach($users as $u): ?>
        <option><?= htmlspecialchars($u) ?></option>
      <?php endforeach; ?>
    </select>
  </div>
</div>

<!-- Summary Cards -->
<div class="row g-3 mb-4">
  <?php foreach(['Total','Processing','Complete','Canceled','Hold','Unassigned'] as $label): ?>
    <?php $id = strtolower($label); ?>
    <div class="col-6 col-md-2">
      <div class="card text-center">
        <div class="card-body">
          <h6 class="card-title"><?= $label ?></h6>
          <h3 id="<?= $id ?>">0</h3>
        </div>
      </div>
    </div>
  <?php endforeach; ?>
</div>

<!-- Metric Cards -->
<div class="row g-3 mb-4">
  <div class="col-6 col-md-2">
    <div class="card text-center">
      <div class="card-body">
        <h6>Avg Pages Per Ticket</h6>
        <h3 id="avgPages">0</h3>
      </div>
    </div>
  </div>
  <div class="col-6 col-md-2">
    <div class="card text-center">
      <div class="card-body">
        <h6>Top Paper Color</h6>
        <p class="mt-2" id="topColor">—</p>
      </div>
    </div>
  </div>
  <div class="col-6 col-md-2">
    <div class="card text-center">
      <div class="card-body">
        <h6>Top Page Type</h6>
        <p class="mt-2" id="topType">—</p>
      </div>
    </div>
  </div>
  <div class="col-6 col-md-2">
    <div class="card text-center">
      <div class="card-body">
        <h6>Top Paper Size</h6>
        <p class="mt-2" id="topSize">—</p>
      </div>
    </div>
  </div>
  <div class="col-6 col-md-2">
    <div class="card text-center">
      <div class="card-body">
        <h6>Avg Completion</h6>
        <h3 id="avgCompletion">0</h3>
      </div>
    </div>
  </div>
</div>

<!-- Charts -->
<div class="row g-3 mb-4">
  <div class="col-md-6">
    <div class="card">
      <div class="card-header">Submissions (Last 12 Months)</div>
      <div class="card-body">
        <canvas id="lineChart"></canvas>
      </div>
    </div>
  </div>
  <div class="col-md-6">
    <div class="card">
      <div class="card-header">Top 5 by Department</div>
      <div class="card-body">
        <canvas id="deptChart"></canvas>
      </div>
    </div>
  </div>
</div>

<!-- Chart.js -->
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
const userSelect = document.getElementById('userSelect');
let lineChart, deptChart;

function loadStats(user='') {
  userSelect.disabled = true;
  const url = user ? `get_analytics_stats.php?user=${encodeURIComponent(user)}` : 'get_analytics_stats.php';
  fetch(url)
    .then(r => r.json())
    .then(d => {
      ['total','processing','complete','canceled','hold','unassigned'].forEach(k => {
        const el = document.getElementById(k);
        if (el && typeof d[k] !== 'undefined') el.textContent = d[k];
      });
      document.getElementById('avgPages').textContent = d.avgPages;
      document.getElementById('topColor').textContent = d.topColor;
      document.getElementById('topType').textContent = d.topType;
      document.getElementById('topSize').textContent = d.topSize;
      document.getElementById('avgCompletion').textContent = d.avgCompletion;

      if (!lineChart) {
        lineChart = new Chart(document.getElementById('lineChart'), {
          type: 'line',
          data: {
            labels: d.months,
            datasets:[{
              label: 'Tickets',
              data: d.monthCounts,
              fill: true,
              tension: 0.3,
              borderColor: 'rgba(54,162,235,1)',
              backgroundColor: 'rgba(54,162,235,0.2)'
            }]
          },
          options: { scales:{ y:{ beginAtZero:true } } }
        });
      } else {
        lineChart.data.labels = d.months;
        lineChart.data.datasets[0].data = d.monthCounts;
        lineChart.update();
      }

      if (!deptChart) {
        deptChart = new Chart(document.getElementById('deptChart'), {
          type:'bar',
          data:{
            labels:d.deptLabels,
            datasets:[{ label:'Submissions', data:d.deptCounts, borderWidth:1 }]
          },
          options:{ indexAxis:'y', scales:{ x:{ beginAtZero:true } } }
        });
      } else {
        deptChart.data.labels = d.deptLabels;
        deptChart.data.datasets[0].data = d.deptCounts;
        deptChart.update();
      }
    })
    .catch(err => console.error(err))
    .finally(() => { userSelect.disabled = false; });
}

userSelect.addEventListener('change', () => {
  loadStats(userSelect.value);
});

loadStats();
</script>

<?php require_once 'footer.php'; ?>

