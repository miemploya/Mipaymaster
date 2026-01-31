<?php
ini_set('display_errors', 1);
error_reporting(E_ALL);

echo "Testing connection to <b>localhost</b>... ";
try {
    $dsn = "mysql:host=localhost;dbname=mipaymaster;charset=utf8mb4";
    $pdo = new PDO($dsn, 'root', '');
    echo "SUCCESS (localhost works)";
} catch (PDOException $e) {
    echo "FAILED: " . $e->getMessage();
}
?>
