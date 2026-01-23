<?php
require_once '../config/db.php';
try {
    $stmt = $pdo->query("DESCRIBE salary_components");
    echo "<h1>Table: salary_components</h1><pre>";
    print_r($stmt->fetchAll(PDO::FETCH_ASSOC));
    echo "</pre>";
    
    // Also check values for company logic
    $stmt2 = $pdo->query("SELECT * FROM salary_components LIMIT 5");
    echo "<h1>Sample Data</h1><pre>";
    print_r($stmt2->fetchAll(PDO::FETCH_ASSOC));
    echo "</pre>";

} catch(PDOException $e) { echo $e->getMessage(); }
?>
