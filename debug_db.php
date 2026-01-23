<?php
require_once 'config/db.php';

try {
    echo "Database connection successful.\n";
    
    $stmt = $pdo->query("SELECT count(*) FROM users");
    $count = $stmt->fetchColumn();
    echo "Users count: " . $count . "\n";
    
    if ($count > 0) {
        $stmt = $pdo->query("SELECT id, email, role, company_id FROM users LIMIT 5");
        $users = $stmt->fetchAll(PDO::FETCH_ASSOC);
        echo "Users found:\n";
        print_r($users);
    } else {
        echo "No users found in database.\n";
    }

} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
?>
