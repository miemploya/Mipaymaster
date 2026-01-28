<?php
/**
 * Migration: Bonus/Deduction Scope System
 * Adds scope columns and assignment tables for hybrid bonus/deduction assignment
 */

require_once 'includes/functions.php';

echo "<h2>Bonus/Deduction Scope Migration</h2>";
echo "<pre>";

try {
    // === 1. ALTER payroll_bonus_types ===
    echo "1. Updating payroll_bonus_types table...\n";
    
    // Check if scope column exists
    $cols = $pdo->query("SHOW COLUMNS FROM payroll_bonus_types LIKE 'scope'")->fetchAll();
    if (count($cols) == 0) {
        $pdo->exec("ALTER TABLE payroll_bonus_types 
            ADD COLUMN scope ENUM('company', 'category', 'department', 'employee') DEFAULT 'company' AFTER is_active,
            ADD COLUMN category_id INT DEFAULT NULL AFTER scope,
            ADD COLUMN department_id INT DEFAULT NULL AFTER category_id
        ");
        echo "   ✓ Added scope, category_id, department_id to payroll_bonus_types\n";
    } else {
        echo "   • Columns already exist\n";
    }

    // === 2. ALTER payroll_deduction_types ===
    echo "2. Updating payroll_deduction_types table...\n";
    
    $cols = $pdo->query("SHOW COLUMNS FROM payroll_deduction_types LIKE 'scope'")->fetchAll();
    if (count($cols) == 0) {
        $pdo->exec("ALTER TABLE payroll_deduction_types 
            ADD COLUMN scope ENUM('company', 'category', 'department', 'employee') DEFAULT 'company' AFTER is_active,
            ADD COLUMN category_id INT DEFAULT NULL AFTER scope,
            ADD COLUMN department_id INT DEFAULT NULL AFTER category_id
        ");
        echo "   ✓ Added scope, category_id, department_id to payroll_deduction_types\n";
    } else {
        echo "   • Columns already exist\n";
    }

    // === 3. CREATE employee_bonus_assignments ===
    echo "3. Creating employee_bonus_assignments table...\n";
    
    $pdo->exec("CREATE TABLE IF NOT EXISTS employee_bonus_assignments (
        id INT AUTO_INCREMENT PRIMARY KEY,
        employee_id INT NOT NULL,
        bonus_type_id INT NOT NULL,
        is_active TINYINT(1) DEFAULT 1,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        UNIQUE KEY unique_emp_bonus (employee_id, bonus_type_id),
        KEY idx_employee (employee_id),
        KEY idx_bonus (bonus_type_id)
    )");
    echo "   ✓ employee_bonus_assignments table ready\n";

    // === 4. CREATE employee_deduction_assignments ===
    echo "4. Creating employee_deduction_assignments table...\n";
    
    $pdo->exec("CREATE TABLE IF NOT EXISTS employee_deduction_assignments (
        id INT AUTO_INCREMENT PRIMARY KEY,
        employee_id INT NOT NULL,
        deduction_type_id INT NOT NULL,
        is_active TINYINT(1) DEFAULT 1,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        UNIQUE KEY unique_emp_deduction (employee_id, deduction_type_id),
        KEY idx_employee (employee_id),
        KEY idx_deduction (deduction_type_id)
    )");
    echo "   ✓ employee_deduction_assignments table ready\n";

    // === 5. REMOVE Redundant Loan/Lateness Items ===
    echo "5. Removing redundant loan/lateness items...\n";
    
    $stmt = $pdo->prepare("DELETE FROM payroll_deduction_types WHERE LOWER(name) LIKE '%loan%' OR LOWER(name) LIKE '%lateness%'");
    $stmt->execute();
    $deleted_ded = $stmt->rowCount();
    
    $stmt = $pdo->prepare("DELETE FROM payroll_bonus_types WHERE LOWER(name) LIKE '%loan%' OR LOWER(name) LIKE '%lateness%'");
    $stmt->execute();
    $deleted_bon = $stmt->rowCount();
    
    echo "   ✓ Removed $deleted_ded deduction(s) and $deleted_bon bonus(es) containing 'loan' or 'lateness'\n";

    echo "\n========================================\n";
    echo "✅ MIGRATION COMPLETED SUCCESSFULLY!\n";
    echo "========================================\n";

} catch (PDOException $e) {
    echo "\n❌ ERROR: " . $e->getMessage() . "\n";
}

echo "</pre>";
?>
