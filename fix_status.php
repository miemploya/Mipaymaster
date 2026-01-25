<?php
require_once 'includes/functions.php';

echo "=== Fixing Employment Status ===\n";

// Force update ALL employees to have 'active' status
$result = $pdo->exec("UPDATE employees SET employment_status = 'active'");
echo "Updated rows: " . $result . "\n";

// Verify
echo "\n=== Verification ===\n";
$stmt = $pdo->query("SELECT id, first_name, employment_status FROM employees");
foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $emp) {
    echo "ID " . $emp['id'] . " (" . $emp['first_name'] . "): Status = '" . $emp['employment_status'] . "'\n";
}
