<?php
/**
 * Migration: Create employee_id_settings table
 * Stores company-specific Employee ID format configuration
 */

require_once 'config/db.php';

echo "=== Employee ID Settings Migration ===\n\n";

try {
    // Create the employee_id_settings table
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS employee_id_settings (
            id INT AUTO_INCREMENT PRIMARY KEY,
            company_id INT NOT NULL UNIQUE,
            prefix VARCHAR(10) NOT NULL DEFAULT 'EMP',
            include_year TINYINT(1) DEFAULT 1,
            id_separator VARCHAR(2) DEFAULT '-',
            number_padding INT DEFAULT 3,
            next_number INT DEFAULT 1,
            is_locked TINYINT(1) DEFAULT 0,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            INDEX(company_id),
            FOREIGN KEY (company_id) REFERENCES companies(id) ON DELETE CASCADE
        )
    ");
    echo "✅ Created employee_id_settings table\n";

    // Initialize settings for existing companies that don't have settings yet
    $stmt = $pdo->query("SELECT id, name FROM companies WHERE id NOT IN (SELECT company_id FROM employee_id_settings)");
    $companies = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    if ($companies) {
        $insert = $pdo->prepare("
            INSERT INTO employee_id_settings (company_id, prefix, include_year, id_separator, number_padding, next_number, is_locked) 
            VALUES (?, ?, 1, '-', 3, 1, 0)
        ");
        
        foreach ($companies as $company) {
            // Generate a default prefix from company name (first 3 letters uppercase)
            $words = explode(' ', $company['name']);
            if (count($words) >= 2) {
                // Use initials if multiple words
                $prefix = strtoupper(substr($words[0], 0, 1) . substr($words[1], 0, 1) . (isset($words[2]) ? substr($words[2], 0, 1) : 'P'));
            } else {
                // Use first 3 letters if single word
                $prefix = strtoupper(substr($company['name'], 0, 3));
            }
            
            $insert->execute([$company['id'], $prefix]);
            echo "  ✅ Initialized settings for: {$company['name']} (Prefix: $prefix)\n";
        }
    } else {
        echo "  ℹ️ All existing companies already have ID settings\n";
    }

    echo "\n✅ Migration completed successfully!\n";

} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
}
