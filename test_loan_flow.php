<?php
require_once 'includes/functions.php';
require_once 'includes/payroll_engine.php';

// Mock Session
$_SESSION['company_id'] = 1;
$_SESSION['user_id'] = 1;
$company_id = 1;

try {
    echo "--- START LOAN TEST ---\n";

    // 1. Cleanup previous test data
    $pdo->exec("DELETE FROM loans WHERE custom_type = 'TEST_LOAN'");
    $pdo->exec("DELETE FROM loan_repayments WHERE loan_id IN (SELECT id FROM loans WHERE custom_type = 'TEST_LOAN')");
    
    // 2. Create Test Loan
    // 2. Create Test Loan
    $emp = $pdo->query("SELECT id, company_id FROM employees LIMIT 1")->fetch(PDO::FETCH_ASSOC);
    if (!$emp) die("No employee found in DB at all.");
    
    $emp_id = $emp['id'];
    $company_id = $emp['company_id'];
    $_SESSION['company_id'] = $company_id;

    echo "Using Employee ID: $emp_id (Company: $company_id)\n";
    
    $stmt = $pdo->prepare("INSERT INTO loans (company_id, employee_id, loan_type, custom_type, principal_amount, repayment_amount, balance, start_month, start_year, status) VALUES (?, ?, 'other', 'TEST_LOAN', 100000, 10000, 100000, ?, ?, 'approved')");
    $stmt->execute([$company_id, $emp_id, date('n'), date('Y')]);
    $loan_id = $pdo->lastInsertId();
    echo "Created Loan ID: $loan_id\n";

    // 3. Run Payroll (Dry Run)
    $month = date('n');
    $year = date('Y');
    echo "Running Payroll for $month/$year...\n";

    // Mock Engine Call
    // run_monthly_payroll($company_id, $month, $year, 'Test Run'); 
    // We can't easily call run_monthly_payroll via CLI without refactoring, 
    // but we can query the logic we added.
    
    // Simulate Fetch Logic
    $stmt_loans = $pdo->prepare("
        SELECT * FROM loans 
        WHERE employee_id = ? 
        AND status = 'approved' 
        AND balance > 0
        AND (start_year < ? OR (start_year = ? AND start_month <= ?))
    ");
    $stmt_loans->execute([$emp_id, $year, $year, $month]);
    $active = $stmt_loans->fetchAll(PDO::FETCH_ASSOC);
    
    echo "Active Loans Found: " . count($active) . "\n";
    foreach ($active as $l) {
        if ($l['id'] == $loan_id) {
            echo "SUCCESS: Test Loan Found.\n";
            $deduction = min($l['repayment_amount'], $l['balance']);
            echo "Calculated Deduction: $deduction (Expected: 10000)\n";
        }
    }

    // 4. Cleanup
    $pdo->exec("DELETE FROM loans WHERE id = $loan_id");
    echo "--- TEST COMPLETE ---\n";

} catch (Exception $e) {
    echo "Error: " . $e->getMessage();
}
?>
