<?php
/**
 * Debug Leave Approval - Check what's happening
 */
require_once __DIR__ . '/config/db.php';

echo "<pre style='font-family: monospace; background: #1e1e1e; color: #0f0; padding: 20px;'>";
echo "=== DEBUG LEAVE APPROVAL ===\n\n";

$company_id = 2; // Openclax
$year = date('Y');

try {
    // 1. Find pending leave requests
    echo "1️⃣ PENDING LEAVE REQUESTS:\n";
    echo str_repeat("-", 70) . "\n";
    $stmt = $pdo->prepare("SELECT lr.*, e.first_name, e.last_name 
                           FROM leave_requests lr 
                           JOIN employees e ON lr.employee_id = e.id
                           WHERE lr.company_id = ? AND lr.status = 'pending'");
    $stmt->execute([$company_id]);
    $requests = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    if (empty($requests)) {
        echo "   No pending requests found.\n\n";
    } else {
        foreach ($requests as $r) {
            echo "   ID: {$r['id']} | {$r['first_name']} {$r['last_name']} | Type: {$r['leave_type']} | {$r['start_date']} to {$r['end_date']}\n";
            
            // Check the leave type lookup
            $stmt = $pdo->prepare("SELECT id, name FROM leave_types WHERE company_id = ? AND name = ?");
            $stmt->execute([$company_id, $r['leave_type']]);
            $lt = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($lt) {
                echo "   ✓ Leave Type Found: ID={$lt['id']}, Name={$lt['name']}\n";
                
                // Check balance for this employee/type/year
                $stmt = $pdo->prepare("SELECT * FROM employee_leave_balances WHERE employee_id = ? AND leave_type_id = ? AND year = ?");
                $stmt->execute([$r['employee_id'], $lt['id'], $year]);
                $bal = $stmt->fetch(PDO::FETCH_ASSOC);
                
                if ($bal) {
                    $available = $bal['balance_days'] - $bal['used_days'];
                    echo "   ✓ Balance Found: balance_days={$bal['balance_days']}, used_days={$bal['used_days']}, available={$available}\n";
                } else {
                    echo "   ❌ NO BALANCE RECORD for employee_id={$r['employee_id']}, leave_type_id={$lt['id']}, year={$year}\n";
                }
            } else {
                echo "   ❌ LEAVE TYPE NOT FOUND: '{$r['leave_type']}' for company_id={$company_id}\n";
            }
            echo "\n";
        }
    }
    
    // 2. Show all leave types
    echo "\n2️⃣ ALL LEAVE TYPES FOR COMPANY:\n";
    echo str_repeat("-", 70) . "\n";
    $stmt = $pdo->prepare("SELECT * FROM leave_types WHERE company_id = ?");
    $stmt->execute([$company_id]);
    $types = $stmt->fetchAll(PDO::FETCH_ASSOC);
    foreach ($types as $t) {
        echo "   ID: {$t['id']} | Name: '{$t['name']}' | Active: {$t['is_active']}\n";
    }
    
    // 3. Show Ighodaro's balances
    echo "\n3️⃣ IGHODARO'S LEAVE BALANCES (Year $year):\n";
    echo str_repeat("-", 70) . "\n";
    $stmt = $pdo->query("SELECT e.id as emp_id, e.first_name, lt.id as lt_id, lt.name as leave_type, 
                         lb.balance_days, lb.used_days, lb.year,
                         (lb.balance_days - lb.used_days) as available
                         FROM employees e
                         JOIN employee_leave_balances lb ON e.id = lb.employee_id
                         JOIN leave_types lt ON lb.leave_type_id = lt.id
                         WHERE e.first_name LIKE 'Ighodaro%'
                         ORDER BY lb.year DESC, lt.name");
    $bals = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    printf("   %-8s %-15s %8s %8s %8s %8s\n", "Emp ID", "Leave Type", "Year", "Balance", "Used", "Avail");
    echo "   " . str_repeat("-", 60) . "\n";
    foreach ($bals as $b) {
        printf("   %-8s %-15s %8s %8s %8s %8s\n", 
            $b['emp_id'], $b['leave_type'], $b['year'], $b['balance_days'], $b['used_days'], $b['available']);
    }
    
} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
}
echo "</pre>";
?>
