<?php
header("Access-Control-Allow-Origin: http://localhost:8080");
header("Access-Control-Allow-Credentials: true");
header("Access-Control-Allow-Methods: POST, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type");
header("Content-Type: application/json");

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') { http_response_code(200); exit; }

require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/session.php';

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Not authenticated']); exit;
}

$user_id     = $_SESSION['user_id'];
$type        = $_POST['type'] ?? '';
$amount      = floatval($_POST['amount'] ?? 0);
$date        = $_POST['date'] ?? '';
$description = trim($_POST['description'] ?? '');
$category_id = intval($_POST['category_id'] ?? 0) ?: null;
$budget_id   = intval($_POST['budget_id'] ?? 0) ?: null;

if (!in_array($type, ['income', 'expense']) || $amount <= 0 || !$date) {
    echo json_encode(['success' => false, 'message' => 'Type, amount and date are required']); exit;
}

try {
    $stmt = $pdo->prepare("
        INSERT INTO transactions (user_id, budget_id, category_id, type, amount, date, description)
        VALUES (?, ?, ?, ?, ?, ?, ?)
    ");
    $stmt->execute([$user_id, $budget_id, $category_id, $type, $amount, $date, $description]);
    $id = $pdo->lastInsertId();

    // Check budget alert if linked to a budget
    if ($budget_id && $type === 'expense') {
        $stmt = $pdo->prepare("
            SELECT b.cap_amount, COALESCE(SUM(t.amount), 0) AS spent
            FROM budgets b
            LEFT JOIN transactions t ON t.budget_id = b.id AND t.type = 'expense'
            WHERE b.id = ?
            GROUP BY b.id
        ");
        $stmt->execute([$budget_id]);
        $budget = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($budget) {
            $pct = ($budget['spent'] / $budget['cap_amount']) * 100;
            $status = $pct >= 100 ? 'exceeded' : ($pct >= 80 ? 'warning' : 'ok');
            $triggered = ($status !== 'ok') ? date('Y-m-d H:i:s') : null;
            $pdo->prepare("UPDATE alerts SET status = ?, triggered_at = ? WHERE budget_id = ?")
                ->execute([$status, $triggered, $budget_id]);
        }
    }

    echo json_encode(['success' => true, 'id' => $id]);
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}