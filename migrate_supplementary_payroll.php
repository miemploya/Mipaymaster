<?php
/**
 * Database Migration: Add Supplementary Payroll Support
 * Run this once to update the database schema
 */
require_once 'config/db.php';

echo "<h2>Supplementary Payroll Database Migration</h2>";

try {
    // 1. Add payroll_type column to payroll_runs
    echo "<p>Adding payroll_type column to payroll_runs...</p>";
    
    // Check if column exists
    $check = $pdo->query("SHOW COLUMNS FROM payroll_runs LIKE 'payroll_type'");
    if ($check->rowCount() == 0) {
        $pdo->exec("ALTER TABLE payroll_runs ADD COLUMN payroll_type VARCHAR(30) DEFAULT 'regular' AFTER period_year");
        echo "<p style='color:green'>✓ Added payroll_type column</p>";
    } else {
        echo "<p style='color:blue'>→ payroll_type column already exists</p>";
    }

    // 2. Update unique key to include payroll_type
    echo "<p>Updating unique key...</p>";
    
    // Check current unique key
    $keys = $pdo->query("SHOW INDEX FROM payroll_runs WHERE Key_name = 'unique_run'")->fetchAll();
    if (count($keys) > 0) {
        // Check if payroll_type is already in the key
        $hasType = false;
        foreach ($keys as $key) {
            if ($key['Column_name'] === 'payroll_type') {
                $hasType = true;
                break;
            }
        }
        
        if (!$hasType) {
            $pdo->exec("ALTER TABLE payroll_runs DROP INDEX unique_run");
            $pdo->exec("ALTER TABLE payroll_runs ADD UNIQUE KEY unique_run (company_id, period_month, period_year, payroll_type)");
            echo "<p style='color:green'>✓ Updated unique key to include payroll_type</p>";
        } else {
            echo "<p style='color:blue'>→ Unique key already includes payroll_type</p>";
        }
    } else {
        // Create new unique key
        $pdo->exec("ALTER TABLE payroll_runs ADD UNIQUE KEY unique_run (company_id, period_month, period_year, payroll_type)");
        echo "<p style='color:green'>✓ Created unique key with payroll_type</p>";
    }

    // 3. Create supplementary_entries staging table
    echo "<p>Creating supplementary_entries staging table...</p>";
    
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS supplementary_entries (
            id INT AUTO_INCREMENT PRIMARY KEY,
            company_id INT NOT NULL,
            employee_id INT NOT NULL,
            bonus_name VARCHAR(100) NOT NULL,
            amount DECIMAL(15,2) NOT NULL,
            notes TEXT,
            payroll_month INT NOT NULL,
            payroll_year INT NOT NULL,
            session_id VARCHAR(50) NOT NULL,
            created_by INT,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_session (session_id),
            INDEX idx_company_period (company_id, payroll_month, payroll_year)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ");
    echo "<p style='color:green'>✓ Created supplementary_entries table</p>";

    echo "<h3 style='color:green'>Migration Complete!</h3>";
    echo "<p>You can now delete this file.</p>";

} catch (Exception $e) {
    echo "<p style='color:red'>Error: " . htmlspecialchars($e->getMessage()) . "</p>";
}
?>
