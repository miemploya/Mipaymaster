<?php
require_once __DIR__ . '/includes/functions.php';
header('Content-Type: text/plain');

echo "=== EMPLOYEES TABLE STRUCTURE ===\n\n";
$cols = $pdo->query("DESCRIBE employees")->fetchAll(PDO::FETCH_ASSOC);
foreach ($cols as $col) {
    echo $col['Field'] . " (" . $col['Type'] . ")\n";
}

echo "\n=== SAMPLE EMPLOYEE DATA ===\n";
$sample = $pdo->query("SELECT * FROM employees LIMIT 1")->fetch(PDO::FETCH_ASSOC);
print_r($sample);
?>
