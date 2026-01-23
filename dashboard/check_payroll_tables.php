<?php
require_once '../config/db.php';

$tables = ['payroll_runs', 'payroll_entries', 'payroll_snapshots', 'payroll_reversals'];

echo "<h1>Payroll Table Verification</h1>";

foreach ($tables as $table) {
    try {
        $stmt = $pdo->query("DESCRIBE $table");
        echo "<h3 style='color:green'>Table Found: $table</h3>";
        echo "<pre>";
        // print_r($stmt->fetchAll(PDO::FETCH_ASSOC));
        echo "OK";
        echo "</pre>";
    } catch(PDOException $e) {
        echo "<h3 style='color:red'>Table Missing: $table</h3>";
        echo "Error: " . $e->getMessage();
    }
}
?>
