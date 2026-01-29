<?php
/**
 * Test Employee ID Generation System
 */

require_once 'includes/functions.php';

echo "=== Employee ID Generation Test ===\n\n";

$company_id = 2; // Openclax Limited

echo "Company ID: $company_id\n\n";

// 1. Get current settings
echo "--- Current Settings ---\n";
$settings = get_employee_id_settings($company_id);
print_r($settings);

// 2. Get ID preview (without incrementing)
echo "\n--- Next 3 IDs Preview ---\n";
$previews = get_employee_id_preview($company_id, 3);
foreach ($previews as $i => $id) {
    echo ($i + 1) . ". $id\n";
}

// 3. Generate one ID (with increment)
echo "\n--- Generate One ID (with increment) ---\n";
$new_id = generate_employee_id($company_id, true);
echo "Generated: $new_id\n";

// 4. Show new settings after increment
echo "\n--- Settings After Increment ---\n";
$settings_after = get_employee_id_settings($company_id);
echo "Next Number is now: " . $settings_after['next_number'] . "\n";

echo "\n✅ Test completed!\n";
