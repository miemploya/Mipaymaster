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
        
        // Extract filter parameters
        $department = trim($input['department'] ?? '');
        $category = $input['category'] ?? '';
        
        // Basic validation
        if (!$month || !$year) throw new Exception("Month and Year required");

        // Run Engine with filters
        $result = run_monthly_payroll($company_id, $month, $year, $user_id, $department, $category);
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
            SELECT pe.*, ps.snapshot_json, e.first_name, e.last_name, e.payroll_id, 
                   e.job_title as designation, e.bank_name, e.account_number,
                   d.name as department
            FROM payroll_entries pe
            JOIN employees e ON pe.employee_id = e.id
            LEFT JOIN payroll_snapshots ps ON pe.id = ps.payroll_entry_id
            LEFT JOIN departments d ON e.department_id = d.id
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
                'overtime_notes' => $snap['overtime']['notes'] ?? '',
                // ALL COMPONENT VALUES (for dynamic columns)
                'all_components' => $snap['breakdown'] ?? []
            ];
            unset($row['snapshot_json']); // Remove heavy json string
            $entries[] = $row;
        }
        
        // Calculate Totals (exclude negative net_pay from total_net)
        $stmt_totals = $pdo->prepare("
            SELECT 
                SUM(gross_salary) as total_gross,
                SUM(total_deductions) as total_deductions,
                SUM(CASE WHEN net_pay > 0 THEN net_pay ELSE 0 END) as total_net,
                COUNT(id) as employee_count,
                SUM(CASE WHEN net_pay < 0 THEN 1 ELSE 0 END) as negative_count
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
        $run_id = intval($input['run_id']);
        
        if (!$run_id) {
            throw new Exception("Invalid payroll run ID");
        }
        
        // Verify run exists and belongs to this company
        $stmt = $pdo->prepare("SELECT id, status FROM payroll_runs WHERE id = ? AND company_id = ?");
        $stmt->execute([$run_id, $company_id]);
        $run = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$run) {
            throw new Exception("Payroll run not found");
        }
        
        if ($run['status'] === 'locked') {
            throw new Exception("Payroll is already locked");
        }
        
        // Lock the payroll
        $stmt = $pdo->prepare("UPDATE payroll_runs SET status = 'locked', locked_at = NOW(), locked_by = ? WHERE id = ?");
        $stmt->execute([$user_id, $run_id]);
        
        echo json_encode([
            'status' => true,
            'message' => 'Payroll locked successfully'
        ]);
    }
    elseif ($action === 'reject_payroll') {
        // Reject and delete a draft payroll run
        $run_id = intval($input['run_id']);
        $reason = trim($input['reason'] ?? '');
        
        if (!$run_id) {
            throw new Exception("Invalid payroll run ID");
        }
        
        // Verify the run exists and belongs to this company
        $stmt = $pdo->prepare("SELECT id, status FROM payroll_runs WHERE id = ? AND company_id = ?");
        $stmt->execute([$run_id, $company_id]);
        $run = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$run) {
            throw new Exception("Payroll run not found");
        }
        
        if ($run['status'] === 'locked' || $run['status'] === 'paid') {
            throw new Exception("Cannot reject a locked or paid payroll");
        }
        
        // Start transaction
        $pdo->beginTransaction();
        try {
            // Delete payroll snapshots
            $pdo->prepare("DELETE ps FROM payroll_snapshots ps 
                           JOIN payroll_entries pe ON ps.payroll_entry_id = pe.id 
                           WHERE pe.payroll_run_id = ?")
                ->execute([$run_id]);
            
            // Delete payroll entries
            $pdo->prepare("DELETE FROM payroll_entries WHERE payroll_run_id = ?")->execute([$run_id]);
            
            // Delete the payroll run
            $pdo->prepare("DELETE FROM payroll_runs WHERE id = ?")->execute([$run_id]);
            
            // Log the rejection (optional - if you have an audit log table)
            // Could also store the reason in a payroll_logs table
            
            $pdo->commit();
            
            echo json_encode([
                'status' => true,
                'message' => 'Payroll rejected successfully'
            ]);
        } catch (Exception $e) {
            $pdo->rollBack();
            throw $e;
        }
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
    elseif ($action === 'get_payslips') {
        // Fetch all payslip entries for this company
        $stmt = $pdo->prepare("
            SELECT 
                pe.id,
                pe.employee_id,
                pe.payroll_run_id,
                CONCAT(e.first_name, ' ', e.last_name) as employee_name,
                e.payroll_id,
                d.name as department,
                pr.month,
                pr.year,
                pr.status,
                pe.gross_salary,
                pe.total_deductions,
                pe.net_pay,
                CONCAT(
                    CASE pr.month 
                        WHEN 1 THEN 'Jan' WHEN 2 THEN 'Feb' WHEN 3 THEN 'Mar'
                        WHEN 4 THEN 'Apr' WHEN 5 THEN 'May' WHEN 6 THEN 'Jun'
                        WHEN 7 THEN 'Jul' WHEN 8 THEN 'Aug' WHEN 9 THEN 'Sep'
                        WHEN 10 THEN 'Oct' WHEN 11 THEN 'Nov' WHEN 12 THEN 'Dec'
                    END, ' ', pr.year
                ) as period
            FROM payroll_entries pe
            JOIN payroll_runs pr ON pe.payroll_run_id = pr.id
            JOIN employees e ON pe.employee_id = e.id
            LEFT JOIN departments d ON e.department_id = d.id
            WHERE pr.company_id = ?
            ORDER BY pr.year DESC, pr.month DESC, e.last_name ASC
        ");
        $stmt->execute([$company_id]);
        $payslips = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        echo json_encode(['status' => true, 'payslips' => $payslips]);
    }
    elseif ($action === 'get_employee_ot_config') {
        // Get employee-specific overtime configuration based on their shift assignment
        $employee_id = intval($input['employee_id'] ?? 0);
        
        if (!$employee_id) {
            throw new Exception("Employee ID is required");
        }
        
        // Get company behaviour settings as defaults
        $stmt_behaviour = $pdo->prepare("SELECT * FROM behaviour_settings WHERE company_id = ?");
        $stmt_behaviour->execute([$company_id]);
        $behaviour = $stmt_behaviour->fetch(PDO::FETCH_ASSOC);
        
        $default_daily_hours = floatval($behaviour['daily_work_hours'] ?? 8);
        $default_monthly_days = intval($behaviour['monthly_work_days'] ?? 22);
        $ot_rate = floatval($behaviour['overtime_rate'] ?? 1.5);
        
        // Get employee's shift assignment
        $stmt_assign = $pdo->prepare("
            SELECT ea.attendance_mode, ea.shift_id,
                   s.name as shift_name, s.shift_type
            FROM employee_attendance_assignments ea
            LEFT JOIN attendance_shifts s ON ea.shift_id = s.id
            WHERE ea.employee_id = ? AND ea.is_active = 1
            ORDER BY ea.effective_from DESC
            LIMIT 1
        ");
        $stmt_assign->execute([$employee_id]);
        $assignment = $stmt_assign->fetch(PDO::FETCH_ASSOC);
        
        $mode = $assignment['attendance_mode'] ?? 'daily';
        $shift_name = null;
        $daily_hours = $default_daily_hours;
        $monthly_days = $default_monthly_days;
        
        if ($mode === 'shift' && !empty($assignment['shift_id'])) {
            $shift_id = $assignment['shift_id'];
            $shift_type = $assignment['shift_type'] ?? 'fixed';
            $shift_name = $assignment['shift_name'];
            
            if ($shift_type === 'fixed') {
                // Calculate from 7-day schedule
                $stmt_sched = $pdo->prepare("
                    SELECT is_working_day, check_in_time, check_out_time
                    FROM attendance_shift_schedules
                    WHERE shift_id = ?
                ");
                $stmt_sched->execute([$shift_id]);
                $schedules = $stmt_sched->fetchAll(PDO::FETCH_ASSOC);
                
                $total_hours = 0;
                $working_days_per_week = 0;
                
                foreach ($schedules as $sched) {
                    if ($sched['is_working_day'] && $sched['check_in_time'] && $sched['check_out_time']) {
                        $working_days_per_week++;
                        // Calculate hours for this day
                        $in = new DateTime($sched['check_in_time']);
                        $out = new DateTime($sched['check_out_time']);
                        // Handle overnight shifts
                        if ($out < $in) {
                            $out->modify('+1 day');
                        }
                        $diff = $in->diff($out);
                        $hours = $diff->h + ($diff->i / 60);
                        $total_hours += $hours;
                    }
                }
                
                if ($working_days_per_week > 0) {
                    $daily_hours = round($total_hours / $working_days_per_week, 2);
                    // Approximate monthly days: working_days_per_week * 4.33 weeks
                    $monthly_days = round($working_days_per_week * 4.33);
                }
            } else {
                // Rotational/Weekly/Monthly: Get from attendance_shift_daily_hours
                $stmt_hours = $pdo->prepare("
                    SELECT check_in_time, check_out_time
                    FROM attendance_shift_daily_hours
                    WHERE shift_id = ?
                ");
                $stmt_hours->execute([$shift_id]);
                $hours_row = $stmt_hours->fetch(PDO::FETCH_ASSOC);
                
                if ($hours_row && $hours_row['check_in_time'] && $hours_row['check_out_time']) {
                    $in = new DateTime($hours_row['check_in_time']);
                    $out = new DateTime($hours_row['check_out_time']);
                    if ($out < $in) {
                        $out->modify('+1 day');
                    }
                    $diff = $in->diff($out);
                    $daily_hours = round($diff->h + ($diff->i / 60), 2);
                }
                
                // For rotational shifts, estimate monthly days based on pattern
                if ($shift_type === 'rotational') {
                    // 24 on / 24 off = ~15 days per month
                    $monthly_days = 15;
                } elseif ($shift_type === 'weekly') {
                    // Week on / week off = ~11 days per month
                    $monthly_days = 11;
                } elseif ($shift_type === 'monthly') {
                    // Month on / month off = varies, use average ~11
                    $monthly_days = 11;
                }
            }
        } elseif ($mode === 'daily') {
            // Use company attendance_policies for daily mode
            $stmt_policy = $pdo->prepare("
                SELECT mon_enabled, mon_check_in, mon_check_out,
                       tue_enabled, tue_check_in, tue_check_out,
                       wed_enabled, wed_check_in, wed_check_out,
                       thu_enabled, thu_check_in, thu_check_out,
                       fri_enabled, fri_check_in, fri_check_out,
                       sat_enabled, sat_check_in, sat_check_out,
                       sun_enabled, sun_check_in, sun_check_out
                FROM attendance_policies
                WHERE company_id = ?
            ");
            $stmt_policy->execute([$company_id]);
            $policy = $stmt_policy->fetch(PDO::FETCH_ASSOC);
            
            if ($policy) {
                $days = ['mon', 'tue', 'wed', 'thu', 'fri', 'sat', 'sun'];
                $total_hours = 0;
                $working_days_per_week = 0;
                
                foreach ($days as $day) {
                    if ($policy[$day . '_enabled'] && $policy[$day . '_check_in'] && $policy[$day . '_check_out']) {
                        $working_days_per_week++;
                        $in = new DateTime($policy[$day . '_check_in']);
                        $out = new DateTime($policy[$day . '_check_out']);
                        if ($out < $in) {
                            $out->modify('+1 day');
                        }
                        $diff = $in->diff($out);
                        $total_hours += $diff->h + ($diff->i / 60);
                    }
                }
                
                if ($working_days_per_week > 0) {
                    $daily_hours = round($total_hours / $working_days_per_week, 2);
                    $monthly_days = round($working_days_per_week * 4.33);
                }
            }
        }
        
        echo json_encode([
            'status' => true,
            'daily_hours' => $daily_hours,
            'monthly_days' => $monthly_days,
            'ot_rate' => $ot_rate,
            'shift_name' => $shift_name,
            'mode' => $mode
        ]);
    }
    else {
        throw new Exception("Invalid Action");
    }

} catch (Exception $e) {
    echo json_encode(['status' => false, 'message' => $e->getMessage()]);
}
?>
