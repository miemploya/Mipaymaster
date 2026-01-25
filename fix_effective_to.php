<?php
require_once 'includes/functions.php';

echo "=== Fixing effective_to values ===\n\n";

// Fix 0000-00-00 to NULL
$count = $pdo->exec("UPDATE employee_salary_adjustments SET effective_to = NULL WHERE effective_to = '0000-00-00'");
echo "Updated $count records with invalid effective_to\n";

// Verify
$stmt = $pdo->query("SELECT id, effective_from, effective_to FROM employee_salary_adjustments");
foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $inc) {
    echo "  ID " . $inc['id'] . ": from=" . $inc['effective_from'] . ", to=" . ($inc['effective_to'] ?? 'NULL') . "\n";
}
