<?php
require_once __DIR__ . '/../config/session.php';
require_once __DIR__ . '/../config/db.php';

header('Content-Type: application/json');

function sendDeleteUserError(string $message, int $statusCode = 400): void
{
    http_response_code($statusCode);
    echo json_encode(['success' => false, 'message' => $message]);
    exit;
}

if (empty($_SESSION['user_id'])) {
    sendDeleteUserError('Non connecté', 401);
}

if (($_SESSION['user_role'] ?? '') !== 'admin') {
    sendDeleteUserError('Rôle administrateur requis', 403);
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    sendDeleteUserError('Méthode non autorisée', 405);
}

$data = json_decode(file_get_contents('php://input'), true) ?: [];
$userId = (int) ($data['user_id'] ?? 0);

if ($userId <= 0) {
    sendDeleteUserError('Utilisateur invalide');
}

if ($userId === (int) $_SESSION['user_id']) {
    sendDeleteUserError('Vous ne pouvez pas supprimer votre propre compte');
}

$stmt = $pdo->prepare("DELETE FROM users WHERE id = ?");
$stmt->execute([$userId]);

echo json_encode(['success' => true, 'message' => 'Utilisateur supprimé']);
