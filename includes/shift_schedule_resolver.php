<?php
/**
 * Shift Schedule Resolver
 * Centralized function to determine if a given date is a working day for an employee
 * and return the expected schedule (check-in/out times, grace period).
 * 
 * Supports: Fixed, Rotational, Weekly, and Monthly shift types.
 */

/**
 * Resolve an employee's schedule for a specific date.
 * 
 * @param PDO $pdo Database connection
 * @param int $employee_id Employee ID
 * @param string $date Date in Y-m-d format
 * @param int|null $company_id Optional company ID for fallback policy lookup
 * @return array [
 *     'is_working_day' => bool,
 *     'check_in' => string|null (TIME format),
 *     'check_out' => string|null (TIME format),
 *     'grace_period' => int,
 *     'shift_name' => string|null,
 *     'shift_type' => string ('daily'|'fixed'|'rotational'|'weekly'|'monthly'),
 *     'attendance_mode' => string ('daily'|'shift')
 * ]
 */
function resolve_employee_schedule($pdo, $employee_id, $date, $company_id = null) {
    // Default response
    $result = [
        'is_working_day' => false,
        'check_in' => null,
        'check_out' => null,
        'grace_period' => 15,
        'shift_name' => null,
        'shift_type' => 'daily',
        'attendance_mode' => 'daily',
        // Backward-compatible aliases for staff.php
        'expected_in' => null,
        'expected_out' => null,
        'grace' => 15,
        'mode' => 'daily'
    ];

    
    $date_obj = new DateTime($date);
    $day_of_week = (int) $date_obj->format('w'); // 0=Sun, 1=Mon, ..., 6=Sat
    
    // 1. Get active assignment for employee
    $stmt = $pdo->prepare("
        SELECT ea.attendance_mode, ea.shift_id, ea.cycle_start_date,
               s.name as shift_name, s.shift_type, s.weeks_on, s.weeks_off
        FROM employee_attendance_assignments ea
        LEFT JOIN attendance_shifts s ON ea.shift_id = s.id
        WHERE ea.employee_id = ? AND ea.is_active = 1
        ORDER BY ea.effective_from DESC
        LIMIT 1
    ");
    $stmt->execute([$employee_id]);
    $assignment = $stmt->fetch(PDO::FETCH_ASSOC);
    
    // 2. Determine mode (default to daily if no assignment)
    $attendance_mode = $assignment['attendance_mode'] ?? 'daily';
    $result['attendance_mode'] = $attendance_mode;
    
    // 3. If DAILY mode, use company attendance_policies
    if ($attendance_mode === 'daily') {
        // Get company_id from employee if not provided
        if (!$company_id) {
            $stmt_co = $pdo->prepare("SELECT company_id FROM employees WHERE id = ?");
            $stmt_co->execute([$employee_id]);
            $company_id = $stmt_co->fetchColumn();
        }
        
        $day_names = ['sun', 'mon', 'tue', 'wed', 'thu', 'fri', 'sat'];
        $day_key = $day_names[$day_of_week];
        
        $stmt_policy = $pdo->prepare("
            SELECT {$day_key}_enabled as is_enabled,
                   {$day_key}_check_in as check_in,
                   {$day_key}_check_out as check_out,
                   {$day_key}_grace as grace_period
            FROM attendance_policies
            WHERE company_id = ?
        ");
        $stmt_policy->execute([$company_id]);
        $policy = $stmt_policy->fetch(PDO::FETCH_ASSOC);
        
        if ($policy) {
            $result['is_working_day'] = (bool) $policy['is_enabled'];
            $result['check_in'] = $policy['check_in'];
            $result['check_out'] = $policy['check_out'];
            $result['grace_period'] = (int) ($policy['grace_period'] ?? 15);
            $result['shift_type'] = 'daily';
        }
        
        // Sync aliases for backward compatibility
        $result['expected_in'] = $result['check_in'] ? substr($result['check_in'], 0, 5) : '08:00';
        $result['expected_out'] = $result['check_out'] ? substr($result['check_out'], 0, 5) : '17:00';
        $result['grace'] = $result['grace_period'];
        $result['mode'] = $result['attendance_mode'];
        
        return $result;
    }
    
    // 4. SHIFT mode - determine based on shift_type
    $shift_id = $assignment['shift_id'];
    $shift_type = $assignment['shift_type'] ?? 'fixed';
    $cycle_start = $assignment['cycle_start_date'];
    
    $result['shift_name'] = $assignment['shift_name'];
    $result['shift_type'] = $shift_type;
    
    switch ($shift_type) {
        case 'fixed':
            // Use 7-day schedule from attendance_shift_schedules
            $stmt_sched = $pdo->prepare("
                SELECT is_working_day, check_in_time, check_out_time, grace_period_minutes
                FROM attendance_shift_schedules
                WHERE shift_id = ? AND day_of_week = ?
            ");
            $stmt_sched->execute([$shift_id, $day_of_week]);
            $sched = $stmt_sched->fetch(PDO::FETCH_ASSOC);
            
            if ($sched) {
                $result['is_working_day'] = (bool) $sched['is_working_day'];
                $result['check_in'] = $sched['check_in_time'];
                $result['check_out'] = $sched['check_out_time'];
                $result['grace_period'] = (int) ($sched['grace_period_minutes'] ?? 15);
            }
            break;
            
        case 'rotational':
            // 24hr on, 24hr off pattern
            // Calculate if date is a work day based on days since cycle_start
            if ($cycle_start) {
                $cycle_start_obj = new DateTime($cycle_start);
                $days_diff = (int) $cycle_start_obj->diff($date_obj)->days;
                
                // Work day if even number of days since start (0, 2, 4, 6...)
                $is_work_day = ($days_diff % 2 === 0);
                $result['is_working_day'] = $is_work_day;
                
                if ($is_work_day) {
                    // Get hours from attendance_shift_daily_hours
                    $stmt_hours = $pdo->prepare("
                        SELECT check_in_time, check_out_time, grace_period_minutes
                        FROM attendance_shift_daily_hours
                        WHERE shift_id = ?
                    ");
                    $stmt_hours->execute([$shift_id]);
                    $hours = $stmt_hours->fetch(PDO::FETCH_ASSOC);
                    
                    if ($hours) {
                        $result['check_in'] = $hours['check_in_time'];
                        $result['check_out'] = $hours['check_out_time'];
                        $result['grace_period'] = (int) ($hours['grace_period_minutes'] ?? 15);
                    } else {
                        // Fallback defaults for rotational shifts
                        $result['check_in'] = '08:00:00';
                        $result['check_out'] = '08:00:00'; // 24hr shift ends next day same time
                        $result['grace_period'] = 15;
                    }
                }
            }
            break;
            
        case 'weekly':
            // Week-in / Week-out pattern
            $weeks_on = (int) ($assignment['weeks_on'] ?? 1);
            $weeks_off = (int) ($assignment['weeks_off'] ?? 1);
            $cycle_length = $weeks_on + $weeks_off; // Total weeks in cycle
            
            if ($cycle_start) {
                $cycle_start_obj = new DateTime($cycle_start);
                $days_diff = (int) $cycle_start_obj->diff($date_obj)->days;
                $weeks_diff = floor($days_diff / 7);
                
                // Position within cycle
                $week_in_cycle = $weeks_diff % $cycle_length;
                
                // Is this a work week?
                $is_work_week = ($week_in_cycle < $weeks_on);
                
                if ($is_work_week) {
                    // On a work week - check if it's a weekday (Mon-Fri default)
                    // Use daily hours from attendance_shift_daily_hours
                    $stmt_hours = $pdo->prepare("
                        SELECT check_in_time, check_out_time, grace_period_minutes
                        FROM attendance_shift_daily_hours
                        WHERE shift_id = ?
                    ");
                    $stmt_hours->execute([$shift_id]);
                    $hours = $stmt_hours->fetch(PDO::FETCH_ASSOC);
                    
                    // Default: work Mon-Fri during work weeks
                    $is_weekday = ($day_of_week >= 1 && $day_of_week <= 5);
                    $result['is_working_day'] = $is_weekday;
                    
                    if ($is_weekday && $hours) {
                        $result['check_in'] = $hours['check_in_time'];
                        $result['check_out'] = $hours['check_out_time'];
                        $result['grace_period'] = (int) ($hours['grace_period_minutes'] ?? 15);
                    } elseif ($is_weekday) {
                        $result['check_in'] = '08:00:00';
                        $result['check_out'] = '17:00:00';
                        $result['grace_period'] = 15;
                    }
                } else {
                    $result['is_working_day'] = false;
                }
            }
            break;
            
        case 'monthly':
            // Month-in / Month-out pattern
            if ($cycle_start) {
                $cycle_start_obj = new DateTime($cycle_start);
                
                // Calculate months difference
                $start_year = (int) $cycle_start_obj->format('Y');
                $start_month = (int) $cycle_start_obj->format('n');
                $current_year = (int) $date_obj->format('Y');
                $current_month = (int) $date_obj->format('n');
                
                $months_diff = (($current_year - $start_year) * 12) + ($current_month - $start_month);
                
                // Work month if even number of months since start (0, 2, 4...)
                $is_work_month = ($months_diff % 2 === 0);
                
                if ($is_work_month) {
                    // On a work month - check if weekday
                    $is_weekday = ($day_of_week >= 1 && $day_of_week <= 5);
                    $result['is_working_day'] = $is_weekday;
                    
                    if ($is_weekday) {
                        // Get hours
                        $stmt_hours = $pdo->prepare("
                            SELECT check_in_time, check_out_time, grace_period_minutes
                            FROM attendance_shift_daily_hours
                            WHERE shift_id = ?
                        ");
                        $stmt_hours->execute([$shift_id]);
                        $hours = $stmt_hours->fetch(PDO::FETCH_ASSOC);
                        
                        if ($hours) {
                            $result['check_in'] = $hours['check_in_time'];
                            $result['check_out'] = $hours['check_out_time'];
                            $result['grace_period'] = (int) ($hours['grace_period_minutes'] ?? 15);
                        } else {
                            $result['check_in'] = '08:00:00';
                            $result['check_out'] = '17:00:00';
                            $result['grace_period'] = 15;
                        }
                    }
                } else {
                    $result['is_working_day'] = false;
                }
            }
            break;
    }
    
    // Sync aliases for backward compatibility
    $result['expected_in'] = $result['check_in'] ? substr($result['check_in'], 0, 5) : '08:00';
    $result['expected_out'] = $result['check_out'] ? substr($result['check_out'], 0, 5) : '17:00';
    $result['grace'] = $result['grace_period'];
    $result['mode'] = $result['attendance_mode'] === 'shift' ? 'shift' : 'daily';
    
    return $result;
}

/**
 * Get the next working day for an employee (useful for Staff Portal display)
 * 
 * @param PDO $pdo
 * @param int $employee_id
 * @param string $from_date Starting date (Y-m-d)
 * @param int $max_days Maximum days to look ahead
 * @return string|null Next working date or null if none found
 */
function get_next_working_day($pdo, $employee_id, $from_date, $max_days = 30) {
    $date = new DateTime($from_date);
    
    for ($i = 1; $i <= $max_days; $i++) {
        $date->modify('+1 day');
        $check_date = $date->format('Y-m-d');
        $schedule = resolve_employee_schedule($pdo, $employee_id, $check_date);
        
        if ($schedule['is_working_day']) {
            return $check_date;
        }
    }
    
    return null;
}

/**
 * Get a human-readable description of a shift pattern
 * 
 * @param string $shift_type
 * @param int $weeks_on (for weekly shifts)
 * @param int $weeks_off (for weekly shifts)
 * @return string
 */
function get_shift_pattern_description($shift_type, $weeks_on = 1, $weeks_off = 1) {
    switch ($shift_type) {
        case 'fixed':
            return 'Fixed weekly schedule';
        case 'rotational':
            return '24hr On / 24hr Off rotation';
        case 'weekly':
            return "{$weeks_on} week(s) on / {$weeks_off} week(s) off";
        case 'monthly':
            return 'Month On / Month Off rotation';
        default:
            return 'Standard daily schedule';
    }
}
?>
