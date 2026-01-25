<?php
require_once 'includes/functions.php';
require_once 'includes/increment_manager.php';

echo "=== Diagnosing Increment Application Issue ===\n\n";

// 1. Check employees and their category assignments
echo "1. Employee Category Assignment:\n";
$stmt = $pdo->query("SELECT e.id, e.first_name, e.category_id FROM employees e");
$employees = $stmt->fetchAll(PDO::FETCH_ASSOC);
foreach ($employees as $emp) {
    echo "  Employee " . $emp['id'] . " (" . $emp['first_name'] . "): category_id = " . ($emp['category_id'] ?? 'NULL') . "\n";
}

// 2. Check salary categories
echo "\n2. Available Salary Categories:\n";
$stmt = $pdo->query("SELECT id, name, base_gross_amount FROM salary_categories");
$categories = $stmt->fetchAll(PDO::FETCH_ASSOC);
if (empty($categories)) {
    echo "  NO SALARY CATEGORIES EXIST!\n";
} else {
    foreach ($categories as $cat) {
        echo "  ID " . $cat['id'] . ": " . $cat['name'] . " (Base: " . number_format($cat['base_gross_amount']) . ")\n";
    }
}

// 3. Check increments
echo "\n3. All Increments in Database:\n";
$stmt = $pdo->query("SELECT * FROM employee_salary_adjustments");
$increments = $stmt->fetchAll(PDO::FETCH_ASSOC);
foreach ($increments as $inc) {
    echo "  ID " . $inc['id'] . ": Employee " . $inc['employee_id'] . ", Type=" . $inc['adjustment_type'] . ", Value=" . $inc['adjustment_value'] . ", Status=" . $inc['approval_status'] . ", is_active=" . $inc['is_active'] . "\n";
}

// 4. Check payroll runs
echo "\n4. Recent Payroll Runs:\n";
$stmt = $pdo->query("SELECT * FROM payroll_runs ORDER BY id DESC LIMIT 5");
$runs = $stmt->fetchAll(PDO::FETCH_ASSOC);
if (empty($runs)) {
    echo "  No payroll runs exist yet. Increments only apply when payroll is run.\n";
} else {
    foreach ($runs as $run) {
        echo "  Run ID " . $run['id'] . ": " . $run['period_month'] . "/" . $run['period_year'] . " - Status: " . $run['status'] . "\n";
    }
}

echo "\n5. DIAGNOSIS:\n";
$hasNullCategory = false;
foreach ($employees as $emp) {
    if (empty($emp['category_id'])) {
        $hasNullCategory = true;
    }
}
if ($hasNullCategory) {
    echo "  [ISSUE] Some employees have NULL category_id - they need to be assigned to a salary category!\n";
}
if (empty($categories)) {
    echo "  [ISSUE] No salary categories exist - create them first!\n";
}
if (empty($runs)) {
    echo "  [INFO] No payroll runs yet - increments only modify gross DURING payroll calculation.\n";
}
