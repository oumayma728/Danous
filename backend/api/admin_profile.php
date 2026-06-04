<?php
require_once __DIR__ . '/auth/api_guard.php';
require_once __DIR__ . '/../config/db.php';

header('Content-Type: application/json');
requireApiAdmin();

function sendAdminProfileError(string $message, int $statusCode = 400): void
{
    http_response_code($statusCode);
    echo json_encode(['success' => false, 'message' => $message]);
    exit;
}

$adminId = (int) $_SESSION['user_id'];

try {
    if ($_SERVER['REQUEST_METHOD'] === 'GET') {
        $stmt = $pdo->prepare("SELECT id, name, email FROM users WHERE id = ?");
        $stmt->execute([$adminId]);
        echo json_encode(['success' => true, 'profile' => $stmt->fetch(PDO::FETCH_ASSOC)]);
        exit;
    }

    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        sendAdminProfileError('Methode non autorisee', 405);
    }

    $data = json_decode(file_get_contents('php://input'), true) ?: [];
    $name = trim($data['name'] ?? '');
    $email = trim($data['email'] ?? '');
    $currentPassword = $data['current_password'] ?? '';
    $newPassword = $data['new_password'] ?? '';
    $confirmPassword = $data['confirm_password'] ?? '';

    if ($name === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        sendAdminProfileError('Nom ou e-mail invalide');
    }

    $stmt = $pdo->prepare("SELECT id FROM users WHERE id <> ? AND (LOWER(name) = LOWER(?) OR LOWER(email) = LOWER(?))");
    $stmt->execute([$adminId, $name, $email]);
    if ($stmt->fetch()) {
        sendAdminProfileError("Nom ou e-mail deja utilise");
    }

    if ($newPassword !== '') {
        if ($newPassword !== $confirmPassword) {
            sendAdminProfileError('Les mots de passe ne correspondent pas');
        }
        if (strlen($newPassword) < 6) {
            sendAdminProfileError('Le nouveau mot de passe doit contenir au moins 6 caracteres');
        }

        $stmt = $pdo->prepare("SELECT password_hash FROM users WHERE id = ?");
        $stmt->execute([$adminId]);
        $admin = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$admin || !password_verify($currentPassword, $admin['password_hash'])) {
            sendAdminProfileError('Mot de passe actuel incorrect');
        }

        $hash = password_hash($newPassword, PASSWORD_BCRYPT);
        $stmt = $pdo->prepare("UPDATE users SET name = ?, email = ?, password_hash = ? WHERE id = ?");
        $stmt->execute([$name, $email, $hash, $adminId]);
    } else {
        $stmt = $pdo->prepare("UPDATE users SET name = ?, email = ? WHERE id = ?");
        $stmt->execute([$name, $email, $adminId]);
    }

    $_SESSION['user_name'] = $name;

    echo json_encode([
        'success' => true,
        'message' => 'Profil administrateur modifie',
        'profile' => ['id' => $adminId, 'name' => $name, 'email' => $email]
    ]);
} catch (PDOException $e) {
    sendAdminProfileError('Erreur de base de donnees : ' . $e->getMessage(), 500);
}
