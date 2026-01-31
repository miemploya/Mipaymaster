<?php
ini_set('display_errors', 1);
error_reporting(E_ALL);
set_time_limit(10); 

function test_connection($host, $user, $pass, $db) {
    echo "Testing connection to <b>$host</b>... ";
    flush(); 
    $start = microtime(true);
    try {
        $dsn = "mysql:host=$host;dbname=$db;charset=utf8mb4";
        $options = [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_TIMEOUT => 3, // Short timeout
        ];
        $pdo = new PDO($dsn, $user, $pass, $options);
        $duration = round((microtime(true) - $start) * 1000);
        echo "<span style='color:green'>SUCCESS</span> ($duration ms)<br>";
        return true;
    } catch (PDOException $e) {
        $duration = round((microtime(true) - $start) * 1000);
        echo "<span style='color:red'>FAILED</span> ($duration ms) - " . $e->getMessage() . "<br>";
        return false;
    }
}

echo "<h1>MySQL Connection Diagnostic</h1>";

$user = 'root';
$pass = ''; // Default XAMPP
$db = 'mipaymaster';

test_connection('127.0.0.1', $user, $pass, $db);
test_connection('localhost', $user, $pass, $db);
test_connection('::1', $user, $pass, $db);

echo "<br>Done.";
?>
