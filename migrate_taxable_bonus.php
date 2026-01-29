<?php
/**
 * Migration: Add is_taxable column to payroll tables
 * Per PIT Act - All bonuses are taxable by default
 * Run this script once to add the columns
 */

require_once __DIR__ . '/includes/functions.php';

echo "<h2>Taxable Column Migration (PIT Act Compliance)</h2>";
echo "<p>Per PIT Act, all bonuses are taxable income. This migration adds is_taxable tracking.</p>";

try {
    // 1. Add is_taxable column to payroll_adjustments
    $sql = "ALTER TABLE payroll_adjustments 
            ADD COLUMN IF NOT EXISTS is_taxable TINYINT(1) NOT NULL DEFAULT 1 
            COMMENT 'Default TRUE per PIT Act - all bonuses are taxable'";
    
    try {
        $pdo->exec($sql);
        echo "<p style='color:green'>✓ Added is_taxable column to payroll_adjustments</p>";
    } catch (PDOException $e) {
        if (strpos($e->getMessage(), 'Duplicate column') !== false) {
            echo "<p style='color:orange'>⚠ is_taxable column already exists in payroll_adjustments</p>";
        } else {
            throw $e;
        }
    }
    
    // 2. Add is_taxable column to payroll_bonus_types (recurring bonuses)
    $sql = "ALTER TABLE payroll_bonus_types 
            ADD COLUMN IF NOT EXISTS is_taxable TINYINT(1) NOT NULL DEFAULT 1 
            COMMENT 'Default TRUE per PIT Act - all bonuses are taxable'";
    
    try {
        $pdo->exec($sql);
        echo "<p style='color:green'>✓ Added is_taxable column to payroll_bonus_types</p>";
    } catch (PDOException $e) {
        if (strpos($e->getMessage(), 'Duplicate column') !== false) {
            echo "<p style='color:orange'>⚠ is_taxable column already exists in payroll_bonus_types</p>";
        } else {
            throw $e;
        }
    }
    
    // 3. Add is_pensionable column to payroll_bonus_types (for clarity - NOT pensionable)
    $sql = "ALTER TABLE payroll_bonus_types 
            ADD COLUMN IF NOT EXISTS is_pensionable TINYINT(1) NOT NULL DEFAULT 0 
            COMMENT 'Default FALSE - bonuses are NOT pensionable'";
    
    try {
        $pdo->exec($sql);
        echo "<p style='color:green'>✓ Added is_pensionable column to payroll_bonus_types (default FALSE)</p>";
    } catch (PDOException $e) {
        if (strpos($e->getMessage(), 'Duplicate column') !== false) {
            echo "<p style='color:orange'>⚠ is_pensionable column already exists in payroll_bonus_types</p>";
        } else {
            throw $e;
        }
    }
    
    // 4. Ensure all existing bonuses are marked as taxable (per PIT Act)
    $updated = $pdo->exec("UPDATE payroll_bonus_types SET is_taxable = 1 WHERE is_taxable IS NULL OR is_taxable != 1");
    echo "<p style='color:blue'>ℹ Updated $updated bonus types to taxable (PIT Act compliance)</p>";
    
    $updated2 = $pdo->exec("UPDATE payroll_adjustments SET is_taxable = 1 WHERE type = 'bonus' AND (is_taxable IS NULL OR is_taxable != 1)");
    echo "<p style='color:blue'>ℹ Updated $updated2 one-time bonus adjustments to taxable (PIT Act compliance)</p>";
    
    // Show current table structures
    echo "<h3>Updated Table Structures:</h3>";
    
    echo "<h4>payroll_adjustments:</h4><pre>";
    $stmt = $pdo->query("DESCRIBE payroll_adjustments");
    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $col) {
        if (in_array($col['Field'], ['is_taxable', 'type', 'name', 'amount'])) {
            echo $col['Field'] . " - " . $col['Type'] . " (Default: " . ($col['Default'] ?? 'NULL') . ")\n";
        }
    }
    echo "</pre>";
    
    echo "<h4>payroll_bonus_types:</h4><pre>";
    $stmt = $pdo->query("DESCRIBE payroll_bonus_types");
    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $col) {
        if (in_array($col['Field'], ['is_taxable', 'is_pensionable', 'name', 'amount'])) {
            echo $col['Field'] . " - " . $col['Type'] . " (Default: " . ($col['Default'] ?? 'NULL') . ")\n";
        }
    }
    echo "</pre>";
    
    echo "<h3>✅ Migration Complete!</h3>";
    echo "<div style='background:#e8f5e9;padding:15px;border-radius:8px;margin-top:15px'>";
    echo "<h4>PIT Act Compliance Summary:</h4>";
    echo "<ul>";
    echo "<li><strong>Taxable:</strong> All bonuses are now tracked as taxable income (is_taxable = 1)</li>";
    echo "<li><strong>Pensionable:</strong> Bonuses are NOT pensionable (is_pensionable = 0)</li>";
    echo "<li><strong>PAYE Calculation:</strong> Tax is now calculated on (Adjusted Gross + All Taxable Bonuses)</li>";
    echo "<li><strong>Pension Calculation:</strong> Pension is calculated on (Basic + Housing + Transport) only</li>";
    echo "</ul>";
    echo "</div>";
    
} catch (PDOException $e) {
    echo "<p style='color:red'>Error: " . $e->getMessage() . "</p>";
}
?>
