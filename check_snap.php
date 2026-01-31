<?php
require_once __DIR__ . '/config/db.php';

echo "=== payroll_snapshots columns ===\n";
$stmt = $pdo->query("DESCRIBE payroll_snapshots");
while ($col = $stmt->fetch(PDO::FETCH_ASSOC)) {
    echo "- " . $col['Field'] . "\n";
}
?>
