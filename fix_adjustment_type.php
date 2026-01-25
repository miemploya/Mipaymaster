<?php
require_once 'includes/functions.php';

echo "=== Checking adjustment_type ENUM ===\n";
$stmt = $pdo->query("SHOW COLUMNS FROM employee_salary_adjustments LIKE 'adjustment_type'");
$col = $stmt->fetch(PDO::FETCH_ASSOC);
echo "Current: " . $col['Type'] . "\n";

// Fix: Modify to include fixed, percentage, override
echo "\n=== Modifying ENUM ===\n";
try {
    $pdo->exec("ALTER TABLE employee_salary_adjustments MODIFY COLUMN adjustment_type ENUM('fixed', 'percentage', 'override', 'increment', 'decrement') NOT NULL DEFAULT 'fixed'");
    echo "[SUCCESS] Modified ENUM\n";
} catch (Exception $e) {
    echo "[ERROR] " . $e->getMessage() . "\n";
}

// Verify
$stmt = $pdo->query("SHOW COLUMNS FROM employee_salary_adjustments LIKE 'adjustment_type'");
$col = $stmt->fetch(PDO::FETCH_ASSOC);
echo "\nNew: " . $col['Type'] . "\n";

// Update existing records with empty type
echo "\n=== Updating empty types ===\n";
$pdo->exec("UPDATE employee_salary_adjustments SET adjustment_type = 'fixed' WHERE adjustment_type = '' OR adjustment_type IS NULL");

// Verify
$stmt = $pdo->query("SELECT id, adjustment_type, adjustment_value FROM employee_salary_adjustments");
foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
    echo "ID " . $row['id'] . ": Type=" . $row['adjustment_type'] . ", Value=" . $row['adjustment_value'] . "\n";
}
