<?php
session_start();
// Valid test script
require_once __DIR__ . '/config/db.php';
$_POST['action'] = 'fetch_loans';
$_POST['filter'] = 'pending';
$_SESSION['company_id'] = 2;
$_SESSION['user_id'] = 2;
$_SESSION['role'] = 'super_admin';

// Simulate AJAX call to catch error
ob_start();
require __DIR__ . '/ajax/loan_operations.php';
$output = ob_get_clean();
echo "OUTPUT START\n" . $output . "\nOUTPUT END";
?>
