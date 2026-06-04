<?php
// Plafonds par catégorie
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
    
    // Récupérer les plafonds par catégorie
    $stmt = $pdo->prepare("
        SELECT c.id as category_id, c.name, c.is_default,
               COALESCE(bcc.cap_amount, 0) as cap_amount,
               COALESCE(SUM(t.amount), 0) as spent
        FROM categories c
        LEFT JOIN budget_category_caps bcc ON bcc.budget_id = ? AND bcc.category_id = c.id
        LEFT JOIN transactions t ON t.category_id = c.id AND t.budget_id = ? AND t.type = 'expense'
        WHERE c.is_default = 1 OR c.user_id = ?
        GROUP BY c.id
        HAVING cap_amount > 0 OR spent > 0
        ORDER BY c.name ASC
    ");
    $stmt->execute([$budget_id, $budget_id, $user_id]);
    $caps = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo json_encode(['success' => true, 'category_caps' => $caps]);
} catch (PDOException $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?>
