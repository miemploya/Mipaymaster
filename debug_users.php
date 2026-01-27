<?php
require_once 'includes/functions.php';

echo "<h1>User Company Debug</h1>";
echo "<pre>";
try {
    $stmt = $pdo->query("SELECT id, first_name, email, company_id, role, created_at FROM users WHERE id IN (2, 4)");
    $users = $stmt->fetchAll(PDO::FETCH_ASSOC);
    print_r($users);
} catch (Exception $e) {
    echo "Error: " . $e->getMessage();
}
echo "</pre>";
