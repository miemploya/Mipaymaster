<?php
require_once 'includes/functions.php';

try {
    echo "Adding interest columns to loans table...\n";
    
    // Add interest_rate
    try {
        $pdo->exec("ALTER TABLE loans ADD COLUMN interest_rate DECIMAL(5,2) DEFAULT 0.00 AFTER principal_amount");
        echo "Added 'interest_rate'.\n";
    } catch (Exception $e) { echo "interest_rate might exist: " . $e->getMessage() . "\n"; }

    // Add interest_amount
    try {
        $pdo->exec("ALTER TABLE loans ADD COLUMN interest_amount DECIMAL(15,2) DEFAULT 0.00 AFTER interest_rate");
        echo "Added 'interest_amount'.\n";
    } catch (Exception $e) { echo "interest_amount might exist: " . $e->getMessage() . "\n"; }

    echo "Migration Complete.";

} catch (Exception $e) {
    echo "Fatal Error: " . $e->getMessage();
}
?>
