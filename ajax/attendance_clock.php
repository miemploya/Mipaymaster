<?php
require_once '../includes/functions.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['status' => false, 'message' => 'Invalid request method.']);
    exit;
}

require_login();

// 1. Verify Role & context
if ($_SESSION['role'] !== 'employee') {
    echo json_encode(['status' => false, 'message' => 'Unauthorized access.']);
    exit;
}

$user_id = $_SESSION['user_id'];
$company_id = $_SESSION['company_id'];
$type = $_POST['type'] ?? ''; // 'in' or 'out'

if (!in_array($type, ['in', 'out'])) {
    echo json_encode(['status' => false, 'message' => 'Invalid action type.']);
    exit;
}

try {
    // 2. Fetch Employee & Company Policy
    $stmt = $pdo->prepare("SELECT id FROM employees WHERE user_id = ? AND company_id = ?");
    $stmt->execute([$user_id, $company_id]);
    $employee_id = $stmt->fetchColumn();

    if (!$employee_id) throw new Exception("Employee record not found.");

    // Check Company Policy
    $stmt = $pdo->prepare("SELECT attendance_method FROM companies WHERE id = ?");
    $stmt->execute([$company_id]);
    $method = $stmt->fetchColumn();

    if ($method !== 'self') {
        throw new Exception("Self Check-In is not enabled for this company.");
    }

    // Get Policy Details
    $stmt = $pdo->prepare("SELECT * FROM attendance_policies WHERE company_id = ?");
    $stmt->execute([$company_id]);
    $policy = $stmt->fetch(PDO::FETCH_ASSOC);

    // DUAL MODE SUPPORT: Determine employee's attendance mode
    $attendance_mode = 'daily'; // Default to daily
    $shift_id = null;
    $day_schedule = null; // Will hold check_in, check_out, grace for today
    
    // Check if employee has a specific assignment
    $stmt = $pdo->prepare("
        SELECT attendance_mode, shift_id 
        FROM employee_attendance_assignments 
        WHERE employee_id = ? AND is_active = 1
    ");
    $stmt->execute([$employee_id]);
    $assignment = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($assignment) {
        $attendance_mode = $assignment['attendance_mode'];
        $shift_id = $assignment['shift_id'];
    } else {
        // Use company's default mode
        $attendance_mode = $policy['default_mode'] ?? 'daily';
    }
    
    // Get today's schedule based on mode
    $day_of_week = date('w'); // 0=Sunday, 6=Saturday
    $day_keys = ['sun', 'mon', 'tue', 'wed', 'thu', 'fri', 'sat'];
    $today_key = $day_keys[$day_of_week];
    
    if ($attendance_mode === 'shift' && $shift_id) {
        // SHIFT MODE: Get schedule from shift_schedules table
        $stmt = $pdo->prepare("
            SELECT is_working_day, check_in_time, check_out_time, grace_period_minutes
            FROM attendance_shift_schedules 
            WHERE shift_id = ? AND day_of_week = ?
        ");
        $stmt->execute([$shift_id, $day_of_week]);
        $shift_sched = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($shift_sched && $shift_sched['is_working_day']) {
            $day_schedule = [
                'enabled' => true,
                'check_in' => $shift_sched['check_in_time'],
                'check_out' => $shift_sched['check_out_time'],
                'grace' => intval($shift_sched['grace_period_minutes'] ?? 15)
            ];
        } else {
            // Not a working day for this shift
            $day_schedule = ['enabled' => false];
        }
    } else {
        // DAILY MODE: Get per-day schedule from attendance_policies
        $enabled_col = $today_key . '_enabled';
        $checkin_col = $today_key . '_check_in';
        $checkout_col = $today_key . '_check_out';
        $grace_col = $today_key . '_grace';
        
        // Check if per-day columns exist, fallback to global policy settings
        if (isset($policy[$enabled_col])) {
            $day_schedule = [
                'enabled' => (bool)$policy[$enabled_col],
                'check_in' => $policy[$checkin_col] ?? $policy['check_in_start'],
                'check_out' => $policy[$checkout_col] ?? $policy['check_out_end'],
                'grace' => intval($policy[$grace_col] ?? $policy['grace_period_minutes'] ?? 15)
            ];
        } else {
            // Fallback to global policy settings
            $day_schedule = [
                'enabled' => true,
                'check_in' => $policy['check_in_start'] ?? '08:00:00',
                'check_out' => $policy['check_out_end'] ?? '17:00:00',
                'grace' => intval($policy['grace_period_minutes'] ?? 15)
            ];
        }
    }

    // 3. Process Logic
    $today = date('Y-m-d');
    $now = date('H:i:s');
    $status = 'Present'; 
    $late_minutes = 0;

    // Check for existing record today
    $stmt = $pdo->prepare("SELECT * FROM attendance_logs WHERE employee_id = ? AND date = ?");
    $stmt->execute([$employee_id, $today]);
    $log = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($type === 'in') {
        // AUTO-CLOSE STALE SESSIONS: Close any unclosed sessions from previous days
        $auto_close_time = $day_schedule['check_out'] ?? $policy['check_out_end'] ?? '18:00:00';
        $stmt_stale = $pdo->prepare("
            SELECT id, date, check_in_time 
            FROM attendance_logs 
            WHERE employee_id = ? 
              AND date < ? 
              AND check_out_time IS NULL
        ");
        $stmt_stale->execute([$employee_id, $today]);
        $stale_sessions = $stmt_stale->fetchAll(PDO::FETCH_ASSOC);
        
        foreach ($stale_sessions as $stale) {
            // Auto-close with policy's check_out_end or end of that day
            $auto_checkout_time = $stale['date'] . ' ' . $auto_close_time;
            
            $stmt_close = $pdo->prepare("
                UPDATE attendance_logs 
                SET check_out_time = ?, 
                    status = CASE 
                        WHEN status = 'late' THEN 'late' 
                        ELSE 'Present' 
                    END,
                    requires_review = 1,
                    review_reason = 'Auto-closed: missed checkout',
                    updated_at = NOW() 
                WHERE id = ?
            ");
            $stmt_close->execute([$auto_checkout_time, $stale['id']]);
            
            // Audit log for auto-close
            log_audit($company_id, $user_id, 'AUTO_CLOSE_ATTENDANCE', 
                "Auto-closed missed checkout for {$stale['date']}. Check-out set to: " . $auto_close_time);
        }
        
        if ($log) {
            throw new Exception("You have already clocked in today.");
        }

        // Check if today is a working day
        if (!$day_schedule['enabled']) {
            throw new Exception("Today is not a working day according to your schedule.");
        }

        // Lateness Calculation Logic using day-specific schedule
        $late_minutes = 0;
        $auto_deduction = 0;
        
        if ($day_schedule && !empty($day_schedule['check_in'])) {
            // Calculate expected check-in time (check_in + grace_period)
            $grace = $day_schedule['grace'];
            $expected_time = strtotime($day_schedule['check_in']) + ($grace * 60);
            $current_time = strtotime($now);
            
            // If current time is after expected time (including grace), employee is late
            if ($current_time > $expected_time) {
                $status = 'late';
                $late_minutes = round(($current_time - $expected_time) / 60);
                
                // Calculate deduction based on policy settings
                // Check if lateness deduction is enabled AND allowed for 'self' method
                $apply_for_self = $policy['lateness_apply_self'] ?? 1; // Default to enabled for backward compatibility
                
                if ($policy['lateness_deduction_enabled'] && $apply_for_self) {
                    $deduction_type = $policy['lateness_deduction_type'] ?? 'fixed';
                    $deduction_amount = floatval($policy['lateness_deduction_amount'] ?? 0);
                    $per_minute_rate = floatval($policy['lateness_per_minute_rate'] ?? 0);
                    $max_deduction = floatval($policy['max_lateness_deduction'] ?? 0);
                    
                    switch ($deduction_type) {
                        case 'fixed':
                            // Fixed amount deduction for being late
                            $auto_deduction = $deduction_amount;
                            break;
                        case 'per_minute':
                            // Deduction per minute late
                            $auto_deduction = $late_minutes * $per_minute_rate;
                            break;
                        case 'percentage':
                            // This would require knowing daily salary - placeholder for future
                            // For now, treat as fixed amount
                            $auto_deduction = $deduction_amount;
                            break;
                    }
                    
                    // Apply max cap if set
                    if ($max_deduction > 0 && $auto_deduction > $max_deduction) {
                        $auto_deduction = $max_deduction;
                    }
                }
            }
        }

        $stmt = $pdo->prepare("INSERT INTO attendance_logs (company_id, employee_id, date, check_in_time, status, method_used, auto_deduction_amount, final_deduction_amount, created_at) VALUES (?, ?, ?, NOW(), ?, 'self', ?, ?, NOW())");
        $stmt->execute([$company_id, $employee_id, $today, $status, $auto_deduction, $auto_deduction]);
        
        $msg = "Clocked In Successfully at " . date('H:i');
        if ($status === 'late') {
            $msg .= " (Late by {$late_minutes} mins";
            if ($auto_deduction > 0) {
                $msg .= ", Deduction: ₦" . number_format($auto_deduction, 2);
            }
            $msg .= ")";
        }

    } else { // OUT
        if (!$log) {
            throw new Exception("You have not clocked in yet.");
        }
        if ($log['check_out_time']) {
            throw new Exception("You have already clocked out today.");
        }

        // Calculate Duration
        $t1 = strtotime($log['check_in_time']);
        $t2 = time();
        $hours = round(($t2 - $t1) / 3600, 2);

        $stmt = $pdo->prepare("UPDATE attendance_logs SET check_out_time = NOW(), updated_at = NOW() WHERE id = ?");
        $stmt->execute([$log['id']]);

        $msg = "Clocked Out Successfully. Worked: {$hours} hrs.";
    }
    
    // Audit? Maybe too noisy for daily clockins, but good for debugging.
    // log_audit($company_id, $user_id, 'ATTENDANCE', "$type: $msg");

    echo json_encode(['status' => true, 'message' => $msg]);

} catch (Exception $e) {
    echo json_encode(['status' => false, 'message' => $e->getMessage()]);
}
