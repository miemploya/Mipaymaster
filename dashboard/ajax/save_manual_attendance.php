<?php
require_once '../../includes/functions.php';
require_login();

header('Content-Type: application/json');

try {
    $company_id = $_SESSION['company_id'];
    $user_id = $_SESSION['user_id'];

    // Get Input
    $input = json_decode(file_get_contents('php://input'), true);
    
    $employee_id = $input['employee_id'] ?? null;
    $date = $input['date'] ?? null;
    $status = $input['status'] ?? 'Present';
    $check_in = $input['check_in'] ?? null;
    $check_out = $input['check_out'] ?? null;
    $reason = $input['reason'] ?? null;
    $custom_deduction = $input['custom_deduction'] ?? '';

    // Validation
    if (!$employee_id || !$date || !$reason) {
        throw new Exception("Employee, Date, and Reason are required.");
    }
    
    // Format times
    $check_in_dt = $check_in ? "$date $check_in:00" : null;
    $check_out_dt = $check_out ? "$date $check_out:00" : null;
    
    // Calculate lateness deduction for 'Late' status
    $auto_deduction = 0;
    $final_deduction = 0;
    
    // If custom deduction is provided, use it directly
    if ($custom_deduction !== '' && is_numeric($custom_deduction)) {
        $final_deduction = floatval($custom_deduction);
        $auto_deduction = $final_deduction; // For consistency
    } elseif (strtolower($status) === 'late' && $check_in) {
        // Fetch attendance policy
        $stmt_policy = $pdo->prepare("SELECT * FROM attendance_policies WHERE company_id = ?");
        $stmt_policy->execute([$company_id]);
        $policy = $stmt_policy->fetch(PDO::FETCH_ASSOC);
        
        // Check if lateness deduction is enabled for manual method
        $apply_for_manual = $policy['lateness_apply_manual'] ?? 0;
        
        if ($policy && $policy['lateness_deduction_enabled'] && $apply_for_manual) {
            // Calculate late minutes
            $grace = intval($policy['grace_period_minutes'] ?? 0);
            $expected_time = strtotime($policy['check_in_start']) + ($grace * 60);
            $actual_time = strtotime($check_in);
            
            if ($actual_time > $expected_time) {
                $late_minutes = round(($actual_time - $expected_time) / 60);
                
                $deduction_type = $policy['lateness_deduction_type'] ?? 'fixed';
                $deduction_amount = floatval($policy['lateness_deduction_amount'] ?? 0);
                $per_minute_rate = floatval($policy['lateness_per_minute_rate'] ?? 0);
                $max_deduction = floatval($policy['max_lateness_deduction'] ?? 0);
                
                switch ($deduction_type) {
                    case 'fixed':
                        $auto_deduction = $deduction_amount;
                        break;
                    case 'per_minute':
                        $auto_deduction = $late_minutes * $per_minute_rate;
                        break;
                    default:
                        $auto_deduction = $deduction_amount;
                }
                
                // Apply max cap
                if ($max_deduction > 0 && $auto_deduction > $max_deduction) {
                    $auto_deduction = $max_deduction;
                }
                
                $final_deduction = $auto_deduction;
            }
        }
    }
    
    // Check for existing record
    $stmt = $pdo->prepare("SELECT id FROM attendance_logs WHERE employee_id = ? AND date = ?");
    $stmt->execute([$employee_id, $date]);
    $existing = $stmt->fetch();

    if ($existing) {
        // UPDATE
        $stmt_upd = $pdo->prepare("
            UPDATE attendance_logs 
            SET status = ?, 
                check_in_time = ?, 
                check_out_time = ?, 
                method_used = 'manual',
                auto_deduction_amount = ?,
                final_deduction_amount = ?,
                updated_at = NOW()
            WHERE id = ?
        ");
        $stmt_upd->execute([$status, $check_in_dt, $check_out_dt, $auto_deduction, $final_deduction, $existing['id']]);
        
    } else {
        // INSERT
        $stmt_ins = $pdo->prepare("
            INSERT INTO attendance_logs 
            (company_id, employee_id, date, status, check_in_time, check_out_time, method_used, auto_deduction_amount, final_deduction_amount, created_at, updated_at)
            VALUES (?, ?, ?, ?, ?, ?, 'manual', ?, ?, NOW(), NOW())
        ");
        $stmt_ins->execute([$company_id, $employee_id, $date, $status, $check_in_dt, $check_out_dt, $auto_deduction, $final_deduction]);
    }

    echo json_encode(['status' => true, 'message' => 'Attendance record saved successfully.']);

} catch (Exception $e) {
    http_response_code(400);
    echo json_encode(['status' => false, 'message' => $e->getMessage()]);
}

