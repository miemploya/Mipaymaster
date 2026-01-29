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
            
            // Safety check for malformed snapshot
            if (!is_array($snap)) {
                $snap = ['breakdown' => [], 'statutory' => [], 'loans' => []];
            }
            
            // Extract common components
            // Note: Keys in breakdown match Component Names
            $basic = floatval($snap['breakdown']['Basic Salary'] ?? 0);
            $housing = floatval($snap['breakdown']['Housing Allowance'] ?? 0);
            $transport = floatval($snap['breakdown']['Transport Allowance'] ?? 0);
            
            $paye = floatval($snap['statutory']['paye'] ?? 0);
            $pension = floatval($snap['statutory']['pension_employee'] ?? 0);
            $nhis = floatval($snap['statutory']['nhis'] ?? 0);
            $nhf = floatval($snap['statutory']['nhf'] ?? 0);
            
            // Calculate loan deduction from snapshot
            $loan_total = 0;
            if (isset($snap['loans']) && is_array($snap['loans'])) {
                foreach ($snap['loans'] as $loan_item) {
                    if (isset($loan_item['amount'])) {
                        $loan_total += floatval($loan_item['amount']);
                    }
                }
            }
            
            // Extract attendance/lateness deduction
            $lateness_deduction = floatval($snap['attendance']['deduction'] ?? 0);
            
            // Extract bonuses total (custom_bonuses + onetime_adjustments bonuses)
            $bonus_total = 0;
            if (isset($snap['custom_bonuses']) && is_array($snap['custom_bonuses'])) {
                foreach ($snap['custom_bonuses'] as $bonus_item) {
                    $bonus_total += floatval($bonus_item['amount'] ?? 0);
                }
            }
            if (isset($snap['onetime_adjustments']) && is_array($snap['onetime_adjustments'])) {
                foreach ($snap['onetime_adjustments'] as $adj) {
                    if ($adj['type'] === 'bonus') {
                        $bonus_total += floatval($adj['amount'] ?? 0);
                    }
                }
            }
            
            // Extract custom deductions total (custom_deductions + onetime_adjustments deductions)
            $custom_ded_total = 0;
            $deduction_items = [];
            if (isset($snap['custom_deductions']) && is_array($snap['custom_deductions'])) {
                foreach ($snap['custom_deductions'] as $ded_item) {
                    $amt = floatval($ded_item['amount'] ?? 0);
                    $custom_ded_total += $amt;
                    if ($amt > 0) {
                        $deduction_items[] = [
                            'name' => $ded_item['name'] ?? 'Custom Deduction',
                            'amount' => $amt
                        ];
                    }
                }
            }
            if (isset($snap['onetime_adjustments']) && is_array($snap['onetime_adjustments'])) {
                foreach ($snap['onetime_adjustments'] as $adj) {
                    if ($adj['type'] === 'deduction') {
                        $amt = floatval($adj['amount'] ?? 0);
                        $custom_ded_total += $amt;
                        if ($amt > 0) {
                            $deduction_items[] = [
                                'name' => $adj['name'] ?? 'One-Time Deduction',
                                'amount' => $amt
                            ];
                        }
                    }
                }
            }
            
            // Build bonus items array with names
            $bonus_items = [];
            if (isset($snap['custom_bonuses']) && is_array($snap['custom_bonuses'])) {
                foreach ($snap['custom_bonuses'] as $bonus_item) {
                    $amt = floatval($bonus_item['amount'] ?? 0);
                    if ($amt > 0) {
                        $bonus_items[] = [
                            'name' => $bonus_item['name'] ?? 'Recurring Bonus',
                            'amount' => $amt
                        ];
                    }
                }
            }
            if (isset($snap['onetime_adjustments']) && is_array($snap['onetime_adjustments'])) {
                foreach ($snap['onetime_adjustments'] as $adj) {
                    if ($adj['type'] === 'bonus') {
                        $amt = floatval($adj['amount'] ?? 0);
                        if ($amt > 0) {
                            $bonus_items[] = [
                                'name' => $adj['name'] ?? 'One-Time Bonus',
                                'amount' => $amt
                            ];
                        }
                    }
                }
            }
            
            // Build loan items array with names
            $loan_items = [];
            if (isset($snap['loans']) && is_array($snap['loans'])) {
                foreach ($snap['loans'] as $loan_item) {
                    $amt = floatval($loan_item['amount'] ?? 0);
                    if ($amt > 0) {
                        $loan_items[] = [
                            'name' => $loan_item['type'] ?? 'Loan Repayment',
                            'amount' => $amt
                        ];
                    }
                }
            }
            
            $row['breakdown'] = [
                'basic' => $basic,
                'housing' => $housing,
                'transport' => $transport,
                'paye' => $paye,
                'pension' => $pension,
                'nhis' => $nhis,
                'nhf' => $nhf,
                'loan' => $loan_total,
                'lateness' => $lateness_deduction,
                'bonus' => $bonus_total,
                'custom_deductions' => $custom_ded_total,
                // Itemized arrays for payslip
                'bonus_items' => $bonus_items,
                'deduction_items' => $deduction_items,
                'loan_items' => $loan_items,
                // Overtime data from payroll_overtime table
                'overtime_hours' => floatval($snap['overtime']['hours'] ?? 0),
                'overtime_pay' => floatval($snap['overtime']['amount'] ?? 0),
                'overtime_notes' => $snap['overtime']['notes'] ?? ''
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
    elseif ($action === 'add_adjustment') {
        // Add one-time bonus/deduction for this payroll period
        $employee_id = intval($input['employee_id']);
        $type = $input['type']; // 'bonus' or 'deduction'
        $name = trim($input['name']);
        $amount = floatval($input['amount']);
        $notes = trim($input['notes'] ?? '');
        $month = intval($input['month']);
        $year = intval($input['year']);
        
        if (!$employee_id || !$name || !$amount) {
            throw new Exception("Employee, name, and amount are required");
        }
        if (!in_array($type, ['bonus', 'deduction'])) {
            throw new Exception("Invalid adjustment type");
        }
        
        $stmt = $pdo->prepare("
            INSERT INTO payroll_adjustments (company_id, employee_id, payroll_month, payroll_year, type, name, amount, notes, created_by)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");
        $stmt->execute([$company_id, $employee_id, $month, $year, $type, $name, $amount, $notes, $user_id]);
        
        echo json_encode(['status' => true, 'message' => 'Adjustment saved. Re-run payroll to apply changes.']);
    }
    elseif ($action === 'save_overtime') {
        // Save overtime hours for an employee for this payroll period
        $employee_id = intval($input['employee_id']);
        $hours = floatval($input['hours']);
        $notes = trim($input['notes'] ?? '');
        $month = intval($input['month']);
        $year = intval($input['year']);
        
        if (!$employee_id) {
            throw new Exception("Employee ID is required");
        }
        
        // Check if overtime record exists for this period
        $stmt = $pdo->prepare("SELECT id FROM payroll_overtime WHERE company_id = ? AND employee_id = ? AND payroll_month = ? AND payroll_year = ?");
        $stmt->execute([$company_id, $employee_id, $month, $year]);
        $existing = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($existing) {
            // Update existing record
            $stmt = $pdo->prepare("UPDATE payroll_overtime SET overtime_hours = ?, notes = ? WHERE id = ?");
            $stmt->execute([$hours, $notes, $existing['id']]);
        } else {
            // Insert new record
            $stmt = $pdo->prepare("
                INSERT INTO payroll_overtime (company_id, employee_id, payroll_month, payroll_year, overtime_hours, notes, created_by)
                VALUES (?, ?, ?, ?, ?, ?, ?)
            ");
            $stmt->execute([$company_id, $employee_id, $month, $year, $hours, $notes, $user_id]);
        }
        
        echo json_encode(['status' => true, 'message' => 'Overtime saved. Re-run payroll to apply changes.']);
    }
    else {
        throw new Exception("Invalid Action");
    }

} catch (Exception $e) {
    echo json_encode(['status' => false, 'message' => $e->getMessage()]);
}
?>
