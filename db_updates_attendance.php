<?php
require_once 'includes/functions.php';

try {
    echo "Starting Attendance Module Database Updates...\n";

    // 1. Add attendance_method to companies table
    echo "Checking companies table for attendance_method column...\n";
    $cols = $pdo->query("SHOW COLUMNS FROM companies LIKE 'attendance_method'")->fetchAll();
    if (count($cols) == 0) {
        $pdo->exec("ALTER TABLE companies ADD COLUMN attendance_method ENUM('manual', 'self', 'biometric') DEFAULT 'manual' AFTER email");
        echo "Added 'attendance_method' column to companies table.\n";
    } else {
        echo "'attendance_method' column already exists.\n";
    }

    // 2. Create attendance_policies table
    echo "Creating attendance_policies table...\n";
    $pdo->exec("CREATE TABLE IF NOT EXISTS attendance_policies (
        id INT AUTO_INCREMENT PRIMARY KEY,
        company_id INT NOT NULL,
        check_in_start TIME DEFAULT '08:00:00',
        check_in_end TIME DEFAULT '09:00:00',
        check_out_start TIME DEFAULT '17:00:00',
        check_out_end TIME DEFAULT '18:00:00',
        grace_period_minutes INT DEFAULT 15,
        enable_ip_logging TINYINT(1) DEFAULT 1,
        allowed_ips TEXT,
        require_supervisor_confirmation TINYINT(1) DEFAULT 1,
        lateness_deduction_enabled TINYINT(1) DEFAULT 1,
        overtime_enabled TINYINT(1) DEFAULT 0,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        FOREIGN KEY (company_id) REFERENCES companies(id) ON DELETE CASCADE,
        UNIQUE KEY unique_company_policy (company_id)
    )");
    echo "attendance_policies table check complete.\n";

    // 3. Create attendance_logs table
    echo "Creating attendance_logs table...\n";
    $pdo->exec("CREATE TABLE IF NOT EXISTS attendance_logs (
        id INT AUTO_INCREMENT PRIMARY KEY,
        company_id INT NOT NULL,
        employee_id INT NOT NULL,
        date DATE NOT NULL,
        check_in_time DATETIME DEFAULT NULL,
        check_out_time DATETIME DEFAULT NULL,
        status ENUM('present', 'late', 'early', 'absent', 'on_leave', 'overridden') DEFAULT 'absent',
        method_used ENUM('manual', 'self', 'biometric') DEFAULT 'manual',
        auto_deduction_amount DECIMAL(10,2) DEFAULT 0.00,
        final_deduction_amount DECIMAL(10,2) DEFAULT 0.00,
        override_reason VARCHAR(255) DEFAULT NULL,
        confirmed_by INT DEFAULT NULL, -- User ID of supervisor/HR
        confirmed_at DATETIME DEFAULT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        FOREIGN KEY (company_id) REFERENCES companies(id) ON DELETE CASCADE,
        FOREIGN KEY (employee_id) REFERENCES employees(id) ON DELETE CASCADE,
        UNIQUE KEY unique_daily_log (employee_id, date)
    )");
    echo "attendance_logs table check complete.\n";

    echo "Database updates completed successfully.\n";

} catch (PDOException $e) {
    die("Database Error: " . $e->getMessage() . "\n");
}
?>
