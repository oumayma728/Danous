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

$stmt = $pdo->query("SELECT id, name, email, status FROM users ORDER BY name ASC");
echo json_encode($stmt->fetchAll(PDO::FETCH_ASSOC));