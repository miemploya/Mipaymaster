<?php
/**
 * Migration: Leave Policy System
 * Creates leave_policies, leave_types tables and adds balance columns to employees
 */
require_once 'includes/functions.php';
require_login();

echo "<h2>Leave Policy Migration</h2>";
echo "<pre>";

try {
    $pdo->beginTransaction();
    
    // 1. Create leave_types table (for custom leave types)
    $pdo->exec("CREATE TABLE IF NOT EXISTS leave_types (
        id INT AUTO_INCREMENT PRIMARY KEY,
        company_id INT NOT NULL,
        name VARCHAR(100) NOT NULL,
        is_system TINYINT(1) DEFAULT 0,
        is_active TINYINT(1) DEFAULT 1,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        UNIQUE KEY (company_id, name),
        FOREIGN KEY (company_id) REFERENCES companies(id) ON DELETE CASCADE
    )");
    echo "✅ Created leave_types table\n";
    
    // 2. Create leave_policies table
    $pdo->exec("CREATE TABLE IF NOT EXISTS leave_policies (
        id INT AUTO_INCREMENT PRIMARY KEY,
        company_id INT NOT NULL,
        category_id INT NULL,
        leave_type_id INT NOT NULL,
        days_per_year INT DEFAULT 0,
        carry_over_allowed TINYINT(1) DEFAULT 0,
        max_carry_over_days INT DEFAULT 0,
        is_active TINYINT(1) DEFAULT 1,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP NULL ON UPDATE CURRENT_TIMESTAMP,
        UNIQUE KEY (company_id, category_id, leave_type_id),
        FOREIGN KEY (company_id) REFERENCES companies(id) ON DELETE CASCADE,
        FOREIGN KEY (leave_type_id) REFERENCES leave_types(id) ON DELETE CASCADE
    )");
    echo "✅ Created leave_policies table\n";
    
    // 3. Create employee_leave_balances table (normalized approach)
    $pdo->exec("CREATE TABLE IF NOT EXISTS employee_leave_balances (
        id INT AUTO_INCREMENT PRIMARY KEY,
        employee_id INT NOT NULL,
        leave_type_id INT NOT NULL,
        balance_days DECIMAL(5,1) DEFAULT 0,
        used_days DECIMAL(5,1) DEFAULT 0,
        carry_over_days DECIMAL(5,1) DEFAULT 0,
        year INT NOT NULL,
        updated_at TIMESTAMP NULL ON UPDATE CURRENT_TIMESTAMP,
        UNIQUE KEY (employee_id, leave_type_id, year),
        FOREIGN KEY (employee_id) REFERENCES employees(id) ON DELETE CASCADE,
        FOREIGN KEY (leave_type_id) REFERENCES leave_types(id) ON DELETE CASCADE
    )");
    echo "✅ Created employee_leave_balances table\n";
    
    // 4. Add columns to leave_requests if not exists
    $cols = $pdo->query("SHOW COLUMNS FROM leave_requests LIKE 'days_count'")->fetchAll();
    if (count($cols) == 0) {
        $pdo->exec("ALTER TABLE leave_requests ADD COLUMN days_count INT DEFAULT 1 AFTER end_date");
        echo "✅ Added days_count to leave_requests\n";
    }
    
    $cols = $pdo->query("SHOW COLUMNS FROM leave_requests LIKE 'approved_by'")->fetchAll();
    if (count($cols) == 0) {
        $pdo->exec("ALTER TABLE leave_requests ADD COLUMN approved_by INT NULL AFTER status");
        echo "✅ Added approved_by to leave_requests\n";
    }
    
    $cols = $pdo->query("SHOW COLUMNS FROM leave_requests LIKE 'approved_at'")->fetchAll();
    if (count($cols) == 0) {
        $pdo->exec("ALTER TABLE leave_requests ADD COLUMN approved_at DATETIME NULL AFTER approved_by");
        echo "✅ Added approved_at to leave_requests\n";
    }
    
    // 5. Change leave_type in leave_requests to leave_type_id
    $cols = $pdo->query("SHOW COLUMNS FROM leave_requests LIKE 'leave_type_id'")->fetchAll();
    if (count($cols) == 0) {
        // Keep old column for migration, add new
        $pdo->exec("ALTER TABLE leave_requests ADD COLUMN leave_type_id INT NULL AFTER leave_type");
        echo "✅ Added leave_type_id to leave_requests (old column kept for migration)\n";
    }
    
    // 6. Insert default system leave types for the company
    $company_id = $_SESSION['company_id'];
    $default_types = ['Annual', 'Sick', 'Casual', 'Compassionate', 'Maternity', 'Paternity'];
    
    $stmt_check = $pdo->prepare("SELECT id FROM leave_types WHERE company_id = ? AND name = ?");
    $stmt_insert = $pdo->prepare("INSERT INTO leave_types (company_id, name, is_system) VALUES (?, ?, 1)");
    
    foreach ($default_types as $type) {
        $stmt_check->execute([$company_id, $type]);
        if (!$stmt_check->fetch()) {
            $stmt_insert->execute([$company_id, $type]);
            echo "✅ Added default leave type: $type\n";
        }
    }
    
    $pdo->commit();
    echo "\n✅ Migration complete!\n";
    
} catch (PDOException $e) {
    $pdo->rollBack();
    echo "❌ Error: " . $e->getMessage() . "\n";
}

echo "</pre>";
echo "<p><a href='dashboard/leaves.php'>← Back to Leaves</a> | <a href='dashboard/company.php?tab=leave_policy'>Configure Leave Policy →</a></p>";
?>
