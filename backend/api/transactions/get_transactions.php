<?php
require_once __DIR__ . '/../auth/api_guard.php';
header('Content-Type: application/json');
requireApiUser();

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['error' => 'Non authentifié']);
    exit();
}

$user_id = $_SESSION['user_id'];
$role = $_SESSION['user_role'] ?? 'user';
require_once __DIR__ . '/../../config/db.php';
try
{if ($role === 'admin') {
    $sql = "
            SELECT 
                transactions .*, 
                c.name as category_name,
                u.name as user_name,
                u.email as user_email
            FROM transactions 
            JOIN categories c ON transactions.category_id = c.id
            JOIN users u ON transactions.user_id = u.id
            ORDER BY transactions.created_at DESC        ";
         //execture sql command
        $stmt = $pdo->query($sql);
}
else
{
    $sql = "
            SELECT 
                transactions.*, 
                c.name as category_name,
                u.name as user_name,
                u.email as user_email
            FROM transactions 
            JOIN categories c ON transactions.category_id = c.id
            JOIN users u ON transactions.user_id = u.id
            WHERE transactions.user_id = :user_id
            ORDER BY transactions.created_at DESC
        ";

        $stmt = $pdo->prepare($sql);
        $stmt->execute(['user_id' => $user_id]);
}
//Retrieves all query results.
$transactions = $stmt->fetchAll(PDO::FETCH_ASSOC);
 echo json_encode([
        'success' => true,
        'transactions' => $transactions,
        'role' => $role
    ]);
    
} catch (PDOException $e) {
    echo json_encode(['error' => $e->getMessage()]);
}
?>
