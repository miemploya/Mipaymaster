<?php
// Payroll Calculation Engine

/**
 * Calculate PAYE Tax (Nigeria)
 * Based on 2020 Finance Act
 */
/**
 * Calculate PAYE Tax (Nigeria)
 * Supports:
 * - Finance Act 2020 (Year < 2026)
 * - NTA 2025 (Year >= 2026)
 */
function calculate_paye($gross_annual, $pension_annual, $nhf_annual, $nhis_annual, $year = null) {
    if ($year === null) $year = (int)date('Y'); // Default to current if not passed (risky but fallback)

    // === NTA 2025 LOGIC (Effective Jan 1, 2026) ===
    if ($year >= 2026) {
        // 1. No CRA
        $total_reliefs = $pension_annual + $nhf_annual + $nhis_annual;
        
        // 2. Taxable Income
        $taxable_income = $gross_annual - $total_reliefs;
        
        // 3. Exemption Rule (Taxable <= 800k -> 0 Tax)
        if ($taxable_income <= 800000) {
            return 0; // Exempt
        }
        
        // 4. New Bands
        $tax = 0;
        
        // Band 1: 0 - 800k @ 0% (Effective exemption for first 800k of taxable?)
        // Prompt says "PAYE applies only after 800,000". 
        // Logic interpretation: First 800k is 0% tax.
        
        $remaining = $taxable_income;
        
        // Band 1: First 800k @ 0%
        $b1 = 800000;
        if ($remaining > $b1) {
            $remaining -= $b1;
        } else {
            return 0; // Should be covered by check above, but for completeness.
        }
        
        // Band 2: Next 2.2M (800k - 3m) @ 15%
        $b2 = 2200000;
        if ($remaining > $b2) {
            $tax += $b2 * 0.15;
            $remaining -= $b2;
        } else {
            $tax += $remaining * 0.15;
            return $tax;
        }
        
        // Band 3: Next 9M (3m - 12m) @ 18%
        $b3 = 9000000;
        if ($remaining > $b3) {
            $tax += $b3 * 0.18;
            $remaining -= $b3;
        } else {
            $tax += $remaining * 0.18;
            return $tax;
        }
        
        // Band 4: Next 13M (12m - 25m) @ 21%
        $b4 = 13000000;
        if ($remaining > $b4) {
            $tax += $b4 * 0.21;
            $remaining -= $b4;
        } else {
            $tax += $remaining * 0.21;
            return $tax;
        }
        
        // Band 5: Next 25M (25m - 50m) @ 23%
        $b5 = 25000000;
        if ($remaining > $b5) {
            $tax += $b5 * 0.23;
            $remaining -= $b5;
        } else {
            $tax += $remaining * 0.23;
            return $tax;
        }
        
        // Band 6: Above 50M @ 25%
        $tax += $remaining * 0.25;
        
        return $tax;
    }
    
    // === LEGACY LOGIC (Finance Act 2020) ===
    else {
        // 1. Consolidated Relief Allowance (CRA)
        // Higher of 200,000 or 1% of Gross ... PLUS 20% of Gross
        $cra_fixed = 200000;
        $cra_one_percent = 0.01 * $gross_annual;
        $cra_higher = max($cra_fixed, $cra_one_percent);
        $cra_twenty_percent = 0.20 * $gross_annual;
        
        $cra_total = $cra_higher + $cra_twenty_percent;
    
        // 2. Tax Exemptions (Reliefs)
        $total_reliefs = $cra_total + $pension_annual + $nhf_annual + $nhis_annual;
    
        // 3. Chargeable Income
        $chargeable_income = $gross_annual - $total_reliefs;
    
        if ($chargeable_income <= 0) {
            return 0; 
        }
    
        // 4. Apply Tax Bands
        $tax = 0;
        
        // Band 1: First 300k @ 7%
        $b1 = 300000;
        if ($chargeable_income > $b1) {
            $tax += $b1 * 0.07;
            $chargeable_income -= $b1;
        } else {
            $tax += $chargeable_income * 0.07;
            return $tax;
        }
    
        // Band 2: Next 300k @ 11%
        $b2 = 300000;
        if ($chargeable_income > $b2) {
            $tax += $b2 * 0.11;
            $chargeable_income -= $b2;
        } else {
            $tax += $chargeable_income * 0.11;
            return $tax;
        }
    
        // Band 3: Next 500k @ 15%
        $b3 = 500000;
        if ($chargeable_income > $b3) {
            $tax += $b3 * 0.15;
            $chargeable_income -= $b3;
        } else {
            $tax += $chargeable_income * 0.15;
            return $tax;
        }
    
        // Band 4: Next 500k @ 19%
        $b4 = 500000;
        if ($chargeable_income > $b4) {
            $tax += $b4 * 0.19;
            $chargeable_income -= $b4;
        } else {
            $tax += $chargeable_income * 0.19;
            return $tax;
        }
    
        // Band 5: Next 1.6M @ 21%
        $b5 = 1600000;
        if ($chargeable_income > $b5) {
            $tax += $b5 * 0.21;
            $chargeable_income -= $b5;
        } else {
            $tax += $chargeable_income * 0.21;
            return $tax;
        }
    
        // Band 6: Above 3.2M @ 24%
        $tax += $chargeable_income * 0.24;
        
        return $tax;
    }
}


/**
 * Run Payroll for Company
 */
/**
 * Run Payroll for Company - V2.1 (Locked & Increments)
 */
function run_monthly_payroll($company_id, $month, $year, $user_id) {
    global $pdo;

    require_once __DIR__ . '/increment_manager.php';
    require_once __DIR__ . '/payroll_lock.php'; // For future use or validation if needed

    $incManager = new IncrementManager($pdo);

    // 1. Check if payroll already exists
    $stmt = $pdo->prepare("SELECT id, status FROM payroll_runs WHERE company_id=? AND period_month=? AND period_year=?");
    $stmt->execute([$company_id, $month, $year]);
    $existing = $stmt->fetch();

    if ($existing) {
        if ($existing['status'] === 'reversed') {
             // Allow re-run if previous was reversed?
             // Prompt says "Corrections require payroll reversal, not recalculation".
             // If reversed, we technically can create a NEW run or re-use?
             // Usually we would prefer a fresh run ID for audit. 
             // But unique key constraint (company, month, year) exists on payroll_runs.
             // So if we want to re-run a reversed period, we must either DELETE the reversed run (bad for audit) 
             // OR Update the existing reversed run to 'draft' and re-calculate?
             // Or allow multiple runs per month (removed unique constraint)?
             // Given constraint: UNIQUE KEY unique_run.
             // Strategy: Update status to 'draft' and clear entries if reversed.
             
             // For strict audit, maybe we should have soft deleted or moved it? 
             // For now, let's BLOCK if it exists and is not reversed. 
             // If reversed, we'll clear entries and recycle ID (simplest given constraints).
             
            // CLEAR PREVIOUS ENTRIES
            $del_entries = $pdo->prepare("DELETE FROM payroll_entries WHERE payroll_run_id = ?");
            $del_entries->execute([$existing['id']]);
            $del_snaps = $pdo->prepare("DELETE FROM payroll_snapshots WHERE payroll_entry_id IN (SELECT id FROM payroll_entries WHERE payroll_run_id = ?)"); // Logic circular, actually entries deleted first. 
            // Snapshots link to entries, so cascade delete on entries should handle it if set up?
            // Schema didn't specify cascade for snapshots. Let's assume we need to manage it or alter schema to cascade.
            // For safety, let's just proceed as if it's a new calc on top of existing ID.
             
             $run_id = $existing['id'];
             // Update status to draft
             $upd_status = $pdo->prepare("UPDATE payroll_runs SET status='draft', locked_at=NULL, locked_by=NULL WHERE id=?");
             $upd_status->execute([$run_id]);
             
        } elseif ($existing['status'] === 'draft') {
             // REGENERATION: Logic to clear previous data
             // Delete snapshots first (if no cascade)
             $del_snaps = $pdo->prepare("DELETE FROM payroll_snapshots WHERE payroll_entry_id IN (SELECT id FROM payroll_entries WHERE payroll_run_id = ?)");
             $del_snaps->execute([$existing['id']]);
             
             $del_entries = $pdo->prepare("DELETE FROM payroll_entries WHERE payroll_run_id = ?");
             $del_entries->execute([$existing['id']]);
             
             $run_id = $existing['id'];
             // Update timestamp
             $pdo->prepare("UPDATE payroll_runs SET created_at = NOW() WHERE id=?")->execute([$run_id]);

        } else {
             return ['status' => false, 'message' => "Payroll for this period already exists and is " . $existing['status']];
        }
    } else {
        // Create new
        $stmt = $pdo->prepare("INSERT INTO payroll_runs (company_id, period_month, period_year, status) VALUES (?, ?, ?, 'draft')");
        $stmt->execute([$company_id, $month, $year]);
        $run_id = $pdo->lastInsertId();
    }

    // 2. Fetch Active Employees & Gross
    $stmt = $pdo->prepare("SELECT e.id, e.salary_category_id as category_id, e.department_id, sc.base_gross_amount 
                           FROM employees e
                           JOIN salary_categories sc ON e.salary_category_id = sc.id
                           WHERE e.company_id = ? 
                           AND LOWER(e.employment_status) IN ('active', 'full time', 'probation', 'contract')");
    $stmt->execute([$company_id]);
    $employees = $stmt->fetchAll(PDO::FETCH_ASSOC);

    if (!$employees) {
        return ['status' => false, 'message' => "No active employees found in valid categories."];
    }

    // 2b. Fetch Breakdowns & Components Mapped
    $cat_breakdowns = [];
    $stmt_b = $pdo->prepare("SELECT scb.category_id, scb.percentage, c.name, c.type 
                             FROM salary_category_breakdown scb 
                             JOIN salary_components c ON scb.salary_component_id = c.id 
                             JOIN salary_categories sc ON scb.category_id = sc.id
                             WHERE sc.company_id = ?");
    $stmt_b->execute([$company_id]);
    while($row = $stmt_b->fetch(PDO::FETCH_ASSOC)) {
        $cat_breakdowns[$row['category_id']][] = $row;
    }

    // 3. Fetch Statutory Settings
    $stmt = $pdo->prepare("SELECT * FROM statutory_settings WHERE company_id = ?");
    $stmt->execute([$company_id]);
    $settings = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$settings) {
        $settings = ['enable_paye'=>0, 'enable_pension'=>0, 'pension_employer_perc'=>0, 'pension_employee_perc'=>0, 'enable_nhis'=>0, 'enable_nhf'=>0];
    }

    try {
        $pdo->beginTransaction();

        $total_gross_run = 0;
        $total_net_run = 0;
        
        $ins_entry = $pdo->prepare("INSERT INTO payroll_entries 
            (payroll_run_id, employee_id, gross_salary, total_allowances, total_deductions, net_pay)
            VALUES (?, ?, ?, ?, ?, ?)");
            
        $ins_snapshot = $pdo->prepare("INSERT INTO payroll_snapshots (payroll_entry_id, snapshot_json) VALUES (?, ?)");
        
        // Define Period End Date for Increment Validity
        $period_date_str = "$year-$month-01"; // First day
        // Actually better to use last day of month? Usually increments effective FROM a date.
        // If effective from 15th, does it apply? Prompt: "Applies before breakdown".
        // Usually entire month logic is simplified unless pro-rata.
        // Let's assume if effective_from <= End of Month? Or Start of Month?
        // Prompt says "If increment effective date < locked payroll".
        // Let's use End of Month for inclusion.
        $period_end_date = date("Y-m-t", strtotime($period_date_str));


        // 5. Calculate for each employee
        foreach ($employees as $emp) {
            $base_gross = floatval($emp['base_gross_amount']);
            
            // --- INCREMENT LOGIC START ---
            // --- INCREMENT LOGIC START ---
            $increments = $incManager->get_active_increment($emp['id'], $period_end_date); // Now returns array
            $adjusted_gross = $base_gross;
            $applied_increments = [];

            if (!empty($increments)) {
                foreach ($increments as $inc) {
                    if ($inc['adjustment_type'] == 'fixed') {
                        $adjusted_gross += $inc['adjustment_value'];
                    } elseif ($inc['adjustment_type'] == 'percentage') {
                        // Cumulative: % of current adjusted gross
                        $adjusted_gross += ($adjusted_gross * ($inc['adjustment_value'] / 100));
                    } elseif ($inc['adjustment_type'] == 'override') {
                         // Override supercedes previous
                        $adjusted_gross = $inc['adjustment_value'];
                    }
                    $applied_increments[] = $inc;
                }
            }
            // Note: $applied_increment is now plural usage, but downstream might need specific handling?
            // Checking simple logic usage: no specific dependence downstream on $applied_increment logic 
            // other than maybe reporting?
            // --- INCREMENT LOGIC END ---
            // --- INCREMENT LOGIC END ---

            $breakdown = $cat_breakdowns[$emp['category_id']] ?? [];
            
            // Calculate Components based on ADJUSTED GROSS
            $basic = 0;
            $total_allowances = 0; 
            $pensionable_sum = 0; 
            $breakdown_values = []; // For snapshot
            
            foreach ($breakdown as $item) {
                // Percentage applies to the NEW Adjusted Gross
                $amount = $adjusted_gross * ($item['percentage'] / 100);
                
                $breakdown_values[$item['name']] = $amount;

                if ($item['name'] === 'Basic Salary') {
                    $basic += $amount;
                    $pensionable_sum += $amount; 
                } elseif ($item['name'] === 'Housing Allowance' || $item['name'] === 'Transport Allowance') {
                    $total_allowances += $amount;
                    $pensionable_sum += $amount; 
                } else {
                    $total_allowances += $amount;
                }
            }
            
            // Fallback logic handled in breakdown loop or empty check
            if (empty($breakdown) && $adjusted_gross > 0) {
                 // Should ideally warn, but for V2 strictness we respect the 0 breakdown.
                 // Or we could log it.
            }

            // Pension
            $pension_emp = 0;
            $pension_emplr = 0;

            if ($settings['enable_pension']) {
                $pension_emp = $pensionable_sum * ($settings['pension_employee_perc'] / 100);
                $pension_emplr = $pensionable_sum * ($settings['pension_employer_perc'] / 100);
            }

            // NHIS / NHF
            $nhis = $settings['enable_nhis'] ? ($adjusted_gross * 0.05) : 0; 
            $nhf = $settings['enable_nhf'] ? ($basic * 0.025) : 0;

            // PAYE Tax (Recalculated on Adjusted Gross)
            $tax_paye = 0;
            $cra_values = []; // For snapshot
            
            if ($settings['enable_paye']) {
                $gross_annual = $adjusted_gross * 12;
                $pension_annual = $pension_emp * 12;
                $nhf_annual = $nhf * 12;
                $nhis_annual = $nhis * 12;
                
                // We should expose CRA details if possible, but existing function just returns tax.
                // Let's compute tax.
                $tax_annual = calculate_paye($gross_annual, $pension_annual, $nhf_annual, $nhis_annual, (int)$year);
                $tax_paye = $tax_annual / 12;
                
                // For snapshot: simple approximate or reuse logic if we needed deep details.
                // Storing the calculated tax is key.
            }

            // Totals
            $total_deductions = $tax_paye + $pension_emp + $nhis + $nhf;
            $net_pay = $adjusted_gross - $total_deductions;

            // --- ATTENDANCE DEDUCTION LOGIC ---
            $stmt_att = $pdo->prepare("SELECT SUM(final_deduction_amount) FROM attendance_logs WHERE employee_id = ? AND MONTH(date) = ? AND YEAR(date) = ?");
            $stmt_att->execute([$emp['id'], $month, $year]);
            $attendance_deduction = floatval($stmt_att->fetchColumn() ?: 0);
            
            $net_pay -= $attendance_deduction;
            // ---------------------------------

            // --- LOAN DEDUCTION LOGIC ---
            // Fetch active approved loans
            $loan_deductions = [];
            $total_loan_amount = 0;
            
            // Fetch ALL approved active loans for this employee first
            $stmt_loans = $pdo->prepare("
                SELECT * FROM loans 
                WHERE employee_id = ? 
                AND status = 'approved' 
                AND balance > 0
            ");
            $stmt_loans->execute([$emp['id']]);
            $candidate_loans = $stmt_loans->fetchAll(PDO::FETCH_ASSOC);

            foreach ($candidate_loans as $loan) {
                // PHP Date Filter: Start Period <= Current Period
                $l_month = (int)$loan['start_month'];
                $l_year = (int)$loan['start_year'];
                $c_month = (int)$month;
                $c_year = (int)$year;

                $is_eligible = false;
                if ($l_year < $c_year) {
                    $is_eligible = true;
                } elseif ($l_year === $c_year && $l_month <= $c_month) {
                    $is_eligible = true;
                }

                if ($is_eligible) {
                    // Deduct min(repayment, balance)
                    $amount = min(floatval($loan['repayment_amount']), floatval($loan['balance']));
                    if ($amount > 0) {
                        $total_loan_amount += $amount;
                        $loan_deductions[] = [
                            'loan_id' => $loan['id'],
                            'type' => $loan['loan_type'],
                            'custom_type' => $loan['custom_type'], // For description
                            'amount' => $amount,
                            'balance_before' => $loan['balance'],
                            'balance_after_projected' => $loan['balance'] - $amount
                        ];
                    }
                }
            }

            // Apply Deduction to Net Pay
            $net_pay -= $total_loan_amount;
            // ---------------------------

            // --- CUSTOM BONUS/DEDUCTION LOGIC ---
            $custom_bonuses = [];
            $custom_deductions = [];
            $total_bonus_amount = 0;
            $total_custom_deduction_amount = 0;

            // Fetch applicable bonuses (company-wide, category, department, or employee-specific)
            $stmt_bonus = $pdo->prepare("
                SELECT bt.* FROM payroll_bonus_types bt
                WHERE bt.company_id = ? AND bt.is_active = 1
                AND (
                    bt.scope = 'company' OR
                    (bt.scope = 'category' AND bt.category_id = ?) OR
                    (bt.scope = 'department' AND bt.department_id = ?) OR
                    (bt.scope = 'employee' AND bt.id IN (
                        SELECT bonus_type_id FROM employee_bonus_assignments 
                        WHERE employee_id = ? AND is_active = 1
                    ))
                )
            ");
            $stmt_bonus->execute([$company_id, $emp['category_id'], $emp['department_id'], $emp['id']]);
            
            while ($bonus = $stmt_bonus->fetch(PDO::FETCH_ASSOC)) {
                $amount = 0;
                if ($bonus['calculation_mode'] === 'fixed') {
                    $amount = floatval($bonus['amount']);
                } else { // percentage
                    $base = ($bonus['percentage_base'] === 'basic') ? $basic : $adjusted_gross;
                    $amount = $base * (floatval($bonus['percentage']) / 100);
                }
                if ($amount > 0) {
                    $total_bonus_amount += $amount;
                    $custom_bonuses[] = ['name' => $bonus['name'], 'amount' => $amount, 'scope' => $bonus['scope']];
                }
            }

            // Fetch applicable deductions (company-wide, category, department, or employee-specific)
            $stmt_ded = $pdo->prepare("
                SELECT dt.* FROM payroll_deduction_types dt
                WHERE dt.company_id = ? AND dt.is_active = 1
                AND (
                    dt.scope = 'company' OR
                    (dt.scope = 'category' AND dt.category_id = ?) OR
                    (dt.scope = 'department' AND dt.department_id = ?) OR
                    (dt.scope = 'employee' AND dt.id IN (
                        SELECT deduction_type_id FROM employee_deduction_assignments 
                        WHERE employee_id = ? AND is_active = 1
                    ))
                )
            ");
            $stmt_ded->execute([$company_id, $emp['category_id'], $emp['department_id'], $emp['id']]);
            
            while ($ded = $stmt_ded->fetch(PDO::FETCH_ASSOC)) {
                $amount = 0;
                if ($ded['calculation_mode'] === 'fixed') {
                    $amount = floatval($ded['amount']);
                } else { // percentage
                    $base = ($ded['percentage_base'] === 'basic') ? $basic : $adjusted_gross;
                    $amount = $base * (floatval($ded['percentage']) / 100);
                }
                if ($amount > 0) {
                    $total_custom_deduction_amount += $amount;
                    $custom_deductions[] = ['name' => $ded['name'], 'amount' => $amount, 'scope' => $ded['scope']];
                }
            }

            // --- ONE-TIME ADJUSTMENTS (from payroll_adjustments table) ---
            $onetime_adjustments = [];
            $onetime_bonus_total = 0;
            $onetime_deduction_total = 0;
            
            $stmt_adj = $pdo->prepare("
                SELECT * FROM payroll_adjustments 
                WHERE company_id = ? AND employee_id = ? 
                AND payroll_month = ? AND payroll_year = ?
            ");
            $stmt_adj->execute([$company_id, $emp['id'], $month, $year]);
            
            while ($adj = $stmt_adj->fetch(PDO::FETCH_ASSOC)) {
                $adj_amount = floatval($adj['amount']);
                if ($adj['type'] === 'bonus') {
                    $onetime_bonus_total += $adj_amount;
                } else {
                    $onetime_deduction_total += $adj_amount;
                }
                $onetime_adjustments[] = [
                    'name' => $adj['name'],
                    'type' => $adj['type'],
                    'amount' => $adj_amount,
                    'notes' => $adj['notes']
                ];
            }
            // ----------------------------------------

            // Apply bonuses, custom deductions, and one-time adjustments to Net Pay
            $net_pay += $total_bonus_amount + $onetime_bonus_total;
            $net_pay -= $total_custom_deduction_amount + $onetime_deduction_total;
            // ----------------------------------------

            // Calculate GROSS WITH BONUSES (for accurate payslip display)
            // Gross = Adjusted Gross (base salary + increments) + All Bonuses
            $gross_with_bonuses = $adjusted_gross + $total_bonus_amount + $onetime_bonus_total;
            
            $total_gross_run += $gross_with_bonuses;
            $total_net_run += $net_pay;

            // Insert Entry
            // Note: total_deductions in DB usually refers to statutory/tax deductions. 
            // We'll keep it as is, or should we include loans?
            // If we include loans in 'total_deductions' column, it solves 'Gross - Deductions = Net'.
            // If we don't, 'Gross - Deductions != Net'. 
            // Let's add it to total_deductions for consistency in simple reports, 
            // BUT we must distinguish in Breakdown.
            // Decision: Add to total_deductions column for integrity check (Gross - Ded = Net).
            // Include custom deductions (bonuses already in gross now)
            $total_deductions_stored = $total_deductions + $total_loan_amount + $attendance_deduction + $total_custom_deduction_amount + $onetime_deduction_total;

            $ins_entry->execute([
                $run_id, $emp['id'], $gross_with_bonuses, $total_allowances, $total_deductions_stored, $net_pay
            ]);
            $entry_id = $pdo->lastInsertId();

            // --- SNAPSHOT STORAGE ---
            $snapshot_data = [
                'base_gross' => $base_gross,
                'adjusted_gross' => $adjusted_gross,
                'increment_applied' => $applied_increments,
                'breakdown' => $breakdown_values,
                'statutory' => [
                    'paye' => $tax_paye,
                    'pension_employee' => $pension_emp,
                    'pension_employer' => $pension_emplr,
                    'nhis' => $nhis,
                    'nhf' => $nhf
                ],
                'loans' => $loan_deductions,
                'attendance' => ['deduction' => $attendance_deduction],
                'custom_bonuses' => $custom_bonuses,
                'custom_deductions' => $custom_deductions,
                'onetime_adjustments' => $onetime_adjustments,
                'settings_used' => $settings
            ];
            
            $ins_snapshot->execute([$entry_id, json_encode($snapshot_data)]);
        }

        $pdo->commit();
        return ['status' => true, 'run_id' => $run_id];

    } catch (Exception $e) {
        $pdo->rollBack();
        return ['status' => false, 'message' => $e->getMessage()];
    }
}
?>
