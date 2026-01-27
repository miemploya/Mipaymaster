<?php
// Debug script to check AJAX response for payroll sheet
require_once __DIR__ . '/includes/functions.php';

header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['error' => 'Not logged in']);
    exit;
}

$company_id = $_SESSION['company_id'];
$month = 1;
$year = 2026;

// Simulate the exact same logic as ajax/payroll_operations.php fetch_sheet

// Fetch Run ID
$stmt = $pdo->prepare("SELECT * FROM payroll_runs WHERE company_id = ? AND period_month = ? AND period_year = ?");
$stmt->execute([$company_id, $month, $year]);
$run = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$run) {
    echo json_encode(['status' => true, 'run' => null, 'entries' => [], 'totals' => [], 'anomalies' => []]);
    exit;
}

// Fetch Entries with Breakdown
$stmt = $pdo->prepare("
    SELECT pe.*, e.first_name, e.last_name, e.payroll_id, e.account_number, ps.snapshot_json
    FROM payroll_entries pe
    JOIN employees e ON pe.employee_id = e.id
    LEFT JOIN payroll_snapshots ps ON ps.payroll_entry_id = pe.id
    WHERE pe.payroll_run_id = ?
    ORDER BY e.first_name ASC
");
$stmt->execute([$run['id']]);
$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

$entries = [];

foreach ($rows as $row) {
    // Parse snapshot for detailed breakdown
    $snap = json_decode($row['snapshot_json'], true);
    
    // Safety check - ensure $snap is a valid array
    if (!is_array($snap)) {
        $snap = [
            'breakdown' => [],
            'statutory' => [],
            'loans' => []
        ];
    }
    
    // Calculate total loan deduction
    $loan_total = 0;
    if (isset($snap['loans']) && is_array($snap['loans'])) {
        foreach ($snap['loans'] as $loan_item) {
            if (isset($loan_item['amount'])) {
                $loan_total += floatval($loan_item['amount']);
            }
        }
    }
    
    // Construct breakdown object for frontend
    $breakdown = [
        'basic' => floatval($snap['breakdown']['Basic Salary'] ?? 0),
        'housing' => floatval($snap['breakdown']['Housing Allowance'] ?? 0),
        'transport' => floatval($snap['breakdown']['Transport Allowance'] ?? 0),
        'paye' => floatval($snap['statutory']['paye'] ?? 0),
        'pension' => floatval($snap['statutory']['pension_employee'] ?? 0),
        'nhis' => floatval($snap['statutory']['nhis'] ?? 0),
        'loan' => $loan_total
    ];

    $entries[] = [
        'id' => $row['employee_id'],
        'first_name' => $row['first_name'],
        'last_name' => $row['last_name'],
        'payroll_id' => $row['payroll_id'],
        'gross_salary' => floatval($row['gross_salary']),
        'net_pay' => floatval($row['net_pay']),
        'breakdown' => $breakdown,
        '_debug_snap_loans' => $snap['loans'] ?? 'NOT SET'  // Debug field
    ];
}

echo json_encode([
    'status' => true,
    'run' => $run,
    'entries' => $entries,
    'debug_message' => 'This is directly from debug script'
], JSON_PRETTY_PRINT);
?>
