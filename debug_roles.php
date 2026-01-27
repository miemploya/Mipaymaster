<?php
require_once 'includes/functions.php';

echo "<h1>User Roles Debug</h1>";
echo "<pre>";
try {
    $stmt = $pdo->query("SELECT id, first_name, last_name, email, role FROM users");
    $users = $stmt->fetchAll(PDO::FETCH_ASSOC);
    print_r($users);
} catch (Exception $e) {
    echo "Error: " . $e->getMessage();
}
echo "</pre>";
