<?php
require_once __DIR__ . '/../auth/api_guard.php';

header('Content-Type: application/json');
requireApiUser();

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Non authentifié']);
    exit;
}

$user_id = (int) $_SESSION['user_id'];
$data = json_decode(file_get_contents('php://input'), true);

if (!is_array($data)) {
    echo json_encode(['success' => false, 'message' => 'Données invalides']);
    exit;
}

$category_id = !empty($data['category_id']) ? (int) $data['category_id'] : null;
$name = trim($data['name'] ?? '');

if (!$category_id || $name === '') {
    echo json_encode(['success' => false, 'message' => 'Données de catégorie invalides']);
    exit;
}

require_once __DIR__ . '/../../config/db.php';

try {
    $stmt = $pdo->prepare("SELECT id FROM categories WHERE id = ? AND user_id = ? AND is_default = 0");
    $stmt->execute([$category_id, $user_id]);
    if (!$stmt->fetch()) {
        echo json_encode(['success' => false, 'message' => 'Vous ne pouvez pas modifier cette catégorie']);
        exit;
    }

    $stmt = $pdo->prepare("
        SELECT id
        FROM categories
        WHERE id <> ? AND (user_id = ? OR is_default = 1) AND LOWER(name) = LOWER(?)
    ");
    $stmt->execute([$category_id, $user_id, $name]);
    if ($stmt->fetch()) {
        echo json_encode(['success' => false, 'message' => 'Cette catégorie existe déjà']);
        exit;
    }

    $stmt = $pdo->prepare("UPDATE categories SET name = ? WHERE id = ? AND user_id = ?");
    $stmt->execute([$name, $category_id, $user_id]);

    echo json_encode(['success' => true, 'message' => 'Catégorie modifiée']);
} catch (PDOException $e) {
    echo json_encode(['success' => false, 'message' => 'Erreur de base de données : ' . $e->getMessage()]);
}
