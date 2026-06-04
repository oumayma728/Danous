<?php
require_once __DIR__ . '/auth/api_guard.php';
require_once __DIR__ . '/../config/db.php';

header('Content-Type: application/json');
requireApiUser();

function sendUserStatisticsError(string $message, int $statusCode = 400): void
{
    http_response_code($statusCode);
    echo json_encode(['success' => false, 'message' => $message]);
    exit;
}

function isValidDateString(?string $date): bool
{
    if (!$date) {
        return false;
    }

    $parsed = DateTime::createFromFormat('Y-m-d', $date);
    return $parsed && $parsed->format('Y-m-d') === $date;
}

function applyDateFilter(string $alias, ?string $startDate, ?string $endDate, array &$params): string
{
    $where = "{$alias}.user_id = ?";
    $params[] = (int) $_SESSION['user_id'];

    if ($startDate) {
        $where .= " AND {$alias}.date >= ?";
        $params[] = $startDate;
    }
    if ($endDate) {
        $where .= " AND {$alias}.date <= ?";
        $params[] = $endDate;
    }

    return $where;
}

$period = $_GET['period'] ?? 'year';
$today = new DateTimeImmutable('today');
$startDate = null;
$endDate = $today->format('Y-m-d');

switch ($period) {
    case '30d':
        $startDate = $today->modify('-30 days')->format('Y-m-d');
        break;
    case '90d':
        $startDate = $today->modify('-90 days')->format('Y-m-d');
        break;
    case '6m':
        $startDate = $today->modify('-5 months')->modify('first day of this month')->format('Y-m-d');
        break;
    case 'all':
        $endDate = null;
        break;
    case 'custom':
        $customStart = $_GET['start_date'] ?? null;
        $customEnd = $_GET['end_date'] ?? null;
        if (!isValidDateString($customStart) || !isValidDateString($customEnd)) {
            sendUserStatisticsError('Dates personnalisees invalides');
        }
        if ($customStart > $customEnd) {
            sendUserStatisticsError('La date de debut doit etre avant la date de fin');
        }
        $startDate = $customStart;
        $endDate = $customEnd;
        break;
    case 'year':
    default:
        $period = 'year';
        $startDate = $today->format('Y-01-01');
        break;
}

try {
    $params = [];
    $where = applyDateFilter('t', $startDate, $endDate, $params);

    $stmt = $pdo->prepare("
        SELECT type, COALESCE(SUM(amount), 0) AS total, COUNT(*) AS count_items
        FROM transactions t
        WHERE {$where}
        GROUP BY type
    ");
    $stmt->execute($params);

    $totals = [
        'income' => 0,
        'expense' => 0,
        'income_count' => 0,
        'expense_count' => 0
    ];
    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
        $type = $row['type'];
        $totals[$type] = (float) $row['total'];
        $totals[$type . '_count'] = (int) $row['count_items'];
    }
    $totals['balance'] = $totals['income'] - $totals['expense'];

    $params = [];
    $where = applyDateFilter('t', $startDate, $endDate, $params);
    $stmt = $pdo->prepare("
        SELECT DATE_FORMAT(t.date, '%Y-%m') AS month,
               SUM(CASE WHEN t.type = 'income' THEN t.amount ELSE 0 END) AS income,
               SUM(CASE WHEN t.type = 'expense' THEN t.amount ELSE 0 END) AS expense
        FROM transactions t
        WHERE {$where}
        GROUP BY month
        ORDER BY month ASC
    ");
    $stmt->execute($params);
    $trend = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $params = [];
    $where = applyDateFilter('t', $startDate, $endDate, $params);
    $stmt = $pdo->prepare("
        SELECT COALESCE(c.name, 'Sans categorie') AS category_name,
               COALESCE(SUM(t.amount), 0) AS total
        FROM transactions t
        LEFT JOIN categories c ON c.id = t.category_id
        WHERE {$where} AND t.type = 'expense'
        GROUP BY category_name
        ORDER BY total DESC
        LIMIT 10
    ");
    $stmt->execute($params);
    $byCategory = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $params = [];
    $where = applyDateFilter('t', $startDate, $endDate, $params);
    $stmt = $pdo->prepare("
        SELECT COALESCE(b.name, 'Sans budget') AS budget_name,
               COALESCE(SUM(t.amount), 0) AS total
        FROM transactions t
        LEFT JOIN budgets b ON b.id = t.budget_id
        WHERE {$where} AND t.type = 'expense'
        GROUP BY budget_name
        ORDER BY total DESC
        LIMIT 10
    ");
    $stmt->execute($params);
    $byBudget = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $params = [];
    $where = applyDateFilter('t', $startDate, $endDate, $params);
    $stmt = $pdo->prepare("
        SELECT t.id, t.date, t.type, t.amount, t.description,
               COALESCE(c.name, 'Sans categorie') AS category_name,
               COALESCE(b.name, 'Sans budget') AS budget_name
        FROM transactions t
        LEFT JOIN categories c ON c.id = t.category_id
        LEFT JOIN budgets b ON b.id = t.budget_id
        WHERE {$where}
        ORDER BY t.date DESC, t.created_at DESC
        LIMIT 8
    ");
    $stmt->execute($params);
    $recent = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode([
        'success' => true,
        'period' => $period,
        'start_date' => $startDate,
        'end_date' => $endDate,
        'totals' => $totals,
        'trend' => $trend,
        'by_category' => $byCategory,
        'by_budget' => $byBudget,
        'recent' => $recent
    ]);
} catch (PDOException $e) {
    sendUserStatisticsError('Erreur de base de donnees : ' . $e->getMessage(), 500);
}
