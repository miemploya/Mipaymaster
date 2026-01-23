<?php
require_once '../config/db.php';
echo "<h1>Debug Users</h1>";
try {
    $stmt = $pdo->query("SELECT id, first_name, last_name, email, company_id, role FROM users");
    $users = $stmt->fetchAll(PDO::FETCH_ASSOC);
    echo "<table border='1'><tr><th>ID</th><th>Name</th><th>Email</th><th>Company ID</th><th>Role</th></tr>";
    foreach ($users as $u) {
        echo "<tr><td>{$u['id']}</td><td>{$u['first_name']} {$u['last_name']}</td><td>{$u['email']}</td><td>" . ($u['company_id'] ?? 'NULL') . "</td><td>{$u['role']}</td></tr>";
    }
    echo "</table>";
} catch (PDOException $e) { echo $e->getMessage(); }
?>
