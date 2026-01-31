<?php
/**
 * Test Script: Month Reconciliation for New Company
 * Simulates company registered on Jan 14th, 2026
 */

error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/month_reconciliation.php';

echo "<h1>Month Reconciliation Test - New Company Scenario</h1>";
echo "<p>Testing with Company ID: 2, simulating registration on January 14, 2026</p>";
echo "<hr>";

$company_id = 2;
$test_month = 1;
$test_year = 2026;

// 1. Get current company created_at
$stmt = $pdo->prepare("SELECT id, name, created_at FROM companies WHERE id = ?");
$stmt->execute([$company_id]);
$company = $stmt->fetch(PDO::FETCH_ASSOC);

echo "<h2>Step 1: Company Info</h2>";
echo "<table border='1' cellpadding='8'>";
echo "<tr><th>Field</th><th>Original Value</th><th>Test Value</th></tr>";
echo "<tr><td>Company Name</td><td colspan='2'>{$company['name']}</td></tr>";
echo "<tr><td>Created At</td><td>{$company['created_at']}</td><td><strong>2026-01-14 09:00:00</strong></td></tr>";
echo "</table>";

// 2. TEMPORARILY update company created_at for testing
$original_created_at = $company['created_at'];
$stmt_update = $pdo->prepare("UPDATE companies SET created_at = '2026-01-14 09:00:00' WHERE id = ?");
$stmt_update->execute([$company_id]);

echo "<p style='color:orange;'>⚠️ Temporarily set company created_at to 2026-01-14 for testing</p>";

// 3. Get employees with their salary
echo "<h2>Step 2: Employees & Salary</h2>";
$stmt_emp = $pdo->prepare("
    SELECT e.id, e.first_name, e.last_name, e.date_of_joining, e.created_at as emp_created,
           sc.base_gross_amount
    FROM employees e
    LEFT JOIN salary_categories sc ON e.salary_category_id = sc.id
    WHERE e.company_id = ? AND LOWER(e.employment_status) IN ('active', 'full time', 'probation', 'contract')
");
$stmt_emp->execute([$company_id]);
$employees = $stmt_emp->fetchAll(PDO::FETCH_ASSOC);

echo "<table border='1' cellpadding='8'>";
echo "<tr><th>#</th><th>Name</th><th>Date of Joining</th><th>Gross Salary</th></tr>";
foreach ($employees as $idx => $emp) {
    echo "<tr>";
    echo "<td>" . ($idx + 1) . "</td>";
    echo "<td>{$emp['first_name']} {$emp['last_name']}</td>";
    echo "<td>{$emp['date_of_joining']}</td>";
    echo "<td>₦" . number_format($emp['base_gross_amount'] ?? 0, 2) . "</td>";
    echo "</tr>";
}
echo "</table>";

// 4. Clear any existing absent records from previous tests (only auto-marked ones for reconciliation testing)
$stmt_clear = $pdo->prepare("
    DELETE FROM attendance_logs 
    WHERE company_id = ? 
    AND MONTH(date) = ? 
    AND YEAR(date) = ?
    AND review_reason LIKE 'Month-end reconciliation%'
");
$stmt_clear->execute([$company_id, $test_month, $test_year]);
echo "<p>🧹 Cleared previous test reconciliation records</p>";

// 5. Run the reconciliation
echo "<h2>Step 3: Running Month Reconciliation</h2>";
$start_time = microtime(true);
$summary = reconcile_month_attendance($pdo, $company_id, $test_month, $test_year);
$elapsed = round(microtime(true) - $start_time, 3);

echo "<table border='1' cellpadding='8'>";
echo "<tr><th>Metric</th><th>Value</th></tr>";
echo "<tr><td>Days Processed</td><td>{$summary['processed']}</td></tr>";
echo "<tr style='background:#ffe0e0;'><td><strong>Absents Created</strong></td><td><strong>{$summary['absents_created']}</strong></td></tr>";
echo "<tr><td>Skipped (Not Employed Yet)</td><td>{$summary['skipped_not_employed']}</td></tr>";
echo "<tr><td>Skipped (Existing Record)</td><td>{$summary['skipped_existing']}</td></tr>";
echo "<tr><td>Skipped (On Leave)</td><td>{$summary['skipped_leave']}</td></tr>";
echo "<tr><td>Skipped (Non-Working Day)</td><td>{$summary['skipped_non_working']}</td></tr>";
echo "<tr style='background:#ffcccc;'><td><strong>Total Deduction</strong></td><td><strong>₦" . number_format($summary['total_deduction'], 2) . "</strong></td></tr>";
echo "<tr><td>Execution Time</td><td>{$elapsed}s</td></tr>";
echo "</table>";

// 6. Show created absent records by employee
echo "<h2>Step 4: Created Absent Records</h2>";
$stmt_results = $pdo->prepare("
    SELECT al.employee_id, e.first_name, e.last_name, 
           COUNT(*) as absent_days, 
           SUM(al.final_deduction_amount) as total_deduction
    FROM attendance_logs al
    JOIN employees e ON al.employee_id = e.id
    WHERE al.company_id = ? 
    AND MONTH(al.date) = ? 
    AND YEAR(al.date) = ?
    AND al.status = 'Absent'
    AND al.review_reason LIKE 'Month-end reconciliation%'
    GROUP BY al.employee_id, e.first_name, e.last_name
    ORDER BY absent_days DESC
");
$stmt_results->execute([$company_id, $test_month, $test_year]);
$results = $stmt_results->fetchAll(PDO::FETCH_ASSOC);

echo "<table border='1' cellpadding='8'>";
echo "<tr><th>Employee</th><th>Absent Days</th><th>Total Deduction</th></tr>";
foreach ($results as $r) {
    echo "<tr>";
    echo "<td>{$r['first_name']} {$r['last_name']}</td>";
    echo "<td>{$r['absent_days']}</td>";
    echo "<td>₦" . number_format($r['total_deduction'], 2) . "</td>";
    echo "</tr>";
}
echo "</table>";

// 7. Show date breakdown for first employee
if (!empty($results)) {
    $first_emp_id = $results[0]['employee_id'];
    echo "<h2>Step 5: Date Breakdown (First Employee)</h2>";
    
    $stmt_dates = $pdo->prepare("
        SELECT date, status, final_deduction_amount, review_reason
        FROM attendance_logs
        WHERE employee_id = ? 
        AND MONTH(date) = ? 
        AND YEAR(date) = ?
        ORDER BY date
    ");
    $stmt_dates->execute([$first_emp_id, $test_month, $test_year]);
    $dates = $stmt_dates->fetchAll(PDO::FETCH_ASSOC);
    
    echo "<table border='1' cellpadding='5' style='font-size:12px;'>";
    echo "<tr><th>Date</th><th>Status</th><th>Deduction</th><th>Note</th></tr>";
    foreach ($dates as $d) {
        $color = strtolower($d['status']) === 'absent' ? '#ffe0e0' : '#e0ffe0';
        $deduction = $d['final_deduction_amount'] > 0 ? '₦' . number_format($d['final_deduction_amount'], 2) : '-';
        echo "<tr style='background:{$color};'>";
        echo "<td>{$d['date']}</td>";
        echo "<td>{$d['status']}</td>";
        echo "<td>{$deduction}</td>";
        echo "<td>" . substr($d['review_reason'] ?? '', 0, 40) . "</td>";
        echo "</tr>";
    }
    echo "</table>";
}

// 8. RESTORE original company created_at
$stmt_restore = $pdo->prepare("UPDATE companies SET created_at = ? WHERE id = ?");
$stmt_restore->execute([$original_created_at, $company_id]);

echo "<hr>";
echo "<p style='color:green;'>✅ <strong>Restored company created_at to original: {$original_created_at}</strong></p>";

echo "<h2>Summary</h2>";
echo "<ul>";
echo "<li>Company start date was simulated as <strong>January 14, 2026</strong></li>";
echo "<li>Days Jan 1-13 were <strong>skipped</strong> (company didn't exist)</li>";
echo "<li>Days Jan 14-30 were <strong>processed</strong> (working days only)</li>";
echo "<li>Missing attendance records were marked as <strong>Absent with deduction</strong></li>";
echo "</ul>";
?>
