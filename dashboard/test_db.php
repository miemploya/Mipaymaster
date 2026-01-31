<?php
// Quick database test
error_reporting(E_ALL);
ini_set('display_errors', 1);
set_time_limit(10); // 10 second timeout

header('Content-Type: text/plain');
echo "Testing database connection...\n";

try {
    $start = microtime(true);
    $pdo = new PDO("mysql:host=127.0.0.1;dbname=mipaymaster", "root", "");
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    echo "Connected in " . round((microtime(true) - $start) * 1000) . "ms\n";
    
    // Test a simple query
    $start = microtime(true);
    $stmt = $pdo->query("SELECT COUNT(*) as cnt FROM employees");
    $row = $stmt->fetch();
    echo "Employee count query: {$row['cnt']} employees in " . round((microtime(true) - $start) * 1000) . "ms\n";
    
    // Test companies table
    $start = microtime(true);
    $stmt = $pdo->query("SELECT COUNT(*) as cnt FROM companies");
    $row = $stmt->fetch();
    echo "Companies count query: {$row['cnt']} companies in " . round((microtime(true) - $start) * 1000) . "ms\n";
    
    echo "\nDatabase is working correctly!\n";
    
} catch (Exception $e) {
    echo "ERROR: " . $e->getMessage() . "\n";
}
?>
