<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "Testing db.php...<br>";

// Check if db.php exists
$db_path = __DIR__ . '/../../config/db.php';
if (file_exists($db_path)) {
    echo "db.php found at: " . $db_path . "<br>";
} else {
    echo "db.php NOT found at: " . $db_path . "<br>";
    die();
}

// Try to include it
require_once $db_path;
echo "db.php included successfully<br>";

// Check if $pdo is set
if (isset($pdo)) {
    echo "PDO is set! Database connected.<br>";
    
    // Test a simple query
    $result = $pdo->query("SELECT 1");
    echo "Database query works!<br>";
    
    // Check if users table exists
    $tables = $pdo->query("SHOW TABLES LIKE 'users'");
    if ($tables->rowCount() > 0) {
        echo "Users table exists!<br>";
        
        // Count users
        $count = $pdo->query("SELECT COUNT(*) FROM users")->fetchColumn();
        echo "Number of users in database: " . $count . "<br>";
    } else {
        echo "Users table does NOT exist!<br>";
    }
} else {
    echo "PDO is NOT set after including db.php<br>";
}
