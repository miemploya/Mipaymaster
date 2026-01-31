<?php
// Force text content type
header('Content-Type: text/plain');
header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");

require_once '../config/db.php';
session_start();

echo "--- DEBUG V3 START ---\n";

// Get latest run globally
$stmt = $pdo->query("SELECT * FROM payroll_runs ORDER BY id DESC LIMIT 1");
$run = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$run) {
    echo "NO PAYROLL RUNS FOUND AT ALL.\n";
} else {
    echo "Latest Run Found: ID {$run['id']} for Company {$run['company_id']} (Month {$run['period_month']}/{$run['period_year']}, Status: {$run['status']})\n";
    $company_id = $run['company_id'];
    
    // Check Behaviour Settings for this company
    $stmt_beh = $pdo->prepare("SELECT * FROM payroll_behaviours WHERE company_id = ?");
    $stmt_beh->execute([$company_id]);
    $beh = $stmt_beh->fetch(PDO::FETCH_ASSOC);
    echo "Company Behaviour Settings:\n";
    print_r($beh);
    
    // Check OT table for this company/month
    echo "\nMatching Payroll OT Records:\n";
    $stmt_ot = $pdo->prepare("SELECT * FROM payroll_overtime WHERE company_id = ? AND payroll_month = ? AND payroll_year = ?");
    $stmt_ot->execute([$company_id, $run['period_month'], $run['period_year']]);
    $ots = $stmt_ot->fetchAll(PDO::FETCH_ASSOC);
    foreach($ots as $r) { 
        echo " - Emp: {$r['employee_id']}, Hrs: {$r['overtime_hours']}\n"; 
    }

    echo "\nSnapshots for this run (First 5):\n";
    $stmt_ent = $pdo->prepare("SELECT e.id, e.employee_id, e.gross_salary, e.net_pay, s.snapshot_json 
                              FROM payroll_entries e 
                              JOIN payroll_snapshots s ON e.id = s.payroll_entry_id 
                              WHERE e.payroll_run_id = ? LIMIT 5");
    $stmt_ent->execute([$run['id']]);
    
    while ($row = $stmt_ent->fetch(PDO::FETCH_ASSOC)) {
        $snap = json_decode($row['snapshot_json'], true);
        echo "Emp ID: {$row['employee_id']} | Net: {$row['net_pay']}\n";
        echo "Snapshot Overtime: ";
        if (isset($snap['overtime'])) {
            print_r($snap['overtime']);
        } else {
            echo "[MISSING]\n";
        }
        echo "Calculated OT Pay: " . ($snap['overtime']['amount'] ?? 0) . "\n";
        
        echo "----------------\n";
    }
}
echo "--- DEBUG V3 END ---\n";
?>
