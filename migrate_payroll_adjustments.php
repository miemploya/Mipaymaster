<?php
/**
 * Migration: Create payroll_adjustments table for one-time bonuses/deductions
 * Run this script once to create the table structure
 */

require_once __DIR__ . '/includes/functions.php';

echo "<h2>Payroll Adjustments Table Migration</h2>";

try {
    // Create payroll_adjustments table
    $sql = "CREATE TABLE IF NOT EXISTS payroll_adjustments (
        id INT AUTO_INCREMENT PRIMARY KEY,
        company_id INT NOT NULL,
        employee_id INT NOT NULL,
        payroll_month INT NOT NULL,
        payroll_year INT NOT NULL,
        type ENUM('bonus', 'deduction') NOT NULL,
        name VARCHAR(100) NOT NULL,
        amount DECIMAL(15,2) NOT NULL DEFAULT 0,
        notes TEXT,
        created_by INT,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        
        INDEX idx_company_period (company_id, payroll_month, payroll_year),
        INDEX idx_employee (employee_id),
        
        FOREIGN KEY (company_id) REFERENCES companies(id) ON DELETE CASCADE,
        FOREIGN KEY (employee_id) REFERENCES employees(id) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";
    
    $pdo->exec($sql);
    echo "<p style='color:green'>✓ Created payroll_adjustments table</p>";
    
    // Show table structure
    $stmt = $pdo->query("DESCRIBE payroll_adjustments");
    echo "<h3>Table Structure:</h3><pre>";
    print_r($stmt->fetchAll(PDO::FETCH_ASSOC));
    echo "</pre>";
    
    echo "<h3>✅ Migration Complete!</h3>";
    echo "<p>You can now use payroll adjustments for one-time bonuses and deductions.</p>";
    
} catch (PDOException $e) {
    echo "<p style='color:red'>Error: " . $e->getMessage() . "</p>";
}
?>
