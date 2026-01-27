<?php
require_once 'includes/functions.php';

// Check DB Name
echo "Connected. Checking tables...\n";
$stmt = $pdo->query("SELECT DATABASE()");
echo "Current DB: " . $stmt->fetchColumn() . "\n";

// Describe Employees
echo "\nSchema for 'employees':\n";
$stmt = $pdo->query("DESCRIBE employees");
$cols = $stmt->fetchAll(PDO::FETCH_ASSOC);
foreach ($cols as $c) {
    echo "{$c['Field']} ({$c['Type']})\n";
}

// Check content
echo "\nData for Company 2:\n";
$stmt = $pdo->query("SELECT id, employment_status FROM employees WHERE company_id=2");
$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
print_r($rows);

// Try explicit update for one ID
echo "\nAttempting Single Update for ID 4...\n";
$stmt = $pdo->prepare("UPDATE employees SET employment_status='Active' WHERE id=4");
$stmt->execute();
echo "Affected: " . $stmt->rowCount() . "\n";
print_r($pdo->errorInfo());
