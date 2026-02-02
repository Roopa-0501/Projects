<?php
// core/db.php
$config = require __DIR__ . '/../config/config.sample.php';

try {
    $pdo = new PDO(
        "mysql:host={$config['DB_HOST']};dbname={$config['DB_NAME']};charset=utf8mb4",
        $config['DB_USER'],
        $config['DB_PASS']
    );
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    die("Database connection failed: " . $e->getMessage());
}

// Optional: function to reuse PDO
function getDB() {
    global $pdo;
    return $pdo;
}
?>
