<?php
require_once '../includes/functions.php'; // functions.php usually includes db_connect
// Or try to access global $pdo if functions.php establishes it.
if (!isset($pdo)) {
    // Fallback if functions.php doesn't expose $pdo globally (though it usually does in this project)
    // Check if db_connect exists there
    if(file_exists('../includes/db_connect.php')) require_once '../includes/db_connect.php';
}

try {
    $stmt = $pdo->query("SHOW COLUMNS FROM employees");
    $columns = $stmt->fetchAll(PDO::FETCH_COLUMN);
    echo "Columns in employees table:\n";
    print_r($columns);

    echo "\n\nChecking for specific columns:\n";
    $required = ['phone', 'gender', 'dob', 'address', 'marital_status', 'employment_type', 'employment_status', 'date_of_joining', 'job_description', 'salary_category_id', 'bank_name', 'account_number', 'account_name', 'pension_pfa', 'pension_rsa'];
    foreach ($required as $col) {
        if (in_array($col, $columns)) {
            echo "[OK] $col exists\n";
        } else {
            echo "[MISSING] $col\n";
        }
    }

} catch (PDOException $e) {
    echo "Error: " . $e->getMessage();
}
?>
