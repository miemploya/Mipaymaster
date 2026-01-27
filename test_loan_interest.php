<?php
// 1. Setup Data for Create Loan
require_once 'config/db.php';
require_once 'includes/functions.php';

// Mock Session AFTER session_start() (which is in functions.php)
$_SESSION['company_id'] = 1;
$_SESSION['user_id'] = 1;
$_SERVER['REQUEST_METHOD'] = 'POST';

$_POST['action'] = 'create_loan';
// Get a valid employee
$emp = $pdo->query("SELECT id, company_id FROM employees LIMIT 1")->fetch(PDO::FETCH_ASSOC);
if (!$emp) die("No employee.");

$company_id = $emp['company_id'];
$_SESSION['company_id'] = $company_id;
$_POST['employee_id'] = $emp['id'];
$_POST['loan_type'] = 'personal';
$_POST['principal_amount'] = 100000;
$_POST['repayment_amount'] = 11000;
$_POST['interest_rate'] = 10; // 10%
$_POST['start_month'] = date('n');
$_POST['start_year'] = date('Y');

// 2. Run API
echo "--- TESTING CREATE LOAN ---\n";
ob_start(); // Capture output
$cwd = getcwd();
chdir($cwd . '/ajax');
require 'loan_operations.php';
chdir($cwd);
$out = ob_get_clean();
$json = json_decode($out, true);

if ($json['status']) {
    echo "Loan Created Successfully.\n";
    
    // 3. Verify DB Data
    $stmt = $pdo->prepare("SELECT * FROM loans WHERE employee_id = ? ORDER BY id DESC LIMIT 1");
    $stmt->execute([$emp['id']]);
    $loan = $stmt->fetch(PDO::FETCH_ASSOC);
    
    echo "Principal: " . $loan['principal_amount'] . "\n";
    echo "Interest Rate: " . $loan['interest_rate'] . "%\n";
    echo "Interest Amount: " . $loan['interest_amount'] . "\n";
    echo "Balance: " . $loan['balance'] . "\n";
    
    $expected_interest = 100000 * 0.10;
    $expected_balance = 100000 + $expected_interest;
    
    if (floatval($loan['interest_amount']) == $expected_interest && floatval($loan['balance']) == $expected_balance) {
        echo "SUCCESS: Interest Calculation Correct.\n";
    } else {
        echo "FAILURE: Calc Mismatch.\n";
    }
    
    // Cleanup
    $pdo->exec("DELETE FROM loans WHERE id = " . $loan['id']);
    
} else {
    echo "Loan Creation Failed: " . $json['message'] . "\n";
}
?>
