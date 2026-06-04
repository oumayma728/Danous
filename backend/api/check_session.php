<?php
require_once __DIR__ . '/../config/session.php';

header('Content-Type: application/json');

if (empty($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode([
        'success' => false,
        'logged_in' => false,
        'message' => 'Non authentifié'
    ]);
    exit;
}

$user = [
    'id' => $_SESSION['user_id'],
    'name' => $_SESSION['user_name'],
    'role' => $_SESSION['user_role']
];

echo json_encode([
    'success' => true,
    'logged_in' => true,
    'user' => $user,
    'user_id' => $user['id'],
    'user_name' => $user['name'],
    'role' => $user['role']
]);
