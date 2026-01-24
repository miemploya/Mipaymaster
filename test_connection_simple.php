<?php
ini_set('display_errors', 1);
error_reporting(E_ALL);
echo "Hello World";
try {
    $pdo = new PDO('mysql:host=localhost;dbname=mipaymaster', 'root', '');
    echo " | DB Connected";
} catch (Exception $e) {
    echo " | DB Fail: " . $e->getMessage();
}
?>
