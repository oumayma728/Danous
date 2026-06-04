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
try{
    $stmt = $pdo->prepare("
    SELECT * FROM categories
    WHERE (is_default = 1 OR user_id = :user_id)
    order by is_default desc, name asc
    ");
    $stmt->execute(['user_id' => $user_id]);
    //fetch all categories
    $categories = $stmt->fetchAll(PDO::FETCH_ASSOC);
    echo json_encode([
        'success' => true,
        'categories' => $categories
    ]);
} catch (PDOException $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?>
