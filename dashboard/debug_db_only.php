<?php
// Bypass auth, just check DB
require_once '../config/db.php'; 

echo "<h1>Debug DB Only</h1>";
try {
    $stmt = $pdo->query("SELECT * FROM companies");
    $companies = $stmt->fetchAll(PDO::FETCH_ASSOC);
    echo "Count: " . count($companies) . "\n";
    print_r($companies);
} catch (PDOException $e) {
    echo "Error: " . $e->getMessage();
}
?>
