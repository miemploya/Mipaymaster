<?php
session_start();
$_SESSION['user_id'] = 1;
$_SESSION['company_id'] = 2;
require_once 'includes/functions.php';
require_once 'includes/payroll_engine.php';

// Simulate fetch logic
$month = 3; $year = 2026;
$stmt = $pdo->prepare('SELECT * FROM payroll_runs WHERE company_id = 2 AND period_month = 3 AND period_year = 2026');
$stmt->execute();
$run = $stmt->fetch(PDO::FETCH_ASSOC);
if(!$run) { echo 'Run not found'; exit; }
echo 'Run Found: ' . $run['id'] . '
';

$stmt = $pdo->prepare('SELECT count(*) FROM payroll_entries WHERE payroll_run_id = ?');
$stmt->execute([$run['id']]);
echo 'Entries: ' . $stmt->fetchColumn() . '
';
?>