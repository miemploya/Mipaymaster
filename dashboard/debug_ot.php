<?php
// Force text content type
header('Content-Type: text/plain');

require_once '../config/db.php';
session_start();

echo "--- DEBUG START ---\n";
echo "Session ID: " . session_id() . "\n";
echo "Company ID in Session: " . ($_SESSION['company_id'] ?? 'NULL') . "\n";
echo "User ID in Session: " . ($_SESSION['user_id'] ?? 'NULL') . "\n";

$company_id = $_SESSION['company_id'] ?? 0;

if ($company_id == 0) {
    echo "ERROR: No Company ID in session. Cannot query.\n";
    // Try to guess company from employees?
    $stmt = $pdo->query("SELECT company_id FROM employees LIMIT 1");
    $guess = $stmt->fetchColumn();
    echo "Guessed Company ID from Employees table: $guess\n";
    if ($guess) $company_id = $guess;
}

echo "Using Company ID: $company_id\n";

// 1. Check payroll_overtime
echo "\n--- 1. PAYROLL OVERTIME (Last 5) ---\n";
try {
    $stmt = $pdo->prepare("SELECT * FROM payroll_overtime WHERE company_id = ? ORDER BY id DESC LIMIT 5");
    $stmt->execute([$company_id]);
    $ots = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    if ($ots) {
        foreach ($ots as $ot) {
            echo "ID: {$ot['id']}, Emp: {$ot['employee_id']}, Month: {$ot['payroll_month']}, Year: {$ot['payroll_year']}, Hrs: {$ot['overtime_hours']}, Notes: {$ot['notes']}\n";
        }
    } else {
        echo "NO RECORDS FOUND in payroll_overtime for Company $company_id\n";
        
        // Check ANY company
        $stmt_any = $pdo->query("SELECT COUNT(*) FROM payroll_overtime");
        $count = $stmt_any->fetchColumn();
        echo "Total records in payroll_overtime (all companies): $count\n";
    }
} catch (Exception $e) {
    echo "SQL Error: " . $e->getMessage() . "\n";
}

// 2. Check Payroll Runs
echo "\n--- 2. LATEST PAYROLL RUN ---\n";
$stmt = $pdo->prepare("SELECT * FROM payroll_runs WHERE company_id = ? ORDER BY id DESC LIMIT 1");
$stmt->execute([$company_id]);
$run = $stmt->fetch(PDO::FETCH_ASSOC);

if ($run) {
    echo "Run ID: {$run['id']}, Month: {$run['period_month']}, Year: {$run['period_year']}, Status: {$run['status']}\n";
    
    // Snapshots
    echo "\n--- 3. SNAPSHOT SAMPLES (First 3) ---\n";
    $stmt_ent = $pdo->prepare("SELECT e.id, e.employee_id, e.net_pay, s.snapshot_json 
                              FROM payroll_entries e 
                              JOIN payroll_snapshots s ON e.id = s.payroll_entry_id 
                              WHERE e.payroll_run_id = ? LIMIT 3");
    $stmt_ent->execute([$run['id']]);
    
    while ($row = $stmt_ent->fetch(PDO::FETCH_ASSOC)) {
        $snap = json_decode($row['snapshot_json'], true);
        echo "Emp ID: {$row['employee_id']}, Net: {$row['net_pay']}\n";
        echo "Snapshot Overtime Data:\n";
        if (isset($snap['overtime'])) {
            print_r($snap['overtime']);
        } else {
            echo "  [MISSING 'overtime' KEY]\n";
        }
        echo "Computed Breakdown Overtime Pay from fetch_sheet logic: " . ($snap['overtime']['amount'] ?? 0) . "\n";
        echo "----------------\n";
    }
} else {
    echo "NO PAYROLL RUNS FOUND for Company $company_id\n";
}

echo "--- DEBUG END ---\n";
?>
