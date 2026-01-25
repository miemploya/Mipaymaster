<?php
// Debug script for Dropdown Data Issue
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Mock session if needed or load regular includes
if (file_exists('includes/functions.php')) {
    require_once 'includes/functions.php';
} else {
    // try to manually connect if functions missing (unlikely)
    die("includes/functions.php not found");
}

// Ensure session is started (functions.php should do it)
if (session_status() === PHP_SESSION_NONE) session_start();

echo "<pre>";
echo "<h3>1. Session Info</h3>";
echo "User ID: " . ($_SESSION['user_id'] ?? 'NULL') . "\n";
echo "Company ID: " . ($_SESSION['company_id'] ?? 'NULL') . "\n";

$company_id = $_SESSION['company_id'] ?? 0;

echo "<h3>2. Database Check</h3>";
if (!isset($pdo)) {
    echo "PDO Object missing!\n";
    exit;
}

// Check Raw Counts
$total_emps = $pdo->query("SELECT count(*) FROM employees")->fetchColumn();
echo "Total Employees in Table: $total_emps\n";

// Check Specific Query used in increments.php
echo "<h3>3. Query Simulation</h3>";
$query = "SELECT id, first_name, last_name, payroll_id FROM employees WHERE company_id = ? AND employment_status = 'active' ORDER BY first_name ASC";
echo "Query: $query\n";
echo "Param: $company_id\n";

$stmt = $pdo->prepare($query);
$stmt->execute([$company_id]);
$employees = $stmt->fetchAll(PDO::FETCH_ASSOC);

echo "Result Count: " . count($employees) . "\n";

if (count($employees) === 0) {
    echo "\n[DIAGNOSIS] The list is empty. Possible reasons:\n";
    echo "- No employees with company_id = $company_id\n";
    echo "- No employees with employment_status = 'active' (case sensitive check)\n";
    
    // Deep dive
    echo "\n--- Deep Dive ---\n";
    $stmt = $pdo->query("SELECT id, company_id, employment_status FROM employees LIMIT 10");
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
    foreach ($rows as $r) {
        echo "ID: {$r['id']} | CompanyID: {$r['company_id']} | Status: {$r['employment_status']}\n";
    }
} else {
    echo "\n[SUCCESS] PHP is finding employees. The issue is likely in the JS/Alpine rendering.\n";
    echo "Sample JSON for JS:\n";
    $js_array = [];
    foreach ($employees as $emp) {
        $js_array[] = [
            'id' => $emp['id'],
            'name' => $emp['first_name'] . ' ' . $emp['last_name'] . ' (' . ($emp['payroll_id'] ?? 'N/A') . ')'
        ];
    }
    echo json_encode($js_array, JSON_PRETTY_PRINT);
}

echo "</pre>";
?>
