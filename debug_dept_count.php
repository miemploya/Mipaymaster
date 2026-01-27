<?php
require_once 'includes/functions.php';

$company_id = 2; // Openclax Limited

echo "Debug Employee Data for Company $company_id\n";
echo "----------------------------------------\n";

// 1. Sample Data
$stmt = $pdo->prepare("SELECT id, first_name, department, department_id FROM employees WHERE company_id = ?");
$stmt->execute([$company_id]);
$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

if (!$rows) {
    echo "No employees found for company $company_id.\n";
} else {
    foreach($rows as $r) {
        $depId = $r['department_id'] === null ? 'NULL' : $r['department_id'];
        echo "Emp ID: {$r['id']} | Dept (Text): '{$r['department']}' | Dept ID: $depId\n";
    }
}

// 2. Check Matching Departments
echo "\nMatching Candidates:\n";
foreach($rows as $r) {
    if ($r['department'] && !$r['department_id']) {
        $stmt = $pdo->prepare("SELECT id, name FROM departments WHERE company_id = ? AND name LIKE ?");
        $stmt->execute([$company_id, $r['department']]); 
        // Note: Strict match first, then maybe fuzzy if needed
        $d = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($d) {
            echo "MATCH FOUND: '{$r['department']}' => ID {$d['id']} ({$d['name']})\n";
        } else {
            echo "NO MATCH: '{$r['department']}'\n";
        }
    }
}
?>
