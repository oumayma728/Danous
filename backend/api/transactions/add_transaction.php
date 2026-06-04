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

$type = $data['type'] ?? '';
if ($type === 'revenu') {
    $type = 'income';
} elseif ($type === 'depense') {
    $type = 'expense';
}

$amount = $data['amount'] ?? '';
$category_id = !empty($data['category_id']) ? (int) $data['category_id'] : null;
$budget_id = !empty($data['budget_id']) ? (int) $data['budget_id'] : null;
$description = trim($data['description'] ?? '');
$date = $data['date'] ?? $data['transaction_date'] ?? date('Y-m-d');

if (!in_array($type, ['income', 'expense'], true)) {
    echo json_encode(['success' => false, 'message' => 'Type de transaction invalide']);
    exit;
}

if (!is_numeric($amount) || (float) $amount <= 0 || !$date) {
    echo json_encode(['success' => false, 'message' => 'Montant ou date invalide']);
    exit;
}

if (!$category_id) {
    echo json_encode(['success' => false, 'message' => 'Catégorie requise']);
    exit;
}

require_once __DIR__ . '/../../config/db.php';

try {
    $stmt = $pdo->prepare("
        SELECT id
        FROM categories
        WHERE id = ? AND (user_id = ? OR is_default = 1)
    ");
    $stmt->execute([$category_id, $user_id]);
    if (!$stmt->fetch()) {
        echo json_encode(['success' => false, 'message' => 'Catégorie non autorisée']);
        exit;
    }

    if ($budget_id) {
        $stmt = $pdo->prepare("
            SELECT b.id
            FROM budgets b
            LEFT JOIN budget_members bm ON bm.budget_id = b.id
            WHERE b.id = ? AND (b.owner_id = ? OR bm.user_id = ?)
            LIMIT 1
        ");
        $stmt->execute([$budget_id, $user_id, $user_id]);
        if (!$stmt->fetch()) {
            echo json_encode(['success' => false, 'message' => 'Budget non autorisé']);
            exit;
        }
    }

    $stmt = $pdo->prepare("
        INSERT INTO transactions (user_id, budget_id, category_id, type, amount, description, date)
        VALUES (?, ?, ?, ?, ?, ?, ?)
    ");
    $stmt->execute([$user_id, $budget_id, $category_id, $type, $amount, $description, $date]);

    $alert_message = null;
    if ($budget_id && $type === 'expense') {
        $alert_message = updateBudgetAlert($pdo, $budget_id);
    }

    echo json_encode([
        'success' => true,
        'message' => 'Transaction ajoutée',
        'transaction_id' => $pdo->lastInsertId(),
        'alert' => $alert_message
    ]);
} catch (PDOException $e) {
    echo json_encode(['success' => false, 'message' => 'Erreur de base de données : ' . $e->getMessage()]);
}

function updateBudgetAlert(PDO $pdo, int $budget_id): ?string
{
    $stmt = $pdo->prepare("
        SELECT b.cap_amount, COALESCE(SUM(t.amount), 0) AS spent
        FROM budgets b
        LEFT JOIN transactions t ON t.budget_id = b.id AND t.type = 'expense'
        WHERE b.id = ?
        GROUP BY b.id
    ");
    $stmt->execute([$budget_id]);
    $budget = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$budget || (float) $budget['cap_amount'] <= 0) {
        return null;
    }

    $pct = ((float) $budget['spent'] / (float) $budget['cap_amount']) * 100;
    $status = $pct >= 100 ? 'exceeded' : ($pct >= 80 ? 'warning' : 'ok');
    $triggered = $status === 'ok' ? null : date('Y-m-d H:i:s');

    $stmt = $pdo->prepare("UPDATE alerts SET status = ?, triggered_at = ? WHERE budget_id = ?");
    $stmt->execute([$status, $triggered, $budget_id]);

    if ($status === 'exceeded') {
        return 'Budget dépassé';
    }
    if ($status === 'warning') {
        return 'Le budget approche de sa limite';
    }
    return null;
}
