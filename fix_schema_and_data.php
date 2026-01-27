<?php
// fix_schema_and_data.php
require_once 'includes/functions.php';

echo "Modifying 'employees' table schema...\n";

try {
    // 1. Alter Table
    // Add Active, Probation to ENUM. Preserve existing values.
    $sql = "ALTER TABLE employees MODIFY COLUMN employment_status ENUM('Full Time','Part Time','Contract','Intern','Active','Probation') DEFAULT 'Active'";
    $pdo->exec($sql);
    echo "Schema updated successfully. Added 'Active' and 'Probation' to ENUM.\n";
    
    // 2. Update Data
    $company_id = 2;
    $stmt = $pdo->prepare("UPDATE employees SET employment_status = 'Active' WHERE company_id = ? AND (employment_status IS NULL OR employment_status = '')");
    $stmt->execute([$company_id]);
    echo "Updated " . $stmt->rowCount() . " employees to 'Active'.\n";

} catch (PDOException $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
