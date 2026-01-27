<?php
require_once 'includes/functions.php';
try {
    $stmt = $pdo->query("SHOW COLUMNS FROM loans");
    $cols = $stmt->fetchAll(PDO::FETCH_COLUMN);
    echo "COLUMNS_START\n" . implode("\n", $cols) . "\nCOLUMNS_END";
} catch(Exception $e) {
    echo "Error: " . $e->getMessage();
}
?>
