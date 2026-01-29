<?php
/**
 * Migration: Enhance ID Card Settings with Templates, Shapes, and Verification Tokens
 * Run this file once to update the database schema
 */

require_once 'config/db.php';

echo "<h2>Enhanced ID Card Settings Migration</h2>";
echo "<pre>";

try {
    // 1. Add new columns to id_card_settings
    $alterQueries = [
        "ALTER TABLE id_card_settings ADD COLUMN IF NOT EXISTS card_shape ENUM('horizontal', 'vertical', 'square') DEFAULT 'horizontal' AFTER code_type",
        "ALTER TABLE id_card_settings ADD COLUMN IF NOT EXISTS template_id INT DEFAULT 1 AFTER card_shape",
        "ALTER TABLE id_card_settings ADD COLUMN IF NOT EXISTS logo_position ENUM('left', 'center', 'right') DEFAULT 'left' AFTER template_id"
    ];
    
    foreach ($alterQueries as $sql) {
        try {
            $pdo->exec($sql);
            echo "✓ Executed: " . substr($sql, 0, 60) . "...\n";
        } catch (PDOException $e) {
            if (strpos($e->getMessage(), 'Duplicate column') !== false) {
                echo "⚠ Column already exists, skipping...\n";
            } else {
                throw $e;
            }
        }
    }
    
    // 2. Add verification_token to employees table
    try {
        $pdo->exec("ALTER TABLE employees ADD COLUMN IF NOT EXISTS id_card_token VARCHAR(64) DEFAULT NULL");
        echo "✓ Added id_card_token column to employees\n";
    } catch (PDOException $e) {
        if (strpos($e->getMessage(), 'Duplicate column') !== false) {
            echo "⚠ id_card_token column already exists\n";
        } else {
            throw $e;
        }
    }
    
    // 3. Add index for faster token lookups
    try {
        $pdo->exec("ALTER TABLE employees ADD INDEX idx_id_card_token (id_card_token)");
        echo "✓ Added index on id_card_token\n";
    } catch (PDOException $e) {
        if (strpos($e->getMessage(), 'Duplicate') !== false) {
            echo "⚠ Index already exists\n";
        } else {
            throw $e;
        }
    }
    
    // 4. Generate tokens for existing employees who don't have one
    $stmt = $pdo->query("SELECT id FROM employees WHERE id_card_token IS NULL OR id_card_token = ''");
    $employees = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    $updateStmt = $pdo->prepare("UPDATE employees SET id_card_token = ? WHERE id = ?");
    $count = 0;
    
    foreach ($employees as $emp) {
        $token = bin2hex(random_bytes(32)); // 64 character secure token
        $updateStmt->execute([$token, $emp['id']]);
        $count++;
    }
    
    echo "✓ Generated tokens for $count employees\n";
    
    echo "\n<strong>Migration completed successfully!</strong>\n";
    
} catch (Exception $e) {
    echo "✗ Error: " . $e->getMessage() . "\n";
}

echo "</pre>";
?>
