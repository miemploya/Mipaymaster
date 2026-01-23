&lt;?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

$output = "";
$output .= "=== MiPayMaster Login Debug Report ===\n\n";

// 1. Test Database Connection
$output .= "1. Database Connection Test\n";
try {
    $pdo = new PDO("mysql:host=127.0.0.1;dbname=mipaymaster", "root", "");
    $pdo-&gt;setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $output .= "   ✓ Database connection successful\n\n";
} catch (PDOException $e) {
    $output .= "   ✗ Database connection FAILED: " . $e-&gt;getMessage() . "\n\n";
    file_put_contents('debug_result.txt', $output);
    die();
}

// 2. Simple Query Test (SELECT 1)
$output .= "2. Simple Query Test (SELECT 1)\n";
try {
    $result = $pdo-&gt;query("SELECT 1 as test")-&gt;fetch();
    $output .= "   ✓ Simple query successful. Result: " . $result['test'] . "\n\n";
} catch (PDOException $e) {
    $output .= "   ✗ Simple query FAILED: " . $e-&gt;getMessage() . "\n\n";
}

// 3. Check Read-Only Mode
$output .= "3. Read-Only Mode Check\n";
try {
    $stmt = $pdo-&gt;query("SHOW VARIABLES LIKE 'read_only'");
    $readOnly = $stmt-&gt;fetch();
    $output .= "   read_only = " . $readOnly['Value'] . ($readOnly['Value'] == 'OFF' ? ' ✓' : ' ⚠ WARNING') . "\n";
    
    $stmt = $pdo-&gt;query("SHOW VARIABLES LIKE 'innodb_force_recovery'");
    $recovery = $stmt-&gt;fetch();
    $output .= "   innodb_force_recovery = " . $recovery['Value'] . ($recovery['Value'] == '0' ? ' ✓' : ' ⚠ CAUTION: Recovery mode active') . "\n\n";
} catch (PDOException $e) {
    $output .= "   ✗ Read-only check FAILED: " . $e-&gt;getMessage() . "\n\n";
}

// 4. Test INSERT capability
$output .= "4. Database Write Test\n";
try {
    $pdo-&gt;exec("CREATE TABLE IF NOT EXISTS debug_test (id INT AUTO_INCREMENT PRIMARY KEY, test_value VARCHAR(50), created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP)");
    $output .= "   ✓ Table creation successful\n";
    
    $pdo-&gt;exec("INSERT INTO debug_test (test_value) VALUES ('test_" . date('His') . "')");
    $output .= "   ✓ INSERT operation successful\n";
    
    $count = $pdo-&gt;query("SELECT COUNT(*) as cnt FROM debug_test")-&gt;fetch()['cnt'];
    $output .= "   ✓ SELECT count successful. Records: $count\n";
    
    $pdo-&gt;exec("DROP TABLE debug_test");
    $output .= "   ✓ Table deletion successful\n";
    $output .= "   ** Database is fully writable! **\n\n";
} catch (PDOException $e) {
    $output .= "   ✗ Write test FAILED: " . $e-&gt;getMessage() . "\n";
    $output .= "   ⚠ Database may be in READ-ONLY mode!\n\n";
}

// 5. Check Users Table
$output .= "5. Users Table Verification\n";
try {
    $stmt = $pdo-&gt;query("DESCRIBE users");
    $output .= "   ✓ Users table exists. Key columns:\n";
    $cols = [];
    while ($row = $stmt-&gt;fetch()) {
        if (in_array($row['Field'], ['id', 'email', 'password_hash', 'role', 'company_id', 'first_name'])) {
            $cols[] = $row['Field'];
        }
    }
    $output .= "     " . implode(', ', $cols) . "\n\n";
} catch (PDOException $e) {
    $output .= "   ✗ Users table check FAILED: " . $e-&gt;getMessage() . "\n\n";
}

// 6. List All Users
$output .= "6. User Accounts in Database\n";
try {
    $stmt = $pdo-&gt;query("SELECT id, email, role, company_id, first_name, last_name, password_hash FROM users ORDER BY id");
    $users = $stmt-&gt;fetchAll();
    
    if (count($users) &gt; 0) {
        $output .= "   ✓ Found " . count($users) . " user(s):\n\n";
        $output .= "   " . str_pad("ID", 5) . str_pad("Email", 30) . str_pad("Name", 25) . str_pad("Role", 15) . str_pad("Co.ID", 8) . "Has Pass\n";
        $output .= "   " . str_repeat("-", 90) . "\n";
        
        foreach ($users as $user) {
            $hasPassword = !empty($user['password_hash']) ? 'YES' : 'NO';
            $name = $user['first_name'] . ' ' . $user['last_name'];
            
            $output .= "   " . str_pad($user['id'], 5) . 
                 str_pad($user['email'], 30) . 
                 str_pad(substr($name, 0, 24), 25) . 
                 str_pad($user['role'], 15) . 
                 str_pad($user['company_id'], 8) . 
                 $hasPassword . "\n";
        }
        $output .= "\n";
    } else {
        $output .= "   ⚠ No users found in database!\n\n";
    }
} catch (PDOException $e) {
    $output .= "   ✗ User listing FAILED: " . $e-&gt;getMessage() . "\n\n";
}

// 7. Test a specific user login
$output .= "7. Test Login Logic with First User\n";
try {
    $stmt = $pdo-&gt;query("SELECT * FROM users LIMIT 1");
    $user = $stmt-&gt;fetch();
    
    if ($user) {
        $output .= "   Testing with user: {$user['email']}\n";
        $output .= "   - User ID: " . $user['id'] . "\n";
        $output .= "   - Name: " . $user['first_name'] . " " . $user['last_name'] . "\n";
        $output .= "   - Role: " . $user['role'] . "\n";
        $output .= "   - Company ID: " . $user['company_id'] . "\n";
        $output .= "   - Password hash exists: " . (!empty($user['password_hash']) ? 'YES' : 'NO') . "\n";
        
        if (!empty($user['password_hash'])) {
            $hashInfo = password_get_info($user['password_hash']);
            $output .= "   - Hash algorithm: " . $hashInfo['algoName'] . "\n";
            $output .= "   - Hash preview: " . substr($user['password_hash'], 0, 30) . "...\n";
            
            // Note about testing
            $output .= "\n   NOTE: To test actual login, use the debug_login.php page in browser\n";
            $output .= "   or check login_debug.log after attempting login through login.php\n";
        }
        $output .= "\n";
    } else {
        $output .= "   ⚠ No users to test\n\n";
    }
} catch (PDOException $e) {
    $output .= "   ✗ Test FAILED: " . $e-&gt;getMessage() . "\n\n";
}

// 8. Check login files
$output .= "8. Login File Check\n";
$files = [
    'auth/login.php' =&gt; 'Login page',
    'includes/functions.php' =&gt; 'Functions file',
    'config/db.php' =&gt; 'Database config',
    'dashboard/index.php' =&gt; 'Dashboard page'
];

foreach ($files as $file =&gt; $desc) {
    $fullPath = __DIR__ . '/' . $file;
    if (file_exists($fullPath)) {
        $output .= "   ✓ $desc: $file (" . filesize($fullPath) . " bytes)\n";
    } else {
        $output .= "   ✗ $desc NOT FOUND: $file\n";
    }
}

$output .= "\n=== Debug report completed at " . date('Y-m-d H:i:s') . " ===\n";

// Write to file
file_put_contents('debug_result.txt', $output);
echo $output;
echo "\n\nReport saved to debug_result.txt\n";
?&gt;
