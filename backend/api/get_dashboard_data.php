<?php
require_once __DIR__ . '/../config/session.php';
require_once __DIR__ . '/../config/db.php';

header('Content-Type: application/json');

function sendDashboardError(string $message, int $statusCode = 400): void
{
    http_response_code($statusCode);
    echo json_encode(['success' => false, 'message' => $message]);
    exit;
}

if (empty($_SESSION['user_id'])) {
    sendDashboardError('Non connecté', 401);
}

if (($_SESSION['user_role'] ?? '') !== 'user') {
    sendDashboardError('Le tableau de bord utilisateur nécessite un compte utilisateur', 403);
}

$userId = (int) $_SESSION['user_id'];

try {
    $stmt = $pdo->prepare("
        SELECT
            COALESCE(SUM(CASE WHEN type = 'income' THEN amount ELSE 0 END), 0) AS total_income,
            COALESCE(SUM(CASE WHEN type = 'expense' THEN amount ELSE 0 END), 0) AS total_expenses
        FROM transactions
        WHERE user_id = ?
    ");
    $stmt->execute([$userId]);
    $totals = $stmt->fetch(PDO::FETCH_ASSOC);

    $totalIncome = (float) $totals['total_income'];
    $totalExpenses = (float) $totals['total_expenses'];
    $balance = $totalIncome - $totalExpenses;

    $stmt = $pdo->prepare("
        SELECT COALESCE(SUM(cap_amount), 0) AS budget_total
        FROM budgets
        WHERE owner_id = ?
    ");
    $stmt->execute([$userId]);
    $budgetTotal = (float) $stmt->fetchColumn();
    $budgetPercent = $budgetTotal > 0 ? round(($totalExpenses / $budgetTotal) * 100, 1) : 0;

    $stmt = $pdo->prepare("
        SELECT
            c.name,
            COALESCE(SUM(t.amount), 0) AS total
        FROM categories c
        LEFT JOIN transactions t
            ON t.category_id = c.id
            AND t.user_id = ?
            AND t.type = 'expense'
        WHERE c.user_id = ? OR c.is_default = 1
        GROUP BY c.id, c.name
        HAVING total > 0
        ORDER BY total DESC
        LIMIT 10
    ");
    $stmt->execute([$userId, $userId]);
    $categories = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $stmt = $pdo->prepare("
        SELECT
            DATE_FORMAT(`date`, '%b') AS month,
            DATE_FORMAT(`date`, '%Y-%m') AS sort_month,
            SUM(amount) AS total
        FROM transactions
        WHERE user_id = ?
            AND type = 'expense'
            AND `date` >= DATE_SUB(CURDATE(), INTERVAL 6 MONTH)
        GROUP BY sort_month, month
        ORDER BY sort_month ASC
    ");
    $stmt->execute([$userId]);
    $monthlyRows = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $months = [];
    $monthlyExpenses = [];
    foreach ($monthlyRows as $row) {
        $months[] = $row['month'];
        $monthlyExpenses[] = (float) $row['total'];
    }

    $stmt = $pdo->prepare("
        SELECT
            t.id,
            t.type,
            t.amount,
            t.`date`,
            t.description,
            COALESCE(c.name, 'Uncategorized') AS category_name
        FROM transactions t
        LEFT JOIN categories c ON c.id = t.category_id
        WHERE t.user_id = ?
        ORDER BY t.`date` DESC, t.id DESC
        LIMIT 10
    ");
    $stmt->execute([$userId]);
    $recentTransactions = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode([
        'success' => true,
        'total_income' => $totalIncome,
        'total_expenses' => $totalExpenses,
        'balance' => $balance,
        'budget_percent' => $budgetPercent,
        'categories' => $categories,
        'months' => $months,
        'monthly_expenses' => $monthlyExpenses,
        'recent_transactions' => $recentTransactions
    ]);
} catch (PDOException $e) {
    sendDashboardError('Erreur de base de données : ' . $e->getMessage(), 500);
}
