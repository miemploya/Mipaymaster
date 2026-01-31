<?php
ini_set('display_errors', 1);
error_reporting(E_ALL);

echo "Testing connection to <b>::1</b>... ";
try {
    $dsn = "mysql:host=::1;dbname=mipaymaster;charset=utf8mb4";
    $pdo = new PDO($dsn, 'root', '');
    echo "SUCCESS (IPv6 works)";
} catch (PDOException $e) {
    echo "FAILED: " . $e->getMessage();
}
?>
