<?php
header("Access-Control-Allow-Origin: http://localhost:8080");
header("Access-Control-Allow-Credentials: true");
header("Content-Type: application/json");

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') { http_response_code(200); exit; }

require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/session.php';

if (!isset($_SESSION['user_id'])) {
    echo json_encode([]); exit;
}

$user_id     = $_SESSION['user_id'];
$type        = $_GET['type'] ?? '';
$category_id = intval($_GET['category_id'] ?? 0);
$budget_id   = intval($_GET['budget_id'] ?? 0);
$start       = $_GET['start'] ?? '';
$end         = $_GET['end'] ?? '';

if ($budget_id) {
    $where = ['t.budget_id = ?'];
    $params = [$budget_id];
} else {
    $where = ['t.user_id = ?'];
    $params = [$user_id];
}

if ($type)        { $where[] = 't.type = ?';        $params[] = $type; }
if ($category_id) { $where[] = 't.category_id = ?'; $params[] = $category_id; }
if ($start)       { $where[] = 't.date >= ?';        $params[] = $start; }
if ($end)         { $where[] = 't.date <= ?';        $params[] = $end; }

$sql = "
    SELECT t.*, c.name AS category_name, b.name AS budget_name, u.name AS user_name
    FROM transactions t
    LEFT JOIN categories c ON c.id = t.category_id
    LEFT JOIN budgets b ON b.id = t.budget_id
    LEFT JOIN users u ON u.id = t.user_id
    WHERE " . implode(' AND ', $where) . "
    ORDER BY t.date DESC, t.created_at DESC
    LIMIT 200
";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
echo json_encode($stmt->fetchAll(PDO::FETCH_ASSOC));