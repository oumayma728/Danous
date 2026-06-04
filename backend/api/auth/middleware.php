<?php
require_once __DIR__ . '/../../config/session.php';

function frontendPage(string $page): string {
    $projectBase = '';
    $backendPos = strpos($_SERVER['SCRIPT_NAME'] ?? '', '/backend/');
    if ($backendPos !== false) {
        $projectBase = substr($_SERVER['SCRIPT_NAME'], 0, $backendPos);
    }

    return $projectBase . '/frontend/pages/' . ltrim($page, '/');
}

function requireLogin() {
    if (empty($_SESSION['user_id'])) {
        header('Location: ' . frontendPage('login.html'));
        exit;
    }
}

function requireAdmin() {
    requireLogin();

    if (($_SESSION['user_role'] ?? '') !== 'admin') {
        header('Location: ' . frontendPage('dashboard.html'));
        exit;
    }
}
