&lt;?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "=== MiPayMaster Login Debug Report ===\n\n";

// 1. Test Database Connection
echo "1. Database Connection Test\n";
try {
    $pdo = new PDO("mysql:host=127.0.0.1;dbname=mipaymaster", "root", "");
    $pdo-&gt;setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    echo "   ✓ Database connection successful\n\n";
} catch (PDOException $e) {
    echo "   ✗ Database connection FAILED: " . $e-&gt;getMessage() . "\n\n";
    die();
}

// 2. Simple Query Test (SELECT 1)
echo "2. Simple Query Test (SELECT 1)\n";
try {
    $result = $pdo-&gt;query("SELECT 1 as test")-&gt;fetch();
    echo "   ✓ Simple query successful. Result: " . $result['test'] . "\n\n";
} catch (PDOException $e) {
    echo "   ✗ Simple query FAILED: " . $e-&gt;getMessage() . "\n\n";
}

// 3. Check Read-Only Mode
echo "3. Read-Only Mode Check\n";
try {
    $stmt = $pdo-&gt;query("SHOW VARIABLES LIKE 'read_only'");
    $readOnly = $stmt-&gt;fetch();
    echo "   read_only = " . $readOnly['Value'] . ($readOnly['Value'] == 'OFF' ? ' ✓' : ' ⚠ WARNING') . "\n";
    
    $stmt = $pdo-&gt;query("SHOW VARIABLES LIKE 'innodb_force_recovery'");
    $recovery = $stmt-&gt;fetch();
    echo "   innodb_force_recovery = " . $recovery['Value'] . ($recovery['Value'] == '0' ? ' ✓' : ' ⚠ CAUTION: Recovery mode active') . "\n\n";
} catch (PDOException $e) {
    echo "   ✗ Read-only check FAILED: " . $e-&gt;getMessage() . "\n\n";
}

// 4. Test INSERT capability
echo "4. Database Write Test\n";
try {
    $pdo-&gt;exec("CREATE TABLE IF NOT EXISTS debug_test (id INT AUTO_INCREMENT PRIMARY KEY, test_value VARCHAR(50), created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP)");
    echo "   ✓ Table creation successful\n";
    
    $pdo-&gt;exec("INSERT INTO debug_test (test_value) VALUES ('test_" . date('His') . "')");
    echo "   ✓ INSERT operation successful\n";
    
    $count = $pdo-&gt;query("SELECT COUNT(*) as cnt FROM debug_test")-&gt;fetch()['cnt'];
    echo "   ✓ SELECT count successful. Records: $count\n";
    
    $pdo-&gt;exec("DROP TABLE debug_test");
    echo "   ✓ Table deletion successful\n";
    echo "   Database is fully writable!\n\n";
} catch (PDOException $e) {
    echo "   ✗ Write test FAILED: " . $e-&gt;getMessage() . "\n";
    echo "   ⚠ Database may be in READ-ONLY mode!\n\n";
}

// 5. Check Users Table
echo "5. Users Table Verification\n";
try {
    $stmt = $pdo-&gt;query("DESCRIBE users");
    echo "   ✓ Users table exists. Key columns:\n";
    $cols = [];
    while ($row = $stmt-&gt;fetch()) {
        if (in_array($row['Field'], ['id', 'email', 'password_hash', 'role', 'company_id', 'first_name'])) {
            $cols[] = $row['Field'];
        }
    }
    echo "     " . implode(', ', $cols) . "\n\n";
} catch (PDOException $e) {
    echo "   ✗ Users table check FAILED: " . $e-&gt;getMessage() . "\n\n";
}

// 6. List All Users
echo "6. User Accounts in Database\n";
try {
    $stmt = $pdo-&gt;query("SELECT id, email, role, company_id, first_name, last_name, password_hash FROM users ORDER BY id");
    $users = $stmt-&gt;fetchAll();
    
    if (count($users) &gt; 0) {
        echo "   ✓ Found " . count($users) . " user(s):\n\n";
        echo "   " . str_pad("ID", 5) . str_pad("Email", 30) . str_pad("Name", 25) . str_pad("Role", 15) . str_pad("Co.ID", 8) . "Has Pass\n";
        echo "   " . str_repeat("-", 90) . "\n";
        
        foreach ($users as $user) {
            $hasPassword = !empty($user['password_hash']) ? '✓' : '✗';
            $name = $user['first_name'] . ' ' . $user['last_name'];
            
            echo "   " . str_pad($user['id'], 5) . 
                 str_pad($user['email'], 30) . 
                 str_pad($name, 25) . 
                 str_pad($user['role'], 15) . 
                 str_pad($user['company_id'], 8) . 
                 $hasPassword . "\n";
        }
        echo "\n";
    } else {
        echo "   ⚠ No users found in database!\n\n";
    }
} catch (PDOException $e) {
    echo "   ✗ User listing FAILED: " . $e-&gt;getMessage() . "\n\n";
}

// 7. Test a specific user login
echo "7. Test Login with First User (if exists)\n";
try {
    $stmt = $pdo-&gt;query("SELECT * FROM users LIMIT 1");
    $user = $stmt-&gt;fetch();
    
    if ($user) {
        echo "   Testing with user: {$user['email']}\n";
        echo "   - User ID: " . $user['id'] . "\n";
        echo "   - Name: " . $user['first_name'] . " " . $user['last_name'] . "\n";
        echo "   - Role: " . $user['role'] . "\n";
        echo "   - Company ID: " . $user['company_id'] . "\n";
        echo "   - Password hash exists: " . (!empty($user['password_hash']) ? 'YES' : 'NO') . "\n";
        
        if (!empty($user['password_hash'])) {
            $hashInfo = password_get_info($user['password_hash']);
            echo "   - Hash algorithm: " . $hashInfo['algoName'] . "\n";
            echo "   - Hash preview: " . substr($user['password_hash'], 0, 30) . "...\n";
        }
        echo "\n";
    } else {
        echo "   ⚠ No users to test\n\n";
    }
} catch (PDOException $e) {
    echo "   ✗ Test FAILED: " . $e-&gt;getMessage() . "\n\n";
}

// 8. Check login files
echo "8. Login File Check\n";
$files = [
    'auth/login.php' =&gt; 'Login page',
    'includes/functions.php' =&gt; 'Functions file',
    'config/db.php' =&gt; 'Database config'
];

foreach ($files as $file =&gt; $desc) {
    $fullPath = __DIR__ . '/' . $file;
    if (file_exists($fullPath)) {
        echo "   ✓ $desc: $file (" . filesize($fullPath) . " bytes)\n";
    } else {
        echo "   ✗ $desc NOT FOUND: $file\n";
    }
}

echo "\n=== Debug report completed at " . date('Y-m-d H:i:s') . " ===\n";
?&gt;
