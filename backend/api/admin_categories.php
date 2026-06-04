<?php
require_once __DIR__ . '/auth/api_guard.php';
require_once __DIR__ . '/../config/db.php';

header('Content-Type: application/json');
requireApiAdmin();

function sendAdminCategoryError(string $message, int $statusCode = 400): void
{
    http_response_code($statusCode);
    echo json_encode(['success' => false, 'message' => $message]);
    exit;
}

try {
    if ($_SERVER['REQUEST_METHOD'] === 'GET') {
        $stmt = $pdo->query("
            SELECT id, name
            FROM categories
            WHERE is_default = 1 AND user_id IS NULL
            ORDER BY name ASC
        ");
        echo json_encode(['success' => true, 'categories' => $stmt->fetchAll(PDO::FETCH_ASSOC)]);
        exit;
    }

    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        sendAdminCategoryError('Methode non autorisee', 405);
    }

    $data = json_decode(file_get_contents('php://input'), true) ?: [];
    $action = $data['action'] ?? '';
    $categoryId = (int) ($data['category_id'] ?? 0);
    $name = trim($data['name'] ?? '');

    if ($action === 'add') {
        if ($name === '') {
            sendAdminCategoryError('Nom de categorie requis');
        }

        $stmt = $pdo->prepare("SELECT id FROM categories WHERE is_default = 1 AND user_id IS NULL AND LOWER(name) = LOWER(?)");
        $stmt->execute([$name]);
        if ($stmt->fetch()) {
            sendAdminCategoryError('Cette categorie globale existe deja');
        }

        $stmt = $pdo->prepare("INSERT INTO categories (user_id, name, is_default) VALUES (NULL, ?, 1)");
        $stmt->execute([$name]);
        echo json_encode(['success' => true, 'message' => 'Categorie globale ajoutee']);
        exit;
    }

    if ($action === 'update') {
        if ($categoryId <= 0 || $name === '') {
            sendAdminCategoryError('Categorie invalide');
        }

        $stmt = $pdo->prepare("SELECT id FROM categories WHERE id = ? AND is_default = 1 AND user_id IS NULL");
        $stmt->execute([$categoryId]);
        if (!$stmt->fetch()) {
            sendAdminCategoryError('Categorie globale introuvable');
        }

        $stmt = $pdo->prepare("
            SELECT id
            FROM categories
            WHERE id <> ? AND is_default = 1 AND user_id IS NULL AND LOWER(name) = LOWER(?)
        ");
        $stmt->execute([$categoryId, $name]);
        if ($stmt->fetch()) {
            sendAdminCategoryError('Cette categorie globale existe deja');
        }

        $stmt = $pdo->prepare("UPDATE categories SET name = ? WHERE id = ? AND is_default = 1 AND user_id IS NULL");
        $stmt->execute([$name, $categoryId]);
        echo json_encode(['success' => true, 'message' => 'Categorie globale modifiee']);
        exit;
    }

    if ($action === 'delete') {
        if ($categoryId <= 0) {
            sendAdminCategoryError('Categorie invalide');
        }

        $stmt = $pdo->prepare("SELECT COUNT(*) FROM transactions WHERE category_id = ?");
        $stmt->execute([$categoryId]);
        $usageCount = (int) $stmt->fetchColumn();
        if ($usageCount > 0) {
            sendAdminCategoryError("Cette categorie est utilisee par $usageCount transaction(s)");
        }

        $stmt = $pdo->prepare("DELETE FROM categories WHERE id = ? AND is_default = 1 AND user_id IS NULL");
        $stmt->execute([$categoryId]);
        echo json_encode(['success' => true, 'message' => 'Categorie globale supprimee']);
        exit;
    }

    sendAdminCategoryError('Action invalide');
} catch (PDOException $e) {
    sendAdminCategoryError('Erreur de base de donnees : ' . $e->getMessage(), 500);
}
