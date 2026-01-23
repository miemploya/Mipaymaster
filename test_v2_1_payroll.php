<?php
// test_v2_1_payroll.php
require 'includes/functions.php'; // Checks DB connection usually
require 'includes/payroll_engine.php';
require_once 'includes/increment_manager.php';
require_once 'includes/payroll_lock.php';

// Force enable error reporting
error_reporting(E_ALL);
ini_set('display_errors', 1);

global $pdo;

echo "<h1>V2.1 Verification Test</h1>";

function assert_true($condition, $message) {
    if ($condition) echo "<p style='color:green'>[PASS] $message</p>";
    else echo "<p style='color:red'>[FAIL] $message</p>";
}

// 1. SETUP CLEAN STATE
try {
    // Migration is assumed to be applied via migrations/run_v2_1.php
    
    // Clean tables related to test
    $pdo->exec("SET FOREIGN_KEY_CHECKS=0");
    $pdo->exec("TRUNCATE TABLE employee_salary_adjustments");
    $pdo->exec("TRUNCATE TABLE payroll_snapshots");
    $pdo->exec("TRUNCATE TABLE payroll_entries");
    $pdo->exec("TRUNCATE TABLE payroll_runs");
    $pdo->exec("TRUNCATE TABLE payroll_reversals");
    // Ensure we have a test company and employee (using ID 1 for simplicity if exists, else create)
    
    // Check Company
    $stmt = $pdo->query("SELECT id FROM companies LIMIT 1");
    $c = $stmt->fetch();
    if (!$c) {
        $pdo->exec("INSERT INTO companies (name, email) VALUES ('Test Corp', 'test@example.com')");
        $cid = $pdo->lastInsertId();
    } else {
        $cid = $c['id'];
    }

    // Check Category
    $stmt = $pdo->prepare("SELECT id FROM salary_categories WHERE company_id = ? LIMIT 1");
    $stmt->execute([$cid]);
    $cat = $stmt->fetch();
    if (!$cat) {
        $pdo->prepare("INSERT INTO salary_categories (company_id, title, base_gross_amount) VALUES (?, 'Test Cat', 500000)")->execute([$cid]);
        $catHas = $pdo->lastInsertId();
    } else {
        $catHas = $cat['id'];
    }

    // Ensure Components & Breakdown
    // ... Assuming migration seeded them or they exist. Verify Breakdown.
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM salary_category_breakdown WHERE category_id = ?");
    $stmt->execute([$catHas]);
    if ($stmt->fetchColumn() == 0) {
        // Create components if needed
        $comp = $pdo->prepare("SELECT id FROM salary_components WHERE company_id = ? AND type='basic' LIMIT 1");
        $comp->execute([$cid]);
        $basic = $comp->fetch();
        if (!$basic) {
            $pdo->prepare("INSERT INTO salary_components (company_id, name, type, default_percentage) VALUES (?, 'Basic Salary', 'basic', 100)")->execute([$cid]);
            $bid = $pdo->lastInsertId();
        } else {
            $bid = $basic['id'];
        }
        
        $pdo->prepare("INSERT INTO salary_category_breakdown (category_id, salary_component_id, component_name, percentage) VALUES (?, ?, 'Basic Salary', 100)")->execute([$catHas, $bid]);
    }

    // Check Employee
    $stmt = $pdo->prepare("SELECT id FROM employees WHERE company_id = ? LIMIT 1");
    $stmt->execute([$cid]);
    $emp = $stmt->fetch();
    if (!$emp) {
        $pdo->prepare("INSERT INTO employees (company_id, category_id, first_name, last_name, email, status) VALUES (?, ?, 'John', 'Doe', 'j@doe.com', 'active')")->execute([$cid, $catHas]);
        $eid = $pdo->lastInsertId();
    } else {
        $eid = $emp['id'];
        // Ensure category matches
        $pdo->prepare("UPDATE employees SET category_id = ?, status='active' WHERE id = ?")->execute([$catHas, $eid]);
    }
    
    echo "<p>Setup Complete. Company: $cid, Emp: $eid, Cat: $catHas</p>";
    
    // 2. INCREMENT TEST
    echo "<h2>Testing Increment Logic</h2>";
    $incManager = new IncrementManager($pdo);
    
    // Add Increment (+50k)
    $res = $incManager->add_increment($eid, 'fixed', 50000, date('Y-01-01'), 'Perf Bonus');
    assert_true($res['status'], "Increment Added: " . $res['message']);
    $incId = $res['id'];
    
    // Approve it
    $res = $incManager->approve_increment($incId, 1); // 1 = admin
    assert_true($res['status'], "Increment Approved");

    // 3. RUN PAYROLL
    echo "<h2>Running Payroll</h2>";
    $month = date('m');
    $year = date('Y');
    
    $res = run_monthly_payroll($cid, $month, $year, 1);
    assert_true($res['status'], "Payroll Run: " . ($res['message'] ?? 'OK'));
    $runId = $res['run_id'] ?? 0;
    
    if ($runId) {
        // Verify Entries
        $stmt = $pdo->prepare("SELECT * FROM payroll_entries WHERE payroll_run_id = ? AND employee_id = ?");
        $stmt->execute([$runId, $eid]);
        $entry = $stmt->fetch(PDO::FETCH_ASSOC);
        
        // Expected Gross: Base (500k) + Inc (50k) = 550k
        // Wait, did we check base of category?
        $base = $pdo->query("SELECT base_gross_amount FROM salary_categories WHERE id=$catHas")->fetchColumn();
        $expected = $base + 50000;
        
        assert_true($entry['gross_salary'] == $expected, "Gross Salary matches expected ($expected). Actual: " . $entry['gross_salary']);
        
        // Verify Snapshot
        $stmt = $pdo->prepare("SELECT snapshot_json FROM payroll_snapshots WHERE payroll_entry_id = ?");
        $stmt->execute([$entry['id']]);
        $snap = $stmt->fetchColumn();
        $json = json_decode($snap, true);
        
        assert_true(isset($json['increment_applied']), "Snapshot contains increment data");
        assert_true($json['increment_applied']['adjustment_value'] == 50000, "Snapshot records correct increment value");

        // 4. LOCKING TEST
        echo "<h2>Testing Locking & Reversal</h2>";
        $locker = new PayrollLock($pdo);
        
        $res = $locker->lock_payroll($runId, 1);
        assert_true($res['status'], "Payroll Locked");
        
        // Verify Locked State
        $status = $pdo->query("SELECT status FROM payroll_runs WHERE id=$runId")->fetchColumn();
        assert_true($status === 'locked', "DB Status is 'locked'");
        
        // Try Reverse
        $res = $locker->reverse_payroll($runId, 1, "Mistake in tax");
        assert_true($res['status'], "Payroll Reversed");
        
        $status = $pdo->query("SELECT status FROM payroll_runs WHERE id=$runId")->fetchColumn();
        assert_true($status === 'reversed', "DB Status is 'reversed'");
        
        // Re-run Payroll (Draft)
        $res = run_monthly_payroll($cid, $month, $year, 1);
        assert_true($res['status'], "Re-run Payroll allowed (Recycled/New entries): " . ($res['message'] ?? 'OK'));
        
    }

    $pdo->exec("SET FOREIGN_KEY_CHECKS=1");

} catch (Exception $e) {
    echo "<p style='color:red'>EXCEPTION: " . $e->getMessage() . "</p>";
}
?>
