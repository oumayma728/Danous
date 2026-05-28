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

$user_id = $_SESSION['user_id'];
$id      = intval($_POST['id'] ?? 0);

if (!$id) { echo json_encode(['success' => false, 'message' => 'Invalid ID']); exit; }

$stmt = $pdo->prepare("DELETE FROM transactions WHERE id = ? AND user_id = ?");
$stmt->execute([$id, $user_id]);

echo json_encode($stmt->rowCount() > 0
    ? ['success' => true]
    : ['success' => false, 'message' => 'Not found or not authorized']);