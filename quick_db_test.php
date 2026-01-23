<?php
ini_set('max_execution_time', 5);
echo "Testing DB connection...\n";

try {
    $pdo = new PDO('mysql:host=127.0.0.1;dbname=mipaymaster', 'root', '');
    echo "SUCCESS: Connected to database\n";
    
    $stmt = $pdo->query("SELECT COUNT(*) FROM users");
    $count = $stmt->fetchColumn();
    echo "Users in database: " . $count . "\n";
} catch (Exception $e) {
    echo "FAILED: " . $e->getMessage() . "\n";
}
?>
