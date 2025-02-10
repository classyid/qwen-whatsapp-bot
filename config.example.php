<?php
// Database configuration
$db_host = 'localhost';
$db_name = 'dbname';
$db_user = 'dbuser';
$db_pass = 'dbpass';

try {
    $db = new PDO(
        "mysql:host=$db_host;dbname=$db_name;charset=utf8mb4",
        $db_user,
        $db_pass,
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
    );
} catch (PDOException $e) {
    error_log("Database Connection Error: " . $e->getMessage());
    die("Database connection failed");
}
