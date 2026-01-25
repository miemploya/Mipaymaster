<?php
require_once 'includes/functions.php';

echo "=== Testing Increment Creation ===\n";

require_once 'includes/increment_manager.php';

$incManager = new IncrementManager($pdo);

// Test with employee ID 4, fixed type, 50000 value
$result = $incManager->add_increment(4, 'fixed', 50000, '2026-02-01', 'Test increment', null);

echo "Result:\n";
print_r($result);

echo "\n=== Checking Database ===\n";
$stmt = $pdo->query("SELECT * FROM employee_salary_adjustments ORDER BY id DESC LIMIT 3");
foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
    echo "ID: " . $row['id'] . "\n";
    echo "  Employee: " . $row['employee_id'] . "\n";
    echo "  Type: " . $row['adjustment_type'] . "\n";
    echo "  Value: " . $row['adjustment_value'] . "\n";
    echo "  Effective From: " . $row['effective_from'] . "\n";
    echo "  Status: " . $row['approval_status'] . "\n\n";
}
