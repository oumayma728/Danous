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
$category_id = $data['category_id'] ?? null;
if (!$category_id) {
    echo json_encode(['success' => false, 'message' => 'ID de catégorie manquant']);
    exit;
}
require_once __DIR__ . '/../../config/db.php';
try {
    // Vérifie que la catégorie appartient à l'utilisateur et n'est pas par défaut
    $stmt = $pdo->prepare("SELECT id FROM categories WHERE id = ? AND user_id = ? AND is_default = 0");
    $stmt->execute([$category_id, $user_id]);
    if (!$stmt->fetch()) {
        echo json_encode(['success' => false, 'message' => 'Vous ne pouvez pas supprimer cette catégorie']);
        exit;
    }
    // Vérifier si des transactions utilisent cette catégorie
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM transactions WHERE category_id = ?");
    $stmt->execute([$category_id]);
    $count = $stmt->fetchColumn();
    
    if ($count > 0) {
        echo json_encode(['success' => false, 'message' => "Cette catégorie est utilisée par $count transaction(s). Supprimez ou modifiez les transactions d'abord."]);
        exit;
    }
    // Supprime la catégorie
    $stmt = $pdo->prepare("DELETE FROM categories WHERE id = ? AND user_id = ?");
    $stmt->execute([$category_id, $user_id]);
    
    echo json_encode(['success' => true, 'message' => 'Catégorie supprimée avec succès']);
} catch (PDOException $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}

?>
