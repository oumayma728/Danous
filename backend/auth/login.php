<?php

ini_set('display_errors', 1);
error_reporting(E_ALL);

header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: POST, GET, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type");
header("Content-Type: application/json");

require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/session.php';

// Debug: Log what we received
file_put_contents(__DIR__ . '/login_debug.log', date('Y-m-d H:i:s') . " - Request Method: " . $_SERVER['REQUEST_METHOD'] . "\n", FILE_APPEND);
file_put_contents(__DIR__ . '/login_debug.log', date('Y-m-d H:i:s') . " - POST data: " . print_r($_POST, true) . "\n", FILE_APPEND);
file_put_contents(__DIR__ . '/login_debug.log', date('Y-m-d H:i:s') . " - RAW input: " . file_get_contents('php://input') . "\n", FILE_APPEND);

if (!isset($pdo)) {
    http_response_code(500);
    echo json_encode([
        "success" => false,
        "message" => "Database not connected"
    ]);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method Not Allowed']);
    exit;
}

$email = trim($_POST['email'] ?? '');
$password = $_POST['password'] ?? '';

// If no POST data, try to get from JSON input
if (empty($email) && empty($password)) {
    $json = file_get_contents('php://input');
    $data = json_decode($json, true);
    if ($data) {
        $email = trim($data['email'] ?? '');
        $password = $data['password'] ?? '';
        file_put_contents(__DIR__ . '/login_debug.log', date('Y-m-d H:i:s') . " - Using JSON data: email=" . $email . "\n", FILE_APPEND);
    }
}

if (!$email || !$password) {
    echo json_encode([
        'success' => false, 
        'message' => 'Email and password are required',
        'debug' => [
            'email_provided' => !empty($email),
            'password_provided' => !empty($password),
            'post_data' => $_POST,
            'request_method' => $_SERVER['REQUEST_METHOD']
        ]
    ]);
    exit;
}

try {
    $stmt = $pdo->prepare("SELECT * FROM users WHERE email = ?");
    $stmt->execute([$email]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
    
    file_put_contents(__DIR__ . '/login_debug.log', date('Y-m-d H:i:s') . " - User found: " . ($user ? 'Yes' : 'No') . "\n", FILE_APPEND);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        "success" => false,
        "message" => "Database query error",
        "debug" => $e->getMessage()
    ]);
    exit;
}

if (!$user || !password_verify($password, $user['password_hash'])) {
    echo json_encode(['success' => false, 'message' => 'Incorrect email or password']);
    exit;
}

if ($user['status'] === 'pending') {
    echo json_encode(['success' => false, 'message' => 'Your account is pending admin approval']);
    exit;
}

if ($user['status'] === 'disabled') {
    echo json_encode(['success' => false, 'message' => 'Your account has been disabled']);
    exit;
}

$_SESSION['user_id'] = $user['id'];
$_SESSION['user_name'] = $user['name'];
$_SESSION['user_role'] = $user['role'];

$redirect = ($user['role'] === 'admin')
    ? '../../frontend/pages/admin.html'
    : '../../frontend/pages/dashboard.html';

echo json_encode([
    "success" => true,
    "redirect" => $redirect,
    "user" => [
        "name" => $user['name'],
        "role" => $user['role']
    ]
]);