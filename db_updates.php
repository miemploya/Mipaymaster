<?php
require_once 'config/db.php';

echo "=== Database Update Started ===\n";

try {
    // 1. Create audit_logs table
    $pdo->exec("CREATE TABLE IF NOT EXISTS audit_logs (
        id INT AUTO_INCREMENT PRIMARY KEY,
        company_id INT NOT NULL,
        user_id INT NULL,
        action VARCHAR(100) NOT NULL,
        details TEXT,
        ip_address VARCHAR(45),
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        INDEX idx_company (company_id),
        INDEX idx_created_at (created_at)
    )");
    echo "✓ audit_logs table created/verified.\n";

    // 2. Add columns to employee_salary_adjustments for rollback
    // Check if rollback_reason exists
    $stmt = $pdo->query("SHOW COLUMNS FROM employee_salary_adjustments LIKE 'rollback_reason'");
    if (!$stmt->fetch()) {
        $pdo->exec("ALTER TABLE employee_salary_adjustments 
            ADD COLUMN rollback_reason TEXT NULL,
            ADD COLUMN rolled_back_at DATETIME NULL,
            ADD COLUMN rolled_back_by INT NULL");
        echo "✓ Rollback columns added to employee_salary_adjustments.\n";
    }

    // 3. Update ENUM for approval_status if needed
    // We need to check if 'rolled_back' is in the ENUM. 
    // Easier to just modify the column to include it.
    // Current: ENUM('pending', 'approved', 'rejected') - inferred. 
    // Let's get current definition.
    $stmt = $pdo->query("SHOW COLUMNS FROM employee_salary_adjustments LIKE 'approval_status'");
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    $type = $row['Type'];
    
    if (strpos($type, "'rolled_back'") === false) {
        $newType = str_replace(")", ",'rolled_back')", $type);
        // Be careful with string replacement if it already had it or format differs.
        // Safer to explicitly set the list we know + rolled_back
        // 'pending','approved','rejected'
        $pdo->exec("ALTER TABLE employee_salary_adjustments MODIFY COLUMN approval_status ENUM('pending','approved','rejected','rolled_back') NOT NULL DEFAULT 'pending'");
        echo "✓ approval_status ENUM updated to include 'rolled_back'.\n";
    }

    echo "=== Database Update Completed ===\n";

} catch (PDOException $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
}
?>
