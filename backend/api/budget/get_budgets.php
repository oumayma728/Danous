<?php
require_once __DIR__ . '/../auth/api_guard.php';
header('Content-Type: application/json');
requireApiUser();

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Non authentifié']);
    exit;
}

$user_id = $_SESSION['user_id'];
require_once __DIR__ . '/../../config/db.php';

try {
    // Budgets où l'utilisateur est owner OU membre
    $stmt = $pdo->prepare("
        SELECT DISTINCT 
            b.*,
            CASE 
                WHEN b.owner_id = ? THEN 'owner'
                WHEN bm.user_id = ? THEN 'member'
                ELSE NULL
            END as user_role,
            (SELECT COALESCE(SUM(amount), 0) FROM transactions 
             WHERE budget_id = b.id AND type = 'expense') as total_spent
        FROM budgets b
        LEFT JOIN budget_members bm ON b.id = bm.budget_id
        WHERE b.owner_id = ? OR bm.user_id = ?
        ORDER BY b.created_at DESC
    ");
    $stmt->execute([$user_id, $user_id, $user_id, $user_id]);
    $budgets = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Pour chaque budget, récupérer les plafonds par catégorie
    foreach ($budgets as &$budget) {
        $stmt = $pdo->prepare("
            SELECT c.name, bcc.cap_amount
            FROM budget_category_caps bcc
            JOIN categories c ON bcc.category_id = c.id
            WHERE bcc.budget_id = ?
        ");
        $stmt->execute([$budget['id']]);
        $budget['category_caps'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    echo json_encode(['success' => true, 'budgets' => $budgets]);
} catch (PDOException $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?>
