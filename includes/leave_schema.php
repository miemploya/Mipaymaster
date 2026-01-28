<?php
/**
 * Leave System Schema Validator
 * Ensures all leave-related tables have required columns
 * This runs automatically when leave_operations.php is loaded
 */

function ensureLeaveSchema($pdo) {
    static $checked = false;
    if ($checked) return; // Only run once per request
    $checked = true;
    
    try {
        // 1. Ensure employee_leave_balances has all columns
        $columns = $pdo->query("SHOW COLUMNS FROM employee_leave_balances")->fetchAll(PDO::FETCH_COLUMN);
        
        if (!in_array('created_at', $columns)) {
            $pdo->exec("ALTER TABLE employee_leave_balances ADD COLUMN created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP");
        }
        if (!in_array('updated_at', $columns)) {
            $pdo->exec("ALTER TABLE employee_leave_balances ADD COLUMN updated_at TIMESTAMP NULL ON UPDATE CURRENT_TIMESTAMP");
        }
        if (!in_array('carry_over_days', $columns)) {
            $pdo->exec("ALTER TABLE employee_leave_balances ADD COLUMN carry_over_days DECIMAL(5,1) DEFAULT 0");
        }
        
        // 2. Ensure leave_requests has all columns
        $columns = $pdo->query("SHOW COLUMNS FROM leave_requests")->fetchAll(PDO::FETCH_COLUMN);
        
        if (!in_array('days_count', $columns)) {
            $pdo->exec("ALTER TABLE leave_requests ADD COLUMN days_count INT DEFAULT NULL AFTER end_date");
        }
        if (!in_array('approved_by', $columns)) {
            $pdo->exec("ALTER TABLE leave_requests ADD COLUMN approved_by INT NULL");
        }
        if (!in_array('approved_at', $columns)) {
            $pdo->exec("ALTER TABLE leave_requests ADD COLUMN approved_at DATETIME NULL");
        }
        
        // 3. Ensure leave_types has all columns
        $columns = $pdo->query("SHOW COLUMNS FROM leave_types")->fetchAll(PDO::FETCH_COLUMN);
        
        if (!in_array('is_active', $columns)) {
            $pdo->exec("ALTER TABLE leave_types ADD COLUMN is_active TINYINT(1) DEFAULT 1");
        }
        if (!in_array('is_system', $columns)) {
            $pdo->exec("ALTER TABLE leave_types ADD COLUMN is_system TINYINT(1) DEFAULT 0");
        }
        
        // 4. Ensure leave_policies has all columns
        $columns = $pdo->query("SHOW COLUMNS FROM leave_policies")->fetchAll(PDO::FETCH_COLUMN);
        
        if (!in_array('is_active', $columns)) {
            $pdo->exec("ALTER TABLE leave_policies ADD COLUMN is_active TINYINT(1) DEFAULT 1");
        }
        if (!in_array('updated_at', $columns)) {
            $pdo->exec("ALTER TABLE leave_policies ADD COLUMN updated_at TIMESTAMP NULL ON UPDATE CURRENT_TIMESTAMP");
        }
        
    } catch (PDOException $e) {
        // Silent fail - tables might not exist yet
        error_log("Leave schema check: " . $e->getMessage());
    }
}
