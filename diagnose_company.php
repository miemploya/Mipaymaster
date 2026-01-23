&lt;?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
ob_start();

echo "=== COMPANY SETUP DIAGNOSIS ===\n\n";

try {
    $pdo = new PDO("mysql:host=127.0.0.1;dbname=mipaymaster", "root", "");
    $pdo-&gt;setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    echo "[OK] Database connected\n\n";
    
    // 1. Check Companies
    echo "1. COMPANIES TABLE:\n";
    echo str_repeat("-", 80) . "\n";
    $stmt = $pdo-&gt;query("SELECT * FROM companies");
    $companies = $stmt-&gt;fetchAll(PDO::FETCH_ASSOC);
    
    if (empty($companies)) {
        echo "   WARNING: No companies found!\n\n";
    } else {
        foreach ($companies as $company) {
            echo "   [ID: {$company['id']}] {$company['name']} - {$company['email']}\n";
        }
        echo "   Total: " . count($companies) . " companies\n\n";
    }
    
    // 2. Check Users
    echo "2. USERS TABLE:\n";
    echo str_repeat("-", 80) . "\n";
    $stmt = $pdo-&gt;query("SELECT id, email, first_name, last_name, role, company_id FROM users ORDER BY id");
    $users = $stmt-&gt;fetchAll(PDO::FETCH_ASSOC);
    
    if (empty($users)) {
        echo "   WARNING: No users found!\n\n";
    } else {
        foreach ($users as $user) {
            echo "   [ID: {$user['id']}] {$user['email']}\n";
            echo "     Name: {$user['first_name']} {$user['last_name']}\n";
            echo "     Role: {$user['role']}\n";
            echo "     Company ID: {$user['company_id']}\n";
            echo "\n";
        }
        echo "   Total: " . count($users) . " users\n\n";
    }
    
    // 3. Check for orphaned users (users without valid company)
    echo "3. ORPHANED USERS CHECK:\n";
    echo str_repeat("-", 80) . "\n";
    $stmt = $pdo-&gt;query("
        SELECT u.id, u.email, u.company_id 
        FROM users u 
        LEFT JOIN companies c ON u.company_id = c.id 
        WHERE c.id IS NULL
    ");
    $orphaned = $stmt-&gt;fetchAll(PDO::FETCH_ASSOC);
    
    if (empty($orphaned)) {
        echo "   [OK] All users have valid company references\n\n";
    } else {
        echo "   WARNING: Found " . count($orphaned) . " orphaned users:\n";
        foreach ($orphaned as $user) {
            echo "   - User ID {$user['id']} ({$user['email']}) references non-existent company ID {$user['company_id']}\n";
        }
        echo "\n";
    }
    
    // 4. Check for multiple accounts per email
    echo "4. DUPLICATE EMAIL CHECK:\n";
    echo str_repeat("-", 80) . "\n";
    $stmt = $pdo-&gt;query("
        SELECT email, COUNT(*) as count 
        FROM users 
        GROUP BY email 
        HAVING count &gt; 1
    ");
    $duplicates = $stmt-&gt;fetchAll(PDO::FETCH_ASSOC);
    
    if (empty($duplicates)) {
        echo "   [OK] No duplicate email addresses\n\n";
    } else {
        echo "   WARNING: Found duplicate emails:\n";
        foreach ($duplicates as $dup) {
            echo "   - {$dup['email']} appears {$dup['count']} times\n";
        }
        echo "\n";
    }
    
    // 5. Check employees per company
    echo "5. EMPLOYEES PER COMPANY:\n";
    echo str_repeat("-", 80) . "\n";
    $stmt = $pdo-&gt;query("
        SELECT c.id, c.name, COUNT(e.id) as employee_count 
        FROM companies c 
        LEFT JOIN employees e ON c.id = e.company_id 
        GROUP BY c.id, c.name
    ");
    $companyCounts = $stmt-&gt;fetchAll(PDO::FETCH_ASSOC);
    
    foreach ($companyCounts as $row) {
        echo "   Company [{$row['id']}] {$row['name']}: {$row['employee_count']} employees\n";
    }
    echo "\n";
    
    // 6. Recommended Actions
    echo "6. RECOMMENDED ACTIONS:\n";
    echo str_repeat("-", 80) . "\n";
    
    if (empty($companies)) {
        echo "   ACTION NEEDED: Create a company record\n";
    }
    
    if (empty($users)) {
        echo "   ACTION NEEDED: Create a user account\n";
    }
    
    if (!empty($orphaned)) {
        echo "   ACTION NEEDED: Fix orphaned users - assign to valid company or delete\n";
    }
    
    if (!empty($duplicates)) {
        echo "   ACTION NEEDED: Merge or remove duplicate email accounts\n";
    }
    
    if (count($companies) &gt; 1 &amp;&amp; count($users) == 1) {
        echo "   ISSUE: Multiple companies but only one user - may cause account switching issues\n";
    }
    
    echo "\n";
    
} catch (PDOException $e) {
    echo "ERROR: " . $e-&gt;getMessage() . "\n";
}

echo "=== END DIAGNOSIS ===\n";

$output = ob_get_clean();
file_put_contents('company_diagnosis.txt', $output);
echo $output;
?&gt;
