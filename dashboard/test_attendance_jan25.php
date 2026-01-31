<?php
/**
 * Test Attendance Scenario: January 25, 2026
 * - All employees came late
 * - Samson had auto check-in and auto check-out
 * 
 * This script tests the attendance system's handling of:
 * 1. Late attendance detection
 * 2. Auto check-in/out markers
 * 3. Schedule resolution for different shift types
 */

require_once '../includes/functions.php';
require_once '../includes/shift_schedule_resolver.php';

header('Content-Type: text/html; charset=utf-8');

// Test date
$test_date = '2026-01-25';
$day_name = date('l', strtotime($test_date));

echo "<!DOCTYPE html><html><head><title>Attendance Test - Jan 25, 2026</title>";
echo "<style>
    body { font-family: 'Segoe UI', sans-serif; padding: 20px; background: #f8fafc; }
    .container { max-width: 1200px; margin: 0 auto; }
    h1 { color: #1e293b; }
    h2 { color: #475569; margin-top: 30px; border-bottom: 2px solid #e2e8f0; padding-bottom: 10px; }
    table { width: 100%; border-collapse: collapse; margin-top: 15px; background: white; box-shadow: 0 1px 3px rgba(0,0,0,0.1); }
    th, td { padding: 12px; text-align: left; border: 1px solid #e2e8f0; }
    th { background: #f1f5f9; font-weight: 600; color: #334155; }
    .late { background: #fef3c7; color: #92400e; }
    .present { background: #dcfce7; color: #166534; }
    .absent { background: #fee2e2; color: #991b1b; }
    .auto { background: #dbeafe; color: #1e40af; font-style: italic; }
    .badge { display: inline-block; padding: 4px 8px; border-radius: 4px; font-size: 12px; font-weight: 600; }
    .badge-shift { background: #8b5cf6; color: white; }
    .badge-daily { background: #3b82f6; color: white; }
    .summary-box { background: white; padding: 20px; border-radius: 8px; margin: 20px 0; box-shadow: 0 1px 3px rgba(0,0,0,0.1); }
    .stat { display: inline-block; margin-right: 30px; }
    .stat-value { font-size: 24px; font-weight: 700; color: #1e293b; }
    .stat-label { font-size: 12px; color: #64748b; }
</style></head><body>";
echo "<div class='container'>";
echo "<h1>🧪 Attendance Test: January 25, 2026 ({$day_name})</h1>";

// Get company ID from session or default
$company_id = $_SESSION['company_id'] ?? 1;

echo "<p>Testing for Company ID: <strong>{$company_id}</strong></p>";

// 1. Get all active employees
$stmt = $pdo->prepare("SELECT id, payroll_id, first_name, last_name, department, job_title FROM employees WHERE company_id = ? AND employment_status = 'Active' ORDER BY first_name");
$stmt->execute([$company_id]);
$employees = $stmt->fetchAll(PDO::FETCH_ASSOC);

echo "<h2>📋 Employee Schedule Resolution for {$test_date}</h2>";
echo "<p>Testing how each employee's schedule is resolved using the shift_schedule_resolver...</p>";
echo "<table>";
echo "<tr><th>Employee</th><th>Department</th><th>Schedule Mode</th><th>Shift/Policy</th><th>Is Working Day?</th><th>Expected In</th><th>Expected Out</th><th>Grace (mins)</th></tr>";

$schedule_results = [];
foreach ($employees as $emp) {
    $schedule = resolve_employee_schedule($pdo, $emp['id'], $test_date, $company_id);
    $schedule_results[$emp['id']] = $schedule;
    
    $mode_badge = $schedule['mode'] === 'shift' 
        ? "<span class='badge badge-shift'>SHIFT</span>" 
        : "<span class='badge badge-daily'>DAILY</span>";
    
    $shift_name = $schedule['shift_name'] ?? 'Default Daily Policy';
    $is_working = $schedule['is_working_day'] ? '✅ Yes' : '❌ No';
    
    echo "<tr>";
    echo "<td><strong>{$emp['first_name']} {$emp['last_name']}</strong><br><small>{$emp['payroll_id']}</small></td>";
    echo "<td>{$emp['department']}</td>";
    echo "<td>{$mode_badge}</td>";
    echo "<td>{$shift_name}</td>";
    echo "<td>{$is_working}</td>";
    echo "<td>{$schedule['expected_in']}</td>";
    echo "<td>{$schedule['expected_out']}</td>";
    echo "<td>{$schedule['grace']}</td>";
    echo "</tr>";
}
echo "</table>";

// 2. Get attendance logs for Jan 25
echo "<h2>📊 Attendance Logs for {$test_date}</h2>";
$stmt = $pdo->prepare("
    SELECT al.*, e.first_name, e.last_name, e.payroll_id 
    FROM attendance_logs al
    JOIN employees e ON al.employee_id = e.id
    WHERE al.date = ? AND e.company_id = ?
    ORDER BY e.first_name, e.last_name
");
$stmt->execute([$test_date, $company_id]);
$logs = $stmt->fetchAll(PDO::FETCH_ASSOC);

if (empty($logs)) {
    echo "<p style='color: #94a3b8;'>No attendance logs found for this date. Creating test data...</p>";
    
    // Create test attendance data
    echo "<h3>🔧 Simulating Late Arrivals for All Employees</h3>";
    echo "<table>";
    echo "<tr><th>Employee</th><th>Expected In</th><th>Simulated Check-In</th><th>Late By</th><th>Status</th></tr>";
    
    foreach ($employees as $emp) {
        $schedule = $schedule_results[$emp['id']];
        
        if (!$schedule['is_working_day']) {
            echo "<tr class='absent'><td>{$emp['first_name']} {$emp['last_name']}</td><td colspan='4'>Not a working day</td></tr>";
            continue;
        }
        
        // Simulate late arrival: 30 minutes after expected (past grace)
        $expected_in = $schedule['expected_in'];
        $grace = $schedule['grace'];
        $late_minutes = 30; // 30 minutes late
        
        $expected_datetime = new DateTime($test_date . ' ' . $expected_in);
        $checkin_datetime = clone $expected_datetime;
        $checkin_datetime->modify("+{$late_minutes} minutes");
        
        $simulated_checkin = $checkin_datetime->format('H:i:s');
        $late_threshold = clone $expected_datetime;
        $late_threshold->modify("+{$grace} minutes");
        
        $is_late = $checkin_datetime > $late_threshold;
        $status = $is_late ? 'Late' : 'Present';
        $late_by = $is_late ? ($late_minutes - $grace) . ' mins past grace' : 'Within grace';
        
        $row_class = $is_late ? 'late' : 'present';
        
        // Special handling for Samson (auto check-in/out)
        $is_samson = stripos($emp['first_name'], 'samson') !== false;
        if ($is_samson) {
            $row_class = 'auto';
            $status = 'Late (AUTO)';
        }
        
        echo "<tr class='{$row_class}'>";
        echo "<td><strong>{$emp['first_name']} {$emp['last_name']}</strong></td>";
        echo "<td>{$expected_in}</td>";
        echo "<td>{$simulated_checkin}" . ($is_samson ? " <em>(auto)</em>" : "") . "</td>";
        echo "<td>{$late_by}</td>";
        echo "<td><strong>{$status}</strong></td>";
        echo "</tr>";
    }
    echo "</table>";
} else {
    // Display existing logs
    echo "<table>";
    echo "<tr><th>Employee</th><th>Check-In</th><th>Check-Out</th><th>Status</th><th>Auto Flags</th><th>Deduction</th></tr>";
    
    $late_count = 0;
    $present_count = 0;
    $absent_count = 0;
    $auto_checkin_count = 0;
    $auto_checkout_count = 0;
    
    foreach ($logs as $log) {
        $status = strtolower($log['status']);
        $row_class = $status === 'late' ? 'late' : ($status === 'present' ? 'present' : 'absent');
        
        // Check for auto flags
        $auto_flags = [];
        if (!empty($log['is_auto_checkin'])) {
            $auto_flags[] = '🤖 Auto Check-In';
            $auto_checkin_count++;
        }
        if (!empty($log['is_auto_checkout'])) {
            $auto_flags[] = '🤖 Auto Check-Out';
            $auto_checkout_count++;
        }
        
        if ($status === 'late') $late_count++;
        elseif ($status === 'present') $present_count++;
        else $absent_count++;
        
        $checkin = $log['check_in_time'] ? date('H:i', strtotime($log['check_in_time'])) : '-';
        $checkout = $log['check_out_time'] ? date('H:i', strtotime($log['check_out_time'])) : '-';
        $deduction = $log['auto_deduction_amount'] > 0 ? '₦' . number_format($log['auto_deduction_amount'], 2) : '-';
        
        echo "<tr class='{$row_class}'>";
        echo "<td><strong>{$log['first_name']} {$log['last_name']}</strong><br><small>{$log['payroll_id']}</small></td>";
        echo "<td>{$checkin}</td>";
        echo "<td>{$checkout}</td>";
        echo "<td><strong>" . ucfirst($status) . "</strong></td>";
        echo "<td>" . (empty($auto_flags) ? '-' : implode('<br>', $auto_flags)) . "</td>";
        echo "<td>{$deduction}</td>";
        echo "</tr>";
    }
    echo "</table>";
    
    // Summary
    echo "<div class='summary-box'>";
    echo "<h3>📈 Summary for {$test_date}</h3>";
    echo "<div class='stat'><div class='stat-value'>" . count($logs) . "</div><div class='stat-label'>Total Records</div></div>";
    echo "<div class='stat'><div class='stat-value' style='color: #f59e0b;'>{$late_count}</div><div class='stat-label'>Late</div></div>";
    echo "<div class='stat'><div class='stat-value' style='color: #22c55e;'>{$present_count}</div><div class='stat-label'>Present</div></div>";
    echo "<div class='stat'><div class='stat-value' style='color: #ef4444;'>{$absent_count}</div><div class='stat-label'>Absent</div></div>";
    echo "<div class='stat'><div class='stat-value' style='color: #3b82f6;'>{$auto_checkin_count}</div><div class='stat-label'>Auto Check-Ins</div></div>";
    echo "<div class='stat'><div class='stat-value' style='color: #8b5cf6;'>{$auto_checkout_count}</div><div class='stat-label'>Auto Check-Outs</div></div>";
    echo "</div>";
}

// 3. Look specifically for Samson
echo "<h2>🔍 Samson's Records (Auto Check-In/Out)</h2>";
$stmt = $pdo->prepare("
    SELECT e.id, e.first_name, e.last_name, e.payroll_id,
           al.check_in_time, al.check_out_time, al.status, 
           al.is_auto_checkin, al.is_auto_checkout, al.is_auto_absent, al.auto_deduction_amount
    FROM employees e
    LEFT JOIN attendance_logs al ON e.id = al.employee_id AND al.date = ?
    WHERE e.company_id = ? AND LOWER(e.first_name) LIKE '%samson%'
    LIMIT 1
");
$stmt->execute([$test_date, $company_id]);
$samson = $stmt->fetch(PDO::FETCH_ASSOC);

if ($samson) {
    echo "<table>";
    echo "<tr><th>Field</th><th>Value</th></tr>";
    echo "<tr><td>Employee</td><td><strong>{$samson['first_name']} {$samson['last_name']}</strong> ({$samson['payroll_id']})</td></tr>";
    
    // Get Samson's schedule
    $samson_schedule = resolve_employee_schedule($pdo, $samson['id'], $test_date, $company_id);
    echo "<tr><td>Schedule Mode</td><td>" . ($samson_schedule['mode'] === 'shift' ? 'Shift: ' . $samson_schedule['shift_name'] : 'Daily Default') . "</td></tr>";
    echo "<tr><td>Expected In</td><td>{$samson_schedule['expected_in']}</td></tr>";
    echo "<tr><td>Expected Out</td><td>{$samson_schedule['expected_out']}</td></tr>";
    echo "<tr><td>Grace Period</td><td>{$samson_schedule['grace']} mins</td></tr>";
    
    if ($samson['check_in_time']) {
        echo "<tr><td>Actual Check-In</td><td>" . date('H:i:s', strtotime($samson['check_in_time'])) . "</td></tr>";
        echo "<tr><td>Auto Check-In?</td><td>" . ($samson['is_auto_checkin'] ? '✅ YES (system auto-marked)' : '❌ No (manual)') . "</td></tr>";
    } else {
        echo "<tr><td>Check-In</td><td>No record</td></tr>";
    }
    
    if ($samson['check_out_time']) {
        echo "<tr><td>Actual Check-Out</td><td>" . date('H:i:s', strtotime($samson['check_out_time'])) . "</td></tr>";
        echo "<tr><td>Auto Check-Out?</td><td>" . ($samson['is_auto_checkout'] ? '✅ YES (system auto-marked)' : '❌ No (manual)') . "</td></tr>";
    } else {
        echo "<tr><td>Check-Out</td><td>No record</td></tr>";
    }
    
    echo "<tr><td>Status</td><td><strong>" . ucfirst($samson['status'] ?? 'No record') . "</strong></td></tr>";
    echo "<tr><td>Deduction</td><td>" . ($samson['auto_deduction_amount'] > 0 ? '₦' . number_format($samson['auto_deduction_amount'], 2) : 'None') . "</td></tr>";
    echo "</table>";
} else {
    echo "<p style='color: #ef4444;'>⚠️ No employee named 'Samson' found in this company.</p>";
}

echo "<hr style='margin: 30px 0; border-color: #e2e8f0;'>";
echo "<p style='color: #64748b; font-size: 12px;'>Test completed at: " . date('Y-m-d H:i:s') . "</p>";
echo "</div></body></html>";
?>
