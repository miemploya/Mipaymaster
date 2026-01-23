&lt;?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "&lt;h2&gt;MiPayMaster Login Debug Report&lt;/h2&gt;";
echo "&lt;hr&gt;";

// 1. Test Database Connection
echo "&lt;h3&gt;1. Database Connection Test&lt;/h3&gt;";
try {
    $pdo = new PDO("mysql:host=127.0.0.1;dbname=mipaymaster", "root", "");
    $pdo-&gt;setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    echo "✅ Database connection successful&lt;br&gt;";
} catch (PDOException $e) {
    echo "❌ Database connection FAILED: " . $e-&gt;getMessage() . "&lt;br&gt;";
    die();
}

// 2. Simple Query Test (SELECT 1)
echo "&lt;h3&gt;2. Simple Query Test (SELECT 1)&lt;/h3&gt;";
try {
    $result = $pdo-&gt;query("SELECT 1 as test")-&gt;fetch();
    echo "✅ Simple query successful. Result: " . $result['test'] . "&lt;br&gt;";
} catch (PDOException $e) {
    echo "❌ Simple query FAILED: " . $e-&gt;getMessage() . "&lt;br&gt;";
}

// 3. Check Read-Only Mode
echo "&lt;h3&gt;3. Read-Only Mode Check&lt;/h3&gt;";
try {
    $stmt = $pdo-&gt;query("SHOW VARIABLES LIKE 'read_only'");
    $readOnly = $stmt-&gt;fetch();
    echo "read_only = " . $readOnly['Value'] . " " . ($readOnly['Value'] == 'OFF' ? '✅' : '⚠️') . "&lt;br&gt;";
    
    $stmt = $pdo-&gt;query("SHOW VARIABLES LIKE 'innodb_force_recovery'");
    $recovery = $stmt-&gt;fetch();
    echo "innodb_force_recovery = " . $recovery['Value'] . " " . ($recovery['Value'] == '0' ? '✅' : '⚠️ CAUTION: Recovery mode active') . "&lt;br&gt;";
} catch (PDOException $e) {
    echo "❌ Read-only check FAILED: " . $e-&gt;getMessage() . "&lt;br&gt;";
}

// 4. Test INSERT capability
echo "&lt;h3&gt;4. Database Write Test&lt;/h3&gt;";
try {
    // Try creating a test table
    $pdo-&gt;exec("CREATE TABLE IF NOT EXISTS debug_test (id INT AUTO_INCREMENT PRIMARY KEY, test_value VARCHAR(50), created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP)");
    echo "✅ Table creation successful&lt;br&gt;";
    
    // Try inserting
    $pdo-&gt;exec("INSERT INTO debug_test (test_value) VALUES ('test_" . date('His') . "')");
    echo "✅ INSERT operation successful&lt;br&gt;";
    
    // Clean up
    $count = $pdo-&gt;query("SELECT COUNT(*) as cnt FROM debug_test")-&gt;fetch()['cnt'];
    echo "✅ SELECT count successful. Records: $count&lt;br&gt;";
    
    $pdo-&gt;exec("DROP TABLE debug_test");
    echo "✅ Table deletion successful&lt;br&gt;";
    echo "&lt;strong&gt;Database is fully writable!&lt;/strong&gt;&lt;br&gt;";
} catch (PDOException $e) {
    echo "❌ Write test FAILED: " . $e-&gt;getMessage() . "&lt;br&gt;";
    echo "&lt;strong style='color:red'&gt;⚠️ Database may be in READ-ONLY mode!&lt;/strong&gt;&lt;br&gt;";
}

// 5. Check Users Table
echo "&lt;h3&gt;5. Users Table Verification&lt;/h3&gt;";
try {
    $stmt = $pdo-&gt;query("DESCRIBE users");
    echo "✅ Users table exists. Columns:&lt;br&gt;";
    while ($row = $stmt-&gt;fetch()) {
        echo "  - " . $row['Field'] . " (" . $row['Type'] . ")&lt;br&gt;";
    }
} catch (PDOException $e) {
    echo "❌ Users table check FAILED: " . $e-&gt;getMessage() . "&lt;br&gt;";
}

// 6. List All Users
echo "&lt;h3&gt;6. User Accounts in Database&lt;/h3&gt;";
try {
    $stmt = $pdo-&gt;query("SELECT id, email, role, company_id, first_name, last_name FROM users ORDER BY id");
    $users = $stmt-&gt;fetchAll();
    
    if (count($users) &gt; 0) {
        echo "✅ Found " . count($users) . " user(s):&lt;br&gt;";
        echo "&lt;table border='1' cellpadding='5' style='border-collapse: collapse; margin-top: 10px;'&gt;";
        echo "&lt;tr&gt;&lt;th&gt;ID&lt;/th&gt;&lt;th&gt;Email&lt;/th&gt;&lt;th&gt;Name&lt;/th&gt;&lt;th&gt;Role&lt;/th&gt;&lt;th&gt;Company ID&lt;/th&gt;&lt;th&gt;Has Password&lt;/th&gt;&lt;/tr&gt;";
        
        foreach ($users as $user) {
            // Check if password_hash exists
            $passStmt = $pdo-&gt;prepare("SELECT password_hash FROM users WHERE id = ?");
            $passStmt-&gt;execute([$user['id']]);
            $passData = $passStmt-&gt;fetch();
            $hasPassword = !empty($passData['password_hash']) ? '✅' : '❌';
            
            echo "&lt;tr&gt;";
            echo "&lt;td&gt;{$user['id']}&lt;/td&gt;";
            echo "&lt;td&gt;{$user['email']}&lt;/td&gt;";
            echo "&lt;td&gt;{$user['first_name']} {$user['last_name']}&lt;/td&gt;";
            echo "&lt;td&gt;{$user['role']}&lt;/td&gt;";
            echo "&lt;td&gt;{$user['company_id']}&lt;/td&gt;";
            echo "&lt;td&gt;{$hasPassword}&lt;/td&gt;";
            echo "&lt;/tr&gt;";
        }
        echo "&lt;/table&gt;";
    } else {
        echo "⚠️ No users found in database!&lt;br&gt;";
    }
} catch (PDOException $e) {
    echo "❌ User listing FAILED: " . $e-&gt;getMessage() . "&lt;br&gt;";
}

// 7. Test Login Logic with Specific User
echo "&lt;h3&gt;7. Test Login Logic&lt;/h3&gt;";
echo "&lt;form method='POST' style='margin: 15px 0;'&gt;";
echo "Email: &lt;input type='email' name='test_email' placeholder='user@example.com' required&gt;&lt;br&gt;&lt;br&gt;";
echo "Password: &lt;input type='password' name='test_password' placeholder='password' required&gt;&lt;br&gt;&lt;br&gt;";
echo "&lt;button type='submit' name='test_login'&gt;Test Login&lt;/button&gt;";
echo "&lt;/form&gt;";

if (isset($_POST['test_login'])) {
    $test_email = $_POST['test_email'];
    $test_password = $_POST['test_password'];
    
    echo "&lt;div style='background: #f0f0f0; padding: 15px; border-radius: 5px; margin-top: 10px;'&gt;";
    echo "&lt;strong&gt;Testing login with email: " . htmlspecialchars($test_email) . "&lt;/strong&gt;&lt;br&gt;&lt;br&gt;";
    
    try {
        // Step 1: Query user
        $stmt = $pdo-&gt;prepare("SELECT * FROM users WHERE email = ?");
        $stmt-&gt;execute([$test_email]);
        $user = $stmt-&gt;fetch();
        
        if ($user) {
            echo "✅ User found in database&lt;br&gt;";
            echo "  - User ID: " . $user['id'] . "&lt;br&gt;";
            echo "  - Name: " . $user['first_name'] . " " . $user['last_name'] . "&lt;br&gt;";
            echo "  - Role: " . $user['role'] . "&lt;br&gt;";
            echo "  - Company ID: " . $user['company_id'] . "&lt;br&gt;";
            echo "  - Password hash exists: " . (!empty($user['password_hash']) ? 'YES' : 'NO') . "&lt;br&gt;";
            echo "&lt;br&gt;";
            
            // Step 2: Verify password
            if (password_verify($test_password, $user['password_hash'])) {
                echo "&lt;strong style='color: green; font-size: 18px;'&gt;✅ PASSWORD VERIFICATION SUCCESSFUL!&lt;/strong&gt;&lt;br&gt;";
                echo "Login would succeed. User would be redirected to dashboard.&lt;br&gt;";
                
                // Simulate session variables
                echo "&lt;br&gt;&lt;strong&gt;Session variables that would be set:&lt;/strong&gt;&lt;br&gt;";
                echo "  - user_id: " . $user['id'] . "&lt;br&gt;";
                echo "  - role: " . $user['role'] . "&lt;br&gt;";
                echo "  - company_id: " . $user['company_id'] . "&lt;br&gt;";
                echo "  - user_name: " . $user['first_name'] . "&lt;br&gt;";
            } else {
                echo "&lt;strong style='color: red; font-size: 18px;'&gt;❌ PASSWORD VERIFICATION FAILED!&lt;/strong&gt;&lt;br&gt;";
                echo "The password you entered does not match the stored hash.&lt;br&gt;";
                
                // Debug: Show hash info
                echo "&lt;br&gt;&lt;small&gt;Password hash info:&lt;br&gt;";
                echo "  - Algorithm: " . password_get_info($user['password_hash'])['algoName'] . "&lt;br&gt;";
                echo "  - Hash starts with: " . substr($user['password_hash'], 0, 20) . "...&lt;br&gt;";
                echo "&lt;/small&gt;";
            }
        } else {
            echo "&lt;strong style='color: orange; font-size: 18px;'&gt;⚠️ USER NOT FOUND&lt;/strong&gt;&lt;br&gt;";
            echo "No user with email '" . htmlspecialchars($test_email) . "' exists in the database.&lt;br&gt;";
        }
    } catch (PDOException $e) {
        echo "&lt;strong style='color: red;'&gt;❌ LOGIN TEST FAILED:&lt;/strong&gt; " . $e-&gt;getMessage() . "&lt;br&gt;";
    }
    
    echo "&lt;/div&gt;";
}

// 8. Session Test
echo "&lt;h3&gt;8. Session Configuration&lt;/h3&gt;";
session_start();
echo "✅ Session started successfully&lt;br&gt;";
echo "Session ID: " . session_id() . "&lt;br&gt;";
echo "Session Save Path: " . session_save_path() . "&lt;br&gt;";

// 9. Check login.php file
echo "&lt;h3&gt;9. Login File Check&lt;/h3&gt;";
$loginFile = dirname(__FILE__) . '/auth/login.php';
if (file_exists($loginFile)) {
    echo "✅ login.php exists at: " . $loginFile . "&lt;br&gt;";
    echo "File size: " . filesize($loginFile) . " bytes&lt;br&gt;";
    echo "Last modified: " . date("Y-m-d H:i:s", filemtime($loginFile)) . "&lt;br&gt;";
} else {
    echo "❌ login.php NOT FOUND at: " . $loginFile . "&lt;br&gt;";
}

echo "&lt;hr&gt;";
echo "&lt;p&gt;&lt;strong&gt;Debug report completed at " . date('Y-m-d H:i:s') . "&lt;/strong&gt;&lt;/p&gt;";
?&gt;
