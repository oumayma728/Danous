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
    // Récupérer le budget et vérifier l'accès utilisateur.
    $stmt = $pdo->prepare("
        SELECT b.*,
               u.name AS owner_name,
               (SELECT COALESCE(SUM(amount), 0) FROM transactions 
                WHERE budget_id = b.id AND type = 'expense' 
                AND date BETWEEN b.start_date AND b.end_date) as total_spent
        FROM budgets b
        JOIN users u ON u.id = b.owner_id
        WHERE b.id = ? AND (b.owner_id = ? OR EXISTS (
            SELECT 1 FROM budget_members WHERE budget_id = b.id AND user_id = ?
        ))
        LIMIT 1
    ");
    $stmt->execute([$budget_id, $user_id, $user_id]);
    $budget = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$budget) {
        echo json_encode(['success' => false, 'message' => 'Budget non trouvé']);
        exit;
    }
    
    $total_spent = (float) $budget['total_spent'];
    $cap_amount = (float) $budget['cap_amount'];
    $remaining = $cap_amount - $total_spent;
    $percent_used = ($cap_amount > 0)
        ? ($total_spent / $cap_amount) * 100
        : 0;
    
    // Déterminer le statut
    $status = 'ok';
    if ($percent_used >= 100) {
        $status = 'exceeded';
    } elseif ($percent_used >= 80) {
        $status = 'warning';
    }
    
    // Mettre à jour l'alerte si nécessaire
    $stmt = $pdo->prepare("
        UPDATE alerts 
        SET status = ?, triggered_at = IF(? != 'ok', NOW(), NULL)
        WHERE budget_id = ? AND threshold_pct = 80
    ");
    $stmt->execute([$status, $status, $budget_id]);

    $stmt = $pdo->prepare("
        SELECT COALESCE(c.name, 'Sans catégorie') AS name,
               bcc.cap_amount,
               COALESCE(SUM(t.amount), 0) AS spent
        FROM transactions t
        LEFT JOIN categories c ON c.id = t.category_id
        LEFT JOIN budget_category_caps bcc
            ON bcc.budget_id = t.budget_id AND bcc.category_id = t.category_id
        WHERE t.budget_id = ?
          AND t.type = 'expense'
          AND t.date BETWEEN ? AND ?
        GROUP BY t.category_id, c.name, bcc.cap_amount
        ORDER BY spent DESC
    ");
    $stmt->execute([$budget_id, $budget['start_date'], $budget['end_date']]);
    $category_breakdown = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $stmt = $pdo->prepare("
        SELECT t.id, t.date, t.type, t.amount, t.description,
               COALESCE(c.name, 'Sans catégorie') AS category_name,
               u.name AS user_name,
               u.email AS user_email
        FROM transactions t
        JOIN users u ON u.id = t.user_id
        LEFT JOIN categories c ON c.id = t.category_id
        WHERE t.budget_id = ?
          AND t.type = 'expense'
          AND t.date BETWEEN ? AND ?
        ORDER BY t.date DESC, t.created_at DESC
        LIMIT 10
    ");
    $stmt->execute([$budget_id, $budget['start_date'], $budget['end_date']]);
    $recent_transactions = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $budget['total_spent'] = $total_spent;
    $budget['cap_amount'] = $cap_amount;
    
    echo json_encode([
        'success' => true,
        'budget' => $budget,
        'total_spent' => $total_spent,
        'cap_amount' => $cap_amount,
        'percent_used' => round($percent_used, 1),
        'status' => $status,
        'remaining' => $remaining,
        'category_breakdown' => $category_breakdown,
        'recent_transactions' => $recent_transactions,
        'alert_message' => $status === 'exceeded' ? 'Budget dépassé !' : ($status === 'warning' ? 'Attention : budget à plus de 80%' : null)
    ]);
} catch (PDOException $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?>
