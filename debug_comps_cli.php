<?php
// Bypass login for CLI
// require_once 'includes/functions.php'; 
// require_login();

// Minimal DB connection
try {
    $pdo = new PDO('mysql:host=localhost;dbname=mipaymaster', 'root', '');
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    die("DB Connection failed: " . $e->getMessage());
}

$company_id = 1;

echo "--- Salary Components Debug ---\n";

// 1. Show Columns
echo "\n[SCHEMA]\n";
$cols = $pdo->query("SHOW COLUMNS FROM salary_components")->fetchAll(PDO::FETCH_ASSOC);
foreach($cols as $c) {
    echo $c['Field'] . " (" . $c['Type'] . ") | Default: " . ($c['Default'] ?? 'NULL') . "\n";
}

// 2. Show Data
echo "\n[DATA Company $company_id]\n";
$rows = $pdo->query("SELECT id, name, type, default_percentage, percentage, is_active FROM salary_components WHERE company_id = $company_id")->fetchAll(PDO::FETCH_ASSOC);
printf("%-5s %-30s %-10s %-10s %-10s %-10s\n", "ID", "Name", "Type", "Def %", "Cur %", "Active");
foreach($rows as $r) {
    printf("%-5d %-30s %-10s %-10s %-10s %-10s\n", 
        $r['id'], 
        substr($r['name'], 0, 28), 
        $r['type'], 
        $r['default_percentage'], 
        $r['percentage'], 
        $r['is_active']
    );
}
?>
