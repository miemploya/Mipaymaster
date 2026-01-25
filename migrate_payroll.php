<?php
require_once 'includes/functions.php';

echo "=== Migrating Payroll Schema ===\n\n";

// 1. payroll_entries
echo "1. Creating payroll_entries...\n";
$sql = "CREATE TABLE IF NOT EXISTS payroll_entries (
    id INT AUTO_INCREMENT PRIMARY KEY,
    payroll_run_id INT NOT NULL,
    employee_id INT NOT NULL,
    gross_salary DECIMAL(15,2) NOT NULL DEFAULT 0,
    total_allowances DECIMAL(15,2) NOT NULL DEFAULT 0,
    total_deductions DECIMAL(15,2) NOT NULL DEFAULT 0,
    net_pay DECIMAL(15,2) NOT NULL DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    KEY (payroll_run_id),
    KEY (employee_id)
)";
try {
    $pdo->exec($sql);
    echo "[SUCCESS] payroll_entries created/verified.\n";
} catch (Exception $e) { echo "[ERROR] " . $e->getMessage() . "\n"; }

// 2. payroll_snapshots
echo "\n2. Creating payroll_snapshots...\n";
$sql = "CREATE TABLE IF NOT EXISTS payroll_snapshots (
    id INT AUTO_INCREMENT PRIMARY KEY,
    payroll_entry_id INT NOT NULL,
    snapshot_json JSON NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    KEY (payroll_entry_id)
)";
try {
    $pdo->exec($sql);
    echo "[SUCCESS] payroll_snapshots created/verified.\n";
} catch (Exception $e) { echo "[ERROR] " . $e->getMessage() . "\n"; }

// 3. Update payroll_runs columns
echo "\n3. Updating payroll_runs columns...\n";
// Rename month -> period_month ? No, easier to stick with schema or code?
// Code uses period_month extensively in my new engine logic. 
// Existing schema uses `month`, `year`.
// Let's standardise on `period_month` and `period_year` to avoid ambiguity with keywords.
// Rename column.
try {
    // Check if period_month exists
    $stmt = $pdo->query("SHOW COLUMNS FROM payroll_runs LIKE 'period_month'");
    if (!$stmt->fetch()) {
        $pdo->exec("ALTER TABLE payroll_runs CHANGE month period_month INT(11) NOT NULL");
        $pdo->exec("ALTER TABLE payroll_runs CHANGE year period_year INT(11) NOT NULL");
        echo "[SUCCESS] Renamed month/year to period_month/period_year.\n";
    } else {
        echo "[INFO] Columns already renamed.\n";
    }
} catch (Exception $e) { echo "[ERROR] " . $e->getMessage() . "\n"; }

// 4. Update payroll_runs status ENUM
echo "\n4. Updating payroll_runs status ENUM...\n";
try {
    $pdo->exec("ALTER TABLE payroll_runs MODIFY COLUMN status ENUM('draft', 'locked', 'approved', 'paid', 'reversed') NOT NULL DEFAULT 'draft'");
    echo "[SUCCESS] Updated status ENUM.\n";
} catch (Exception $e) { echo "[ERROR] " . $e->getMessage() . "\n"; }


// 5. Add locking columns if missing
echo "\n5. Adding lock columns...\n";
try {
    $pdo->exec("ALTER TABLE payroll_runs ADD COLUMN locked_at DATETIME DEFAULT NULL");
    $pdo->exec("ALTER TABLE payroll_runs ADD COLUMN locked_by INT DEFAULT NULL");
    echo "[SUCCESS] Added lock columns.\n";
} catch (Exception $e) { echo "[INFO] Lock columns likely exist or error: " . $e->getMessage() . "\n"; }

?>
