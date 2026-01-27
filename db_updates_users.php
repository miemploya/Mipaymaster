<?php
require_once 'includes/functions.php';

try {
    echo "Checking 'users' table schema...\n";
    $stmt = $pdo->query("SHOW COLUMNS FROM users LIKE 'username'");
    $col = $stmt->fetch();

    if (!$col) {
        echo "Adding 'username' column...\n";
        $pdo->exec("ALTER TABLE users ADD COLUMN username VARCHAR(50) NULL UNIQUE AFTER email");
        echo "Column added successfully.\n";
    } else {
        echo "Column 'username' already exists.\n";
    }

    // Add index if not exists (UNIQUE constraint creates index automatically usually, but good to verify)
    
    echo "Migration complete.\n";

} catch (PDOException $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
