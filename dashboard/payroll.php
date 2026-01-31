<?php
require_once '../includes/functions.php';
require_once '../includes/payroll_engine.php';
require_login();

$company_id = $_SESSION['company_id'] ?? 0;
$company_name = $_SESSION['company_name'] ?? 'Company';
$current_page = 'payroll';

// Placeholder for backend logic handling (kept from previous file)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['create_payroll'])) {
    // Logic to be adapted to new UI later
    $month = $_POST['month'];
    $year = $_POST['year'];
    // ... logic ...
}

// Fetch Active Departments
$stmt = $pdo->prepare("SELECT name FROM departments WHERE company_id = ? ORDER BY name");
$stmt->execute([$company_id]);
$departments = $stmt->fetchAll(PDO::FETCH_COLUMN);

// Fetch Salary Categories
$stmt = $pdo->prepare("SELECT id, name FROM salary_categories WHERE company_id = ? ORDER BY name");
$stmt->execute([$company_id]);
$categories = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Fetch Company Details for Payslip
$stmt = $pdo->prepare("SELECT name, address, logo_url, email, phone FROM companies WHERE id = ?");
$stmt->execute([$company_id]);
$company_details = $stmt->fetch(PDO::FETCH_ASSOC);
$payslip_company_name = $company_details['name'] ?? $company_name;
$company_logo = $company_details['logo_url'] ?? '';
$company_address = $company_details['address'] ?? '';
$company_email = $company_details['email'] ?? '';
$company_phone = $company_details['phone'] ?? '';

// Fetch Statutory Settings for legal compliance (PAYE, Pension, NHF, NHIS)
$stmt = $pdo->prepare("SELECT enable_paye, enable_pension, enable_nhf, enable_nhis FROM statutory_settings WHERE company_id = ?");
$stmt->execute([$company_id]);
$statutory_settings = $stmt->fetch(PDO::FETCH_ASSOC) ?: [
    'enable_paye' => 1,
    'enable_pension' => 1,
    'enable_nhf' => 0,
    'enable_nhis' => 0
];

// Fetch Behaviour Settings for Display & Overtime (NEW)
$stmt = $pdo->prepare("SELECT show_lateness, show_loan, show_bonus, show_deductions, overtime_enabled, daily_work_hours, monthly_work_days, overtime_rate FROM payroll_behaviours WHERE company_id = ?");
$stmt->execute([$company_id]);
$behaviour_settings = $stmt->fetch(PDO::FETCH_ASSOC) ?: [
    'show_lateness' => 1,
    'show_loan' => 1,
    'show_bonus' => 1,
    'show_deductions' => 1,
    'overtime_enabled' => 0,
    'daily_work_hours' => 8,
    'monthly_work_days' => 22,
    'overtime_rate' => 1.5
];

// Fetch Active Salary Components for dynamic columns
$stmt = $pdo->prepare("SELECT id, name, type FROM salary_components WHERE company_id = ? AND is_active = 1 ORDER BY type ASC, id ASC");
$stmt->execute([$company_id]);
$salary_components = $stmt->fetchAll(PDO::FETCH_ASSOC);
// Sort: Basic first, then allowances
usort($salary_components, function($a, $b) {
    $typeOrder = ['basic' => 0, 'allowance' => 1];
    return ($typeOrder[$a['type']] ?? 2) - ($typeOrder[$b['type']] ?? 2);
});

// TAXABLE BONUSES - Per PIT (Personal Income Tax) Act, all bonuses are taxable
$master_bonuses = [
    "Performance Bonus", "Productivity Bonus", "Target Achievement Bonus", "KPI / Appraisal Bonus", 
    "Efficiency Bonus", "Excellence Bonus", "Output-based Bonus",
    "Service Charge Bonus", "Long-service Bonus", "Loyalty Bonus", "Retention Bonus", 
    "End-of-year Service Bonus", "Contract Completion Bonus",
    "13th-month Bonus", "Christmas / Festive Bonus", "Sallah / Easter / Holiday Bonus", 
    "Anniversary Bonus", "Special Recognition Bonus",
    "Sales Commission", "Profit-sharing Bonus", "Revenue Incentive Bonus", 
    "Market Expansion Bonus", "Commission Override Bonus",
    "Attendance Bonus", "Night-shift Bonus", "Weekend / Public Holiday Bonus", 
    "Overtime Bonus", "Call-out / Standby Bonus",
    "Management Bonus", "Supervisory Bonus", "Acting Allowance (Bonus)", 
    "Responsibility Allowance (Cash)", "Leadership Bonus",
    "Hazard Bonus", "Field-work Bonus", "Offshore / Site Bonus", 
    "Relocation Bonus (Cash)", "Technical Skill Bonus",
    "Discretionary Bonus", "Spot Bonus", "Project Completion Bonus", 
    "Signing-on Bonus", "Ex-gratia Bonus",
    "Others"
];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Payroll Management - Mipaymaster</title>
    <!-- Tailwind CSS -->
    <script src="https://cdn.tailwindcss.com"></script>
    <script>
        tailwind.config = {
            darkMode: 'class',
            theme: {
                extend: {
                    fontFamily: {
                        sans: ['Inter', 'sans-serif'],
                    },
                    colors: {
                        brand: {
                            50: '#eef2ff',
                            100: '#e0e7ff',
                            500: '#6366f1',
                            600: '#4f46e5',
                            700: '#4338ca',
                            900: '#312e81',
                        }
                    }
                }
            }
        }
    </script>
    <!-- Lucide Icons -->
    <script src="https://unpkg.com/lucide@latest"></script>
    <!-- Alpine.js -->
    <script src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js" defer></script>
    <!-- Export Libraries -->
    <script src="https://cdnjs.cloudflare.com/ajax/libs/html2pdf.js/0.10.1/html2pdf.bundle.min.js"></script>
    <script src="https://cdn.sheetjs.com/xlsx-0.20.1/package/dist/xlsx.full.min.js"></script>
    
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap');
        body { font-family: 'Inter', sans-serif; }
        
        /* Custom Scrollbar */
        ::-webkit-scrollbar { width: 6px; height: 6px; }
        ::-webkit-scrollbar-track { background: transparent; }
        ::-webkit-scrollbar-thumb { background: #cbd5e1; border-radius: 3px; }
        .dark ::-webkit-scrollbar-thumb { background: #334155; }
        
        [x-cloak] { display: none !important; }
        
        .sidebar-transition { transition: width 0.3s ease-in-out, transform 0.3s ease-in-out, padding 0.3s; }
        #sidebar.w-0 { overflow: hidden; }

        /* Sticky Table Styles */
        .payroll-sheet-container { overflow: auto; max-height: 70vh; }
        .payroll-table { border-collapse: separate; border-spacing: 0; }
        .payroll-table th, .payroll-table td { white-space: nowrap; padding: 12px; border-bottom-width: 1px; }
        
        .sticky-col-left { position: sticky; left: 0; z-index: 20; border-right-width: 2px; }
        .sticky-header { position: sticky; top: 0; z-index: 30; }
        .sticky-corner { z-index: 40 !important; }
        
        .toolbar-hidden { transform: translateY(-100%); opacity: 0; pointer-events: none; height: 0; padding: 0; border: none; }
        .toolbar-visible { transform: translateY(0); opacity: 1; pointer-events: auto; height: auto; }
    </style>
    
    <script>
        function payrollApp() {
            return {
                view: 'dashboard', 
                sidebarOpen: false,
                loading: false,
                runSuccess: false,
                errorMsg: '',

                currentPeriod: {
                    month: new Date().getMonth() + 1, // Current month (1-12)
                    year: new Date().getFullYear()
                },
                
                payrollRun: null, // Stores metadata
                sheetData: [],    // Stores entries
                
                // FILTER DATA
                deptList: <?php echo json_encode($departments ?: [], JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT); ?>,
                catList: <?php echo json_encode($categories ?: [], JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT); ?>,
                
                // SELECTION
                selectedDept: '',
                selectedCat: '',
                payrollType: 'regular',
                
                // STATUTORY SETTINGS (dynamic visibility)
                statutoryFlags: {
                    paye: <?php echo $statutory_settings['enable_paye'] ? 'true' : 'false'; ?>,
                    pension: <?php echo $statutory_settings['enable_pension'] ? 'true' : 'false'; ?>,
                    nhf: <?php echo $statutory_settings['enable_nhf'] ? 'true' : 'false'; ?>,
                    nhis: <?php echo $statutory_settings['enable_nhis'] ? 'true' : 'false'; ?>,
                    // Payroll Display Settings (Now from Behaviour)
                    showLateness: <?php echo ($behaviour_settings['show_lateness'] ?? 1) ? 'true' : 'false'; ?>,
                    showLoan: <?php echo ($behaviour_settings['show_loan'] ?? 1) ? 'true' : 'false'; ?>,
                    showBonus: <?php echo ($behaviour_settings['show_bonus'] ?? 1) ? 'true' : 'false'; ?>,
                    showDeductions: <?php echo ($behaviour_settings['show_deductions'] ?? 1) ? 'true' : 'false'; ?>
                },
                
                // Employee Info Columns Toggle (collapsed by default)
                showEmployeeInfo: false,
                
                // Hide Employee Names (privacy mode - show ID only)
                hideEmployeeNames: false,
                
                // SALARY COMPONENTS (dynamic columns)
                salaryComponents: <?php echo json_encode($salary_components, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT); ?>,

                totals: {
                    gross: 0,
                    deductions: 0,
                    net: 0,
                    count: 0
                },
                
                checklist: {
                    active_employees: 0,
                    statutory_set: false,
                    missing_bank: 0,
                    missing_category: 0,
                    ready: false
                },
                
                anomalies: [],
                
                // ADJUSTMENT MODAL
                adjustmentModalOpen: false,
                adjustmentForm: {
                    employee_id: null,
                    employee_name: '',
                    type: 'bonus',
                    name: '',
                    customName: '', // For 'Others' selection
                    amount: 0,
                    overtime_hours: 0, // For overtime type
                    taxable: true, // Default to taxable per PIT Act
                    notes: ''
                },
                adjustments: [],
                masterBonuses: <?php echo json_encode($master_bonuses); ?>,
                masterDeductions: [
                    "Loan Repayment", "Unpaid Backlog Loan Repayment", "Salary Advance Recovery", 
                    "Staff Welfare Deductions", "Training Fees Recovery", "Staff Uniform", 
                    "Working Tools Deductions", "Lateness", "Absenteeism Deductions", 
                    "Damage to Company Property", "Lost Company Items Recovery", "Unapproved Expenses Recovery", 
                    "Misconduct Fines", "Disciplinary Charges", "Health Maintenance Organization (HMO) Premiums", 
                    "Court-Ordered Garnishments", "Insurance Premiums", "Mortgage Deductions", 
                    "Vehicle or Asset Financing Repayment", "Leave Without Pay (LWOP)", "Excess Leave Taken", 
                    "Training Bond Recovery", "Company Vehicle Usage Charges", "Phone Bill Reimbursement Deductions", 
                    "Accommodation Rent Deduction", "Utilities (Power, Water, Gas)", "Furniture Loan or Lease Repayment", 
                    "Laptop or Gadget Recovery", "Company Meal or Feeding Charges", "Internet or Wi-Fi Charges", 
                    "Official Attire/Uniform Deduction", "Fuel/Transport Card Repayment", "Advance Recovery", 
                    "Unretired Expense Claims", "Unpaid Tax Backlog Deduction", "Expatriate Work Permit Reimbursement", 
                    "Visa, Flight Ticket, Relocation Reimbursement", "Special HR Disciplinary Fines", 
                    "Staff Benevolent Fund", "End-of-Year Party Contribution", "Thrift Savings", "Esusu Deductions", 
                    "CSR or Fundraising Deductions", "Voluntary Religious Contributions", "Cooperative Society Contributions", 
                    "Christmas Contribution", "Ramadan Contribution", "Birthday Contribution", "Others"
                ],
                deductionSearch: '',
                showDeductionList: false,
                filteredDeductions: [],
                
                // OVERTIME MODAL (NEW)
                overtimeModalOpen: false,
                overtimeForm: {
                    employee_id: null,
                    employee_name: '',
                    hours: 0,
                    notes: ''
                },
                overtimeConfig: {
                    enabled: <?php echo ($behaviour_settings['overtime_enabled'] ?? 0) ? 'true' : 'false'; ?>,
                    dailyHours: <?php echo $behaviour_settings['daily_work_hours'] ?? 8.00; ?>,
                    monthlyDays: <?php echo $behaviour_settings['monthly_work_days'] ?? 22; ?>,
                    rate: <?php echo $behaviour_settings['overtime_rate'] ?? 1.50; ?>
                },
                // Employee-specific OT config (populated when adjustment modal opens)
                currentOTConfig: {
                    dailyHours: <?php echo $behaviour_settings['daily_work_hours'] ?? 8.00; ?>,
                    monthlyDays: <?php echo $behaviour_settings['monthly_work_days'] ?? 22; ?>,
                    rate: <?php echo $behaviour_settings['overtime_rate'] ?? 1.50; ?>,
                    shiftName: null,
                    mode: 'daily',
                    mode: 'daily',
                    loading: false
                },
                dataHasOvertime: false,
                
                // PAYSLIP MODAL
                payslipModalOpen: false,
                selectedEmployee: null,

                init() {
                    this.$watch('view', () => setTimeout(() => lucide.createIcons(), 50));
                    // Initial load
                    this.fetchSheet();
                },
                
                changeView(newView) {
                    this.view = newView;
                    window.scrollTo({top: 0, behavior: 'smooth'});
                    if(newView === 'sheet' || newView === 'preview') {
                        this.fetchSheet();
                    }
                    if(newView === 'run') {
                        this.checkReadiness();
                    }
                },

                async checkReadiness() {
                    this.loading = true;
                    try {
                        const res = await fetch('ajax/payroll_operations.php', {
                            method: 'POST',
                            headers: {'Content-Type': 'application/json'},
                            body: JSON.stringify({ action: 'check_readiness' })
                        });
                        const data = await res.json();
                        if(data.status) {
                            this.checklist = { ...data.checks, ready: true };
                            // Basic logic for "Ready": 
                            // Warn if missing bank or category, but don't hard block unless 0 active?
                        }
                    } catch(e) {
                        console.error("Readiness check failed", e);
                    } finally {
                        this.loading = false;
                    }
                },

                async runPayroll() {
                    console.log('runPayroll() called');
                    this.loading = true;
                    this.errorMsg = '';
                    try {
                        const formData = {
                            action: 'initiate',
                            month: this.currentPeriod.month,
                            year: this.currentPeriod.year,
                            payroll_type: this.payrollType,
                            department: this.selectedDept,
                            category: this.selectedCat
                        };
                        console.log('Sending request:', formData);
                        
                        const res = await fetch('ajax/payroll_operations.php', {
                            method: 'POST',
                            headers: {'Content-Type': 'application/json'},
                            body: JSON.stringify(formData)
                        });
                        console.log('Response status:', res.status);
                        const data = await res.json();
                        console.log('Response data:', data);
                        
                        if(data.status) {
                            // Success
                            console.log('Success! Changing view to sheet');
                            this.changeView('sheet');
                        } else {
                            console.log('Error from server:', data.message);
                            alert('Error: ' + data.message);
                        }
                    } catch(e) {
                        console.error('Exception:', e);
                        alert('System Error: ' + e.message);
                    } finally {
                        this.loading = false;
                        console.log('runPayroll() finished');
                    }
                },

                async fetchSheet() {
                    console.log('fetchSheet() called');
                    this.loading = true;
                    try {
                        const formData = {
                            action: 'fetch_sheet',
                            month: this.currentPeriod.month,
                            year: this.currentPeriod.year
                        };
                        console.log('fetchSheet sending:', formData);
                        const res = await fetch('ajax/payroll_operations.php', {
                            method: 'POST',
                            headers: {'Content-Type': 'application/json'},
                            body: JSON.stringify(formData)
                        });
                        console.log('fetchSheet response status:', res.status);
                        const data = await res.json();
                        console.log('fetchSheet response data:', data);
                        
                        if(data.status && data.run) {
                            console.log('fetchSheet: Got run data, entries count:', data.entries?.length);
                            this.payrollRun = data.run; // id, status, etc.
                            this.sheetData = data.entries;
                            
                            // Check if any employee has overtime data to force-show the column
                            this.dataHasOvertime = this.sheetData.some(emp => parseFloat(emp.breakdown.overtime_pay || 0) > 0);
                            console.log('fetchSheet: dataHasOvertime =', this.dataHasOvertime);
                            // Update Totals
                            this.totals = {
                                gross: parseFloat(data.totals.total_gross || 0),
                                deductions: parseFloat(data.totals.total_deductions || 0),
                                net: parseFloat(data.totals.total_net || 0),
                                count: parseInt(data.totals.employee_count || 0)
                            };
                            console.log('fetchSheet: totals updated:', this.totals);
                            // Update Anomalies
                            this.anomalies = data.anomalies || [];
                        } else {
                            console.log('fetchSheet: No run found or status false');
                            // No run found
                            this.payrollRun = null;
                            this.sheetData = [];
                            this.totals = { gross: 0, deductions: 0, net: 0, count: 0 };
                            this.anomalies = [];
                        }
                    } catch(e) {
                        console.error('fetchSheet exception:', e);
                    } finally {
                        this.loading = false;
                        console.log('fetchSheet() finished, sheetData length:', this.sheetData?.length);
                    }
                },

                async rejectPayroll() {
                    if(!this.payrollRun || !this.payrollRun.id) return;
                    
                    const reason = document.querySelector('textarea[placeholder*="Approval notes"]')?.value || '';
                    const confirmMsg = reason 
                        ? `Are you sure you want to REJECT this payroll?\n\nReason: ${reason}\n\nThis will delete the current draft and allow you to re-run payroll.`
                        : 'Are you sure you want to REJECT this payroll? This will delete the current draft and allow you to re-run payroll.';
                    
                    if(!confirm(confirmMsg)) return;

                    this.loading = true;
                    try {
                        const res = await fetch('ajax/payroll_operations.php', {
                            method: 'POST',
                            headers: {'Content-Type': 'application/json'},
                            body: JSON.stringify({
                                action: 'reject_payroll',
                                run_id: this.payrollRun.id,
                                reason: reason
                            })
                        });
                        const data = await res.json();
                        if(data.status) {
                            alert('Payroll rejected. You can now make changes and re-run payroll.');
                            // Reset state
                            this.payrollRun = null;
                            this.sheetData = [];
                            this.changeView('run');
                        } else {
                            alert('Error: ' + data.message);
                        }
                    } catch(e) {
                        alert('Error: ' + e.message);
                    } finally {
                        this.loading = false;
                    }
                },

                async approvePayroll() {
                    if(!this.payrollRun || !this.payrollRun.id) return;
                    if(!confirm("Are you sure you want to FINALISE and LOCK this payroll? This action cannot be undone.")) return;

                    this.loading = true;
                    try {
                         const res = await fetch('ajax/payroll_operations.php', {
                            method: 'POST',
                            headers: {'Content-Type': 'application/json'},
                            body: JSON.stringify({
                                action: 'lock_payroll',
                                run_id: this.payrollRun.id
                            })
                        });
                        const data = await res.json();
                        if(data.status) {
                            this.runSuccess = true;
                            this.payrollRun.status = 'locked';
                            setTimeout(() => {
                                this.runSuccess = false;
                                this.changeView('dashboard');
                            }, 3000);
                        } else {
                            alert('Error: ' + data.message);
                        }
                    } catch(e) {
                        alert('Error: ' + e.message);
                    } finally {
                        this.loading = false;
                    }
                },

                formatCurrency(amount) {
                    // Defensive handling for null, undefined, or non-numeric values
                    const num = parseFloat(amount);
                    if (isNaN(num)) {
                        return new Intl.NumberFormat('en-NG', { style: 'currency', currency: 'NGN' }).format(0);
                    }
                    return new Intl.NumberFormat('en-NG', { style: 'currency', currency: 'NGN' }).format(num);
                },
                
                numberToWords(amount) {
                    const ones = ['', 'One', 'Two', 'Three', 'Four', 'Five', 'Six', 'Seven', 'Eight', 'Nine', 'Ten', 'Eleven', 'Twelve', 'Thirteen', 'Fourteen', 'Fifteen', 'Sixteen', 'Seventeen', 'Eighteen', 'Nineteen'];
                    const tens = ['', '', 'Twenty', 'Thirty', 'Forty', 'Fifty', 'Sixty', 'Seventy', 'Eighty', 'Ninety'];
                    const scales = ['', 'Thousand', 'Million', 'Billion'];
                    
                    const num = Math.floor(parseFloat(amount) || 0);
                    if (num === 0) return 'Zero Naira Only';
                    
                    const convertHundreds = (n) => {
                        let result = '';
                        if (n >= 100) {
                            result += ones[Math.floor(n / 100)] + ' Hundred ';
                            n %= 100;
                        }
                        if (n >= 20) {
                            result += tens[Math.floor(n / 10)] + ' ';
                            n %= 10;
                        }
                        if (n > 0) result += ones[n] + ' ';
                        return result;
                    };
                    
                    let words = '';
                    let scaleIndex = 0;
                    let remaining = num;
                    
                    while (remaining > 0) {
                        const chunk = remaining % 1000;
                        if (chunk > 0) {
                            words = convertHundreds(chunk) + scales[scaleIndex] + ' ' + words;
                        }
                        remaining = Math.floor(remaining / 1000);
                        scaleIndex++;
                    }
                    
                    // Handle kobo (cents)
                    const kobo = Math.round((parseFloat(amount) - num) * 100);
                    let koboText = '';
                    if (kobo > 0) {
                        koboText = ', ' + convertHundreds(kobo).trim() + ' Kobo';
                    }
                    
                    return words.trim() + ' Naira' + koboText + ' Only';
                },
                
                getMonthName(month) {
                    const months = ['January', 'February', 'March', 'April', 'May', 'June', 
                                   'July', 'August', 'September', 'October', 'November', 'December'];
                    return months[parseInt(month) - 1] || 'Unknown';
                },
                
                get payrollSheet() {
                    return this.sheetData || [];
                },
                
                get lastRunId() {
                    return this.payrollRun?.id || null;
                },
                
                calculateStatutoryTotal(type) {
                    if (!this.sheetData || this.sheetData.length === 0) return 0;
                    return this.sheetData.reduce((sum, emp) => {
                        const breakdown = emp.breakdown || {};
                        switch(type) {
                            case 'paye': return sum + parseFloat(breakdown.paye || 0);
                            case 'pension': return sum + parseFloat(breakdown.pension || 0);
                            case 'nhf': return sum + parseFloat(breakdown.nhf || 0);
                            case 'nhis': return sum + parseFloat(breakdown.nhis || 0);
                            default: return sum;
                        }
                    }, 0);
                },
                
                getDepartmentBreakdown() {
                    if (!this.sheetData || this.sheetData.length === 0) return [];
                    const depts = {};
                    this.sheetData.forEach(emp => {
                        const deptName = emp.department || 'Unassigned';
                        if (!depts[deptName]) {
                            depts[deptName] = { name: deptName, count: 0, netPay: 0 };
                        }
                        depts[deptName].count++;
                        // Only include positive net pay in department total
                        const empNet = parseFloat(emp.net_pay || 0);
                        if (empNet > 0) depts[deptName].netPay += empNet;
                    });
                    return Object.values(depts).sort((a, b) => b.netPay - a.netPay);
                },
                
                getActiveStatutoryTotal() {
                    let total = 0;
                    if (this.statutoryFlags.paye) total += this.calculateStatutoryTotal('paye');
                    if (this.statutoryFlags.pension) total += this.calculateStatutoryTotal('pension');
                    if (this.statutoryFlags.nhf) total += this.calculateStatutoryTotal('nhf');
                    if (this.statutoryFlags.nhis) total += this.calculateStatutoryTotal('nhis');
                    return total;
                },
                
                // Calculate "Others" allowances (all except Basic, Housing, Transport)
                getOtherAllowances(emp) {
                    const allComps = emp.breakdown?.all_components || {};
                    const exclude = ['Basic Salary', 'Housing Allowance', 'Transport Allowance'];
                    let total = 0;
                    for (const [name, value] of Object.entries(allComps)) {
                        if (!exclude.includes(name)) {
                            total += parseFloat(value) || 0;
                        }
                    }
                    return total;
                },
                
                // ADJUSTMENT METHODS
                async openAdjustment(emp) {
                    // Show modal immediately with loading state
                    this.currentOTConfig.loading = true;
                    this.adjustmentModalOpen = true;
                    
                    // Fetch employee-specific OT configuration
                    try {
                        const config = await this.fetchEmployeeOTConfig(emp.employee_id);
                        this.currentOTConfig = {
                            dailyHours: config.daily_hours || this.overtimeConfig.dailyHours,
                            monthlyDays: config.monthly_days || this.overtimeConfig.monthlyDays,
                            rate: config.ot_rate || this.overtimeConfig.rate,
                            shiftName: config.shift_name,
                            mode: config.mode || 'daily',
                            loading: false
                        };
                    } catch (e) {
                        console.error('Failed to fetch OT config:', e);
                        // Fallback to global config
                        this.currentOTConfig = {
                            dailyHours: this.overtimeConfig.dailyHours,
                            monthlyDays: this.overtimeConfig.monthlyDays,
                            rate: this.overtimeConfig.rate,
                            shiftName: null,
                            mode: 'daily',
                            loading: false
                        };
                    }
                    
                    // Calculate hourly rate using employee-specific values
                    const hourlyRate = parseFloat(emp.gross_salary) / (this.currentOTConfig.dailyHours * this.currentOTConfig.monthlyDays);
                    
                    this.adjustmentForm = {
                        employee_id: emp.employee_id,
                        employee_name: emp.first_name + ' ' + emp.last_name,
                        type: 'bonus',
                        name: '',
                        amount: 0,
                        overtime_hours: 0,
                        hourly_rate: hourlyRate,
                        gross_salary: parseFloat(emp.gross_salary),
                        notes: ''
                    };
                    setTimeout(() => lucide.createIcons(), 50);
                },
                
                // Fetch employee-specific OT configuration from server
                async fetchEmployeeOTConfig(employee_id) {
                    const res = await fetch('ajax/payroll_operations.php', {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/json' },
                        body: JSON.stringify({ action: 'get_employee_ot_config', employee_id })
                    });
                    const data = await res.json();
                    if (data.status) {
                        return data;
                    }
                    throw new Error(data.message || 'Failed to fetch OT config');
                },
                
                // Calculate overtime pay when hours change
                calculateOvertimePay() {
                    const hours = parseFloat(this.adjustmentForm.overtime_hours) || 0;
                    const hourlyRate = this.adjustmentForm.hourly_rate || 0;
                    const otRate = this.currentOTConfig.rate || 1.5;
                    
                    this.adjustmentForm.amount = hours * hourlyRate * otRate;
                    this.adjustmentForm.name = 'Overtime';
                },
                
                async saveAdjustment() {
                    // Validation based on type
                    if (this.adjustmentForm.type === 'overtime') {
                        if (!this.adjustmentForm.overtime_hours || this.adjustmentForm.overtime_hours <= 0) {
                            alert('Please enter overtime hours');
                            return;
                        }
                        // Use save_overtime action for overtime
                        this.loading = true;
                        try {
                            const res = await fetch('ajax/payroll_operations.php', {
                                method: 'POST',
                                headers: { 'Content-Type': 'application/json' },
                                body: JSON.stringify({
                                    action: 'save_overtime',
                                    employee_id: this.adjustmentForm.employee_id,
                                    hours: parseFloat(this.adjustmentForm.overtime_hours),
                                    notes: this.adjustmentForm.notes,
                                    month: this.currentPeriod.month,
                                    year: this.currentPeriod.year
                                })
                            });
                            const data = await res.json();
                            if (data.status) {
                                alert('Overtime saved! Re-run payroll to apply.');
                                this.adjustmentModalOpen = false;
                                this.fetchSheet(); // Refresh data
                            } else {
                                alert('Error: ' + data.message);
                            }
                        } catch (e) {
                            alert('Error: ' + e.message);
                        } finally {
                            this.loading = false;
                        }
                        return;
                    }
                    
                    // Non-overtime adjustments (bonus/deduction)
                    if (!this.adjustmentForm.name || !this.adjustmentForm.amount) {
                        alert('Please fill in name and amount');
                        return;
                    }
                    
                    this.loading = true;
                    try {
                        const res = await fetch('ajax/payroll_operations.php', {
                            method: 'POST',
                            headers: { 'Content-Type': 'application/json' },
                            body: JSON.stringify({
                                action: 'add_adjustment',
                                employee_id: this.adjustmentForm.employee_id,
                                type: this.adjustmentForm.type,
                                name: this.adjustmentForm.name,
                                amount: this.adjustmentForm.amount,
                                notes: this.adjustmentForm.notes,
                                month: this.currentPeriod.month,
                                year: this.currentPeriod.year
                            })
                        });
                        const data = await res.json();
                        if (data.status) {
                            alert('Adjustment added! Re-run payroll to apply.');
                            this.adjustmentModalOpen = false;
                        } else {
                            alert('Error: ' + data.message);
                        }
                    } catch (e) {
                        alert('Error: ' + e.message);
                    } finally {
                        this.loading = false;
                    }
                },
                
                // DEDUCTION AUTOCOMPLETE
                filterDeductions() {
                    const search = this.deductionSearch.toLowerCase();
                    if (search.length === 0) {
                        this.filteredDeductions = this.masterDeductions.slice(0, 10);
                    } else {
                        this.filteredDeductions = this.masterDeductions.filter(d => 
                            d.toLowerCase().includes(search)
                        ).slice(0, 15);
                    }
                    this.showDeductionList = true;
                },
                
                selectDeduction(ded) {
                    this.adjustmentForm.name = ded;
                    this.deductionSearch = '';
                    this.showDeductionList = false;
                    setTimeout(() => lucide.createIcons(), 50);
                },
                
                // OVERTIME METHODS (NEW)
                openOvertimeModal(emp) {
                    if (!this.overtimeConfig.enabled) {
                        alert('Overtime is not enabled. Please enable it in Company Setup → Statutory tab first.');
                        return;
                    }
                    this.overtimeForm = {
                        employee_id: emp.employee_id,
                        employee_name: emp.first_name + ' ' + emp.last_name,
                        hours: emp.breakdown.overtime_hours || 0,
                        notes: emp.breakdown.overtime_notes || ''
                    };
                    this.overtimeModalOpen = true;
                    setTimeout(() => lucide.createIcons(), 50);
                },
                
                calculateOvertimePay(hours, grossSalary) {
                    if (!hours || !grossSalary) return 0;
                    const hourlyRate = grossSalary / (this.overtimeConfig.dailyHours * this.overtimeConfig.monthlyDays);
                    return hours * hourlyRate * this.overtimeConfig.rate;
                },
                
                async saveOvertime() {
                    if (this.overtimeForm.hours < 0) {
                        alert('Hours cannot be negative');
                        return;
                    }
                    this.loading = true;
                    try {
                        const res = await fetch('ajax/payroll_operations.php', {
                            method: 'POST',
                            headers: { 'Content-Type': 'application/json' },
                            body: JSON.stringify({
                                action: 'save_overtime',
                                employee_id: this.overtimeForm.employee_id,
                                hours: parseFloat(this.overtimeForm.hours) || 0,
                                notes: this.overtimeForm.notes,
                                month: this.currentPeriod.month,
                                year: this.currentPeriod.year
                            })
                        });
                        const data = await res.json();
                        if (data.status) {
                            alert('Overtime saved! Re-run payroll to apply.');
                            this.overtimeModalOpen = false;
                            this.fetchSheet(); // Refresh data
                        } else {
                            alert('Error: ' + data.message);
                        }
                    } catch (e) {
                        alert('Error: ' + e.message);
                    } finally {
                        this.loading = false;
                    }
                },
                
                // EXPORT METHODS
                exportToExcel() {
                    if (!this.sheetData.length) {
                        alert('No payroll data to export');
                        return;
                    }
                    
                    // Prepare data for Excel
                    const data = this.sheetData.map(emp => ({
                        'Employee': `${emp.first_name} ${emp.last_name}`,
                        'Payroll ID': emp.payroll_id || 'N/A',
                        'Basic': emp.breakdown.basic,
                        'Housing': emp.breakdown.housing,
                        'Transport': emp.breakdown.transport,
                        'Bonus': emp.breakdown.bonus || 0,
                        'Gross Pay': emp.gross_salary,
                        'PAYE': emp.breakdown.paye,
                        'Pension': emp.breakdown.pension,
                        'NHIS': emp.breakdown.nhis,
                        'Lateness': emp.breakdown.lateness || 0,
                        'Loan': emp.breakdown.loan,
                        'Deductions': emp.breakdown.custom_deductions || 0,
                        'Net Pay': emp.net_pay
                    }));
                    
                    const ws = XLSX.utils.json_to_sheet(data);
                    const wb = XLSX.utils.book_new();
                    XLSX.utils.book_append_sheet(wb, ws, 'Payroll');
                    
                    // Auto-size columns
                    const colWidths = Object.keys(data[0]).map(key => ({ wch: Math.max(key.length, 12) }));
                    ws['!cols'] = colWidths;
                    
                    XLSX.writeFile(wb, `Payroll_${this.currentPeriod.year}_${String(this.currentPeriod.month).padStart(2, '0')}.xlsx`);
                },
                
                exportToPDF() {
                    if (!this.sheetData.length) {
                        alert('No payroll data to export');
                        return;
                    }
                    
                    const companyName = <?php echo json_encode($payslip_company_name); ?>;
                    const companyLogo = <?php echo json_encode($company_logo); ?>;
                    const companyAddress = <?php echo json_encode($company_address); ?>;
                    const logoPath = companyLogo ? `../uploads/logos/${companyLogo}` : '';
                    
                    const periodStr = new Date(this.currentPeriod.year, this.currentPeriod.month - 1)
                        .toLocaleDateString('en-NG', { month: 'long', year: 'numeric' });
                    
                    // Check what columns to show
                    const showBonus = this.sheetData.some(emp => (emp.breakdown.bonus || 0) > 0);
                    const showOT = this.overtimeConfig.enabled || this.dataHasOvertime;
                    const showPension = this.statutoryFlags.pension;
                    const showNHF = this.statutoryFlags.nhf;
                    const showNHIS = this.statutoryFlags.nhis;
                    const hasOtherStatutory = showPension || showNHF || showNHIS;
                    
                    // Build header row
                    const employeeColHeader = this.hideEmployeeNames ? 'Employee ID' : 'Employee';
                    let headerCells = `
                        <th style="background:#1e293b;color:white;padding:4px;text-align:center;border:1px solid #334155;font-size:13px;">S/N</th>
                        <th style="background:#1e293b;color:white;padding:4px;text-align:left;border:1px solid #334155;font-size:13px;">${employeeColHeader}</th>
                        <th style="background:#1e293b;color:white;padding:4px;text-align:left;border:1px solid #334155;font-size:13px;max-width:80px;">Designation</th>
                        <th style="background:#1e293b;color:white;padding:4px;text-align:left;border:1px solid #334155;font-size:13px;">Bank</th>
                        <th style="background:#1e293b;color:white;padding:4px;text-align:left;border:1px solid #334155;font-size:13px;">Account No</th>
                    `;
                    if (showBonus) headerCells += `<th style="background:#166534;color:white;padding:4px;text-align:right;border:1px solid #334155;font-size:13px;">Bonus</th>`;
                    if (showOT) headerCells += `<th style="background:#ea580c;color:white;padding:4px;text-align:right;border:1px solid #334155;font-size:13px;">OT</th>`;
                    headerCells += `
                        <th style="background:#0369a1;color:white;padding:4px;text-align:right;border:1px solid #334155;font-size:13px;">Gross Pay</th>
                        <th style="background:#dc2626;color:white;padding:4px;text-align:right;border:1px solid #334155;font-size:13px;">PAYE</th>
                    `;
                    if (hasOtherStatutory) headerCells += `<th style="background:#dc2626;color:white;padding:4px;text-align:right;border:1px solid #334155;font-size:13px;">Other Statutory Ded.</th>`;
                    headerCells += `
                        <th style="background:#7c2d12;color:white;padding:4px;text-align:right;border:1px solid #334155;font-size:13px;">Total Ded.</th>
                        <th style="background:#15803d;color:white;padding:4px;text-align:right;border:1px solid #334155;font-size:13px;">Net Pay</th>
                    `;
                    
                    // Build data rows
                    let dataRows = '';
                    let totals = { bonus: 0, ot: 0, gross: 0, paye: 0, other: 0, totalDed: 0, net: 0 };
                    
                    this.sheetData.forEach((emp, idx) => {
                        const bonus = emp.breakdown.bonus || 0;
                        const otPay = emp.breakdown.overtime_pay || 0;
                        const gross = emp.gross_salary || 0;
                        const paye = emp.breakdown.paye || 0;
                        const pension = emp.breakdown.pension || 0;
                        const nhf = emp.breakdown.nhf || 0;
                        const nhis = emp.breakdown.nhis || 0;
                        const otherStat = pension + nhf + nhis;
                        const totalDed = emp.total_deductions || 0;
                        const netPay = emp.net_pay || 0;
                        
                        totals.bonus += bonus;
                        totals.ot += otPay;
                        totals.gross += gross;
                        totals.paye += paye;
                        totals.other += otherStat;
                        totals.totalDed += totalDed;
                        // Only include positive net pay in total (negative balances excluded)
                        if (netPay > 0) totals.net += netPay;
                        
                        const rowStyle = idx % 2 === 0 ? 'background:#f8fafc;' : 'background:#ffffff;';
                        const employeeDisplay = this.hideEmployeeNames 
                            ? (emp.payroll_id || 'N/A')
                            : `${emp.first_name} ${emp.last_name}`;
                        
                        dataRows += `<tr style="${rowStyle}">
                            <td style="padding:4px;border:1px solid #e2e8f0;text-align:center;font-size:13px;">${idx + 1}</td>
                            <td style="padding:4px;border:1px solid #e2e8f0;font-size:13px;white-space:nowrap;">${employeeDisplay}</td>
                            <td style="padding:4px;border:1px solid #e2e8f0;font-size:13px;max-width:80px;overflow:hidden;text-overflow:ellipsis;">${emp.designation || '—'}</td>
                            <td style="padding:4px;border:1px solid #e2e8f0;font-size:13px;">${emp.bank_name || '—'}</td>
                            <td style="padding:4px;border:1px solid #e2e8f0;font-size:13px;">${emp.account_number || '—'}</td>
                        `;
                        if (showBonus) dataRows += `<td style="padding:4px;border:1px solid #e2e8f0;text-align:right;font-size:13px;">${this.formatCurrency(bonus)}</td>`;
                        if (showOT) dataRows += `<td style="padding:4px;border:1px solid #e2e8f0;text-align:right;font-size:13px;">${this.formatCurrency(otPay)}</td>`;
                        dataRows += `
                            <td style="padding:4px;border:1px solid #e2e8f0;text-align:right;font-size:13px;font-weight:bold;">${this.formatCurrency(gross)}</td>
                            <td style="padding:4px;border:1px solid #e2e8f0;text-align:right;font-size:13px;color:#dc2626;">${this.formatCurrency(paye)}</td>
                        `;
                        if (hasOtherStatutory) dataRows += `<td style="padding:4px;border:1px solid #e2e8f0;text-align:right;font-size:13px;color:#dc2626;">${this.formatCurrency(otherStat)}</td>`;
                        dataRows += `
                            <td style="padding:4px;border:1px solid #e2e8f0;text-align:right;font-size:13px;color:#7c2d12;font-weight:bold;">${this.formatCurrency(totalDed)}</td>
                            <td style="padding:4px;border:1px solid #e2e8f0;text-align:right;font-size:13px;color:#15803d;font-weight:bold;">${this.formatCurrency(netPay)}</td>
                        </tr>`;
                    });
                    
                    // Totals row
                    const colSpan = 5;
                    let totalsRow = `<tr style="background:#1e293b;color:white;font-weight:bold;">
                        <td colspan="${colSpan}" style="padding:4px;border:1px solid #334155;text-align:right;font-size:13px;">TOTALS:</td>
                    `;
                    if (showBonus) totalsRow += `<td style="padding:4px;border:1px solid #334155;text-align:right;font-size:13px;">${this.formatCurrency(totals.bonus)}</td>`;
                    if (showOT) totalsRow += `<td style="padding:4px;border:1px solid #334155;text-align:right;font-size:13px;">${this.formatCurrency(totals.ot)}</td>`;
                    totalsRow += `
                        <td style="padding:4px;border:1px solid #334155;text-align:right;font-size:13px;">${this.formatCurrency(totals.gross)}</td>
                        <td style="padding:4px;border:1px solid #334155;text-align:right;font-size:13px;">${this.formatCurrency(totals.paye)}</td>
                    `;
                    if (hasOtherStatutory) totalsRow += `<td style="padding:4px;border:1px solid #334155;text-align:right;font-size:13px;">${this.formatCurrency(totals.other)}</td>`;
                    totalsRow += `
                        <td style="padding:4px;border:1px solid #334155;text-align:right;font-size:13px;">${this.formatCurrency(totals.totalDed)}</td>
                        <td style="padding:4px;border:1px solid #334155;text-align:right;font-size:13px;">${this.formatCurrency(totals.net)}</td>
                    </tr>`;
                    
                    // Build complete HTML
                    const logoHtml = logoPath ? `<img src="${logoPath}" style="height:50px;width:auto;object-fit:contain;" crossorigin="anonymous">` : '';
                    
                    const wrapper = document.createElement('div');
                    wrapper.style.cssText = 'padding:15px;background:white;font-family:Arial,sans-serif;';
                    wrapper.innerHTML = `
                        <div style="display:flex;align-items:center;gap:15px;margin-bottom:15px;border-bottom:2px solid #1e293b;padding-bottom:10px;">
                            ${logoHtml}
                            <div style="flex:1;">
                                <h2 style="margin:0;font-size:16px;font-weight:bold;color:#1e293b;">${companyName}</h2>
                                <p style="margin:2px 0;font-size:10px;color:#64748b;">${companyAddress}</p>
                            </div>
                            <div style="text-align:right;">
                                <h3 style="margin:0;font-size:14px;font-weight:bold;color:#0369a1;">PAYROLL SHEET</h3>
                                <p style="margin:2px 0;font-size:11px;color:#334155;">${periodStr}</p>
                                <p style="margin:0;font-size:9px;color:#94a3b8;">Generated: ${new Date().toLocaleString()}</p>
                            </div>
                        </div>
                        <table style="width:100%;border-collapse:collapse;margin-top:10px;">
                            <thead><tr>${headerCells}</tr></thead>
                            <tbody>${dataRows}</tbody>
                            <tfoot>${totalsRow}</tfoot>
                        </table>
                        <div style="margin-top:20px;padding-top:10px;border-top:1px solid #e2e8f0;display:flex;justify-content:space-between;font-size:9px;color:#64748b;">
                            <span>Total Employees: ${this.sheetData.length}</span>
                            <span>Confidential - ${companyName}</span>
                        </div>
                    `;
                    
                    const opt = {
                        margin: [8, 8, 8, 8],
                        filename: `Payroll_${companyName.replace(/\s+/g, '_')}_${this.currentPeriod.year}_${String(this.currentPeriod.month).padStart(2, '0')}.pdf`,
                        image: { type: 'jpeg', quality: 0.95 },
                        html2canvas: { scale: 1.5, useCORS: true, logging: false },
                        jsPDF: { unit: 'mm', format: 'a3', orientation: 'landscape' },
                        pagebreak: { mode: 'avoid-all', after: '.page-break' }
                    };
                    
                    html2pdf().set(opt).from(wrapper).save();
                },
                
                printPayroll() {
                    if (!this.sheetData.length) {
                        alert('No payroll data to print');
                        return;
                    }
                    
                    // Create print-friendly content
                    const table = document.querySelector('.payroll-table');
                    const clone = table.cloneNode(true);
                    
                    // Remove Actions column
                    clone.querySelectorAll('th:last-child, td:last-child').forEach(el => el.remove());
                    
                    const printWindow = window.open('', '_blank');
                    printWindow.document.write(`
                        <!DOCTYPE html>
                        <html>
                        <head>
                            <title>Payroll Sheet</title>
                            <style>
                                body { font-family: Arial, sans-serif; padding: 20px; }
                                h2 { text-align: center; margin-bottom: 5px; }
                                .meta { text-align: center; color: #666; font-size: 12px; margin-bottom: 20px; }
                                table { width: 100%; border-collapse: collapse; font-size: 10px; }
                                th, td { border: 1px solid #ddd; padding: 6px 8px; text-align: left; }
                                th { background: #f5f5f5; font-weight: bold; }
                                @media print {
                                    body { margin: 0; }
                                    @page { size: landscape; margin: 10mm; }
                                }
                            </style>
                        </head>
                        <body>
                            <h2>Payroll Sheet</h2>
                            <p class="meta">Period: ${new Date(this.currentPeriod.year, this.currentPeriod.month - 1).toLocaleDateString('en-NG', { month: 'long', year: 'numeric' })} | Generated: ${new Date().toLocaleString()}</p>
                            ${clone.outerHTML}
                        </body>
                        </html>
                    `);
                    printWindow.document.close();
                    setTimeout(() => {
                        printWindow.print();
                        printWindow.close();
                    }, 500);
                },
                
                // PAYSLIP METHODS
                viewPayslip(emp) {
                    this.selectedEmployee = emp;
                    this.payslipModalOpen = true;
                    setTimeout(() => lucide.createIcons(), 50);
                },
                
                printPayslip() {
                    const payslipContent = document.getElementById('payslip-content');
                    const clone = payslipContent.cloneNode(true);
                    
                    const printWindow = window.open('', '_blank');
                    printWindow.document.write(`
                        <!DOCTYPE html>
                        <html>
                        <head>
                            <title>Payslip - ${this.selectedEmployee.first_name} ${this.selectedEmployee.last_name}</title>
                            <style>
                                * { margin: 0; padding: 0; box-sizing: border-box; }
                                body { font-family: Arial, sans-serif; padding: 20px; background: white; }
                                .payslip-container { max-width: 800px; margin: 0 auto; }
                                .header { display: flex; justify-content: space-between; margin-bottom: 20px; padding-bottom: 15px; border-bottom: 2px solid #0066cc; }
                                .company-name { font-size: 24px; font-weight: bold; color: #0066cc; }
                                .payslip-title { font-size: 18px; color: #666; }
                                .employee-info { display: grid; grid-template-columns: 1fr 1fr; gap: 10px; margin-bottom: 20px; padding: 15px; background: #f5f5f5; border-radius: 8px; }
                                .info-item { font-size: 13px; }
                                .info-label { color: #666; }
                                .info-value { font-weight: bold; }
                                .columns { display: grid; grid-template-columns: 1fr 1fr; gap: 20px; margin-bottom: 20px; }
                                .column { padding: 15px; border-radius: 8px; }
                                .earnings { background: #e8f5e9; border: 1px solid #c8e6c9; }
                                .deductions { background: #ffebee; border: 1px solid #ffcdd2; }
                                .column-title { font-size: 14px; font-weight: bold; margin-bottom: 10px; padding-bottom: 8px; border-bottom: 1px solid rgba(0,0,0,0.1); }
                                .earnings .column-title { color: #2e7d32; }
                                .deductions .column-title { color: #c62828; }
                                .line-item { display: flex; justify-content: space-between; font-size: 12px; padding: 5px 0; }
                                .line-item.total { font-weight: bold; border-top: 1px solid rgba(0,0,0,0.2); margin-top: 10px; padding-top: 10px; }
                                .net-pay { text-align: center; padding: 20px; background: linear-gradient(135deg, #0066cc, #0099ff); color: white; border-radius: 8px; }
                                .net-pay-label { font-size: 14px; opacity: 0.9; }
                                .net-pay-amount { font-size: 28px; font-weight: bold; }
                                @media print { body { padding: 0; } @page { margin: 15mm; } }
                            </style>
                        </head>
                        <body>${clone.innerHTML}</body>
                        </html>
                    `);
                    printWindow.document.close();
                    setTimeout(() => {
                        printWindow.print();
                        printWindow.close();
                    }, 500);
                },
                
                exportPayslipPDF() {
                    const payslipContent = document.getElementById('payslip-content');
                    
                    const opt = {
                        margin: [10, 10, 10, 10],
                        filename: `Payslip_${this.selectedEmployee.first_name}_${this.selectedEmployee.last_name}_${this.currentPeriod.year}_${String(this.currentPeriod.month).padStart(2, '0')}.pdf`,
                        image: { type: 'jpeg', quality: 0.98 },
                        html2canvas: { scale: 2, useCORS: true },
                        jsPDF: { unit: 'mm', format: 'a4', orientation: 'portrait' }
                    };
                    
                    html2pdf().set(opt).from(payslipContent).save();
                }
            }
        }
    </script>
</head>
<body class="flex h-screen overflow-hidden bg-slate-50 dark:bg-slate-900 text-slate-900 dark:text-slate-100 transition-colors duration-300" x-data="payrollApp()">

    <!-- MANDATORY SIDEBAR STRUCTURE -->
    <?php include '../includes/dashboard_sidebar.php'; ?>

        <!-- NOTIFICATIONS PANEL (SLIDE-OVER) - Starts hidden -->
        <div id="notif-panel" x-data="notificationsPanel()" x-init="fetchNotifications()" class="fixed inset-y-0 right-0 w-80 bg-white dark:bg-slate-950 shadow-2xl transform translate-x-full transition-transform duration-300 z-50 border-l border-slate-200 dark:border-slate-800" style="visibility: hidden;">
            <div class="h-16 flex items-center justify-between px-6 border-b border-slate-100 dark:border-slate-800">
                <div class="flex items-center gap-2">
                    <h3 class="text-lg font-bold text-slate-900 dark:text-white">Notifications</h3>
                    <span x-show="notifications.length > 0" class="px-2 py-0.5 text-xs font-bold bg-red-500 text-white rounded-full" x-text="notifications.length"></span>
                </div>
                <button id="notif-close" class="text-slate-500 hover:text-slate-900 dark:hover:text-white"><i data-lucide="x" class="w-5 h-5"></i></button>
            </div>
            <div class="p-4 space-y-3 overflow-y-auto h-[calc(100vh-64px)]">
                <!-- Loading State -->
                <div x-show="loading" class="flex items-center justify-center py-8">
                    <div class="animate-spin w-6 h-6 border-2 border-brand-500 border-t-transparent rounded-full"></div>
                </div>
                
                <!-- Empty State -->
                <div x-show="!loading && notifications.length === 0" class="text-center py-8">
                    <i data-lucide="bell-off" class="w-12 h-12 text-slate-300 mx-auto mb-4"></i>
                    <p class="text-sm text-slate-500">No notifications</p>
                </div>
                
                <!-- Notification Items -->
                <template x-for="notif in notifications" :key="notif.id">
                    <div class="p-3 rounded-lg border-l-4 transition-colors"
                        :class="{
                            'bg-green-50 dark:bg-green-900/10 border-green-500': notif.color === 'green',
                            'bg-amber-50 dark:bg-amber-900/10 border-amber-500': notif.color === 'amber',
                            'bg-red-50 dark:bg-red-900/10 border-red-500': notif.color === 'red',
                            'bg-blue-50 dark:bg-blue-900/10 border-blue-500': notif.color === 'blue',
                            'bg-brand-50 dark:bg-brand-900/10 border-brand-500': notif.color === 'brand'
                        }">
                        <div class="flex items-start gap-3">
                            <i :data-lucide="notif.icon" class="w-5 h-5 shrink-0 mt-0.5"
                                :class="{
                                    'text-green-600': notif.color === 'green',
                                    'text-amber-600': notif.color === 'amber',
                                    'text-red-600': notif.color === 'red',
                                    'text-blue-600': notif.color === 'blue',
                                    'text-brand-600': notif.color === 'brand'
                                }"></i>
                            <div class="flex-1 min-w-0">
                                <p class="text-sm font-bold text-slate-900 dark:text-white" x-text="notif.title"></p>
                                <p class="text-xs text-slate-600 dark:text-slate-400 mt-0.5" x-text="notif.message"></p>
                                <div class="mt-2 flex items-center gap-3">
                                    <a :href="notif.action_url" class="text-xs font-medium hover:underline"
                                        :class="{
                                            'text-green-600': notif.color === 'green',
                                            'text-amber-600': notif.color === 'amber',
                                            'text-red-600': notif.color === 'red',
                                            'text-blue-600': notif.color === 'blue',
                                            'text-brand-600': notif.color === 'brand'
                                        }" x-text="notif.action"></a>
                                    <span class="text-xs text-slate-400" x-text="formatTime(notif.timestamp)"></span>
                                </div>
                            </div>
                        </div>
                    </div>
                </template>
                
                <!-- Refresh Button -->
                <button @click="fetchNotifications()" x-show="!loading" class="w-full py-2 text-xs text-slate-500 hover:text-slate-700 flex items-center justify-center gap-2">
                    <i data-lucide="refresh-cw" class="w-3 h-3"></i> Refresh
                </button>
            </div>
        </div>
        
        <!-- Overlay -->
        <div id="overlay" class="fixed inset-0 bg-black/50 z-30 hidden"></div>

        <!-- MAIN CONTENT -->
        <div class="flex-1 flex flex-col h-full overflow-hidden w-full relative">
            
            <!-- Header -->
            <?php $page_title = 'Payroll Management'; include '../includes/dashboard_header.php'; ?>
            <!-- Payroll Sub-Header -->
            <?php include '../includes/payroll_header.php'; ?>

            <!-- Collapsed Toolbar (Expand Button) -->
            <div id="collapsed-toolbar" class="toolbar-hidden w-full bg-white dark:bg-slate-950 border-b border-slate-200 dark:border-slate-800 flex items-center px-6 shrink-0 shadow-sm z-20">
                <button id="sidebar-expand-btn" class="flex items-center gap-2 p-2 rounded-lg text-slate-500 hover:text-brand-600 dark:text-slate-400 dark:hover:text-white hover:bg-slate-100 dark:hover:bg-slate-800 transition-colors my-2">
                    <i data-lucide="menu" class="w-5 h-5"></i> <span class="text-sm font-medium">Show Menu</span>
                </button>
            </div>

            <!-- Main Scrollable Area -->
            <main class="flex-1 overflow-y-auto p-6 lg:p-8 bg-slate-50 dark:bg-slate-900 scroll-smooth">
                
                <!-- SUCCESS TOAST -->
                <div x-cloak x-show="runSuccess" x-transition class="fixed top-20 right-6 bg-green-600 text-white px-6 py-4 rounded-lg shadow-xl z-50 flex items-center gap-3">
                    <i data-lucide="check-circle" class="w-6 h-6"></i>
                    <div>
                        <p class="font-bold">Payroll Posted Successfully!</p>
                        <p class="text-xs opacity-90">Payslips generated and ledger updated.</p>
                    </div>
                </div>

                <!-- Navigation Tabs (Segmented Control Style) -->
                <div class="mb-8 overflow-x-auto pb-2">
                    <div class="flex gap-2 min-w-max p-1 bg-slate-100 dark:bg-slate-950/50 border border-slate-200 dark:border-slate-800 rounded-xl w-fit">
                        
                        <button @click="changeView('dashboard')" 
                            :class="view === 'dashboard' ? 'bg-white dark:bg-slate-800 text-brand-600 dark:text-brand-400 shadow-sm ring-1 ring-slate-950/5' : 'text-slate-500 dark:text-slate-400 hover:text-slate-900 dark:hover:text-white hover:bg-white/50 dark:hover:bg-slate-800/50'" 
                            class="px-4 py-2 text-sm font-bold rounded-lg transition-all flex items-center gap-2 whitespace-nowrap">
                            <i data-lucide="layout-grid" class="w-4 h-4"></i> Dashboard
                        </button>
                        
                        <button @click="changeView('run')" 
                            :class="view === 'run' ? 'bg-white dark:bg-slate-800 text-brand-600 dark:text-brand-400 shadow-sm ring-1 ring-slate-950/5' : 'text-slate-500 dark:text-slate-400 hover:text-slate-900 dark:hover:text-white hover:bg-white/50 dark:hover:bg-slate-800/50'" 
                            class="px-4 py-2 text-sm font-bold rounded-lg transition-all flex items-center gap-2 whitespace-nowrap">
                            <i data-lucide="play-circle" class="w-4 h-4"></i> Run
                        </button>
                        
                        <button @click="changeView('sheet')" 
                            :class="view === 'sheet' ? 'bg-white dark:bg-slate-800 text-brand-600 dark:text-brand-400 shadow-sm ring-1 ring-slate-950/5' : 'text-slate-500 dark:text-slate-400 hover:text-slate-900 dark:hover:text-white hover:bg-white/50 dark:hover:bg-slate-800/50'" 
                            class="px-4 py-2 text-sm font-bold rounded-lg transition-all flex items-center gap-2 whitespace-nowrap">
                            <i data-lucide="sheet" class="w-4 h-4"></i> Sheet
                        </button>
                        
                        <button @click="changeView('preview')" 
                            :class="view === 'preview' ? 'bg-white dark:bg-slate-800 text-brand-600 dark:text-brand-400 shadow-sm ring-1 ring-slate-950/5' : 'text-slate-500 dark:text-slate-400 hover:text-slate-900 dark:hover:text-white hover:bg-white/50 dark:hover:bg-slate-800/50'" 
                            class="px-4 py-2 text-sm font-bold rounded-lg transition-all flex items-center gap-2 whitespace-nowrap">
                            <i data-lucide="eye" class="w-4 h-4"></i> Preview
                        </button>
                        
                        <button @click="changeView('approval')" 
                            :class="view === 'approval' ? 'bg-white dark:bg-slate-800 text-brand-600 dark:text-brand-400 shadow-sm ring-1 ring-slate-950/5' : 'text-slate-500 dark:text-slate-400 hover:text-slate-900 dark:hover:text-white hover:bg-white/50 dark:hover:bg-slate-800/50'" 
                            class="px-4 py-2 text-sm font-bold rounded-lg transition-all flex items-center gap-2 whitespace-nowrap">
                            <i data-lucide="check-square" class="w-4 h-4"></i> Approvals
                        </button>
                        
                        <button @click="changeView('payslips')" 
                            :class="view === 'payslips' ? 'bg-white dark:bg-slate-800 text-brand-600 dark:text-brand-400 shadow-sm ring-1 ring-slate-950/5' : 'text-slate-500 dark:text-slate-400 hover:text-slate-900 dark:hover:text-white hover:bg-white/50 dark:hover:bg-slate-800/50'" 
                            class="px-4 py-2 text-sm font-bold rounded-lg transition-all flex items-center gap-2 whitespace-nowrap">
                            <i data-lucide="file-text" class="w-4 h-4"></i> Payslips
                        </button>
                        
                        <button @click="changeView('reports')" 
                            :class="view === 'reports' ? 'bg-white dark:bg-slate-800 text-brand-600 dark:text-brand-400 shadow-sm ring-1 ring-slate-950/5' : 'text-slate-500 dark:text-slate-400 hover:text-slate-900 dark:hover:text-white hover:bg-white/50 dark:hover:bg-slate-800/50'" 
                            class="px-4 py-2 text-sm font-bold rounded-lg transition-all flex items-center gap-2 whitespace-nowrap">
                            <i data-lucide="bar-chart-3" class="w-4 h-4"></i> Reports
                        </button>
                        
                        <button @click="changeView('deductions')" 
                            :class="view === 'deductions' ? 'bg-white dark:bg-slate-800 text-brand-600 dark:text-brand-400 shadow-sm ring-1 ring-slate-950/5' : 'text-slate-500 dark:text-slate-400 hover:text-slate-900 dark:hover:text-white hover:bg-white/50 dark:hover:bg-slate-800/50'" 
                            class="px-4 py-2 text-sm font-bold rounded-lg transition-all flex items-center gap-2 whitespace-nowrap">
                            <i data-lucide="receipt" class="w-4 h-4"></i> Deductions
                        </button>
                    </div>
                </div>

                <!-- VIEW 1: PAYROLL DASHBOARD -->
                <div x-show="view === 'dashboard'" x-transition.opacity>
                    <!-- Summary Cards -->
                    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-5 gap-6 mb-8">
                        <div class="bg-white dark:bg-slate-950 p-6 rounded-xl border border-slate-200 dark:border-slate-800 shadow-sm col-span-1 lg:col-span-2">
                            <div class="flex justify-between items-start mb-4">
                                <div>
                                    <p class="text-sm text-slate-500 dark:text-slate-400">Current Payroll Period</p>
                                    <h3 class="text-2xl font-bold text-slate-900 dark:text-white mt-1" x-text="new Date(currentPeriod.year, currentPeriod.month - 1).toLocaleString('default', { month: 'long', year: 'numeric' })"></h3>
                                </div>
                                <span class="px-2 py-1 rounded text-xs font-bold uppercase tracking-wider" 
                                    :class="payrollRun ? (payrollRun.status === 'locked' ? 'bg-green-100 text-green-700' : 'bg-amber-100 text-amber-700') : 'bg-slate-100 text-slate-700'"
                                    x-text="payrollRun ? payrollRun.status : 'Not Started'"></span>
                            </div>
                            <div class="h-2 w-full bg-slate-100 dark:bg-slate-800 rounded-full overflow-hidden">
                                <div class="h-full bg-amber-500" :style="'width: ' + (payrollRun ? '50%' : '0%')"></div>
                            </div>
                            <p class="text-xs text-slate-500 mt-2" x-text="payrollRun ? 'Draft generated, pending lock.' : 'No active run for this period.'"></p>
                        </div>
                        <div class="bg-white dark:bg-slate-950 p-6 rounded-xl border border-slate-200 dark:border-slate-800 shadow-sm overflow-hidden">
                            <p class="text-xs font-bold text-slate-500 uppercase truncate">Gross Pay</p>
                            <h3 class="text-xl font-bold text-slate-900 dark:text-white mt-2 truncate" x-text="formatCurrency(totals.gross)" :title="formatCurrency(totals.gross)"></h3>
                        </div>
                        <div class="bg-white dark:bg-slate-950 p-6 rounded-xl border border-slate-200 dark:border-slate-800 shadow-sm overflow-hidden">
                            <p class="text-xs font-bold text-red-500 uppercase truncate">Total Deductions</p>
                            <h3 class="text-xl font-bold text-red-600 dark:text-red-400 mt-2 truncate" x-text="formatCurrency(totals.deductions)" :title="formatCurrency(totals.deductions)"></h3>
                        </div>
                        <div class="bg-white dark:bg-slate-950 p-6 rounded-xl border-l-4 border-l-green-500 border-y border-r border-slate-200 dark:border-slate-800 shadow-sm overflow-hidden">
                            <p class="text-xs font-bold text-green-600 uppercase truncate">Total Net Pay</p>
                            <h3 class="text-xl font-bold text-green-700 dark:text-green-400 mt-2 truncate" x-text="formatCurrency(totals.net)" :title="formatCurrency(totals.net)"></h3>
                        </div>
                    </div>

                    <!-- Actions -->
                    <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                        <button @click="changeView('run')" class="p-6 bg-slate-900 dark:bg-white text-white dark:text-slate-900 rounded-xl hover:shadow-xl transition-all flex flex-col items-center justify-center gap-3">
                            <i data-lucide="play-circle" class="w-8 h-8"></i>
                            <span class="font-bold text-lg">Continue Payroll Run</span>
                        </button>
                        <button @click="changeView('sheet')" class="p-6 bg-white dark:bg-slate-950 border border-slate-200 dark:border-slate-800 rounded-xl hover:border-brand-500 transition-colors flex flex-col items-center justify-center gap-3">
                            <i data-lucide="sheet" class="w-8 h-8 text-slate-400"></i>
                            <span class="font-bold text-lg dark:text-white">Review Payroll Sheet</span>
                        </button>
                        <button @click="changeView('payslips')" class="p-6 bg-white dark:bg-slate-950 border border-slate-200 dark:border-slate-800 rounded-xl hover:border-brand-500 transition-colors flex flex-col items-center justify-center gap-3">
                            <i data-lucide="users" class="w-8 h-8 text-slate-400"></i>
                            <span class="font-bold text-lg dark:text-white">Employee Payslips</span>
                        </button>
                    </div>
                </div>

                <!-- VIEW 2: RUN PAYROLL (SETUP) -->
                <div x-show="view === 'run'" x-cloak x-transition.opacity class="max-w-4xl mx-auto">
                    <div class="bg-white dark:bg-slate-950 rounded-xl border border-slate-200 dark:border-slate-800 shadow-sm p-8">
                        <h2 class="text-xl font-bold text-slate-900 dark:text-white mb-6 flex items-center gap-2"><i data-lucide="settings" class="w-5 h-5 text-brand-600"></i> Payroll Initiation</h2>
                        
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mb-8">
                            <div>
                                <label class="block text-sm font-medium text-slate-700 dark:text-slate-300 mb-2">Month</label>
                                <select x-model="currentPeriod.month" class="w-full rounded-lg border-slate-300 dark:border-slate-700 bg-white dark:bg-slate-800 text-slate-900 dark:text-white focus:ring-brand-500 focus:border-brand-500 transition-colors shadow-sm text-sm p-2.5">
                                    <option value="1">January</option>
                                    <option value="2">February</option>
                                    <option value="3">March</option>
                                    <option value="4">April</option>
                                    <option value="5">May</option>
                                    <option value="6">June</option>
                                    <option value="7">July</option>
                                    <option value="8">August</option>
                                    <option value="9">September</option>
                                    <option value="10">October</option>
                                    <option value="11">November</option>
                                    <option value="12">December</option>
                                </select>
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-slate-700 dark:text-slate-300 mb-2">Year</label>
                                <input type="number" x-model="currentPeriod.year" class="w-full rounded-lg border-slate-300 dark:border-slate-700 bg-white dark:bg-slate-800 text-slate-900 dark:text-white focus:ring-brand-500 focus:border-brand-500 transition-colors shadow-sm text-sm p-2.5">
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-slate-700 dark:text-slate-300 mb-2">Payroll Type</label>
                                <select x-model="payrollType" class="w-full rounded-lg border-slate-300 dark:border-slate-700 bg-white dark:bg-slate-800 text-slate-900 dark:text-white focus:ring-brand-500 focus:border-brand-500 transition-colors shadow-sm text-sm p-2.5">
                                    <option value="regular">Regular Monthly</option>
                                </select>
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-slate-700 dark:text-slate-300 mb-2">Department Filter</label>
                                <select x-model="selectedDept" class="w-full rounded-lg border-slate-300 dark:border-slate-700 bg-white dark:bg-slate-800 text-slate-900 dark:text-white focus:ring-brand-500 focus:border-brand-500 transition-colors shadow-sm text-sm p-2.5">
                                    <option value="">All Departments</option>
                                    <template x-for="dept in deptList" :key="dept">
                                        <option :value="dept" x-text="dept"></option>
                                    </template>
                                </select>
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-slate-700 dark:text-slate-300 mb-2">Salary Category Filter</label>
                                <select x-model="selectedCat" class="w-full rounded-lg border-slate-300 dark:border-slate-700 bg-white dark:bg-slate-800 text-slate-900 dark:text-white focus:ring-brand-500 focus:border-brand-500 transition-colors shadow-sm text-sm p-2.5">
                                    <option value="">All Categories</option>
                                     <template x-for="cat in catList" :key="cat.id">
                                        <option :value="cat.id" x-text="cat.name"></option>
                                    </template>
                                </select>
                            </div>
                        </div>

                        <!-- System Checks -->
                        <div class="mb-8">
                            <h3 class="text-sm font-bold text-slate-500 uppercase tracking-wide mb-4">Pre-Run Validation Checklist</h3>
                            <div class="space-y-3">
                                <!-- Active Employees -->
                                <div class="flex items-center gap-3 p-3 rounded-lg border transition-colors"
                                    :class="checklist.active_employees > 0 ? 'bg-green-50 dark:bg-green-900/10 border-green-100 dark:border-green-900/30' : 'bg-red-50 dark:bg-red-900/10 border-red-100 dark:border-red-900/30'">
                                    <i :data-lucide="checklist.active_employees > 0 ? 'check-circle' : 'x-circle'" 
                                       class="w-5 h-5" :class="checklist.active_employees > 0 ? 'text-green-600' : 'text-red-600'"></i>
                                    <span class="text-sm" :class="checklist.active_employees > 0 ? 'text-green-800 dark:text-green-300' : 'text-red-800 dark:text-red-300'"
                                          x-text="checklist.active_employees + ' Active Employees with Salary Category'"></span>
                                </div>
                                
                                <!-- Statutory Settings -->
                                <div class="flex items-center gap-3 p-3 rounded-lg border transition-colors"
                                    :class="checklist.statutory_set ? 'bg-green-50 dark:bg-green-900/10 border-green-100 dark:border-green-900/30' : 'bg-amber-50 dark:bg-amber-900/10 border-amber-100 dark:border-amber-900/30'">
                                    <i :data-lucide="checklist.statutory_set ? 'check-circle' : 'alert-circle'" 
                                       class="w-5 h-5" :class="checklist.statutory_set ? 'text-green-600' : 'text-amber-600'"></i>
                                    <span class="text-sm" :class="checklist.statutory_set ? 'text-green-800 dark:text-green-300' : 'text-amber-800 dark:text-amber-300'"
                                          x-text="checklist.statutory_set ? 'Statutory Settings Configured' : 'Statutory Settings Not Configured (Defaults used)'"></span>
                                </div>

                                <!-- Missing Bank -->
                                <div class="flex items-center gap-3 p-3 rounded-lg border transition-colors"
                                    :class="checklist.missing_bank === 0 ? 'bg-green-50 dark:bg-green-900/10 border-green-100 dark:border-green-900/30' : 'bg-amber-50 dark:bg-amber-900/10 border-amber-100 dark:border-amber-900/30'">
                                    <i :data-lucide="checklist.missing_bank === 0 ? 'check-circle' : 'alert-circle'" 
                                       class="w-5 h-5" :class="checklist.missing_bank === 0 ? 'text-green-600' : 'text-amber-600'"></i>
                                    <span class="text-sm" :class="checklist.missing_bank === 0 ? 'text-green-800 dark:text-green-300' : 'text-amber-800 dark:text-amber-300'"
                                          x-text="checklist.missing_bank === 0 ? 'All Employees Have Bank Details' : checklist.missing_bank + ' Employees Missing Bank Details'"></span>
                                </div>

                                <!-- Missing Category -->
                                <div class="flex items-center gap-3 p-3 rounded-lg border transition-colors"
                                    :class="checklist.missing_category === 0 ? 'bg-green-50 dark:bg-green-900/10 border-green-100 dark:border-green-900/30' : 'bg-red-50 dark:bg-red-900/10 border-red-100 dark:border-red-900/30'">
                                    <i :data-lucide="checklist.missing_category === 0 ? 'check-circle' : 'x-circle'" 
                                       class="w-5 h-5" :class="checklist.missing_category === 0 ? 'text-green-600' : 'text-red-600'"></i>
                                    <span class="text-sm" :class="checklist.missing_category === 0 ? 'text-green-800 dark:text-green-300' : 'text-red-800 dark:text-red-300'"
                                          x-text="checklist.missing_category === 0 ? 'All Employees Have Valid Categories' : checklist.missing_category + ' Employees Missing Salary Category'"></span>
                                </div>
                            </div>
                        </div>

                        <div class="flex justify-end pt-6 border-t border-slate-100 dark:border-slate-800">
                            <button @click="runPayroll()" :disabled="loading" class="px-6 py-3 bg-brand-600 hover:bg-brand-700 text-white font-bold rounded-xl shadow-lg transition-colors flex items-center gap-2">
                                <span x-show="!loading">Generate Payroll Draft</span>
                                <span x-show="loading">Processing...</span>
                                <i x-show="!loading" data-lucide="arrow-right" class="w-4 h-4"></i>
                            </button>
                        </div>
                    </div>
                </div>

                <!-- VIEW 3: PAYROLL SHEET (CORE) -->
                <div x-show="view === 'sheet'" x-cloak x-transition.opacity>
                    <div class="flex justify-between items-center mb-4">
                        <h2 class="text-lg font-bold text-slate-900 dark:text-white">Payroll Sheet: <?php echo date('M Y'); ?> (Draft)</h2>
                        <div class="flex items-center gap-2">
                            <!-- Export Buttons -->
                            <button @click="exportToExcel()" class="flex items-center gap-1.5 px-3 py-2 bg-green-50 text-green-700 rounded-lg hover:bg-green-100 text-sm font-medium transition-colors" title="Export to Excel">
                                <i data-lucide="file-spreadsheet" class="w-4 h-4"></i>
                                <span class="hidden sm:inline">Excel</span>
                            </button>
                            <button @click="exportToPDF()" class="flex items-center gap-1.5 px-3 py-2 bg-red-50 text-red-700 rounded-lg hover:bg-red-100 text-sm font-medium transition-colors" title="Export to PDF">
                                <i data-lucide="file-text" class="w-4 h-4"></i>
                                <span class="hidden sm:inline">PDF</span>
                            </button>
                            <button @click="printPayroll()" class="flex items-center gap-1.5 px-3 py-2 bg-slate-100 text-slate-700 rounded-lg hover:bg-slate-200 text-sm font-medium transition-colors" title="Print">
                                <i data-lucide="printer" class="w-4 h-4"></i>
                                <span class="hidden sm:inline">Print</span>
                            </button>
                            <button @click="changeView('preview')" class="px-4 py-2 bg-brand-600 text-white rounded-lg hover:bg-brand-700 text-sm font-bold shadow-md">Proceed to Validation</button>
                        </div>
                    </div>

                    <div class="bg-white dark:bg-slate-950 rounded-xl border border-slate-200 dark:border-slate-800 shadow-sm payroll-sheet-container">
                        <table class="w-full text-left text-xs payroll-table">
                            <thead class="bg-slate-100 dark:bg-slate-900 text-slate-600 dark:text-slate-400 font-bold sticky-header shadow-sm">
                                <tr>
                                    <!-- S/N Column -->
                                    <th class="sticky-col-sn bg-slate-100 dark:bg-slate-900 w-[50px] text-center">S/N</th>
                                    <!-- Sticky Columns -->
                                    <th class="sticky-col-left sticky-corner bg-slate-100 dark:bg-slate-900 min-w-[200px]">
                                        <div class="flex items-center justify-between">
                                            <span x-text="hideEmployeeNames ? 'Employee ID' : 'Employee'"></span>
                                            <button @click="hideEmployeeNames = !hideEmployeeNames" 
                                                class="ml-2 p-1 rounded hover:bg-slate-200 dark:hover:bg-slate-800 transition-colors"
                                                :title="hideEmployeeNames ? 'Show Names' : 'Hide Names (Show ID Only)'">
                                                <i :data-lucide="hideEmployeeNames ? 'eye' : 'eye-off'" class="w-3 h-3 text-slate-500"></i>
                                            </button>
                                        </div>
                                    </th>
                                    
                                    <!-- Employee Info Toggle Column -->
                                    <th class="bg-slate-50 dark:bg-slate-900 border-l border-slate-200 dark:border-slate-700 cursor-pointer hover:bg-slate-100 dark:hover:bg-slate-800" 
                                        @click="showEmployeeInfo = !showEmployeeInfo" title="Toggle Employee Details">
                                        <div class="flex items-center gap-1 text-slate-500">
                                            <i :data-lucide="showEmployeeInfo ? 'chevron-down' : 'chevron-right'" class="w-3 h-3"></i>
                                            <span class="text-[10px]">Info</span>
                                        </div>
                                    </th>
                                    
                                    <!-- Collapsible Employee Info Columns -->
                                    <th x-show="showEmployeeInfo" x-transition class="bg-slate-50 dark:bg-slate-900 min-w-[120px] text-slate-600">Designation</th>
                                    <th x-show="showEmployeeInfo" x-transition class="bg-slate-50 dark:bg-slate-900 min-w-[100px] text-slate-600">Bank</th>
                                    <th x-show="showEmployeeInfo" x-transition class="bg-slate-50 dark:bg-slate-900 min-w-[120px] text-slate-600 border-r border-slate-200 dark:border-slate-700">Account No</th>
                                    
                                    <!-- Earnings Group (Fixed columns) -->
                                    <th class="bg-green-50/50 dark:bg-green-900/10 border-l border-slate-200 dark:border-slate-800 min-w-[100px]">Basic</th>
                                    <th class="bg-green-50/50 dark:bg-green-900/10 min-w-[100px]">Housing</th>
                                    <th class="bg-green-50/50 dark:bg-green-900/10 min-w-[100px]">Transport</th>
                                    <th class="bg-green-50/50 dark:bg-green-900/10 min-w-[100px] text-emerald-600 dark:text-emerald-400">Others</th>
                                    <th x-show="statutoryFlags.showBonus" class="bg-green-50/50 dark:bg-green-900/10 min-w-[100px] text-green-700 dark:text-green-400">Bonus</th>
                                    <th x-show="overtimeConfig.enabled || dataHasOvertime" class="bg-orange-50/50 dark:bg-orange-900/10 min-w-[80px] text-orange-600 dark:text-orange-400">OT</th>
                                    <th class="bg-slate-200 dark:bg-slate-800 min-w-[120px]">Gross Pay</th>
                                    
                                    <!-- Deductions Group -->
                                    <th x-show="statutoryFlags.paye" class="bg-red-50/50 dark:bg-red-900/10 border-l border-slate-200 dark:border-slate-800 min-w-[100px]">PAYE</th>
                                    <th x-show="statutoryFlags.pension" class="bg-red-50/50 dark:bg-red-900/10 min-w-[100px]">Pension</th>
                                    <th x-show="statutoryFlags.nhf" class="bg-red-50/50 dark:bg-red-900/10 min-w-[100px]">NHF</th>
                                    <th x-show="statutoryFlags.nhis" class="bg-red-50/50 dark:bg-red-900/10 min-w-[100px]">NHIS</th>
                                    <th x-show="statutoryFlags.showLateness" class="bg-red-50/50 dark:bg-red-900/10 min-w-[100px] text-amber-600 dark:text-amber-400">Lateness</th>
                                    <th x-show="statutoryFlags.showLoan" class="bg-red-50/50 dark:bg-red-900/10 min-w-[100px] text-red-700 dark:text-red-400">Loan</th>
                                    <th x-show="statutoryFlags.showDeductions" class="bg-red-50/50 dark:bg-red-900/10 min-w-[100px] text-red-700 dark:text-red-400">Deductions</th>
                                    
                                    <!-- Summary -->
                                    <th class="bg-brand-50 dark:bg-brand-900/20 border-l border-slate-200 dark:border-slate-800 text-brand-700 dark:text-white min-w-[140px]">Net Pay</th>
                                    <th class="bg-slate-100 dark:bg-slate-900 min-w-[80px] text-center">Actions</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-100 dark:divide-slate-800 text-slate-700 dark:text-slate-300">
                                <template x-for="(emp, empIndex) in sheetData" :key="emp.id">
                                    <tr class="hover:bg-slate-50 dark:hover:bg-slate-900">
                                        <!-- S/N Cell -->
                                        <td class="bg-white dark:bg-slate-950 text-center font-medium text-slate-500" x-text="empIndex + 1"></td>
                                        <td class="sticky-col-left bg-white dark:bg-slate-950 border-r border-slate-200 dark:border-slate-700 font-medium">
                                            <template x-if="!hideEmployeeNames">
                                                <div>
                                                    <div x-text="emp.first_name + ' ' + emp.last_name"></div>
                                                    <span class="text-[10px] text-slate-400" x-text="emp.payroll_id || 'N/A'"></span>
                                                </div>
                                            </template>
                                            <template x-if="hideEmployeeNames">
                                                <div class="font-mono text-slate-700 dark:text-slate-300" x-text="emp.payroll_id || 'N/A'"></div>
                                            </template>
                                        </td>
                                        
                                        <!-- Employee Info Toggle Cell (placeholder) -->
                                        <td class="bg-white dark:bg-slate-950 border-l border-slate-200 dark:border-slate-700"></td>
                                        
                                        <!-- Collapsible Employee Info Cells -->
                                        <td x-show="showEmployeeInfo" x-transition class="bg-slate-50/50 dark:bg-slate-900/30 text-slate-600 text-[10px]" x-text="emp.designation || '—'"></td>
                                        <td x-show="showEmployeeInfo" x-transition class="bg-slate-50/50 dark:bg-slate-900/30 text-slate-600 text-[10px]" x-text="emp.bank_name || '—'"></td>
                                        <td x-show="showEmployeeInfo" x-transition class="bg-slate-50/50 dark:bg-slate-900/30 text-slate-600 text-[10px] border-r border-slate-200 dark:border-slate-700" x-text="emp.account_number || '—'"></td>
                                        
                                        <!-- Earnings (Fixed columns) -->
                                        <td class="bg-green-50/5 dark:bg-transparent" x-text="formatCurrency(emp.breakdown.basic || 0)"></td>
                                        <td class="bg-green-50/5 dark:bg-transparent" x-text="formatCurrency(emp.breakdown.housing || 0)"></td>
                                        <td class="bg-green-50/5 dark:bg-transparent" x-text="formatCurrency(emp.breakdown.transport || 0)"></td>
                                        <td class="bg-green-50/5 dark:bg-transparent text-emerald-600" x-text="formatCurrency(getOtherAllowances(emp))"></td>
                                        <td x-show="statutoryFlags.showBonus" class="bg-green-50/5 dark:bg-transparent text-green-600" x-text="formatCurrency(emp.breakdown.bonus || 0)"></td>
                                        <!-- OT Column (shows overtime PAY value) -->
                                        <td x-show="overtimeConfig.enabled || dataHasOvertime" class="bg-orange-50/10 dark:bg-transparent text-orange-600" x-text="formatCurrency(Number(emp.breakdown.overtime_pay || 0))"></td>
                                        <td class="font-bold bg-slate-50 dark:bg-slate-900" x-text="formatCurrency(emp.gross_salary)"></td>
                                        
                                        <!-- Deductions -->
                                        <td x-show="statutoryFlags.paye" class="bg-red-50/5 dark:bg-transparent" x-text="formatCurrency(emp.breakdown.paye)"></td>
                                        <td x-show="statutoryFlags.pension" class="bg-red-50/5 dark:bg-transparent" x-text="formatCurrency(emp.breakdown.pension)"></td>
                                        <td x-show="statutoryFlags.nhf" class="bg-red-50/5 dark:bg-transparent" x-text="formatCurrency(emp.breakdown.nhf || 0)"></td>
                                        <td x-show="statutoryFlags.nhis" class="bg-red-50/5 dark:bg-transparent" x-text="formatCurrency(emp.breakdown.nhis)"></td>
                                        <td x-show="statutoryFlags.showLateness" class="bg-red-50/5 dark:bg-transparent text-amber-600" x-text="formatCurrency(emp.breakdown.lateness || 0)"></td>
                                        <td x-show="statutoryFlags.showLoan" class="bg-red-50/5 dark:bg-transparent text-red-500" x-text="formatCurrency(emp.breakdown.loan)"></td>
                                        <td x-show="statutoryFlags.showDeductions" class="bg-red-50/5 dark:bg-transparent text-red-500" x-text="formatCurrency(emp.breakdown.custom_deductions || 0)"></td>
                                        
                                        <!-- Net -->
                                        <td class="font-bold bg-brand-50/30 text-brand-700 dark:text-white border-l border-slate-200 dark:border-slate-800" x-text="formatCurrency(emp.net_pay)"></td>
                                        <!-- Actions -->
                                        <td class="text-center">
                                            <div class="flex items-center justify-center gap-1">
                                                <button @click="viewPayslip(emp)" class="px-2 py-1 text-xs bg-slate-100 text-slate-600 rounded hover:bg-slate-200 transition-colors" title="View Payslip">
                                                    <i data-lucide="file-text" class="w-3 h-3 inline"></i>
                                                </button>
                                                <button @click="openAdjustment(emp)" class="px-2 py-1 text-xs bg-brand-50 text-brand-600 rounded hover:bg-brand-100 transition-colors" title="Add Adjustment">
                                                    <i data-lucide="plus-circle" class="w-3 h-3 inline"></i>
                                                </button>
                                            </div>
                                        </td>
                                    </tr>
                                </template>
                                <tr x-show="sheetData.length === 0" class="empty-row">
                                    <td colspan="14" class="p-8 text-center text-slate-500 italic">No payroll data generated for this period. Run payroll to see entries.</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                    
                    <!-- ADJUSTMENT MODAL -->
                    <div x-show="adjustmentModalOpen" x-cloak class="fixed inset-0 z-50 flex items-center justify-center">
                        <div class="absolute inset-0 bg-slate-900/50 backdrop-blur-sm" @click="adjustmentModalOpen = false"></div>
                        <div class="relative bg-white dark:bg-slate-950 rounded-xl shadow-2xl w-full max-w-md mx-4 max-h-[90vh] flex flex-col overflow-hidden" @click.stop>
                            <div class="px-5 py-3 border-b border-slate-100 dark:border-slate-800 flex items-center justify-between flex-shrink-0">
                                <h3 class="text-lg font-bold text-slate-900 dark:text-white">Add One-Time Adjustment</h3>
                                <button @click="adjustmentModalOpen = false" class="text-slate-400 hover:text-slate-600">
                                    <i data-lucide="x" class="w-5 h-5"></i>
                                </button>
                            </div>
                            <div class="p-5 space-y-3 overflow-y-auto flex-1">
                                <div class="p-2.5 bg-slate-50 dark:bg-slate-900 rounded-lg">
                                    <p class="text-xs text-slate-500">Employee</p>
                                    <p class="font-bold text-slate-900 dark:text-white" x-text="adjustmentForm.employee_name"></p>
                                </div>
                                
                                <div>
                                    <label class="block text-sm font-medium text-slate-700 dark:text-slate-300 mb-1">Type</label>
                                    <div class="grid grid-cols-3 gap-2">
                                        <label class="flex items-center gap-2 p-2.5 rounded-lg border cursor-pointer transition-colors" :class="adjustmentForm.type === 'bonus' ? 'border-green-500 bg-green-50 dark:bg-green-900/20' : 'border-slate-200 dark:border-slate-700'">
                                            <input type="radio" x-model="adjustmentForm.type" value="bonus" class="text-green-600">
                                            <span class="text-sm font-medium">Bonus</span>
                                        </label>
                                        <label class="flex items-center gap-2 p-2.5 rounded-lg border cursor-pointer transition-colors" :class="adjustmentForm.type === 'deduction' ? 'border-red-500 bg-red-50 dark:bg-red-900/20' : 'border-slate-200 dark:border-slate-700'">
                                            <input type="radio" x-model="adjustmentForm.type" value="deduction" class="text-red-600">
                                            <span class="text-sm font-medium">Deduction</span>
                                        </label>
                                        <label class="flex items-center gap-2 p-2.5 rounded-lg border cursor-pointer transition-colors" :class="adjustmentForm.type === 'overtime' ? 'border-orange-500 bg-orange-50 dark:bg-orange-900/20' : 'border-slate-200 dark:border-slate-700'">
                                            <input type="radio" x-model="adjustmentForm.type" value="overtime" class="text-orange-600">
                                            <span class="text-sm font-medium">Overtime</span>
                                        </label>
                                    </div>
                                </div>
                                
                                <!-- Overtime Section -->
                                <div x-show="adjustmentForm.type === 'overtime'" class="p-4 bg-orange-50 dark:bg-orange-900/20 rounded-lg border border-orange-200 dark:border-orange-800 space-y-3">
                                    <!-- Loading indicator -->
                                    <div x-show="currentOTConfig.loading" class="flex items-center gap-2 text-sm text-orange-600">
                                        <svg class="animate-spin w-4 h-4" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"></path></svg>
                                        Loading shift configuration...
                                    </div>
                                    
                                    <template x-if="!currentOTConfig.loading">
                                        <div class="space-y-3">
                                            <!-- Shift indicator -->
                                            <div x-show="currentOTConfig.shiftName" class="flex items-center gap-2 text-xs text-slate-600 dark:text-slate-400 bg-white dark:bg-slate-800 rounded-md px-2 py-1 border border-slate-200 dark:border-slate-700">
                                                <i data-lucide="clock" class="w-3 h-3"></i>
                                                <span>Shift: <strong x-text="currentOTConfig.shiftName"></strong></span>
                                            </div>
                                            
                                            <label class="block text-sm font-medium text-slate-700 dark:text-slate-300 mb-2">Overtime Hours</label>
                                            <div class="flex items-center gap-3">
                                                <input type="number" x-model.number="adjustmentForm.overtime_hours" @input="$nextTick(() => calculateOvertimePay())" class="flex-1 rounded-lg border-slate-300 dark:border-slate-700 bg-white dark:bg-slate-800 p-2 text-sm" placeholder="0" step="0.5" min="0">
                                                <span class="text-sm text-slate-500">hours</span>
                                            </div>
                                            
                                            <!-- Live Calculation Preview -->
                                            <div x-show="adjustmentForm.overtime_hours > 0" class="p-3 bg-white dark:bg-slate-800 rounded-lg border border-orange-100 dark:border-orange-900">
                                                <div class="flex justify-between text-sm mb-1">
                                                    <span class="text-slate-500">Hourly Rate:</span>
                                                    <span class="font-medium" x-text="formatCurrency(adjustmentForm.hourly_rate || 0)"></span>
                                                </div>
                                                <div class="flex justify-between text-sm mb-1">
                                                    <span class="text-slate-500">OT Rate (<span x-text="currentOTConfig.rate"></span>x):</span>
                                                    <span class="font-medium" x-text="formatCurrency((adjustmentForm.hourly_rate || 0) * currentOTConfig.rate)"></span>
                                                </div>
                                                <div class="flex justify-between text-sm font-bold text-orange-600 pt-2 border-t border-orange-100 dark:border-orange-800">
                                                    <span>Total OT Pay:</span>
                                                    <span x-text="formatCurrency((adjustmentForm.overtime_hours || 0) * (adjustmentForm.hourly_rate || 0) * currentOTConfig.rate)"></span>
                                                </div>
                                            </div>
                                            
                                            <p class="text-xs text-orange-600">
                                                <i data-lucide="info" class="w-3 h-3 inline"></i>
                                                Overtime calculated at <strong x-text="currentOTConfig.rate + 'x'"></strong> hourly rate. 
                                                Hourly = Gross ÷ (<span x-text="currentOTConfig.dailyHours"></span>hrs × <span x-text="currentOTConfig.monthlyDays"></span>days)
                                            </p>
                                        </div>
                                    </template>
                                </div>
                                
                                <div x-show="adjustmentForm.type === 'bonus'">
                                    <label class="block text-sm font-medium text-slate-700 dark:text-slate-300 mb-1">Select Bonus Type</label>
                                    <div class="relative">
                                        <select x-model="adjustmentForm.name" @change="if(adjustmentForm.name === 'Others') adjustmentForm.customName = ''" class="w-full rounded-lg border-slate-300 dark:border-slate-700 bg-white dark:bg-slate-800 p-2 text-sm">
                                            <option value="">-- Select a bonus type --</option>
                                            <optgroup label="Performance & Productivity">
                                                <template x-for="bonus in masterBonuses.slice(0, 7)"><option :value="bonus" x-text="bonus"></option></template>
                                            </optgroup>
                                            <optgroup label="Service-Related">
                                                <template x-for="bonus in masterBonuses.slice(7, 13)"><option :value="bonus" x-text="bonus"></option></template>
                                            </optgroup>
                                            <optgroup label="Time-Based & Special Occasion">
                                                <template x-for="bonus in masterBonuses.slice(13, 18)"><option :value="bonus" x-text="bonus"></option></template>
                                            </optgroup>
                                            <optgroup label="Sales, Revenue & Commission">
                                                <template x-for="bonus in masterBonuses.slice(18, 23)"><option :value="bonus" x-text="bonus"></option></template>
                                            </optgroup>
                                            <optgroup label="Attendance, Shift & Work-Condition">
                                                <template x-for="bonus in masterBonuses.slice(23, 28)"><option :value="bonus" x-text="bonus"></option></template>
                                            </optgroup>
                                            <optgroup label="Management & Responsibility">
                                                <template x-for="bonus in masterBonuses.slice(28, 33)"><option :value="bonus" x-text="bonus"></option></template>
                                            </optgroup>
                                            <optgroup label="Risk, Skill & Location">
                                                <template x-for="bonus in masterBonuses.slice(33, 38)"><option :value="bonus" x-text="bonus"></option></template>
                                            </optgroup>
                                            <optgroup label="Special One-Off & Discretionary">
                                                <template x-for="bonus in masterBonuses.slice(38)"><option :value="bonus" x-text="bonus"></option></template>
                                            </optgroup>
                                        </select>
                                    </div>
                                    <!-- Custom name input when 'Others' is selected -->
                                    <div x-show="adjustmentForm.name === 'Others'" class="mt-2">
                                        <input type="text" x-model="adjustmentForm.customName" class="w-full rounded-lg border-slate-300 dark:border-slate-700 bg-white dark:bg-slate-800 p-2 text-sm" placeholder="Enter custom bonus name...">
                                    </div>
                                </div>
                                
                                <div x-show="adjustmentForm.type === 'deduction'">
                                    <label class="block text-sm font-medium text-slate-700 dark:text-slate-300 mb-1">Deduction Type</label>
                                    <div class="relative">
                                        <!-- Selected deduction display -->
                                        <div x-show="adjustmentForm.name && adjustmentForm.name !== 'Others'" class="flex items-center gap-2 p-2 bg-red-50 dark:bg-red-900/20 rounded-lg border border-red-200 dark:border-red-800">
                                            <span class="flex-1 text-sm font-medium text-red-700 dark:text-red-300" x-text="adjustmentForm.name"></span>
                                            <button type="button" @click="adjustmentForm.name = ''; deductionSearch = ''" class="text-red-500 hover:text-red-700">
                                                <i data-lucide="x" class="w-4 h-4"></i>
                                            </button>
                                        </div>
                                        <!-- Search input -->
                                        <div x-show="!adjustmentForm.name || adjustmentForm.name === 'Others'">
                                            <input type="text" 
                                                x-model="deductionSearch" 
                                                @input="filterDeductions()"
                                                @focus="showDeductionList = true; filterDeductions()"
                                                @click.stop
                                                class="w-full rounded-lg border-slate-300 dark:border-slate-700 bg-white dark:bg-slate-800 p-2 text-sm" 
                                                placeholder="Search deduction type...">
                                            <!-- Dropdown list -->
                                            <div x-show="showDeductionList && filteredDeductions.length > 0" 
                                                @click.away="showDeductionList = false"
                                                class="absolute z-50 w-full mt-1 bg-white dark:bg-slate-900 rounded-lg shadow-lg border border-slate-200 dark:border-slate-700 max-h-48 overflow-y-auto">
                                                <template x-for="ded in filteredDeductions" :key="ded">
                                                    <button type="button" @click="selectDeduction(ded)" 
                                                        class="w-full px-3 py-2 text-left text-sm hover:bg-slate-100 dark:hover:bg-slate-800 transition-colors"
                                                        x-text="ded"></button>
                                                </template>
                                            </div>
                                        </div>
                                    </div>
                                    <!-- Custom name input when 'Others' is selected -->
                                    <div x-show="adjustmentForm.name === 'Others'" class="mt-2">
                                        <input type="text" x-model="adjustmentForm.customName" class="w-full rounded-lg border-slate-300 dark:border-slate-700 bg-white dark:bg-slate-800 p-2 text-sm" placeholder="Enter custom deduction name...">
                                    </div>
                                </div>
                                
                                <div x-show="adjustmentForm.type !== 'overtime'">
                                    <label class="block text-sm font-medium text-slate-700 dark:text-slate-300 mb-1">Amount (₦)</label>
                                    <input type="number" x-model="adjustmentForm.amount" class="w-full rounded-lg border-slate-300 dark:border-slate-700 bg-white dark:bg-slate-800 p-2 text-sm" placeholder="0.00" step="100">
                                </div>
                                
                                <!-- Taxable Checkbox with PIT Notice -->
                                <div x-show="adjustmentForm.type === 'bonus'" class="p-2.5 bg-amber-50 dark:bg-amber-900/20 rounded-lg border border-amber-200 dark:border-amber-800">
                                    <label class="flex items-start gap-2 cursor-pointer">
                                        <input type="checkbox" x-model="adjustmentForm.taxable" class="mt-0.5 text-amber-600 rounded" checked>
                                        <div>
                                            <span class="text-sm font-medium text-slate-800 dark:text-slate-200">Taxable Bonus</span>
                                            <p class="text-xs text-amber-700 dark:text-amber-400">
                                                <i data-lucide="info" class="w-3 h-3 inline"></i>
                                                Per PIT Act, all bonuses are taxable income. Only uncheck if you have verified tax exemption.
                                            </p>
                                        </div>
                                    </label>
                                </div>
                                
                                <div>
                                    <label class="block text-sm font-medium text-slate-700 dark:text-slate-300 mb-1">Notes (Optional)</label>
                                    <textarea x-model="adjustmentForm.notes" class="w-full rounded-lg border-slate-300 dark:border-slate-700 bg-white dark:bg-slate-800 p-2 text-sm" rows="2" placeholder="Add notes..."></textarea>
                                </div>
                            </div>
                            <div class="px-5 py-3 bg-slate-50 dark:bg-slate-900 border-t border-slate-100 dark:border-slate-800 flex justify-end gap-3 flex-shrink-0">
                                <button @click="adjustmentModalOpen = false" class="px-4 py-2 text-sm text-slate-600 hover:text-slate-900">Cancel</button>
                                <button @click="saveAdjustment()" :disabled="loading" class="px-4 py-2 bg-brand-600 text-white text-sm font-bold rounded-lg hover:bg-brand-700 disabled:opacity-50">
                                    <span x-show="!loading">Save Adjustment</span>
                                    <span x-show="loading">Saving...</span>
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- OVERTIME MODAL (NEW) -->
                <div x-show="overtimeModalOpen" x-cloak class="fixed inset-0 z-50 flex items-center justify-center">
                    <div class="absolute inset-0 bg-slate-900/50 backdrop-blur-sm" @click="overtimeModalOpen = false"></div>
                    <div class="relative bg-white dark:bg-slate-950 rounded-xl shadow-2xl w-full max-w-md mx-4 overflow-hidden" @click.stop>
                        <div class="px-6 py-4 border-b border-orange-100 dark:border-orange-900/30 bg-gradient-to-r from-orange-50 to-amber-50 dark:from-orange-900/20 dark:to-amber-900/20 flex items-center justify-between">
                            <div class="flex items-center gap-3">
                                <div class="w-10 h-10 flex items-center justify-center bg-orange-100 dark:bg-orange-900/50 rounded-xl text-orange-600">
                                    <i data-lucide="clock" class="w-5 h-5"></i>
                                </div>
                                <h3 class="text-lg font-bold text-slate-900 dark:text-white">Overtime Entry</h3>
                            </div>
                            <button @click="overtimeModalOpen = false" class="text-slate-400 hover:text-slate-600">
                                <i data-lucide="x" class="w-5 h-5"></i>
                            </button>
                        </div>
                        <div class="p-6 space-y-4">
                            <div class="p-3 bg-slate-50 dark:bg-slate-900 rounded-lg">
                                <p class="text-xs text-slate-500">Employee</p>
                                <p class="font-bold text-slate-900 dark:text-white" x-text="overtimeForm.employee_name"></p>
                            </div>
                            
                            <div>
                                <label class="block text-sm font-medium text-slate-700 dark:text-slate-300 mb-1">Overtime Hours</label>
                                <div class="relative">
                                    <input type="number" x-model="overtimeForm.hours" class="w-full rounded-lg border-slate-300 dark:border-slate-700 bg-white dark:bg-slate-800 p-2.5 pr-12 text-sm" placeholder="0.0" step="0.5" min="0">
                                    <span class="absolute right-3 top-1/2 -translate-y-1/2 text-slate-400 text-xs font-medium">hours</span>
                                </div>
                            </div>
                            
                            <!-- OT Pay Preview -->
                            <div class="p-3 bg-orange-50 dark:bg-orange-900/20 rounded-lg border border-orange-200 dark:border-orange-800/50">
                                <div class="flex justify-between items-center mb-2">
                                    <span class="text-xs text-slate-600 dark:text-slate-400">OT Rate:</span>
                                    <span class="text-xs font-bold text-orange-600" x-text="overtimeConfig.rate + 'x normal rate'"></span>
                                </div>
                                <div class="flex justify-between items-center">
                                    <span class="text-sm font-medium text-slate-700 dark:text-slate-300">Estimated OT Pay:</span>
                                    <span class="text-lg font-bold text-orange-600" x-text="formatCurrency(calculateOvertimePay(overtimeForm.hours, sheetData.find(e => e.employee_id === overtimeForm.employee_id)?.gross_salary || 0))"></span>
                                </div>
                            </div>
                            
                            <div>
                                <label class="block text-sm font-medium text-slate-700 dark:text-slate-300 mb-1">Notes (Optional)</label>
                                <textarea x-model="overtimeForm.notes" class="w-full rounded-lg border-slate-300 dark:border-slate-700 bg-white dark:bg-slate-800 p-2.5 text-sm" rows="2" placeholder="e.g. Weekend work, project deadline..."></textarea>
                            </div>
                            
                            <!-- PIT Tax Notice -->
                            <div class="p-3 bg-amber-50 dark:bg-amber-900/20 rounded-lg border border-amber-200 dark:border-amber-800">
                                <div class="flex items-start gap-2">
                                    <i data-lucide="info" class="w-4 h-4 text-amber-600 mt-0.5 shrink-0"></i>
                                    <p class="text-xs text-amber-700 dark:text-amber-400">
                                        <strong>Tax Notice:</strong> Per PIT Act, overtime pay is taxable income and will be included in PAYE calculation.
                                    </p>
                                </div>
                            </div>
                        </div>
                        <div class="px-6 py-4 bg-slate-50 dark:bg-slate-900 border-t border-slate-100 dark:border-slate-800 flex justify-end gap-3">
                            <button @click="overtimeModalOpen = false" class="px-4 py-2 text-sm text-slate-600 hover:text-slate-900">Cancel</button>
                            <button @click="saveOvertime()" :disabled="loading" class="px-4 py-2 bg-orange-500 text-white text-sm font-bold rounded-lg hover:bg-orange-600 disabled:opacity-50">
                                <span x-show="!loading">Save Overtime</span>
                                <span x-show="loading">Saving...</span>
                            </button>
                        </div>
                    </div>
                </div>
                
                <!-- PAYSLIP MODAL -->
                <div x-show="payslipModalOpen" x-cloak class="fixed inset-0 z-50 flex items-center justify-center p-4">
                    <div class="absolute inset-0 bg-black/50" @click="payslipModalOpen = false"></div>
                    <div class="relative bg-white dark:bg-slate-950 rounded-2xl shadow-xl w-full max-w-3xl max-h-[95vh] overflow-y-auto">
                        <!-- Modal Header -->
                        <div class="sticky top-0 bg-white dark:bg-slate-950 px-6 py-4 border-b border-slate-200 dark:border-slate-800 flex justify-between items-center z-10">
                            <h3 class="text-lg font-bold text-slate-900 dark:text-white">Employee Payslip</h3>
                            <div class="flex items-center gap-2">
                                <button @click="printPayslip()" class="flex items-center gap-1.5 px-3 py-1.5 bg-slate-100 text-slate-700 rounded-lg hover:bg-slate-200 text-sm" title="Print">
                                    <i data-lucide="printer" class="w-4 h-4"></i>
                                    Print
                                </button>
                                <button @click="exportPayslipPDF()" class="flex items-center gap-1.5 px-3 py-1.5 bg-red-50 text-red-700 rounded-lg hover:bg-red-100 text-sm" title="Download PDF">
                                    <i data-lucide="file-text" class="w-4 h-4"></i>
                                    PDF
                                </button>
                                <button @click="payslipModalOpen = false" class="text-slate-400 hover:text-slate-600">
                                    <i data-lucide="x" class="w-5 h-5"></i>
                                </button>
                            </div>
                        </div>
                        
                        <!-- Payslip Content -->
                        <div id="payslip-content" class="p-6" x-show="selectedEmployee">
                            <style>
                                .payslip-wrapper {
                                    font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
                                    background: #fff;
                                    position: relative;
                                    overflow: hidden;
                                }
                                .payslip-watermark {
                                    position: absolute;
                                    top: 50%;
                                    left: 50%;
                                    transform: translate(-50%, -50%) rotate(-30deg);
                                    font-size: 80px;
                                    font-weight: bold;
                                    color: rgba(200, 200, 200, 0.15);
                                    white-space: nowrap;
                                    pointer-events: none;
                                    z-index: 0;
                                    user-select: none;
                                }
                                .payslip-content-inner {
                                    position: relative;
                                    z-index: 1;
                                }
                                .payslip-header {
                                    display: flex;
                                    justify-content: space-between;
                                    align-items: flex-start;
                                    border-bottom: 3px solid #0066cc;
                                    padding-bottom: 15px;
                                    margin-bottom: 20px;
                                }
                                .company-info {
                                    display: flex;
                                    align-items: flex-start;
                                    gap: 15px;
                                }
                                .company-logo img {
                                    max-height: 60px;
                                    max-width: 120px;
                                    object-fit: contain;
                                }
                                .company-details h1 {
                                    font-size: 20px;
                                    font-weight: bold;
                                    color: #0066cc;
                                    margin: 0 0 5px 0;
                                }
                                .company-details p {
                                    font-size: 11px;
                                    color: #666;
                                    margin: 2px 0;
                                }
                                .company-contact p {
                                    font-size: 11px;
                                    color: #666;
                                    margin: 2px 0;
                                }
                                .payslip-label {
                                    text-align: right;
                                }
                                .payslip-label h2 {
                                    font-size: 22px;
                                    font-weight: bold;
                                    color: #333;
                                    margin: 0;
                                }
                                .payslip-label p {
                                    font-size: 11px;
                                    color: #999;
                                }
                                .employee-details {
                                    display: grid;
                                    grid-template-columns: repeat(4, 1fr);
                                    gap: 12px;
                                    background: #f8fafc;
                                    padding: 15px;
                                    border-radius: 8px;
                                    margin-bottom: 20px;
                                }
                                .detail-item label {
                                    font-size: 10px;
                                    color: #888;
                                    text-transform: uppercase;
                                    display: block;
                                }
                                .detail-item span {
                                    font-size: 13px;
                                    font-weight: 600;
                                    color: #333;
                                }
                                .columns-container {
                                    display: grid;
                                    grid-template-columns: 1fr 1fr;
                                    gap: 20px;
                                    margin-bottom: 20px;
                                }
                                .column-box {
                                    padding: 15px;
                                    border-radius: 10px;
                                }
                                .earnings-box {
                                    background: linear-gradient(135deg, #e8f5e9 0%, #c8e6c9 100%);
                                    border: 1px solid #a5d6a7;
                                }
                                .deductions-box {
                                    background: linear-gradient(135deg, #ffebee 0%, #ffcdd2 100%);
                                    border: 1px solid #ef9a9a;
                                }
                                .column-title {
                                    font-size: 12px;
                                    font-weight: bold;
                                    text-transform: uppercase;
                                    padding-bottom: 10px;
                                    margin-bottom: 10px;
                                    border-bottom: 2px solid rgba(0,0,0,0.1);
                                }
                                .earnings-box .column-title { color: #2e7d32; }
                                .deductions-box .column-title { color: #c62828; }
                                .line-item {
                                    display: flex;
                                    justify-content: space-between;
                                    padding: 6px 0;
                                    font-size: 12px;
                                }
                                .line-item-name { color: #555; }
                                .line-item-amount { font-weight: 600; color: #333; }
                                .line-item.bonus .line-item-name, .line-item.bonus .line-item-amount { color: #2e7d32; }
                                .line-item.deduction .line-item-name, .line-item.deduction .line-item-amount { color: #c62828; }
                                .line-item.total {
                                    border-top: 2px solid rgba(0,0,0,0.15);
                                    margin-top: 10px;
                                    padding-top: 10px;
                                    font-weight: bold;
                                }
                                .pension-section {
                                    background: linear-gradient(135deg, #e3f2fd 0%, #bbdefb 100%);
                                    border: 1px solid #90caf9;
                                    border-radius: 10px;
                                    padding: 15px;
                                    margin-bottom: 20px;
                                }
                                .pension-section .section-title {
                                    font-size: 12px;
                                    font-weight: bold;
                                    color: #1565c0;
                                    text-transform: uppercase;
                                    margin-bottom: 10px;
                                    padding-bottom: 8px;
                                    border-bottom: 2px solid rgba(21, 101, 192, 0.2);
                                }
                                .pension-grid {
                                    display: grid;
                                    grid-template-columns: repeat(3, 1fr);
                                    gap: 15px;
                                }
                                .pension-item label {
                                    font-size: 10px;
                                    color: #1565c0;
                                    display: block;
                                }
                                .pension-item span {
                                    font-size: 14px;
                                    font-weight: 600;
                                    color: #0d47a1;
                                }
                                .net-pay-section {
                                    background: linear-gradient(135deg, #1565c0 0%, #0d47a1 50%, #1a237e 100%);
                                    border-radius: 12px;
                                    padding: 25px;
                                    text-align: center;
                                    color: white;
                                    margin-bottom: 20px;
                                    box-shadow: 0 4px 15px rgba(21, 101, 192, 0.3);
                                }
                                .net-pay-label {
                                    font-size: 12px;
                                    text-transform: uppercase;
                                    letter-spacing: 2px;
                                    opacity: 0.9;
                                    margin-bottom: 5px;
                                }
                                .net-pay-amount {
                                    font-size: 32px;
                                    font-weight: bold;
                                    margin-bottom: 8px;
                                }
                                .net-pay-words {
                                    font-size: 12px;
                                    font-style: italic;
                                    opacity: 0.85;
                                    border-top: 1px solid rgba(255,255,255,0.3);
                                    padding-top: 10px;
                                    margin-top: 5px;
                                }
                                .ytd-section {
                                    background: #f1f5f9;
                                    border: 1px solid #e2e8f0;
                                    border-radius: 10px;
                                    padding: 15px;
                                    margin-bottom: 20px;
                                }
                                .ytd-section .section-title {
                                    font-size: 12px;
                                    font-weight: bold;
                                    color: #475569;
                                    text-transform: uppercase;
                                    margin-bottom: 12px;
                                    padding-bottom: 8px;
                                    border-bottom: 2px solid #cbd5e1;
                                }
                                .ytd-grid {
                                    display: grid;
                                    grid-template-columns: repeat(4, 1fr);
                                    gap: 15px;
                                }
                                .ytd-item {
                                    text-align: center;
                                    padding: 10px;
                                    background: white;
                                    border-radius: 8px;
                                }
                                .ytd-item label {
                                    font-size: 10px;
                                    color: #64748b;
                                    text-transform: uppercase;
                                    display: block;
                                    margin-bottom: 5px;
                                }
                                .ytd-item span {
                                    font-size: 14px;
                                    font-weight: bold;
                                    color: #334155;
                                }
                                .payslip-footer {
                                    text-align: center;
                                    padding-top: 20px;
                                    border-top: 2px solid #e2e8f0;
                                }
                                .footer-main {
                                    font-size: 11px;
                                    color: #64748b;
                                    margin-bottom: 5px;
                                }
                                .footer-powered {
                                    font-size: 10px;
                                    color: #94a3b8;
                                }
                                .footer-powered a {
                                    color: #0066cc;
                                    text-decoration: none;
                                }
                            </style>
                            
                            <div class="payslip-wrapper">
                                <!-- Confidential Watermark -->
                                <div class="payslip-watermark">CONFIDENTIAL</div>
                                
                                <div class="payslip-content-inner">
                                    <!-- Header with Logo -->
                                    <div class="payslip-header">
                                        <div class="company-info">
                                            <?php 
                                            $has_logo = !empty($company_logo) && file_exists(__DIR__ . '/../uploads/logos/' . $company_logo);
                                            if ($has_logo): 
                                            ?>
                                            <!-- Logo available - show logo as main identifier -->
                                            <div class="company-logo">
                                                <img src="../uploads/logos/<?php echo htmlspecialchars($company_logo); ?>" alt="<?php echo htmlspecialchars($payslip_company_name); ?>">
                                            </div>
                                            <?php else: ?>
                                            <!-- No logo - show company name as main identifier -->
                                            <div class="company-details">
                                                <h1><?php echo htmlspecialchars($payslip_company_name); ?></h1>
                                            </div>
                                            <?php endif; ?>
                                            <!-- Address, Email, Phone always shown if available -->
                                            <div class="company-contact">
                                                <?php if (!empty($company_address)): ?>
                                                <p><?php echo htmlspecialchars($company_address); ?></p>
                                                <?php endif; ?>
                                                <?php if (!empty($company_email)): ?>
                                                <p><?php echo htmlspecialchars($company_email); ?></p>
                                                <?php endif; ?>
                                                <?php if (!empty($company_phone)): ?>
                                                <p><?php echo htmlspecialchars($company_phone); ?></p>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                        <div class="payslip-label">
                                            <h2>PAYSLIP</h2>
                                            <p x-text="new Date(currentPeriod.year, currentPeriod.month - 1).toLocaleDateString('en-NG', { month: 'long', year: 'numeric' })"></p>
                                            <p x-text="'Generated: ' + new Date().toLocaleDateString()"></p>
                                        </div>
                                    </div>
                                    
                                    <!-- Employee Details -->
                                    <div class="employee-details">
                                        <div class="detail-item">
                                            <label>Employee Name</label>
                                            <span x-text="selectedEmployee?.first_name + ' ' + selectedEmployee?.last_name"></span>
                                        </div>
                                        <div class="detail-item">
                                            <label>Employee ID</label>
                                            <span x-text="selectedEmployee?.payroll_id || 'N/A'"></span>
                                        </div>
                                        <div class="detail-item">
                                            <label>Pay Period</label>
                                            <span x-text="new Date(currentPeriod.year, currentPeriod.month - 1).toLocaleDateString('en-NG', { month: 'long', year: 'numeric' })"></span>
                                        </div>
                                        <div class="detail-item">
                                            <label>Payment Date</label>
                                            <span x-text="new Date(currentPeriod.year, currentPeriod.month, 0).toLocaleDateString('en-NG', { day: 'numeric', month: 'short', year: 'numeric' })"></span>
                                        </div>
                                    </div>
                                    
                                    <!-- Earnings & Deductions -->
                                    <div class="columns-container">
                                        <!-- Earnings -->
                                        <div class="column-box earnings-box">
                                            <div class="column-title">Earnings</div>
                                            <div class="line-item">
                                                <span class="line-item-name">Basic Salary</span>
                                                <span class="line-item-amount" x-text="formatCurrency(selectedEmployee?.breakdown?.basic || 0)"></span>
                                            </div>
                                            <div class="line-item">
                                                <span class="line-item-name">Housing Allowance</span>
                                                <span class="line-item-amount" x-text="formatCurrency(selectedEmployee?.breakdown?.housing || 0)"></span>
                                            </div>
                                            <div class="line-item">
                                                <span class="line-item-name">Transport Allowance</span>
                                                <span class="line-item-amount" x-text="formatCurrency(selectedEmployee?.breakdown?.transport || 0)"></span>
                                            </div>
                                            <template x-for="(bonus, idx) in (selectedEmployee?.breakdown?.bonus_items || [])" :key="'bonus-'+idx">
                                                <div class="line-item bonus">
                                                    <span class="line-item-name" x-text="bonus.name"></span>
                                                    <span class="line-item-amount" x-text="formatCurrency(bonus.amount)"></span>
                                                </div>
                                            </template>
                                            <!-- Overtime Pay (NEW) -->
                                            <template x-if="(selectedEmployee?.breakdown?.overtime_pay || 0) > 0">
                                                <div class="line-item bonus">
                                                    <span class="line-item-name">Overtime Pay <small style="color:#666;">(<span x-text="selectedEmployee?.breakdown?.overtime_hours || 0"></span>h)</small></span>
                                                    <span class="line-item-amount" x-text="formatCurrency(selectedEmployee?.breakdown?.overtime_pay || 0)"></span>
                                                </div>
                                            </template>
                                            <div class="line-item total">
                                                <span class="line-item-name">Gross Earnings</span>
                                                <span class="line-item-amount" x-text="formatCurrency(selectedEmployee?.gross_salary || 0)"></span>
                                            </div>
                                        </div>
                                        
                                        <!-- Deductions -->
                                        <div class="column-box deductions-box">
                                            <div class="column-title">Deductions</div>
                                            <div class="line-item">
                                                <span class="line-item-name">PAYE Tax</span>
                                                <span class="line-item-amount" x-text="formatCurrency(selectedEmployee?.breakdown?.paye || 0)"></span>
                                            </div>
                                            <div class="line-item">
                                                <span class="line-item-name">Pension (Employee 8%)</span>
                                                <span class="line-item-amount" x-text="formatCurrency(selectedEmployee?.breakdown?.pension || 0)"></span>
                                            </div>
                                            <div class="line-item">
                                                <span class="line-item-name">NHIS</span>
                                                <span class="line-item-amount" x-text="formatCurrency(selectedEmployee?.breakdown?.nhis || 0)"></span>
                                            </div>
                                            <template x-if="selectedEmployee?.breakdown?.nhf > 0">
                                                <div class="line-item">
                                                    <span class="line-item-name">NHF</span>
                                                    <span class="line-item-amount" x-text="formatCurrency(selectedEmployee?.breakdown?.nhf || 0)"></span>
                                                </div>
                                            </template>
                                            <template x-if="selectedEmployee?.breakdown?.lateness > 0">
                                                <div class="line-item deduction">
                                                    <span class="line-item-name">Lateness Penalty</span>
                                                    <span class="line-item-amount" x-text="formatCurrency(selectedEmployee?.breakdown?.lateness || 0)"></span>
                                                </div>
                                            </template>
                                            <template x-for="(loan, idx) in (selectedEmployee?.breakdown?.loan_items || [])" :key="'loan-'+idx">
                                                <div class="line-item deduction">
                                                    <span class="line-item-name" x-text="loan.name"></span>
                                                    <span class="line-item-amount" x-text="formatCurrency(loan.amount)"></span>
                                                </div>
                                            </template>
                                            <template x-for="(ded, idx) in (selectedEmployee?.breakdown?.deduction_items || [])" :key="'ded-'+idx">
                                                <div class="line-item deduction">
                                                    <span class="line-item-name" x-text="ded.name"></span>
                                                    <span class="line-item-amount" x-text="formatCurrency(ded.amount)"></span>
                                                </div>
                                            </template>
                                            <div class="line-item total">
                                                <span class="line-item-name">Total Deductions</span>
                                                <span class="line-item-amount" x-text="formatCurrency(selectedEmployee?.total_deductions || 0)"></span>
                                            </div>
                                        </div>
                                    </div>
                                    
                                    <!-- Pension Details Section -->
                                    <div class="pension-section">
                                        <div class="section-title">Pension Details</div>
                                        <div class="pension-grid">
                                            <div class="pension-item">
                                                <label>Employee Contribution (8%)</label>
                                                <span x-text="formatCurrency(selectedEmployee?.breakdown?.pension || 0)"></span>
                                            </div>
                                            <div class="pension-item">
                                                <label>Employer Contribution (10%)</label>
                                                <span x-text="formatCurrency((selectedEmployee?.breakdown?.pension || 0) * 1.25)"></span>
                                            </div>
                                            <div class="pension-item">
                                                <label>Total Pension</label>
                                                <span x-text="formatCurrency((selectedEmployee?.breakdown?.pension || 0) * 2.25)"></span>
                                            </div>
                                        </div>
                                    </div>
                                    
                                    <!-- Net Pay Section -->
                                    <div class="net-pay-section">
                                        <div class="net-pay-label">Net Pay</div>
                                        <div class="net-pay-amount" x-text="formatCurrency(selectedEmployee?.net_pay || 0)"></div>
                                        <div class="net-pay-words" x-text="numberToWords(selectedEmployee?.net_pay || 0)"></div>
                                    </div>
                                    
                                    <!-- Year to Date Summary -->
                                    <div class="ytd-section">
                                        <div class="section-title">Year to Date (YTD) Summary</div>
                                        <div class="ytd-grid">
                                            <div class="ytd-item">
                                                <label>YTD Gross</label>
                                                <span x-text="formatCurrency((selectedEmployee?.gross_salary || 0) * currentPeriod.month)"></span>
                                            </div>
                                            <div class="ytd-item">
                                                <label>YTD Tax (PAYE)</label>
                                                <span x-text="formatCurrency((selectedEmployee?.breakdown?.paye || 0) * currentPeriod.month)"></span>
                                            </div>
                                            <div class="ytd-item">
                                                <label>YTD Pension</label>
                                                <span x-text="formatCurrency((selectedEmployee?.breakdown?.pension || 0) * currentPeriod.month)"></span>
                                            </div>
                                            <div class="ytd-item">
                                                <label>YTD Net</label>
                                                <span x-text="formatCurrency((selectedEmployee?.net_pay || 0) * currentPeriod.month)"></span>
                                            </div>
                                        </div>
                                    </div>
                                    
                                    <!-- Footer -->
                                    <div class="payslip-footer">
                                        <div class="footer-main">This is a system-generated payslip from MiPayMaster. No signature required.</div>
                                        <div class="footer-powered">Powered by Miemploya Platform • <a href="https://www.miemploya.com" target="_blank">www.miemploya.com</a></div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- VIEW 4: PREVIEW & VALIDATION -->
                <div x-show="view === 'preview'" x-cloak x-transition.opacity class="max-w-6xl mx-auto">
                    <!-- Header with Period Info -->
                    <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-4 mb-6">
                        <div>
                            <h2 class="text-xl font-bold text-slate-900 dark:text-white">Payroll Preview & Validation</h2>
                            <p class="text-sm text-slate-500">
                                <span x-text="getMonthName(currentPeriod.month)"></span> <span x-text="currentPeriod.year"></span> • 
                                <span x-text="payrollSheet.length"></span> employees
                            </p>
                        </div>
                        <div class="flex items-center gap-2">
                            <span class="px-3 py-1 bg-amber-100 dark:bg-amber-900/30 text-amber-700 dark:text-amber-400 text-xs font-bold rounded-full uppercase">Draft</span>
                            <span class="text-xs text-slate-500">Run ID: <span x-text="lastRunId || 'N/A'"></span></span>
                        </div>
                    </div>
                    
                    <!-- Summary Cards Grid -->
                    <div class="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-5 gap-4 mb-6">
                        <!-- Employees -->
                        <div class="bg-white dark:bg-slate-950 p-4 rounded-xl border border-slate-200 dark:border-slate-800">
                            <div class="flex items-center gap-2 mb-2">
                                <i data-lucide="users" class="w-4 h-4 text-slate-400"></i>
                                <p class="text-xs text-slate-500 uppercase">Employees</p>
                            </div>
                            <p class="text-2xl font-bold text-slate-900 dark:text-white" x-text="payrollSheet.length"></p>
                        </div>
                        <!-- Gross Pay -->
                        <div class="bg-white dark:bg-slate-950 p-4 rounded-xl border border-slate-200 dark:border-slate-800">
                            <div class="flex items-center gap-2 mb-2">
                                <i data-lucide="wallet" class="w-4 h-4 text-green-500"></i>
                                <p class="text-xs text-slate-500 uppercase">Gross Pay</p>
                            </div>
                            <p class="text-lg font-bold text-slate-900 dark:text-white truncate" x-text="formatCurrency(totals.gross)"></p>
                        </div>
                        <!-- Total Deductions -->
                        <div class="bg-white dark:bg-slate-950 p-4 rounded-xl border border-slate-200 dark:border-slate-800">
                            <div class="flex items-center gap-2 mb-2">
                                <i data-lucide="minus-circle" class="w-4 h-4 text-red-500"></i>
                                <p class="text-xs text-slate-500 uppercase">Deductions</p>
                            </div>
                            <p class="text-lg font-bold text-red-600 truncate" x-text="formatCurrency(totals.deductions)"></p>
                        </div>
                        <!-- Net Pay (highlighted) -->
                        <div class="bg-brand-50 dark:bg-brand-900/20 p-4 rounded-xl border border-brand-200 dark:border-brand-800 col-span-2">
                            <div class="flex items-center gap-2 mb-2">
                                <i data-lucide="banknote" class="w-4 h-4 text-brand-600"></i>
                                <p class="text-xs text-brand-600 uppercase font-bold">Total Net Pay</p>
                            </div>
                            <p class="text-2xl font-bold text-brand-700 dark:text-brand-300" x-text="formatCurrency(totals.net)"></p>
                        </div>
                    </div>
                    
                    <!-- Two Column Layout: Statutory + Department -->
                    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6 mb-6">
                        <!-- Statutory Breakdown -->
                        <div class="bg-white dark:bg-slate-950 rounded-xl border border-slate-200 dark:border-slate-800 overflow-hidden">
                            <div class="p-4 border-b border-slate-100 dark:border-slate-800 flex items-center gap-2">
                                <i data-lucide="landmark" class="w-5 h-5 text-slate-400"></i>
                                <h3 class="font-bold text-slate-900 dark:text-white">Statutory Contributions</h3>
                            </div>
                            <div class="p-4 space-y-3">
                                <div x-show="statutoryFlags.paye" class="flex justify-between text-sm">
                                    <span class="text-slate-600 dark:text-slate-400">PAYE (Tax)</span>
                                    <span class="font-bold text-slate-900 dark:text-white" x-text="formatCurrency(calculateStatutoryTotal('paye'))"></span>
                                </div>
                                <div x-show="statutoryFlags.pension" class="flex justify-between text-sm">
                                    <span class="text-slate-600 dark:text-slate-400">Pension (Employee)</span>
                                    <span class="font-bold text-slate-900 dark:text-white" x-text="formatCurrency(calculateStatutoryTotal('pension'))"></span>
                                </div>
                                <div x-show="statutoryFlags.nhf" class="flex justify-between text-sm">
                                    <span class="text-slate-600 dark:text-slate-400">NHF</span>
                                    <span class="font-bold text-slate-900 dark:text-white" x-text="formatCurrency(calculateStatutoryTotal('nhf'))"></span>
                                </div>
                                <div x-show="statutoryFlags.nhis" class="flex justify-between text-sm">
                                    <span class="text-slate-600 dark:text-slate-400">NHIS</span>
                                    <span class="font-bold text-slate-900 dark:text-white" x-text="formatCurrency(calculateStatutoryTotal('nhis'))"></span>
                                </div>
                                <div class="border-t border-slate-100 dark:border-slate-800 pt-3 flex justify-between text-sm">
                                    <span class="font-medium text-slate-700 dark:text-slate-300">Total Statutory</span>
                                    <span class="font-bold text-red-600" x-text="formatCurrency(getActiveStatutoryTotal())"></span>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Department Breakdown -->
                        <div class="bg-white dark:bg-slate-950 rounded-xl border border-slate-200 dark:border-slate-800 overflow-hidden">
                            <div class="p-4 border-b border-slate-100 dark:border-slate-800 flex items-center gap-2">
                                <i data-lucide="building-2" class="w-5 h-5 text-slate-400"></i>
                                <h3 class="font-bold text-slate-900 dark:text-white">Department Breakdown</h3>
                            </div>
                            <div class="p-4 max-h-48 overflow-y-auto">
                                <template x-for="dept in getDepartmentBreakdown()" :key="dept.name">
                                    <div class="flex justify-between text-sm py-2 border-b border-slate-50 dark:border-slate-800 last:border-0">
                                        <div class="flex items-center gap-2">
                                            <span class="text-slate-600 dark:text-slate-400" x-text="dept.name || 'Unassigned'"></span>
                                            <span class="text-xs text-slate-400" x-text="'(' + dept.count + ')'"></span>
                                        </div>
                                        <span class="font-bold text-slate-900 dark:text-white" x-text="formatCurrency(dept.netPay)"></span>
                                    </div>
                                </template>
                                <div x-show="getDepartmentBreakdown().length === 0" class="text-sm text-slate-500 text-center py-4">
                                    No department data available
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Anomalies Panel -->
                    <div class="bg-white dark:bg-slate-950 rounded-xl border border-red-200 dark:border-red-900/30 overflow-hidden mb-6" x-show="anomalies.length > 0">
                        <div class="p-4 bg-red-50 dark:bg-red-900/10 border-b border-red-100 dark:border-red-900/20 flex items-center gap-2">
                            <i data-lucide="alert-triangle" class="w-5 h-5 text-red-600"></i>
                            <h3 class="font-bold text-red-800 dark:text-red-300" x-text="'Detected Anomalies (' + anomalies.length + ')'"></h3>
                        </div>
                        <div class="p-4 space-y-3">
                            <template x-for="issue in anomalies">
                                <div class="flex items-center justify-between text-sm">
                                    <span class="text-slate-700 dark:text-slate-300"><span class="font-bold" x-text="issue.employee"></span>: <span x-text="issue.issue + ' - ' + issue.detail"></span></span>
                                    <button class="text-xs text-brand-600 hover:underline">Review</button>
                                </div>
                            </template>
                        </div>
                    </div>
                    <!-- No Anomalies -->
                    <div class="bg-white dark:bg-slate-950 rounded-xl border border-green-200 dark:border-green-900/30 overflow-hidden mb-6" x-show="anomalies.length === 0">
                         <div class="p-4 bg-green-50 dark:bg-green-900/10 flex items-center gap-3">
                            <i data-lucide="check-circle" class="w-6 h-6 text-green-600"></i>
                            <div>
                                <h3 class="font-bold text-green-800 dark:text-green-300">No Anomalies Detected</h3>
                                <p class="text-sm text-green-600 dark:text-green-400">Payroll data looks consistent with rules. Proceed with validation.</p>
                            </div>
                        </div>
                    </div>

                    <!-- Action Buttons -->
                    <div class="flex justify-between">
                        <button @click="changeView('sheet')" class="px-6 py-2.5 text-slate-600 border border-slate-300 rounded-lg hover:bg-slate-50 font-medium flex items-center gap-2">
                            <i data-lucide="arrow-left" class="w-4 h-4"></i> Back to Sheet
                        </button>
                        <button @click="changeView('approval')" class="px-6 py-2.5 bg-slate-900 dark:bg-white text-white dark:text-slate-900 rounded-lg font-bold shadow-lg hover:opacity-90 flex items-center gap-2">
                            Submit for Approval <i data-lucide="arrow-right" class="w-4 h-4"></i>
                        </button>
                    </div>
                </div>

                <!-- VIEW 5: APPROVAL -->
                <div x-show="view === 'approval'" x-cloak x-transition.opacity class="max-w-3xl mx-auto">
                    <div class="bg-white dark:bg-slate-950 rounded-xl border border-slate-200 dark:border-slate-800 shadow-lg p-8">
                        <div class="flex justify-between items-center mb-6 pb-6 border-b border-slate-100 dark:border-slate-800">
                            <div>
                                <h2 class="text-xl font-bold text-slate-900 dark:text-white">Final Payroll Approval</h2>
                                <p class="text-sm text-slate-500" x-text="'Draft #PAY-' + currentPeriod.year + '-' + currentPeriod.month"></p>
                            </div>
                            <div class="text-right">
                                <p class="text-xs text-slate-500 uppercase">Prepared By</p>
                                <p class="font-medium text-slate-900 dark:text-white"><?php echo htmlspecialchars($_SESSION['user_name'] ?? 'Admin'); ?></p>
                            </div>
                        </div>

                        <div class="p-4 bg-slate-50 dark:bg-slate-900 rounded-lg border border-slate-200 dark:border-slate-800 mb-8 text-center">
                            <p class="text-sm text-slate-500 mb-1">Total Net Payable</p>
                            <p class="text-3xl font-bold text-slate-900 dark:text-white" x-text="formatCurrency(totals.net)"></p>
                            <p class="text-xs text-green-600 mt-2 flex items-center justify-center gap-1"><i data-lucide="check" class="w-3 h-3"></i> Validated & Ready</p>
                        </div>

                        <div class="space-y-4">
                            <textarea class="w-full rounded-lg border-slate-300 dark:border-slate-700 bg-white dark:bg-slate-800 p-3 text-sm focus:ring-brand-500" rows="3" placeholder="Approval notes (optional)..."></textarea>
                            
                            <div class="flex gap-4">
                                <button @click="rejectPayroll()" :disabled="loading" class="flex-1 py-3 border border-red-200 text-red-600 rounded-xl hover:bg-red-50 dark:hover:bg-red-900/10 font-bold transition-colors disabled:opacity-50 flex items-center justify-center gap-2">
                                    <i data-lucide="x-circle" class="w-4 h-4"></i> Reject Payroll
                                </button>
                                <button @click="approvePayroll()" class="flex-1 py-3 bg-green-600 text-white rounded-xl hover:bg-green-700 font-bold shadow-md shadow-green-500/30 transition-colors flex items-center justify-center gap-2">
                                    <span x-show="!loading"><i data-lucide="lock" class="w-4 h-4"></i> Approve & Post</span>
                                    <span x-show="loading">Processing...</span>
                                </button>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- VIEW 6: PAYSLIPS -->
                <div x-show="view === 'payslips'" x-cloak x-transition.opacity x-data="payslipsView()">
                    <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-4 mb-6">
                        <div>
                            <h2 class="text-lg font-bold text-slate-900 dark:text-white">Employee Payslips</h2>
                            <p class="text-sm text-slate-500">View and download individual employee payslips</p>
                        </div>
                        <button x-show="filteredPayslips.length > 0" @click="exportPayslipsList()" 
                            class="px-4 py-2 bg-slate-100 dark:bg-slate-800 hover:bg-slate-200 dark:hover:bg-slate-700 text-slate-700 dark:text-slate-300 rounded-lg text-sm font-medium flex items-center gap-2">
                            <i data-lucide="download" class="w-4 h-4"></i> Export List
                        </button>
                    </div>
                    
                    <!-- Filters -->
                    <div class="bg-white dark:bg-slate-950 rounded-xl border border-slate-200 dark:border-slate-800 p-5 mb-6">
                        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4">
                            <!-- Employee Search Autocomplete -->
                            <div class="relative">
                                <label class="block text-sm font-medium text-slate-700 dark:text-slate-300 mb-1">Employee</label>
                                <!-- Selected employee badge -->
                                <div x-show="selectedEmployee" class="flex items-center gap-2 p-2 bg-brand-50 dark:bg-brand-900/20 rounded-lg border border-brand-200 dark:border-brand-800">
                                    <span class="flex-1 text-sm font-medium text-brand-700 dark:text-brand-300" x-text="selectedEmployee?.name"></span>
                                    <button type="button" @click="clearEmployeeFilter()" class="text-brand-500 hover:text-brand-700">
                                        <i data-lucide="x" class="w-4 h-4"></i>
                                    </button>
                                </div>
                                <!-- Search input -->
                                <div x-show="!selectedEmployee">
                                    <input type="text" 
                                        x-model="employeeSearch" 
                                        @input="searchEmployees()"
                                        @focus="showEmployeeList = true"
                                        @click.stop
                                        class="w-full rounded-lg border-slate-300 dark:border-slate-700 bg-white dark:bg-slate-800 p-2 text-sm" 
                                        placeholder="Search by name or ID...">
                                    <!-- Dropdown -->
                                    <div x-show="showEmployeeList && employeeResults.length > 0" 
                                        @click.away="showEmployeeList = false"
                                        class="absolute z-50 w-full mt-1 bg-white dark:bg-slate-900 rounded-lg shadow-lg border border-slate-200 dark:border-slate-700 max-h-48 overflow-y-auto">
                                        <template x-for="emp in employeeResults" :key="emp.id">
                                            <button type="button" @click="selectEmployee(emp)" 
                                                class="w-full px-3 py-2 text-left text-sm hover:bg-slate-100 dark:hover:bg-slate-800 flex items-center gap-2">
                                                <span class="font-medium" x-text="emp.name"></span>
                                                <span class="text-slate-400 text-xs" x-text="'(' + emp.payroll_id + ')'"></span>
                                            </button>
                                        </template>
                                    </div>
                                </div>
                            </div>
                            
                            <!-- Department Filter -->
                            <div>
                                <label class="block text-sm font-medium text-slate-700 dark:text-slate-300 mb-1">Department</label>
                                <select x-model="filters.department" @change="applyFilters()" class="w-full rounded-lg border-slate-300 dark:border-slate-700 bg-white dark:bg-slate-800 p-2 text-sm">
                                    <option value="">All Departments</option>
                                    <?php foreach($departments as $dept): ?>
                                    <option value="<?= htmlspecialchars($dept) ?>"><?= htmlspecialchars($dept) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            
                            <!-- Period From -->
                            <div>
                                <label class="block text-sm font-medium text-slate-700 dark:text-slate-300 mb-1">Period From</label>
                                <input type="month" x-model="filters.periodFrom" @change="applyFilters()" 
                                    class="w-full rounded-lg border-slate-300 dark:border-slate-700 bg-white dark:bg-slate-800 p-2 text-sm">
                            </div>
                            
                            <!-- Period To -->
                            <div>
                                <label class="block text-sm font-medium text-slate-700 dark:text-slate-300 mb-1">Period To</label>
                                <input type="month" x-model="filters.periodTo" @change="applyFilters()" 
                                    class="w-full rounded-lg border-slate-300 dark:border-slate-700 bg-white dark:bg-slate-800 p-2 text-sm">
                            </div>
                        </div>
                        
                        <!-- Quick Period Buttons -->
                        <div class="flex flex-wrap gap-2 mt-4 pt-4 border-t border-slate-100 dark:border-slate-800">
                            <span class="text-xs text-slate-500 mr-2 self-center">Quick:</span>
                            <button @click="setQuickPeriod('current')" class="px-3 py-1 text-xs rounded-full border border-slate-200 dark:border-slate-700 hover:bg-slate-100 dark:hover:bg-slate-800">Current Month</button>
                            <button @click="setQuickPeriod('last3')" class="px-3 py-1 text-xs rounded-full border border-slate-200 dark:border-slate-700 hover:bg-slate-100 dark:hover:bg-slate-800">Last 3 Months</button>
                            <button @click="setQuickPeriod('last6')" class="px-3 py-1 text-xs rounded-full border border-slate-200 dark:border-slate-700 hover:bg-slate-100 dark:hover:bg-slate-800">Last 6 Months</button>
                            <button @click="setQuickPeriod('year')" class="px-3 py-1 text-xs rounded-full border border-slate-200 dark:border-slate-700 hover:bg-slate-100 dark:hover:bg-slate-800">This Year</button>
                            <button @click="clearFilters()" class="px-3 py-1 text-xs rounded-full text-red-600 border border-red-200 hover:bg-red-50 dark:hover:bg-red-900/20">Clear All</button>
                        </div>
                    </div>
                    
                    <!-- Results Summary -->
                    <div class="flex items-center justify-between mb-4">
                        <p class="text-sm text-slate-500">
                            Showing <strong x-text="filteredPayslips.length"></strong> payslips
                            <span x-show="filters.department || selectedEmployee || filters.periodFrom"> (filtered)</span>
                        </p>
                        <div class="flex items-center gap-2">
                            <span class="text-xs text-slate-400">Sort:</span>
                            <select x-model="sortBy" @change="sortPayslips()" class="text-xs border-0 bg-transparent text-slate-600 dark:text-slate-400">
                                <option value="date_desc">Newest First</option>
                                <option value="date_asc">Oldest First</option>
                                <option value="name_asc">Name A-Z</option>
                                <option value="amount_desc">Highest Pay</option>
                            </select>
                        </div>
                    </div>
                    
                    <!-- Payslips Table -->
                    <div class="bg-white dark:bg-slate-950 rounded-xl border border-slate-200 dark:border-slate-800 overflow-hidden">
                        <!-- Loading State -->
                        <div x-show="loading" class="p-8 text-center">
                            <div class="animate-spin w-8 h-8 border-4 border-brand-500 border-t-transparent rounded-full mx-auto mb-4"></div>
                            <p class="text-slate-500">Loading payslips...</p>
                        </div>
                        
                        <!-- Empty State -->
                        <div x-show="!loading && filteredPayslips.length === 0" class="p-8 text-center">
                            <i data-lucide="file-x" class="w-12 h-12 text-slate-300 mx-auto mb-4"></i>
                            <p class="text-slate-500">No payslips found matching your filters</p>
                        </div>
                        
                        <!-- Data Table -->
                        <table x-show="!loading && filteredPayslips.length > 0" class="w-full text-left text-sm">
                            <thead class="bg-slate-50 dark:bg-slate-900 border-b border-slate-200 dark:border-slate-800 text-slate-500">
                                <tr>
                                    <th class="p-4">Period</th>
                                    <th class="p-4">Employee</th>
                                    <th class="p-4">Department</th>
                                    <th class="p-4 text-right">Gross</th>
                                    <th class="p-4 text-right">Deductions</th>
                                    <th class="p-4 text-right">Net Pay</th>
                                    <th class="p-4">Status</th>
                                    <th class="p-4 text-center">Actions</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
                                <template x-for="slip in filteredPayslips" :key="slip.id">
                                    <tr class="hover:bg-slate-50 dark:hover:bg-slate-900/50">
                                        <td class="p-4" x-text="slip.period"></td>
                                        <td class="p-4">
                                            <span class="font-medium text-slate-900 dark:text-white" x-text="slip.employee_name"></span>
                                            <span class="text-xs text-slate-400 ml-1" x-text="'(' + slip.payroll_id + ')'"></span>
                                        </td>
                                        <td class="p-4 text-slate-600 dark:text-slate-400" x-text="slip.department || '-'"></td>
                                        <td class="p-4 text-right" x-text="formatCurrency(slip.gross_salary)"></td>
                                        <td class="p-4 text-right text-red-600" x-text="formatCurrency(slip.total_deductions)"></td>
                                        <td class="p-4 text-right font-bold text-green-600" x-text="formatCurrency(slip.net_pay)"></td>
                                        <td class="p-4">
                                            <span class="px-2 py-1 rounded text-xs font-bold uppercase tracking-wider"
                                                :class="slip.status === 'approved' ? 'bg-green-100 text-green-700 dark:bg-green-900/40 dark:text-green-400' : 'bg-amber-100 text-amber-700 dark:bg-amber-900/40 dark:text-amber-400'"
                                                x-text="slip.status === 'approved' ? 'Paid' : 'Pending'"></span>
                                        </td>
                                        <td class="p-4 text-center">
                                            <div class="flex items-center justify-center gap-2">
                                                <button @click="viewPayslip(slip)" class="text-brand-600 hover:text-brand-800 text-sm font-medium">View</button>
                                                <button @click="downloadPayslip(slip)" class="text-slate-500 hover:text-slate-700 p-1">
                                                    <i data-lucide="download" class="w-4 h-4"></i>
                                                </button>
                                            </div>
                                        </td>
                                    </tr>
                                </template>
                            </tbody>
                        </table>
                    </div>
                </div>

                <!-- VIEW 7: REPORTS -->
                <div x-show="view === 'reports'" x-cloak x-transition.opacity>
                    <h2 class="text-lg font-bold text-slate-900 dark:text-white mb-6">Statutory & Management Reports</h2>
                    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
                        <!-- Report Card -->
                        <div class="bg-white dark:bg-slate-950 p-6 rounded-xl border border-slate-200 dark:border-slate-800 hover:border-brand-500 transition-colors cursor-pointer group">
                            <div class="w-12 h-12 rounded-lg bg-green-100 dark:bg-green-900/20 text-green-600 flex items-center justify-center mb-4"><i data-lucide="file-check" class="w-6 h-6"></i></div>
                            <h3 class="font-bold text-slate-900 dark:text-white group-hover:text-brand-600">PAYE Schedule</h3>
                            <p class="text-sm text-slate-500 mt-2">Monthly tax remittance report for internal revenue service.</p>
                        </div>
                        <div class="bg-white dark:bg-slate-950 p-6 rounded-xl border border-slate-200 dark:border-slate-800 hover:border-brand-500 transition-colors cursor-pointer group">
                            <div class="w-12 h-12 rounded-lg bg-blue-100 dark:bg-blue-900/20 text-blue-600 flex items-center justify-center mb-4"><i data-lucide="piggy-bank" class="w-6 h-6"></i></div>
                            <h3 class="font-bold text-slate-900 dark:text-white group-hover:text-brand-600">Pension Report</h3>
                            <p class="text-sm text-slate-500 mt-2">Employee and employer pension contribution schedule.</p>
                        </div>
                        <div class="bg-white dark:bg-slate-950 p-6 rounded-xl border border-slate-200 dark:border-slate-800 hover:border-brand-500 transition-colors cursor-pointer group">
                            <div class="w-12 h-12 rounded-lg bg-brand-100 dark:bg-brand-900/20 text-brand-600 flex items-center justify-center mb-4"><i data-lucide="sheet" class="w-6 h-6"></i></div>
                            <h3 class="font-bold text-slate-900 dark:text-white group-hover:text-brand-600">Payroll Register</h3>
                            <p class="text-sm text-slate-500 mt-2">Comprehensive breakdown of all earnings and deductions.</p>
                        </div>
                    </div>
                </div>

                <!-- VIEW 8: DEDUCTIONS BREAKDOWN -->
                <div x-show="view === 'deductions'" x-cloak x-transition.opacity x-data="deductionsView()">
                    <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-4 mb-6">
                        <div>
                            <h2 class="text-lg font-bold text-slate-900 dark:text-white">Attendance Deduction Breakdown</h2>
                            <p class="text-sm text-slate-500">Review per-employee deductions for reconciliation</p>
                        </div>
                        <button x-show="selectedEmployee" @click="printReport()" class="px-4 py-2 bg-slate-100 dark:bg-slate-800 hover:bg-slate-200 dark:hover:bg-slate-700 text-slate-700 dark:text-slate-300 rounded-lg text-sm font-medium flex items-center gap-2 transition-colors">
                            <i data-lucide="printer" class="w-4 h-4"></i> Print Report
                        </button>
                    </div>
                    
                    <!-- Filters -->
                    <div class="bg-white dark:bg-slate-950 rounded-xl border border-slate-200 dark:border-slate-800 p-6 mb-6">
                        <div class="flex flex-wrap gap-4 items-end">
                            <!-- Employee Search Autocomplete -->
                            <div class="flex-1 min-w-[250px] relative">
                                <label class="block text-sm font-medium text-slate-700 dark:text-slate-300 mb-1">Search Employee</label>
                                <div class="relative">
                                    <input 
                                        type="text" 
                                        x-model="searchQuery" 
                                        @input.debounce.300ms="searchEmployees()"
                                        @focus="showResults = true"
                                        @keydown.arrow-down.prevent="highlightNext()"
                                        @keydown.arrow-up.prevent="highlightPrev()"
                                        @keydown.enter.prevent="selectHighlighted()"
                                        @keydown.escape="showResults = false"
                                        placeholder="Type name or ID to search..."
                                        class="w-full px-4 py-2.5 pl-10 border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-800 rounded-lg focus:ring-2 focus:ring-brand-500 focus:border-brand-500"
                                    />
                                    <i data-lucide="search" class="w-4 h-4 absolute left-3 top-1/2 -translate-y-1/2 text-slate-400"></i>
                                    <button x-show="selectedEmployee" @click="clearSelection()" class="absolute right-3 top-1/2 -translate-y-1/2 text-slate-400 hover:text-red-500">
                                        <i data-lucide="x" class="w-4 h-4"></i>
                                    </button>
                                </div>
                                
                                <!-- Search Results Dropdown -->
                                <div x-show="showResults && searchResults.length > 0" 
                                     @click.outside="showResults = false"
                                     x-transition
                                     class="absolute z-50 w-full mt-1 bg-white dark:bg-slate-800 border border-slate-200 dark:border-slate-700 rounded-lg shadow-lg max-h-60 overflow-y-auto">
                                    <template x-for="(emp, index) in searchResults" :key="emp.id">
                                        <div @click="selectEmployee(emp)"
                                             :class="highlightIndex === index ? 'bg-brand-50 dark:bg-brand-900/20' : ''"
                                             class="px-4 py-3 hover:bg-slate-50 dark:hover:bg-slate-700 cursor-pointer border-b border-slate-100 dark:border-slate-700 last:border-0">
                                            <div class="font-medium text-slate-900 dark:text-white" x-text="emp.name"></div>
                                            <div class="text-xs text-slate-500" x-text="emp.payroll_id + ' • ' + emp.department"></div>
                                        </div>
                                    </template>
                                </div>
                                
                                <!-- No Results -->
                                <div x-show="showResults && searchQuery.length >= 1 && searchResults.length === 0 && !searchLoading" 
                                     class="absolute z-50 w-full mt-1 bg-white dark:bg-slate-800 border border-slate-200 dark:border-slate-700 rounded-lg shadow-lg p-4 text-center text-slate-500">
                                    No employees found
                                </div>
                                
                                <!-- Loading -->
                                <div x-show="searchLoading" 
                                     class="absolute z-50 w-full mt-1 bg-white dark:bg-slate-800 border border-slate-200 dark:border-slate-700 rounded-lg shadow-lg p-4 text-center text-slate-500">
                                    Searching...
                                </div>
                            </div>
                            
                            <!-- Month Selector -->
                            <div class="w-40">
                                <label class="block text-sm font-medium text-slate-700 dark:text-slate-300 mb-1">Month</label>
                                <select x-model="filterMonth" @change="fetchBreakdown()" class="w-full px-4 py-2.5 border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-800 rounded-lg focus:ring-2 focus:ring-brand-500 focus:border-brand-500">
                                    <option value="1">January</option>
                                    <option value="2">February</option>
                                    <option value="3">March</option>
                                    <option value="4">April</option>
                                    <option value="5">May</option>
                                    <option value="6">June</option>
                                    <option value="7">July</option>
                                    <option value="8">August</option>
                                    <option value="9">September</option>
                                    <option value="10">October</option>
                                    <option value="11">November</option>
                                    <option value="12">December</option>
                                </select>
                            </div>
                            
                            <!-- Year Selector -->
                            <div class="w-28">
                                <label class="block text-sm font-medium text-slate-700 dark:text-slate-300 mb-1">Year</label>
                                <select x-model="filterYear" @change="fetchBreakdown()" class="w-full px-4 py-2.5 border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-800 rounded-lg focus:ring-2 focus:ring-brand-500 focus:border-brand-500">
                                    <?php for ($y = date('Y'); $y >= date('Y') - 2; $y--): ?>
                                    <option value="<?php echo $y; ?>"><?php echo $y; ?></option>
                                    <?php endfor; ?>
                                </select>
                            </div>
                        </div>
                    </div>
                    
                    <!-- No Employee Selected -->
                    <template x-if="!selectedEmployee">
                        <div class="bg-white dark:bg-slate-950 rounded-xl border border-slate-200 dark:border-slate-800 p-12 text-center">
                            <i data-lucide="users" class="w-16 h-16 mx-auto mb-4 text-slate-300 dark:text-slate-600"></i>
                            <h3 class="text-lg font-medium text-slate-700 dark:text-slate-300 mb-2">Select an Employee</h3>
                            <p class="text-slate-500">Choose an employee from the dropdown above to view their attendance deduction breakdown.</p>
                        </div>
                    </template>
                    
                    <!-- Employee Data -->
                    <template x-if="selectedEmployee">
                        <div>
                            <!-- Employee Info Card -->
                            <div class="bg-gradient-to-r from-brand-600 to-brand-700 rounded-xl p-6 mb-6 text-white">
                                <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-4">
                                    <div>
                                        <h2 class="text-xl font-bold" x-text="selectedEmployee.name"></h2>
                                        <p class="text-brand-100 text-sm" x-text="selectedEmployee.payroll_id + ' • ' + (selectedEmployee.category || 'No Category')"></p>
                                    </div>
                                    <div class="grid grid-cols-2 md:grid-cols-4 gap-4 text-sm">
                                        <div class="bg-white/10 rounded-lg px-4 py-2">
                                            <p class="text-brand-200 text-xs">Gross Salary</p>
                                            <p class="font-bold" x-text="'₦' + Number(selectedEmployee.gross_salary).toLocaleString()"></p>
                                        </div>
                                        <div class="bg-white/10 rounded-lg px-4 py-2">
                                            <p class="text-brand-200 text-xs">Working Days</p>
                                            <p class="font-bold" x-text="selectedEmployee.working_days + ' days'"></p>
                                        </div>
                                        <div class="bg-white/10 rounded-lg px-4 py-2">
                                            <p class="text-brand-200 text-xs">Daily Rate</p>
                                            <p class="font-bold" x-text="'₦' + Number(selectedEmployee.daily_rate).toLocaleString()"></p>
                                        </div>
                                        <div class="bg-white/10 rounded-lg px-4 py-2">
                                            <p class="text-brand-200 text-xs">Grace Period</p>
                                            <p class="font-bold" x-text="selectedEmployee.grace_period + ' mins'"></p>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            
                            <!-- Summary Cards -->
                            <div class="grid grid-cols-2 md:grid-cols-4 gap-4 mb-6">
                                <div class="bg-white dark:bg-slate-950 rounded-xl border border-slate-200 dark:border-slate-800 p-4">
                                    <p class="text-xs text-slate-500 uppercase font-medium">Total Late Minutes</p>
                                    <p class="text-2xl font-bold text-amber-600 mt-1" x-text="summary.total_late_minutes + ' mins'"></p>
                                </div>
                                <div class="bg-white dark:bg-slate-950 rounded-xl border border-slate-200 dark:border-slate-800 p-4">
                                    <p class="text-xs text-slate-500 uppercase font-medium">Total Absent Days</p>
                                    <p class="text-2xl font-bold text-red-600 mt-1" x-text="summary.total_absent_days + ' days'"></p>
                                </div>
                                <div class="bg-white dark:bg-slate-950 rounded-xl border border-slate-200 dark:border-slate-800 p-4">
                                    <p class="text-xs text-slate-500 uppercase font-medium">Grace Period Days</p>
                                    <p class="text-2xl font-bold text-green-600 mt-1" x-text="summary.grace_days + ' days'"></p>
                                    <p class="text-xs text-slate-400">No deduction applied</p>
                                </div>
                                <div class="bg-white dark:bg-slate-950 rounded-xl border border-red-200 dark:border-red-800 border-2 p-4">
                                    <p class="text-xs text-slate-500 uppercase font-medium">Total Deduction</p>
                                    <p class="text-2xl font-bold text-red-600 mt-1" x-text="'-₦' + Number(summary.total_deduction).toLocaleString()"></p>
                                </div>
                            </div>
                            
                            <!-- Breakdown Table -->
                            <div class="bg-white dark:bg-slate-950 rounded-xl border border-slate-200 dark:border-slate-800 overflow-hidden">
                                <div class="px-6 py-4 border-b border-slate-200 dark:border-slate-800">
                                    <h3 class="font-bold text-slate-900 dark:text-white">Daily Breakdown</h3>
                                </div>
                                
                                <template x-if="records.length === 0">
                                    <div class="p-12 text-center text-slate-500">
                                        <i data-lucide="check-circle" class="w-12 h-12 mx-auto mb-4 text-green-400"></i>
                                        <p class="font-medium">No deductions for this period</p>
                                        <p class="text-sm">Employee has perfect attendance record</p>
                                    </div>
                                </template>
                                
                                <template x-if="records.length > 0">
                                    <div class="overflow-x-auto">
                                        <table class="w-full text-sm">
                                            <thead class="bg-slate-50 dark:bg-slate-900 text-slate-600 dark:text-slate-400">
                                                <tr>
                                                    <th class="px-6 py-3 text-left font-medium">Date</th>
                                                    <th class="px-6 py-3 text-left font-medium">Day</th>
                                                    <th class="px-6 py-3 text-center font-medium">Type</th>
                                                    <th class="px-6 py-3 text-center font-medium">Expected</th>
                                                    <th class="px-6 py-3 text-center font-medium">Actual</th>
                                                    <th class="px-6 py-3 text-center font-medium">Late By</th>
                                                    <th class="px-6 py-3 text-right font-medium">Deduction</th>
                                                    <th class="px-6 py-3 text-center font-medium">Status</th>
                                                    <th class="px-6 py-3 text-center font-medium">Actions</th>
                                                </tr>
                                            </thead>
                                            <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
                                                <template x-for="rec in records" :key="rec.date">
                                                    <tr :class="rec.is_reversed ? 'bg-green-50 dark:bg-green-900/10' : (rec.is_grace ? 'bg-yellow-50 dark:bg-yellow-900/10' : '')">
                                                        <td class="px-6 py-4 font-medium text-slate-900 dark:text-white" x-text="rec.date_formatted"></td>
                                                        <td class="px-6 py-4 text-slate-500" x-text="rec.day_name"></td>
                                                        <td class="px-6 py-4 text-center">
                                                            <span x-show="rec.status === 'absent'" class="px-2 py-1 bg-red-100 text-red-700 dark:bg-red-900/30 dark:text-red-400 rounded-full text-xs font-bold">ABSENT</span>
                                                            <span x-show="rec.status === 'late'" class="px-2 py-1 bg-amber-100 text-amber-700 dark:bg-amber-900/30 dark:text-amber-400 rounded-full text-xs font-bold">LATE</span>
                                                        </td>
                                                        <td class="px-6 py-4 text-center text-slate-500" x-text="rec.expected_time || '08:00 AM'"></td>
                                                        <td class="px-6 py-4 text-center">
                                                            <span x-show="rec.status === 'absent'" class="text-red-500 font-medium">—</span>
                                                            <span x-show="rec.status !== 'absent'" x-text="rec.actual_time || '-'"></span>
                                                        </td>
                                                        <td class="px-6 py-4 text-center">
                                                            <span x-show="rec.status === 'absent'" class="text-red-600 font-medium">Full Day</span>
                                                            <span x-show="rec.status === 'late'" :class="rec.is_grace ? 'text-green-600' : 'text-amber-600'" class="font-medium" x-text="rec.late_minutes + ' mins'"></span>
                                                        </td>
                                                        <td class="px-6 py-4 text-right font-bold" :class="rec.is_reversed ? 'text-green-600 line-through' : 'text-red-600'">
                                                            <span x-show="rec.is_grace" class="text-green-600">₦0.00</span>
                                                            <span x-show="!rec.is_grace && rec.deduction > 0" x-text="'-₦' + Number(rec.deduction).toLocaleString()"></span>
                                                            <span x-show="!rec.is_grace && rec.deduction <= 0" class="text-slate-400">-</span>
                                                        </td>
                                                        <td class="px-6 py-4 text-center">
                                                            <span x-show="rec.is_reversed" class="text-xs text-green-600 font-medium">EXCUSED</span>
                                                            <span x-show="rec.is_grace && !rec.is_reversed" class="text-xs text-green-600 font-medium">GRACE</span>
                                                            <span x-show="!rec.is_grace && !rec.is_reversed && rec.deduction > 0" class="text-xs text-red-500 font-medium">DEDUCTED</span>
                                                        </td>
                                                        <td class="px-6 py-4 text-center">
                                                            <div class="flex items-center justify-center gap-1">
                                                                <button @click="viewCalculation(rec)" class="p-1.5 text-blue-600 hover:bg-blue-50 dark:hover:bg-blue-900/20 rounded-lg transition-colors" title="View Calculation">
                                                                    <i data-lucide="calculator" class="w-4 h-4"></i>
                                                                </button>
                                                                <button x-show="!rec.is_reversed && rec.deduction > 0" @click="openReversalModal(rec)" class="p-1.5 text-amber-600 hover:bg-amber-50 dark:hover:bg-amber-900/20 rounded-lg transition-colors" title="Reverse Deduction">
                                                                    <i data-lucide="undo-2" class="w-4 h-4"></i>
                                                                </button>
                                                            </div>
                                                        </td>
                                                    </tr>
                                                </template>
                                            </tbody>
                                            <tfoot class="bg-slate-100 dark:bg-slate-900 font-bold">
                                                <tr>
                                                    <td colspan="6" class="px-6 py-4 text-right text-slate-700 dark:text-slate-300">TOTAL DEDUCTION:</td>
                                                    <td class="px-6 py-4 text-right text-red-700 dark:text-red-400 text-lg" x-text="'-₦' + Number(summary.total_deduction).toLocaleString()"></td>
                                                    <td colspan="2" class="px-6 py-4"></td>
                                                </tr>
                                            </tfoot>
                                        </table>
                                    </div>
                                </template>
                            </div>
                            
                            <!-- Footer Note -->
                            <div class="mt-6 p-4 bg-amber-50 dark:bg-amber-900/10 border border-amber-200 dark:border-amber-800 rounded-xl text-sm text-amber-800 dark:text-amber-300">
                                <p class="font-medium mb-1">Note:</p>
                                <ul class="list-disc list-inside space-y-1 text-amber-700 dark:text-amber-400">
                                    <li>Grace period of <strong x-text="selectedEmployee?.grace_period || 15"></strong> minutes applies - arrivals within this window are not deducted.</li>
                                    <li>Daily rate is calculated as: Gross Salary ÷ Working Days in Month</li>
                                    <li>Deductions marked as <span class="text-green-600 font-medium">EXCUSED</span> have been reversed by admin.</li>
                                </ul>
                            </div>
                        </div>
                    </template>
                    
                    <!-- Calculation Breakdown Modal -->
                    <div x-show="showCalcModal" x-cloak 
                         class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/50"
                         @keydown.escape.window="showCalcModal = false">
                        <div @click.outside="showCalcModal = false" 
                             class="bg-white dark:bg-slate-900 rounded-2xl shadow-2xl w-full max-w-md overflow-hidden">
                            <div class="bg-gradient-to-r from-blue-600 to-blue-700 px-6 py-4 text-white">
                                <h3 class="text-lg font-bold">Deduction Calculation</h3>
                                <p class="text-blue-100 text-sm" x-text="calcDetails.date_formatted + ' • ' + calcDetails.status?.toUpperCase()"></p>
                            </div>
                            <div class="p-6 space-y-4">
                                <div class="grid grid-cols-2 gap-3 text-sm">
                                    <div class="text-slate-500">Gross Salary:</div>
                                    <div class="font-bold text-right" x-text="'₦' + Number(selectedEmployee?.gross_salary || 0).toLocaleString()"></div>
                                    
                                    <div class="text-slate-500">Working Days:</div>
                                    <div class="font-bold text-right" x-text="(selectedEmployee?.working_days || 22) + ' days'"></div>
                                    
                                    <div class="text-slate-500">Daily Rate:</div>
                                    <div class="font-bold text-right" x-text="'₦' + Number(selectedEmployee?.daily_rate || 0).toLocaleString()"></div>
                                </div>
                                
                                <hr class="border-slate-200 dark:border-slate-700"/>
                                
                                <!-- For Absent -->
                                <template x-if="calcDetails.status === 'absent'">
                                    <div class="grid grid-cols-2 gap-3 text-sm">
                                        <div class="text-slate-500">Days Absent:</div>
                                        <div class="font-bold text-right">× 1 day</div>
                                    </div>
                                </template>
                                
                                <!-- For Late -->
                                <template x-if="calcDetails.status === 'late'">
                                    <div class="grid grid-cols-2 gap-3 text-sm">
                                        <div class="text-slate-500">Late Minutes:</div>
                                        <div class="font-bold text-right" x-text="calcDetails.late_minutes + ' mins'"></div>
                                        
                                        <div class="text-slate-500">Grace Period:</div>
                                        <div class="font-bold text-right" x-text="(selectedEmployee?.grace_period || 15) + ' mins'"></div>
                                        
                                        <div class="text-slate-500">Chargeable:</div>
                                        <div class="font-bold text-right" x-text="Math.max(0, calcDetails.late_minutes - (selectedEmployee?.grace_period || 15)) + ' mins'"></div>
                                        
                                        <div class="text-slate-500">Hourly Rate:</div>
                                        <div class="font-bold text-right" x-text="'₦' + Number((selectedEmployee?.daily_rate || 0) / 8).toLocaleString(undefined, {maximumFractionDigits: 2})"></div>
                                    </div>
                                </template>
                                
                                <hr class="border-slate-200 dark:border-slate-700"/>
                                
                                <div class="flex justify-between items-center py-2 bg-red-50 dark:bg-red-900/20 px-4 rounded-lg">
                                    <span class="font-bold text-slate-700 dark:text-slate-300">TOTAL DEDUCTION:</span>
                                    <span class="text-xl font-bold text-red-600" x-text="'-₦' + Number(calcDetails.deduction || 0).toLocaleString()"></span>
                                </div>
                            </div>
                            <div class="px-6 pb-6">
                                <button @click="showCalcModal = false" class="w-full py-2.5 bg-slate-100 dark:bg-slate-800 text-slate-700 dark:text-slate-300 rounded-lg font-medium hover:bg-slate-200 dark:hover:bg-slate-700 transition-colors">
                                    Close
                                </button>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Reversal Modal -->
                    <div x-show="showReversalModal" x-cloak 
                         class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/50"
                         @keydown.escape.window="showReversalModal = false">
                        <div @click.outside="showReversalModal = false" 
                             class="bg-white dark:bg-slate-900 rounded-2xl shadow-2xl w-full max-w-md overflow-hidden">
                            <div class="bg-gradient-to-r from-amber-500 to-amber-600 px-6 py-4 text-white">
                                <h3 class="text-lg font-bold">Reverse Deduction</h3>
                                <p class="text-amber-100 text-sm">This will remove the deduction from payroll</p>
                            </div>
                            <div class="p-6 space-y-4">
                                <div class="p-4 bg-slate-50 dark:bg-slate-800 rounded-lg">
                                    <div class="flex justify-between mb-2">
                                        <span class="text-slate-500">Employee:</span>
                                        <span class="font-medium" x-text="selectedEmployee?.name"></span>
                                    </div>
                                    <div class="flex justify-between mb-2">
                                        <span class="text-slate-500">Date:</span>
                                        <span class="font-medium" x-text="reversalRecord?.date_formatted"></span>
                                    </div>
                                    <div class="flex justify-between">
                                        <span class="text-slate-500">Amount:</span>
                                        <span class="font-bold text-red-600" x-text="'-₦' + Number(reversalRecord?.deduction || 0).toLocaleString()"></span>
                                    </div>
                                </div>
                                
                                <div>
                                    <label class="block text-sm font-medium text-slate-700 dark:text-slate-300 mb-2">Reason for Reversal <span class="text-red-500">*</span></label>
                                    <textarea x-model="reversalReason" rows="3" 
                                              class="w-full px-4 py-3 border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-800 rounded-lg focus:ring-2 focus:ring-brand-500"
                                              placeholder="e.g., Doctor's note provided, Approved leave application..."></textarea>
                                </div>
                            </div>
                            <div class="px-6 pb-6 flex gap-3">
                                <button @click="showReversalModal = false" class="flex-1 py-2.5 bg-slate-100 dark:bg-slate-800 text-slate-700 dark:text-slate-300 rounded-lg font-medium hover:bg-slate-200 dark:hover:bg-slate-700 transition-colors">
                                    Cancel
                                </button>
                                <button @click="confirmReversal()" :disabled="!reversalReason.trim() || reversalLoading" 
                                        class="flex-1 py-2.5 bg-amber-600 text-white rounded-lg font-medium hover:bg-amber-700 transition-colors disabled:opacity-50 disabled:cursor-not-allowed flex items-center justify-center gap-2">
                                    <span x-show="!reversalLoading">Confirm Reversal</span>
                                    <span x-show="reversalLoading">Processing...</span>
                                </button>
                            </div>
                        </div>
                    </div>
                </div>

            </main>
        </div>


    <!-- Script Logic -->
    <script>
        lucide.createIcons();

    </script>
    <script>
        // Notifications Panel Component
        function notificationsPanel() {
            return {
                notifications: [],
                loading: false,
                
                async fetchNotifications() {
                    this.loading = true;
                    try {
                        const res = await fetch('ajax/notifications.php');
                        const data = await res.json();
                        if (data.status) {
                            this.notifications = data.notifications || [];
                        }
                    } catch (e) {
                        console.error('Notifications error:', e);
                    } finally {
                        this.loading = false;
                        setTimeout(() => lucide.createIcons(), 100);
                    }
                },
                
                formatTime(timestamp) {
                    if (!timestamp) return '';
                    const date = new Date(timestamp);
                    const now = new Date();
                    const diff = Math.floor((now - date) / 1000);
                    
                    if (diff < 60) return 'Just now';
                    if (diff < 3600) return Math.floor(diff / 60) + 'm ago';
                    if (diff < 86400) return Math.floor(diff / 3600) + 'h ago';
                    if (diff < 604800) return Math.floor(diff / 86400) + 'd ago';
                    return date.toLocaleDateString('en-NG', { day: 'numeric', month: 'short' });
                }
            };
        }
    </script>
    <script>
        // Payslips View Component
        function payslipsView() {
            return {
                loading: false,
                payslips: [],
                filteredPayslips: [],
                
                // Employee search
                employeeSearch: '',
                employeeResults: [],
                showEmployeeList: false,
                selectedEmployee: null,
                
                // Filters
                filters: {
                    department: '',
                    periodFrom: '',
                    periodTo: ''
                },
                sortBy: 'date_desc',
                
                init() {
                    this.fetchPayslips();
                    setTimeout(() => lucide.createIcons(), 100);
                },
                
                async fetchPayslips() {
                    this.loading = true;
                    try {
                        const res = await fetch('ajax/payroll_operations.php', {
                            method: 'POST',
                            headers: { 'Content-Type': 'application/json' },
                            body: JSON.stringify({ action: 'get_payslips' })
                        });
                        const data = await res.json();
                        if (data.status) {
                            this.payslips = data.payslips || [];
                            this.applyFilters();
                        }
                    } catch (e) {
                        console.error('Fetch payslips error:', e);
                    } finally {
                        this.loading = false;
                        setTimeout(() => lucide.createIcons(), 50);
                    }
                },
                
                async searchEmployees() {
                    if (this.employeeSearch.length < 2) {
                        this.employeeResults = [];
                        return;
                    }
                    try {
                        const res = await fetch('ajax/search_employees.php', {
                            method: 'POST',
                            headers: { 'Content-Type': 'application/json' },
                            body: JSON.stringify({ query: this.employeeSearch })
                        });
                        const data = await res.json();
                        if (data.status) {
                            this.employeeResults = data.employees || [];
                        }
                    } catch (e) {
                        console.error('Search error:', e);
                    }
                },
                
                selectEmployee(emp) {
                    this.selectedEmployee = emp;
                    this.employeeSearch = '';
                    this.showEmployeeList = false;
                    this.employeeResults = [];
                    this.applyFilters();
                    setTimeout(() => lucide.createIcons(), 50);
                },
                
                clearEmployeeFilter() {
                    this.selectedEmployee = null;
                    this.employeeSearch = '';
                    this.applyFilters();
                    setTimeout(() => lucide.createIcons(), 50);
                },
                
                setQuickPeriod(preset) {
                    const now = new Date();
                    const year = now.getFullYear();
                    const month = String(now.getMonth() + 1).padStart(2, '0');
                    
                    switch(preset) {
                        case 'current':
                            this.filters.periodFrom = `${year}-${month}`;
                            this.filters.periodTo = `${year}-${month}`;
                            break;
                        case 'last3':
                            const d3 = new Date(now.setMonth(now.getMonth() - 2));
                            this.filters.periodFrom = `${d3.getFullYear()}-${String(d3.getMonth() + 1).padStart(2, '0')}`;
                            this.filters.periodTo = `${year}-${month}`;
                            break;
                        case 'last6':
                            const d6 = new Date();
                            d6.setMonth(d6.getMonth() - 5);
                            this.filters.periodFrom = `${d6.getFullYear()}-${String(d6.getMonth() + 1).padStart(2, '0')}`;
                            this.filters.periodTo = `${year}-${month}`;
                            break;
                        case 'year':
                            this.filters.periodFrom = `${year}-01`;
                            this.filters.periodTo = `${year}-12`;
                            break;
                    }
                    this.applyFilters();
                },
                
                clearFilters() {
                    this.selectedEmployee = null;
                    this.employeeSearch = '';
                    this.filters = { department: '', periodFrom: '', periodTo: '' };
                    this.applyFilters();
                },
                
                applyFilters() {
                    let result = [...this.payslips];
                    
                    // Filter by employee
                    if (this.selectedEmployee) {
                        result = result.filter(s => s.employee_id == this.selectedEmployee.id);
                    }
                    
                    // Filter by department
                    if (this.filters.department) {
                        result = result.filter(s => s.department === this.filters.department);
                    }
                    
                    // Filter by period range
                    if (this.filters.periodFrom) {
                        const from = this.filters.periodFrom.replace('-', '');
                        result = result.filter(s => {
                            const period = `${s.year}${String(s.month).padStart(2, '0')}`;
                            return period >= from;
                        });
                    }
                    if (this.filters.periodTo) {
                        const to = this.filters.periodTo.replace('-', '');
                        result = result.filter(s => {
                            const period = `${s.year}${String(s.month).padStart(2, '0')}`;
                            return period <= to;
                        });
                    }
                    
                    this.filteredPayslips = result;
                    this.sortPayslips();
                },
                
                sortPayslips() {
                    switch(this.sortBy) {
                        case 'date_desc':
                            this.filteredPayslips.sort((a, b) => (b.year * 100 + b.month) - (a.year * 100 + a.month));
                            break;
                        case 'date_asc':
                            this.filteredPayslips.sort((a, b) => (a.year * 100 + a.month) - (b.year * 100 + b.month));
                            break;
                        case 'name_asc':
                            this.filteredPayslips.sort((a, b) => a.employee_name.localeCompare(b.employee_name));
                            break;
                        case 'amount_desc':
                            this.filteredPayslips.sort((a, b) => parseFloat(b.net_pay) - parseFloat(a.net_pay));
                            break;
                    }
                },
                
                formatCurrency(amt) {
                    return '₦ ' + parseFloat(amt || 0).toLocaleString('en-NG', { minimumFractionDigits: 2 });
                },
                
                viewPayslip(slip) {
                    // Use parent component's payslip modal
                    const parent = Alpine.$data(document.querySelector('[x-data]'));
                    if (parent && parent.openPayslipModal) {
                        parent.openPayslipModal(slip);
                    } else {
                        alert('Viewing payslip for ' + slip.employee_name);
                    }
                },
                
                downloadPayslip(slip) {
                    alert('Download payslip for ' + slip.employee_name + ' - ' + slip.period);
                },
                
                exportPayslipsList() {
                    let csv = 'Period,Employee,Payroll ID,Department,Gross,Deductions,Net Pay,Status\n';
                    this.filteredPayslips.forEach(s => {
                        csv += `"${s.period}","${s.employee_name}","${s.payroll_id}","${s.department || ''}",${s.gross_salary},${s.total_deductions},${s.net_pay},"${s.status}"\n`;
                    });
                    const blob = new Blob([csv], { type: 'text/csv' });
                    const url = URL.createObjectURL(blob);
                    const a = document.createElement('a');
                    a.href = url;
                    a.download = 'payslips_export.csv';
                    a.click();
                    URL.revokeObjectURL(url);
                }
            };
        }
    </script>
    <script>
        // Deductions View Component
        function deductionsView() {
            return {
                selectedEmployeeId: '',
                filterMonth: new Date().getMonth() + 1,
                filterYear: new Date().getFullYear(),
                selectedEmployee: null,
                records: [],
                summary: {
                    total_late_minutes: 0,
                    total_absent_days: 0,
                    grace_days: 0,
                    total_deduction: 0
                },
                loading: false,
                
                // Search properties
                searchQuery: '',
                searchResults: [],
                searchLoading: false,
                showResults: false,
                highlightIndex: -1,
                
                // Modal properties
                showCalcModal: false,
                calcDetails: {},
                showReversalModal: false,
                reversalRecord: null,
                reversalReason: '',
                reversalLoading: false,
                
                init() {
                    setTimeout(() => lucide.createIcons(), 100);
                },
                
                async searchEmployees() {
                    if (this.searchQuery.length < 1) {
                        this.searchResults = [];
                        return;
                    }
                    
                    this.searchLoading = true;
                    try {
                        const res = await fetch('ajax/search_employees.php', {
                            method: 'POST',
                            headers: { 'Content-Type': 'application/json' },
                            body: JSON.stringify({ query: this.searchQuery })
                        });
                        const data = await res.json();
                        if (data.status) {
                            this.searchResults = data.employees;
                            this.highlightIndex = -1;
                        }
                    } catch (e) {
                        console.error('Search error:', e);
                    } finally {
                        this.searchLoading = false;
                        this.showResults = true;
                    }
                },
                
                selectEmployee(emp) {
                    this.selectedEmployeeId = emp.id;
                    this.searchQuery = emp.name + ' (' + emp.payroll_id + ')';
                    this.showResults = false;
                    this.searchResults = [];
                    this.fetchBreakdown();
                },
                
                clearSelection() {
                    this.selectedEmployeeId = '';
                    this.selectedEmployee = null;
                    this.searchQuery = '';
                    this.records = [];
                    this.summary = { total_late_minutes: 0, total_absent_days: 0, grace_days: 0, total_deduction: 0 };
                },
                
                highlightNext() {
                    if (this.highlightIndex < this.searchResults.length - 1) {
                        this.highlightIndex++;
                    }
                },
                
                highlightPrev() {
                    if (this.highlightIndex > 0) {
                        this.highlightIndex--;
                    }
                },
                
                selectHighlighted() {
                    if (this.highlightIndex >= 0 && this.searchResults[this.highlightIndex]) {
                        this.selectEmployee(this.searchResults[this.highlightIndex]);
                    }
                },
                
                async fetchBreakdown() {
                    if (!this.selectedEmployeeId) {
                        this.selectedEmployee = null;
                        this.records = [];
                        return;
                    }
                    
                    this.loading = true;
                    try {
                        const res = await fetch('ajax/deduction_breakdown.php', {
                            method: 'POST',
                            headers: { 'Content-Type': 'application/json' },
                            body: JSON.stringify({
                                employee_id: this.selectedEmployeeId,
                                month: this.filterMonth,
                                year: this.filterYear
                            })
                        });
                        const data = await res.json();
                        
                        if (data.status) {
                            this.selectedEmployee = data.employee;
                            this.records = data.records;
                            this.summary = data.summary;
                            setTimeout(() => lucide.createIcons(), 50);
                        } else {
                            alert('Error: ' + data.message);
                            this.selectedEmployee = null;
                        }
                    } catch (e) {
                        alert('Error fetching breakdown: ' + e.message);
                    } finally {
                        this.loading = false;
                    }
                },
                
                printReport() {
                    window.print();
                },
                
                viewCalculation(rec) {
                    this.calcDetails = rec;
                    this.showCalcModal = true;
                    setTimeout(() => lucide.createIcons(), 50);
                },
                
                openReversalModal(rec) {
                    this.reversalRecord = rec;
                    this.reversalReason = '';
                    this.showReversalModal = true;
                },
                
                async confirmReversal() {
                    if (!this.reversalReason.trim()) {
                        alert('Please provide a reason for reversal');
                        return;
                    }
                    
                    this.reversalLoading = true;
                    try {
                        const res = await fetch('ajax/reverse_deduction.php', {
                            method: 'POST',
                            headers: { 'Content-Type': 'application/json' },
                            body: JSON.stringify({
                                record_date: this.reversalRecord.date,
                                employee_id: this.selectedEmployeeId,
                                reason: this.reversalReason
                            })
                        });
                        const data = await res.json();
                        
                        if (data.status) {
                            this.showReversalModal = false;
                            // Refresh the breakdown
                            await this.fetchBreakdown();
                            alert('Deduction reversed successfully');
                        } else {
                            alert('Error: ' + data.message);
                        }
                    } catch (e) {
                        alert('Error reversing deduction: ' + e.message);
                    } finally {
                        this.reversalLoading = false;
                    }
                }
            };
        }
        
        // Start Alpine
        document.addEventListener('alpine:init', () => {
             // Any direct inits if needed
        });
    </script>
    <?php include '../includes/dashboard_scripts.php'; ?>
</body>
</html>
