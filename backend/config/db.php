<?php

try {
    $pdo = new PDO(
        "mysql:host=127.0.0.1;dbname=danous;charset=utf8",
        "root",
        "",
        [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
        ]
    );

} catch (PDOException $e) {

    http_response_code(500);

    echo json_encode([
        "success" => false,
        "message" => "DB connection failed",
        "error" => $e->getMessage()
    ]);

    exit;
}