<?php
require_once '../includes/functions.php'; // For DB connection

try {
    echo "Starting Migration...\n";

    $cols = $pdo->query("SHOW COLUMNS FROM employees LIKE 'job_description'")->fetchAll();
    if (count($cols) == 0) {
        $pdo->exec("ALTER TABLE employees ADD COLUMN job_description TEXT DEFAULT NULL");
        echo "Added job_description\n";
    }

    $cols = $pdo->query("SHOW COLUMNS FROM employees LIKE 'phone'")->fetchAll();
    if (count($cols) == 0) {
        $pdo->exec("ALTER TABLE employees ADD COLUMN phone VARCHAR(20) DEFAULT NULL");
        echo "Added phone\n";
    }

    $cols = $pdo->query("SHOW COLUMNS FROM employees LIKE 'gender'")->fetchAll();
    if (count($cols) == 0) {
        $pdo->exec("ALTER TABLE employees ADD COLUMN gender VARCHAR(20) DEFAULT NULL");
        echo "Added gender\n";
    }

    $cols = $pdo->query("SHOW COLUMNS FROM employees LIKE 'dob'")->fetchAll();
    if (count($cols) == 0) {
        $pdo->exec("ALTER TABLE employees ADD COLUMN dob DATE DEFAULT NULL");
        echo "Added dob\n";
    }

    $cols = $pdo->query("SHOW COLUMNS FROM employees LIKE 'address'")->fetchAll();
    if (count($cols) == 0) {
        $pdo->exec("ALTER TABLE employees ADD COLUMN address TEXT DEFAULT NULL");
        echo "Added address\n";
    }

    $cols = $pdo->query("SHOW COLUMNS FROM employees LIKE 'employment_status'")->fetchAll();
    if (count($cols) == 0) {
        $pdo->exec("ALTER TABLE employees ADD COLUMN employment_status VARCHAR(50) DEFAULT 'Active'");
        echo "Added employment_status\n";
    }

    $cols = $pdo->query("SHOW COLUMNS FROM employees LIKE 'date_of_joining'")->fetchAll();
    if (count($cols) == 0) {
        $pdo->exec("ALTER TABLE employees ADD COLUMN date_of_joining DATE DEFAULT NULL");
        echo "Added date_of_joining\n";
    }

    $cols = $pdo->query("SHOW COLUMNS FROM employees LIKE 'bank_name'")->fetchAll();
    if (count($cols) == 0) {
        $pdo->exec("ALTER TABLE employees ADD COLUMN bank_name VARCHAR(100) DEFAULT NULL");
        echo "Added bank_name\n";
    }

    $cols = $pdo->query("SHOW COLUMNS FROM employees LIKE 'account_number'")->fetchAll();
    if (count($cols) == 0) {
        $pdo->exec("ALTER TABLE employees ADD COLUMN account_number VARCHAR(50) DEFAULT NULL");
        echo "Added account_number\n";
    }

    $cols = $pdo->query("SHOW COLUMNS FROM employees LIKE 'account_name'")->fetchAll();
    if (count($cols) == 0) {
        $pdo->exec("ALTER TABLE employees ADD COLUMN account_name VARCHAR(100) DEFAULT NULL");
        echo "Added account_name\n";
    }

    $cols = $pdo->query("SHOW COLUMNS FROM employees LIKE 'pension_pfa'")->fetchAll();
    if (count($cols) == 0) {
        $pdo->exec("ALTER TABLE employees ADD COLUMN pension_pfa VARCHAR(100) DEFAULT NULL");
        echo "Added pension_pfa\n";
    }

    $cols = $pdo->query("SHOW COLUMNS FROM employees LIKE 'pension_rsa'")->fetchAll();
    if (count($cols) == 0) {
        $pdo->exec("ALTER TABLE employees ADD COLUMN pension_rsa VARCHAR(50) DEFAULT NULL");
        echo "Added pension_rsa\n";
    }

    $cols = $pdo->query("SHOW COLUMNS FROM employees LIKE 'salary_category_id'")->fetchAll();
    if (count($cols) == 0) {
        $pdo->exec("ALTER TABLE employees ADD COLUMN salary_category_id INT DEFAULT NULL");
         echo "Added salary_category_id\n";
    }

    echo "Migration Completed.\n";

} catch (PDOException $e) {
    echo "Migration Error: " . $e->getMessage() . "\n";
}
?>
