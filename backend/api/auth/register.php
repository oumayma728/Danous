<?php
require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../config/session.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Méthode non autorisée']);
    exit;
}

$name = trim($_POST['name'] ?? '');
$email = trim($_POST['email'] ?? '');
$password = $_POST['password'] ?? '';
$confirm_password = $_POST['confirm_password'] ?? '';

if (!$name || !$email || !$password || !$confirm_password) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Tous les champs sont requis']);
    exit;
}

if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => "Format d'e-mail invalide"]);
    exit;
}

if ($password !== $confirm_password) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Les mots de passe ne correspondent pas']);
    exit;
}

// check if email or name already exists
$stmt = $pdo->prepare("SELECT id FROM users WHERE email = ? OR name = ?");
$stmt->execute([$email, $name]);

if ($stmt->fetch()) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => "L'e-mail ou le nom d'utilisateur existe déjà"]);
    exit;
}

$hash = password_hash($password, PASSWORD_BCRYPT);
$stmt = $pdo->prepare("INSERT INTO users (name, email, password_hash, role, status) VALUES (?, ?, ?, 'user', 'pending')");
$stmt->execute([$name, $email, $hash]);

echo json_encode([
    'success' => true,
    'message' => "Inscription réussie. En attente d'approbation par l'administrateur."
]);
