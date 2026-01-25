<?php
// repair_salary_components_global.php

try {
    $pdo = new PDO('mysql:host=localhost;dbname=mipaymaster', 'root', '');
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    echo "Connected to Database.\n";

    // Get All Companies
    $companies = $pdo->query("SELECT id, name FROM companies")->fetchAll(PDO::FETCH_ASSOC);
    echo "Found " . count($companies) . " companies.\n";

    // Define System Components
    $system_structure = [
        'Basic Salary' => [
            'type' => 'basic',
            'perc' => 50,
            'base' => 'gross'
        ],
        'Housing Allowance' => [
            'type' => 'system',
            'perc' => 30,
            'base' => 'gross'
        ],
        'Transport Allowance' => [
            'type' => 'system',
            'perc' => 20,
            'base' => 'gross'
        ]
    ];

    foreach ($companies as $comp) {
        $cid = $comp['id'];
        echo "Processing Company: {$comp['name']} (ID: $cid)...\n";

        foreach ($system_structure as $name => $def) {
            // Check existence
            $stmt = $pdo->prepare("SELECT id FROM salary_components WHERE company_id = ? AND name = ?");
            $stmt->execute([$cid, $name]);
            $exists = $stmt->fetch();

            if ($exists) {
                // Update
                $upd = $pdo->prepare("UPDATE salary_components SET 
                    type = ?, 
                    default_percentage = ?, 
                    percentage = ?, 
                    percentage_base = ?, 
                    calculation_method = 'percentage',
                    is_active = 1
                    WHERE id = ?");
                $upd->execute([$def['type'], $def['perc'], $def['perc'], $def['base'], $exists['id']]);
                echo "  - Updated $name\n";
            } else {
                // Insert
                $ins = $pdo->prepare("INSERT INTO salary_components 
                    (company_id, name, type, calculation_method, amount, percentage, default_percentage, percentage_base, is_taxable, is_pensionable, is_active, is_custom)
                    VALUES (?, ?, ?, 'percentage', 0, ?, ?, ?, 1, 1, 1, 0)");
                $ins->execute([$cid, $name, $def['type'], $def['perc'], $def['perc'], $def['base']]);
                echo "  + Created $name\n";
            }
        }
    }

    echo "\nGlobal Repair Complete.\n";

} catch (PDOException $e) {
    echo "Error: " . $e->getMessage();
}
?>
