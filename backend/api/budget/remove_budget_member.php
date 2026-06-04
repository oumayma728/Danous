<?php
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
$member_id = $data['member_id'] ?? null;

if (!$budget_id || !$member_id) {
    echo json_encode(['success' => false, 'message' => 'Données invalides']);
    exit;
}

require_once __DIR__ . '/../../config/db.php';

try {
    // Vérifier que l'utilisateur est owner
    $stmt = $pdo->prepare("
        SELECT id FROM budgets 
        WHERE id = ? AND owner_id = ?
    ");
    $stmt->execute([$budget_id, $user_id]);
    if (!$stmt->fetch()) {
        echo json_encode(['success' => false, 'message' => 'Non autorisé']);
        exit;
    }
    
    // Ne pas permettre de retirer le owner
    $stmt = $pdo->prepare("
        SELECT id FROM budgets WHERE id = ? AND owner_id = ?
    ");
    $stmt->execute([$budget_id, $member_id]);
    if ($stmt->fetch()) {
        echo json_encode(['success' => false, 'message' => 'Vous ne pouvez pas retirer le propriétaire']);
        exit;
    }
    
    // Retirer le membre
    $stmt = $pdo->prepare("
        DELETE FROM budget_members 
        WHERE budget_id = ? AND user_id = ?
    ");
    $stmt->execute([$budget_id, $member_id]);
    
    echo json_encode(['success' => true, 'message' => 'Membre retiré']);
} catch (PDOException $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?>
