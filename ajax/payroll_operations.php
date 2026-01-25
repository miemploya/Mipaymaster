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
        echo json_encode($result);
    }
    elseif ($action === 'fetch_sheet') {
        $month = $input['month'];
        $year = $input['year'];
        
        // Fetch Run ID
        $stmt = $pdo->prepare("SELECT * FROM payroll_runs WHERE company_id = ? AND period_month = ? AND period_year = ?");
        $stmt->execute([$company_id, $month, $year]);
        $run = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$run) {
            echo json_encode(['status' => false, 'message' => 'No payroll run found for this period.']);
            exit;
        }
        
        // Fetch Entries with Breakdown
        $stmt = $pdo->prepare("
            SELECT pe.*, e.first_name, e.last_name, e.payroll_id, ps.snapshot_json
            FROM payroll_entries pe
            JOIN employees e ON pe.employee_id = e.id
            LEFT JOIN payroll_snapshots ps ON ps.payroll_entry_id = pe.id
            WHERE pe.payroll_run_id = ?
            ORDER BY e.first_name ASC
        ");
        $stmt->execute([$run['id']]);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        $entries = [];
        $total_gross = 0;
        $total_deductions = 0;
        $total_net = 0;
        
        foreach ($rows as $row) {
            // Parse snapshot for detailed breakdown
            $snap = json_decode($row['snapshot_json'], true);
            
            // Construct breakdown object for frontend
            $breakdown = [
                'basic' => $snap['breakdown']['Basic Salary'] ?? 0,
                'housing' => $snap['breakdown']['Housing Allowance'] ?? 0,
                'transport' => $snap['breakdown']['Transport Allowance'] ?? 0,
                'paye' => $snap['statutory']['paye'] ?? 0,
                'pension' => $snap['statutory']['pension_employee'] ?? 0,
                'nhis' => $snap['statutory']['nhis'] ?? 0
            ];

            $entries[] = [
                'id' => $row['employee_id'],
                'first_name' => $row['first_name'],
                'last_name' => $row['last_name'],
                'payroll_id' => $row['payroll_id'],
                'gross_salary' => $row['gross_salary'],
                'net_pay' => $row['net_pay'],
                'breakdown' => $breakdown
            ];
            
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
            ]
        ]);
    }
    elseif ($action === 'lock_payroll') {
        $run_id = $input['run_id'];
        
        // Update status
        $stmt = $pdo->prepare("UPDATE payroll_runs SET status = 'locked', locked_at = NOW(), locked_by = ? WHERE id = ? AND company_id = ?");
        $stmt->execute([$user_id, $run_id, $company_id]);
        
        if ($stmt->rowCount() > 0) {
            echo json_encode(['status' => true, 'message' => 'Payroll successfully locked.']);
        } else {
            echo json_encode(['status' => false, 'message' => 'Could not lock payroll. ID may be invalid.']);
        }
    }
    else {
        echo json_encode(['status' => false, 'message' => 'Invalid action']);
    }
} catch (Exception $e) {
    echo json_encode(['status' => false, 'message' => $e->getMessage()]);
}
?>
