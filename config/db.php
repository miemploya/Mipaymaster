<?php
// Database Configuration
define('DB_HOST', '127.0.0.1');
define('DB_USER', 'root');
define('DB_PASS', ''); // Default XAMPP password is empty
define('DB_NAME', 'mipaymaster');

try {
    $pdo = new PDO("mysql:host=" . DB_HOST . ";dbname=" . DB_NAME, DB_USER, DB_PASS);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
    file_put_contents('C:\xampp\htdocs\Mipaymaster\db_debug.log', date('[Y-m-d H:i:s] ') . "DB Connected successfully\n", FILE_APPEND);
} catch (PDOException $e) {
    file_put_contents('C:\xampp\htdocs\Mipaymaster\db_debug.log', date('[Y-m-d H:i:s] ') . "DB Connection FAILED: " . $e->getMessage() . "\n", FILE_APPEND);
    die("ERROR: Could not connect to database. " . $e->getMessage());
}
?>
