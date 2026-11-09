<?php
require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/database/app/db.php';

try {
    $pdo = db();
    $pdo->query("SELECT 1");

    echo "Database connection successful!";
} catch (Throwable $e) {
    echo "<h3>Database connection failed!</h3>";
    echo "<pre>";
    echo htmlspecialchars($e->getMessage(), ENT_QUOTES, 'UTF-8');
    echo "</pre>";
}