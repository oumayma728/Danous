<?php
require_once __DIR__ . '/../auth/api_guard.php';
header('Content-Type: application/json');
requireApiUser();

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Non authentifié']);
    exit;
}

$user_id = $_SESSION['user_id'];
$budget_id = $_GET['budget_id'] ?? null;

if (!$budget_id) {
    echo json_encode(['success' => false, 'message' => 'ID du budget requis']);
    exit;
}

require_once __DIR__ . '/../../config/db.php';

try {
    // Vérifier l'accès
    $stmt = $pdo->prepare("
        SELECT id FROM budgets b
        WHERE b.id = ? AND (b.owner_id = ? OR EXISTS (
            SELECT 1 FROM budget_members WHERE budget_id = b.id AND user_id = ?
        ))
    ");
    $stmt->execute([$budget_id, $user_id, $user_id]);
    if (!$stmt->fetch()) {
        echo json_encode(['success' => false, 'message' => 'Non autorisé']);
        exit;
    }
    
    // Récupérer les membres
    $stmt = $pdo->prepare("
        SELECT u.id, u.name, u.email, bm.role, bm.joined_at
        FROM budget_members bm
        JOIN users u ON bm.user_id = u.id
        WHERE bm.budget_id = ?
        ORDER BY bm.role DESC, bm.joined_at ASC
    ");
    $stmt->execute([$budget_id]);
    $members = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo json_encode(['success' => true, 'members' => $members]);
} catch (PDOException $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?>
