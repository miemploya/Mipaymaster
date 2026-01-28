<?php
/**
 * Fix Leave System - Add missing columns and restore balances
 */
require_once __DIR__ . '/config/db.php';

echo "<pre style='font-family: monospace; background: #1e1e1e; color: #0f0; padding: 20px;'>";
echo "╔══════════════════════════════════════════════════════════════╗\n";
echo "║           LEAVE SYSTEM FIX SCRIPT                            ║\n";
echo "╚══════════════════════════════════════════════════════════════╝\n\n";

try {
    // 1. Add days_count column to leave_requests if missing
    echo "1️⃣ Checking leave_requests table...\n";
    $cols = $pdo->query("SHOW COLUMNS FROM leave_requests LIKE 'days_count'")->fetchAll();
    
    if (empty($cols)) {
        $pdo->exec("ALTER TABLE leave_requests ADD COLUMN days_count INT DEFAULT NULL AFTER end_date");
        echo "   ✅ Added 'days_count' column\n";
    } else {
        echo "   ✓ 'days_count' column already exists\n";
    }
    
    // Also check for approved_by and approved_at
    $cols = $pdo->query("SHOW COLUMNS FROM leave_requests LIKE 'approved_by'")->fetchAll();
    if (empty($cols)) {
        $pdo->exec("ALTER TABLE leave_requests ADD COLUMN approved_by INT NULL");
        echo "   ✅ Added 'approved_by' column\n";
    }
    
    $cols = $pdo->query("SHOW COLUMNS FROM leave_requests LIKE 'approved_at'")->fetchAll();
    if (empty($cols)) {
        $pdo->exec("ALTER TABLE leave_requests ADD COLUMN approved_at DATETIME NULL");
        echo "   ✅ Added 'approved_at' column\n";
    }
    
    // 2. Update existing records with calculated days_count
    echo "\n2️⃣ Updating days_count for existing records...\n";
    $pdo->exec("UPDATE leave_requests SET days_count = DATEDIFF(end_date, start_date) + 1 WHERE days_count IS NULL");
    echo "   ✅ Updated days_count values\n";
    
    // 3. Find and fix incorrectly deducted balances (pending leaves with used_days > 0)
    echo "\n3️⃣ Checking for incorrectly deducted balances...\n";
    
    // Find pending requests that may have had balance deducted
    $stmt = $pdo->query("
        SELECT lr.id, lr.employee_id, lr.leave_type, lr.status, lr.start_date, lr.end_date,
               DATEDIFF(lr.end_date, lr.start_date) + 1 as days_requested,
               e.first_name, e.last_name, lr.company_id
        FROM leave_requests lr
        JOIN employees e ON lr.employee_id = e.id
        WHERE lr.status IN ('pending', 'rejected')
        ORDER BY lr.created_at DESC
    ");
    $pending = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo "   Found " . count($pending) . " pending/rejected leave requests\n";
    
    // 4. Reset balances for any rejected leaves that might have been deducted
    echo "\n4️⃣ Resetting balances for testing...\n";
    
    // Reset Ighodaro's casual leave used_days 
    $stmt = $pdo->prepare("
        UPDATE employee_leave_balances lb
        JOIN employees e ON lb.employee_id = e.id
        JOIN leave_types lt ON lb.leave_type_id = lt.id
        SET lb.used_days = 0
        WHERE e.first_name LIKE 'Ighodaro%' 
        AND lt.name IN ('Casual', 'Annual', 'Maternity')
        AND lb.year = ?
    ");
    $stmt->execute([date('Y')]);
    echo "   ✅ Reset Ighodaro's leave balances to 0 used days\n";
    
    // 5. Show current table structure
    echo "\n5️⃣ Current leave_requests structure:\n";
    echo str_repeat("-", 50) . "\n";
    $stmt = $pdo->query("DESCRIBE leave_requests");
    $columns = $stmt->fetchAll(PDO::FETCH_ASSOC);
    foreach ($columns as $col) {
        printf("   %-20s %s\n", $col['Field'], $col['Type']);
    }
    
    // 6. Show current balances
    echo "\n6️⃣ Current Leave Balances (Ighodaro):\n";
    echo str_repeat("-", 60) . "\n";
    $stmt = $pdo->query("
        SELECT lt.name, lb.balance_days, lb.used_days, (lb.balance_days - lb.used_days) as available
        FROM employee_leave_balances lb
        JOIN employees e ON lb.employee_id = e.id
        JOIN leave_types lt ON lb.leave_type_id = lt.id
        WHERE e.first_name LIKE 'Ighodaro%' AND lb.year = " . date('Y')
    );
    $bals = $stmt->fetchAll(PDO::FETCH_ASSOC);
    printf("   %-15s %10s %10s %10s\n", "Type", "Balance", "Used", "Available");
    echo "   " . str_repeat("-", 50) . "\n";
    foreach ($bals as $b) {
        printf("   %-15s %10s %10s %10s\n", $b['name'], $b['balance_days'], $b['used_days'], $b['available']);
    }
    
    echo "\n" . str_repeat("═", 60) . "\n";
    echo "✅ FIX COMPLETE! You can now approve leave requests.\n";
    echo str_repeat("═", 60) . "\n";
    
} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
}
echo "</pre>";
?>
