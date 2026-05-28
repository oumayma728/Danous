<?php
header("Access-Control-Allow-Origin: http://localhost:8080");
header("Access-Control-Allow-Credentials: true");
header("Access-Control-Allow-Methods: GET, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type");
header("Content-Type: application/json");

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') { http_response_code(200); exit; }

require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/session.php';

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Not authenticated']); exit;
}

$user_id = $_SESSION['user_id'];

try {
    // Get budgets the user owns or is a member of, with total spent
    $stmt = $pdo->prepare("
        SELECT DISTINCT
            b.*,
            (SELECT COALESCE(SUM(t.amount), 0)
             FROM transactions t
             WHERE t.budget_id = b.id AND t.type = 'expense') AS spent
        FROM budgets b
        LEFT JOIN budget_members bm ON bm.budget_id = b.id
        WHERE b.owner_id = ? OR bm.user_id = ?
        GROUP BY b.id
        ORDER BY b.created_at DESC
    ");
    $stmt->execute([$user_id, $user_id]);
    $budgets = $stmt->fetchAll(PDO::FETCH_ASSOC);
    echo json_encode($budgets);

} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}