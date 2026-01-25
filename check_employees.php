<?php
require_once 'includes/functions.php';

echo "=== Employee Data Check ===\n";
$stmt = $pdo->query("SELECT id, first_name, last_name, company_id, employment_status FROM employees");
$employees = $stmt->fetchAll(PDO::FETCH_ASSOC);

echo "Total employees: " . count($employees) . "\n\n";

foreach ($employees as $emp) {
    echo "ID: " . $emp['id'] . "\n";
    echo "  Name: " . $emp['first_name'] . " " . $emp['last_name'] . "\n";
    echo "  Company ID: " . $emp['company_id'] . "\n";
    echo "  Status: '" . $emp['employment_status'] . "'\n";
    echo "  Status length: " . strlen($emp['employment_status']) . "\n\n";
}
