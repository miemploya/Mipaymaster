<?php
// Debug script to check attendance records data flow
require_once __DIR__ . '/includes/functions.php';

header('Content-Type: text/html; charset=utf-8');
?>
<!DOCTYPE html>
<html>
<head><title>Attendance Display Debug</title>
<style>body{font-family:monospace;padding:20px;background:#1a1a2e;color:#eee}.ok{color:#4ade80}.warn{color:#fbbf24}.err{color:#f87171}.box{background:#16213e;padding:15px;margin:10px 0;border-radius:8px}h2{color:#818cf8}table{width:100%;border-collapse:collapse}th,td{padding:8px;text-align:left;border:1px solid #334}th{background:#1e3a5f}pre{background:#000;padding:10px;overflow:auto;border-radius:4px}</style>
</head>
<body>
<h1>🔍 Attendance Display Debug</h1>

<?php
if (!isset($_SESSION['user_id'])) {
    echo "<p class='err'>ERROR: Not logged in. Please login first.</p>";
    exit;
}

$company_id = $_SESSION['company_id'];
$filter_date = $_GET['date'] ?? date('Y-m-d');

echo "<div class='box'>";
echo "<h2>1. Query Parameters</h2>";
echo "<p>Company ID: <b>$company_id</b></p>";
echo "<p>Filter Date: <b>$filter_date</b></p>";
echo "</div>";

// Test the exact query from attendance.php
echo "<div class='box'>";
echo "<h2>2. Raw Query Result</h2>";

try {
    $stmt = $pdo->prepare("
        SELECT al.*, e.first_name, e.last_name, e.employee_id as emp_code, d.name as dept_name 
        FROM attendance_logs al 
        JOIN employees e ON al.employee_id = e.id 
        LEFT JOIN departments d ON e.department_id = d.id 
        WHERE al.company_id = ? AND al.date = ?
    ");
    $stmt->execute([$company_id, $filter_date]);
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo "<p>Rows fetched: <b>" . count($rows) . "</b></p>";
    
    if (count($rows) > 0) {
        echo "<p class='ok'>✅ Data found!</p>";
        echo "<pre>" . print_r($rows, true) . "</pre>";
    } else {
        echo "<p class='warn'>⚠️ No rows returned from query</p>";
        
        // Check if employee_id link is correct
        echo "<br><h3>Checking employee_id linkage:</h3>";
        $stmt2 = $pdo->prepare("SELECT id, employee_id, first_name, last_name FROM employees WHERE company_id = ? LIMIT 5");
        $stmt2->execute([$company_id]);
        $emps = $stmt2->fetchAll(PDO::FETCH_ASSOC);
        echo "<p>Employees (showing PK id and employee_id):</p>";
        echo "<pre>" . print_r($emps, true) . "</pre>";
        
        $stmt3 = $pdo->prepare("SELECT id, employee_id, date, check_in_time FROM attendance_logs WHERE company_id = ? AND date = ?");
        $stmt3->execute([$company_id, $filter_date]);
        $logs = $stmt3->fetchAll(PDO::FETCH_ASSOC);
        echo "<p>Attendance logs (showing employee_id FK):</p>";
        echo "<pre>" . print_r($logs, true) . "</pre>";
    }

} catch (Exception $e) {
    echo "<p class='err'>Query Error: " . $e->getMessage() . "</p>";
}
echo "</div>";

// Check what JSON would be passed
echo "<div class='box'>";
echo "<h2>3. JSON Output (what Alpine.js receives)</h2>";

$records = [];
foreach($rows as $r) {
    $in = $r['check_in_time'] ? date('h:i A', strtotime($r['check_in_time'])) : '-';
    $out = $r['check_out_time'] ? date('h:i A', strtotime($r['check_out_time'])) : '-';
    
    $hours = '0h';
    if($r['check_in_time'] && $r['check_out_time']) {
        $diff = strtotime($r['check_out_time']) - strtotime($r['check_in_time']);
        $hours = round($diff / 3600, 1) . 'h';
    }

    $impact = 'None';
    if ($r['final_deduction_amount'] > 0) $impact = 'Deduction';
    
    $records[] = [
        'date' => $r['date'],
        'id' => $r['emp_code'],
        'name' => $r['first_name'] . ' ' . $r['last_name'],
        'dept' => $r['dept_name'] ?? '-',
        'in' => $in,
        'out' => $out,
        'hours' => $hours,
        'status' => ucfirst($r['status']),
        'overtime' => '0h',
        'impact' => $impact
    ];
}

$records_json = json_encode($records);
echo "<p>JSON length: " . strlen($records_json) . " bytes</p>";
echo "<pre>dailyRecords: " . $records_json . "</pre>";
echo "</div>";
?>
</body>
</html>
