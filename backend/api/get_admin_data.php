<?php
require_once __DIR__ . '/../config/session.php';
require_once __DIR__ . '/../config/db.php';

header('Content-Type: application/json');

function sendAdminError(string $message, int $statusCode = 400): void
{
    http_response_code($statusCode);
    echo json_encode(['success' => false, 'message' => $message]);
    exit;
}

if (empty($_SESSION['user_id'])) {
    sendAdminError('Non connecté', 401);
}

if (($_SESSION['user_role'] ?? '') !== 'admin') {
    sendAdminError('Rôle administrateur requis', 403);
}

function ensureDeletionRequestsTable(PDO $pdo): void
{
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS deletion_requests (
            id INT AUTO_INCREMENT PRIMARY KEY,
            user_id INT NOT NULL,
            reason VARCHAR(255) DEFAULT NULL,
            status ENUM('pending', 'approved', 'rejected') DEFAULT 'pending',
            requested_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            reviewed_at DATETIME DEFAULT NULL,
            reviewed_by INT DEFAULT NULL,
            FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
            FOREIGN KEY (reviewed_by) REFERENCES users(id) ON DELETE SET NULL
        )
    ");
}

function ensureUserActivityColumn(PDO $pdo): void
{
    $stmt = $pdo->query("SHOW COLUMNS FROM users LIKE 'last_login_at'");
    if (!$stmt->fetch(PDO::FETCH_ASSOC)) {
        $pdo->exec("ALTER TABLE users ADD COLUMN last_login_at DATETIME DEFAULT NULL");
    }
}

try {
    ensureDeletionRequestsTable($pdo);
    ensureUserActivityColumn($pdo);

    $totalUsers = (int) $pdo->query("SELECT COUNT(*) FROM users")->fetchColumn();
    $totalTransactions = (int) $pdo->query("SELECT COUNT(*) FROM transactions")->fetchColumn();

    $stmt = $pdo->query("SELECT COALESCE(SUM(amount), 0) FROM transactions WHERE type = 'income'");
    $totalVolume = (float) $stmt->fetchColumn();

    $stmt = $pdo->query("
        SELECT COUNT(*)
        FROM users
        WHERE last_login_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)
    ");
    $activeUsers = (int) $stmt->fetchColumn();

    $stmt = $pdo->query("
        SELECT
            c.name,
            COALESCE(SUM(t.amount), 0) AS total
        FROM categories c
        LEFT JOIN transactions t
            ON t.category_id = c.id
            AND t.type = 'expense'
        GROUP BY c.id, c.name
        HAVING total > 0
        ORDER BY total DESC
        LIMIT 10
    ");
    $globalCategories = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $stmt = $pdo->query("
        SELECT
            DATE_FORMAT(`date`, '%b') AS month,
            DATE_FORMAT(`date`, '%Y-%m') AS sort_month,
            SUM(amount) AS total
        FROM transactions
        WHERE type = 'expense'
            AND `date` >= DATE_SUB(CURDATE(), INTERVAL 6 MONTH)
        GROUP BY sort_month, month
        ORDER BY sort_month ASC
    ");
    $globalMonthlyRows = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $globalMonths = [];
    $globalMonthlyExpenses = [];
    foreach ($globalMonthlyRows as $row) {
        $globalMonths[] = $row['month'];
        $globalMonthlyExpenses[] = (float) $row['total'];
    }

    $stmt = $pdo->query("
        SELECT
            id,
            name,
            email,
            role,
            status,
            DATE_FORMAT(created_at, '%d/%m/%Y') AS created_at
        FROM users
        ORDER BY created_at DESC
    ");
    $users = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $stmt = $pdo->prepare("
        SELECT id, name, email
        FROM users
        WHERE id = ?
    ");
    $stmt->execute([$_SESSION['user_id']]);
    $adminProfile = $stmt->fetch(PDO::FETCH_ASSOC);

    $stmt = $pdo->query("
        SELECT
            id,
            name,
            email,
            DATE_FORMAT(created_at, '%d/%m/%Y') AS request_date
        FROM users
        WHERE status = 'pending'
        ORDER BY created_at ASC
    ");
    $pendingUsers = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $stmt = $pdo->query("
        SELECT
            dr.id,
            dr.user_id,
            dr.reason,
            DATE_FORMAT(dr.requested_at, '%d/%m/%Y') AS requested_at,
            u.name,
            u.email
        FROM deletion_requests dr
        JOIN users u ON u.id = dr.user_id
        WHERE dr.status = 'pending'
        ORDER BY dr.requested_at ASC
    ");
    $deletionRequests = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $stmt = $pdo->query("
        SELECT
            b.id,
            b.name,
            b.period,
            b.cap_amount,
            DATE_FORMAT(b.start_date, '%d/%m/%Y') AS start_date,
            DATE_FORMAT(b.end_date, '%d/%m/%Y') AS end_date,
            b.owner_id AS created_by,
            u.name AS created_by_name,
            COALESCE(m.member_count, 0) AS member_count,
            COALESCE(x.total_expenses, 0) AS total_expenses
        FROM budgets b
        JOIN users u ON u.id = b.owner_id
        LEFT JOIN (
            SELECT budget_id, COUNT(*) AS member_count
            FROM budget_members
            GROUP BY budget_id
        ) m ON m.budget_id = b.id
        LEFT JOIN (
            SELECT budget_id, SUM(amount) AS total_expenses
            FROM transactions
            WHERE type = 'expense'
            GROUP BY budget_id
        ) x ON x.budget_id = b.id
        WHERE b.is_shared = 1
        ORDER BY b.created_at DESC
    ");
    $sharedBudgets = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $stmt = $pdo->query("
        SELECT id, name
        FROM categories
        WHERE is_default = 1 AND user_id IS NULL
        ORDER BY name ASC
    ");
    $defaultCategories = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $stmt = $pdo->query("
        SELECT
            t.id,
            t.type,
            t.amount,
            t.description,
            DATE_FORMAT(t.`date`, '%d/%m/%Y') AS date,
            DATE_FORMAT(t.created_at, '%d/%m/%Y %H:%i') AS created_at,
            u.name AS user_name,
            u.email AS user_email,
            c.name AS category_name,
            b.name AS budget_name
        FROM transactions t
        JOIN users u ON u.id = t.user_id
        LEFT JOIN categories c ON c.id = t.category_id
        LEFT JOIN budgets b ON b.id = t.budget_id
        ORDER BY t.`date` DESC, t.created_at DESC
        LIMIT 300
    ");
    $allTransactions = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $stmt = $pdo->query("
        SELECT
            activity.user_name,
            activity.action,
            DATE_FORMAT(activity.sort_at, '%d/%m/%Y %H:%i') AS date
        FROM (
            SELECT
                u.name AS user_name,
                'Connexion utilisateur' AS action,
                u.last_login_at AS sort_at
            FROM users u
            WHERE u.last_login_at IS NOT NULL

            UNION ALL

            SELECT
                u.name AS user_name,
                CONCAT(
                    CASE
                        WHEN t.type = 'income' THEN 'Revenu ajoute : '
                        WHEN t.type = 'expense' THEN 'Depense ajoutee : '
                        ELSE 'Transaction ajoutee : '
                    END,
                    t.amount,
                    ' TND'
                ) AS action,
                t.created_at AS sort_at
            FROM transactions t
            JOIN users u ON u.id = t.user_id
        ) activity
        ORDER BY activity.sort_at DESC
        LIMIT 20
    ");
    $recentActivities = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $stmt = $pdo->query("
        SELECT
            u.name,
            u.email,
            COUNT(t.id) AS transaction_count,
            COALESCE(SUM(CASE WHEN t.type = 'expense' THEN t.amount ELSE 0 END), 0) AS total_spent,
            COALESCE(SUM(CASE WHEN t.type = 'income' THEN t.amount ELSE 0 END), 0) AS total_income
        FROM users u
        LEFT JOIN transactions t ON t.user_id = u.id
        GROUP BY u.id, u.name, u.email
        ORDER BY total_spent DESC
        LIMIT 5
    ");
    $topUsers = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $stmt = $pdo->query("SELECT COALESCE(SUM(amount), 0) FROM transactions WHERE type = 'expense'");
    $totalExpensesAll = (float) $stmt->fetchColumn();

    $stmt = $pdo->query("
        SELECT COALESCE(AVG(transaction_count), 0)
        FROM (
            SELECT COUNT(*) AS transaction_count
            FROM transactions
            GROUP BY user_id
        ) user_transactions
    ");
    $avgTransactionsPerUser = round((float) $stmt->fetchColumn(), 1);

    $stmt = $pdo->query("
        SELECT c.name
        FROM transactions t
        JOIN categories c ON c.id = t.category_id
        GROUP BY c.id, c.name
        ORDER BY COUNT(t.id) DESC
        LIMIT 1
    ");
    $mostUsedCategory = $stmt->fetchColumn() ?: 'Aucune';

    echo json_encode([
        'success' => true,
        'total_users' => $totalUsers,
        'total_transactions' => $totalTransactions,
        'total_volume' => round($totalVolume, 2),
        'active_users' => $activeUsers,
        'total_expenses_all' => round($totalExpensesAll, 2),
        'avg_transactions_per_user' => $avgTransactionsPerUser,
        'most_used_category' => $mostUsedCategory,
        'global_categories' => $globalCategories,
        'global_months' => $globalMonths,
        'global_monthly_expenses' => $globalMonthlyExpenses,
        'users' => $users,
        'admin_profile' => $adminProfile,
        'pending_users' => $pendingUsers,
        'deletion_requests' => $deletionRequests,
        'shared_budgets' => $sharedBudgets,
        'default_categories' => $defaultCategories,
        'all_transactions' => $allTransactions,
        'recent_activities' => $recentActivities,
        'top_users' => $topUsers,
        'last_updated' => date('Y-m-d H:i:s')
    ]);
} catch (PDOException $e) {
    sendAdminError('Erreur de base de données : ' . $e->getMessage(), 500);
}
