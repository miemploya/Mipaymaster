<?php
require_once 'includes/functions.php';

$stmt = $pdo->query("DESCRIBE users");
print_r($stmt->fetchAll(PDO::FETCH_ASSOC));

echo "\n\nChecking if any employees are linked:\n";
$stmt = $pdo->query("SELECT id, email, role, company_id FROM users LIMIT 5");
print_r($stmt->fetchAll(PDO::FETCH_ASSOC));
