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
if (empty($name)) {
    echo json_encode(['success' => false, 'message' => 'Nom de catégorie requis']);
    exit;
}

require_once __DIR__ . '/../../config/db.php';

try {
    // Vérifier si la catégorie existe déjà pour cet utilisateur
    $stmt = $pdo->prepare("
        SELECT id FROM categories 
        WHERE (user_id = ? OR is_default = 1) AND LOWER(name) = LOWER(?)
    ");
    $stmt->execute([$user_id, $name]);
    if ($stmt->fetch()) {
        echo json_encode(['success' => false, 'message' => 'Cette catégorie existe déjà']);
        exit;
    }
    
    // Ajouter la catégorie personnalisée
    $stmt = $pdo->prepare("
        INSERT INTO categories (name, user_id, is_default)
        VALUES (?, ?, 0)
    ");
    $stmt->execute([$name, $user_id]);
    
    echo json_encode([
        'success' => true,
        'message' => 'Catégorie ajoutée avec succès',
        'category_id' => $pdo->lastInsertId()
    ]);
} catch (PDOException $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?>
