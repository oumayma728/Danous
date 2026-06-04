<?php
require_once __DIR__ . '/../../config/session.php';

$_SESSION = [];

if (ini_get('session.use_cookies')) {
    $params = session_get_cookie_params();
    setcookie(session_name(), '', time() - 42000, $params['path'], $params['domain'], $params['secure'], $params['httponly']);
}

session_destroy();

$projectBase = '';
$backendPos = strpos($_SERVER['SCRIPT_NAME'] ?? '', '/backend/');
if ($backendPos !== false) {
    $projectBase = substr($_SERVER['SCRIPT_NAME'], 0, $backendPos);
}

header('Location: ' . $projectBase . '/frontend/pages/login.html');
exit;
