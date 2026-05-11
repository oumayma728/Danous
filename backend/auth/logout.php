<?php 
require_once '../config/db.php';
session_destroy();
header('Location: ../../frontend/pages/login.html');
exit;