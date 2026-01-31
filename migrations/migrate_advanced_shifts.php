<?php
/**
 * Migration: Add Advanced Shift Types (Fixed, Rotational, Weekly, Monthly)
 * Run via browser: http://localhost/Mipaymaster/migrations/migrate_advanced_shifts.php
 */
require_once __DIR__ . '/../includes/functions.php';

echo "<pre>";
echo "=== Advanced Shift Types Migration ===\n\n";

$errors = [];

// 1. Add shift_type column to attendance_shifts
echo "1. Adding shift_type column to attendance_shifts...\n";
try {
    $pdo->exec("
        ALTER TABLE attendance_shifts
        ADD COLUMN shift_type ENUM('fixed', 'rotational', 'weekly', 'monthly') DEFAULT 'fixed' AFTER name
    ");
    echo "   ✓ shift_type column added\n";
} catch (PDOException $e) {
    if (strpos($e->getMessage(), 'Duplicate column') !== false) {
        echo "   ~ shift_type already exists\n";
    } else {
        echo "   ✗ Error: " . $e->getMessage() . "\n";
        $errors[] = $e->getMessage();
    }
}

// 2. Add weeks_on and weeks_off columns for Weekly shifts
echo "\n2. Adding weeks_on/weeks_off columns to attendance_shifts...\n";
$weekly_cols = [
    "weeks_on INT DEFAULT 1 AFTER shift_type",
    "weeks_off INT DEFAULT 1 AFTER weeks_on"
];

foreach ($weekly_cols as $col_def) {
    $col_name = explode(' ', $col_def)[0];
    try {
        $pdo->exec("ALTER TABLE attendance_shifts ADD COLUMN $col_def");
        echo "   + Added $col_name\n";
    } catch (PDOException $e) {
        if (strpos($e->getMessage(), 'Duplicate column') !== false) {
            echo "   ~ $col_name already exists\n";
        } else {
            echo "   ✗ $col_name failed: " . $e->getMessage() . "\n";
            $errors[] = $e->getMessage();
        }
    }
}

// 3. Add cycle_start_date to employee_attendance_assignments
echo "\n3. Adding cycle_start_date to employee_attendance_assignments...\n";
try {
    $pdo->exec("
        ALTER TABLE employee_attendance_assignments
        ADD COLUMN cycle_start_date DATE NULL AFTER shift_id
    ");
    echo "   ✓ cycle_start_date column added\n";
} catch (PDOException $e) {
    if (strpos($e->getMessage(), 'Duplicate column') !== false) {
        echo "   ~ cycle_start_date already exists\n";
    } else {
        echo "   ✗ Error: " . $e->getMessage() . "\n";
        $errors[] = $e->getMessage();
    }
}

// 4. Create attendance_shift_daily_hours table for rotational shifts
echo "\n4. Creating attendance_shift_daily_hours table...\n";
try {
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS attendance_shift_daily_hours (
            id INT AUTO_INCREMENT PRIMARY KEY,
            shift_id INT NOT NULL,
            check_in_time TIME NOT NULL DEFAULT '08:00:00',
            check_out_time TIME NOT NULL DEFAULT '20:00:00',
            grace_period_minutes INT DEFAULT 15,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            UNIQUE KEY uk_shift (shift_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ");
    echo "   ✓ attendance_shift_daily_hours created\n";
} catch (PDOException $e) {
    echo "   ✗ Error: " . $e->getMessage() . "\n";
    $errors[] = $e->getMessage();
}

// 5. Update existing shifts to have shift_type = 'fixed'
echo "\n5. Setting existing shifts to type 'fixed'...\n";
try {
    $stmt = $pdo->exec("UPDATE attendance_shifts SET shift_type = 'fixed' WHERE shift_type IS NULL");
    echo "   ✓ Existing shifts updated\n";
} catch (PDOException $e) {
    echo "   ~ " . $e->getMessage() . "\n";
}

// Summary
echo "\n";
if (empty($errors)) {
    echo "=== Migration Complete! ===\n";
    echo "\nDatabase is now ready for advanced shift types:\n";
    echo "  • Fixed Shift (weekly pattern - existing behavior)\n";
    echo "  • Rotational Shift (24hr on/off pattern)\n";
    echo "  • Weekly Shift (week-in/week-out)\n";
    echo "  • Monthly Shift (month-in/month-out)\n";
} else {
    echo "=== Migration completed with " . count($errors) . " error(s) ===\n";
}

echo "</pre>";
?>
