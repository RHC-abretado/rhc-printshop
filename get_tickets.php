<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

header('Content-Type: application/json');

if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
    echo json_encode(['tickets' => [], 'totalPages' => 0]);
    exit;
}

require_once 'assets/database.php';

$page  = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
$size  = isset($_GET['size']) ? max(1, (int)$_GET['size']) : 30;
$status = $_GET['status'] ?? 'All';
$search = trim($_GET['search'] ?? '');

$offset = ($page - 1) * $size;

$where = [];
$params = [];

if (!in_array($_SESSION['role'], ['Manager', 'Super Admin'], true)) {
    $where[] = 'assigned_to = :user';
    $params[':user'] = $_SESSION['username'];
}

if ($status !== 'All') {
    $where[] = 'ticket_status = :status';
    $params[':status'] = $status;
}

if ($search !== '') {
    $where[] = "(CONCAT(first_name, ' ', last_name) LIKE :search
                 OR assigned_to LIKE :search
                 OR department_name LIKE :search
                 OR location_code LIKE :search)";
    $params[':search'] = '%' . $search . '%';
}

$whereSql = $where ? 'WHERE ' . implode(' AND ', $where) : '';

$countSql = "SELECT COUNT(*) FROM job_tickets $whereSql";
$countStmt = $pdo->prepare($countSql);
$countStmt->execute($params);
$total = (int)$countStmt->fetchColumn();
$totalPages = (int)ceil($total / $size);

$sql = "SELECT id, ticket_number, ticket_status, created_at, date_wanted,
               job_title, first_name, last_name, admin_notes, assigned_to,
               department_name, email, phone, location_code, other_location_code,
               delivery_method, description, pages_in_original, number_of_sets,
               completed_at, total_cost
        FROM job_tickets $whereSql
        ORDER BY created_at DESC
        LIMIT :limit OFFSET :offset";

$stmt = $pdo->prepare($sql);
foreach ($params as $k => $v) {
    $stmt->bindValue($k, $v);
}
$stmt->bindValue(':limit', $size, PDO::PARAM_INT);
$stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
$stmt->execute();
$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

foreach ($rows as &$row) {
    $row['created_at_display'] = toLA($row['created_at'], 'm/d/Y');
    $row['date_wanted_display'] = date('m/d/Y', strtotime($row['date_wanted']));
    if (!empty($row['completed_at'])) {
        $row['completed_at_display'] = toLA($row['completed_at'], 'm/d/Y H:i:s');
        $row['created_at_raw'] = toLA($row['created_at'], 'Y-m-d H:i:s');
        $row['completed_at_raw'] = toLA($row['completed_at'], 'Y-m-d H:i:s');
    }
}
unset($row);

echo json_encode([
    'tickets' => $rows,
    'totalPages' => $totalPages
]);
