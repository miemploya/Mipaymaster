<?php
// Migration script to add lateness deduction fields to attendance_policies
require_once __DIR__ . '/includes/functions.php';

header('Content-Type: text/plain');

echo "=== ADDING LATENESS DEDUCTION COLUMNS ===\n\n";

try {
    // Check existing columns
    $cols = $pdo->query("SHOW COLUMNS FROM attendance_policies")->fetchAll(PDO::FETCH_COLUMN);
    
    // Add lateness_deduction_amount if missing
    if (!in_array('lateness_deduction_amount', $cols)) {
        $pdo->exec("ALTER TABLE attendance_policies ADD COLUMN lateness_deduction_amount DECIMAL(10,2) DEFAULT 0 AFTER lateness_deduction_enabled");
        echo "✅ Added lateness_deduction_amount column\n";
    } else {
        echo "⏭️ lateness_deduction_amount already exists\n";
    }
    
    // Add lateness_deduction_type if missing (fixed amount or percentage)
    if (!in_array('lateness_deduction_type', $cols)) {
        $pdo->exec("ALTER TABLE attendance_policies ADD COLUMN lateness_deduction_type ENUM('fixed', 'percentage', 'per_minute') DEFAULT 'fixed' AFTER lateness_deduction_amount");
        echo "✅ Added lateness_deduction_type column\n";
    } else {
        echo "⏭️ lateness_deduction_type already exists\n";
    }
    
    // Add lateness_per_minute_rate if missing (for per-minute calculation)
    if (!in_array('lateness_per_minute_rate', $cols)) {
        $pdo->exec("ALTER TABLE attendance_policies ADD COLUMN lateness_per_minute_rate DECIMAL(10,2) DEFAULT 0 AFTER lateness_deduction_type");
        echo "✅ Added lateness_per_minute_rate column\n";
    } else {
        echo "⏭️ lateness_per_minute_rate already exists\n";
    }
    
    // Add max_lateness_deduction if missing (cap on deduction amount)
    if (!in_array('max_lateness_deduction', $cols)) {
        $pdo->exec("ALTER TABLE attendance_policies ADD COLUMN max_lateness_deduction DECIMAL(10,2) DEFAULT NULL AFTER lateness_per_minute_rate");
        echo "✅ Added max_lateness_deduction column\n";
    } else {
        echo "⏭️ max_lateness_deduction already exists\n";
    }
    
    echo "\n=== COLUMNS NOW AVAILABLE ===\n";
    $cols = $pdo->query("SHOW COLUMNS FROM attendance_policies")->fetchAll(PDO::FETCH_ASSOC);
    foreach ($cols as $col) {
        echo "  - {$col['Field']} ({$col['Type']}) Default: '{$col['Default']}'\n";
    }
    
    echo "\n=== MIGRATION COMPLETE ===\n";
    echo "\nYou can now configure lateness deduction in Company Settings > Attendance Policy.\n";
    
} catch (Exception $e) {
    echo "❌ ERROR: " . $e->getMessage() . "\n";
}
?>
