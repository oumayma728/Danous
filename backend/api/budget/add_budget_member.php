<?php
//Inviter quelqu'un à un budget partagé
require_once __DIR__ . '/../auth/api_guard.php';
header('Content-Type: application/json');
requireApiUser();

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Non authentifié']);
    exit;
}

$user_id = $_SESSION['user_id'];
$data = json_decode(file_get_contents('php://input'), true);

$budget_id = $data['budget_id'] ?? null;
$member_email = $data['email'] ?? null;

if (!$budget_id || !$member_email) {
    echo json_encode(['success' => false, 'message' => 'Données invalides']);
    exit;
}

require_once __DIR__ . '/../../config/db.php';

try {
    // Vérifier que l'utilisateur est owner du budget
    $stmt = $pdo->prepare("SELECT id FROM budgets WHERE id = ? AND owner_id = ? AND is_shared = 1");
    $stmt->execute([$budget_id, $user_id]);
    if (!$stmt->fetch()) {
        echo json_encode(['success' => false, 'message' => 'Non autorisé']);
        exit;
    }
    
    // Trouver l'utilisateur par email
    $stmt = $pdo->prepare("SELECT id, name FROM users WHERE email = ? AND status = 'active'");
    $stmt->execute([$member_email]);
    $member = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$member) {
        echo json_encode(['success' => false, 'message' => 'Utilisateur non trouvé']);
        exit;
    }
    
    // Ajouter le membre
    $stmt = $pdo->prepare("
        INSERT INTO budget_members (budget_id, user_id, role)
        VALUES (?, ?, 'member')
    ");
    $stmt->execute([$budget_id, $member['id']]);
    
    echo json_encode(['success' => true, 'message' => $member['name'] . ' a été ajouté']);
} catch (PDOException $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?>
