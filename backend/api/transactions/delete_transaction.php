<?php
require_once __DIR__ . '/../auth/api_guard.php';
header('Content-Type: application/json');
requireApiUser();

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Non authentifié']);
    exit();
}

$user_id = $_SESSION['user_id'];
$role = $_SESSION['user_role'];
$data = json_decode(file_get_contents('php://input'), true);

$transaction_id = $data['transaction_id'] ?? null;

if (!$transaction_id) {
    echo json_encode(['success' => false, 'message' => 'ID transaction requis']);
    exit();
}

require_once __DIR__ . '/../../config/db.php';

try {
    // Vérifier les droits
    if ($role === 'admin') {
        $check_sql = "SELECT id FROM transactions WHERE id = ?";
        $check_stmt = $pdo->prepare($check_sql);
        $check_stmt->execute([$transaction_id]);
    } else {
        $check_sql = "SELECT id FROM transactions WHERE id = ? AND user_id = ?";
        $check_stmt = $pdo->prepare($check_sql);
        $check_stmt->execute([$transaction_id, $user_id]);
    }
    
    if (!$check_stmt->fetch()) {
        echo json_encode(['success' => false, 'message' => 'Transaction non trouvée ou non autorisée']);
        exit();
    }
    
    // Supprimer la transaction
    $stmt = $pdo->prepare("DELETE FROM transactions WHERE id = ?");
    $stmt->execute([$transaction_id]);
    
    echo json_encode([
        'success' => true,
        'message' => 'Transaction supprimée avec succès'
    ]);
    
} catch (PDOException $e) {
    echo json_encode(['success' => false, 'message' => 'Erreur: ' . $e->getMessage()]);
}
?>
