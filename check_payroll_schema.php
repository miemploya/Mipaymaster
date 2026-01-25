<?php
require_once 'includes/functions.php';

echo "=== Schema Check ===\n\n";

echo "1. payroll_runs:\n";
try {
    $stmt = $pdo->query("DESCRIBE payroll_runs");
    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $col) {
        echo "  " . $col['Field'] . " (" . $col['Type'] . ")\n";
    }
} catch (Exception $e) { echo $e->getMessage() . "\n"; }

echo "\n2. payroll_entries:\n";
try {
    $stmt = $pdo->query("DESCRIBE payroll_entries");
    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $col) {
        echo "  " . $col['Field'] . " (" . $col['Type'] . ")\n";
    }
} catch (Exception $e) { echo $e->getMessage() . "\n"; }
?>
