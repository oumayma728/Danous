<?php
require_once __DIR__ . '/../../config/session.php';

function requireApiLogin(): void
{
    if (empty($_SESSION['user_id'])) {
        http_response_code(401);
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'message' => 'Non authentifié']);
        exit;
    }
}

function requireApiRole(array $roles): void
{
    requireApiLogin();

    $role = $_SESSION['user_role'] ?? '';
    if (!in_array($role, $roles, true)) {
        http_response_code(403);
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'message' => 'Accès refusé']);
        exit;
    }
}

function requireApiUser(): void
{
    requireApiRole(['user']);
}

function requireApiAdmin(): void
{
    requireApiRole(['admin']);
}
