<?php
require_once 'includes/functions.php';

echo "=== Debug Increment Query ===\n\n";

// Check all increments
echo "1. All increments in database:\n";
$stmt = $pdo->query("SELECT * FROM employee_salary_adjustments");
foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $inc) {
    echo "  ID " . $inc['id'] . ":\n";
    echo "    employee_id: " . $inc['employee_id'] . "\n";
    echo "    type: '" . $inc['adjustment_type'] . "'\n";
    echo "    value: " . $inc['adjustment_value'] . "\n";
    echo "    effective_from: " . $inc['effective_from'] . "\n";
    echo "    effective_to: " . ($inc['effective_to'] ?? 'NULL') . "\n";
    echo "    approval_status: '" . $inc['approval_status'] . "'\n";
    echo "    is_active: " . $inc['is_active'] . "\n\n";
}

// Test query for employee 5
echo "2. Testing query for employee 5, date 2026-02-28:\n";
$emp_id = 5;
$date = '2026-02-28';
$stmt = $pdo->prepare("SELECT * FROM employee_salary_adjustments 
    WHERE employee_id = ? 
    AND approval_status = 'approved' 
    AND is_active = 1
    AND effective_from <= ?
    AND (effective_to IS NULL OR effective_to >= ?)
    ORDER BY effective_from DESC, id DESC 
    LIMIT 1");
$stmt->execute([$emp_id, $date, $date]);
$result = $stmt->fetch(PDO::FETCH_ASSOC);
if ($result) {
    echo "  Found: " . print_r($result, true) . "\n";
} else {
    echo "  No result found. Checking why...\n";
    
    // Check each condition
    echo "  - Has approval_status='approved'? ";
    $stmt = $pdo->prepare("SELECT id FROM employee_salary_adjustments WHERE employee_id = ? AND approval_status = 'approved'");
    $stmt->execute([$emp_id]);
    echo ($stmt->fetch() ? "YES" : "NO") . "\n";
    
    echo "  - Has is_active=1? ";
    $stmt = $pdo->prepare("SELECT id FROM employee_salary_adjustments WHERE employee_id = ? AND is_active = 1");
    $stmt->execute([$emp_id]);
    echo ($stmt->fetch() ? "YES" : "NO") . "\n";
    
    echo "  - Has effective_from <= '$date'? ";
    $stmt = $pdo->prepare("SELECT id, effective_from FROM employee_salary_adjustments WHERE employee_id = ? AND effective_from <= ?");
    $stmt->execute([$emp_id, $date]);
    $r = $stmt->fetch();
    echo ($r ? "YES (effective_from=" . $r['effective_from'] . ")" : "NO") . "\n";
}
