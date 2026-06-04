<?php
require_once __DIR__ . '/auth/api_guard.php';
require_once __DIR__ . '/../config/db.php';

header('Content-Type: application/json');
requireApiUser();

function sendUserProfileError(string $message, int $statusCode = 400): void
{
    http_response_code($statusCode);
    echo json_encode(['success' => false, 'message' => $message]);
    exit;
}

function ensureUserDeletionRequestsTable(PDO $pdo): void
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

$userId = (int) $_SESSION['user_id'];

try {
    ensureUserDeletionRequestsTable($pdo);

    if ($_SERVER['REQUEST_METHOD'] === 'GET') {
        $stmt = $pdo->prepare("
            SELECT id, name, email, status, DATE_FORMAT(created_at, '%Y-%m-%d') AS created_at
            FROM users
            WHERE id = ?
        ");
        $stmt->execute([$userId]);
        $profile = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$profile) {
            sendUserProfileError('Profil introuvable', 404);
        }

        $stmt = $pdo->prepare("
            SELECT id, reason, status,
                   DATE_FORMAT(requested_at, '%Y-%m-%d %H:%i') AS requested_at,
                   DATE_FORMAT(reviewed_at, '%Y-%m-%d %H:%i') AS reviewed_at
            FROM deletion_requests
            WHERE user_id = ?
            ORDER BY requested_at DESC
            LIMIT 1
        ");
        $stmt->execute([$userId]);
        $deletionRequest = $stmt->fetch(PDO::FETCH_ASSOC) ?: null;

        echo json_encode([
            'success' => true,
            'profile' => $profile,
            'deletion_request' => $deletionRequest
        ]);
        exit;
    }

    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        sendUserProfileError('Methode non autorisee', 405);
    }

    $data = json_decode(file_get_contents('php://input'), true) ?: [];
    $action = $data['action'] ?? 'update_profile';

    if ($action === 'request_deletion') {
        $reason = trim($data['reason'] ?? '');
        if (strlen($reason) > 255) {
            $reason = substr($reason, 0, 255);
        }

        $stmt = $pdo->prepare("
            SELECT id
            FROM deletion_requests
            WHERE user_id = ? AND status = 'pending'
            LIMIT 1
        ");
        $stmt->execute([$userId]);
        if ($stmt->fetch()) {
            sendUserProfileError('Une demande de suppression est deja en attente');
        }

        $stmt = $pdo->prepare("
            INSERT INTO deletion_requests (user_id, reason, status)
            VALUES (?, ?, 'pending')
        ");
        $stmt->execute([$userId, $reason !== '' ? $reason : null]);

        echo json_encode([
            'success' => true,
            'message' => 'Demande de suppression envoyee a l administrateur'
        ]);
        exit;
    }

    if ($action !== 'update_profile') {
        sendUserProfileError('Action invalide');
    }

    $name = trim($data['name'] ?? '');
    $email = trim($data['email'] ?? '');
    $currentPassword = $data['current_password'] ?? '';
    $newPassword = $data['new_password'] ?? '';
    $confirmPassword = $data['confirm_password'] ?? '';

    if ($name === '' || strlen($name) > 100 || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        sendUserProfileError('Nom ou e-mail invalide');
    }

    $stmt = $pdo->prepare("
        SELECT id
        FROM users
        WHERE id <> ? AND (LOWER(name) = LOWER(?) OR LOWER(email) = LOWER(?))
        LIMIT 1
    ");
    $stmt->execute([$userId, $name, $email]);
    if ($stmt->fetch()) {
        sendUserProfileError('Nom ou e-mail deja utilise');
    }

    if ($newPassword !== '') {
        if ($newPassword !== $confirmPassword) {
            sendUserProfileError('Les mots de passe ne correspondent pas');
        }
        if (strlen($newPassword) < 6) {
            sendUserProfileError('Le nouveau mot de passe doit contenir au moins 6 caracteres');
        }

        $stmt = $pdo->prepare("SELECT password_hash FROM users WHERE id = ?");
        $stmt->execute([$userId]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$user || !password_verify($currentPassword, $user['password_hash'])) {
            sendUserProfileError('Mot de passe actuel incorrect');
        }

        $hash = password_hash($newPassword, PASSWORD_BCRYPT);
        $stmt = $pdo->prepare("UPDATE users SET name = ?, email = ?, password_hash = ? WHERE id = ?");
        $stmt->execute([$name, $email, $hash, $userId]);
    } else {
        $stmt = $pdo->prepare("UPDATE users SET name = ?, email = ? WHERE id = ?");
        $stmt->execute([$name, $email, $userId]);
    }

    $_SESSION['user_name'] = $name;

    echo json_encode([
        'success' => true,
        'message' => 'Profil modifie',
        'profile' => ['id' => $userId, 'name' => $name, 'email' => $email]
    ]);
} catch (PDOException $e) {
    sendUserProfileError('Erreur de base de donnees : ' . $e->getMessage(), 500);
}
