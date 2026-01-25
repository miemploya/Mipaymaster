<?php
require_once 'includes/functions.php';

echo "=== Adding missing columns to employee_salary_adjustments ===\n";

$columns_to_add = [
    "adjustment_value DECIMAL(15,2) NOT NULL DEFAULT 0",
    "effective_from DATE DEFAULT NULL",
    "effective_to DATE DEFAULT NULL",
    "is_active TINYINT(1) NOT NULL DEFAULT 1",
    "reason TEXT DEFAULT NULL",
    "approved_at DATETIME DEFAULT NULL"
];

foreach ($columns_to_add as $colDef) {
    $colName = explode(' ', $colDef)[0];
    
    // Check if column exists
    $check = $pdo->query("SHOW COLUMNS FROM employee_salary_adjustments LIKE '$colName'");
    if ($check->rowCount() == 0) {
        try {
            $pdo->exec("ALTER TABLE employee_salary_adjustments ADD COLUMN $colDef");
            echo "[ADDED] $colName\n";
        } catch (Exception $e) {
            echo "[ERROR] $colName: " . $e->getMessage() . "\n";
        }
    } else {
        echo "[EXISTS] $colName\n";
    }
}

// Check adjustment_type - it's an ENUM
$check = $pdo->query("SHOW COLUMNS FROM employee_salary_adjustments LIKE 'adjustment_type'");
if ($check->rowCount() == 0) {
    try {
        $pdo->exec("ALTER TABLE employee_salary_adjustments MODIFY COLUMN adjustment_type ENUM('fixed', 'percentage', 'override', 'increment', 'decrement') NOT NULL DEFAULT 'fixed'");
        echo "[MODIFIED] adjustment_type\n";
    } catch (Exception $e) {
        // Maybe add instead
        try {
            $pdo->exec("ALTER TABLE employee_salary_adjustments ADD COLUMN adjustment_type ENUM('fixed', 'percentage', 'override', 'increment', 'decrement') NOT NULL DEFAULT 'fixed' AFTER employee_id");
            echo "[ADDED] adjustment_type\n";
        } catch (Exception $e2) {
            echo "[ERROR] adjustment_type: " . $e2->getMessage() . "\n";
        }
    }
} else {
    echo "[EXISTS] adjustment_type\n";
}

echo "\n=== Final Table Structure ===\n";
$stmt = $pdo->query("DESCRIBE employee_salary_adjustments");
foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $col) {
    echo $col['Field'] . " (" . $col['Type'] . ")\n";
}
