<?php
require_once 'includes/functions.php';

echo "=== Schema Check ===\n\n";

echo "1. employees columns:\n";
$stmt = $pdo->query("DESCRIBE employees");
foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $col) {
    echo "  " . $col['Field'] . " (" . $col['Type'] . ")\n";
}

echo "\n2. salary_categories columns:\n";
try {
    $stmt = $pdo->query("DESCRIBE salary_categories");
    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $col) {
        echo "  " . $col['Field'] . " (" . $col['Type'] . ")\n";
    }
} catch (Exception $e) {
    echo "  Table does not exist: " . $e->getMessage() . "\n";
}

echo "\n3. employee_salary_adjustments columns:\n";
$stmt = $pdo->query("DESCRIBE employee_salary_adjustments");
foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $col) {
    echo "  " . $col['Field'] . " (" . $col['Type'] . ")\n";
}
