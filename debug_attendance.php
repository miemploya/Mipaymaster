<?php
// Debug script to check attendance_method setting
require_once __DIR__ . '/includes/functions.php';

header('Content-Type: text/plain');

if (!isset($_SESSION['user_id'])) {
    echo "ERROR: Not logged in. Please login first.\n";
    exit;
}

$company_id = $_SESSION['company_id'];

echo "=== ATTENDANCE CHECK-IN DEBUG ===\n\n";

// Check company attendance_method
$stmt = $pdo->prepare("SELECT id, name, attendance_method FROM companies WHERE id = ?");
$stmt->execute([$company_id]);
$company = $stmt->fetch(PDO::FETCH_ASSOC);

echo "Company: {$company['name']} (ID: {$company['id']})\n";
echo "Attendance Method: '" . ($company['attendance_method'] ?? 'NULL/NOT SET') . "'\n\n";

// Check what columns exist in companies table
echo "=== COMPANIES TABLE COLUMNS ===\n";
$cols = $pdo->query("SHOW COLUMNS FROM companies")->fetchAll(PDO::FETCH_ASSOC);
foreach ($cols as $col) {
    echo "  - {$col['Field']} ({$col['Type']}) Default: '{$col['Default']}'\n";
}

// Check attendance_policies
echo "\n=== ATTENDANCE POLICIES ===\n";
$stmt = $pdo->prepare("SELECT * FROM attendance_policies WHERE company_id = ?");
$stmt->execute([$company_id]);
$policy = $stmt->fetch(PDO::FETCH_ASSOC);
if ($policy) {
    print_r($policy);
} else {
    echo "No attendance policy found for this company\n";
}

// Check for attendance_logs table structure
echo "\n=== ATTENDANCE_LOGS TABLE COLUMNS ===\n";
$cols = $pdo->query("SHOW COLUMNS FROM attendance_logs")->fetchAll(PDO::FETCH_ASSOC);
foreach ($cols as $col) {
    echo "  - {$col['Field']} ({$col['Type']})\n";
}

echo "\n=== DEBUG COMPLETE ===\n";
?>
