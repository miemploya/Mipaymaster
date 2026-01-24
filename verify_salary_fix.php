<?php
ini_set('display_errors', 1);
error_reporting(E_ALL);

// Ensure session starts before including anything that might check it
session_start();
// Mock login to bypass require_login checks in included files if needed
$_SESSION['user_id'] = 1; 
$_SESSION['company_id'] = 1;

require_once 'includes/functions.php';

echo "<h2>Verifying Salary Structure Fix (V2)</h2>";

try {
    // 1. Clean previous test data safely
    $pdo->exec("DELETE FROM salary_categories WHERE name='TEST_CATEGORY_V2' AND company_id=1");
    // Ensure company exists
    $chk_comp = $pdo->query("SELECT id FROM companies WHERE id=1");
    if ($chk_comp->rowCount() == 0) {
        $pdo->exec("INSERT INTO companies (id, name, email) VALUES (1, 'Test Company', 'test@example.com')");
        echo "Created Test Company ID 1<br>";
    }
    
    // 2. Insert Category
    $stmt = $pdo->prepare("INSERT INTO salary_categories (company_id, name, base_gross_amount) VALUES (1, 'TEST_CATEGORY_V2', 500000)");
    $stmt->execute();
    $cat_id = $pdo->lastInsertId();
    echo "✅ Category Created (ID: $cat_id)<br>";

    // 3. Ensure System Components Exist
    $defaults = [
        ['name' => 'Basic Salary', 'type' => 'basic', 'default' => 40],
        ['name' => 'Housing Allowance', 'type' => 'allowance', 'default' => 30],
        ['name' => 'Transport Allowance', 'type' => 'allowance', 'default' => 20]
    ];
    foreach ($defaults as $def) {
        $chk = $pdo->prepare("SELECT id FROM salary_components WHERE company_id=1 AND name=?");
        $chk->execute([$def['name']]);
        if ($chk->rowCount() == 0) {
            $ins = $pdo->prepare("INSERT INTO salary_components (company_id, name, type, default_percentage, is_active, is_custom) VALUES (1, ?, ?, ?, 1, 0)");
            $ins->execute([$def['name'], $def['type'], $def['default']]);
            echo "Created component: {$def['name']}<br>";
        }
    }

    // 4. Fetch Components
    $comps = $pdo->query("SELECT id, name FROM salary_components WHERE company_id=1")->fetchAll(PDO::FETCH_KEY_PAIR);
    
    // 5. Insert Breakdown
    $breakdown_log = [];
    $total_perc = 0;
    
    foreach ($comps as $id => $name) {
        $perc = 0;
        if (strpos($name, 'Basic') !== false) $perc = 40.00;
        elseif (strpos($name, 'Housing') !== false) $perc = 30.00;
        elseif (strpos($name, 'Transport') !== false) $perc = 20.00;
        else $perc = 0.00; 
        
        if ($perc > 0) {
            $pdo->prepare("INSERT INTO salary_category_breakdown (category_id, salary_component_id, component_name, percentage) VALUES (?, ?, ?, ?)")
                ->execute([$cat_id, $id, $name, $perc]);
            $breakdown_log[] = "$name: $perc%";
            $total_perc += $perc;
        }
    }
    
    echo "✅ Breakdown Inserted: " . implode(', ', $breakdown_log) . "<br>";

    // 6. Verify Read
    $check = $pdo->query("SELECT * FROM salary_category_breakdown WHERE category_id=$cat_id")->fetchAll();
    if (count($check) > 0) {
        echo "✅ Verification Successful: " . count($check) . " breakdown rows found.<br>";
        
        // Cleanup if success
        $pdo->exec("DELETE FROM salary_categories WHERE id=$cat_id");
        echo "✅ Cleanup complete.";
    } else {
        echo "❌ Verification Failed: No rows found.<br>";
    }

} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage();
}
?>
