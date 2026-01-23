<?php
require_once '../config/db.php';
try {
    // Check table
    $stmt = $pdo->query("SHOW TABLES LIKE 'salary_components'");
    echo $stmt->fetch() ? "salary_components EXISTS\n" : "salary_components MISSING\n";
    
    // Check column
    $stmt = $pdo->query("SHOW COLUMNS FROM employees LIKE 'department_id'");
    echo $stmt->fetch() ? "department_id EXISTS in employees\n" : "department_id MISSING in employees\n";
    
} catch (PDOException $e) {
    echo "Error: " . $e->getMessage();
}
?>
