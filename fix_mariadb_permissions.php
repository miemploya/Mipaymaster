<?php
/**
 * MariaDB/MySQL User Fix Script
 * This script creates commands to fix root user permissions
 * 
 * MANUAL FIX STEPS:
 * 
 * 1. Open XAMPP Control Panel
 * 2. Click "Stop" next to MySQL/MariaDB
 * 3. Open CMD as Administrator
 * 4. Run: C:\xampp\mysql\bin\mysqld.exe --skip-grant-tables
 * 5. Open another CMD and run:
 *    C:\xampp\mysql\bin\mysql.exe -u root mysql
 * 6. Then execute these SQL commands:
 */

echo "<h2>MariaDB User Permission Fix</h2>";
echo "<h3>Manual Fix Required</h3>";
echo "<pre style='background:#1e1e1e; color:#d4d4d4; padding:20px; border-radius:10px;'>";
echo "
STEP 1: Stop MariaDB via XAMPP Control Panel

STEP 2: Open CMD as Administrator and run:
        C:\\xampp\\mysql\\bin\\mysqld.exe --skip-grant-tables

STEP 3: Open ANOTHER CMD window and run:
        C:\\xampp\\mysql\\bin\\mysql.exe -u root mysql

STEP 4: In the MySQL prompt, run these commands:

        UPDATE user SET host='localhost' WHERE user='root' AND host='127.0.0.1';
        UPDATE user SET host='%' WHERE user='root' AND host='::1';
        FLUSH PRIVILEGES;
        
        -- Or recreate root user with all permissions:
        DELETE FROM user WHERE user='root';
        INSERT INTO user (host, user, password, Select_priv, Insert_priv, Update_priv, Delete_priv, Create_priv, Drop_priv, Reload_priv, Shutdown_priv, Process_priv, File_priv, Grant_priv, References_priv, Index_priv, Alter_priv, Show_db_priv, Super_priv, Create_tmp_table_priv, Lock_tables_priv, Execute_priv, Repl_slave_priv, Repl_client_priv, Create_view_priv, Show_view_priv, Create_routine_priv, Alter_routine_priv, Create_user_priv, Event_priv, Trigger_priv, Create_tablespace_priv, ssl_type, max_questions, max_updates, max_connections, max_user_connections, plugin) 
        VALUES ('localhost', 'root', '', 'Y','Y','Y','Y','Y','Y','Y','Y','Y','Y','Y','Y','Y','Y','Y','Y','Y','Y','Y','Y','Y','Y','Y','Y','Y','Y','Y','Y','Y','','0','0','0','0','');
        FLUSH PRIVILEGES;
        
STEP 5: Type 'exit' to quit MySQL

STEP 6: Close both CMD windows

STEP 7: Stop the mysqld.exe process (Ctrl+C or Task Manager)

STEP 8: Start MariaDB normally via XAMPP Control Panel
";
echo "</pre>";

echo "<h3>Alternative: Quick Reset with phpMyAdmin</h3>";
echo "<p>If the above is too complex, you can also:</p>";
echo "<ol>";
echo "<li>Reinstall MariaDB component in XAMPP</li>";
echo "<li>OR restore from a backup of C:\\xampp\\mysql\\data</li>";
echo "</ol>";

// Check if we can at least test the connection
echo "<h3>Connection Test</h3>";
try {
    $pdo = new PDO("mysql:host=127.0.0.1;dbname=mysql", "root", "");
    echo "<p style='color:green'>✓ Connection successful! The issue may have been resolved.</p>";
} catch (PDOException $e) {
    echo "<p style='color:red'>✗ Still cannot connect: " . htmlspecialchars($e->getMessage()) . "</p>";
}
?>
