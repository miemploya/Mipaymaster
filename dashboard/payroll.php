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
                    taxable: true, // Default to taxable per PIT Act
                    notes: ''
                },
                adjustments: [],
                masterBonuses: <?php echo json_encode($master_bonuses); ?>,
                
                // OVERTIME MODAL (NEW)
                overtimeModalOpen: false,
                overtimeForm: {
                    employee_id: null,
                    employee_name: '',
                    hours: 0,
                    notes: ''
                },
                overtimeConfig: {
                    enabled: <?php 
                        $stmt = $pdo->prepare("SELECT overtime_enabled, daily_work_hours, monthly_work_days, overtime_rate FROM statutory_settings WHERE company_id = ?");
                        $stmt->execute([$company_id]);
                        $ot_config = $stmt->fetch(PDO::FETCH_ASSOC);
                        echo ($ot_config && $ot_config['overtime_enabled']) ? 'true' : 'false';
                    ?>,
                    dailyHours: <?php echo $ot_config['daily_work_hours'] ?? 8.00; ?>,
                    monthlyDays: <?php echo $ot_config['monthly_work_days'] ?? 22; ?>,
                    rate: <?php echo $ot_config['overtime_rate'] ?? 1.50; ?>
                },
                
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
                            year: this.currentPeriod.year
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
                
                // ADJUSTMENT METHODS
                openAdjustment(emp) {
                    this.adjustmentForm = {
                        employee_id: emp.employee_id,
                        employee_name: emp.first_name + ' ' + emp.last_name,
                        type: 'bonus',
                        name: '',
                        amount: 0,
                        notes: ''
                    };
                    this.adjustmentModalOpen = true;
                    setTimeout(() => lucide.createIcons(), 50);
                },
                
                async saveAdjustment() {
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
                    
                    // Create a clean table for PDF
                    const table = document.querySelector('.payroll-table');
                    const clone = table.cloneNode(true);
                    
                    // Remove Actions column from clone
                    clone.querySelectorAll('th:last-child, td:last-child').forEach(el => el.remove());
                    
                    // Create wrapper
                    const wrapper = document.createElement('div');
                    wrapper.style.cssText = 'padding: 20px; background: white;';
                    wrapper.innerHTML = `
                        <div style="text-align: center; margin-bottom: 20px;">
                            <h2 style="margin: 0; font-size: 18px; font-weight: bold;">Payroll Sheet</h2>
                            <p style="margin: 5px 0; color: #666; font-size: 12px;">Period: ${new Date(this.currentPeriod.year, this.currentPeriod.month - 1).toLocaleDateString('en-NG', { month: 'long', year: 'numeric' })}</p>
                            <p style="margin: 5px 0; color: #666; font-size: 11px;">Generated: ${new Date().toLocaleString()}</p>
                        </div>
                    `;
                    wrapper.appendChild(clone);
                    
                    // Style the cloned table
                    clone.style.cssText = 'width: 100%; border-collapse: collapse; font-size: 8px;';
                    clone.querySelectorAll('th, td').forEach(cell => {
                        cell.style.cssText = 'border: 1px solid #ddd; padding: 4px 6px; text-align: left;';
                    });
                    clone.querySelectorAll('th').forEach(th => {
                        th.style.cssText += 'background: #f5f5f5; font-weight: bold;';
                    });
                    
                    const opt = {
                        margin: [10, 5, 10, 5],
                        filename: `Payroll_${this.currentPeriod.year}_${String(this.currentPeriod.month).padStart(2, '0')}.pdf`,
                        image: { type: 'jpeg', quality: 0.98 },
                        html2canvas: { scale: 2, useCORS: true },
                        jsPDF: { unit: 'mm', format: 'a4', orientation: 'landscape' }
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
        <div id="notif-panel" class="fixed inset-y-0 right-0 w-80 bg-white dark:bg-slate-950 shadow-2xl transform translate-x-full transition-transform duration-300 z-50 border-l border-slate-200 dark:border-slate-800" style="visibility: hidden;">
            <div class="h-16 flex items-center justify-between px-6 border-b border-slate-100 dark:border-slate-800">
                <h3 class="text-lg font-bold text-slate-900 dark:text-white">Notifications</h3>
                <button id="notif-close" class="text-slate-500 hover:text-slate-900 dark:hover:text-white"><i data-lucide="x" class="w-5 h-5"></i></button>
            </div>
            <div class="p-4 space-y-4 overflow-y-auto h-[calc(100vh-64px)]">
                <div class="p-3 bg-brand-50 dark:bg-brand-900/10 rounded-lg border-l-4 border-brand-500">
                    <p class="text-sm font-bold text-slate-900 dark:text-white mb-1">Payroll Completed</p>
                    <p class="text-xs text-slate-600 dark:text-slate-400">January 2026 payroll has been processed successfully.</p>
                    <div class="mt-2 flex gap-2">
                        <button class="text-xs text-brand-600 font-medium hover:underline">View</button>
                    </div>
                </div>
                <div class="p-3 bg-white dark:bg-slate-900 rounded-lg border border-slate-100 dark:border-slate-800">
                    <p class="text-sm font-bold text-slate-900 dark:text-white mb-1">Approval Required</p>
                    <p class="text-xs text-slate-600 dark:text-slate-400">2 leave requests are pending your approval.</p>
                    <div class="mt-2 flex gap-2">
                        <button class="text-xs text-brand-600 font-medium hover:underline">Review</button>
                    </div>
                </div>
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
                                <select class="w-full rounded-lg border-slate-300 dark:border-slate-700 bg-white dark:bg-slate-800 text-slate-900 dark:text-white focus:ring-brand-500 focus:border-brand-500 transition-colors shadow-sm text-sm p-2.5">
                                    <option>Regular Monthly</option>
                                    <option>Supplementary (Bonus/Arrears)</option>
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
                                    <!-- Sticky Columns -->
                                    <th class="sticky-col-left sticky-corner bg-slate-100 dark:bg-slate-900 min-w-[200px]">Employee</th>
                                    
                                    <!-- Earnings Group -->
                                    <th class="bg-green-50/50 dark:bg-green-900/10 border-l border-slate-200 dark:border-slate-800 min-w-[100px]">Basic</th>
                                    <th class="bg-green-50/50 dark:bg-green-900/10 min-w-[100px]">Housing</th>
                                    <th class="bg-green-50/50 dark:bg-green-900/10 min-w-[100px]">Transport</th>
                                    <th class="bg-green-50/50 dark:bg-green-900/10 min-w-[100px] text-green-700 dark:text-green-400">Bonus</th>
                                    <th class="bg-orange-50/50 dark:bg-orange-900/10 min-w-[80px] text-orange-600 dark:text-orange-400">OT</th>
                                    <th class="bg-slate-200 dark:bg-slate-800 min-w-[120px]">Gross Pay</th>
                                    
                                    <!-- Deductions Group -->
                                    <th class="bg-red-50/50 dark:bg-red-900/10 border-l border-slate-200 dark:border-slate-800 min-w-[100px]">PAYE</th>
                                    <th class="bg-red-50/50 dark:bg-red-900/10 min-w-[100px]">Pension</th>
                                    <th class="bg-red-50/50 dark:bg-red-900/10 min-w-[100px]">NHIS</th>
                                    <th class="bg-red-50/50 dark:bg-red-900/10 min-w-[100px] text-amber-600 dark:text-amber-400">Lateness</th>
                                    <th class="bg-red-50/50 dark:bg-red-900/10 min-w-[100px] text-red-700 dark:text-red-400">Loan</th>
                                    <th class="bg-red-50/50 dark:bg-red-900/10 min-w-[100px] text-red-700 dark:text-red-400">Deductions</th>
                                    
                                    <!-- Summary -->
                                    <th class="bg-brand-50 dark:bg-brand-900/20 border-l border-slate-200 dark:border-slate-800 text-brand-700 dark:text-white min-w-[140px]">Net Pay</th>
                                    <th class="bg-slate-100 dark:bg-slate-900 min-w-[80px] text-center">Actions</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-100 dark:divide-slate-800 text-slate-700 dark:text-slate-300">
                                <template x-for="emp in sheetData" :key="emp.id">
                                    <tr class="hover:bg-slate-50 dark:hover:bg-slate-900">
                                        <td class="sticky-col-left bg-white dark:bg-slate-950 border-r border-slate-200 dark:border-slate-700 font-medium">
                                            <div x-text="emp.first_name + ' ' + emp.last_name"></div>
                                            <span class="text-[10px] text-slate-400" x-text="emp.payroll_id || 'N/A'"></span>
                                        </td>
                                        <!-- Earnings -->
                                        <td class="bg-green-50/5 dark:bg-transparent" x-text="formatCurrency(emp.breakdown.basic)"></td>
                                        <td class="bg-green-50/5 dark:bg-transparent" x-text="formatCurrency(emp.breakdown.housing)"></td>
                                        <td class="bg-green-50/5 dark:bg-transparent" x-text="formatCurrency(emp.breakdown.transport)"></td>
                                        <td class="bg-green-50/5 dark:bg-transparent text-green-600" x-text="formatCurrency(emp.breakdown.bonus || 0)"></td>
                                        <!-- OT Column -->
                                        <td class="bg-orange-50/10 dark:bg-transparent">
                                            <div class="flex items-center gap-1">
                                                <span x-show="(emp.breakdown.overtime_hours || 0) > 0" class="text-orange-600 font-medium" x-text="(emp.breakdown.overtime_hours || 0) + 'h'"></span>
                                                <button @click="openOvertimeModal(emp)" class="px-1.5 py-0.5 text-[10px] bg-orange-100 text-orange-600 rounded hover:bg-orange-200 transition-colors" title="Add/Edit Overtime">
                                                    <i data-lucide="clock" class="w-3 h-3 inline"></i>
                                                </button>
                                            </div>
                                        </td>
                                        <td class="font-bold bg-slate-50 dark:bg-slate-900" x-text="formatCurrency(emp.gross_salary)"></td>
                                        
                                        <!-- Deductions -->
                                        <td class="bg-red-50/5 dark:bg-transparent" x-text="formatCurrency(emp.breakdown.paye)"></td>
                                        <td class="bg-red-50/5 dark:bg-transparent" x-text="formatCurrency(emp.breakdown.pension)"></td>
                                        <td class="bg-red-50/5 dark:bg-transparent" x-text="formatCurrency(emp.breakdown.nhis)"></td>
                                        <td class="bg-red-50/5 dark:bg-transparent text-amber-600" x-text="formatCurrency(emp.breakdown.lateness || 0)"></td>
                                        <td class="bg-red-50/5 dark:bg-transparent text-red-500" x-text="formatCurrency(emp.breakdown.loan)"></td>
                                        <td class="bg-red-50/5 dark:bg-transparent text-red-500" x-text="formatCurrency(emp.breakdown.custom_deductions || 0)"></td>
                                        
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
                        <div class="relative bg-white dark:bg-slate-950 rounded-xl shadow-2xl w-full max-w-md mx-4 overflow-hidden" @click.stop>
                            <div class="px-6 py-4 border-b border-slate-100 dark:border-slate-800 flex items-center justify-between">
                                <h3 class="text-lg font-bold text-slate-900 dark:text-white">Add One-Time Adjustment</h3>
                                <button @click="adjustmentModalOpen = false" class="text-slate-400 hover:text-slate-600">
                                    <i data-lucide="x" class="w-5 h-5"></i>
                                </button>
                            </div>
                            <div class="p-6 space-y-4">
                                <div class="p-3 bg-slate-50 dark:bg-slate-900 rounded-lg">
                                    <p class="text-xs text-slate-500">Employee</p>
                                    <p class="font-bold text-slate-900 dark:text-white" x-text="adjustmentForm.employee_name"></p>
                                </div>
                                
                                <div>
                                    <label class="block text-sm font-medium text-slate-700 dark:text-slate-300 mb-1">Type</label>
                                    <div class="grid grid-cols-2 gap-2">
                                        <label class="flex items-center gap-2 p-3 rounded-lg border cursor-pointer transition-colors" :class="adjustmentForm.type === 'bonus' ? 'border-green-500 bg-green-50 dark:bg-green-900/20' : 'border-slate-200 dark:border-slate-700'">
                                            <input type="radio" x-model="adjustmentForm.type" value="bonus" class="text-green-600">
                                            <span class="text-sm font-medium">Bonus</span>
                                        </label>
                                        <label class="flex items-center gap-2 p-3 rounded-lg border cursor-pointer transition-colors" :class="adjustmentForm.type === 'deduction' ? 'border-red-500 bg-red-50 dark:bg-red-900/20' : 'border-slate-200 dark:border-slate-700'">
                                            <input type="radio" x-model="adjustmentForm.type" value="deduction" class="text-red-600">
                                            <span class="text-sm font-medium">Deduction</span>
                                        </label>
                                    </div>
                                </div>
                                
                                <div x-show="adjustmentForm.type === 'bonus'">
                                    <label class="block text-sm font-medium text-slate-700 dark:text-slate-300 mb-1">Select Bonus Type</label>
                                    <div class="relative">
                                        <select x-model="adjustmentForm.name" @change="if(adjustmentForm.name === 'Others') adjustmentForm.customName = ''" class="w-full rounded-lg border-slate-300 dark:border-slate-700 bg-white dark:bg-slate-800 p-2.5 text-sm">
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
                                        <input type="text" x-model="adjustmentForm.customName" class="w-full rounded-lg border-slate-300 dark:border-slate-700 bg-white dark:bg-slate-800 p-2.5 text-sm" placeholder="Enter custom bonus name...">
                                    </div>
                                </div>
                                
                                <div x-show="adjustmentForm.type === 'deduction'">
                                    <label class="block text-sm font-medium text-slate-700 dark:text-slate-300 mb-1">Name/Description</label>
                                    <input type="text" x-model="adjustmentForm.name" class="w-full rounded-lg border-slate-300 dark:border-slate-700 bg-white dark:bg-slate-800 p-2.5 text-sm" placeholder="e.g. Loan Repayment, Salary Advance">
                                </div>
                                
                                <div>
                                    <label class="block text-sm font-medium text-slate-700 dark:text-slate-300 mb-1">Amount (₦)</label>
                                    <input type="number" x-model="adjustmentForm.amount" class="w-full rounded-lg border-slate-300 dark:border-slate-700 bg-white dark:bg-slate-800 p-2.5 text-sm" placeholder="0.00" step="100">
                                </div>
                                
                                <!-- Taxable Checkbox with PIT Notice -->
                                <div x-show="adjustmentForm.type === 'bonus'" class="p-3 bg-amber-50 dark:bg-amber-900/20 rounded-lg border border-amber-200 dark:border-amber-800">
                                    <label class="flex items-start gap-3 cursor-pointer">
                                        <input type="checkbox" x-model="adjustmentForm.taxable" class="mt-0.5 text-amber-600 rounded" checked>
                                        <div>
                                            <span class="text-sm font-medium text-slate-800 dark:text-slate-200">Taxable Bonus</span>
                                            <p class="text-xs text-amber-700 dark:text-amber-400 mt-0.5">
                                                <i data-lucide="info" class="w-3 h-3 inline"></i>
                                                Per PIT Act, all bonuses are taxable income. Only uncheck if you have verified tax exemption.
                                            </p>
                                        </div>
                                    </label>
                                </div>
                                
                                <div>
                                    <label class="block text-sm font-medium text-slate-700 dark:text-slate-300 mb-1">Notes (Optional)</label>
                                    <textarea x-model="adjustmentForm.notes" class="w-full rounded-lg border-slate-300 dark:border-slate-700 bg-white dark:bg-slate-800 p-2.5 text-sm" rows="2" placeholder="Add notes..."></textarea>
                                </div>
                            </div>
                            <div class="px-6 py-4 bg-slate-50 dark:bg-slate-900 border-t border-slate-100 dark:border-slate-800 flex justify-end gap-3">
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
                <div x-show="view === 'preview'" x-cloak x-transition.opacity class="max-w-5xl mx-auto">
                    <h2 class="text-xl font-bold text-slate-900 dark:text-white mb-6">Payroll Preview & Validation</h2>
                    
                    <!-- Totals Grid -->
                    <div class="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-6 gap-4 mb-8">
                        <div class="bg-white dark:bg-slate-950 p-4 rounded-xl border border-slate-200 dark:border-slate-800 overflow-hidden">
                            <p class="text-xs text-slate-500 uppercase truncate">Gross Pay</p>
                            <p class="text-lg font-bold text-slate-900 dark:text-white truncate" x-text="formatCurrency(totals.gross)" :title="formatCurrency(totals.gross)"></p>
                        </div>
                        <div class="bg-white dark:bg-slate-950 p-4 rounded-xl border border-slate-200 dark:border-slate-800 overflow-hidden">
                            <p class="text-xs text-slate-500 uppercase truncate">Total Deductions</p>
                            <p class="text-lg font-bold text-red-600 dark:text-red-400 truncate" x-text="formatCurrency(totals.deductions)" :title="formatCurrency(totals.deductions)"></p>
                        </div>
                        <!-- Summaries below can be calculated from breakdown sum if needed, but for now we use placeholder or 0 if not pre-calc -->
                        <!-- Actually totals.deductions includes PAYE+Pension+NHIS. We can't split easily without looping. -->
                        <!-- Let's just show Deductions again or omit specifics if not available in totals object. -->
                        <!-- Or better: We bind to specific sub-totals if we added them to totals response. I only added gross, deductions, net. -->
                        <!-- Suggestion: Just show gross, deductions, net for MVP reliability. -->
                        
                         <div class="bg-white dark:bg-slate-950 p-4 rounded-xl border border-slate-200 dark:border-slate-800 col-span-2 bg-brand-50 dark:bg-brand-900/10 border-brand-200 dark:border-brand-900/30 overflow-hidden">
                            <p class="text-xs text-brand-600 uppercase font-bold truncate">Total Net Pay Cost</p>
                            <p class="text-2xl font-bold text-brand-700 dark:text-brand-300 truncate" x-text="formatCurrency(totals.net)" :title="formatCurrency(totals.net)"></p>
                        </div>
                    </div>

                    <!-- Exceptions Panel -->
                    <div class="bg-white dark:bg-slate-950 rounded-xl border border-red-200 dark:border-red-900/30 overflow-hidden mb-8" x-show="anomalies.length > 0">
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
                    <div class="bg-white dark:bg-slate-950 rounded-xl border border-green-200 dark:border-green-900/30 overflow-hidden mb-8" x-show="anomalies.length === 0">
                         <div class="p-4 bg-green-50 dark:bg-green-900/10 border-b border-green-100 dark:border-green-900/20 flex items-center gap-2">
                            <i data-lucide="check-circle" class="w-5 h-5 text-green-600"></i>
                            <h3 class="font-bold text-green-800 dark:text-green-300">No Anomalies Detected</h3>
                        </div>
                        <div class="p-4 text-sm text-slate-600 dark:text-slate-400">
                            Payroll data looks consistent with rules. Proceed with validation.
                        </div>
                    </div>

                    <div class="flex justify-between">
                        <button @click="changeView('sheet')" class="px-6 py-2 text-slate-600 border border-slate-300 rounded-lg hover:bg-slate-50 font-medium">Back to Sheet</button>
                        <button @click="changeView('approval')" class="px-6 py-2 bg-slate-900 dark:bg-white text-white dark:text-slate-900 rounded-lg font-bold shadow-lg hover:opacity-90">Submit for Approval</button>
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
                                <button class="flex-1 py-3 border border-red-200 text-red-600 rounded-xl hover:bg-red-50 dark:hover:bg-red-900/10 font-bold transition-colors">Reject Payroll</button>
                                <button @click="approvePayroll()" class="flex-1 py-3 bg-green-600 text-white rounded-xl hover:bg-green-700 font-bold shadow-md shadow-green-500/30 transition-colors flex items-center justify-center gap-2">
                                    <span x-show="!loading"><i data-lucide="lock" class="w-4 h-4"></i> Approve & Post</span>
                                    <span x-show="loading">Processing...</span>
                                </button>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- VIEW 6: PAYSLIPS -->
                <div x-show="view === 'payslips'" x-cloak x-transition.opacity>
                    <h2 class="text-lg font-bold text-slate-900 dark:text-white mb-6">Employee Payslips</h2>
                    <div class="bg-white dark:bg-slate-950 rounded-xl border border-slate-200 dark:border-slate-800 overflow-hidden">
                        <table class="w-full text-left text-sm">
                            <thead class="bg-slate-50 dark:bg-slate-900 border-b border-slate-200 dark:border-slate-800 text-slate-500">
                                <tr>
                                    <th class="p-4">Period</th>
                                    <th class="p-4">Employee</th>
                                    <th class="p-4">Net Pay</th>
                                    <th class="p-4">Status</th>
                                    <th class="p-4 text-center">Action</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
                                <tr class="hover:bg-slate-50 dark:hover:bg-slate-900/50">
                                    <td class="p-4"><?php echo date('M Y'); ?></td>
                                    <td class="p-4 font-medium">John Doe</td>
                                    <td class="p-4 font-bold text-slate-900 dark:text-white">₦ 207,500</td>
                                    <td class="p-4"><span class="px-2 py-1 rounded text-xs font-bold uppercase tracking-wider bg-green-100 text-green-700 dark:bg-green-900/40 dark:text-green-400">Paid</span></td>
                                    <td class="p-4 text-center"><button class="text-brand-600 hover:underline">View PDF</button></td>
                                </tr>
                                <!-- More rows -->
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

            </main>
        </div>


    <!-- Script Logic -->
    <script>
        lucide.createIcons();

    </script>
    <script>
        // Start Alpine
        document.addEventListener('alpine:init', () => {
             // Any direct inits if needed
        });
    </script>
    <?php include '../includes/dashboard_scripts.php'; ?>
</body>
</html>
