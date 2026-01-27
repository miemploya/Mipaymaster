<?php
// ajax/payroll_operations.php
require_once '../includes/functions.php';
require_once '../includes/payroll_engine.php';

header('Content-Type: application/json');

// Ensure user is logged in
if (!isset($_SESSION['user_id'])) {
    echo json_encode(['status' => false, 'message' => 'Unauthorized']);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);
$action = $input['action'] ?? '';
$company_id = $_SESSION['company_id'];
$user_id = $_SESSION['user_id'];

try {
    if ($action === 'initiate') {
        $month = $input['month'];
        $year = $input['year'];
        
        $result = run_monthly_payroll($company_id, $month, $year, $user_id);
        if ($result['status'] ?? false) {
            log_audit($company_id, $user_id, 'INITIATE_PAYROLL', "Initiated payroll for $month/$year (Run ID: {$result['run_id']})");
        }
        echo json_encode($result);
    }
    elseif ($action === 'check_readiness') {
        // 1. Active Employees
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM employees WHERE company_id = ? AND employment_status IN ('Full Time', 'Active', 'Probation', 'Contract')");
        $stmt->execute([$company_id]);
        $active_count = $stmt->fetchColumn();
        
        // 2. Statutory Settings
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM statutory_settings WHERE company_id = ?");
        $stmt->execute([$company_id]);
        $statutory_configured = ($stmt->fetchColumn() > 0);
        
        // 3. Missing Bank Details (Active only)
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM employees WHERE company_id = ? AND employment_status IN ('Full Time', 'Active', 'Probation', 'Contract') AND (account_number IS NULL OR account_number = '')");
        $stmt->execute([$company_id]);
        $missing_bank = $stmt->fetchColumn();

        // 4. Missing Category
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM employees WHERE company_id = ? AND employment_status IN ('Full Time', 'Active', 'Probation', 'Contract') AND (salary_category_id IS NULL OR salary_category_id = 0)");
        $stmt->execute([$company_id]);
        $missing_cat = $stmt->fetchColumn();
        
        echo json_encode([
            'status' => true,
            'checks' => [
                'active_employees' => $active_count,
                'statutory_set' => $statutory_configured,
                'missing_bank' => $missing_bank,
                'missing_category' => $missing_cat
            ]
        ]);
        exit;
    }
    elseif ($action === 'fetch_sheet') {
        $month = $input['month'];
        $year = $input['year'];
        
        // Fetch Run ID
        $stmt = $pdo->prepare("SELECT * FROM payroll_runs WHERE company_id = ? AND period_month = ? AND period_year = ?");
        $stmt->execute([$company_id, $month, $year]);
        $run = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$run) {
            echo json_encode(['status' => true, 'run' => null, 'entries' => [], 'totals' => [], 'anomalies' => []]);
            exit;
        }
        
        // Fetch Entries with Breakdown
        $stmt = $pdo->prepare("
            SELECT pe.*, e.first_name, e.last_name, e.payroll_id, e.account_number, ps.snapshot_json
            FROM payroll_entries pe
            JOIN employees e ON pe.employee_id = e.id
            LEFT JOIN payroll_snapshots ps ON ps.payroll_entry_id = pe.id
            WHERE pe.payroll_run_id = ?
            ORDER BY e.first_name ASC
        ");
        $stmt->execute([$run['id']]);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        $entries = [];
        $anomalies = [];
        $total_gross = 0;
        $total_deductions = 0;
        $total_net = 0;
        
        foreach ($rows as $row) {
            // Parse snapshot for detailed breakdown
            $snap = json_decode($row['snapshot_json'], true);
            
            // Safety check - ensure $snap is a valid array
            if (!is_array($snap)) {
                $snap = [
                    'breakdown' => [],
                    'statutory' => [],
                    'loans' => []
                ];
            }
            
            // Calculate total loan deduction
            $loan_total = 0;
            if (isset($snap['loans']) && is_array($snap['loans'])) {
                foreach ($snap['loans'] as $loan_item) {
                    if (isset($loan_item['amount'])) {
                        $loan_total += floatval($loan_item['amount']);
                    }
                }
            }
            
            // Construct breakdown object for frontend
            $breakdown = [
                'basic' => floatval($snap['breakdown']['Basic Salary'] ?? 0),
                'housing' => floatval($snap['breakdown']['Housing Allowance'] ?? 0),
                'transport' => floatval($snap['breakdown']['Transport Allowance'] ?? 0),
                'paye' => floatval($snap['statutory']['paye'] ?? 0),
                'pension' => floatval($snap['statutory']['pension_employee'] ?? 0),
                'nhis' => floatval($snap['statutory']['nhis'] ?? 0),
                'loan' => $loan_total
            ];

            $entries[] = [
                'id' => $row['employee_id'],
                'first_name' => $row['first_name'],
                'last_name' => $row['last_name'],
                'payroll_id' => $row['payroll_id'],
                'gross_salary' => floatval($row['gross_salary']),
                'net_pay' => floatval($row['net_pay']),
                'breakdown' => $breakdown
            ];
            
            // Check Anomalies
            if ($row['net_pay'] < 0) {
                $anomalies[] = [
                    'employee' => $row['first_name'] . ' ' . $row['last_name'],
                    'issue' => 'Negative Net Pay',
                    'detail' => 'Net Pay is ' . number_format($row['net_pay'], 2)
                ];
            }
            if (empty($row['account_number'])) {
                $anomalies[] = [
                    'employee' => $row['first_name'] . ' ' . $row['last_name'],
                    'issue' => 'Missing Bank Account',
                    'detail' => 'Payment cannot be processed.'
                ];
            }
            if ($row['gross_salary'] == 0) {
                 $anomalies[] = [
                    'employee' => $row['first_name'] . ' ' . $row['last_name'],
                    'issue' => 'Zero Gross Salary',
                    'detail' => 'Employee has 0 earnings.'
                ];
            }
            
            $total_gross += $row['gross_salary'];
            $total_deductions += $row['total_deductions'];
            $total_net += $row['net_pay'];
        }
        
        echo json_encode([
            'status' => true,
            'run' => $run,
            'entries' => $entries,
            'totals' => [
                'total_gross' => $total_gross,
                'total_deductions' => $total_deductions,
                'total_net' => $total_net,
                'employee_count' => count($entries)
            ],
            'anomalies' => $anomalies
        ]);
    }
    elseif ($action === 'lock_payroll') {
        $run_id = $input['run_id'];
        
        // Update runs status to LOCKED (using status column as primary source of truth)
        $upd = $pdo->prepare("UPDATE payroll_runs SET status='locked', is_locked=1, locked_at=NOW(), locked_by=? WHERE id=? AND company_id=?");
        $upd->execute([$user_id, $run_id, $company_id]);
        
        // --- PROCESS LOAN REPAYMENTS ---
        // Fetch all snapshots for this run
        $stmt = $pdo->prepare("SELECT ps.snapshot_json, pe.id as entry_id 
                               FROM payroll_snapshots ps 
                               JOIN payroll_entries pe ON ps.payroll_entry_id = pe.id 
                               WHERE pe.payroll_run_id = ?");
        $stmt->execute([$run_id]);
        $snapshots = $stmt->fetchAll(PDO::FETCH_ASSOC);

        foreach ($snapshots as $row) {
            $data = json_decode($row['snapshot_json'], true);
            if (isset($data['loans']) && is_array($data['loans'])) {
                foreach ($data['loans'] as $loan_deduction) {
                    $l_id = $loan_deduction['loan_id'];
                    $amt = $loan_deduction['amount'];
                    
                    // Insert Repayment Record
                    $b_after = $loan_deduction['balance_after_projected'];
                    $ins_rep = $pdo->prepare("INSERT INTO loan_repayments (loan_id, payroll_run_id, amount_paid, balance_after) VALUES (?, ?, ?, ?)");
                    $ins_rep->execute([$l_id, $run_id, $amt, $b_after]);
                    
                    // Update Loan Balance & Status
                    $status_sql = "";
                    if ($b_after <= 0) {
                        $status_sql = ", status='completed'";
                    }
                    $upd_loan = $pdo->prepare("UPDATE loans SET balance = balance - ?$status_sql WHERE id=?");
                    $upd_loan->execute([$amt, $l_id]);
                }
            }
        }
        // -------------------------------

        log_audit($company_id, $user_id, 'LOCK_PAYROLL', "Locked payroll Run ID: $run_id");
        echo json_encode(['status' => true, 'message' => 'Payroll Locked']);
    }
    else {
        echo json_encode(['status' => false, 'message' => 'Invalid action']);
    }
} catch (Exception $e) {
    echo json_encode(['status' => false, 'message' => $e->getMessage()]);
}
?>
