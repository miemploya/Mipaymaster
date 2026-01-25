<?php
require_once 'includes/functions.php';
require_once 'includes/increment_manager.php';

echo "=== Full Increment Test ===\n\n";

$incManager = new IncrementManager($pdo);

// 1. Check employees with categories
echo "1. Employees with Salary Categories:\n";
$stmt = $pdo->query("SELECT e.id, e.first_name, e.salary_category_id, e.employment_status, sc.name, sc.base_gross_amount 
    FROM employees e 
    LEFT JOIN salary_categories sc ON e.salary_category_id = sc.id");
$employees = $stmt->fetchAll(PDO::FETCH_ASSOC);
foreach ($employees as $emp) {
    echo "  " . $emp['first_name'] . " (ID " . $emp['id'] . "): ";
    if ($emp['salary_category_id']) {
        echo "Category='" . $emp['name'] . "', Base=" . number_format($emp['base_gross_amount']) . ", Status=" . $emp['employment_status'] . "\n";
    } else {
        echo "NO CATEGORY ASSIGNED\n";
    }
}

// 2. Check approved increments
echo "\n2. Approved & Active Increments:\n";
$stmt = $pdo->query("SELECT * FROM employee_salary_adjustments WHERE approval_status = 'approved' AND is_active = 1");
$increments = $stmt->fetchAll(PDO::FETCH_ASSOC);
if (empty($increments)) {
    echo "  No approved active increments. Create and approve one first.\n";
} else {
    foreach ($increments as $inc) {
        echo "  Employee " . $inc['employee_id'] . ": " . $inc['adjustment_type'] . " " . $inc['adjustment_value'] . " (effective " . $inc['effective_from'] . ")\n";
    }
}

// 3. Simulate payroll for employee 5
echo "\n3. Simulating Payroll Calculation for Employee 5:\n";
$emp_id = 5;
$test_date = '2026-02-28';

$stmt = $pdo->prepare("SELECT e.id, e.first_name, e.salary_category_id, sc.base_gross_amount 
    FROM employees e 
    LEFT JOIN salary_categories sc ON e.salary_category_id = sc.id
    WHERE e.id = ?");
$stmt->execute([$emp_id]);
$emp = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$emp) {
    echo "  Employee not found\n";
} elseif (!$emp['salary_category_id']) {
    echo "  Employee has NO salary category assigned - cannot calculate payroll!\n";
} else {
    $base_gross = floatval($emp['base_gross_amount']);
    echo "  Base Gross: N" . number_format($base_gross) . "\n";
    
    // Get increment
    $increment = $incManager->get_active_increment($emp_id, $test_date);
    $adjusted_gross = $base_gross;
    
    if ($increment) {
        echo "  Active Increment: " . $increment['adjustment_type'] . " " . $increment['adjustment_value'] . "\n";
        
        if ($increment['adjustment_type'] == 'fixed') {
            $adjusted_gross += floatval($increment['adjustment_value']);
        } elseif ($increment['adjustment_type'] == 'percentage') {
            $adjusted_gross += ($base_gross * (floatval($increment['adjustment_value']) / 100));
        } elseif ($increment['adjustment_type'] == 'override') {
            $adjusted_gross = floatval($increment['adjustment_value']);
        }
        
        echo "  Adjusted Gross: N" . number_format($adjusted_gross) . "\n";
        echo "  Difference: N" . number_format($adjusted_gross - $base_gross) . "\n";
    } else {
        echo "  No active increment for date $test_date\n";
    }
}
