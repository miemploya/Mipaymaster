<?php
/**
 * Fix missing days_count column in leave_requests table
 */
require_once __DIR__ . '/config/db.php';

echo "<pre>";
echo "=== Fixing Leave Requests Table ===\n\n";

try {
    // Check if days_count exists
    $cols = $pdo->query("SHOW COLUMNS FROM leave_requests LIKE 'days_count'")->fetchAll();
    
    if (empty($cols)) {
        $pdo->exec("ALTER TABLE leave_requests ADD COLUMN days_count INT DEFAULT 1 AFTER end_date");
        echo "✅ Added 'days_count' column to leave_requests table\n";
        
        // Update existing records to calculate days_count
        $pdo->exec("UPDATE leave_requests SET days_count = DATEDIFF(end_date, start_date) + 1 WHERE days_count IS NULL OR days_count = 0");
        echo "✅ Updated existing records with calculated days_count\n";
    } else {
        echo "✓ Column 'days_count' already exists\n";
    }
    
    // Show current table structure
    echo "\n=== Current leave_requests Structure ===\n";
    $stmt = $pdo->query("DESCRIBE leave_requests");
    $columns = $stmt->fetchAll(PDO::FETCH_ASSOC);
    foreach ($columns as $col) {
        echo sprintf("  %-20s %s %s\n", $col['Field'], $col['Type'], $col['Null'] === 'YES' ? 'NULL' : 'NOT NULL');
    }
    
    echo "\n✅ Fix complete!\n";
    
} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
}
echo "</pre>";
?>
