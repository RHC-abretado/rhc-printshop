<?php
// get_user_stats.php — returns JSON counts for a single user
header('Content-Type: application/json');
require_once 'assets/database.php';

$user = $_GET['user'] ?? '';
if(!$user) {
  echo json_encode(['error'=>'No user specified']);
  exit;
}

try {
    // 1) Totals per status
    $sql = "
      SELECT
        COUNT(*)                                                    AS total,
        SUM(CASE WHEN ticket_status='Processing' THEN 1 ELSE 0 END) AS processing,
        SUM(CASE WHEN ticket_status='Complete'   THEN 1 ELSE 0 END) AS complete,
        SUM(CASE WHEN ticket_status='Canceled'   THEN 1 ELSE 0 END) AS canceled
      FROM job_tickets
      WHERE assigned_to = :user
    ";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([':user' => $user]);
    $d = $stmt->fetch(PDO::FETCH_ASSOC);

    // 2) Raw avg seconds
    $sql2 = "
      SELECT AVG(
        TIMESTAMPDIFF(SECOND, created_at, completed_at)
      ) AS avg_sec
      FROM job_tickets
      WHERE assigned_to = :user
        AND ticket_status = 'Complete'
        AND completed_at IS NOT NULL
    ";
    $stmt2 = $pdo->prepare($sql2);
    $stmt2->execute([':user' => $user]);
    $row = $stmt2->fetch(PDO::FETCH_ASSOC);
    $avgSec = $row['avg_sec'] !== null
            ? (int)$row['avg_sec']
            : 0;

    // 3) Convert to days/hours/minutes
    $days    = floor($avgSec / 86400);
    $hours   = floor(($avgSec % 86400) / 3600);
    $minutes = floor(($avgSec % 3600) / 60);

    $parts = [];
    if ($days)    $parts[] = "{$days}d";
    if ($hours)   $parts[] = "{$hours}h";
    // always show minutes (even if zero, when days or hours are non-zero)
    if ($minutes || empty($parts)) {
        $parts[] = "{$minutes}m";
    }

    $avgTurnaround = implode(' ', $parts);

    echo json_encode([
      'total'            => (int)$d['total'],
      'processing'       => (int)$d['processing'],
      'complete'         => (int)$d['complete'],
      'canceled'         => (int)$d['canceled'],
      'avg_turnaround'   => $avgTurnaround,
    ]);
} catch (PDOException $e) {
    echo json_encode(['error' => 'DB error: ' . $e->getMessage()]);
}