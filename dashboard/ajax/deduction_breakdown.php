<?php
/**
 * AJAX: Deduction Breakdown
 * Fetches employee attendance deduction details for the payroll deductions view
 */
require_once '../../includes/functions.php';
require_login();

header('Content-Type: application/json');

$company_id = $_SESSION['company_id'];
$input = json_decode(file_get_contents('php://input'), true);

$employee_id = (int)($input['employee_id'] ?? 0);
$month = (int)($input['month'] ?? date('n'));
$year = (int)($input['year'] ?? date('Y'));

if (!$employee_id) {
    echo json_encode(['status' => false, 'message' => 'Employee ID required']);
    exit;
}

try {
    // Get employee info
    $stmt_emp = $pdo->prepare("
        SELECT e.id, e.first_name, e.last_name, e.payroll_id,
               sc.name as category_name, sc.base_gross_amount
        FROM employees e
        LEFT JOIN salary_categories sc ON e.salary_category_id = sc.id
        WHERE e.id = ? AND e.company_id = ?
    ");
    $stmt_emp->execute([$employee_id, $company_id]);
    $employee = $stmt_emp->fetch(PDO::FETCH_ASSOC);
    
    if (!$employee) {
        echo json_encode(['status' => false, 'message' => 'Employee not found']);
        exit;
    }
    
    // Get attendance policy for grace period
    $stmt_policy = $pdo->prepare("SELECT grace_period_minutes FROM attendance_policies WHERE company_id = ?");
    $stmt_policy->execute([$company_id]);
    $policy = $stmt_policy->fetch(PDO::FETCH_ASSOC);
    $grace_period = (int)($policy['grace_period_minutes'] ?? 15);
    
    // Calculate working days for this employee this month
    require_once '../../includes/shift_schedule_resolver.php';
    $month_start = sprintf('%04d-%02d-01', $year, $month);
    $days_in_month = (int) date('t', strtotime($month_start));
    
    $working_days = 0;
    for ($d = 1; $d <= $days_in_month; $d++) {
        $date_str = sprintf('%04d-%02d-%02d', $year, $month, $d);
        $schedule = resolve_employee_schedule($pdo, $employee_id, $date_str, $company_id);
        if (!empty($schedule['is_working_day'])) {
            $working_days++;
        }
    }
    
    $gross_salary = (float)($employee['base_gross_amount'] ?? 0);
    $daily_rate = $working_days > 0 ? round($gross_salary / $working_days, 2) : 0;
    
    // Get attendance records with deductions
    $stmt_att = $pdo->prepare("
        SELECT al.*, 
               TIME_FORMAT(al.check_in_time, '%h:%i %p') as check_in_formatted,
               TIME_FORMAT(al.check_out_time, '%h:%i %p') as check_out_formatted
        FROM attendance_logs al
        WHERE al.employee_id = ? 
        AND MONTH(al.date) = ? 
        AND YEAR(al.date) = ?
        AND (al.final_deduction_amount > 0 OR al.auto_deduction_amount > 0 
             OR LOWER(al.status) IN ('late', 'absent'))
        ORDER BY al.date ASC
    ");
    $stmt_att->execute([$employee_id, $month, $year]);
    $raw_records = $stmt_att->fetchAll(PDO::FETCH_ASSOC);
    
    // Process records and calculate summary
    $records = [];
    $summary = [
        'total_late_minutes' => 0,
        'total_absent_days' => 0,
        'grace_days' => 0,
        'total_deduction' => 0
    ];
    
    foreach ($raw_records as $rec) {
        $date = new DateTime($rec['date']);
        $status = strtolower($rec['status']);
        $deduction = (float)($rec['final_deduction_amount'] ?? $rec['auto_deduction_amount'] ?? 0);
        $late_mins = (int)($rec['late_minutes'] ?? 0);
        $is_reversed = !empty($rec['deduction_reversed']);
        $is_grace = ($status === 'late' && $late_mins <= $grace_period && $deduction == 0);
        
        // Update summary
        $summary['total_deduction'] += $deduction;
        if ($status === 'absent') {
            $summary['total_absent_days']++;
        } elseif ($status === 'late') {
            $summary['total_late_minutes'] += $late_mins;
            if ($is_grace) {
                $summary['grace_days']++;
            }
        }
        
        $records[] = [
            'date' => $rec['date'],
            'date_formatted' => $date->format('M d, Y'),
            'day_name' => $date->format('l'),
            'status' => $status,
            'expected_time' => '08:00 AM',
            'actual_time' => $rec['check_in_formatted'] ?? null,
            'late_minutes' => $late_mins,
            'deduction' => $deduction,
            'is_reversed' => $is_reversed,
            'is_grace' => $is_grace
        ];
    }
    
    echo json_encode([
        'status' => true,
        'employee' => [
            'id' => $employee['id'],
            'name' => $employee['first_name'] . ' ' . $employee['last_name'],
            'payroll_id' => $employee['payroll_id'],
            'category' => $employee['category_name'],
            'gross_salary' => $gross_salary,
            'working_days' => $working_days,
            'daily_rate' => $daily_rate,
            'grace_period' => $grace_period
        ],
        'records' => $records,
        'summary' => $summary
    ]);
    
} catch (Exception $e) {
    echo json_encode(['status' => false, 'message' => 'Error: ' . $e->getMessage()]);
}
