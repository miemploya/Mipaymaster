<?php
/**
 * Tax Report Print View
 * Receives JSON payload via POST to render a clean, printable report.
 */
require_once '../includes/functions.php';
require_login();

// 1. Capture and Decode Data
$payload = $_POST['report_data'] ?? null;
if (!$payload) {
    die("Error: No report data received. Please generate the report from the calculator first.");
}

$data = json_decode($payload, true);
if (json_last_error() !== JSON_ERROR_NONE) {
    die("Error: Invalid data format.");
}

// 2. Extract Variables
$meta = $data['meta'] ?? [];
$annual = $data['annual'] ?? [];
$monthly = $data['monthly'] ?? [];
$workings = $data['workings'] ?? [];
$structure = $data['structure'] ?? [];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>PAYE Report - <?php echo date('Y-m-d'); ?></title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&family=Noto+Serif:wght@700&display=swap" rel="stylesheet">
    <style>
        /* RESET & BASE */
        * { box-sizing: border-box; -webkit-print-color-adjust: exact !important; print-color-adjust: exact !important; }
        body { margin: 0; padding: 0; font-family: 'Inter', sans-serif; background: #525659; min-height: 100vh; display: flex; justify-content: center; }
        
        /* PAGE (A4) */
        .page {
            background: white;
            width: 210mm;
            min-height: 297mm;
            padding: 15mm;
            margin: 2rem auto;
            box-shadow: 0 0 10px rgba(0,0,0,0.5);
            position: relative;
        }

        /* PRINT MEDIA QUERY */
        @media print {
            body { background: white; margin: 0; display: block; }
            .page { width: 100%; margin: 0; box-shadow: none; padding: 10mm; }
            .no-print { display: none !important; }
        }

        /* TYPOGRAPHY & UTILS */
        h1, h2, h3, h4, p { margin: 0; }
        .text-right { text-align: right; }
        .text-center { text-align: center; }
        .font-mono { font-family: 'Courier New', monospace; letter-spacing: -0.5px; }
        .bold { font-weight: 700; }
        .uppercase { text-transform: uppercase; }
        .text-sm { font-size: 12px; }
        .text-xs { font-size: 10px; }
        .mb-2 { margin-bottom: 0.5rem; }
        .mb-4 { margin-bottom: 1rem; }
        .mb-6 { margin-bottom: 1.5rem; }
        .border-b { border-bottom: 1px solid #e2e8f0; }
        .text-slate-500 { color: #64748b; }
        .text-brand { color: #4338ca; }
        
        /* TABLES */
        table { width: 100%; border-collapse: collapse; font-size: 11px; margin-bottom: 1rem; }
        th { background: #f8fafc; text-align: left; padding: 6px; border: 1px solid #cbd5e1; font-weight: 700; text-transform: uppercase; color: #475569; }
        td { padding: 6px; border: 1px solid #e2e8f0; color: #1e293b; }
        td.num { text-align: right; font-family: 'Courier New', monospace; }
        
        /* HEADER */
        header { display: flex; justify-content: space-between; border-bottom: 3px solid #1e293b; padding-bottom: 1rem; margin-bottom: 2rem; }
        .brand h1 { font-size: 1.5rem; color: #4338ca; }
        .report-title { text-align: right; }
        .report-title h2 { font-size: 1.25rem; font-family: 'Noto Serif', serif; }
        
        /* SECTIONS */
        .section-title { font-size: 12px; font-weight: 700; text-transform: uppercase; border-bottom: 1px solid #94a3b8; padding-bottom: 4px; margin-bottom: 8px; color: #0f172a; }
        
        /* WATERMARK */
        .watermark { position: absolute; top: 50%; left: 50%; transform: translate(-50%, -50%) rotate(-45deg); font-size: 80px; color: #f1f5f9; font-weight: 900; white-space: nowrap; pointer-events: none; z-index: 0; }
        .content { position: relative; z-index: 10; }

        /* FOOTER */
        footer { position: absolute; bottom: 10mm; left: 10mm; right: 10mm; text-align: center; font-size: 9px; color: #94a3b8; border-top: 1px solid #e2e8f0; padding-top: 5px; }
    </style>
</head>
<body>

    <div class="page">
        <!-- Watermark -->
        <div class="watermark">MiPayMaster COPY</div>

        <!-- Utility Bar (Screen Only) -->
        <div class="no-print" style="position: fixed; top: 0; left: 0; width: 100%; background: #333; color: white; padding: 10px; text-align: center; z-index: 100;">
            <button onclick="window.print()" style="padding: 8px 16px; background: #4338ca; color: white; border: none; border-radius: 4px; cursor: pointer; font-weight: bold;">🖨️ Print / Save as PDF</button>
            <button onclick="window.close()" style="padding: 8px 16px; background: #64748b; color: white; border: none; border-radius: 4px; cursor: pointer; margin-left: 10px;">Close</button>
        </div>

        <div class="content">
            <!-- Header -->
            <header>
                <div class="brand">
                    <h1>MiPayMaster</h1>
                    <div class="text-xs uppercase bold text-slate-500">Tax Support Services</div>
                </div>
                <div class="report-title">
                    <h2>PAYE Computation Report</h2>
                    <div class="text-brand bold text-xs">Nigeria Tax Act (NTA) 2025</div>
                    <div class="text-xs text-slate-500 mt-1">Ref: <?php echo $meta['ref'] ?? 'N/A'; ?></div>
                    <div class="text-xs text-slate-500">Date: <?php echo date('d M, Y'); ?></div>
                </div>
            </header>

            <!-- Employee Context -->
            <section class="mb-6" style="background: #f8fafc; padding: 10px; border: 1px solid #e2e8f0; border-radius: 4px;">
                <table style="margin:0; border:none; background: transparent;">
                    <tr>
                        <td style="border:none; width: 50%;">
                            <div class="text-xs text-slate-500 uppercase bold">Employee Name</div>
                            <div class="bold" style="font-size: 14px;"><?php echo htmlspecialchars($meta['employee_name'] ?? 'N/A'); ?></div>
                            <div class="text-xs text-slate-500 mt-1">Dept: <?php echo htmlspecialchars($meta['department'] ?? 'N/A'); ?></div>
                        </td>
                        <td style="border:none; width: 50%; text-align: right;">
                            <div class="text-xs text-slate-500 uppercase bold">Payroll ID</div>
                            <div class="font-mono bold"><?php echo htmlspecialchars($meta['employee_id'] ?? 'N/A'); ?></div>
                            <div class="text-xs text-slate-500 mt-1">Period: <?php echo htmlspecialchars($meta['period'] ?? 'N/A'); ?></div>
                        </td>
                    </tr>
                </table>
            </section>

            <!-- A. Income Breakdown -->
            <section class="mb-6">
                <div class="section-title">A. Annual Gross Income Breakdown</div>
                <table>
                    <thead>
                        <tr>
                            <th>Component</th>
                            <th class="text-center">Basis</th>
                            <th class="text-center">%</th>
                            <th class="text-right">Annual (₦)</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr><td>Basic Salary</td><td class="text-center">Gross</td><td class="text-center"><?php echo $structure['basic']; ?>%</td><td class="num"><?php echo number_format($annual['basic'], 2); ?></td></tr>
                        <tr><td>Housing Allowance</td><td class="text-center">Gross</td><td class="text-center"><?php echo $structure['housing']; ?>%</td><td class="num"><?php echo number_format($annual['housing'], 2); ?></td></tr>
                        <tr><td>Transport Allowance</td><td class="text-center">Gross</td><td class="text-center"><?php echo $structure['transport']; ?>%</td><td class="num"><?php echo number_format($annual['transport'], 2); ?></td></tr>
                        <tr><td>Other Allowances</td><td class="text-center">Gross</td><td class="text-center"><?php echo $structure['other']; ?>%</td><td class="num"><?php echo number_format($annual['other'], 2); ?></td></tr>
                        <tr style="background: #f1f5f9; font-weight: bold;">
                            <td colspan="3" class="text-right">TOTAL GROSS INCOME</td>
                            <td class="num"><?php echo number_format($annual['gross'], 2); ?></td>
                        </tr>
                    </tbody>
                </table>
            </section>

            <!-- B. CRA & Statutory -->
            <section class="mb-6">
                <div class="section-title">B. Statutory Deductions & Reliefs (NTA 2025)</div>
                <table>
                    <thead>
                        <tr>
                            <th>Item</th>
                            <th>Description</th>
                            <th class="text-right">Deducted (₦)</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr>
                            <td>Pension (Employee)</td>
                            <td>8% of BHT (Standard)</td>
                            <td class="num"><?php echo number_format($annual['pensionEmp'], 2); ?></td>
                        </tr>
                        <tr>
                            <td>NHF (Housing)</td>
                            <td>2.5% of Basic</td>
                            <td class="num"><?php echo number_format($annual['nhf'], 2); ?></td>
                        </tr>
                        <tr>
                            <td>NHIS (Health)</td>
                            <td>Standard Rate</td>
                            <td class="num"><?php echo number_format($annual['nhis'], 2); ?></td>
                        </tr>
                        <tr style="color: #64748b; font-style: italic; background-color: #f8fafc;">
                            <td>Consolidated Relief (CRA)</td>
                            <td><strong>Abolished</strong> under Nigeria Tax Act (NTA) 2025</td>
                            <td class="num">0.00</td>
                        </tr>
                    </tbody>
                </table>
                
                <!-- Taxable Summary Box -->
                <div style="background-color: #e0e7ff; border: 1px solid #c7d2fe; padding: 10px; border-radius: 4px; display: flex; justify-content: space-between; align-items: center;">
                    <div class="text-sm bold text-brand uppercase">Taxable Income</div>
                    <div class="font-mono bold" style="font-size: 14px;">
                        <?php if($workings['isExempt']): ?>
                            <span style="color: green;">EXEMPT (≤ ₦800k)</span>
                        <?php else: ?>
                            ₦<?php echo number_format($annual['taxable'], 2); ?>
                        <?php endif; ?>
                    </div>
                </div>
            </section>

            <!-- C. PAYE Bands -->
            <?php if(!$workings['isExempt']): ?>
            <section class="mb-6">
                <div class="section-title">C. PAYE Tax Bands Application</div>
                <table>
                    <thead>
                        <tr>
                            <th>Income Range (₦)</th>
                            <th class="text-center">Rate</th>
                            <th class="text-right">Taxable Amount</th>
                            <th class="text-right">Tax Payable</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach($workings['bandDetails'] as $band): ?>
                        <tr>
                            <td><?php echo htmlspecialchars($band['range']); ?></td>
                            <td class="text-center"><?php echo $band['rate']; ?>%</td>
                            <td class="num"><?php echo number_format($band['taxable'], 2); ?></td>
                            <td class="num bold"><?php echo number_format($band['tax'], 2); ?></td>
                        </tr>
                        <?php endforeach; ?>
                        <tr style="background: #1e293b; color: white;">
                            <td colspan="3" class="text-right bold" style="color: white !important;">TOTAL ANNUAL PAYE</td>
                            <td class="num bold" style="color: white !important;"><?php echo number_format($annual['tax'], 2); ?></td>
                        </tr>
                    </tbody>
                </table>
            </section>
            <?php else: ?>
             <section class="mb-6">
                <div style="padding: 20px; border: 2px dashed #22c55e; background: #f0fdf4; color: #15803d; text-align: center; border-radius: 8px;">
                    <h3 style="margin-bottom: 5px;">PAYE EXEMPT</h3>
                    <p class="text-sm">Taxable income is ₦800,000 or less. No tax is payable.</p>
                </div>
             </section>
            <?php endif; ?>

            <!-- D. Final Summary -->
            <section>
                <div class="section-title">D. Final Pay Summary</div>
                <table style="border: 2px solid #0f172a;">
                    <tr style="background: #f8fafc;">
                        <td class="bold">Description</td>
                        <td class="text-right bold">Monthly (₦)</td>
                        <td class="text-right bold">Annual (₦)</td>
                    </tr>
                    <tr>
                        <td>Gross Income</td>
                        <td class="num"><?php echo number_format($monthly['gross'], 2); ?></td>
                        <td class="num"><?php echo number_format($annual['gross'], 2); ?></td>
                    </tr>
                    <tr>
                        <td class="text-slate-500">Total Deductions</td>
                        <td class="num text-slate-500">-<?php echo number_format($monthly['deductions'], 2); ?></td>
                        <td class="num text-slate-500">-<?php echo number_format($annual['deductions'], 2); ?></td>
                    </tr>
                    <tr style="font-size: 14px; background: #0f172a; color: white;">
                        <td class="bold" style="color: white !important;">NET PAY</td>
                        <td class="num bold" style="color: white !important;">₦<?php echo number_format($monthly['net'], 2); ?></td>
                        <td class="num bold" style="color: white !important;">₦<?php echo number_format($annual['net'], 2); ?></td>
                    </tr>
                </table>
            </section>

        </div>

        <footer>
            Powered by Miemploya Tax Support Services &bull; Generated on <?php echo date('d M Y H:i:s'); ?> &bull; Page 1 of 1
            <br>
            Calculations compliant with Nigeria Tax Act (NTA) 2025
        </footer>
    </div>

    <script>
        // Auto-print on load if desired, or let user click button
        // window.onload = function() { window.print(); }
    </script>
</body>
</html>
