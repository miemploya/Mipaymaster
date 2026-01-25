<?php
session_start();
$_SESSION['user_id'] = 1;
$_SESSION['company_id'] = 2;
$_SERVER['REQUEST_METHOD'] = 'POST';
// Inject input
$json = json_encode(['action' => 'initiate', 'month' => 3, 'year' => 2026]);
// Hack: we can't easily inject into php://input for specific include.
// Alternatives: 
// 1. Modify ajax/payroll_operations.php to check a global $TEST_INPUT if set.
// 2. Use a real HTTP request if possible.
// 3. Just reproduce the logic in the test script.

// Let's try reproduction to verify logic first.
require_once 'includes/functions.php';
require_once 'includes/payroll_engine.php';
echo json_encode(run_monthly_payroll(2, 3, 2026, 1));
?>