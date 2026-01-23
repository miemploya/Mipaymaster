<?php
require_once '../../includes/functions.php';
require_once '../../includes/payroll_engine.php';
require_once '../../includes/payroll_lock.php';
require_login();

header('Content-Type: application/json');

$input = json_decode(file_get_contents('php://input'), true);
$action = $input['action'] ?? $_GET['action'] ?? '';
$company_id = $_SESSION['company_id'];
$user_id = $_SESSION['user_id'];

try {
    if ($action === 'initiate') {
        $month = $input['month'];
        $year = $input['year'];
        
        // Basic validation
        if (!$month || !$year) throw new Exception("Month and Year required");

        // Run Engine
        $result = run_monthly_payroll($company_id, $month, $year, $user_id);
        echo json_encode($result);
        
    } 
    elseif ($action === 'fetch_sheet') {
        $month = $input['month'] ?? date('m');
        $year = $input['year'] ?? date('Y');
        
        // Find the run
        $stmt = $pdo->prepare("SELECT * FROM payroll_runs WHERE company_id = ? AND period_month = ? AND period_year = ?");
        $stmt->execute([$company_id, $month, $year]);
        $run = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$run) {
            echo json_encode(['status' => true, 'run' => null, 'entries' => [], 'totals' => []]);
            exit;
        }
        
        // Fetch Entries with Snapshots
        $stmt_entries = $pdo->prepare("
            SELECT pe.*, ps.snapshot_json, e.first_name, e.last_name, e.payroll_id 
            FROM payroll_entries pe
            JOIN employees e ON pe.employee_id = e.id
            LEFT JOIN payroll_snapshots ps ON pe.id = ps.payroll_entry_id
            WHERE pe.payroll_run_id = ?
            ORDER BY e.last_name ASC
        ");
        $stmt_entries->execute([$run['id']]);
        $raw_entries = $stmt_entries->fetchAll(PDO::FETCH_ASSOC);
        
        $entries = [];
        foreach($raw_entries as $row) {
            $snap = json_decode($row['snapshot_json'], true);
            
            // Extract common components
            // Note: Keys in breakdown match Component Names
            $basic = $snap['breakdown']['Basic Salary'] ?? 0;
            $housing = $snap['breakdown']['Housing Allowance'] ?? 0;
            $transport = $snap['breakdown']['Transport Allowance'] ?? 0;
            
            $paye = $snap['statutory']['paye'] ?? 0;
            $pension = $snap['statutory']['pension_employee'] ?? 0;
            $nhis = $snap['statutory']['nhis'] ?? 0;
            $nhf = $snap['statutory']['nhf'] ?? 0;
            
            $row['breakdown'] = [
                'basic' => $basic,
                'housing' => $housing,
                'transport' => $transport,
                'paye' => $paye,
                'pension' => $pension,
                'nhis' => $nhis,
                'nhf' => $nhf
            ];
            unset($row['snapshot_json']); // Remove heavy json string
            $entries[] = $row;
        }
        
        // Calculate Totals
        $stmt_totals = $pdo->prepare("
            SELECT 
                SUM(gross_salary) as total_gross,
                SUM(total_deductions) as total_deductions,
                SUM(net_pay) as total_net,
                COUNT(id) as employee_count
            FROM payroll_entries WHERE payroll_run_id = ?
        ");
        $stmt_totals->execute([$run['id']]);
        $totals = $stmt_totals->fetch(PDO::FETCH_ASSOC);

        echo json_encode([
            'status' => true,
            'run' => $run,
            'entries' => $entries,
            'totals' => $totals
        ]);
        
    }
    elseif ($action === 'lock_payroll') {
        $run_id = $input['run_id'];
        $locker = new PayrollLock($pdo);
        $result = $locker->lock_payroll($run_id, $user_id);
        echo json_encode($result);
    }
    else {
        throw new Exception("Invalid Action");
    }

} catch (Exception $e) {
    echo json_encode(['status' => false, 'message' => $e->getMessage()]);
}
?>
