<?php
require_once 'includes/functions.php';

$company_id = 2; // Openclax

echo "Verification for Company $company_id\n";
echo "----------------------------------\n";

// 1. Departments + Counts
$stmt = $pdo->prepare("
    SELECT d.id, d.name, 
           (SELECT COUNT(*) FROM employees e WHERE e.department_id = d.id) as employee_count 
    FROM departments d 
    WHERE d.company_id = ? 
    ORDER BY d.name ASC
");
$stmt->execute([$company_id]);
$results = $stmt->fetchAll(PDO::FETCH_ASSOC);

echo "Department Counts (as shown in UI):\n";
$total_ui = 0;
foreach($results as $r) {
    echo " - {$r['name']}: {$r['employee_count']}\n";
    $total_ui += $r['employee_count'];
}

// 2. Real Total
$stmt = $pdo->prepare("SELECT COUNT(*) FROM employees WHERE company_id = ?");
$stmt->execute([$company_id]);
$real_total = $stmt->fetchColumn();

echo "\nTotal Employees Data: $real_total\n";
echo "Sum of Department Counts: $total_ui\n";

if ($real_total == $total_ui) {
    echo "SUCCESS: Counts match!\n";
} else {
    echo "WARNING: Check for Unassigned Employees\n";
    
    // Check unassigned
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM employees WHERE company_id = ? AND (department_id IS NULL OR department_id = 0)");
    $stmt->execute([$company_id]);
    $unassigned = $stmt->fetchColumn();
    echo "Unassigned Employees: $unassigned\n";
}
?>
