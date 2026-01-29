<?php
/**
 * Check employee table schema for photo column
 */
require_once 'config/db.php';

echo "=== Employee Table Schema ===\n\n";

$stmt = $pdo->query("DESCRIBE employees");
$columns = $stmt->fetchAll(PDO::FETCH_ASSOC);

foreach ($columns as $col) {
    echo $col['Field'] . " - " . $col['Type'] . "\n";
}
