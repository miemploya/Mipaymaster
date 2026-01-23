<?php
require_once '../config/db.php';
try {
    $stmt = $pdo->query("SHOW TABLES LIKE 'salary_components'");
    if ($stmt->fetch()) {
        echo "Table 'salary_components' EXISTS.\n";
    } else {
        echo "Table 'salary_components' DOES NOT EXIST.\n";
    }
} catch (PDOException $e) {
    echo "Error: " . $e->getMessage();
}
?>
