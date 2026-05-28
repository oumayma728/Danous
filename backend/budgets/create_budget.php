<?php
header("Access-Control-Allow-Origin: http://localhost:8080");
header("Access-Control-Allow-Credentials: true");
header("Access-Control-Allow-Methods: POST, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type");
header("Content-Type: application/json");

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') { http_response_code(200); exit; }

require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/session.php';

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Not authenticated']); exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Method not allowed']); exit;
}

$user_id    = $_SESSION['user_id'];
$name       = trim($_POST['name'] ?? '');
$period     = $_POST['period'] ?? 'monthly';
$cap_amount = floatval($_POST['cap_amount'] ?? 0);
$start_date = $_POST['start_date'] ?? '';
$end_date   = $_POST['end_date'] ?? '';
$is_shared  = ($_POST['is_shared'] ?? '0') === '1' ? 1 : 0;

if (!$name || !$start_date || !$end_date || $cap_amount <= 0) {
    echo json_encode(['success' => false, 'message' => 'All fields are required and cap must be > 0']); exit;
}

if (!in_array($period, ['weekly', 'monthly', 'custom'])) {
    echo json_encode(['success' => false, 'message' => 'Invalid period']); exit;
}

try {
    $pdo->beginTransaction();

    // Insert budget
    $stmt = $pdo->prepare("
        INSERT INTO budgets (owner_id, name, period, cap_amount, start_date, end_date, is_shared)
        VALUES (?, ?, ?, ?, ?, ?, ?)
    ");
    $stmt->execute([$user_id, $name, $period, $cap_amount, $start_date, $end_date, $is_shared]);
    $budget_id = $pdo->lastInsertId();

    // Add owner as member
    $stmt = $pdo->prepare("INSERT INTO budget_members (budget_id, user_id, role) VALUES (?, ?, 'owner')");
    $stmt->execute([$budget_id, $user_id]);

    // Per-category caps
    $cat_ids  = $_POST['category_id'] ?? [];
    $cat_caps = $_POST['cat_cap'] ?? [];
    foreach ($cat_ids as $i => $cat_id) {
        $cat_cap = floatval($cat_caps[$i] ?? 0);
        if ($cat_id && $cat_cap > 0) {
            $stmt = $pdo->prepare("
                INSERT INTO budget_category_caps (budget_id, category_id, cap_amount)
                VALUES (?, ?, ?)
                ON DUPLICATE KEY UPDATE cap_amount = VALUES(cap_amount)
            ");
            $stmt->execute([$budget_id, $cat_id, $cat_cap]);
        }
    }

    // Shared members
    if ($is_shared) {
        $members = $_POST['members'] ?? [];
        foreach ($members as $member_id) {
            $member_id = intval($member_id);
            if ($member_id && $member_id !== $user_id) {
                $stmt = $pdo->prepare("
                    INSERT IGNORE INTO budget_members (budget_id, user_id, role)
                    VALUES (?, ?, 'member')
                ");
                $stmt->execute([$budget_id, $member_id]);
            }
        }
    }

    // Create default alert at 80%
    $stmt = $pdo->prepare("INSERT INTO alerts (budget_id, threshold_pct) VALUES (?, 80)");
    $stmt->execute([$budget_id]);

    $pdo->commit();
    echo json_encode(['success' => true, 'budget_id' => $budget_id]);

} catch (Exception $e) {
    $pdo->rollBack();
    echo json_encode(['success' => false, 'message' => 'DB error: ' . $e->getMessage()]);
}