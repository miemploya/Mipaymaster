<?php
// Debug script to check attendance data in employer view
require_once __DIR__ . '/includes/functions.php';

header('Content-Type: text/html; charset=utf-8');
?>
<!DOCTYPE html>
<html>
<head><title>Attendance Debug</title>
<style>body{font-family:monospace;padding:20px;background:#1a1a2e;color:#eee}.ok{color:#4ade80}.warn{color:#fbbf24}.err{color:#f87171}.box{background:#16213e;padding:15px;margin:10px 0;border-radius:8px}h2{color:#818cf8}table{width:100%;border-collapse:collapse}th,td{padding:8px;text-align:left;border:1px solid #334}th{background:#1e3a5f}</style>
</head>
<body>
<h1>🔍 Attendance Data Debug</h1>

<?php
if (!isset($_SESSION['user_id'])) {
    echo "<p class='err'>ERROR: Not logged in. Please login first.</p>";
    exit;
}

$company_id = $_SESSION['company_id'];
$today = date('Y-m-d');

echo "<div class='box'>";
echo "<h2>1. Session Info</h2>";
echo "<p>Company ID: <b>$company_id</b></p>";
echo "<p>Today's Date: <b>$today</b></p>";
echo "</div>";

// Check all attendance_logs for today
echo "<div class='box'>";
echo "<h2>2. Today's Attendance Logs (Raw)</h2>";
$stmt = $pdo->prepare("SELECT * FROM attendance_logs WHERE company_id = ? AND date = ?");
$stmt->execute([$company_id, $today]);
$logs = $stmt->fetchAll(PDO::FETCH_ASSOC);

if (empty($logs)) {
    echo "<p class='warn'>⚠️ No attendance logs found for today ($today)</p>";
} else {
    echo "<p class='ok'>✅ Found " . count($logs) . " log(s) for today</p>";
    echo "<table><tr><th>ID</th><th>Employee ID</th><th>Date</th><th>Check In</th><th>Check Out</th><th>Status</th><th>Method</th></tr>";
    foreach ($logs as $log) {
        echo "<tr>";
        echo "<td>{$log['id']}</td>";
        echo "<td>{$log['employee_id']}</td>";
        echo "<td>{$log['date']}</td>";
        echo "<td>" . ($log['check_in_time'] ?? 'NULL') . "</td>";
        echo "<td>" . ($log['check_out_time'] ?? 'NULL') . "</td>";
        echo "<td>{$log['status']}</td>";
        echo "<td>" . ($log['method_used'] ?? 'NULL') . "</td>";
        echo "</tr>";
    }
    echo "</table>";
}
echo "</div>";

// Check all attendance_logs (last 7 days)
echo "<div class='box'>";
echo "<h2>3. Recent Attendance Logs (Last 7 Days)</h2>";
$stmt = $pdo->prepare("SELECT al.*, e.first_name, e.last_name FROM attendance_logs al JOIN employees e ON al.employee_id = e.id WHERE al.company_id = ? AND al.date >= DATE_SUB(CURDATE(), INTERVAL 7 DAY) ORDER BY al.date DESC, al.check_in_time DESC");
$stmt->execute([$company_id]);
$recent_logs = $stmt->fetchAll(PDO::FETCH_ASSOC);

if (empty($recent_logs)) {
    echo "<p class='warn'>⚠️ No attendance logs found in the last 7 days</p>";
} else {
    echo "<p class='ok'>✅ Found " . count($recent_logs) . " log(s) in the last 7 days</p>";
    echo "<table><tr><th>Date</th><th>Employee</th><th>Check In</th><th>Check Out</th><th>Status</th></tr>";
    foreach ($recent_logs as $log) {
        echo "<tr>";
        echo "<td>{$log['date']}</td>";
        echo "<td>{$log['first_name']} {$log['last_name']}</td>";
        echo "<td>" . ($log['check_in_time'] ?? '-') . "</td>";
        echo "<td>" . ($log['check_out_time'] ?? '-') . "</td>";
        echo "<td>{$log['status']}</td>";
        echo "</tr>";
    }
    echo "</table>";
}
echo "</div>";

// Check attendance_policies
echo "<div class='box'>";
echo "<h2>4. Attendance Policy Settings</h2>";
$stmt = $pdo->prepare("SELECT * FROM attendance_policies WHERE company_id = ?");
$stmt->execute([$company_id]);
$policy = $stmt->fetch(PDO::FETCH_ASSOC);

if ($policy) {
    echo "<table>";
    echo "<tr><th>Setting</th><th>Value</th></tr>";
    echo "<tr><td>Check-In Start</td><td>{$policy['check_in_start']}</td></tr>";
    echo "<tr><td>Check-In End (Late after)</td><td>{$policy['check_in_end']}</td></tr>";
    echo "<tr><td>Check-Out Start</td><td>{$policy['check_out_start']}</td></tr>";
    echo "<tr><td>Check-Out End</td><td>{$policy['check_out_end']}</td></tr>";
    echo "<tr><td>Grace Period (minutes)</td><td>{$policy['grace_period_minutes']}</td></tr>";
    echo "<tr><td>Lateness Deduction Enabled</td><td>" . ($policy['lateness_deduction_enabled'] ? 'Yes' : 'No') . "</td></tr>";
    echo "</table>";
    
    // Check if lateness deduction fields exist
    $cols = $pdo->query("SHOW COLUMNS FROM attendance_policies")->fetchAll(PDO::FETCH_COLUMN);
    $has_deduction_amount = in_array('lateness_deduction_amount', $cols);
    $has_deduction_type = in_array('lateness_deduction_type', $cols);
    
    echo "<br><p>Lateness Deduction Fields:</p>";
    echo "<p class='" . ($has_deduction_amount ? 'ok' : 'warn') . "'>";
    echo $has_deduction_amount ? "✅ lateness_deduction_amount exists" : "⚠️ lateness_deduction_amount column MISSING";
    echo "</p>";
    echo "<p class='" . ($has_deduction_type ? 'ok' : 'warn') . "'>";
    echo $has_deduction_type ? "✅ lateness_deduction_type exists" : "⚠️ lateness_deduction_type column MISSING";
    echo "</p>";
} else {
    echo "<p class='err'>❌ No attendance policy found for this company</p>";
}
echo "</div>";

echo "<div class='box' style='background:#1e3a5f'>";
echo "<h2>📋 Next Steps</h2>";
echo "<p>Based on the above:</p>";
echo "<ul>";
echo "<li>If no logs found today, the employee check-in might be saving with wrong date or not saving at all</li>";
echo "<li>If logs exist but don't show in employer view, check attendance.php query</li>";
echo "<li>If lateness deduction fields are missing, we need to add them to attendance_policies table</li>";
echo "</ul>";
echo "</div>";
?>
</body>
</html>
