<?php
// Fix Employee Data
require_once 'includes/functions.php';

try {
    echo "1. Checking empty status count...\n";
    $count = $pdo->query("SELECT count(*) FROM employees WHERE employment_status IS NULL OR employment_status = ''")->fetchColumn();
    echo "Found $count employees with empty status.\n";
    
    if ($count > 0) {
        echo "2. Updating status to 'active'...\n";
        $pdo->exec("UPDATE employees SET employment_status = 'active' WHERE employment_status IS NULL OR employment_status = ''");
        echo "[SUCCESS] Updated records.\n";
    }
    
    echo "3. Verification:\n";
    $stmt = $pdo->query("SELECT id, employment_status FROM employees");
    foreach($stmt->fetchAll(PDO::FETCH_ASSOC) as $r) {
        echo "ID {$r['id']}: {$r['employment_status']}\n";
    }
    
} catch (Exception $e) {
    echo "Error: " . $e->getMessage();
}
?>
