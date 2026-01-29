<?php
/**
 * Migration: Create id_card_settings table
 * Stores company-specific ID card configuration
 */

require_once 'config/db.php';

echo "=== ID Card Settings Migration ===\n\n";

try {
    // Create the id_card_settings table
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS id_card_settings (
            id INT AUTO_INCREMENT PRIMARY KEY,
            company_id INT NOT NULL UNIQUE,
            validity_years INT DEFAULT 1,
            code_type ENUM('qr', 'barcode', 'none') DEFAULT 'qr',
            primary_color VARCHAR(7) DEFAULT '#1e40af',
            secondary_color VARCHAR(7) DEFAULT '#ffffff',
            accent_color VARCHAR(7) DEFAULT '#3b82f6',
            text_color VARCHAR(7) DEFAULT '#1f2937',
            show_department TINYINT(1) DEFAULT 1,
            show_designation TINYINT(1) DEFAULT 1,
            show_employee_id TINYINT(1) DEFAULT 1,
            custom_back_text TEXT,
            emergency_contact VARCHAR(50),
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            INDEX(company_id),
            FOREIGN KEY (company_id) REFERENCES companies(id) ON DELETE CASCADE
        )
    ");
    echo "✅ Created id_card_settings table\n";

    // Initialize settings for existing companies that don't have settings yet
    $stmt = $pdo->query("SELECT id, name, phone FROM companies WHERE id NOT IN (SELECT company_id FROM id_card_settings)");
    $companies = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    if ($companies) {
        $insert = $pdo->prepare("
            INSERT INTO id_card_settings (company_id, emergency_contact, custom_back_text) 
            VALUES (?, ?, 'This card is the property of the company. If found, please return to the address above.')
        ");
        
        foreach ($companies as $company) {
            $insert->execute([$company['id'], $company['phone'] ?? '']);
            echo "  ✅ Initialized ID card settings for: {$company['name']}\n";
        }
    } else {
        echo "  ℹ️ All existing companies already have ID card settings\n";
    }

    echo "\n✅ Migration completed successfully!\n";

} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
}
