<?php
require_once __DIR__ . '/../config/session.php';
require_once __DIR__ . '/../config/db.php';

header('Content-Type: application/json');

function sendChangeRoleError(string $message, int $statusCode = 400): void
{
    http_response_code($statusCode);
    echo json_encode(['success' => false, 'message' => $message]);
    exit;
}

if (empty($_SESSION['user_id'])) {
    sendChangeRoleError('Non connecté', 401);
}

if (($_SESSION['user_role'] ?? '') !== 'admin') {
    sendChangeRoleError('Rôle administrateur requis', 403);
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    sendChangeRoleError('Méthode non autorisée', 405);
}

$data = json_decode(file_get_contents('php://input'), true) ?: [];
$userId = (int) ($data['user_id'] ?? 0);
$role = $data['role'] ?? '';

if ($userId <= 0 || !in_array($role, ['admin', 'user'], true)) {
    sendChangeRoleError('Utilisateur ou rôle invalide');
}

if ($userId === (int) $_SESSION['user_id']) {
    sendChangeRoleError('Vous ne pouvez pas modifier votre propre rôle');
}

$stmt = $pdo->prepare("UPDATE users SET role = ? WHERE id = ?");
$stmt->execute([$role, $userId]);

echo json_encode(['success' => true, 'message' => 'Rôle modifié']);
