<?php
/**
 * Lazy Cron - Automatic Background Task Execution
 * 
 * This system runs background tasks (auto-checkout, auto-absent) automatically
 * when admins access key pages. No external scheduled tasks required!
 * 
 * How it works:
 * 1. When an admin loads dashboard/attendance pages, this is called
 * 2. It checks when each task last ran (stored in DB)
 * 3. If enough time has passed, it runs the task
 * 4. Tasks run quickly and don't slow down page loads
 */

require_once __DIR__ . '/shift_schedule_resolver.php';

/**
 * Run lazy cron tasks if due
 * Call this from dashboard pages: run_lazy_cron($pdo, $company_id);
 */
function run_lazy_cron($pdo, $company_id) {
    // Check if we should run (avoid running on every single page load)
    $last_check = $_SESSION['lazy_cron_last_check'] ?? 0;
    $now = time();
    
    // Only check every 5 minutes per session to avoid hammering DB
    if ($now - $last_check < 300) {
        return;
    }
    $_SESSION['lazy_cron_last_check'] = $now;
    
    try {
        // Get company's automation settings
        $stmt = $pdo->prepare("SELECT * FROM attendance_policies WHERE company_id = ?");
        $stmt->execute([$company_id]);
        $policy = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$policy) return;
        
        // Run auto-checkout if enabled
        if (!empty($policy['auto_checkout_enabled'])) {
            run_lazy_auto_checkout($pdo, $company_id, $policy);
        }
        
        // Run auto-absent if enabled
        if (!empty($policy['auto_absent_enabled'])) {
            run_lazy_auto_absent($pdo, $company_id, $policy);
        }
        
    } catch (Exception $e) {
        error_log("Lazy Cron Error: " . $e->getMessage());
    }
}

/**
 * Auto-checkout: Close open sessions for employees who forgot to clock out
 */
function run_lazy_auto_checkout($pdo, $company_id, $policy) {
    $today = date('Y-m-d');
    $hours_after = (int) ($policy['auto_checkout_hours_after'] ?? 3);
    
    // Find open sessions where current time is past (checkout_time + hours_after)
    $stmt = $pdo->prepare("
        SELECT al.id, al.employee_id, al.check_in_time, al.date,
               e.first_name, e.last_name
        FROM attendance_logs al
        JOIN employees e ON al.employee_id = e.id
        WHERE al.company_id = ? 
          AND al.check_in_time IS NOT NULL
          AND al.check_out_time IS NULL
          AND al.date <= ?
    ");
    $stmt->execute([$company_id, $today]);
    $open_sessions = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    foreach ($open_sessions as $session) {
        $employee_id = $session['employee_id'];
        $log_date = $session['date'];
        
        // Get expected checkout time for this employee on that date
        $schedule = resolve_employee_schedule($pdo, $employee_id, $log_date, $company_id);
        $expected_checkout = $schedule['check_out'] ?? $schedule['expected_out'] ?? '17:00:00';
        
        // Calculate when auto-checkout should happen
        $checkout_datetime = strtotime($log_date . ' ' . $expected_checkout);
        $auto_checkout_deadline = $checkout_datetime + ($hours_after * 3600);
        
        // If current time is past the deadline, auto-checkout
        if (time() > $auto_checkout_deadline) {
            $auto_checkout_time = $log_date . ' ' . $expected_checkout;
            
            $stmt_update = $pdo->prepare("
                UPDATE attendance_logs 
                SET check_out_time = ?,
                    is_auto_checkout = 1,
                    requires_review = 1,
                    review_reason = 'Auto-checkout: employee did not clock out',
                    updated_at = NOW()
                WHERE id = ?
            ");
            $stmt_update->execute([$auto_checkout_time, $session['id']]);
            
            // Log it
            error_log("Lazy Cron: Auto-checkout {$session['first_name']} {$session['last_name']} for {$log_date}");
        }
    }
}

/**
 * Auto-absent: Mark employees who didn't clock in as absent
 * - Checks approved leave first (skips if on leave)
 * - Calculates daily deduction based on gross salary / working days
 */
function run_lazy_auto_absent($pdo, $company_id, $policy) {
    $today = date('Y-m-d');
    $hours_after = (int) ($policy['auto_absent_hours_after'] ?? 2);
    
    // Get all active employees with their salary info
    $stmt = $pdo->prepare("
        SELECT e.id, e.first_name, e.last_name, e.payroll_id,
               sc.base_gross_amount
        FROM employees e
        LEFT JOIN salary_categories sc ON e.salary_category_id = sc.id
        WHERE e.company_id = ? AND e.employment_status = 'Active'
    ");
    $stmt->execute([$company_id]);
    $employees = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    foreach ($employees as $emp) {
        // Check if they already have a record for today
        $stmt_check = $pdo->prepare("
            SELECT id FROM attendance_logs 
            WHERE employee_id = ? AND date = ?
        ");
        $stmt_check->execute([$emp['id'], $today]);
        
        if ($stmt_check->fetch()) {
            // Already has a record, skip
            continue;
        }
        
        // Check if employee is on approved leave
        if (is_employee_on_leave($pdo, $emp['id'], $today)) {
            // On leave - don't mark absent, skip
            continue;
        }
        
        // Get their schedule for today
        $schedule = resolve_employee_schedule($pdo, $emp['id'], $today, $company_id);
        
        // If not a working day, skip
        if (empty($schedule['is_working_day'])) {
            continue;
        }
        
        // Get expected check-in time
        $expected_checkin = $schedule['check_in'] ?? $schedule['expected_in'] ?? '09:00:00';
        
        // Calculate when auto-absent should trigger
        $checkin_datetime = strtotime($today . ' ' . $expected_checkin);
        $auto_absent_deadline = $checkin_datetime + ($hours_after * 3600);
        
        // If current time is past the deadline, mark absent with deduction
        if (time() > $auto_absent_deadline) {
            // Calculate working days for THIS EMPLOYEE based on their shift/schedule
            $working_days = get_employee_working_days_in_month($pdo, $emp['id'], $company_id, $today);
            
            // Calculate daily deduction
            $gross_salary = (float) ($emp['base_gross_amount'] ?? 0);
            $daily_rate = $working_days > 0 ? round($gross_salary / $working_days, 2) : 0;
            
            $stmt_insert = $pdo->prepare("
                INSERT INTO attendance_logs 
                (company_id, employee_id, date, status, is_auto_absent, 
                 auto_deduction_amount, final_deduction_amount, requires_review, review_reason, created_at)
                VALUES (?, ?, ?, 'Absent', 1, ?, ?, 1, 'Auto-marked absent: no check-in by deadline', NOW())
            ");
            $stmt_insert->execute([$company_id, $emp['id'], $today, $daily_rate, $daily_rate]);
            
            // Log it
            error_log("Lazy Cron: Auto-absent {$emp['first_name']} {$emp['last_name']} for {$today} - Deduction: {$daily_rate}");
        }
    }
}

/**
 * Check if employee is on approved leave for a given date
 */
function is_employee_on_leave($pdo, $employee_id, $date) {
    $stmt = $pdo->prepare("
        SELECT id FROM leave_requests
        WHERE employee_id = ?
          AND status = 'Approved'
          AND start_date <= ?
          AND end_date >= ?
        LIMIT 1
    ");
    $stmt->execute([$employee_id, $date, $date]);
    return $stmt->fetch() !== false;
}

/**
 * Get total working days in a month FOR A SPECIFIC EMPLOYEE
 * Uses resolve_employee_schedule() to check each day based on their shift or daily default
 */
function get_employee_working_days_in_month($pdo, $employee_id, $company_id, $date) {
    $year = date('Y', strtotime($date));
    $month = date('m', strtotime($date));
    $days_in_month = date('t', strtotime($date));
    
    $working_days = 0;
    
    for ($day = 1; $day <= $days_in_month; $day++) {
        $date_str = sprintf('%s-%s-%02d', $year, $month, $day);
        
        // Use the shift schedule resolver to determine if this is a working day for THIS employee
        $schedule = resolve_employee_schedule($pdo, $employee_id, $date_str, $company_id);
        
        // If is_working_day is set and true, count it
        if (!empty($schedule['is_working_day'])) {
            $working_days++;
        }
    }
    
    // Fallback to 22 only if resolver returned 0 for all days (shouldn't happen)
    return $working_days > 0 ? $working_days : 22;
}
