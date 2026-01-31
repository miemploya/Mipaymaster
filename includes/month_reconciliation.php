<?php
/**
 * Month-End Attendance Reconciliation
 * 
 * This script fills in missing attendance records for the entire month
 * before payroll runs. It ensures all working days are accounted for.
 * 
 * Key Features:
 * - Processes entire month calendar (not just logged days)
 * - Respects company creation date (new companies)
 * - Respects employee start date (new hires)
 * - Checks approved leave before marking absent
 * - Uses employee's shift schedule to determine working days
 * - Calculates correct daily deduction per employee
 */

require_once __DIR__ . '/shift_schedule_resolver.php';

/**
 * Reconcile attendance for an entire month before payroll
 * 
 * @param PDO $pdo Database connection
 * @param int $company_id Company ID
 * @param int $month Month (1-12)
 * @param int $year Year (e.g., 2026)
 * @return array Summary of actions taken
 */
function reconcile_month_attendance($pdo, $company_id, $month, $year) {
    $summary = [
        'processed' => 0,
        'absents_created' => 0,
        'skipped_leave' => 0,
        'skipped_not_employed' => 0,
        'skipped_existing' => 0,
        'skipped_non_working' => 0,
        'total_deduction' => 0
    ];
    
    // Get company creation date
    $stmt = $pdo->prepare("SELECT created_at FROM companies WHERE id = ?");
    $stmt->execute([$company_id]);
    $company = $stmt->fetch(PDO::FETCH_ASSOC);
    $company_start = $company ? date('Y-m-d', strtotime($company['created_at'])) : '1970-01-01';
    
    // Get all active employees with their salary info and start dates
    $stmt = $pdo->prepare("
        SELECT e.id, e.first_name, e.last_name, e.payroll_id,
               e.date_of_joining, e.created_at as emp_created_at,
               sc.base_gross_amount
        FROM employees e
        LEFT JOIN salary_categories sc ON e.salary_category_id = sc.id
        WHERE e.company_id = ? 
        AND LOWER(e.employment_status) IN ('active', 'full time', 'probation', 'contract')
    ");
    $stmt->execute([$company_id]);
    $employees = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Build month date range
    $month_start = sprintf('%04d-%02d-01', $year, $month);
    $month_end = date('Y-m-t', strtotime($month_start));
    $days_in_month = (int) date('t', strtotime($month_start));
    $today = date('Y-m-d');
    
    foreach ($employees as $emp) {
        $employee_id = $emp['id'];
        
        // Determine effective start date (later of company start or employee start)
        $emp_start = $emp['date_of_joining'] ?? $emp['emp_created_at'] ?? $company_start;
        $emp_start = date('Y-m-d', strtotime($emp_start));
        $effective_start = max($company_start, $emp_start);
        
        // Calculate working days for this employee in this month
        $working_days_in_month = get_employee_working_days_in_month_from_date(
            $pdo, $employee_id, $company_id, $month_start, $effective_start
        );
        
        // Calculate daily rate
        $gross_salary = (float) ($emp['base_gross_amount'] ?? 0);
        $daily_rate = $working_days_in_month > 0 ? round($gross_salary / $working_days_in_month, 2) : 0;
        
        // Process each day of the month
        for ($day = 1; $day <= $days_in_month; $day++) {
            $date_str = sprintf('%04d-%02d-%02d', $year, $month, $day);
            $summary['processed']++;
            
            // Skip future dates
            if ($date_str > $today) {
                continue;
            }
            
            // Skip if before effective start date
            if ($date_str < $effective_start) {
                $summary['skipped_not_employed']++;
                continue;
            }
            
            // Check if attendance record already exists
            $stmt_check = $pdo->prepare("
                SELECT id FROM attendance_logs 
                WHERE employee_id = ? AND date = ?
            ");
            $stmt_check->execute([$employee_id, $date_str]);
            
            if ($stmt_check->fetch()) {
                $summary['skipped_existing']++;
                continue;
            }
            
            // Check if on approved leave
            if (is_employee_on_leave_for_reconciliation($pdo, $employee_id, $date_str)) {
                $summary['skipped_leave']++;
                continue;
            }
            
            // Check if it's a working day for this employee
            $schedule = resolve_employee_schedule($pdo, $employee_id, $date_str, $company_id);
            
            if (empty($schedule['is_working_day'])) {
                $summary['skipped_non_working']++;
                continue;
            }
            
            // Create absent record with deduction
            $stmt_insert = $pdo->prepare("
                INSERT INTO attendance_logs 
                (company_id, employee_id, date, status, is_auto_absent, 
                 auto_deduction_amount, final_deduction_amount, 
                 requires_review, review_reason, created_at)
                VALUES (?, ?, ?, 'Absent', 1, ?, ?, 0, 
                        'Month-end reconciliation: no attendance record found', NOW())
            ");
            $stmt_insert->execute([
                $company_id, 
                $employee_id, 
                $date_str, 
                $daily_rate, 
                $daily_rate
            ]);
            
            $summary['absents_created']++;
            $summary['total_deduction'] += $daily_rate;
        }
    }
    
    return $summary;
}

/**
 * Check if employee is on approved leave for a given date
 * (Duplicate-safe version for reconciliation)
 */
function is_employee_on_leave_for_reconciliation($pdo, $employee_id, $date) {
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
 * Get working days in month for an employee, starting from effective date
 */
function get_employee_working_days_in_month_from_date($pdo, $employee_id, $company_id, $month_start, $effective_start) {
    $year = date('Y', strtotime($month_start));
    $month = date('m', strtotime($month_start));
    $days_in_month = (int) date('t', strtotime($month_start));
    
    $working_days = 0;
    
    for ($day = 1; $day <= $days_in_month; $day++) {
        $date_str = sprintf('%04d-%02d-%02d', $year, $month, $day);
        
        // Skip if before effective start
        if ($date_str < $effective_start) {
            continue;
        }
        
        // Use the shift schedule resolver
        $schedule = resolve_employee_schedule($pdo, $employee_id, $date_str, $company_id);
        
        if (!empty($schedule['is_working_day'])) {
            $working_days++;
        }
    }
    
    return $working_days > 0 ? $working_days : 22; // Fallback
}

/**
 * Run reconciliation and return summary for display
 */
function run_month_reconciliation_with_report($pdo, $company_id, $month, $year) {
    $start_time = microtime(true);
    
    $summary = reconcile_month_attendance($pdo, $company_id, $month, $year);
    
    $elapsed = round(microtime(true) - $start_time, 2);
    $summary['elapsed_seconds'] = $elapsed;
    
    // Log it
    error_log(sprintf(
        "Month Reconciliation: Company %d, %d/%d - Created %d absents, Total Deduction: %.2f, Time: %.2fs",
        $company_id, $month, $year,
        $summary['absents_created'],
        $summary['total_deduction'],
        $elapsed
    ));
    
    return $summary;
}
