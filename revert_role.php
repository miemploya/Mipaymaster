<?php
require_once 'includes/functions.php';

try {
    // Revert User 4 to employee
    $stmt = $pdo->prepare("UPDATE users SET role = 'employee' WHERE id = 4");
    $stmt->execute();
    echo "User 4 role reverted to employee successfully.\n";

    // Verify User 2 is super_admin (just to be sure)
    $stmt = $pdo->prepare("SELECT id, role FROM users WHERE id = 2");
    $stmt->execute();
    $user2 = $stmt->fetch(PDO::FETCH_ASSOC);
    echo "User 2 Role: " . $user2['role'] . "\n";

} catch (Exception $e) {
    echo "Error: " . $e->getMessage();
}
