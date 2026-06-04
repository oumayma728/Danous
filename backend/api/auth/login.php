<?php

ini_set('display_errors', 1);
error_reporting(E_ALL);

header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: POST, GET, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type");
header("Content-Type: application/json");

require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../config/session.php';

function ensureLastLoginColumn(PDO $pdo): void
{
    $stmt = $pdo->query("SHOW COLUMNS FROM users LIKE 'last_login_at'");
    if (!$stmt->fetch(PDO::FETCH_ASSOC)) {
        $pdo->exec("ALTER TABLE users ADD COLUMN last_login_at DATETIME DEFAULT NULL");
    }
}

$rawInput = file_get_contents('php://input');

if (!isset($pdo)) {
    http_response_code(500);
    echo json_encode([
        "success" => false,
        "message" => "Base de données non connectée"
    ]);
    exit;
}

$email = trim($_POST['email'] ?? '');
$password = $_POST['password'] ?? '';

if ((!$email || !$password) && $rawInput !== '') {
    $data = json_decode($rawInput, true);
    if (is_array($data)) {
        $email = trim($data['email'] ?? '');
        $password = $data['password'] ?? '';
    }
}

error_log(sprintf(
    '[login] method=%s email=%s payload=%s',
    $_SERVER['REQUEST_METHOD'],
    $email !== '' ? $email : '(missing)',
    $_POST ? 'form' : ($rawInput !== '' ? 'json/raw' : 'empty')
));

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Méthode non autorisée']);
    exit;
}

if (!$email || !$password) {
    echo json_encode([
        'success' => false, 
        'message' => 'E-mail et mot de passe requis'
    ]);
    exit;
}

try {
    $stmt = $pdo->prepare("SELECT * FROM users WHERE email = ?");
    $stmt->execute([$email]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        "success" => false,
        "message" => "Erreur de requête de base de données",
        "debug" => $e->getMessage()
    ]);
    exit;
}

if (!$user || !password_verify($password, $user['password_hash'])) {
    echo json_encode(['success' => false, 'message' => 'E-mail ou mot de passe incorrect']);
    exit;
}

if ($user['status'] === 'pending') {
    echo json_encode(['success' => false, 'message' => "Votre compte est en attente d'approbation par l'administrateur"]);
    exit;
}

if ($user['status'] === 'disabled') {
    echo json_encode(['success' => false, 'message' => 'Votre compte a été désactivé']);
    exit;
}

$_SESSION['user_id'] = $user['id'];
$_SESSION['user_name'] = $user['name'];
$_SESSION['user_role'] = $user['role'];

try {
    ensureLastLoginColumn($pdo);
    $stmt = $pdo->prepare("UPDATE users SET last_login_at = NOW() WHERE id = ?");
    $stmt->execute([$user['id']]);
} catch (PDOException $e) {
    error_log('[login] last_login_at update failed: ' . $e->getMessage());
}

$redirect = ($user['role'] === 'admin')
    ? 'admin.html'
    : 'dashboard.html';

echo json_encode([
    "success" => true,
    "redirect" => $redirect,
    "user" => [
        "id" => $user['id'],
        "name" => $user['name'],
        "role" => $user['role']
    ]
]);
