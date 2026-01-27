<?php
require_once 'includes/functions.php';

echo "<h1>Table Check</h1>";
echo "<pre>";
$tables = ['attendance_records', 'attendance_logs', 'loans', 'leaves', 'leave_requests'];
foreach ($tables as $t) {
    try {
        $result = $pdo->query("SHOW TABLES LIKE '$t'")->rowCount() > 0 ? "EXISTS" : "MISSING";
        echo "$t: $result\n";
    } catch (Exception $e) {
        echo "$t: Error - " . $e->getMessage() . "\n";
    }
}
echo "</pre>";
