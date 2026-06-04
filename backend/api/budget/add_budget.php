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

$name = trim($data['name'] ?? '');
$period = $data['period'] ?? 'monthly';
$cap_amount = $data['cap_amount'] ?? 0;
$start_date = $data['start_date'] ?? date('Y-m-d');
$end_date = $data['end_date'] ?? date('Y-m-d', strtotime('+1 month'));
$is_shared = $data['is_shared'] ?? false;
$category_caps = $data['category_caps'] ?? []; // [{category_id, cap_amount}]

if (empty($name) || $cap_amount <= 0) {
    echo json_encode(['success' => false, 'message' => 'Nom et montant requis']);
    exit;
}

require_once __DIR__ . '/../../config/db.php';

try {
    $pdo->beginTransaction();
    
    // Créer le budget
    $stmt = $pdo->prepare("
        INSERT INTO budgets (owner_id, name, period, cap_amount, start_date, end_date, is_shared)
        VALUES (?, ?, ?, ?, ?, ?, ?)
    ");
    $stmt->execute([$user_id, $name, $period, $cap_amount, $start_date, $end_date, $is_shared]);
    $budget_id = $pdo->lastInsertId();
    
    // Ajouter les plafonds par catégorie
    foreach ($category_caps as $cap) {
        if (!empty($cap['category_id']) && !empty($cap['cap_amount'])) {
            $stmt = $pdo->prepare("
                INSERT INTO budget_category_caps (budget_id, category_id, cap_amount)
                VALUES (?, ?, ?)
            ");
            $stmt->execute([$budget_id, $cap['category_id'], $cap['cap_amount']]);
        }
    }
    
    // Si budget partagé, ajouter l'owner comme membre
    if ($is_shared) {
        $stmt = $pdo->prepare("
            INSERT INTO budget_members (budget_id, user_id, role)
            VALUES (?, ?, 'owner')
        ");
        $stmt->execute([$budget_id, $user_id]);
    }
    
    // Créer une alerte par défaut
    $stmt = $pdo->prepare("
        INSERT INTO alerts (budget_id, threshold_pct, status)
        VALUES (?, 80, 'ok')
    ");
    $stmt->execute([$budget_id]);
    
    $pdo->commit();
    
    echo json_encode(['success' => true, 'message' => 'Budget créé', 'budget_id' => $budget_id]);
} catch (PDOException $e) {
    $pdo->rollBack();
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?>
