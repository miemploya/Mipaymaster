<?php
/**
 * Initialize Leave Balances for All Employees Based on Policies
 * Run this to populate employee_leave_balances from leave_policies
 */
require_once __DIR__ . '/config/db.php';

echo "<pre style='font-family: monospace; background: #1e1e1e; color: #0f0; padding: 20px;'>";
echo "╔══════════════════════════════════════════════════════════════╗\n";
echo "║           LEAVE BALANCE INITIALIZATION SCRIPT                ║\n";
echo "╚══════════════════════════════════════════════════════════════╝\n\n";

$year = date('Y');

try {
    // Get all companies
    $companies = $pdo->query("SELECT id, name FROM companies")->fetchAll(PDO::FETCH_ASSOC);
    
    $totalInitialized = 0;
    
    foreach ($companies as $company) {
        $company_id = $company['id'];
        echo "📂 Company: {$company['name']} (ID: $company_id)\n";
        echo str_repeat("─", 60) . "\n";
        
        // Get all employees
        $stmt = $pdo->prepare("SELECT id, first_name, last_name, salary_category_id FROM employees WHERE company_id = ?");
        $stmt->execute([$company_id]);
        $employees = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        if (empty($employees)) {
            echo "   ⚠️  No employees found\n\n";
            continue;
        }
        
        // Get all leave types for this company
        $stmt = $pdo->prepare("SELECT id, name FROM leave_types WHERE company_id = ? AND is_active = 1");
        $stmt->execute([$company_id]);
        $leaveTypes = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        if (empty($leaveTypes)) {
            echo "   ⚠️  No leave types found\n\n";
            continue;
        }
        
        foreach ($employees as $emp) {
            $emp_id = $emp['id'];
            $cat_id = $emp['salary_category_id'];
            $emp_name = $emp['first_name'] . ' ' . $emp['last_name'];
            
            echo "   👤 $emp_name (Cat: " . ($cat_id ?: 'None') . ")\n";
            
            foreach ($leaveTypes as $lt) {
                $lt_id = $lt['id'];
                $lt_name = $lt['name'];
                
                // Check if balance already exists
                $stmt = $pdo->prepare("SELECT id FROM employee_leave_balances WHERE employee_id = ? AND leave_type_id = ? AND year = ?");
                $stmt->execute([$emp_id, $lt_id, $year]);
                if ($stmt->fetch()) {
                    echo "      ✓ $lt_name - Already exists\n";
                    continue;
                }
                
                // Get policy days (category-specific or all-category)
                $stmt = $pdo->prepare("
                    SELECT days_per_year FROM leave_policies 
                    WHERE company_id = ? AND leave_type_id = ? 
                    AND (category_id = ? OR category_id IS NULL)
                    ORDER BY category_id DESC LIMIT 1
                ");
                $stmt->execute([$company_id, $lt_id, $cat_id]);
                $policy = $stmt->fetch();
                
                $days = $policy ? $policy['days_per_year'] : 0;
                
                if ($days > 0) {
                    // Insert balance
                    $stmt = $pdo->prepare("
                        INSERT INTO employee_leave_balances (employee_id, leave_type_id, balance_days, used_days, carry_over_days, year, created_at)
                        VALUES (?, ?, ?, 0, 0, ?, NOW())
                    ");
                    $stmt->execute([$emp_id, $lt_id, $days, $year]);
                    echo "      ✅ $lt_name - Initialized: $days days\n";
                    $totalInitialized++;
                } else {
                    echo "      ⚪ $lt_name - No policy (0 days)\n";
                }
            }
        }
        echo "\n";
    }
    
    echo str_repeat("═", 60) . "\n";
    echo "✅ COMPLETE: Initialized $totalInitialized balance records for $year\n";
    echo str_repeat("═", 60) . "\n";
    
    // Show summary
    echo "\n📊 Current Balance Summary:\n";
    $stmt = $pdo->query("
        SELECT e.first_name, e.last_name, lt.name as leave_type, lb.balance_days, lb.used_days
        FROM employee_leave_balances lb
        JOIN employees e ON lb.employee_id = e.id
        JOIN leave_types lt ON lb.leave_type_id = lt.id
        WHERE lb.year = $year
        ORDER BY e.first_name, lt.name
        LIMIT 30
    ");
    $summary = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    if (!empty($summary)) {
        printf("\n%-20s %-20s %10s %10s\n", "Employee", "Leave Type", "Balance", "Used");
        echo str_repeat("-", 65) . "\n";
        foreach ($summary as $row) {
            printf("%-20s %-20s %10s %10s\n", 
                substr($row['first_name'] . ' ' . $row['last_name'], 0, 19),
                substr($row['leave_type'], 0, 19),
                $row['balance_days'],
                $row['used_days']
            );
        }
    }
    
} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
}
echo "</pre>";
?>
