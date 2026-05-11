<?php
require_once '../config/db.php';
require_once '../config/session.php';
header('Content-Type: application/json');
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Method Not Allowed']);
    exit;
}
$name = trim($_POST['name'] ?? '');
$email = trim($_POST['email'] ?? '');
$password = $_POST['password'] ?? '';
$confirm_password = $_POST['confirm_password'] ?? '';

//validation 
if (!$name || !$email || !$password || !$confirm_password) {
    http_response_code(400);
    echo json_encode(['error' => 'All fields are required']);
    exit;
}
if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid email format']);
    exit;
}
if ($password !== $confirm_password) {
    http_response_code(400);
    echo json_encode(['error' => 'Passwords do not match']);
    exit;
}
// check if email or name already exists
$stmt = $pdo -> prepare("SELECT id FROM users WHERE email=? OR name=?");
$stmt -> execute([$email, $name]);
if ($stmt -> fetch()) {
    http_response_code(400);
    echo json_encode(['error' => 'Email or username already exists']);
    exit;
}
//Insert new user
$hash = password_hash($password, PASSWORD_BCRYPT);
$stmt = $pdo->prepare("INSERT INTO users (name, email, password_hash, role, status) VALUES (?, ?, ?, 'user', 'pending')");
$stmt->execute([$name, $email, $hash]);
echo json_encode(['success' => 'Registration successful. Awaiting admin approval.']);