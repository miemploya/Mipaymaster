<?php
require_once __DIR__ . '/config/db.php';

echo "=== Payroll Behaviours Check ===\n";

$stmt = $pdo->query("SELECT * FROM payroll_behaviours WHERE company_id = 1");
$row = $stmt->fetch(PDO::FETCH_ASSOC);

if ($row) {
    echo "overtime_enabled: " . $row['overtime_enabled'] . "\n";
    echo "daily_work_hours: " . $row['daily_work_hours'] . "\n";
    echo "monthly_work_days: " . $row['monthly_work_days'] . "\n";
    echo "overtime_rate: " . $row['overtime_rate'] . "\n";
} else {
    echo "NO RECORD FOUND FOR company_id=1\n";
}

echo "\n=== Overtime Records ===\n";
$month = date('n');
$year = date('Y');
$stmt = $pdo->query("SELECT employee_id, overtime_hours FROM payroll_overtime WHERE company_id = 1 AND payroll_month = $month AND payroll_year = $year");
$ot_rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
print_r($ot_rows);
?>
