<?php
// repair_salary_components.php

try {
    // Connect to DB (Standalone)
    $pdo = new PDO('mysql:host=localhost;dbname=mipaymaster', 'root', '');
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    echo "Connected to Database.\n";

    $company_id = 1;

    // 1. Fix Basic Salary
    $stmt = $pdo->prepare("UPDATE salary_components SET 
        type='basic', 
        default_percentage=50, 
        percentage=50, 
        percentage_base='gross', 
        calculation_method='percentage',
        is_active=1
        WHERE name='Basic Salary' AND company_id=?");
    $stmt->execute([$company_id]);
    echo "Updated Basic Salary: Rows affected: " . $stmt->rowCount() . "\n";

    // 2. Fix Housing Allowance
    $stmt = $pdo->prepare("UPDATE salary_components SET 
        type='system', 
        default_percentage=30, 
        percentage=30, 
        percentage_base='gross', 
        calculation_method='percentage',
        is_active=1
        WHERE name='Housing Allowance' AND company_id=?");
    $stmt->execute([$company_id]);
    echo "Updated Housing Allowance: Rows affected: " . $stmt->rowCount() . "\n";

    // 3. Fix Transport Allowance
    $stmt = $pdo->prepare("UPDATE salary_components SET 
        type='system', 
        default_percentage=20, 
        percentage=20, 
        percentage_base='gross', 
        calculation_method='percentage',
        is_active=1
        WHERE name='Transport Allowance' AND company_id=?");
    $stmt->execute([$company_id]);
    echo "Updated Transport Allowance: Rows affected: " . $stmt->rowCount() . "\n";

    echo "\nRepair Complete.\n";

} catch (PDOException $e) {
    echo "Error: " . $e->getMessage();
}
?>
