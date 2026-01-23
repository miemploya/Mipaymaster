<?php
ini_set('display_errors', 1);
error_reporting(E_ALL);

echo "Attempting connection...\n";

// Set a short timeout
$options = [
    PDO::ATTR_TIMEOUT => 5,
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION
];

try {
    $dsn = "mysql:host=127.0.0.1;dbname=mipaymaster;charset=utf8mb4";
    $pdo = new PDO($dsn, 'root', '', $options);
    echo "Connection successful!\n";
    
    echo "Querying users count...\n";
    $stmt = $pdo->query("SELECT count(*) FROM users");
    $count = $stmt->fetchColumn();
    echo "Users: $count\n";
    
} catch (PDOException $e) {
    echo "Connection failed: " . $e->getMessage() . "\n";
}
?>
