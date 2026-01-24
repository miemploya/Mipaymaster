<?php
require_once 'includes/functions.php';

function inspect_table($pdo, $table) {
    echo "Inspecting $table:\n";
    try {
        $stmt = $pdo->query("DESCRIBE $table");
        $columns = $stmt->fetchAll(PDO::FETCH_COLUMN);
        print_r($columns);
    } catch (Exception $e) {
        echo "Table $table not found or error: " . $e->getMessage() . "\n";
    }
    echo "\n";
}

inspect_table($pdo, 'salary_categories');
inspect_table($pdo, 'salary_components');
inspect_table($pdo, 'salary_category_breakdown');
?>
