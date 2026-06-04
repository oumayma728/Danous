<?php
require_once __DIR__ . '/auth/api_guard.php';
require_once __DIR__ . '/../config/db.php';

header('Content-Type: application/json');
requireApiAdmin();

function sendDeletionRequestError(string $message, int $statusCode = 400): void
{
    http_response_code($statusCode);
    echo json_encode(['success' => false, 'message' => $message]);
    exit;
}

function ensureDeletionRequestsTable(PDO $pdo): void
{
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS deletion_requests (
            id INT AUTO_INCREMENT PRIMARY KEY,
            user_id INT NOT NULL,
            reason VARCHAR(255) DEFAULT NULL,
            status ENUM('pending', 'approved', 'rejected') DEFAULT 'pending',
            requested_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            reviewed_at DATETIME DEFAULT NULL,
            reviewed_by INT DEFAULT NULL,
            FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
            FOREIGN KEY (reviewed_by) REFERENCES users(id) ON DELETE SET NULL
        )
    ");
}

try {
    ensureDeletionRequestsTable($pdo);

    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        sendDeletionRequestError('Methode non autorisee', 405);
    }

    $data = json_decode(file_get_contents('php://input'), true) ?: [];
    $requestId = (int) ($data['request_id'] ?? 0);
    $action = $data['action'] ?? '';

    if ($requestId <= 0 || !in_array($action, ['approve', 'reject'], true)) {
        sendDeletionRequestError('Demande ou action invalide');
    }

    $stmt = $pdo->prepare("
        SELECT dr.id, dr.user_id, u.name
        FROM deletion_requests dr
        JOIN users u ON u.id = dr.user_id
        WHERE dr.id = ? AND dr.status = 'pending'
    ");
    $stmt->execute([$requestId]);
    $request = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$request) {
        sendDeletionRequestError('Demande introuvable');
    }

    if ((int) $request['user_id'] === (int) $_SESSION['user_id']) {
        sendDeletionRequestError('Vous ne pouvez pas supprimer votre propre compte');
    }

    if ($action === 'approve') {
        $pdo->beginTransaction();
        $stmt = $pdo->prepare("UPDATE deletion_requests SET status = 'approved', reviewed_at = NOW(), reviewed_by = ? WHERE id = ?");
        $stmt->execute([$_SESSION['user_id'], $requestId]);
        $stmt = $pdo->prepare("DELETE FROM users WHERE id = ?");
        $stmt->execute([$request['user_id']]);
        $pdo->commit();
        echo json_encode(['success' => true, 'message' => 'Demande approuvee et compte supprime']);
        exit;
    }

    $stmt = $pdo->prepare("UPDATE deletion_requests SET status = 'rejected', reviewed_at = NOW(), reviewed_by = ? WHERE id = ?");
    $stmt->execute([$_SESSION['user_id'], $requestId]);
    echo json_encode(['success' => true, 'message' => 'Demande rejetee']);
} catch (PDOException $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    sendDeletionRequestError('Erreur de base de donnees : ' . $e->getMessage(), 500);
}
