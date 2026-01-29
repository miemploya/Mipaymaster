<?php
/**
 * Migration: Create overtime tables and settings
 * Per PIT Act - Overtime is taxable income
 * Run this script once to create the table structure
 */

require_once __DIR__ . '/includes/functions.php';

echo "<h2>Overtime System Migration</h2>";
echo "<p>Setting up overtime tracking for payroll. Per PIT Act, overtime is taxable income.</p>";

try {
    // 1. Add overtime columns to statutory_settings
    $alterQueries = [
        "ALTER TABLE statutory_settings ADD COLUMN IF NOT EXISTS overtime_enabled TINYINT(1) NOT NULL DEFAULT 0 COMMENT 'Enable overtime tracking'",
        "ALTER TABLE statutory_settings ADD COLUMN IF NOT EXISTS daily_work_hours DECIMAL(4,2) NOT NULL DEFAULT 8.00 COMMENT 'Standard daily working hours'",
        "ALTER TABLE statutory_settings ADD COLUMN IF NOT EXISTS monthly_work_days INT NOT NULL DEFAULT 22 COMMENT 'Working days per month'",
        "ALTER TABLE statutory_settings ADD COLUMN IF NOT EXISTS overtime_rate DECIMAL(3,2) NOT NULL DEFAULT 1.50 COMMENT 'Overtime multiplier (e.g., 1.5x)'"
    ];
    
    foreach ($alterQueries as $sql) {
        try {
            $pdo->exec($sql);
            // Extract column name for feedback
            preg_match('/ADD COLUMN.*?(\w+)\s+(TINYINT|DECIMAL|INT)/i', $sql, $matches);
            $colName = $matches[1] ?? 'column';
            echo "<p style='color:green'>✓ Added $colName to statutory_settings</p>";
        } catch (PDOException $e) {
            if (strpos($e->getMessage(), 'Duplicate column') !== false) {
                preg_match('/ADD COLUMN.*?(\w+)\s+/i', $sql, $matches);
                $colName = $matches[1] ?? 'column';
                echo "<p style='color:orange'>⚠ $colName already exists</p>";
            } else {
                throw $e;
            }
        }
    }

    // 2. Create payroll_overtime table
    $sql = "CREATE TABLE IF NOT EXISTS payroll_overtime (
        id INT AUTO_INCREMENT PRIMARY KEY,
        company_id INT NOT NULL,
        employee_id INT NOT NULL,
        payroll_month INT NOT NULL,
        payroll_year INT NOT NULL,
        overtime_hours DECIMAL(6,2) NOT NULL DEFAULT 0,
        hourly_rate DECIMAL(12,2) NOT NULL DEFAULT 0 COMMENT 'Calculated from gross/monthly_hours',
        overtime_multiplier DECIMAL(3,2) NOT NULL DEFAULT 1.50,
        overtime_amount DECIMAL(15,2) NOT NULL DEFAULT 0 COMMENT 'Final calculated amount',
        notes TEXT,
        created_by INT,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        
        INDEX idx_company_period (company_id, payroll_month, payroll_year),
        INDEX idx_employee (employee_id),
        
        FOREIGN KEY (company_id) REFERENCES companies(id) ON DELETE CASCADE,
        FOREIGN KEY (employee_id) REFERENCES employees(id) ON DELETE CASCADE,
        
        UNIQUE KEY uk_employee_period (employee_id, payroll_month, payroll_year)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";
    
    $pdo->exec($sql);
    echo "<p style='color:green'>✓ Created payroll_overtime table</p>";

    // 3. Create overtime_suggestions table (for attendance-based suggestions)
    $sql = "CREATE TABLE IF NOT EXISTS overtime_suggestions (
        id INT AUTO_INCREMENT PRIMARY KEY,
        company_id INT NOT NULL,
        employee_id INT NOT NULL,
        suggestion_month INT NOT NULL,
        suggestion_year INT NOT NULL,
        calculated_hours DECIMAL(6,2) NOT NULL DEFAULT 0 COMMENT 'Hours worked beyond standard',
        source VARCHAR(50) DEFAULT 'attendance' COMMENT 'Source: attendance, manual',
        status ENUM('pending', 'approved', 'rejected') DEFAULT 'pending',
        approved_by INT,
        approved_at TIMESTAMP NULL,
        notes TEXT,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        
        INDEX idx_company_period (company_id, suggestion_month, suggestion_year),
        INDEX idx_employee (employee_id),
        INDEX idx_status (status),
        
        FOREIGN KEY (company_id) REFERENCES companies(id) ON DELETE CASCADE,
        FOREIGN KEY (employee_id) REFERENCES employees(id) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";
    
    $pdo->exec($sql);
    echo "<p style='color:green'>✓ Created overtime_suggestions table</p>";

    // Show current settings structure
    echo "<h3>Overtime Settings Structure:</h3><pre>";
    $stmt = $pdo->query("DESCRIBE statutory_settings");
    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $col) {
        if (strpos($col['Field'], 'overtime') !== false || strpos($col['Field'], 'work') !== false) {
            echo $col['Field'] . " - " . $col['Type'] . " (Default: " . ($col['Default'] ?? 'NULL') . ")\n";
        }
    }
    echo "</pre>";

    echo "<h3>✅ Migration Complete!</h3>";
    echo "<div style='background:#e8f5e9;padding:15px;border-radius:8px;margin-top:15px'>";
    echo "<h4>Overtime System Ready:</h4>";
    echo "<ul>";
    echo "<li><strong>Settings:</strong> Configure in Company Setup → Behaviour tab</li>";
    echo "<li><strong>Entry:</strong> Add overtime via payroll sheet modal</li>";
    echo "<li><strong>Suggestions:</strong> Review attendance-based overtime in dedicated page</li>";
    echo "<li><strong>Tax:</strong> Overtime is automatically included in PAYE calculation</li>";
    echo "</ul>";
    echo "</div>";
    
} catch (PDOException $e) {
    echo "<p style='color:red'>Error: " . $e->getMessage() . "</p>";
}
?>
