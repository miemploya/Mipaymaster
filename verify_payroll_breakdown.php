<?php
require_once 'config/db.php';

echo "=== ESOSA PAYROLL VERIFICATION ===\n\n";

// Get Esosa's category info
$stmt = $pdo->query("SELECT e.first_name, e.salary_category_id, sc.name as category, sc.base_gross_amount FROM employees e JOIN salary_categories sc ON e.salary_category_id = sc.id WHERE e.first_name = 'Esosa'");
$emp = $stmt->fetch(PDO::FETCH_ASSOC);
echo "Employee: " . $emp['first_name'] . "\n";
echo "Category: " . $emp['category'] . " (ID: " . $emp['salary_category_id'] . ")\n";
echo "Base Gross: " . number_format($emp['base_gross_amount']) . "\n\n";

// Get category breakdown
echo "--- Expected Category Breakdown ---\n";
$stmt = $pdo->prepare("
    SELECT sc.name as component, scb.percentage 
    FROM salary_category_breakdown scb 
    JOIN salary_components sc ON scb.salary_component_id = sc.id 
    WHERE scb.category_id = ?
    ORDER BY percentage DESC
");
$stmt->execute([$emp['salary_category_id']]);
while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
    echo "  " . $row['component'] . ": " . $row['percentage'] . "%\n";
}

// Get latest payroll snapshot for Esosa
echo "\n--- Actual Payroll Snapshot Data ---\n";
$stmt = $pdo->query("
    SELECT pe.gross_salary, pe.total_allowances, pe.total_deductions, pe.net_pay, ps.snapshot_json 
    FROM payroll_entries pe 
    JOIN payroll_snapshots ps ON ps.payroll_entry_id = pe.id 
    JOIN employees e ON pe.employee_id = e.id 
    WHERE e.first_name = 'Esosa' 
    ORDER BY pe.id DESC LIMIT 1
");
$entry = $stmt->fetch(PDO::FETCH_ASSOC);

if ($entry) {
    echo "Gross Salary: " . number_format($entry['gross_salary']) . "\n";
    echo "Total Allowances: " . number_format($entry['total_allowances']) . "\n";
    echo "Total Deductions: " . number_format($entry['total_deductions']) . "\n";
    echo "Net Pay: " . number_format($entry['net_pay']) . "\n\n";
    
    $snapshot = json_decode($entry['snapshot_json'], true);
    
    echo "  Adjusted Gross: " . number_format($snapshot['adjusted_gross']) . "\n\n";
    
    echo "  Breakdown from Snapshot:\n";
    foreach ($snapshot['breakdown'] as $component => $amount) {
        $percentage = ($snapshot['adjusted_gross'] > 0) ? round(($amount / $snapshot['adjusted_gross']) * 100, 1) : 0;
        echo "    $component: " . number_format($amount) . " ($percentage%)\n";
    }
    
    echo "\n  Statutory Deductions:\n";
    echo "    PAYE: " . number_format($snapshot['statutory']['paye']) . "\n";
    echo "    Pension Employee: " . number_format($snapshot['statutory']['pension_employee']) . "\n";
    echo "    NHIS: " . number_format($snapshot['statutory']['nhis']) . "\n";
    echo "    NHF: " . number_format($snapshot['statutory']['nhf']) . "\n";
} else {
    echo "No payroll entries found for Esosa.\n";
}
