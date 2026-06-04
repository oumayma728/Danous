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

if (!$budget_id) {
    echo json_encode(['success' => false, 'message' => 'ID requis']);
    exit;
}

require_once __DIR__ . '/../../config/db.php';

try {
    // Vérifier que l'utilisateur est owner
    $stmt = $pdo->prepare("SELECT id FROM budgets WHERE id = ? AND owner_id = ?");
    $stmt->execute([$budget_id, $user_id]);
    if (!$stmt->fetch()) {
        echo json_encode(['success' => false, 'message' => 'Non autorisé']);
        exit;
    }
    
    // Supprimer (les tables liées sont en CASCADE)
    $stmt = $pdo->prepare("DELETE FROM budgets WHERE id = ?");
    $stmt->execute([$budget_id]);
    
    echo json_encode(['success' => true, 'message' => 'Budget supprimé']);
} catch (PDOException $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?>
