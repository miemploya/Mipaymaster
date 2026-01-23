&lt;?php
// Quick Database and User Check
// Run this from commandline: C:\xampp\php\php.exe quick_check.php

try {
    $pdo = new PDO("mysql:host=127.0.0.1;dbname=mipaymaster", "root", "");
    $pdo-&gt;setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    echo "Database: Connected\n";
    
    // Check read-only
    $ro = $pdo-&gt;query("SHOW VARIABLES LIKE 'read_only'")-&gt;fetch()['Value'];
    $rec = $pdo-&gt;query("SHOW VARIABLES LIKE 'innodb_force_recovery'")-&gt;fetch()['Value'];
    
    echo "Read-Only: $ro\n";
    echo "Recovery Mode: $rec\n\n";
    
    // List users
    $stmt = $pdo-&gt;query("SELECT id, email, first_name, last_name, role FROM users");
    $users = $stmt-&gt;fetchAll();
    
    echo "Users (" . count($users) . "):\n";
    foreach ($users as $u) {
        echo "  [{$u['id']}] {$u['email']} - {$u['first_name']} {$u['last_name']} ({$u['role']})\n";
    }
    
} catch (Exception $e) {
    echo "ERROR: " . $e-&gt;getMessage() . "\n";
}
?&gt;
