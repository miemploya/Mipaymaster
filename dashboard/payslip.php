<?php
require_once '../includes/functions.php';
require_login();

$id = $_GET['id'] ?? 0;

// Fetch Payslip Data joined with Employee and Company
$stmt = $pdo->prepare("SELECT p.*, e.first_name, e.last_name, e.payroll_id, e.bank_name, e.account_number,
                       c.name as company_name, c.address as company_address,
                       r.period_month as month, r.period_year as year
                       FROM payroll_entries p 
                       JOIN employees e ON p.employee_id = e.id
                       JOIN payroll_runs r ON p.payroll_run_id = r.id
                       JOIN companies c ON r.company_id = c.id
                       WHERE p.id = ? AND r.company_id = ?");
$stmt->execute([$id, $_SESSION['company_id']]);
$ps = $stmt->fetch();

if (!$ps) die("Payslip not found or access denied.");
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Payslip - <?php echo $ps['first_name']; ?></title>
    <link rel="stylesheet" href="../assets/css/style.css">
    <style>
        body { background: #f1f5f9; padding: 2rem; }
        .payslip-container {
            max-width: 800px;
            margin: 0 auto;
            background: white;
            padding: 3rem;
            border-radius: 1rem;
            box-shadow: 0 4px 6px -1px rgb(0 0 0 / 0.1);
        }
        .header { text-align: center; border-bottom: 2px solid #e2e8f0; padding-bottom: 2rem; margin-bottom: 2rem; }
        .company-name { font-size: 1.5rem; font-weight: 700; color: var(--primary-color); }
        .payslip-title { font-size: 1.25rem; font-weight: 600; margin-top: 1rem; }
        
        .grid-2 { display: grid; grid-template-columns: 1fr 1fr; gap: 2rem; margin-bottom: 2rem; }
        .info-group label { display: block; font-size: 0.8rem; text-transform: uppercase; color: #64748b; margin-bottom: 0.25rem; }
        .info-group div { font-weight: 500; font-size: 1.1rem; }

        .earnings-table { width: 100%; border-collapse: collapse; margin-bottom: 2rem; }
        .earnings-table th { text-align: left; padding: 0.75rem; background: #f8fafc; border-bottom: 1px solid #e2e8f0; }
        .earnings-table td { padding: 0.75rem; border-bottom: 1px solid #f1f5f9; }
        .text-right { text-align: right; }
        
        .total-row { font-weight: 700; background: #f8fafc; }
        .net-pay-section { background: #f0fdf4; padding: 1.5rem; border-radius: 0.5rem; border: 1px solid #bbf7d0; text-align: center; margin-top: 2rem; }
        .net-pay-label { color: #166534; font-size: 0.9rem; text-transform: uppercase; letter-spacing: 0.1em; }
        .net-pay-amount { color: #166534; font-size: 2rem; font-weight: 700; }

        @media print {
            body { background: white; padding: 0; }
            .payslip-container { box-shadow: none; max-width: 100%; padding: 0; }
            .btn-print { display: none; }
        }
    </style>
</head>
<body>


    <div class="payslip-container">
        
        <button onclick="window.print()" class="btn btn-primary btn-print" style="position:fixed; top:20px; right:20px;">Print / Download PDF</button>

        <header class="header">
            <div class="company-name"><?php echo htmlspecialchars($ps['company_name']); ?></div>
            <div style="color: #64748b; font-size: 0.9rem;"><?php echo htmlspecialchars($ps['company_address']); ?></div>
            <div class="payslip-title">Payslip: <?php echo date('F Y', mktime(0, 0, 0, $ps['month'], 10, $ps['year'])); ?></div>
        </header>

        <div class="grid-2">
            <div>
                <div class="info-group mb-4">
                    <label>Employee Name</label>
                    <div><?php echo htmlspecialchars($ps['first_name'] . ' ' . $ps['last_name']); ?></div>
                </div>
                <div class="info-group">
                    <label>Employee ID</label>
                    <div><?php echo htmlspecialchars($ps['payroll_id']); ?></div>
                </div>
            </div>
            <div>
                <div class="info-group mb-4">
                    <label>Bank Name</label>
                    <div><?php echo htmlspecialchars($ps['bank_name'] ?? 'N/A'); ?></div>
                </div>
                <div class="info-group">
                    <label>Account Number</label>
                    <div><?php echo htmlspecialchars($ps['account_number'] ?? 'N/A'); ?></div>
                </div>
            </div>
        </div>

        <?php 
        // Fetch snapshot data for this payroll entry
        $stmt_snap = $pdo->prepare("SELECT snapshot_json FROM payroll_snapshots WHERE payroll_entry_id = ?");
        $stmt_snap->execute([$ps['id']]);
        $snapshot_row = $stmt_snap->fetch();
        $snapshot = $snapshot_row ? json_decode($snapshot_row['snapshot_json'], true) : [];
        
        // Extract breakdown from snapshot
        $breakdown = $snapshot['breakdown'] ?? [];
        $statutory = $snapshot['statutory'] ?? [];
        
        // Extract Earnings from breakdown
        $basic = $breakdown['Basic Salary'] ?? 0;
        $housing = $breakdown['Housing Allowance'] ?? 0;
        $transport = $breakdown['Transport Allowance'] ?? 0;
        $other = 0;
        foreach ($breakdown as $key => $val) {
            if (!in_array($key, ['Basic Salary', 'Housing Allowance', 'Transport Allowance'])) {
                $other += $val;
            }
        }
        $overtime = $breakdown['overtime'] ?? 0;
        
        // Extract Deductions from statutory
        $paye = $statutory['paye'] ?? 0;
        $pension = $statutory['pension_employee'] ?? 0;
        $nhis = $statutory['nhis'] ?? 0;
        $nhf = $statutory['nhf'] ?? 0;
        
        // Loan deductions
        $loans = $snapshot['loans'] ?? [];
        $total_loan = 0;
        foreach ($loans as $loan) {
            $total_loan += floatval($loan['amount'] ?? 0);
        }
        
        // Attendance/Lateness deductions
        $attendance = $snapshot['attendance'] ?? [];
        $lateness_deduction = floatval($attendance['deduction'] ?? 0);
        ?>

        <!-- Tables -->
        <div class="flex gap-4" style="align-items: flex-start; display:flex;">
            <!-- Earnings -->
            <div style="flex:1;">
                <h4 style="margin-bottom: 0.5rem; color: #64748b;">Earnings</h4>
                <table class="earnings-table">
                    <tr>
                        <td>Basic Salary</td>
                        <td class="text-right">₦<?php echo number_format($basic, 2); ?></td>
                    </tr>
                    <tr>
                        <td>Housing Allowance</td>
                        <td class="text-right">₦<?php echo number_format($housing, 2); ?></td>
                    </tr>
                    <tr>
                        <td>Transport Allowance</td>
                        <td class="text-right">₦<?php echo number_format($transport, 2); ?></td>
                    </tr>
                    <?php if($other > 0): ?>
                    <tr>
                        <td>Other Allowances</td>
                        <td class="text-right">₦<?php echo number_format($other, 2); ?></td>
                    </tr>
                    <?php endif; ?>
                    <?php if($overtime > 0): ?>
                    <tr>
                        <td>Overtime</td>
                        <td class="text-right">₦<?php echo number_format($overtime, 2); ?></td>
                    </tr>
                    <?php endif; ?>
                    <tr class="total-row">
                        <td>Total Gross</td>
                        <td class="text-right">₦<?php echo number_format($ps['gross_salary'], 2); ?></td>
                    </tr>
                </table>
            </div>

            <!-- Deductions -->
            <div style="flex:1;">
                <h4 style="margin-bottom: 0.5rem; color: #64748b;">Deductions</h4>
                <table class="earnings-table">
                    <tr>
                        <td>PAYE Tax</td>
                        <td class="text-right">₦<?php echo number_format($paye, 2); ?></td>
                    </tr>
                    <tr>
                        <td>Pension (8%)</td>
                        <td class="text-right">₦<?php echo number_format($pension, 2); ?></td>
                    </tr>
                    <?php if($nhis > 0): ?>
                    <tr>
                        <td>NHIS</td>
                        <td class="text-right">₦<?php echo number_format($nhis, 2); ?></td>
                    </tr>
                    <?php endif; ?>
                    <?php if($nhf > 0): ?>
                    <tr>
                        <td>NHF</td>
                        <td class="text-right">₦<?php echo number_format($nhf, 2); ?></td>
                    </tr>
                    <?php endif; ?>
                    <?php if($total_loan > 0): ?>
                    <tr>
                        <td>Loan Repayment</td>
                        <td class="text-right">₦<?php echo number_format($total_loan, 2); ?></td>
                    </tr>
                    <?php endif; ?>
                    <?php if($lateness_deduction > 0): ?>
                    <tr style="background: #fef3c7;">
                        <td>Lateness Deduction</td>
                        <td class="text-right" style="color: #d97706;">₦<?php echo number_format($lateness_deduction, 2); ?></td>
                    </tr>
                    <?php endif; ?>
                    <tr class="total-row" style="background: #fff1f2;">
                        <td>Total Deductions</td>
                        <td class="text-right" style="color: #be123c;">- ₦<?php echo number_format($ps['total_deductions'], 2); ?></td>
                    </tr>
                </table>
            </div>
        </div>

        <div class="net-pay-section">
            <div class="net-pay-label">Net Pay</div>
            <div class="net-pay-amount">₦<?php echo number_format($ps['net_pay'], 2); ?></div>
        </div>

        <div style="text-align: center; margin-top: 3rem; color: #cbd5e1; font-size: 0.8rem;">
            Generated by MiPayMaster System on <?php echo date('d M Y'); ?>
        </div>

    </div>

</body>
</html>
