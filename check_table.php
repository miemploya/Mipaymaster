<?php
require_once 'includes/functions.php';

echo "=== Table Structure ===\n";
$stmt = $pdo->query("DESCRIBE employee_salary_adjustments");
foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $col) {
    echo $col['Field'] . " (" . $col['Type'] . ")\n";
}
