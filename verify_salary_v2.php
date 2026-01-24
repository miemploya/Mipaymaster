<?php
ini_set('display_errors', 1);
error_reporting(E_ALL);

echo "<h2>Standalone Verification V2</h2>";

try {
    $pdo = new PDO('mysql:host=localhost;dbname=mipaymaster', 'root', '');
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    echo " | DB Connected<br>";

    // 1. Create Test Category
    $pdo->exec("DELETE FROM salary_categories WHERE name='TEST_VERIFY_V2'");
    
    // Ensure company 1 exists
    $pdo->exec("INSERT IGNORE INTO companies (id, name, email) VALUES (1, 'Test Co', 'test@test.com')");

    $stmt = $pdo->prepare("INSERT INTO salary_categories (company_id, name, base_gross_amount) VALUES (1, 'TEST_VERIFY_V2', 1200000)");
    $stmt->execute();
    $cat_id = $pdo->lastInsertId();
    echo "✅ Category Created: $cat_id<br>";

    // 2. Ensure Components
    $comps = ['Basic Salary', 'Housing Allowance', 'Transport Allowance'];
    foreach($comps as $c) {
        $pdo->prepare("INSERT IGNORE INTO salary_components (company_id, name, type, default_percentage, is_active, is_custom) VALUES (1, ?, 'allowance', 10, 1, 0)")->execute([$c]);
    }
    
    // 3. Get IDs
    $comp_ids = [];
    foreach($comps as $c) {
        $s = $pdo->prepare("SELECT id FROM salary_components WHERE name=? AND company_id=1");
        $s->execute([$c]);
        $comp_ids[$c] = $s->fetchColumn();
    }

    // 4. Insert Breakdown
    echo "Inserting Breakdown...<br>";
    $pdo->prepare("INSERT INTO salary_category_breakdown (category_id, salary_component_id, component_name, percentage) VALUES (?, ?, ?, 40)")->execute([$cat_id, $comp_ids['Basic Salary'], 'Basic Salary']);
    
    // 5. Read Back
    $rows = $pdo->query("SELECT * FROM salary_category_breakdown WHERE category_id=$cat_id")->fetchAll(PDO::FETCH_ASSOC);
    echo "✅ Rows Found: " . count($rows) . "<br>";
    if(count($rows) > 0) {
        foreach($rows as $r) {
            echo " - " . $r['component_name'] . ": " . $r['percentage'] . "%<br>";
        }
    }

    // Cleanup
    $pdo->exec("DELETE FROM salary_categories WHERE id=$cat_id");
    echo "✅ Cleanup Done.";

} catch (Exception $e) {
    echo "❌ CRITICAL ERROR: " . $e->getMessage();
}
?>
