<?php
require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/includes/functions.php';

$company_id = 2; // Openclax
$employee_id = 5; // Esosa
$month = 1;
$year = 2026;

echo "<h1>Overtime Calculation Debug</h1>";
echo "<p>Testing for Employee ID: $employee_id, Company: $company_id, Period: $month/$year</p>";

// 1. Get Employee Basic Salary Info
// From payroll_engine:
// Fetch employee salary category and base gross
$stmt = $pdo->prepare("
    SELECT e.*, sc.name as category_name, sc.base_gross_amount 
    FROM employees e 
    LEFT JOIN salary_categories sc ON e.salary_category_id = sc.id
    WHERE e.id = ? AND e.company_id = ?
");
$stmt->execute([$employee_id, $company_id]);
$emp = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$emp) die("Employee not found");

$base_gross = floatval($emp['base_gross_amount']);
echo "Base Gross: " . number_format($base_gross, 2) . "<br>";

// 2. Increments
$applied_increments = 0;
// (Skipping increment logic for simplicity, assuming 0 or assuming engine handles it)
$adjusted_gross = $base_gross + $applied_increments;
echo "Adjusted Gross: " . number_format($adjusted_gross, 2) . "<br>";

// 3. Global Settings
$stmt_beh = $pdo->prepare("SELECT overtime_enabled, daily_work_hours, monthly_work_days, overtime_rate FROM payroll_behaviours WHERE company_id = ?");
$stmt_beh->execute([$company_id]);
$settings = $stmt_beh->fetch(PDO::FETCH_ASSOC);
echo "<h3>Global Settings</h3>";
print_r($settings);
echo "<br>";

// 4. Overtime Record
$stmt_ot = $pdo->prepare("SELECT overtime_hours, notes FROM payroll_overtime WHERE company_id = ? AND employee_id = ? AND payroll_month = ? AND payroll_year = ?");
$stmt_ot->execute([$company_id, $employee_id, $month, $year]);
$ot_record = $stmt_ot->fetch(PDO::FETCH_ASSOC);
echo "<h3>Overtime Record</h3>";
print_r($ot_record);
echo "<br>";

if ($ot_record) {
    echo "Overtime Hours: " . $ot_record['overtime_hours'] . "<br>";
    
    // 5. Shift Calculation Logic (Replica of what I put in payroll_engine)
    $stmt_shift = $pdo->prepare("
        SELECT eaa.attendance_mode, eaa.shift_id, 
               ass.check_in_time, ass.check_out_time
        FROM employee_attendance_assignments eaa
        LEFT JOIN attendance_shift_schedules ass ON eaa.shift_id = ass.shift_id 
            AND ass.is_working_day = 1
        WHERE eaa.employee_id = ? AND eaa.is_active = 1
        LIMIT 1
    ");
    $stmt_shift->execute([$employee_id]);
    $shift_info = $stmt_shift->fetch(PDO::FETCH_ASSOC);
    
    echo "<h3>Shift Info</h3>";
    print_r($shift_info);
    echo "<br>";
    
    $daily_hours = 0;
    
    if ($shift_info && $shift_info['attendance_mode'] === 'shift' 
        && $shift_info['check_in_time'] && $shift_info['check_out_time']) {
        
        $check_in = strtotime($shift_info['check_in_time']);
        $check_out = strtotime($shift_info['check_out_time']);
        if ($check_out < $check_in) {
            $check_out += 86400; // Add 24 hours
        }
        $daily_hours = ($check_out - $check_in) / 3600;
        echo "Calculated Shift Daily Hours: $daily_hours<br>";
    } else {
        $daily_hours = floatval($settings['daily_work_hours'] ?? 8.0);
        echo "Using Global Daily Hours: $daily_hours<br>";
    }
    
    $monthly_days = intval($settings['monthly_work_days'] ?? 22);
    $ot_rate_multiplier = floatval($settings['overtime_rate'] ?? 1.5);
    
    echo "Monthly Days: $monthly_days<br>";
    echo "Multiplier: $ot_rate_multiplier<br>";
    
    $hourly_rate = ($daily_hours > 0 && $monthly_days > 0) 
        ? $adjusted_gross / ($daily_hours * $monthly_days) 
        : 0;
        
    echo "Hourly Rate: " . number_format($hourly_rate, 2) . "<br>";
    
    $overtime_pay = floatval($ot_record['overtime_hours']) * $hourly_rate * $ot_rate_multiplier;
    echo "<strong>Calculated Overtime Pay: " . number_format($overtime_pay, 2) . "</strong><br>";
}

echo "<hr>";
echo "<h2>Database Records Dump</h2>";

echo "<h3>All Overtime Records</h3>";
$stmt = $pdo->query("SELECT * FROM payroll_overtime");
$all_ot = $stmt->fetchAll(PDO::FETCH_ASSOC);
echo "<pre>" . print_r($all_ot, true) . "</pre>";

echo "<h3>Latest Snapshot for Employee $employee_id</h3>";
// Find latest payroll entry for this employee
$stmt = $pdo->prepare("SELECT id FROM payroll_entries WHERE employee_id = ? ORDER BY id DESC LIMIT 1");
$stmt->execute([$employee_id]);
$entry_id = $stmt->fetchColumn();

if ($entry_id) {
    $stmt = $pdo->prepare("SELECT snapshot_json FROM payroll_snapshots WHERE payroll_entry_id = ?");
    $stmt->execute([$entry_id]);
    $json = $stmt->fetchColumn();
    
    if ($json) {
        $data = json_decode($json, true);
        echo "<h4>Snapshot 'overtime' Key:</h4>";
        if (isset($data['overtime'])) {
            echo "<pre>" . print_r($data['overtime'], true) . "</pre>";
        } else {
            echo "<span style='color:red'>'overtime' key MISSING in snapshot!</span><br>";
            echo "Top level keys: " . implode(', ', array_keys($data));
        }
    } else {
        echo "No snapshot data found for entry $entry_id";
    }
} else {
    echo "No payroll entry found for employee $employee_id";
}
?>
