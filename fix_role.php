<?php
require_once 'includes/functions.php';

try {
    $stmt = $pdo->prepare("UPDATE users SET role = 'super_admin' WHERE id = 4");
    $stmt->execute();
    echo "User 4 role updated to super_admin successfully.";
} catch (Exception $e) {
    echo "Error: " . $e->getMessage();
}
