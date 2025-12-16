<?php
try {
    $pdo = new PDO("mysql:host=mysql;dbname=articulate_test;charset=utf8mb4", "root", "rootpassword");
    echo "MySQL: OK\n";
} catch (Exception $e) {
    echo "MySQL: FAILED - " . $e->getMessage() . "\n";
}

try {
    $pdo = new PDO("pgsql:host=pgsql;port=5432;dbname=articulate_test", "postgres", "rootpassword");
    echo "PostgreSQL: OK\n";
} catch (Exception $e) {
    echo "PostgreSQL: FAILED - " . $e->getMessage() . "\n";
}

