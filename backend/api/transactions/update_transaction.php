<?php
require_once __DIR__ . '/../auth/api_guard.php';
header('Content-Type: application/json');
requireApiUser();
if (!isset($_SESSION['user_id'])) {
    echo json_encode(['error' => 'Non authentifié']);
    exit();
}
$user_id = $_SESSION['user_id'];
$role = $_SESSION['user_role'];
$data = json_decode(file_get_contents('php://input'), true);


$transaction_id = $data['transaction_id'] ?? null;
$type = $data['type'] ?? '';
$amount = $data['amount'] ?? '';
$category_id = $data['category_id'] ?? '';
$description = $data['description'] ?? '';
$date = $data['date'] ?? $data['transaction_date'] ?? '';

if (!$transaction_id) {
    echo json_encode(['success' => false, 'message' => 'ID transaction requis']);
    exit();
}
if ($type === 'revenu') {
    $type = 'income';
} elseif ($type === 'depense') {
    $type = 'expense';
}

if (!in_array($type, ['income', 'expense'], true)) {
    echo json_encode(['success' => false, 'message' => 'Type invalide']);
    exit();
}

if (!is_numeric($amount) || $amount <= 0 || !$date) {
    echo json_encode(['success' => false, 'message' => 'Montant ou date invalide']);
    exit();
}

require_once __DIR__ . '/../../config/db.php';

try {
    // Vérifier que l'utilisateur a le droit de modifier cette transaction
    if ($role === 'admin') {
        // Admin peut modifier n'importe quelle transaction
        $check_sql = "SELECT id FROM transactions WHERE id = ?";
        $check_stmt = $pdo->prepare($check_sql);
        $check_stmt->execute([$transaction_id]);
    } else {
        // User ne peut modifier que ses propres transactions
        $check_sql = "SELECT id FROM transactions WHERE id = ? AND user_id = ?";
        $check_stmt = $pdo->prepare($check_sql);
        $check_stmt->execute([$transaction_id, $user_id]);
    }
    if (!$check_stmt->fetch()) {
        echo json_encode(['success' => false, 'message' => 'Transaction non trouvée ou non autorisée']);
        exit();
    }
    // Mettre à jour la transaction
    $sql = "
        UPDATE transactions 
        SET type = ?, amount = ?, category_id = ?, description = ?, date = ?
        WHERE id = ?
    ";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$type, $amount, $category_id, $description, $date, $transaction_id]);
    
    echo json_encode([
        'success' => true,
        'message' => 'Transaction modifiée avec succès'
    ]);
    
} catch (PDOException $e) {
    echo json_encode(['success' => false, 'message' => 'Erreur: ' . $e->getMessage()]);
}
?>
