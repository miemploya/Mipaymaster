<?php
// debug_payroll_eligibility.php
require_once 'includes/functions.php';

$company_id = 2; // Assuming Openclax

echo "Analyzing Employee Payroll Eligibility for Company $company_id\n";
echo "--------------------------------------------------------\n";

// 1. Get All Employees
$stmt = $pdo->prepare("SELECT id, first_name, last_name, employment_status, salary_category_id FROM employees WHERE company_id = ?");
$stmt->execute([$company_id]);
$employees = $stmt->fetchAll(PDO::FETCH_ASSOC);

echo "Total Employees Found: " . count($employees) . "\n\n";

// 2. Check Categories
$stmt = $pdo->prepare("SELECT id, name FROM salary_categories WHERE company_id = ?");
$stmt->execute([$company_id]);
$categories = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);
echo "Valid Salary Categories: " . print_r($categories, true) . "\n";

echo "Detailed Status:\n";
$eligible_count = 0;

foreach ($employees as $emp) {
    $status_ok = in_array($emp['employment_status'], ['Full Time', 'Active', 'Probation', 'Contract']);
    $cat_ok = isset($categories[$emp['salary_category_id']]);
    
    $reasons = [];
    if (!$status_ok) $reasons[] = "Invalid Status: '{$emp['employment_status']}'";
    if (!$cat_ok) $reasons[] = "Invalid/Missing Category ID: '{$emp['salary_category_id']}'";
    
    if ($status_ok && $cat_ok) {
        echo "[OK] {$emp['first_name']} {$emp['last_name']} (ID: {$emp['id']})\n";
        $eligible_count++;
    } else {
        echo "[FAILED] {$emp['first_name']} {$emp['last_name']} (ID: {$emp['id']}) -> " . implode(", ", $reasons) . "\n";
    }
}

echo "\nSummary: $eligible_count out of " . count($employees) . " are eligible for payroll.\n";
