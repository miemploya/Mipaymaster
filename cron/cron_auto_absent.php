<?php
/**
 * Cron Job: Auto-Mark Absent Employees
 * 
 * This script runs daily (recommended: 30 minutes after the latest shift's check-in time)
 * to mark employees as absent if they haven't clocked in.
 * 
 * Usage: 
 *   php cron/cron_auto_absent.php
 *   
 * Recommended cron schedule:
 *   30 10 * * 1-5 php /path/to/Mipaymaster/cron/cron_auto_absent.php >> /path/to/logs/cron_absent.log 2>&1
 *   (10:30 AM Mon-Fri, adjust based on your company's latest check-in time + buffer)
 */

// Prevent web access
if (php_sapi_name() !== 'cli' && !defined('CRON_ALLOWED')) {
    die('This script can only be run from the command line.');
}

require_once __DIR__ . '/../includes/functions.php';

// Date configuration
$today = date('Y-m-d');
$day_of_week = date('w'); // 0=Sunday, 6=Saturday
$day_keys = ['sun', 'mon', 'tue', 'wed', 'thu', 'fri', 'sat'];
$today_key = $day_keys[$day_of_week];

$log = [];
$log[] = "=== Auto-Absent Cron Job Started: " . date('Y-m-d H:i:s') . " ===";

try {
    // Get all companies with auto_mark_absent enabled
    $stmt = $pdo->prepare("
        SELECT c.id as company_id, c.name as company_name, ap.*
        FROM companies c
        JOIN attendance_policies ap ON c.id = ap.company_id
        WHERE ap.auto_mark_absent = 1
    ");
    $stmt->execute();
    $companies = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    $log[] = "Found " . count($companies) . " companies with auto-absent enabled.";
    
    foreach ($companies as $company) {
        $company_id = $company['company_id'];
        $log[] = "\n--- Processing: {$company['company_name']} (ID: $company_id) ---";
        
        // Check if today is a working day (for daily mode without per-day schedules)
        $enabled_col = $today_key . '_enabled';
        $is_company_working_day = true;
        
        if (isset($company[$enabled_col])) {
            $is_company_working_day = (bool)$company[$enabled_col];
        }
        
        // Get cutoff time - time after which we mark absent
        // Use today's check-in time + absent_cutoff_minutes
        $checkin_col = $today_key . '_check_in';
        $cutoff_minutes = intval($company['absent_cutoff_minutes'] ?? 60);
        $base_checkin = $company[$checkin_col] ?? $company['check_in_start'] ?? '09:00:00';
        
        $cutoff_time = date('H:i:s', strtotime($base_checkin) + ($cutoff_minutes * 60));
        $current_time = date('H:i:s');
        
        // Skip if it's before cutoff time
        if ($current_time < $cutoff_time) {
            $log[] = "Skipping - Current time ($current_time) is before cutoff ($cutoff_time)";
            continue;
        }
        
        // Get absent deduction amount from policy
        $absent_deduction = floatval($company['absent_deduction_amount'] ?? 0);
        
        // Get all active employees for this company
        $stmt_emp = $pdo->prepare("
            SELECT e.id, e.first_name, e.last_name, e.payroll_id,
                   ea.attendance_mode, ea.shift_id
            FROM employees e
            LEFT JOIN employee_attendance_assignments ea ON e.id = ea.employee_id AND ea.is_active = 1
            WHERE e.company_id = ? AND e.status = 'active'
        ");
        $stmt_emp->execute([$company_id]);
        $employees = $stmt_emp->fetchAll(PDO::FETCH_ASSOC);
        
        $log[] = "Found " . count($employees) . " active employees.";
        $absent_count = 0;
        
        foreach ($employees as $emp) {
            $employee_id = $emp['id'];
            $attendance_mode = $emp['attendance_mode'] ?? $company['default_mode'] ?? 'daily';
            $shift_id = $emp['shift_id'];
            
            // Check if employee has attendance record for today
            $stmt_check = $pdo->prepare("
                SELECT id FROM attendance_logs 
                WHERE employee_id = ? AND date = ?
            ");
            $stmt_check->execute([$employee_id, $today]);
            $existing = $stmt_check->fetch();
            
            if ($existing) {
                // Already has a record (clocked in or manually marked)
                continue;
            }
            
            // Check if today is a working day for this employee
            $is_working_day = false;
            
            if ($attendance_mode === 'shift' && $shift_id) {
                // Check shift schedule
                $stmt_shift = $pdo->prepare("
                    SELECT is_working_day FROM attendance_shift_schedules
                    WHERE shift_id = ? AND day_of_week = ?
                ");
                $stmt_shift->execute([$shift_id, $day_of_week]);
                $shift_day = $stmt_shift->fetch();
                $is_working_day = $shift_day ? (bool)$shift_day['is_working_day'] : false;
            } else {
                // Daily mode - use company policy
                $is_working_day = $is_company_working_day;
            }
            
            if (!$is_working_day) {
                // Not a working day for this employee, skip
                continue;
            }
            
            // Mark as absent
            $stmt_insert = $pdo->prepare("
                INSERT INTO attendance_logs (
                    company_id, employee_id, date, status, 
                    is_auto_marked, auto_marked_at, auto_mark_reason,
                    absent_deduction_amount, final_deduction_amount,
                    method_used, created_at, updated_at
                ) VALUES (
                    ?, ?, ?, 'absent',
                    1, NOW(), 'Cron: No check-in by cutoff time',
                    ?, ?,
                    'system', NOW(), NOW()
                )
            ");
            $stmt_insert->execute([
                $company_id, $employee_id, $today,
                $absent_deduction, $absent_deduction
            ]);
            
            $absent_count++;
            $log[] = "  Marked ABSENT: {$emp['first_name']} {$emp['last_name']} ({$emp['payroll_id']})";
        }
        
        $log[] = "Marked $absent_count employees as absent for $today.";
        
        // Log audit entry
        if ($absent_count > 0) {
            log_audit($company_id, null, 'CRON_AUTO_ABSENT', 
                "Auto-marked $absent_count employees as absent for $today");
        }
    }
    
    $log[] = "\n=== Auto-Absent Cron Job Completed: " . date('Y-m-d H:i:s') . " ===";
    
} catch (Exception $e) {
    $log[] = "ERROR: " . $e->getMessage();
}

// Output log
echo implode("\n", $log) . "\n";
