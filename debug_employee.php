<?php
// Comprehensive Employee Environment Debug Script
require_once __DIR__ . '/includes/functions.php';

header('Content-Type: text/html; charset=utf-8');
?>
<!DOCTYPE html>
<html>
<head><title>Employee Debug</title>
<style>body{font-family:monospace;padding:20px;background:#1a1a2e;color:#eee}.ok{color:#4ade80}.warn{color:#fbbf24}.err{color:#f87171}.box{background:#16213e;padding:15px;margin:10px 0;border-radius:8px}h2{color:#818cf8}</style>
</head>
<body>
<h1>🔍 Employee Portal Debug</h1>

<?php
if (!isset($_SESSION['user_id'])) {
    echo "<p class='err'>ERROR: Not logged in. Please login first.</p>";
    exit;
}

$user_id = $_SESSION['user_id'];
$company_id = $_SESSION['company_id'];
$role = $_SESSION['role'];

echo "<div class='box'>";
echo "<h2>1. Session Data</h2>";
echo "<p>User ID: <b>$user_id</b></p>";
echo "<p>Company ID: <b>$company_id</b></p>";
echo "<p>Role: <b>$role</b></p>";
echo "<p class='" . ($role === 'employee' ? 'ok' : 'warn') . "'>";
echo $role === 'employee' ? "✅ Correct role for employee portal" : "⚠️ You are logged in as '$role', not 'employee'";
echo "</p></div>";

// Check if user has a linked employee record
echo "<div class='box'>";
echo "<h2>2. Employee Record Link</h2>";
$stmt = $pdo->prepare("SELECT * FROM employees WHERE user_id = ? AND company_id = ?");
$stmt->execute([$user_id, $company_id]);
$employee = $stmt->fetch(PDO::FETCH_ASSOC);

if ($employee) {
    echo "<p class='ok'>✅ Employee record found</p>";
    echo "<p>Employee ID: <b>{$employee['id']}</b></p>";
    echo "<p>Name: <b>{$employee['first_name']} {$employee['last_name']}</b></p>";
    echo "<p>Payroll ID: <b>{$employee['payroll_id']}</b></p>";
    echo "<p>Employment Status: <b>'{$employee['employment_status']}'</b></p>";
    
    // Check if status would fail the active check
    $status_lower = strtolower($employee['employment_status']);
    if ($status_lower === 'active') {
        echo "<p class='ok'>✅ Status is 'active' - employee is eligible for features</p>";
    } else {
        echo "<p class='warn'>⚠️ Status is '{$employee['employment_status']}' - NOT 'active'. Some features may not work.</p>";
    }
} else {
    echo "<p class='err'>❌ No employee record linked to this user!</p>";
    echo "<p class='warn'>The employees table must have user_id = $user_id for employee features to work.</p>";
    
    // Check if there are any employees in the company
    $stmt = $pdo->prepare("SELECT id, first_name, last_name, user_id, employment_status FROM employees WHERE company_id = ? LIMIT 10");
    $stmt->execute([$company_id]);
    $all_employees = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo "<p>Employees in company:</p><pre>";
    foreach ($all_employees as $emp) {
        echo "ID: {$emp['id']} | {$emp['first_name']} {$emp['last_name']} | user_id: " . ($emp['user_id'] ?? 'NULL') . " | status: {$emp['employment_status']}\n";
    }
    echo "</pre>";
}
echo "</div>";

// Check users table
echo "<div class='box'>";
echo "<h2>3. Users Table</h2>";
$stmt = $pdo->prepare("SELECT id, first_name, last_name, email, username, role FROM users WHERE id = ?");
$stmt->execute([$user_id]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);

if ($user) {
    echo "<p>User Email: <b>{$user['email']}</b></p>";
    echo "<p>Username: <b>" . ($user['username'] ?? 'NULL') . "</b></p>";
    echo "<p>User Role: <b>{$user['role']}</b></p>";
}
echo "</div>";

// Check attendance settings
echo "<div class='box'>";
echo "<h2>4. Attendance Config</h2>";
$stmt = $pdo->prepare("SELECT attendance_method FROM companies WHERE id = ?");
$stmt->execute([$company_id]);
$method = $stmt->fetchColumn();
echo "<p>Attendance Method: <b>'" . ($method ?? 'NULL') . "'</b></p>";
echo "<p class='" . ($method === 'self' ? 'ok' : 'err') . "'>";
echo $method === 'self' ? "✅ Self Check-In enabled" : "❌ Self Check-In NOT enabled. Set to 'self' in Company Settings.";
echo "</p>";
echo "</div>";

// Check leave_requests table exists
echo "<div class='box'>";
echo "<h2>5. Leave Requests Table</h2>";
try {
    $cols = $pdo->query("SHOW COLUMNS FROM leave_requests")->fetchAll(PDO::FETCH_ASSOC);
    echo "<p class='ok'>✅ leave_requests table exists with " . count($cols) . " columns</p>";
} catch (Exception $e) {
    echo "<p class='err'>❌ leave_requests table missing: " . $e->getMessage() . "</p>";
}
echo "</div>";

// Check loans table
echo "<div class='box'>";
echo "<h2>6. Loans Table</h2>";
try {
    $cols = $pdo->query("SHOW COLUMNS FROM loans")->fetchAll(PDO::FETCH_ASSOC);
    echo "<p class='ok'>✅ loans table exists with " . count($cols) . " columns</p>";
} catch (Exception $e) {
    echo "<p class='err'>❌ loans table missing: " . $e->getMessage() . "</p>";
}
echo "</div>";

// Check if employee_status check is failing in loan_operations.php
if (isset($employee)) {
    echo "<div class='box'>";
    echo "<h2>7. Loan Eligibility Check</h2>";
    $stmt = $pdo->prepare("SELECT id, first_name, last_name FROM employees WHERE id = ? AND company_id = ? AND employment_status = 'active'");
    $stmt->execute([$employee['id'], $company_id]);
    $loan_eligible = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($loan_eligible) {
        echo "<p class='ok'>✅ Employee passes loan eligibility check (status = 'active')</p>";
    } else {
        echo "<p class='err'>❌ Employee FAILS loan eligibility check!</p>";
        echo "<p class='warn'>The loan_operations.php checks for employment_status = 'active' (case-sensitive)</p>";
        echo "<p class='warn'>Current status: '{$employee['employment_status']}'</p>";
    }
    echo "</div>";
}

echo "<div class='box' style='background:#1e3a5f'>";
echo "<h2>📋 Summary</h2>";
echo "<p>If you see red ❌ items above, those need to be fixed.</p>";
echo "<p>Common issues:</p>";
echo "<ul>";
echo "<li>Employee not linked: Update employees table SET user_id = $user_id WHERE id = [employee_id]</li>";
echo "<li>Wrong status: Update employees table SET employment_status = 'active'</li>";
echo "<li>Self check-in disabled: Update companies table SET attendance_method = 'self'</li>";
echo "</ul>";
echo "</div>";
?>
</body>
</html>
