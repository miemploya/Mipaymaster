<?php
// Migration: Add per-method lateness toggles to attendance_policies
require_once __DIR__ . '/includes/functions.php';

header('Content-Type: text/plain; charset=utf-8');

echo "=== Adding Per-Method Lateness Toggle Columns ===\n\n";

$columns_to_add = [
    'lateness_apply_manual' => "ALTER TABLE attendance_policies ADD COLUMN lateness_apply_manual TINYINT(1) DEFAULT 0 AFTER lateness_deduction_enabled",
    'lateness_apply_self' => "ALTER TABLE attendance_policies ADD COLUMN lateness_apply_self TINYINT(1) DEFAULT 1 AFTER lateness_apply_manual",
    'lateness_apply_biometric' => "ALTER TABLE attendance_policies ADD COLUMN lateness_apply_biometric TINYINT(1) DEFAULT 1 AFTER lateness_apply_self"
];

try {
    foreach ($columns_to_add as $col => $sql) {
        // Check if column exists
        $check = $pdo->query("SHOW COLUMNS FROM attendance_policies LIKE '$col'")->fetch();
        if (!$check) {
            $pdo->exec($sql);
            echo "✅ Added column: $col\n";
        } else {
            echo "⏭️ Column already exists: $col\n";
        }
    }
    
    echo "\n=== Migration Complete! ===\n";
    echo "Default values:\n";
    echo "  - Manual: OFF (admin enters time, no auto-deduction)\n";
    echo "  - Self Check-in: ON\n";
    echo "  - Biometric: ON\n";
    
} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
}
?>
