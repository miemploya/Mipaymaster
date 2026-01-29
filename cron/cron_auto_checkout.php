<?php
/**
 * Cron Job: Auto-Checkout Employees
 * 
 * This script runs daily (recommended: at or after the latest shift's check-out time)
 * to automatically close open attendance sessions (employees who forgot to clock out).
 * 
 * Usage: 
 *   php cron/cron_auto_checkout.php
 *   
 * Recommended cron schedule:
 *   0 20 * * 1-5 php /path/to/Mipaymaster/cron/cron_auto_checkout.php >> /path/to/logs/cron_checkout.log 2>&1
 *   (8:00 PM Mon-Fri, adjust based on your company's latest check-out time + buffer)
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
$log[] = "=== Auto-Checkout Cron Job Started: " . date('Y-m-d H:i:s') . " ===";

try {
    // Get all companies with auto_checkout enabled
    $stmt = $pdo->prepare("
        SELECT c.id as company_id, c.name as company_name, ap.*
        FROM companies c
        JOIN attendance_policies ap ON c.id = ap.company_id
        WHERE ap.auto_checkout_enabled = 1
    ");
    $stmt->execute();
    $companies = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    $log[] = "Found " . count($companies) . " companies with auto-checkout enabled.";
    
    foreach ($companies as $company) {
        $company_id = $company['company_id'];
        $log[] = "\n--- Processing: {$company['company_name']} (ID: $company_id) ---";
        
        // Get default checkout time from policy
        $checkout_col = $today_key . '_check_out';
        $default_checkout = $company[$checkout_col] ?? $company['check_out_end'] ?? '17:00:00';
        
        // Find all open sessions (clocked in but not out) for today
        $stmt_open = $pdo->prepare("
            SELECT al.id, al.employee_id, al.check_in_time, al.status,
                   e.first_name, e.last_name, e.payroll_id,
                   ea.attendance_mode, ea.shift_id
            FROM attendance_logs al
            JOIN employees e ON al.employee_id = e.id
            LEFT JOIN employee_attendance_assignments ea ON e.id = ea.employee_id AND ea.is_active = 1
            WHERE al.company_id = ? 
              AND al.date = ? 
              AND al.check_in_time IS NOT NULL
              AND al.check_out_time IS NULL
        ");
        $stmt_open->execute([$company_id, $today]);
        $open_sessions = $stmt_open->fetchAll(PDO::FETCH_ASSOC);
        
        $log[] = "Found " . count($open_sessions) . " open sessions.";
        $checkout_count = 0;
        
        foreach ($open_sessions as $session) {
            $attendance_mode = $session['attendance_mode'] ?? $company['default_mode'] ?? 'daily';
            $shift_id = $session['shift_id'];
            
            // Determine checkout time for this employee
            $checkout_time = $default_checkout;
            
            if ($attendance_mode === 'shift' && $shift_id) {
                // Get shift's checkout time
                $stmt_shift = $pdo->prepare("
                    SELECT check_out_time FROM attendance_shift_schedules
                    WHERE shift_id = ? AND day_of_week = ?
                ");
                $stmt_shift->execute([$shift_id, $day_of_week]);
                $shift_sched = $stmt_shift->fetch();
                if ($shift_sched && $shift_sched['check_out_time']) {
                    $checkout_time = $shift_sched['check_out_time'];
                }
            }
            
            // Only auto-checkout if current time is past checkout time
            $current_time = date('H:i:s');
            if ($current_time < $checkout_time) {
                $log[] = "  Skipping {$session['first_name']} - not past checkout time ($checkout_time)";
                continue;
            }
            
            // Auto-checkout at the expected checkout time
            $auto_checkout_timestamp = $today . ' ' . $checkout_time;
            
            // Update the attendance record
            $stmt_update = $pdo->prepare("
                UPDATE attendance_logs 
                SET check_out_time = ?,
                    is_auto_marked = 1,
                    auto_marked_at = NOW(),
                    auto_mark_reason = CONCAT(COALESCE(auto_mark_reason, ''), ' | Cron: Auto-checkout missed'),
                    requires_review = 1,
                    review_reason = CONCAT(COALESCE(review_reason, ''), 'Auto-checkout: employee did not clock out'),
                    updated_at = NOW()
                WHERE id = ?
            ");
            $stmt_update->execute([$auto_checkout_timestamp, $session['id']]);
            
            $checkout_count++;
            $log[] = "  Auto-Checkout: {$session['first_name']} {$session['last_name']} ({$session['payroll_id']}) at $checkout_time";
        }
        
        $log[] = "Auto-checked out $checkout_count employees for $today.";
        
        // Log audit entry
        if ($checkout_count > 0) {
            log_audit($company_id, null, 'CRON_AUTO_CHECKOUT', 
                "Auto-checkout $checkout_count employees for $today");
        }
    }
    
    // ALSO: Close any stale sessions from previous days
    $log[] = "\n--- Closing Stale Sessions (Previous Days) ---";
    
    $stmt_stale = $pdo->prepare("
        SELECT al.id, al.date, al.employee_id, al.company_id,
               e.first_name, e.last_name, e.payroll_id,
               ap.check_out_end
        FROM attendance_logs al
        JOIN employees e ON al.employee_id = e.id
        JOIN attendance_policies ap ON al.company_id = ap.company_id
        WHERE al.date < ? 
          AND al.check_in_time IS NOT NULL
          AND al.check_out_time IS NULL
    ");
    $stmt_stale->execute([$today]);
    $stale_sessions = $stmt_stale->fetchAll(PDO::FETCH_ASSOC);
    
    $log[] = "Found " . count($stale_sessions) . " stale sessions from previous days.";
    
    foreach ($stale_sessions as $stale) {
        $checkout_time = $stale['check_out_end'] ?? '17:00:00';
        $auto_checkout_timestamp = $stale['date'] . ' ' . $checkout_time;
        
        $stmt_close = $pdo->prepare("
            UPDATE attendance_logs 
            SET check_out_time = ?,
                is_auto_marked = 1,
                auto_marked_at = NOW(),
                auto_mark_reason = CONCAT(COALESCE(auto_mark_reason, ''), ' | Cron: Stale session closed'),
                requires_review = 1,
                review_reason = CONCAT(COALESCE(review_reason, ''), 'Stale session: checkout added by cron'),
                updated_at = NOW()
            WHERE id = ?
        ");
        $stmt_close->execute([$auto_checkout_timestamp, $stale['id']]);
        
        $log[] = "  Closed stale: {$stale['first_name']} {$stale['last_name']} ({$stale['date']})";
    }
    
    $log[] = "\n=== Auto-Checkout Cron Job Completed: " . date('Y-m-d H:i:s') . " ===";
    
} catch (Exception $e) {
    $log[] = "ERROR: " . $e->getMessage();
}

// Output log
echo implode("\n", $log) . "\n";
