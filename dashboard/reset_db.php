<?php
require_once '../config/db.php';
try {
    $pdo->exec("DROP TABLE IF EXISTS salary_components");
    echo "<h1>Table Dropped</h1><p>The salary_components table has been deleted. Reload <a href='company.php?tab=components'>Company Settings</a> to regenerate.</p>";
} catch(PDOException $e) { echo $e->getMessage(); }
?>
