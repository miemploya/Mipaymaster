<?php
/**
 * Deep Database Connection Diagnostic
 * Tests multiple connection methods to find a working path
 */
echo "<h2>Database Connection Diagnostics</h2>";

$tests = [
    ['host' => '127.0.0.1', 'user' => 'root', 'pass' => ''],
    ['host' => 'localhost', 'user' => 'root', 'pass' => ''],
    ['host' => '::1', 'user' => 'root', 'pass' => ''],
    ['host' => '127.0.0.1', 'user' => 'root', 'pass' => 'root'], // Common alt password
    ['host' => 'localhost', 'user' => 'pma', 'pass' => ''], // PMA control user
];

echo "<table border='1' cellpadding='5' style='border-collapse:collapse; width:100%'>";
echo "<tr><th>Host</th><th>User</th><th>Pass</th><th>Status</th><th>Error</th></tr>";

$success = false;

foreach ($tests as $t) {
    echo "<tr>";
    echo "<td>{$t['host']}</td>";
    echo "<td>{$t['user']}</td>";
    echo "<td>" . ($t['pass'] ? '****' : '(empty)') . "</td>";
    
    try {
        $dsn = "mysql:host={$t['host']};dbname=mysql";
        $options = [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION];
        // Short timeout
        $options[PDO::ATTR_TIMEOUT] = 3;
        
        $pdo = new PDO($dsn, $t['user'], $t['pass'], $options);
        echo "<td style='color:green; font-weight:bold'>SUCCESS</td>";
        echo "<td>Connected!</td>";
        $success = true;
    } catch (PDOException $e) {
        echo "<td style='color:red'>FAILED</td>";
        echo "<td>" . htmlspecialchars($e->getMessage()) . "</td>";
    }
    echo "</tr>";
}
echo "</table>";

if (!$success) {
    echo "<h3>Diagnosis: ALL Connections Rejected</h3>";
    echo "<p>This confirms that the `root` user permissions are corrupted or missing for local connections.</p>";
    echo "<p><strong>You MUST perform the manual reset via Command Line.</strong></p>";
} else {
    echo "<h3>Diagnosis: Found a working connection!</h3>";
    echo "<p>Please update your config/db.php to use the working Host/User combination above.</p>";
}
?>
