<?php
require_once 'includes/functions.php';

echo "=== Checking Column Definition ===\n";
$stmt = $pdo->query("SHOW COLUMNS FROM employees LIKE 'employment_status'");
$col = $stmt->fetch(PDO::FETCH_ASSOC);
print_r($col);

echo "\n=== Trying direct SQL ===\n";
try {
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $stmt = $pdo->prepare("UPDATE employees SET employment_status = ? WHERE id = ?");
    $stmt->execute(['active', 4]);
    echo "Rows affected: " . $stmt->rowCount() . "\n";
} catch (PDOException $e) {
    echo "Error: " . $e->getMessage() . "\n";
}

echo "\n=== Check result ===\n";
$stmt = $pdo->query("SELECT id, employment_status FROM employees WHERE id = 4");
$row = $stmt->fetch(PDO::FETCH_ASSOC);
echo "ID 4 status: '" . $row['employment_status'] . "'\n";
