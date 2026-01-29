<?php
/**
 * Migration: Add Shift & Daily Attendance System Tables
 * Run via browser: http://localhost/Mipaymaster/migrations/migrate_shift_attendance.php
 */
require_once __DIR__ . '/../includes/functions.php';

echo "<pre>";
echo "=== Shift & Daily Attendance System Migration ===\n\n";

$errors = [];

// 1. Create attendance_shifts table
echo "Creating attendance_shifts table...\n";
try {
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS attendance_shifts (
            id INT AUTO_INCREMENT PRIMARY KEY,
            company_id INT NOT NULL,
            name VARCHAR(100) NOT NULL,
            description TEXT NULL,
            is_active TINYINT(1) DEFAULT 1,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            INDEX idx_company_active (company_id, is_active)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ");
    echo "✓ attendance_shifts created\n";
} catch (PDOException $e) {
    echo "✗ Error: " . $e->getMessage() . "\n";
    $errors[] = $e->getMessage();
}

// 2. Create attendance_shift_schedules table
echo "Creating attendance_shift_schedules table...\n";
try {
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS attendance_shift_schedules (
            id INT AUTO_INCREMENT PRIMARY KEY,
            shift_id INT NOT NULL,
            day_of_week TINYINT NOT NULL COMMENT '0=Sun,1=Mon...6=Sat',
            is_working_day TINYINT(1) DEFAULT 1,
            check_in_time TIME NULL,
            check_out_time TIME NULL,
            grace_period_minutes INT DEFAULT 15,
            UNIQUE KEY uk_shift_day (shift_id, day_of_week)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ");
    echo "✓ attendance_shift_schedules created\n";
} catch (PDOException $e) {
    echo "✗ Error: " . $e->getMessage() . "\n";
    $errors[] = $e->getMessage();
}

// 3. Create employee_attendance_assignments table
echo "Creating employee_attendance_assignments table...\n";
try {
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS employee_attendance_assignments (
            id INT AUTO_INCREMENT PRIMARY KEY,
            employee_id INT NOT NULL,
            attendance_mode ENUM('shift','daily') NOT NULL DEFAULT 'daily',
            shift_id INT NULL,
            effective_from DATE NOT NULL,
            is_active TINYINT(1) DEFAULT 1,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            INDEX idx_employee_active (employee_id, is_active)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ");
    echo "✓ employee_attendance_assignments created\n";
} catch (PDOException $e) {
    echo "✗ Error: " . $e->getMessage() . "\n";
    $errors[] = $e->getMessage();
}

// 4. Add columns to attendance_policies
echo "\nAdding per-day schedule columns to attendance_policies...\n";

$columns_to_add = [
    "default_mode ENUM('shift','daily','both') DEFAULT 'daily'",
    "auto_absent_enabled TINYINT(1) DEFAULT 0",
    "auto_checkout_enabled TINYINT(1) DEFAULT 0",
    "auto_checkout_hours_after INT DEFAULT 3",
    "mon_enabled TINYINT(1) DEFAULT 1",
    "mon_check_in TIME DEFAULT '08:00:00'",
    "mon_check_out TIME DEFAULT '17:00:00'",
    "mon_grace INT DEFAULT 15",
    "tue_enabled TINYINT(1) DEFAULT 1",
    "tue_check_in TIME DEFAULT '08:00:00'",
    "tue_check_out TIME DEFAULT '17:00:00'",
    "tue_grace INT DEFAULT 15",
    "wed_enabled TINYINT(1) DEFAULT 1",
    "wed_check_in TIME DEFAULT '08:00:00'",
    "wed_check_out TIME DEFAULT '17:00:00'",
    "wed_grace INT DEFAULT 15",
    "thu_enabled TINYINT(1) DEFAULT 1",
    "thu_check_in TIME DEFAULT '08:00:00'",
    "thu_check_out TIME DEFAULT '17:00:00'",
    "thu_grace INT DEFAULT 15",
    "fri_enabled TINYINT(1) DEFAULT 1",
    "fri_check_in TIME DEFAULT '08:00:00'",
    "fri_check_out TIME DEFAULT '17:00:00'",
    "fri_grace INT DEFAULT 15",
    "sat_enabled TINYINT(1) DEFAULT 0",
    "sat_check_in TIME DEFAULT '09:00:00'",
    "sat_check_out TIME DEFAULT '13:00:00'",
    "sat_grace INT DEFAULT 15",
    "sun_enabled TINYINT(1) DEFAULT 0",
    "sun_check_in TIME NULL",
    "sun_check_out TIME NULL",
    "sun_grace INT DEFAULT 15"
];

foreach ($columns_to_add as $col_def) {
    $col_name = explode(' ', $col_def)[0];
    try {
        $pdo->exec("ALTER TABLE attendance_policies ADD COLUMN $col_def");
        echo "  + Added $col_name\n";
    } catch (PDOException $e) {
        if (strpos($e->getMessage(), 'Duplicate column') !== false) {
            echo "  ~ $col_name already exists\n";
        } else {
            echo "  ✗ $col_name failed: " . $e->getMessage() . "\n";
            $errors[] = $e->getMessage();
        }
    }
}
echo "✓ attendance_policies updated\n";

// 5. Add columns to attendance_logs
echo "\nAdding auto-marking and reversal columns to attendance_logs...\n";

$log_columns = [
    "is_auto_checkin TINYINT(1) DEFAULT 0",
    "is_auto_checkout TINYINT(1) DEFAULT 0",
    "is_auto_absent TINYINT(1) DEFAULT 0",
    "deduction_reversed TINYINT(1) DEFAULT 0",
    "reversal_reason VARCHAR(255) NULL",
    "reversed_by INT NULL",
    "reversed_at DATETIME NULL"
];

foreach ($log_columns as $col_def) {
    $col_name = explode(' ', $col_def)[0];
    try {
        $pdo->exec("ALTER TABLE attendance_logs ADD COLUMN $col_def");
        echo "  + Added $col_name\n";
    } catch (PDOException $e) {
        if (strpos($e->getMessage(), 'Duplicate column') !== false) {
            echo "  ~ $col_name already exists\n";
        } else {
            echo "  ✗ $col_name failed: " . $e->getMessage() . "\n";
            $errors[] = $e->getMessage();
        }
    }
}
echo "✓ attendance_logs updated\n";

// Summary
echo "\n";
if (empty($errors)) {
    echo "=== Migration Complete! ===\n";
} else {
    echo "=== Migration completed with " . count($errors) . " error(s) ===\n";
}

echo "</pre>";
?>
