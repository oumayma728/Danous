<?php
#load config and db connection
require_once '../config/db.php';
require_once '../config/session.php';
#file will return json
header('Content-Type: application/json');
#allow only POST requests
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Method Not Allowed']);
    exit;
}
#trim email and get password
$email = trim($_POST['email'] ?? '');
$password = $_POST['password'] ?? '';
//validation
if (!$email || !$password) {
    echo json_encode(['error' => 'Email and password are required']);
    exit;
}

$stmt = $pdo->prepare("SELECT * FROM users WHERE email = ?");
$stmt->execute([$email]);
#get user data from database
$user = $stmt->fetch();

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
// Start session
$_SESSION['user_id']   = $user['id'];
$_SESSION['user_name'] = $user['name'];
$_SESSION['user_role'] = $user['role'];

$redirect = $user['role'] === 'admin' ? '../../frontend/pages/admin.html' : '../../frontend/pages/dashboard.html';

echo json_encode(['success' => true, 'redirect' => $redirect]);