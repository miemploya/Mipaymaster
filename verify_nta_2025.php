<?php
// verify_nta_2025.php
// Unit tests for calculate_paye function in includes/payroll_engine.php

require_once 'includes/payroll_engine.php';

function assert_tax($year, $gross, $reliefs, $expected_tax, $desc) {
    // calculate_paye($gross, $pension, $nhf, $nhis, $year)
    // We treat reliefs as a lump sum here for simplicity, or split them.
    // The function expects: calculate_paye($gross_annual, $pension_annual, $nhf_annual, $nhis_annual, $year)
    
    // For NTA 2025, total reliefs = pension + nhf + nhis.
    // So we can just pass reliefs as pension and 0 for others to match total.
    $actual = calculate_paye($gross, $reliefs, 0, 0, $year);
    
    $diff = abs($actual - $expected_tax);
    $pass = $diff < 0.01; // FP tolerance
    
    echo $pass ? "[PASS] " : "[FAIL] ";
    echo "$desc (Year: $year, Gross: " . number_format($gross) . ", Taxable/Reliefs: " . number_format($gross-$reliefs) . "/" . number_format($reliefs) . ")\n";
    echo "       Expected: " . number_format($expected_tax, 2) . " | Actual: " . number_format($actual, 2) . "\n";
    echo $pass ? "" : "       Diff: " . number_format($diff, 2) . "\n";
    echo "---------------------------------------------------\n";
}

echo "=== VERIFYING NTA 2025 PAYE LOGIC ===\n\n";

// TEST 1: Exemption (Taxable Income <= 800,000)
// Gross 800k, 0 Reliefs -> Taxable 800k. Tax should be 0.
assert_tax(2026, 800000, 0, 0, "Exemption Threshold (800k Taxable)");

// TEST 2: Just Above Exemption
// Gross 1,000,000. Reliefs 0. Taxable 1,000,000.
// 0-800k @ 0% = 0
// 800k-1m (200k) @ 15% = 30,000
assert_tax(2026, 1000000, 0, 30000, "1M Taxable (Just above exemption)");

// TEST 3: Mid Range
// Gross 5,000,000. Reliefs 0. Taxable 5,000,000.
// 0-800k @ 0% = 0
// 800k-3m (2.2m) @ 15% = 330,000
// 3m-5m (2m) @ 18% = 360,000
// Total = 690,000
assert_tax(2026, 5000000, 0, 690000, "5M Taxable (Band 3)");

// TEST 4: High Income
// Gross 60,000,000. Reliefs 0. Taxable 60,000,000.
// 0-800k @ 0% = 0
// 800k-3m (2.2m) @ 15% = 330,000
// 3m-12m (9m) @ 18% = 1,620,000
// 12m-25m (13m) @ 21% = 2,730,000
// 25m-50m (25m) @ 23% = 5,750,000
// 50m-60m (10m) @ 25% = 2,500,000
// Total = 12,930,000
assert_tax(2026, 60000000, 0, 12930000, "60M Taxable (Top Band)");


echo "\n=== VERIFYING LEGACY (2025) LOGIC ===\n\n";

// TEST 5: Legacy Logic (includes CRA calculation internally)
// For legacy, calculate_paye calculates CRA internally based on Gross.
// calculate_paye($gross, $pension, $nhf, $nhis)
// Gross 1,000,000. Pension 0.
// CRA Fixed = 200k. CRA 1% = 10k. Max = 200k.
// CRA 20% = 200k. Total CRA = 400k.
// Reliefs input = 0. Total Reliefs = 400k.
// Chargeable = 1m - 400k = 600k.
// Band 1: 300k @ 7% = 21,000
// Band 2: 300k @ 11% = 33,000
// Total Tax = 54,000.
assert_tax(2025, 1000000, 0, 54000, "Legacy 1M Gross (Auto CRA)");
?>
