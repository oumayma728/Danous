<?php 
require_once __DIR__ . '/../config/db.php';
function requireLogin() {
    if (empty($_SESSION['user_id'])) {
        header('Location: ../../frontend/pages/login.html');
        exit;
    }
}

function requireAdmin() {
    requireLogin();
    if ($_SESSION['user_role'] !== 'admin') {
        header('Location: ../../frontend/pages/dashboard.html');
        exit;
    }
}