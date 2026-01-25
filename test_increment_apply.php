<?php
require_once 'includes/functions.php';
require_once 'includes/increment_manager.php';

echo "=== Testing Increment Application ===\n\n";

$incManager = new IncrementManager($pdo);

// Test get_active_increment for employee 4 and 5
$test_date = '2026-02-28'; // End of Feb 2026

echo "1. Checking active increments for test date: $test_date\n\n";

foreach ([4, 5] as $emp_id) {
    echo "Employee $emp_id:\n";
    $increment = $incManager->get_active_increment($emp_id, $test_date);
    if ($increment) {
        echo "  Found increment:\n";
        echo "    Type: " . $increment['adjustment_type'] . "\n";
        echo "    Value: " . $increment['adjustment_value'] . "\n";
        echo "    Effective From: " . $increment['effective_from'] . "\n";
        echo "    Status: " . $increment['approval_status'] . "\n";
        echo "    Is Active: " . $increment['is_active'] . "\n";
    } else {
        echo "  No active increment found\n";
    }
    echo "\n";
}

echo "2. Checking employee salary categories:\n\n";
$stmt = $pdo->query("SELECT e.id, e.first_name, e.category_id, sc.name as category_name, sc.base_gross_amount 
    FROM employees e 
    LEFT JOIN salary_categories sc ON e.category_id = sc.id");
foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
    echo "Employee " . $row['id'] . " (" . $row['first_name'] . "):\n";
    echo "  Category: " . ($row['category_name'] ?? 'NULL') . " (ID: " . ($row['category_id'] ?? 'NULL') . ")\n";
    echo "  Base Gross: " . ($row['base_gross_amount'] ?? 'NULL') . "\n\n";
}

echo "3. Simulating payroll calculation:\n\n";
// Get employee with increment
$stmt = $pdo->query("SELECT e.id, e.first_name, e.category_id, sc.base_gross_amount 
    FROM employees e 
    LEFT JOIN salary_categories sc ON e.category_id = sc.id
    WHERE e.id = 5");
$emp = $stmt->fetch(PDO::FETCH_ASSOC);

if ($emp) {
    $base_gross = floatval($emp['base_gross_amount'] ?? 0);
    echo "Employee 5 (" . $emp['first_name'] . "):\n";
    echo "  Base Gross: " . number_format($base_gross) . "\n";
    
    $increment = $incManager->get_active_increment(5, $test_date);
    if ($increment) {
        echo "  Increment Type: " . $increment['adjustment_type'] . "\n";
        echo "  Increment Value: " . $increment['adjustment_value'] . "\n";
        
        $adjusted_gross = $base_gross;
        if ($increment['adjustment_type'] == 'fixed') {
            $adjusted_gross += floatval($increment['adjustment_value']);
        } elseif ($increment['adjustment_type'] == 'percentage') {
            $adjusted_gross += ($base_gross * (floatval($increment['adjustment_value']) / 100));
        } elseif ($increment['adjustment_type'] == 'override') {
            $adjusted_gross = floatval($increment['adjustment_value']);
        }
        
        echo "  Adjusted Gross: " . number_format($adjusted_gross) . "\n";
    } else {
        echo "  No increment found\n";
    }
}
