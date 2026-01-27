<?php
// verify_regen.php
require_once 'includes/functions.php';
require_once 'includes/payroll_engine.php';

$company_id = 2; // Openclax
$user_id = 1;
$month = date('n');
$year = date('Y');

echo "1. First Run (Expect Success)...\n";
// Ensure clean state
$pdo->exec("DELETE FROM payroll_runs WHERE company_id=$company_id AND period_month=$month AND period_year=$year");
$pdo->exec("DELETE FROM payroll_entries WHERE payroll_run_id IN (SELECT id FROM payroll_runs WHERE company_id=$company_id)");

$res1 = run_monthly_payroll($company_id, $month, $year, $user_id);
print_r($res1);

if ($res1['status']) {
    $run_id = $res1['run_id'];
    echo "Run ID: $run_id\n";
    
    // Simulate Data Change (optional, but let's just re-run)
    echo "\n2. Second Run (Expect Success - Regeneration of Draft)...\n";
    $res2 = run_monthly_payroll($company_id, $month, $year, $user_id);
    print_r($res2);
    
    if ($res2['status'] && $res2['run_id'] == $run_id) {
        echo "\nSUCCESS: Re-run successful and ID preserved.\n";
    } else {
        echo "\nFAILURE: Re-run failed or ID changed.\n";
    }
} else {
    echo "First run failed.\n";
}
