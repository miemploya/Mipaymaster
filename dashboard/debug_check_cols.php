<?php
require_once '../config/db.php';
try {
    echo "Columns in 'employees':\n";
    $stmt = $pdo->query("SHOW COLUMNS FROM employees");
    $cols = $stmt->fetchAll(PDO::FETCH_ASSOC);
    foreach ($cols as $col) {
        echo $col['Field'] . "\n";
    }
} catch (PDOException $e) {
    echo "Error: " . $e->getMessage();
}
?>
