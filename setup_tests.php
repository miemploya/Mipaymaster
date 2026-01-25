<?php
// Test full payroll flow simulating AJAX calls
session_start();
$_SESSION['user_id'] = 1; // Mock Admin
$_SESSION['company_id'] = 2; // Mock Company

$url = 'http://localhost/Mipaymaster/ajax/payroll_operations.php';

// Helper to simulate request
function mock_request($action, $data = []) {
    global $url;
    $data['action'] = $action;
    // We can't actually curl localhost easily inside the environment if dns/networking is restricted, 
    // but we can include the file and mock the input.
    // actually, let's just include the logic directly or use CLI curl if available.
    // better: create a wrapper script that requires the ajax file but sets up the input.
}

echo "=== MOCKING FRONTEND AJAX CALLS ===\n\n";

// 1. INITIATE
echo "1. Initiating Payroll (March 2026)...\n";
$_POST = []; 
$input_json = json_encode(['action' => 'initiate', 'month' => 3, 'year' => 2026]);

// We will use a separate small script for each call to avoid header already sent issues
if (!is_dir('tests')) mkdir('tests');

$script_init = <<<'PHP'
<?php
session_start();
$_SESSION['user_id'] = 1;
$_SESSION['company_id'] = 2;
require_once 'includes/functions.php';
// Mock php://input
function file_get_contents_mock() { return json_encode(['action' => 'initiate', 'month' => 3, 'year' => 2026]); }
// We can't override built-in easily.
// Let's just instantiate the engine directly? No, we want to test the AJAX handler.
// We'll use CURL via command line.
?>
PHP;

// Let's use simple CLI curl if possible, or just build a PHP tester that requires the file
// but the ajax file reads php://input. 
// We can mock it by setting a global variable or modifying the ajax file to read from a param if testing?
// No, let's just use the `functions.php` and `payroll_engine.php` directly to verify logic, 
// AND a special test wrapper for the ajax endpoint.

// Wrapper for initiate
$wrapper = "<?php
session_start();
\$_SESSION['user_id'] = 1;
\$_SESSION['company_id'] = 2;
\$_SERVER['REQUEST_METHOD'] = 'POST';
// Inject input
\$json = json_encode(['action' => 'initiate', 'month' => 3, 'year' => 2026]);
// Hack: we can't easily inject into php://input for specific include.
// Alternatives: 
// 1. Modify ajax/payroll_operations.php to check a global \$TEST_INPUT if set.
// 2. Use a real HTTP request if possible.
// 3. Just reproduce the logic in the test script.

// Let's try reproduction to verify logic first.
require_once 'includes/functions.php';
require_once 'includes/payroll_engine.php';
echo json_encode(run_monthly_payroll(2, 3, 2026, 1));
?>";
file_put_contents('tests/test_initiate.php', $wrapper);

// Wrapper for fetch
$wrapper_fetch = "<?php
session_start();
\$_SESSION['user_id'] = 1;
\$_SESSION['company_id'] = 2;
require_once 'includes/functions.php';
require_once 'includes/payroll_engine.php';

// Simulate fetch logic
\$month = 3; \$year = 2026;
\$stmt = \$pdo->prepare('SELECT * FROM payroll_runs WHERE company_id = 2 AND period_month = 3 AND period_year = 2026');
\$stmt->execute();
\$run = \$stmt->fetch(PDO::FETCH_ASSOC);
if(!\$run) { echo 'Run not found'; exit; }
echo 'Run Found: ' . \$run['id'] . '\n';

\$stmt = \$pdo->prepare('SELECT count(*) FROM payroll_entries WHERE payroll_run_id = ?');
\$stmt->execute([\$run['id']]);
echo 'Entries: ' . \$stmt->fetchColumn() . '\n';
?>";
file_put_contents('tests/test_fetch.php', $wrapper_fetch);

?>
