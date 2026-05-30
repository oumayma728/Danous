<?php
header("Access-Control-Allow-Origin: http://localhost:8080");
header("Access-Control-Allow-Credentials: true");
header("Content-Type: application/json");
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') { http_response_code(200); exit; }
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/session.php';
if (!isset($_SESSION['user_id'])) { echo json_encode(['success' => false]); exit; }

$user_id = $_SESSION['user_id'];

// Total income & expense
$stmt = $pdo->prepare("SELECT type, COALESCE(SUM(amount),0) AS total FROM transactions WHERE user_id = ? GROUP BY type");
$stmt->execute([$user_id]);
$totals = ['income' => 0, 'expense' => 0];
foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) $totals[$row['type']] = floatval($row['total']);

// Spending by category (pie chart)
$stmt = $pdo->prepare("
    SELECT c.name AS category, COALESCE(SUM(t.amount),0) AS total
    FROM transactions t
    LEFT JOIN categories c ON c.id = t.category_id
    WHERE t.user_id = ? AND t.type = 'expense'
    GROUP BY t.category_id
    ORDER BY total DESC
    LIMIT 8
");
$stmt->execute([$user_id]);
$byCategory = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Spending over time - last 6 months (line chart)
$stmt = $pdo->prepare("
    SELECT 
        DATE_FORMAT(date, '%Y-%m') AS month,
        SUM(CASE WHEN type='income' THEN amount ELSE 0 END) AS income,
        SUM(CASE WHEN type='expense' THEN amount ELSE 0 END) AS expense
    FROM transactions
    WHERE user_id = ? AND date >= DATE_SUB(CURDATE(), INTERVAL 6 MONTH)
    GROUP BY month
    ORDER BY month ASC
");
$stmt->execute([$user_id]);
$overTime = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Budget consumption
$stmt = $pdo->prepare("
    SELECT DISTINCT b.id, b.name, b.cap_amount,
        (SELECT COALESCE(SUM(t.amount),0) FROM transactions t WHERE t.budget_id = b.id AND t.type='expense') AS spent
    FROM budgets b
    LEFT JOIN budget_members bm ON bm.budget_id = b.id
    WHERE b.owner_id = ? OR bm.user_id = ?
    GROUP BY b.id
    ORDER BY b.created_at DESC
    LIMIT 6
");
$stmt->execute([$user_id, $user_id]);
$budgets = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Recent transactions
$stmt = $pdo->prepare("
    SELECT t.*, c.name AS category_name, b.name AS budget_name
    FROM transactions t
    LEFT JOIN categories c ON c.id = t.category_id
    LEFT JOIN budgets b ON b.id = t.budget_id
    WHERE t.user_id = ?
    ORDER BY t.date DESC, t.created_at DESC
    LIMIT 5
");
$stmt->execute([$user_id]);
$recent = $stmt->fetchAll(PDO::FETCH_ASSOC);

echo json_encode([
    'success'     => true,
    'totals'      => $totals,
    'byCategory'  => $byCategory,
    'overTime'    => $overTime,
    'budgets'     => $budgets,
    'recent'      => $recent,
]);