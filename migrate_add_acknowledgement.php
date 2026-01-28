<?php
/**
 * Migration: Add acknowledged_at column to employee_case_messages table
 */
require_once 'includes/functions.php';

global $pdo;

try {
    // Check if column already exists
    $stmt = $pdo->query("SHOW COLUMNS FROM employee_case_messages LIKE 'acknowledged_at'");
    $exists = $stmt->fetch();
    
    if (!$exists) {
        echo "Adding acknowledged_at column to employee_case_messages table...\n";
        $pdo->exec("ALTER TABLE employee_case_messages ADD COLUMN acknowledged_at DATETIME NULL AFTER attachment_name");
        echo "✓ Column added successfully!\n";
    } else {
        echo "✓ Column already exists. Skipping.\n";
    }
    
    echo "\nMigration completed successfully!\n";
} catch (Exception $e) {
    echo "ERROR: " . $e->getMessage() . "\n";
    exit(1);
}
