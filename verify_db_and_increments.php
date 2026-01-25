<?php
// Adjust path based on where this script is located (root) vs includes
$cwd = getcwd();
echo "Debug: CWD is $cwd\n";

if (file_exists('includes/functions.php')) {
    require_once 'includes/functions.php';
} elseif (file_exists('../includes/functions.php')) {
    require_once '../includes/functions.php';
} else {
    // Try absolute path if all else fails
    if (file_exists(__DIR__ . '/includes/functions.php')) {
        require_once __DIR__ . '/includes/functions.php';
    } else {
        die("Could not find includes/functions.php");
    }
}

echo "1. Checking Database Connection...\n";
if ($pdo) {
    echo "[OK] Connection Successful.\n";
} else {
    echo "[FAIL] Connection Failed.\n";
    exit;
}

echo "\n2. Checking 'employee_salary_adjustments' table...\n";
try {
    $result = $pdo->query("SHOW TABLES LIKE 'employee_salary_adjustments'");
    if ($result->rowCount() > 0) {
        echo "[OK] Table 'employee_salary_adjustments' exists.\n";
    } else {
        echo "[FAIL] Table 'employee_salary_adjustments' DOES NOT exist.\n";
    }
} catch (PDOException $e) {
    echo "[ERROR] " . $e->getMessage() . "\n";
}

echo "\n3. Checking Employee Fetch Logic...\n";
try {
    // Check distinct employment_status
    echo "   Checking values in 'employment_status'...\n";
    try {
    // Check total count
    $count = $pdo->query("SELECT count(*) FROM employees")->fetchColumn();
    echo "   Total Employees in DB: $count\n";

    if ($count > 0) {
        $stmt = $pdo->query("SELECT id, employment_status, company_id FROM employees LIMIT 10");
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
        foreach($rows as $r) {
            echo "   ID {$r['id']}: Status='" . $r['employment_status'] . "' CompanyID=" . $r['company_id'] . "\n";
        }
        
        // Group by company
        echo "   -- Group by Company --\n";
        $stmt = $pdo->query("SELECT company_id, count(*) as c FROM employees GROUP BY company_id");
        foreach($stmt->fetchAll(PDO::FETCH_ASSOC) as $r) {
             echo "   Company ID {$r['company_id']}: {$r['c']} employees\n";
        }
    }
        $stmt = $pdo->query("SHOW COLUMNS FROM employees");
        foreach($stmt->fetchAll() as $col) {
            echo "   Column: " . $col['Field'] . "\n";
        }
    }
} catch (PDOException $e) {
    echo "[ERROR] Employee fetch failed: " . $e->getMessage() . "\n";
}
?>
