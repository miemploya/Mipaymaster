<?php
require_once 'includes/functions.php';
require_login();

$company_id = $_SESSION['company_id'] ?? 1;

echo "<h2>Salary Components Debug</h2>";

// 1. Show Columns
echo "<h3>Schema</h3>";
$cols = $pdo->query("SHOW COLUMNS FROM salary_components")->fetchAll(PDO::FETCH_ASSOC);
foreach($cols as $c) {
    echo $c['Field'] . " (" . $c['Type'] . ") | Default: " . $c['Default'] . "<br>";
}

// 2. Show Data
echo "<h3>Data (Company $company_id)</h3>";
$rows = $pdo->query("SELECT id, name, type, default_percentage, percentage, is_active FROM salary_components WHERE company_id = $company_id")->fetchAll(PDO::FETCH_ASSOC);
echo "<table border='1'><tr><th>ID</th><th>Name</th><th>Type</th><th>Default %</th><th>Current %</th><th>Active</th></tr>";
foreach($rows as $r) {
    echo "<tr>";
    foreach($r as $k => $v) echo "<td>$v</td>";
    echo "</tr>";
}
echo "</table>";
?>
