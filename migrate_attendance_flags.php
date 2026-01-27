<?php
/**
 * Migration: Add requires_review flag to attendance_logs
 * This flag marks records that need admin attention (auto-closed, absent, etc.)
 */
require_once 'includes/functions.php';
require_login();

echo "<h2>Attendance Flag Migration</h2>";
echo "<pre>";

try {
    // 1. Add requires_review column
    $cols = $pdo->query("SHOW COLUMNS FROM attendance_logs LIKE 'requires_review'")->fetchAll();
    if (count($cols) == 0) {
        $pdo->exec("ALTER TABLE attendance_logs ADD COLUMN requires_review TINYINT(1) DEFAULT 0 AFTER status");
        echo "✅ Added 'requires_review' column\n";
    } else {
        echo "ℹ️ 'requires_review' column already exists\n";
    }
    
    // 2. Add review_reason column (to explain why flagged)
    $cols = $pdo->query("SHOW COLUMNS FROM attendance_logs LIKE 'review_reason'")->fetchAll();
    if (count($cols) == 0) {
        $pdo->exec("ALTER TABLE attendance_logs ADD COLUMN review_reason VARCHAR(100) NULL AFTER requires_review");
        echo "✅ Added 'review_reason' column\n";
    } else {
        echo "ℹ️ 'review_reason' column already exists\n";
    }
    
    // 3. Add absent_deduction_amount column
    $cols = $pdo->query("SHOW COLUMNS FROM attendance_logs LIKE 'absent_deduction_amount'")->fetchAll();
    if (count($cols) == 0) {
        $pdo->exec("ALTER TABLE attendance_logs ADD COLUMN absent_deduction_amount DECIMAL(15,2) DEFAULT 0 AFTER final_deduction_amount");
        echo "✅ Added 'absent_deduction_amount' column\n";
    } else {
        echo "ℹ️ 'absent_deduction_amount' column already exists\n";
    }
    
    echo "\n✅ Migration complete!\n";
    
} catch (PDOException $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
}

echo "</pre>";
echo "<p><a href='dashboard/attendance.php'>← Back to Attendance</a></p>";
?>
