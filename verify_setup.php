<?php
// Verification script to confirm database setup is correct
error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "============================================\n";
echo "   MIPAYMASTER SETUP VERIFICATION\n";
echo "============================================\n\n";

try {
    // Connect to database
    $pdo = new PDO("mysql:host=127.0.0.1;dbname=mipaymaster", "root", "");
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    echo "[✓] Database connection successful\n\n";
    
    // Check Companies
    echo "1. COMPANIES CHECK:\n";
    echo str_repeat("-", 50) . "\n";
    $stmt = $pdo->query("SELECT * FROM companies");
    $companies = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    if (count($companies) === 1) {
        $company = $companies[0];
        echo "[✓] Single company found (correct setup)\n";
        echo "    ID: {$company['id']}\n";
        echo "    Name: {$company['name']}\n";
        echo "    Email: {$company['email']}\n\n";
        $companyId = $company['id'];
    } else {
        echo "[✗] WARNING: Found " . count($companies) . " companies (expected 1)\n\n";
        $companyId = $companies[0]['id'] ?? null;
    }
    
    // Check Users
    echo "2. USERS CHECK:\n";
    echo str_repeat("-", 50) . "\n";
    $stmt = $pdo->query("SELECT id, email, first_name, last_name, role, company_id FROM users");
    $users = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    if (count($users) === 1) {
        $user = $users[0];
        echo "[✓] Single admin user found (correct setup)\n";
        echo "    ID: {$user['id']}\n";
        echo "    Email: {$user['email']}\n";
        echo "    Name: {$user['first_name']} {$user['last_name']}\n";
        echo "    Role: {$user['role']}\n";
        echo "    Company ID: {$user['company_id']}\n";
        
        if ($user['company_id'] == $companyId) {
            echo "[✓] User is correctly linked to company\n\n";
        } else {
            echo "[✗] WARNING: User company_id mismatch\n\n";
        }
    } else {
        echo "[✗] Found " . count($users) . " users\n";
        foreach ($users as $user) {
            echo "    - {$user['email']} (Company ID: {$user['company_id']})\n";
        }
        echo "\n";
    }
    
    // Check Salary Categories
    echo "3. SALARY CATEGORIES CHECK:\n";
    echo str_repeat("-", 50) . "\n";
    $stmt = $pdo->prepare("SELECT COUNT(*) as count FROM salary_categories WHERE company_id = ?");
    $stmt->execute([$companyId]);
    $catCount = $stmt->fetch()['count'];
    
    if ($catCount > 0) {
        echo "[✓] Found $catCount salary categories\n\n";
    } else {
        echo "[✗] WARNING: No salary categories found\n\n";
    }
    
    // Check Statutory Settings
    echo "4. STATUTORY SETTINGS CHECK:\n";
    echo str_repeat("-", 50) . "\n";
    $stmt = $pdo->prepare("SELECT * FROM statutory_settings WHERE company_id = ?");
    $stmt->execute([$companyId]);
    $statutory = $stmt->fetch();
    
    if ($statutory) {
        echo "[✓] Statutory settings configured\n";
        echo "    PAYE: " . ($statutory['enable_paye'] ? 'Enabled' : 'Disabled') . "\n";
        echo "    Pension: " . ($statutory['enable_pension'] ? 'Enabled' : 'Disabled') . "\n";
        echo "    Employer %: {$statutory['pension_employer_perc']}%\n";
        echo "    Employee %: {$statutory['pension_employee_perc']}%\n\n";
    } else {
        echo "[✗] WARNING: No statutory settings found\n\n";
    }
    
    // Check Departments
    echo "5. DEPARTMENTS CHECK:\n";
    echo str_repeat("-", 50) . "\n";
    $stmt = $pdo->prepare("SELECT COUNT(*) as count FROM departments WHERE company_id = ?");
    $stmt->execute([$companyId]);
    $deptCount = $stmt->fetch()['count'];
    
    if ($deptCount > 0) {
        echo "[✓] Found $deptCount departments\n\n";
    } else {
        echo "[✗] WARNING: No departments found\n\n";
    }
    
    // Final Status
    echo "\n============================================\n";
    echo "   VERIFICATION SUMMARY\n";
    echo "============================================\n\n";
    
    if (count($companies) === 1 && count($users) === 1 && $catCount > 0 && $statutory && $deptCount > 0) {
        echo "[✓✓✓] ALL CHECKS PASSED!\n\n";
        echo "Your database is correctly set up.\n";
        echo "You can now login at: http://localhost/Mipaymaster/auth/login.php\n\n";
        echo "Login Credentials:\n";
        echo "  Email: {$users[0]['email']}\n";
        echo "  Password: password\n\n";
        echo "IMPORTANT: Change your password after first login!\n\n";
    } else {
        echo "[!] Some checks failed. Review warnings above.\n\n";
    }
    
} catch (PDOException $e) {
    echo "[✗] DATABASE ERROR: " . $e->getMessage() . "\n\n";
    echo "Make sure:\n";
    echo "1. MySQL is running\n";
    echo "2. Database 'mipaymaster' exists\n";
    echo "3. You have run FRESH_DATABASE_SETUP.sql\n\n";
}

echo "============================================\n";
?>
