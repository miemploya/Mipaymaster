<?php
// Debug script to check payroll snapshots for loan data
require_once __DIR__ . '/includes/functions.php';

header('Content-Type: text/plain');

if (!isset($_SESSION['user_id'])) {
    echo "ERROR: Not logged in. Please login first.\n";
    exit;
}

$company_id = $_SESSION['company_id'];

echo "=== PAYROLL SNAPSHOT DEBUG ===\n\n";

// Get latest payroll run
$stmt = $pdo->prepare("SELECT * FROM payroll_runs WHERE company_id = ? ORDER BY id DESC LIMIT 1");
$stmt->execute([$company_id]);
$run = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$run) {
    echo "ERROR: No payroll run found\n";
    exit;
}

echo "Latest Payroll Run: ID {$run['id']}, Period: {$run['period_month']}/{$run['period_year']}, Status: {$run['status']}\n\n";

// Get entries with snapshots
$stmt = $pdo->prepare("
    SELECT pe.*, e.first_name, e.last_name, ps.snapshot_json
    FROM payroll_entries pe
    JOIN employees e ON pe.employee_id = e.id
    LEFT JOIN payroll_snapshots ps ON ps.payroll_entry_id = pe.id
    WHERE pe.payroll_run_id = ?
");
$stmt->execute([$run['id']]);
$entries = $stmt->fetchAll(PDO::FETCH_ASSOC);

echo "Total Entries: " . count($entries) . "\n\n";

foreach ($entries as $entry) {
    echo "--- Employee: {$entry['first_name']} {$entry['last_name']} (ID: {$entry['employee_id']}) ---\n";
    echo "  Gross: {$entry['gross_salary']}\n";
    echo "  Net Pay: {$entry['net_pay']}\n";
    echo "  Total Deductions: {$entry['total_deductions']}\n";
    
    if ($entry['snapshot_json']) {
        $snap = json_decode($entry['snapshot_json'], true);
        echo "  Snapshot:\n";
        
        // Check loans specifically
        if (isset($snap['loans']) && !empty($snap['loans'])) {
            echo "    LOANS FOUND:\n";
            foreach ($snap['loans'] as $loan) {
                echo "      - Loan ID: {$loan['loan_id']}, Type: {$loan['type']}, Amount: {$loan['amount']}\n";
            }
        } else {
            echo "    LOANS: EMPTY or NOT SET\n";
        }
        
        // Check statutory
        if (isset($snap['statutory'])) {
            echo "    Statutory: PAYE={$snap['statutory']['paye']}, Pension={$snap['statutory']['pension_employee']}\n";
        }
    } else {
        echo "  Snapshot: NOT FOUND\n";
    }
    echo "\n";
}

// Also check what loans exist for these employees
echo "=== ACTIVE LOANS FOR PAYROLL EMPLOYEES ===\n\n";
foreach ($entries as $entry) {
    $stmt = $pdo->prepare("SELECT * FROM loans WHERE employee_id = ? AND status = 'approved' AND balance > 0");
    $stmt->execute([$entry['employee_id']]);
    $loans = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    if ($loans) {
        echo "{$entry['first_name']} {$entry['last_name']}:\n";
        foreach ($loans as $loan) {
            echo "  - Loan ID: {$loan['id']}, Type: {$loan['loan_type']}, Balance: {$loan['balance']}, Repayment: {$loan['repayment_amount']}, Start: {$loan['start_month']}/{$loan['start_year']}\n";
        }
    }
}

echo "\n=== DEBUG COMPLETE ===\n";
?>
