<?php
header("Access-Control-Allow-Origin: http://localhost:8080");
header("Access-Control-Allow-Credentials: true");
header("Content-Type: application/json");
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') { http_response_code(200); exit; }
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../api/auth/api_guard.php';
requireApiUser();
if (!isset($_SESSION['user_id'])) { echo json_encode([]); exit; }

$user_id = $_SESSION['user_id'];

// Get all budgets the user owns or is member of, with their alert status and spending
$stmt = $pdo->prepare("
    SELECT DISTINCT
        b.id, b.name, b.cap_amount, b.owner_id,
        (SELECT COALESCE(SUM(t.amount), 0)
         FROM transactions t
         WHERE t.budget_id = b.id AND t.type = 'expense') AS spent,
        a.threshold_pct, a.status, a.triggered_at
    FROM budgets b
    LEFT JOIN budget_members bm ON bm.budget_id = b.id
    LEFT JOIN alerts a ON a.budget_id = b.id
    WHERE b.owner_id = ? OR bm.user_id = ?
    GROUP BY b.id
    ORDER BY b.created_at DESC
");
$stmt->execute([$user_id, $user_id]);
$budgets = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Recalculate and update alert status for each budget
$results = [];
foreach ($budgets as $b) {
    $spent = floatval($b['spent']);
    $cap   = floatval($b['cap_amount']);
    $pct   = $cap > 0 ? ($spent / $cap) * 100 : 0;

    if ($pct >= 100) $status = 'exceeded';
    elseif ($pct >= floatval($b['threshold_pct'] ?? 80)) $status = 'warning';
    else $status = 'ok';

    // Update alert in DB if status changed
    if ($status !== $b['status']) {
        $triggered = ($status !== 'ok') ? date('Y-m-d H:i:s') : null;
        $pdo->prepare("UPDATE alerts SET status = ?, triggered_at = ? WHERE budget_id = ?")
            ->execute([$status, $triggered, $b['id']]);
    }

    $results[] = [
        'budget_id'     => $b['id'],
        'budget_name'   => $b['name'],
        'cap_amount'    => $cap,
        'spent'         => $spent,
        'pct'           => round($pct, 1),
        'threshold_pct' => $b['threshold_pct'] ?? 80,
        'status'        => $status,
        'triggered_at'  => $b['triggered_at'],
    ];
}

echo json_encode($results);
