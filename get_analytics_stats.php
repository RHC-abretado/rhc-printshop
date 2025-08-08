<?php
// get_analytics_stats.php — returns global or per-user analytics
header('Content-Type: application/json');
require_once 'assets/database.php';

$user = $_GET['user'] ?? '';
$bind = [];
$userWhere = '';
if ($user !== '') {
    $userWhere = ' AND assigned_to = :user';
    $bind[':user'] = $user;
}

try {
    // Summary counts
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM job_tickets WHERE 1=1$userWhere");
    $stmt->execute($bind);
    $total = (int)$stmt->fetchColumn();

    $stmt = $pdo->prepare("SELECT COUNT(*) FROM job_tickets WHERE ticket_status='Processing'$userWhere");
    $stmt->execute($bind);
    $processing = (int)$stmt->fetchColumn();

    $stmt = $pdo->prepare("SELECT COUNT(*) FROM job_tickets WHERE ticket_status='Complete'$userWhere");
    $stmt->execute($bind);
    $complete = (int)$stmt->fetchColumn();

    $stmt = $pdo->prepare("SELECT COUNT(*) FROM job_tickets WHERE ticket_status='Canceled'$userWhere");
    $stmt->execute($bind);
    $canceled = (int)$stmt->fetchColumn();

    $stmt = $pdo->prepare("SELECT COUNT(*) FROM job_tickets WHERE ticket_status='Hold'$userWhere");
    $stmt->execute($bind);
    $hold = (int)$stmt->fetchColumn();

    if ($user === '') {
        $unassigned = (int)$pdo->query("SELECT COUNT(*) FROM job_tickets WHERE assigned_to IS NULL OR assigned_to=''")->fetchColumn();
    } else {
        $unassigned = 0;
    }

    // 12-month submissions
    $months = $counts = [];
    $tz    = new DateTimeZone('America/Los_Angeles');
    $start = new DateTimeImmutable('first day of this month', $tz);
    for ($i = 11; $i >= 0; $i--) {
        $m = $start->modify("-{$i} months")->format('Y-m');
        $months[]   = $m;
        $counts[$m] = 0;
    }
    $offset = date('P');
    $sqlMonth = "
      SELECT DATE_FORMAT(CONVERT_TZ(created_at, '+00:00', :offset), '%Y-%m') AS m,
             COUNT(*) AS cnt
        FROM job_tickets
       WHERE created_at >= DATE_SUB(CURDATE(), INTERVAL 11 MONTH)$userWhere
       GROUP BY m";
    $stmt = $pdo->prepare($sqlMonth);
    $params = [':offset' => $offset] + ($user !== '' ? [':user' => $user] : []);
    $stmt->execute($params);
    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $r) {
        if (isset($counts[$r['m']])) {
            $counts[$r['m']] = (int)$r['cnt'];
        }
    }
    $monthCounts = array_values($counts);

    // Avg pages per ticket
    $stmt = $pdo->prepare("SELECT AVG(pages_in_original * number_of_sets) FROM job_tickets WHERE 1=1$userWhere");
    $stmt->execute($bind);
    $avgPages = $stmt->fetchColumn();
    $avgPages = $avgPages ? round($avgPages, 1) : 0;

    // Most requested attributes
    $topOne = function($col) use ($pdo, $userWhere, $bind) {
        $sql = "SELECT `$col`, COUNT(*) AS c FROM job_tickets WHERE 1=1$userWhere GROUP BY `$col` ORDER BY c DESC LIMIT 1";
        $stmt = $pdo->prepare($sql);
        $stmt->execute($bind);
        $r = $stmt->fetch(PDO::FETCH_ASSOC);
        return $r && $r[$col] !== null && $r[$col] !== '' ? $r[$col] : '—';
    };
    $topColor = $topOne('paper_color');
    $topType  = $topOne('page_type');
    $topSize  = $topOne('paper_size');

    // Avg completion time
    $stmt = $pdo->prepare("SELECT AVG(TIMESTAMPDIFF(SECOND, created_at, completed_at)) FROM job_tickets WHERE ticket_status='Complete' AND completed_at IS NOT NULL$userWhere");
    $stmt->execute($bind);
    $avgSeconds = $stmt->fetchColumn();
    if ($avgSeconds) {
        $days = intdiv($avgSeconds, 86400);
        $avgSeconds %= 86400;
        $hours = intdiv($avgSeconds, 3600);
        $avgSeconds %= 3600;
        $minutes = intdiv($avgSeconds, 60);
        $parts = [];
        if ($days > 0) {
            $parts[] = "{$days}d";
        }
        if ($hours > 0) {
            $parts[] = "{$hours}h";
        }
        if ($minutes > 0 || empty($parts)) {
            $parts[] = "{$minutes}m";
        }
        $avgCompletion = implode(' ', $parts);
    } else {
        $avgCompletion = '0m';
    }

    // Top departments
    $sql = "SELECT location_code, COUNT(*) AS cnt FROM job_tickets WHERE 1=1$userWhere GROUP BY location_code ORDER BY cnt DESC LIMIT 5";
    $stmt = $pdo->prepare($sql);
    $stmt->execute($bind);
    $deptLabels = $deptCounts = [];
    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
        $deptLabels[] = $row['location_code'];
        $deptCounts[] = (int)$row['cnt'];
    }

    echo json_encode([
        'total'       => $total,
        'processing'  => $processing,
        'complete'    => $complete,
        'canceled'    => $canceled,
        'hold'        => $hold,
        'unassigned'  => $unassigned,
        'months'      => $months,
        'monthCounts' => $monthCounts,
        'avgPages'    => $avgPages,
        'topColor'    => $topColor,
        'topType'     => $topType,
        'topSize'     => $topSize,
        'avgCompletion' => $avgCompletion,
        'deptLabels'  => $deptLabels,
        'deptCounts'  => $deptCounts,
    ]);
} catch (PDOException $e) {
    echo json_encode(['error' => 'DB error: ' . $e->getMessage()]);
}

