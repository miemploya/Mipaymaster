<?php
require_once 'includes/functions.php';

echo "=== Checking ENUM values ===\n";
$stmt = $pdo->query("SHOW COLUMNS FROM employees LIKE 'employment_status'");
$col = $stmt->fetch(PDO::FETCH_ASSOC);
echo "Type: " . $col['Type'] . "\n\n";

// Parse ENUM values
preg_match_all("/'([^']+)'/", $col['Type'], $matches);
echo "Valid ENUM values:\n";
foreach ($matches[1] as $val) {
    echo "  - '$val'\n";
}

// Try setting to 'Active' (title case as shown in some ENUMs)
echo "\n=== Trying 'Active' ===\n";
$pdo->exec("UPDATE employees SET employment_status = 'Active' WHERE id = 4");
$stmt = $pdo->query("SELECT employment_status FROM employees WHERE id = 4");
echo "Result: '" . $stmt->fetchColumn() . "'\n";

// If that didn't work, try the first value from ENUM
if (count($matches[1]) > 0) {
    $firstVal = $matches[1][0];
    echo "\n=== Trying first ENUM value '$firstVal' ===\n";
    $pdo->exec("UPDATE employees SET employment_status = '$firstVal'");
    $stmt = $pdo->query("SELECT id, employment_status FROM employees");
    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
        echo "ID " . $row['id'] . ": '" . $row['employment_status'] . "'\n";
    }
}
