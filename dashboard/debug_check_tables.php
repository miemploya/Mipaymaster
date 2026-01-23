<?php
require_once '../config/db.php';

$company_id = 1; // Assuming 1, but let's check companies too.

try {
    echo "Companies:\n";
    $stmt = $pdo->query("SELECT id, name FROM companies");
    $comps = $stmt->fetchAll(PDO::FETCH_ASSOC);
    print_r($comps);
    
    echo "\nDepartments:\n";
    $stmt = $pdo->query("SELECT * FROM departments");
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
    print_r($rows);

} catch (PDOException $e) {
    echo "Error: " . $e->getMessage();
}
?>
