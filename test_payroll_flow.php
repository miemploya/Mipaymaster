<?php
// Emulate AJAX calls
require_once 'config/db.php';
require_once 'includes/functions.php';

// Setup Session mock
$_SESSION['company_id'] = 1;
$_SESSION['user_id'] = 1;

// Define helper to call operations
function call_ops($data) {
    global $pdo, $company_id; 
    // We can't include operations direct as it expects POST body.
    // We will just replicate the logic or use CURL?
    // Using CURL is safer to test the actual file.
    
    $url = "http://localhost/Mipaymaster/dashboard/ajax/payroll_operations.php";
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
    // Pass session cookie? Hard in simple script.
    // Instead, I will modify payroll_operations.php slightly to accept GET for testing? No, insecure.
    // I'll use the direct include method by capturing output buffer.
    
    return "CURL_SKIP";
}

// DIRECT INCLUDE METHOD (Simpler)
// We need to trick the script into thinking it's a POST request
$_SERVER['REQUEST_METHOD'] = 'POST';
$_SESSION['company_id'] = 1;
$_SESSION['user_id'] = 1;

// 1. INITIATE
echo "<h3>1. Testing Initiate Action</h3>";
$input_json = json_encode(['action' => 'initiate', 'month' => '05', 'year' => '2026']);
// We need to inject this into php://input reading? Impossible.
// We'll modify the operations script to read from $_POST too if json fails?
// Or just write a mock wrapper.

// Let's just create a test function that calls the SAME logic as operations.php
require_once 'includes/payroll_engine.php';

try {
    // A. Clean up old runs
    $pdo->exec("DELETE FROM payroll_entries WHERE payroll_run_id IN (SELECT id FROM payroll_runs WHERE period_month='05' AND period_year='2026')");
    $pdo->exec("DELETE FROM payroll_runs WHERE period_month='05' AND period_year='2026'");
    
    // B. Run
    echo "Running Payroll for May 2026...<br>";
    $res = run_monthly_payroll(1, '05', '2026', 1);
    print_r($res);
    
    if (!$res['status']) {
        echo "<br>Failed: " . $res['message'];
        exit;
    }
    
    $run_id = $res['run_id'];
    echo "<br>Run ID: $run_id Created.<br>";
    
    // C. Fetch Entries (Simulate fetch_sheet logic)
    echo "<h3>2. Verify Entries</h3>";
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM payroll_entries WHERE payroll_run_id = ?");
    $stmt->execute([$run_id]);
    $count = $stmt->fetchColumn();
    echo "Entries count: $count<br>";
    
    if ($count == 0) {
        echo "<b style='color:red'>FAILURE: No entries generated! Check active employees.</b>";
    } else {
        echo "<b style='color:green'>SUCCESS: Entries generated logic works.</b>";
    }
    
} catch (Exception $e) {
    echo "Error: " . $e->getMessage();
}

?>
