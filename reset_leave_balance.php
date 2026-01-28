<?php
/**
 * Reset/Adjust Leave Balance for Testing
 */
require_once __DIR__ . '/config/db.php';

echo "<pre style='font-family: monospace; background: #1e1e1e; color: #0f0; padding: 20px;'>";
echo "=== LEAVE BALANCE ADJUSTMENT ===\n\n";

try {
    // Find Ighodaro's employee record
    $stmt = $pdo->query("SELECT e.id, e.first_name, e.last_name, lb.id as balance_id, lt.name as leave_type, 
                         lb.balance_days, lb.used_days, (lb.balance_days - lb.used_days) as available
                         FROM employees e
                         JOIN employee_leave_balances lb ON e.id = lb.employee_id
                         JOIN leave_types lt ON lb.leave_type_id = lt.id
                         WHERE e.first_name LIKE 'Ighodaro%' AND lb.year = 2026
                         ORDER BY lt.name");
    $balances = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo "📋 Current Balances for Ighodaro:\n";
    echo str_repeat("-", 60) . "\n";
    printf("%-15s %10s %10s %10s\n", "Leave Type", "Balance", "Used", "Available");
    echo str_repeat("-", 60) . "\n";
    
    foreach ($balances as $b) {
        printf("%-15s %10s %10s %10s\n", $b['leave_type'], $b['balance_days'], $b['used_days'], $b['available']);
    }
    
    // Reset Casual leave used_days to 0
    echo "\n\n🔧 Resetting Casual Leave used_days to 0...\n";
    
    $stmt = $pdo->prepare("
        UPDATE employee_leave_balances lb
        JOIN employees e ON lb.employee_id = e.id
        JOIN leave_types lt ON lb.leave_type_id = lt.id
        SET lb.used_days = 0
        WHERE e.first_name LIKE 'Ighodaro%' AND lt.name = 'Casual' AND lb.year = 2026
    ");
    $stmt->execute();
    $affected = $stmt->rowCount();
    
    echo "✅ Reset $affected record(s)\n\n";
    
    // Show updated balances
    echo "📋 Updated Balances:\n";
    echo str_repeat("-", 60) . "\n";
    
    $stmt = $pdo->query("SELECT lt.name as leave_type, lb.balance_days, lb.used_days, 
                         (lb.balance_days - lb.used_days) as available
                         FROM employees e
                         JOIN employee_leave_balances lb ON e.id = lb.employee_id
                         JOIN leave_types lt ON lb.leave_type_id = lt.id
                         WHERE e.first_name LIKE 'Ighodaro%' AND lb.year = 2026
                         ORDER BY lt.name");
    $updated = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    printf("%-15s %10s %10s %10s\n", "Leave Type", "Balance", "Used", "Available");
    echo str_repeat("-", 60) . "\n";
    foreach ($updated as $b) {
        printf("%-15s %10s %10s %10s\n", $b['leave_type'], $b['balance_days'], $b['used_days'], $b['available']);
    }
    
    echo "\n✅ Done! Ighodaro now has 5 Casual leave days available.\n";
    
} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
}
echo "</pre>";
?>
