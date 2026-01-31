<?php
/**
 * Debug Script: Overtime Flow Verification
 * Tests the complete overtime save -> payroll calculation flow
 */
require_once __DIR__ . '/config/db.php';

// Get first company for testing
$stmt = $pdo->query("SELECT id, name FROM companies LIMIT 1");
$company = $stmt->fetch(PDO::FETCH_ASSOC);
$company_id = $company['id'];

echo "<h1>Overtime Debug Report</h1>";
echo "<p>Company ID: <strong>$company_id</strong></p>";
echo "<hr>";

// 1. Check payroll_behaviours settings
echo "<h2>1. Payroll Behaviours (Overtime Config)</h2>";
$stmt = $pdo->prepare("SELECT overtime_enabled, daily_work_hours, monthly_work_days, overtime_rate FROM payroll_behaviours WHERE company_id = ?");
$stmt->execute([$company_id]);
$behaviours = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$behaviours) {
    echo "<p style='color:red;'><strong>ERROR: No payroll_behaviours record found!</strong></p>";
    echo "<p>This means the Behaviour tab was never saved. Go to Company Setup > Behaviour and save.</p>";
} else {
    echo "<table border='1' cellpadding='5'>";
    echo "<tr><th>Setting</th><th>Value</th><th>Status</th></tr>";
    echo "<tr><td>overtime_enabled</td><td>{$behaviours['overtime_enabled']}</td><td>" . ($behaviours['overtime_enabled'] ? '✅ Enabled' : '❌ Disabled') . "</td></tr>";
    echo "<tr><td>daily_work_hours</td><td>{$behaviours['daily_work_hours']}</td><td>OK</td></tr>";
    echo "<tr><td>monthly_work_days</td><td>{$behaviours['monthly_work_days']}</td><td>OK</td></tr>";
    echo "<tr><td>overtime_rate</td><td>{$behaviours['overtime_rate']}x</td><td>OK</td></tr>";
    echo "</table>";
    
    if (!$behaviours['overtime_enabled']) {
        echo "<p style='color:orange;'><strong>WARNING: Overtime is DISABLED!</strong> Enable it in Company Setup > Behaviour.</p>";
    }
}

echo "<hr>";

// 2. Check payroll_overtime records
echo "<h2>2. Saved Overtime Records (payroll_overtime)</h2>";
$month = date('n');
$year = date('Y');
echo "<p>Checking for current period: <strong>{$month}/{$year}</strong></p>";

$stmt = $pdo->prepare("
    SELECT po.*, e.first_name, e.last_name 
    FROM payroll_overtime po 
    JOIN employees e ON po.employee_id = e.id 
    WHERE po.company_id = ? AND po.payroll_month = ? AND po.payroll_year = ?
");
$stmt->execute([$company_id, $month, $year]);
$overtime_records = $stmt->fetchAll(PDO::FETCH_ASSOC);

if (empty($overtime_records)) {
    echo "<p style='color:orange;'>No overtime records found for this period.</p>";
    echo "<p>To test: Add overtime via the Adjustment modal in Payroll Sheet.</p>";
} else {
    echo "<table border='1' cellpadding='5'>";
    echo "<tr><th>Employee</th><th>Hours</th><th>Notes</th><th>Created</th></tr>";
    foreach ($overtime_records as $ot) {
        echo "<tr>";
        echo "<td>{$ot['first_name']} {$ot['last_name']}</td>";
        echo "<td>{$ot['overtime_hours']}</td>";
        echo "<td>{$ot['notes']}</td>";
        echo "<td>{$ot['created_at']}</td>";
        echo "</tr>";
    }
    echo "</table>";
    echo "<p style='color:green;'>✅ Overtime records exist. Re-run payroll to calculate.</p>";
}

echo "<hr>";

// 3. Check if statutory_settings still has overtime columns (legacy)
echo "<h2>3. Legacy Check: statutory_settings</h2>";
$stmt = $pdo->prepare("DESCRIBE statutory_settings");
$stmt->execute();
$columns = $stmt->fetchAll(PDO::FETCH_COLUMN);
$has_overtime = in_array('overtime_enabled', $columns);

if ($has_overtime) {
    $stmt = $pdo->prepare("SELECT overtime_enabled, daily_work_hours, monthly_work_days, overtime_rate FROM statutory_settings WHERE company_id = ?");
    $stmt->execute([$company_id]);
    $legacy = $stmt->fetch(PDO::FETCH_ASSOC);
    echo "<p>statutory_settings still has overtime columns (legacy). Values:</p>";
    echo "<pre>" . print_r($legacy, true) . "</pre>";
    echo "<p style='color:blue;'>Note: These are ignored now. Only payroll_behaviours is used.</p>";
} else {
    echo "<p>statutory_settings does not have overtime columns. ✅</p>";
}

echo "<hr>";

// 4. Simulate what payroll engine would see
echo "<h2>4. Payroll Engine Simulation</h2>";
echo "<p>What the engine fetches for overtime calculation:</p>";

// Re-fetch behaviours
$stmt = $pdo->prepare("SELECT overtime_enabled, daily_work_hours, monthly_work_days, overtime_rate FROM payroll_behaviours WHERE company_id = ?");
$stmt->execute([$company_id]);
$engine_behaviours = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$engine_behaviours || !$engine_behaviours['overtime_enabled']) {
    echo "<p style='color:red;'>⛔ Engine will SKIP overtime calculation because overtime_enabled = 0 or no record.</p>";
} else {
    echo "<p style='color:green;'>✅ Engine will PROCESS overtime.</p>";
    
    // Get first employee with overtime
    if (!empty($overtime_records)) {
        $test = $overtime_records[0];
        $stmt = $pdo->prepare("SELECT base_gross_amount FROM salary_categories sc JOIN employees e ON e.salary_category_id = sc.id WHERE e.id = ?");
        $stmt->execute([$test['employee_id']]);
        $gross = $stmt->fetchColumn() ?: 0;
        
        $hourly = $gross / ($engine_behaviours['daily_work_hours'] * $engine_behaviours['monthly_work_days']);
        $ot_pay = $test['overtime_hours'] * $hourly * $engine_behaviours['overtime_rate'];
        
        echo "<h3>Sample Calculation for {$test['first_name']} {$test['last_name']}:</h3>";
        echo "<ul>";
        echo "<li>Gross: ₦" . number_format($gross, 2) . "</li>";
        echo "<li>Daily Hours: {$engine_behaviours['daily_work_hours']}</li>";
        echo "<li>Monthly Days: {$engine_behaviours['monthly_work_days']}</li>";
        echo "<li>Hourly Rate: ₦" . number_format($hourly, 2) . "</li>";
        echo "<li>OT Rate Multiplier: {$engine_behaviours['overtime_rate']}x</li>";
        echo "<li>Hours Worked: {$test['overtime_hours']}</li>";
        echo "<li><strong>Expected OT Pay: ₦" . number_format($ot_pay, 2) . "</strong></li>";
        echo "</ul>";
    }
}

echo "<hr>";
echo "<h2>Summary</h2>";
$issues = [];
if (!$behaviours) $issues[] = "No payroll_behaviours record exists";
if ($behaviours && !$behaviours['overtime_enabled']) $issues[] = "Overtime is disabled in settings";
if (empty($overtime_records)) $issues[] = "No overtime hours saved for current period";

if (empty($issues)) {
    echo "<p style='color:green; font-size:18px;'>✅ All checks passed! Re-run payroll to see overtime.</p>";
} else {
    echo "<p style='color:red; font-size:18px;'>❌ Issues Found:</p>";
    echo "<ol>";
    foreach ($issues as $issue) {
        echo "<li>$issue</li>";
    }
    echo "</ol>";
}
?>
