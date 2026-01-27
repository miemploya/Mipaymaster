<?php
// fix_employee_status.php
require_once 'includes/functions.php';

$company_id = 2; // Openclax

echo "Fixing Employment Status for Company $company_id...\n";

// 1. Identify rows to fix
$stmt = $pdo->prepare("SELECT id, first_name FROM employees WHERE company_id = ? AND (employment_status IS NULL OR employment_status = '')");
$stmt->execute([$company_id]);
$bad_rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

if (count($bad_rows) > 0) {
    echo "Found " . count($bad_rows) . " employees with invalid status:\n";
    foreach ($bad_rows as $r) {
        echo "- {$r['first_name']} (ID: {$r['id']})\n";
    }

    // 2. Update
    $stmt = $pdo->prepare("UPDATE employees SET employment_status = 'Active' WHERE company_id = ? AND (employment_status IS NULL OR employment_status = '')");
    $stmt->execute([$company_id]);
    
    echo "\nSuccessfully updated " . $stmt->rowCount() . " records to 'Active'.\n";
} else {
    echo "All employees have valid status (or non-empty).\n";
}
