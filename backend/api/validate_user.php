<?php
require_once __DIR__ . '/../config/session.php';
require_once __DIR__ . '/../config/db.php';

header('Content-Type: application/json');

function sendValidateUserError(string $message, int $statusCode = 400): void
{
    http_response_code($statusCode);
    echo json_encode(['success' => false, 'message' => $message]);
    exit;
}

if (empty($_SESSION['user_id'])) {
    sendValidateUserError('Non connecté', 401);
}

if (($_SESSION['user_role'] ?? '') !== 'admin') {
    sendValidateUserError('Rôle administrateur requis', 403);
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    sendValidateUserError('Méthode non autorisée', 405);
}

$data = json_decode(file_get_contents('php://input'), true) ?: [];
$userId = (int) ($data['user_id'] ?? 0);
$action = $data['action'] ?? '';

if ($userId <= 0 || !in_array($action, ['validate', 'reject'], true)) {
    sendValidateUserError('Utilisateur ou action invalide');
}

$status = $action === 'validate' ? 'active' : 'disabled';
$stmt = $pdo->prepare("UPDATE users SET status = ? WHERE id = ?");
$stmt->execute([$status, $userId]);

echo json_encode([
    'success' => true,
    'message' => $action === 'validate' ? 'Utilisateur validé' : 'Utilisateur rejeté'
]);
