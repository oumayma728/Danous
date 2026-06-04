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
$name = trim($data['name'] ?? '');
$period = $data['period'] ?? 'monthly';
$cap_amount = floatval($data['cap_amount'] ?? 0);
$start_date = $data['start_date'] ?? '';
$end_date = $data['end_date'] ?? '';

if (!$budget_id || empty($name)) {
    echo json_encode(['success' => false, 'message' => 'Données invalides']);
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
    
    // Mettre à jour
    $stmt = $pdo->prepare("
        UPDATE budgets 
        SET name = ?, period = ?, cap_amount = ?, start_date = ?, end_date = ?
        WHERE id = ?
    ");
    $stmt->execute([$name, $period, $cap_amount, $start_date, $end_date, $budget_id]);
    
    echo json_encode(['success' => true, 'message' => 'Budget modifié']);
} catch (PDOException $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?>
