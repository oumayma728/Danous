<?php
header("Access-Control-Allow-Origin: http://localhost:8080");
header("Access-Control-Allow-Credentials: true");
header("Access-Control-Allow-Methods: POST, OPTIONS");
header("Content-Type: application/json");

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') { http_response_code(200); exit; }

require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/session.php';

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Not authenticated']); exit;
}

$user_id   = $_SESSION['user_id'];
$budget_id = intval($_POST['budget_id'] ?? 0);

if (!$budget_id) {
    echo json_encode(['success' => false, 'message' => 'Invalid budget ID']); exit;
}

// Only owner can delete
$stmt = $pdo->prepare("DELETE FROM budgets WHERE id = ? AND owner_id = ?");
$stmt->execute([$budget_id, $user_id]);

if ($stmt->rowCount() > 0) {
    echo json_encode(['success' => true]);
} else {
    echo json_encode(['success' => false, 'message' => 'Budget not found or not authorized']);
}