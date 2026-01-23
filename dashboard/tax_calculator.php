<?php
require_once '../includes/functions.php';
require_login();
$current_page = 'tax_calculator';

// Simuluate loading config from DB
// Simuluate loading config from DB
$tax_config = [
    'cra_fixed' => 0, // Removed in NTA 2025
    'cra_percent' => 0,
    'cra_min_percent' => 0,
    'pension' => [
        'enabled' => true,
        'rate_emp' => 8,
        'rate_empr' => 10,
        'base' => 'bht' // basic, bht (basic+housing+transport), gross
    ],
    // NTA 2025 Bands
    'tax_bands' => [
        ['from' => 0, 'to' => 800000, 'rate' => 0],
        ['from' => 800001, 'to' => 3000000, 'rate' => 15],
        ['from' => 3000001, 'to' => 12000000, 'rate' => 18],
        ['from' => 12000001, 'to' => 25000000, 'rate' => 21],
        ['from' => 25000001, 'to' => 50000000, 'rate' => 23],
        ['from' => 50000001, 'to' => null, 'rate' => 25],
    ]
];

// Fetch Active Categories
// Fetch Active Categories (V2 Schema Adaptation)
$categories = [];
try {
    // 1. Fetch Categories (Wrapped in check to prevent Warning)
    if (isset($_SESSION['company_id'])) {
        $stmt = $pdo->prepare("SELECT * FROM salary_categories WHERE company_id = ? AND is_active = 1 ORDER BY name ASC");
        $stmt->execute([$_SESSION['company_id']]);
        $categories_raw = $stmt->fetchAll(PDO::FETCH_ASSOC);

        if($categories_raw) {
            // 2. Fetch Components Map
            $comp_map = [];
            $stmt_c = $pdo->prepare("SELECT id, name FROM salary_components WHERE company_id = ?");
            $stmt_c->execute([$_SESSION['company_id']]);
            while($row = $stmt_c->fetch(PDO::FETCH_ASSOC)) {
                $comp_map[$row['id']] = $row['name'];
            }

            // 3. Fetch Breakdowns
            $stmt_b = $pdo->prepare("SELECT scb.* FROM salary_category_breakdown scb JOIN salary_categories sc ON scb.category_id = sc.id WHERE sc.company_id = ?");
            $stmt_b->execute([$_SESSION['company_id']]);
            $all_breakdowns = $stmt_b->fetchAll(PDO::FETCH_ASSOC);

            // Group breakdowns
            $breaks_by_cat = [];
            foreach($all_breakdowns as $row) {
                $breaks_by_cat[$row['category_id']][] = $row;
            }

            // 4. Transform to Frontend Format (Flat structure with _perc)
            $categories = array_map(function($c) use ($breaks_by_cat, $comp_map) {
                $my_breaks = $breaks_by_cat[$c['id']] ?? [];
            
            $basic = 0; $housing = 0; $transport = 0; $other = 0;
            
            foreach($my_breaks as $b) {
                // Fix: Use correct column name salary_component_id
                $c_name = $comp_map[$b['salary_component_id']] ?? '';
                $perc = floatval($b['percentage']);
                
                if ($c_name === 'Basic Salary') $basic += $perc;
                elseif ($c_name === 'Housing Allowance') $housing += $perc;
                elseif ($c_name === 'Transport Allowance') $transport += $perc;
                else $other += $perc;
            }

            return [
                'id' => (int)$c['id'],
                'name' => $c['name'], // Map name -> name for JS compatibility
                'base_gross_amount' => floatval($c['base_gross_amount']),
                'basic_perc' => $basic,
                'housing_perc' => $housing,
                'transport_perc' => $transport,
                'other_perc' => $other
            ];
        }, $categories_raw);
        }
    }
} catch (Exception $e) {
    // Fail silently or log
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>PAYE Tax Calculator (NTA 2025) - Mipaymaster</title>
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
    <!-- html2pdf.js -->
    <script src="https://cdnjs.cloudflare.com/ajax/libs/html2pdf.js/0.10.1/html2pdf.bundle.min.js"></script>
    
    <style type="text/tailwindcss">
        @import url('https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap');
        body { font-family: 'Inter', sans-serif; }
        
        ::-webkit-scrollbar { width: 6px; height: 6px; }
        ::-webkit-scrollbar-track { background: transparent; }
        ::-webkit-scrollbar-thumb { background: #cbd5e1; border-radius: 3px; }
        .dark ::-webkit-scrollbar-thumb { background: #334155; }
        
        [x-cloak] { display: none !important; }
        
        .sidebar-transition { transition: width 0.3s ease-in-out, transform 0.3s ease-in-out, padding 0.3s; }
        #sidebar.w-0 { overflow: hidden; }

        /* Form Inputs */
        .form-input { @apply w-full rounded-lg border-slate-300 dark:border-slate-700 bg-white dark:bg-slate-800 text-slate-900 dark:text-white focus:ring-brand-500 focus:border-brand-500 transition-colors shadow-sm text-sm h-10; }
        .form-label { @apply block text-xs font-bold text-slate-500 dark:text-slate-400 uppercase tracking-wide mb-1; }
        
        /* Calc Cards */
        .calc-card { @apply bg-white dark:bg-slate-950 p-6 rounded-xl border border-slate-200 dark:border-slate-800 shadow-sm transition-all; }
        .result-row { @apply flex justify-between items-center py-2 border-b border-slate-100 dark:border-slate-800 last:border-0; }
        .result-label { @apply text-sm text-slate-600 dark:text-slate-400; }
        .result-value { @apply font-medium text-slate-900 dark:text-white; }
        
        .toolbar-hidden { transform: translateY(-100%); opacity: 0; pointer-events: none; height: 0; padding: 0; border: none; }
        .toolbar-visible { transform: translateY(0); opacity: 1; pointer-events: auto; height: auto; }
        
        /* Table styles for Report */
        .report-table { @apply w-full text-left text-xs border-collapse; }
        .report-table th { @apply bg-slate-100 dark:bg-slate-800 p-2 font-bold border border-slate-200 dark:border-slate-700; }
        .report-table td { @apply p-2 border border-slate-200 dark:border-slate-700; }
    </style>
    
    <script>
        function taxCalculator() {
            return {
                // Config (Loaded from PHP ideally, here simplified)
                config: {
                    cra: { fixed: 0, percent: 0, minPercent: 0 }, // REMOVED IN NTA 2025
                    taxBands: <?php echo json_encode($tax_config['tax_bands']); ?>
                },
                
                // Inputs
                inputMode: 'manual', // manual, category
                selectedCategoryId: '',
                categories: <?php echo json_encode($categories); ?>,
                
                grossSalary: 250000,
                period: 'Monthly', // Monthly, Annual
                
                // Salary Components (Breakdown)
                structure: {
                    basic: 40,
                    housing: 30,
                    transport: 20,
                    other: 10
                },
                
                // Custom Allowances
                allowances: [], // { name: 'Utility', amount: 0, type: 'fixed'|'percent' }
                newAllowance: { name: '', amount: '', type: 'fixed' },

                // Statutory Settings
                settings: {
                    pension: { enabled: true, rateEmp: 8, rateEmpr: 10, base: 'bht' }, // base: bht, basic, gross
                    nhf: { enabled: true, rate: 2.5 },
                    nhis: { enabled: true, rate: 0 }, // 0 means flat, or use logic if needed. Prompt said check boxes.
                    other: { enabled: false, rate: 0, name: 'Other Stat.' }
                },
                
                // UI State
                showWorkings: false,

                // Push Modal State
                modal: {
                    open: false,
                    employeeId: '',
                    payrollMonth: new Date().toISOString().slice(0, 7), // YYYY-MM
                    mode: 'one-time', // one-time, permanent
                    confirm: false
                },
                
                // Mock Employees
                employees: [
                    { id: 'MIP-001', name: 'John Doe', dept: 'Engineering' },
                    { id: 'MIP-002', name: 'Jane Smith', dept: 'HR' }
                ],

                // Methods
                addAllowance() {
                    if (this.newAllowance.name && this.newAllowance.amount) {
                        this.allowances.push({ ...this.newAllowance, amount: parseFloat(this.newAllowance.amount) });
                        this.newAllowance.name = '';
                        this.newAllowance.amount = '';
                    }
                },
                removeAllowance(index) {
                    this.allowances.splice(index, 1);
                },
                updateFromCategory() {
                    if (this.inputMode === 'category' && this.selectedCategoryId) {
                        const cat = this.categories.find(c => c.id == this.selectedCategoryId);
                        if (cat) {
                            this.grossSalary = parseFloat(cat.base_gross_amount);
                            this.structure.basic = parseFloat(cat.basic_perc);
                            this.structure.housing = parseFloat(cat.housing_perc);
                            this.structure.transport = parseFloat(cat.transport_perc);
                            this.structure.other = parseFloat(cat.other_perc);
                            this.period = 'Annual'; // Categories are annual usually
                        }
                    }
                },
                toggleInputMode() {
                    this.inputMode = this.inputMode === 'manual' ? 'category' : 'manual';
                    if(this.inputMode === 'category') {
                        // Reset or keep? Maybe existing logic. If category selected, apply it.
                        this.updateFromCategory();
                    } else {
                        this.selectedCategoryId = '';
                    }
                },
                
                // COMPUTATION ENGINE (NTA 2025)
                get calc() {
                    const grossInput = parseFloat(this.grossSalary) || 0;
                    const isMonthly = this.period === 'Monthly';
                    const annualGross = isMonthly ? grossInput * 12 : grossInput;
                    
                    // 1. Structure Breakdown (Annual)
                    const basic = (annualGross * this.structure.basic) / 100;
                    const housing = (annualGross * this.structure.housing) / 100;
                    const transport = (annualGross * this.structure.transport) / 100;
                    const otherStructure = (annualGross * this.structure.other) / 100;
                    
                    // Custom Allowances
                    let customAllowancesTotal = 0;
                    this.allowances.forEach(a => {
                        customAllowancesTotal += isMonthly ? (a.amount * 12) : a.amount;
                    });
                    
                    // 2. Pension Calculation
                    let pensionBaseAmount = 0;
                    if (this.settings.pension.base === 'basic') pensionBaseAmount = basic;
                    else if (this.settings.pension.base === 'bht') pensionBaseAmount = basic + housing + transport;
                    else if (this.settings.pension.base === 'gross') pensionBaseAmount = annualGross;
                    
                    const pensionEmp = this.settings.pension.enabled ? (pensionBaseAmount * this.settings.pension.rateEmp) / 100 : 0;
                    const pensionEmpr = this.settings.pension.enabled ? (pensionBaseAmount * this.settings.pension.rateEmpr) / 100 : 0;
                    
                    // 3. Other Statutory
                    const nhf = this.settings.nhf.enabled ? (basic * this.settings.nhf.rate) / 100 : 0;
                    const nhis = this.settings.nhis.enabled ? (basic * this.settings.nhis.rate) / 100 : 0; 
                    
                    // 4. CRA Logic (REMOVED in NTA 2025)
                    const cra = 0; 
                    
                    // 5. Taxable Income (NTA 2025: Gross - Exemptions)
                    const totalTaxExempt = pensionEmp + nhf + nhis + cra; 
                    let taxableIncome = annualGross - totalTaxExempt;
                    
                    // NTA 2025 EXEMPTION RULE: If Taxable Income <= 800,000, Tax is 0.
                    let isExempt = false;
                    if (taxableIncome <= 800000) {
                        isExempt = true; 
                        // Note: We don't necessarily set taxableIncome to 0, it's just tax exempt. 
                        // But for display it's nice to show what was taxable. 
                    }
                    if (taxableIncome < 0) taxableIncome = 0; // Sanity check
                    
                    // 6. PAYE Calculation (NTA 2025 Bands)
                    let tax = 0;
                    let bandDetails = [];
                    
                    if (!isExempt) {
                        let remaining = taxableIncome;
                        
                        this.config.taxBands.forEach(band => {
                            if (remaining > 0) {
                                let span = (band.to === null) ? Number.MAX_VALUE : (band.to - band.from);
                                let t = Math.min(remaining, span);
                                let bandTax = t * (band.rate / 100); 
                                tax += bandTax;
                                remaining -= t;
                                
                                bandDetails.push({
                                    rate: band.rate,
                                    range: band.to ? `₦${band.from.toLocaleString()} - ₦${band.to.toLocaleString()}` : `Above ₦${band.from.toLocaleString()}`,
                                    taxable: t,
                                    tax: bandTax
                                });
                            }
                        });
                    }
                    
                    const totalDeductions = pensionEmp + nhf + nhis + tax;
                    const netPay = annualGross - totalDeductions;
                    
                    return {
                        annual: { gross: annualGross, basic, housing, transport, other: otherStructure, cra, taxable: taxableIncome, tax, pensionEmp, pensionEmpr, nhf, nhis, deductions: totalDeductions, net: netPay },
                        monthly: { 
                            gross: annualGross / 12, 
                            basic: basic / 12,
                            housing: housing / 12,
                            transport: transport / 12,
                            other: otherStructure / 12,
                            
                            tax: tax / 12, 
                            pensionEmp: pensionEmp / 12, 
                            pensionEmpr: pensionEmpr / 12, 
                            nhf: nhf / 12, 
                            nhis: nhis / 12, 
                            deductions: totalDeductions / 12, 
                            net: netPay / 12 
                        },
                        workings: {
                            craOptionA: 0,
                            craOptionB: 0,
                            bandDetails: bandDetails,
                            isExempt: isExempt
                        }
                    };
                },
                
                printReport() {
                    // Reuse the robust report generation logic
                    this.downloadPdf();
                },
                
                downloadPdf() {
                    console.log('Preparing reliable PDF/Print view...');
                    
                    // Validation
                    if (!this.calc.annual.gross || this.calc.annual.gross <= 0) {
                        alert("Please complete tax calculation before generating report.");
                        return;
                    }

                    // Prepare Payload
                    const payload = {
                        meta: {
                            employee_name: this.modal.employeeId ? this.employees.find(e => e.id == this.modal.employeeId)?.name : 'New/Guest Employee',
                            employee_id: this.modal.employeeId || 'TBD',
                            department: this.modal.employeeId ? this.employees.find(e => e.id == this.modal.employeeId)?.dept : 'N/A',
                            period: this.modal.payrollMonth || new Date().toISOString().slice(0,7),
                            ref: 'TAX-' + Date.now().toString().slice(-6)
                        },
                        structure: this.structure,
                        annual: this.calc.annual,
                        monthly: this.calc.monthly,
                        workings: this.calc.workings
                    };
                    
                    // Create invisible form to POST data
                    const form = document.createElement('form');
                    form.method = 'POST';
                    form.target = '_blank'; // Open in new tab
                    form.action = 'print_tax_report.php';
                    
                    const input = document.createElement('input');
                    input.type = 'hidden';
                    input.name = 'report_data';
                    input.value = JSON.stringify(payload);
                    
                    form.appendChild(input);
                    document.body.appendChild(form);
                    form.submit();
                    document.body.removeChild(form);
                },
                
                openPushModal() {
                    this.modal.open = true;
                },
                
                confirmPush() {
                    if(!this.modal.employeeId) return alert('Please select an employee');
                    if(!this.modal.confirm) return alert('Please confirm the action');
                    
                    // Simulate backend push
                    let msg = `SUCCESS: Payroll structure pushed to employee ${this.modal.employeeId}. Audit Log #AX-992 created.`;
                    if(this.calc.workings.isExempt) {
                        msg += "\n\nNOTE: PAYE Exempt status recorded (Taxable Income <= 0).";
                    }
                    alert(msg);
                    this.modal.open = false;
                    this.modal.confirm = false;
                },
                
                init() {
                    setTimeout(() => lucide.createIcons(), 50);
                }
            }
        }
    </script>
</head>
<body class="flex h-screen overflow-hidden bg-slate-50 dark:bg-slate-900 text-slate-900 dark:text-slate-100 transition-colors duration-300" x-data="taxCalculator()">
    <style>

    </style>

        <!-- Sidebar -->
        <?php include '../includes/dashboard_sidebar.php'; ?>
        
        <!-- Notifications & Overlay -->
        <!-- ... identical ... -->

        <!-- MAIN CONTENT -->
        <div class="flex-1 flex flex-col h-full overflow-hidden w-full relative">
            
            <!-- Header -->
            <header class="h-16 bg-white dark:bg-slate-950 border-b border-slate-200 dark:border-slate-800 flex items-center justify-between px-6 shrink-0 z-30">
                <div class="flex items-center gap-4">
                    <button id="mobile-sidebar-toggle" class="md:hidden text-slate-500 hover:text-slate-700 dark:hover:text-slate-300">
                        <i data-lucide="menu" class="w-6 h-6"></i>
                    </button>
                    <h2 class="text-xl font-bold text-slate-800 dark:text-white">PAYE Tax Calculator (NTA 2025)</h2>
                </div>
                <!-- Standard Header Actions -->
                <div class="flex items-center gap-4">
                    <button id="theme-toggle" class="p-2 rounded-full text-slate-500 hover:bg-slate-100 dark:text-slate-400 dark:hover:bg-slate-800 transition-colors">
                        <i data-lucide="moon" class="w-5 h-5 block dark:hidden"></i>
                        <i data-lucide="sun" class="w-5 h-5 hidden dark:block"></i>
                    </button>
                    <button id="notif-toggle" class="p-2 rounded-full text-slate-500 hover:bg-slate-100 dark:text-slate-400 dark:hover:bg-slate-800 transition-colors relative">
                        <i data-lucide="bell" class="w-5 h-5"></i>
                        <span class="absolute top-1.5 right-1.5 w-2 h-2 bg-red-500 rounded-full border border-white dark:border-slate-950"></span>
                    </button>
                     <div class="h-6 w-px bg-slate-200 dark:bg-slate-700 mx-2"></div>
                    <!-- User Avatar -->
                    <div x-data="{ open: false }" class="relative">
                        <button @click="open = !open" class="flex items-center gap-3 cursor-pointer focus:outline-none">
                            <div class="w-9 h-9 rounded-full bg-slate-200 dark:bg-slate-700 border border-slate-300 dark:border-slate-600 flex items-center justify-center overflow-hidden">
                                <i data-lucide="user" class="w-5 h-5 text-slate-500 dark:text-slate-400"></i>
                            </div>
                            <i data-lucide="chevron-down" class="w-4 h-4 text-slate-400 hidden sm:block"></i>
                        </button>
                        <div x-show="open" @click.outside="open = false" class="absolute right-0 mt-2 w-48 bg-white dark:bg-slate-800 rounded-lg shadow-lg border border-slate-100 dark:border-slate-700 py-1 z-50 mr-4" style="display: none;">
                            <div class="px-4 py-2 border-b border-slate-100 dark:border-slate-700">
                                <p class="text-sm font-bold text-slate-900 dark:text-white"><?php echo htmlspecialchars($_SESSION['user_name'] ?? 'User'); ?></p>
                                <p class="text-xs text-slate-500 dark:text-slate-400"><?php echo htmlspecialchars($_SESSION['role'] ?? 'Role'); ?></p>
                            </div>
                            <a href="../auth/logout.php" class="block px-4 py-2 text-sm text-red-600 dark:text-red-400 hover:bg-red-50 dark:hover:bg-red-900/20">Log Out</a>
                        </div>
                    </div>
                </div>
            </header>

            <!-- Collapsed Toolbar -->
            <div id="collapsed-toolbar" class="toolbar-hidden w-full bg-white dark:bg-slate-950 border-b border-slate-200 dark:border-slate-800 flex items-center px-6 shrink-0 shadow-sm z-20">
                <button id="sidebar-expand-btn" class="flex items-center gap-2 p-2 rounded-lg text-slate-500 hover:text-brand-600 dark:text-slate-400 dark:hover:text-white hover:bg-slate-100 dark:hover:bg-slate-800 transition-colors my-2">
                    <i data-lucide="menu" class="w-5 h-5"></i> <span class="text-sm font-medium">Show Menu</span>
                </button>
            </div>

            <!-- Horizontal Nav (Hidden by default) -->
            <div id="horizontal-nav" class="hidden bg-white dark:bg-slate-950 border-b border-slate-200 dark:border-slate-800 px-6 py-2">
                <!-- Dynamic Nav Content -->
            </div>

            <!-- Scrollable Content -->
            <main class="flex-1 overflow-y-auto p-6 lg:p-8 bg-slate-50 dark:bg-slate-900 scroll-smooth">
                
                <div class="grid grid-cols-1 xl:grid-cols-12 gap-8 max-w-7xl mx-auto">
                    
                    <!-- LEFT COLUMN: CONFIGURATION (4 cols) -->
                    <div class="xl:col-span-4 space-y-6">
                        <!-- ... configuration inputs identical ... -->
                         <!-- 1. Gross Income -->
                        <div class="calc-card">
                            <h3 class="font-bold text-slate-900 dark:text-white mb-4 flex items-center gap-2 text-sm uppercase tracking-wide"><i data-lucide="wallet" class="w-4 h-4 text-brand-600"></i> Income & Breakdown</h3>
                            
                            <!-- Input Mode Toggle -->
                            <div class="flex bg-slate-100 dark:bg-slate-900 rounded-lg p-1 mb-4">
                                <button @click="inputMode = 'manual'; selectedCategoryId = ''" :class="inputMode === 'manual' ? 'bg-white dark:bg-slate-800 text-brand-600 shadow-sm' : 'text-slate-500 hover:text-slate-700 dark:hover:text-slate-300'" class="flex-1 py-1.5 text-xs font-bold rounded-md transition-all text-center">Manual Input</button>
                                <button @click="inputMode = 'category'" :class="inputMode === 'category' ? 'bg-white dark:bg-slate-800 text-brand-600 shadow-sm' : 'text-slate-500 hover:text-slate-700 dark:hover:text-slate-300'" class="flex-1 py-1.5 text-xs font-bold rounded-md transition-all text-center">Select Category</button>
                            </div>

                            <div class="space-y-4">
                                <div x-show="inputMode === 'category'">
                                    <label class="form-label">Select Category</label>
                                    <select x-model="selectedCategoryId" @change="updateFromCategory()" class="form-input">
                                        <option value="">-- Select Category --</option>
                                        <template x-for="cat in categories" :key="cat.id">
                                            <option :value="cat.id" x-text="cat.name"></option>
                                        </template>
                                    </select>
                                </div>

                                <div>
                                    <label class="form-label">Gross Salary (₦)</label>
                                    <input type="number" x-model="grossSalary" :readonly="inputMode === 'category'" :class="inputMode === 'category' ? 'bg-slate-50 dark:bg-slate-900 cursor-not-allowed text-slate-500' : ''" class="form-input font-bold text-lg">
                                </div>
                                <div>
                                    <label class="form-label">Frequency</label>
                                    <select x-model="period" class="form-input">
                                        <option value="Monthly">Monthly</option>
                                        <option value="Annual">Annual</option>
                                    </select>
                                </div>
                                <div class="pt-4 border-t border-slate-100 dark:border-slate-800">
                                    <p class="text-xs font-bold text-slate-500 mb-2">Structure Breakdown (%) 
                                        <span x-show="inputMode === 'manual'" class="text-xs text-brand-600 font-normal cursor-pointer float-right hover:underline">Reset</span>
                                        <span x-show="inputMode === 'category'" class="text-[10px] text-amber-600 font-normal float-right flex items-center gap-1"><i data-lucide="lock" class="w-3 h-3"></i> Locked by Category</span>
                                    </p> 
                                    <div class="grid grid-cols-2 gap-3">
                                        <div><label class="text-xs text-slate-500">Basic</label><input type="number" x-model="structure.basic" :readonly="inputMode === 'category'" :class="inputMode === 'category' ? 'bg-slate-50 dark:bg-slate-900 text-slate-500' : ''" class="form-input text-xs h-8"></div>
                                        <div><label class="text-xs text-slate-500">Housing</label><input type="number" x-model="structure.housing" :readonly="inputMode === 'category'" :class="inputMode === 'category' ? 'bg-slate-50 dark:bg-slate-900 text-slate-500' : ''" class="form-input text-xs h-8"></div>
                                        <div><label class="text-xs text-slate-500">Transport</label><input type="number" x-model="structure.transport" :readonly="inputMode === 'category'" :class="inputMode === 'category' ? 'bg-slate-50 dark:bg-slate-900 text-slate-500' : ''" class="form-input text-xs h-8"></div>
                                        <div><label class="text-xs text-slate-500">Other</label><input type="number" x-model="structure.other" :readonly="inputMode === 'category'" :class="inputMode === 'category' ? 'bg-slate-50 dark:bg-slate-900 text-slate-500' : ''" class="form-input text-xs h-8"></div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- 2. Allowances -->
                        <div class="calc-card">
                            <h3 class="font-bold text-slate-900 dark:text-white mb-4 flex items-center gap-2 text-sm uppercase tracking-wide"><i data-lucide="plus-circle" class="w-4 h-4 text-green-600"></i> Additional Allowances</h3>
                            <div class="space-y-3">
                                <div class="flex gap-2">
                                    <input type="text" x-model="newAllowance.name" placeholder="Name" class="form-input text-xs">
                                    <input type="number" x-model="newAllowance.amount" placeholder="Amt" class="form-input text-xs w-20">
                                </div>
                                <button @click="addAllowance()" class="w-full py-2 bg-slate-100 dark:bg-slate-800 text-slate-600 dark:text-slate-300 rounded text-xs font-bold hover:bg-slate-200 dark:hover:bg-slate-700 transition-colors">+ Add Allowance</button>
                                
                                <div class="space-y-2 mt-2">
                                    <template x-for="(allowance, index) in allowances" :key="index">
                                        <div class="flex justify-between items-center text-xs p-2 bg-slate-50 dark:bg-slate-900 rounded border border-slate-100 dark:border-slate-800">
                                            <span x-text="allowance.name" class="font-medium"></span>
                                            <div class="flex items-center gap-2">
                                                <span>₦<span x-text="allowance.amount"></span></span>
                                                <button @click="removeAllowance(index)" class="text-red-400 hover:text-red-600"><i data-lucide="x" class="w-3 h-3"></i></button>
                                            </div>
                                        </div>
                                    </template>
                                </div>
                            </div>
                        </div>

                        <!-- 3. Statutory Config -->
                        <div class="calc-card">
                            <h3 class="font-bold text-slate-900 dark:text-white mb-4 flex items-center gap-2 text-sm uppercase tracking-wide"><i data-lucide="settings-2" class="w-4 h-4 text-amber-600"></i> Statutory Settings</h3>
                            <div class="space-y-4 text-sm">
                                <!-- Pension -->
                                <div class="space-y-2 pb-3 border-b border-slate-100 dark:border-slate-800">
                                    <div class="flex justify-between items-center">
                                        <label class="flex items-center gap-2 font-medium">
                                            <input type="checkbox" x-model="settings.pension.enabled" class="rounded text-brand-600 focus:ring-brand-500"> Pension
                                        </label>
                                        <select x-model="settings.pension.base" class="text-xs border-none bg-slate-100 dark:bg-slate-800 rounded p-1">
                                            <option value="basic">Basic Only</option>
                                            <option value="bht">Basic+Hous+Trans</option>
                                            <option value="gross">Gross</option>
                                        </select>
                                    </div>
                                    <div class="flex gap-2" x-show="settings.pension.enabled">
                                        <div class="flex-1"><label class="text-[10px] text-slate-500">Emp %</label><input type="number" x-model="settings.pension.rateEmp" class="form-input text-xs h-7"></div>
                                        <div class="flex-1"><label class="text-[10px] text-slate-500">Emplr %</label><input type="number" x-model="settings.pension.rateEmpr" class="form-input text-xs h-7"></div>
                                    </div>
                                </div>
                                <!-- NHF -->
                                <div class="flex justify-between items-center">
                                    <label class="flex items-center gap-2 font-medium">
                                        <input type="checkbox" x-model="settings.nhf.enabled" class="rounded text-brand-600 focus:ring-brand-500"> NHF
                                    </label>
                                    <div class="w-16"><input type="number" x-model="settings.nhf.rate" class="form-input text-xs h-7 text-right" placeholder="%"></div>
                                </div>
                                <!-- NHIS -->
                                <div class="flex justify-between items-center">
                                    <label class="flex items-center gap-2 font-medium">
                                        <input type="checkbox" x-model="settings.nhis.enabled" class="rounded text-brand-600 focus:ring-brand-500"> NHIS
                                    </label>
                                    <div class="w-16"><input type="number" x-model="settings.nhis.rate" class="form-input text-xs h-7 text-right" placeholder="%"></div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- RIGHT COLUMN: RESULTS & ACTIONS (8 cols) -->
                    <div class="xl:col-span-8 space-y-6">
                        
                        <!-- High Level Summary -->
                        <div class="bg-gradient-to-r from-brand-900 to-brand-700 rounded-xl p-8 text-white shadow-2xl relative overflow-visible">
                            <div class="absolute top-0 right-0 w-64 h-64 bg-white/10 rounded-full blur-3xl -translate-y-1/2 translate-x-1/4"></div>
                            
                            <div class="grid grid-cols-1 md:grid-cols-3 gap-8 relative z-10 text-center md:text-left">
                                <div class="overflow-hidden">
                                    <p class="text-brand-200 text-xs font-bold uppercase tracking-wider mb-1">Monthly Net Pay</p>
                                    <h2 class="text-2xl lg:text-3xl xl:text-4xl font-extrabold truncate" title="Net Pay">₦ <span x-text="calc.monthly.net.toLocaleString(undefined, {minimumFractionDigits: 2, maximumFractionDigits: 2})"></span></h2>
                                </div>
                                <div class="relative overflow-hidden" x-data="{ tooltipOpen: false }">
                                    <p class="text-brand-200 text-xs font-bold uppercase tracking-wider mb-1 flex items-center justify-center md:justify-start gap-2">
                                        Monthly Tax (PAYE)
                                        <!-- Tooltip Trigger -->
                                        <button @mouseenter="tooltipOpen = true" @mouseleave="tooltipOpen = false" @click="tooltipOpen = !tooltipOpen" class="text-white/70 hover:text-white transition-colors focus:outline-none"><i data-lucide="info" class="w-4 h-4"></i></button>
                                    </p>
                                    <h2 class="text-2xl lg:text-3xl font-bold text-red-100 flex items-center justify-center md:justify-start gap-2 truncate">
                                        <span x-show="!calc.workings.isExempt">₦ <span x-text="calc.monthly.tax.toLocaleString(undefined, {minimumFractionDigits: 2, maximumFractionDigits: 2})"></span></span>
                                        <span x-show="calc.workings.isExempt" class="text-sm bg-white/20 px-2 py-1 rounded">EXEMPT</span>
                                    </h2>

                                    <!-- TOOLTIP CONTENT -->
                                    <div x-show="tooltipOpen" x-transition:enter="transition ease-out duration-200" x-transition:enter-start="opacity-0 translate-y-2" x-transition:enter-end="opacity-100 translate-y-0" x-transition:leave="transition ease-in duration-150" x-transition:leave-start="opacity-100 translate-y-0" x-transition:leave-end="opacity-0 translate-y-2" x-cloak class="absolute top-full left-1/2 md:left-0 -translate-x-1/2 md:translate-x-0 mt-3 w-72 bg-white dark:bg-slate-800 text-slate-800 dark:text-gray-200 p-4 rounded-xl shadow-xl border border-slate-200 dark:border-slate-700 z-50 text-left text-xs leading-relaxed">
                                        <div class="absolute top-0 left-1/2 md:left-6 -translate-x-1/2 -mt-2 w-4 h-4 bg-white dark:bg-slate-800 transform rotate-45 border-t border-l border-slate-200 dark:border-slate-700"></div>
                                        <h4 class="font-bold text-slate-900 dark:text-white mb-2 text-sm border-b border-slate-100 dark:border-slate-700 pb-1" x-text="calc.workings.isExempt ? 'Why PAYE EXEMPTION Applies' : 'Why PAYE Applies'"></h4>
                                        
                                        <!-- Content: Exempt -->
                                        <div x-show="calc.workings.isExempt">
                                            <p class="mb-2">PAYE does not apply because the employee has no taxable income after reliefs.</p>
                                            <p class="mb-2">The statutory deductions (Pension, NHF, NHIS) reduced the taxable income below the threshold.</p>
                                            <p>As a result, PAYE for this period is ₦0, in line with the Nigeria Tax Act (NTA) 2025.</p>
                                        </div>

                                        <!-- Content: Applicable -->
                                        <div x-show="!calc.workings.isExempt">
                                            <p class="mb-2">PAYE applies because the employee still has taxable income after statutory reliefs.</p>
                                            <p class="mb-2">Nigerian tax law does not exempt employees based on salary amount alone. PAYE is charged only when taxable income remains after deducting:</p>
                                            <ul class="list-disc pl-4 mb-2 space-y-1">
                                                <li>Employee pension contribution (where applicable)</li>
                                                <li>National Housing Fund (NHF)</li>
                                                <li>National Health Insurance (NHIS)</li>
                                            </ul>
                                            <p>In this case, the reliefs did not fully offset the employee’s annual income, so PAYE is payable in accordance with the Nigeria Tax Act (NTA) 2025.</p>
                                        </div>
                                        
                                        <a href="#" @click.prevent="showWorkings = true; tooltipOpen = false" class="block mt-3 text-brand-600 font-bold hover:underline">View full tax calculation &rarr;</a>
                                    </div>
                                </div>
                                <div class="overflow-hidden">
                                    <p class="text-brand-200 text-xs font-bold uppercase tracking-wider mb-1">Effective Tax Rate</p>
                                    <h2 class="text-2xl lg:text-3xl font-bold text-amber-100 truncate"><span x-text="((calc.annual.tax / calc.annual.gross) * 100).toFixed(1)"></span>%</h2>
                                </div>
                            </div>
                            <!-- Exemption Badge -->
                            <div x-show="calc.workings.isExempt" class="relative z-10 mt-6 p-2 bg-green-500/20 border border-green-500/30 rounded-lg flex items-center gap-2 text-sm text-green-100">
                                <i data-lucide="shield-check" class="w-4 h-4"></i> 
                                <span class="font-bold">PAYE EXEMPT</span> &mdash; Taxable income is ≤ 0 after reliefs.
                            </div>
                        </div>
                        
                        <!-- Branding Line -->
                        <div class="text-center text-[10px] text-black dark:text-slate-400 font-medium uppercase tracking-widest mt-2">
                            Powered by Miemploya Tax Support Services
                        </div>

                         <!-- DETAILED WORKINGS (A-Z) - NEW SECTION -->
                         <div class="calc-card border-l-4 border-l-brand-600">
                            <button @click="showWorkings = !showWorkings" class="flex items-center justify-between w-full text-left focus:outline-none">
                                <h3 class="font-bold text-slate-900 dark:text-white text-lg flex items-center gap-2"><i data-lucide="book-open" class="w-5 h-5 text-brand-600"></i> PAYE Calculation Workings (A–Z)</h3>
                                <i :class="showWorkings ? 'rotate-180' : ''" data-lucide="chevron-down" class="w-5 h-5 text-slate-400 transition-transform"></i>
                            </button>
                            
                            <div x-show="showWorkings" x-collapse class="mt-6 space-y-8 border-t border-slate-100 dark:border-slate-800 pt-6">
                                <!-- A. Gross Income -->
                                <div>
                                    <h4 class="text-xs font-bold text-slate-500 uppercase mb-2">A. Gross Income Breakdown</h4>
                                    <table class="w-full text-xs text-left border-collapse border border-slate-200 dark:border-slate-700">
                                        <tr class="bg-slate-50 dark:bg-slate-900"><th class="p-2 border border-slate-200 dark:border-slate-700">Component</th><th class="p-2 border border-slate-200 dark:border-slate-700 text-right">Annual (₦)</th></tr>
                                        <tr><td class="p-2 border border-slate-200 dark:border-slate-700">Basic</td><td class="p-2 border border-slate-200 dark:border-slate-700 text-right font-mono" x-text="calc.annual.basic.toLocaleString(undefined, {minimumFractionDigits:2})"></td></tr>
                                        <tr><td class="p-2 border border-slate-200 dark:border-slate-700">Housing</td><td class="p-2 border border-slate-200 dark:border-slate-700 text-right font-mono" x-text="calc.annual.housing.toLocaleString(undefined, {minimumFractionDigits:2})"></td></tr>
                                        <tr><td class="p-2 border border-slate-200 dark:border-slate-700">Transport</td><td class="p-2 border border-slate-200 dark:border-slate-700 text-right font-mono" x-text="calc.annual.transport.toLocaleString(undefined, {minimumFractionDigits:2})"></td></tr>
                                        <tr class="font-bold"><td class="p-2 border border-slate-200 dark:border-slate-700">Total Gross Income</td><td class="p-2 border border-slate-200 dark:border-slate-700 text-right font-mono" x-text="calc.annual.gross.toLocaleString(undefined, {minimumFractionDigits:2})"></td></tr>
                                    </table>
                                </div>

                                <div>
                                    <h4 class="text-xs font-bold text-slate-500 uppercase mb-2">B. Consolidated Relief Allowance (CRA)</h4>
                                    <div class="bg-slate-50 dark:bg-slate-900 p-4 rounded-lg text-sm space-y-2 border border-slate-200 dark:border-slate-700">
                                        <p class="text-slate-500 italic">Consolidated Relief Allowance (CRA) was abolished under the Nigeria Tax Act (NTA) 2025.</p>
                                    </div>
                                </div>

                                <!-- C. Pension -->
                                <div>
                                    <h4 class="text-xs font-bold text-slate-500 uppercase mb-2">C. Pension Deduction</h4>
                                    <table class="w-full text-xs text-left border-collapse border border-slate-200 dark:border-slate-700">
                                        <tr class="bg-slate-50 dark:bg-slate-900"><th class="p-2 border border-slate-200 dark:border-slate-700">Item</th><th class="p-2 border border-slate-200 dark:border-slate-700">Rate</th><th class="p-2 border border-slate-200 dark:border-slate-700 text-right">Annual (₦)</th></tr>
                                        <tr><td class="p-2 border border-slate-200 dark:border-slate-700">Employee Pension</td><td class="p-2 border border-slate-200 dark:border-slate-700" x-text="settings.pension.rateEmp + '%'"></td><td class="p-2 border border-slate-200 dark:border-slate-700 text-right font-mono" x-text="calc.annual.pensionEmp.toLocaleString(undefined, {minimumFractionDigits:2})"></td></tr>
                                    </table>
                                </div>

                                <!-- D. Taxable Income -->
                                <div>
                                    <h4 class="text-xs font-bold text-slate-500 uppercase mb-2">D. Taxable Income Computation</h4>
                                    <div class="bg-brand-50 dark:bg-brand-900/20 p-4 rounded-lg text-sm border border-brand-100 dark:border-brand-800 pointer-events-none">
                                        <p class="flex justify-between mb-1"><span>Annual Gross</span> <span class="font-mono"> ₦<span x-text="calc.annual.gross.toLocaleString(undefined, {minimumFractionDigits:2})"></span></span></p>
                                        <!-- CRA Removed -->
                                        <p class="flex justify-between mb-2 text-red-500"><span>− Employee Pension</span> <span class="font-mono text-red-600"> ₦<span x-text="calc.annual.pensionEmp.toLocaleString(undefined, {minimumFractionDigits:2})"></span></span></p>
                                        <div class="border-t border-brand-200 dark:border-brand-700 pt-2 font-bold flex justify-between">
                                            <span>= Taxable Income</span> 
                                            <span class="font-mono">
                                                <span x-show="calc.workings.isExempt">₦0.00 (EXEMPT)</span>
                                                <span x-show="!calc.workings.isExempt">₦<span x-text="calc.annual.taxable.toLocaleString(undefined, {minimumFractionDigits:2})"></span></span>
                                            </span>
                                        </div>
                                    </div>
                                </div>

                                <!-- E. PAYE Bands -->
                                <div x-show="!calc.workings.isExempt">
                                    <h4 class="text-xs font-bold text-slate-500 uppercase mb-2">E. PAYE Band Application</h4>
                                    <table class="w-full text-xs text-left border-collapse border border-slate-200 dark:border-slate-700">
                                        <tr class="bg-slate-50 dark:bg-slate-900">
                                            <th class="p-2 border border-slate-200 dark:border-slate-700">Range (₦)</th>
                                            <th class="p-2 border border-slate-200 dark:border-slate-700">Rate</th>
                                            <th class="p-2 border border-slate-200 dark:border-slate-700 text-right">Taxable Amt</th>
                                            <th class="p-2 border border-slate-200 dark:border-slate-700 text-right">Tax</th>
                                        </tr>
                                        <template x-for="band in calc.workings.bandDetails">
                                            <tr>
                                                <td class="p-2 border border-slate-200 dark:border-slate-700" x-text="band.range"></td>
                                                <td class="p-2 border border-slate-200 dark:border-slate-700" x-text="band.rate + '%'"></td>
                                                <td class="p-2 border border-slate-200 dark:border-slate-700 text-right font-mono" x-text="band.taxable.toLocaleString(undefined, {minimumFractionDigits:2})"></td>
                                                <td class="p-2 border border-slate-200 dark:border-slate-700 text-right font-mono font-bold" x-text="band.tax.toLocaleString(undefined, {minimumFractionDigits:2})"></td>
                                            </tr>
                                        </template>
                                        <tr class="bg-slate-50 dark:bg-slate-900 font-bold">
                                            <td colspan="3" class="p-2 border border-slate-200 dark:border-slate-700 text-right">TOTAL ANNUAL PAYE</td>
                                            <td class="p-2 border border-slate-200 dark:border-slate-700 text-right" x-text="calc.annual.tax.toLocaleString(undefined, {minimumFractionDigits:2})"></td>
                                        </tr>
                                    </table>
                                </div>

                                <!-- F. Summary -->
                                <div>
                                    <h4 class="text-xs font-bold text-slate-500 uppercase mb-2">F. Final Summary</h4>
                                     <table class="w-full text-xs text-left border-collapse border border-slate-200 dark:border-slate-700">
                                        <tr><td class="p-2 border border-slate-200 dark:border-slate-700">Total Annual PAYE</td><td class="p-2 border border-slate-200 dark:border-slate-700 text-right font-mono font-bold" x-text="calc.annual.tax.toLocaleString(undefined, {minimumFractionDigits:2})"></td></tr>
                                        <tr><td class="p-2 border border-slate-200 dark:border-slate-700">Monthly PAYE</td><td class="p-2 border border-slate-200 dark:border-slate-700 text-right font-mono font-bold" x-text="calc.monthly.tax.toLocaleString(undefined, {minimumFractionDigits:2})"></td></tr>
                                        <tr class="bg-green-50 dark:bg-green-900/10"><td class="p-2 border border-slate-200 dark:border-slate-700 font-bold text-green-700 dark:text-green-400">NET PAY (Monthly)</td><td class="p-2 border border-slate-200 dark:border-slate-700 text-right font-mono font-bold text-green-700 dark:text-green-400" x-text="calc.monthly.net.toLocaleString(undefined, {minimumFractionDigits:2})"></td></tr>
                                    </table>
                                </div>
                            </div>
                         </div>
                        <!-- END OF WORKINGS -->

                        <!-- Result Tabs / Tables (Summary View) -->
                        <div class="calc-card">
                            <!-- ... existing summary table ... -->
                            <div class="flex items-center justify-between mb-6">
                                <h3 class="font-bold text-slate-900 dark:text-white text-lg">Detailed Breakdown</h3>
                                <div class="flex gap-2">
                                    <button @click="downloadPdf()" class="px-3 py-2 bg-brand-50 dark:bg-brand-900/20 hover:bg-brand-100 text-brand-700 dark:text-brand-300 rounded-lg text-xs font-bold flex items-center gap-2 border border-brand-200 dark:border-brand-800"><i data-lucide="download" class="w-4 h-4"></i> Download PDF</button>
                                    <button @click="printReport()" class="px-3 py-2 bg-slate-100 dark:bg-slate-800 hover:bg-slate-200 text-slate-700 dark:text-slate-300 rounded-lg text-xs font-bold flex items-center gap-2"><i data-lucide="printer" class="w-4 h-4"></i> Print</button>
                                </div>
                            </div>

                            <div class="overflow-x-auto">
                                <table class="w-full text-sm text-left">
                                    <thead class="bg-slate-50 dark:bg-slate-900 text-slate-500 uppercase text-xs font-bold">
                                        <tr>
                                            <th class="p-3">Component</th>
                                            <th class="p-3 text-right">Monthly (₦)</th>
                                            <th class="p-3 text-right">Annual (₦)</th>
                                        </tr>
                                    </thead>
                                    <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
                                        <tr class="font-medium text-slate-900 dark:text-white">
                                            <td class="p-3">Gross Income</td>
                                            <td class="p-3 text-right" x-text="calc.monthly.gross.toLocaleString(undefined, {minimumFractionDigits: 2})"></td>
                                            <td class="p-3 text-right" x-text="calc.annual.gross.toLocaleString(undefined, {minimumFractionDigits: 2})"></td>
                                        </tr>
                                        <tr>
                                            <td class="p-3 pl-6 text-slate-500">Basic</td>
                                            <td class="p-3 text-right text-slate-500" x-text="calc.monthly.basic.toLocaleString(undefined, {minimumFractionDigits: 2})"></td>
                                            <td class="p-3 text-right text-slate-500" x-text="calc.annual.basic.toLocaleString(undefined, {minimumFractionDigits: 2})"></td>
                                        </tr>
                                        <!-- ... housing, transport ... -->
                                        <tr>
                                            <td class="p-3 pl-6 text-slate-500">Housing</td>
                                            <td class="p-3 text-right text-slate-500" x-text="calc.monthly.housing.toLocaleString(undefined, {minimumFractionDigits: 2})"></td>
                                            <td class="p-3 text-right text-slate-500" x-text="calc.annual.housing.toLocaleString(undefined, {minimumFractionDigits: 2})"></td>
                                        </tr>
                                         <tr>
                                            <td class="p-3 pl-6 text-slate-500">Transport</td>
                                            <td class="p-3 text-right text-slate-500" x-text="calc.monthly.transport.toLocaleString(undefined, {minimumFractionDigits: 2})"></td>
                                            <td class="p-3 text-right text-slate-500" x-text="calc.annual.transport.toLocaleString(undefined, {minimumFractionDigits: 2})"></td>
                                        </tr>
                                        
                                        <!-- Statutory Section -->
                                        <tr class="bg-slate-50/50 dark:bg-slate-900/50"><td colspan="3" class="p-2 text-xs font-bold text-slate-400 uppercase tracking-wider">Statutory Deductions</td></tr>
                                        
                                        <tr>
                                            <td class="p-3 text-red-600">Pension (Emp)</td>
                                            <td class="p-3 text-right text-red-600" x-text="'-' + calc.monthly.pensionEmp.toLocaleString(undefined, {minimumFractionDigits: 2})"></td>
                                            <td class="p-3 text-right text-red-600" x-text="'-' + calc.annual.pensionEmp.toLocaleString(undefined, {minimumFractionDigits: 2})"></td>
                                        </tr>
                                        <tr>
                                            <td class="p-3 text-red-600">NHF</td>
                                            <td class="p-3 text-right text-red-600" x-text="'-' + calc.monthly.nhf.toLocaleString(undefined, {minimumFractionDigits: 2})"></td>
                                            <td class="p-3 text-right text-red-600" x-text="'-' + calc.annual.nhf.toLocaleString(undefined, {minimumFractionDigits: 2})"></td>
                                        </tr>
                                         <tr>
                                            <td class="p-3 text-red-600">NHIS</td>
                                            <td class="p-3 text-right text-red-600" x-text="'-' + calc.monthly.nhis.toLocaleString(undefined, {minimumFractionDigits: 2})"></td>
                                            <td class="p-3 text-right text-red-600" x-text="'-' + calc.annual.nhis.toLocaleString(undefined, {minimumFractionDigits: 2})"></td>
                                        </tr>
                                        <tr class="font-bold text-brand-700 bg-brand-50 dark:bg-brand-900/20 dark:text-brand-300">
                                            <td class="p-3">PAYE Tax</td>
                                            <td class="p-3 text-right" x-text="'-' + calc.monthly.tax.toLocaleString(undefined, {minimumFractionDigits: 2})"></td>
                                            <td class="p-3 text-right" x-text="'-' + calc.annual.tax.toLocaleString(undefined, {minimumFractionDigits: 2})"></td>
                                        </tr>

                                        <!-- Final -->
                                        <tr class="bg-slate-100 dark:bg-slate-800 font-extrabold text-slate-900 dark:text-white text-base">
                                            <td class="p-4">NET PAY</td>
                                            <td class="p-4 text-right" x-text="calc.monthly.net.toLocaleString(undefined, {minimumFractionDigits: 2})"></td>
                                            <td class="p-4 text-right">-</td>
                                        </tr>
                                    </tbody>
                                </table>
                            </div>
                        </div>

                         <!-- Action Button -->
                        <button @click="openPushModal()" class="w-full py-4 bg-brand-600 hover:bg-brand-700 text-white font-bold rounded-xl shadow-lg transition-colors flex items-center justify-center gap-2">
                             Apply to Employee Payroll <i data-lucide="arrow-right" class="w-5 h-5"></i>
                        </button>
                    </div>
                </div>
            </main>
        </div>
    </div>

    <!-- PUSH TO PAYROLL MODAL (Strict Layout) -->
    <!-- ... Identical to previous step ... -->
    <div x-show="modal.open" x-cloak class="fixed inset-0 z-50 flex items-center justify-center bg-black/60 backdrop-blur-sm p-4 no-print">
        <div @click.outside="modal.open = false" class="bg-white dark:bg-slate-950 rounded-xl shadow-2xl w-full max-w-2xl border border-slate-200 dark:border-slate-800 p-0 overflow-hidden flex flex-col max-h-[90vh]">
            <div class="px-6 py-4 border-b border-slate-200 dark:border-slate-800 bg-slate-50 dark:bg-slate-900 flex justify-between items-center">
                <h3 class="text-lg font-bold text-slate-900 dark:text-white">Apply PAYE Calculation to Employee Payroll</h3>
                <button @click="modal.open = false" class="text-slate-500 hover:text-slate-900"><i data-lucide="x" class="w-5 h-5"></i></button>
            </div>
            <div class="p-6 overflow-y-auto space-y-6">
                <div class="grid grid-cols-2 gap-4">
                    <div><label class="form-label">Employee</label><select x-model="modal.employeeId" class="form-input"><option value="">Select Employee...</option><template x-for="emp in employees" :key="emp.id"><option :value="emp.id" x-text="emp.name + ' (' + emp.id + ')'"></option></template></select></div>
                    <div>
                        <label class="form-label" x-text="modal.mode === 'permanent' ? 'Effective From Month' : 'Payroll Period'"></label>
                        <input type="month" x-model="modal.payrollMonth" class="form-input">
                        <p x-show="modal.mode === 'permanent'" class="text-[10px] text-brand-600 mt-1">Updates calculated here will recur monthly.</p>
                    </div>
                </div>
                <!-- Warning for Exemption -->
                <div x-show="calc.workings.isExempt" class="p-3 bg-amber-50 rounded border border-amber-200 text-amber-800 text-xs flex gap-2">
                    <i data-lucide="alert-circle" class="w-4 h-4"></i>
                    <span>This push will record a <strong>PAYE EXEMPT</strong> status for this period (Taxable Income ≤ 0).</span>
                </div>
                <div class="border rounded-lg overflow-hidden border-slate-200 dark:border-slate-800">
                    <table class="w-full text-sm">
                        <thead class="bg-slate-100 dark:bg-slate-800 text-xs font-bold text-slate-500 uppercase">
                            <tr><th class="p-3 text-left">Item</th><th class="p-3 text-right">Amount (₦)</th></tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
                            <tr><td class="p-2 pl-4">Basic Salary</td><td class="p-2 pr-4 text-right" x-text="calc.monthly.basic.toLocaleString(undefined, {minimumFractionDigits: 2})"></td></tr>
                            <tr><td class="p-2 pl-4">Housing</td><td class="p-2 pr-4 text-right" x-text="calc.monthly.housing.toLocaleString(undefined, {minimumFractionDigits: 2})"></td></tr>
                            <tr><td class="p-2 pl-4">Transport</td><td class="p-2 pr-4 text-right" x-text="calc.monthly.transport.toLocaleString(undefined, {minimumFractionDigits: 2})"></td></tr>
                            <tr class="font-bold bg-slate-50 dark:bg-slate-900"><td class="p-2 pl-4">Gross Pay</td><td class="p-2 pr-4 text-right" x-text="calc.monthly.gross.toLocaleString(undefined, {minimumFractionDigits: 2})"></td></tr>
                            <tr><td class="p-2 pl-4 text-red-600">PAYE</td><td class="p-2 pr-4 text-right text-red-600" x-text="calc.monthly.tax.toLocaleString(undefined, {minimumFractionDigits: 2})"></td></tr>
                            <tr><td class="p-2 pl-4 text-red-600">Pension (Employee)</td><td class="p-2 pr-4 text-right text-red-600" x-text="calc.monthly.pensionEmp.toLocaleString(undefined, {minimumFractionDigits: 2})"></td></tr>
                            <tr class="font-bold bg-green-50 dark:bg-green-900/20 text-green-800 dark:text-green-300"><td class="p-2 pl-4">Net Pay</td><td class="p-2 pr-4 text-right" x-text="calc.monthly.net.toLocaleString(undefined, {minimumFractionDigits: 2})"></td></tr>
                        </tbody>
                    </table>
                </div>
                 <div class="space-y-4">
                 <div class="space-y-4">
                     <div>
                        <label class="form-label">Application Mode</label>
                        <div class="flex flex-col gap-2 mt-2">
                            <label class="flex items-center gap-3 p-3 rounded-lg border cursor-pointer transition-all" :class="modal.mode === 'one-time' ? 'bg-slate-50 border-slate-300 dark:bg-slate-800 dark:border-slate-600' : 'border-transparent'">
                                <input type="radio" value="one-time" x-model="modal.mode" class="text-brand-600"> 
                                <div>
                                    <span class="block text-sm font-bold text-slate-900 dark:text-white">One-Time Override</span>
                                    <span class="block text-xs text-slate-500">Apply only to the selected month.</span>
                                </div>
                            </label>
                            <label class="flex items-center gap-3 p-3 rounded-lg border cursor-pointer transition-all" :class="modal.mode === 'permanent' ? 'bg-brand-50 border-brand-300 dark:bg-brand-900/20 dark:border-brand-700' : 'border-transparent'">
                                <input type="radio" value="permanent" x-model="modal.mode" class="text-brand-600"> 
                                <div>
                                    <span class="block text-sm font-bold text-slate-900 dark:text-white">Recurring (Monthly Auto-Deduction)</span>
                                    <span class="block text-xs text-slate-500">Update employee's structure to use these values permanently.</span>
                                </div>
                            </label>
                        </div>
                     </div>
                     <label class="flex items-center gap-2 p-3 bg-slate-50 dark:bg-slate-900 rounded-lg border border-slate-200 dark:border-slate-800"><input type="checkbox" x-model="modal.confirm" class="w-5 h-5 rounded text-brand-600"><span class="text-sm font-medium">I confirm these values should affect payroll calculations.</span></label>
                 </div>
            </div>
             <div class="p-6 border-t border-slate-200 dark:border-slate-800 bg-slate-50 dark:bg-slate-900 flex justify-end gap-3">
                <button @click="modal.open = false" class="px-4 py-2 border border-slate-300 dark:border-slate-700 rounded-lg font-bold text-slate-600 hover:bg-white transition-colors">Cancel</button>
                <button @click="confirmPush()" :disabled="!modal.confirm || !modal.employeeId" class="px-4 py-2 bg-brand-600 disabled:opacity-50 disabled:cursor-not-allowed text-white rounded-lg font-bold hover:bg-brand-700 transition-colors shadow-sm">Confirm Push</button>
            </div>
        </div>


    <!-- PRINT REPORT CONTAINER (NEW DESIGN) -->

    
    <!-- Script Logic -->
    <script>
        lucide.createIcons();
        const themeBtn = document.getElementById('theme-toggle');
        const html = document.documentElement;
        if (localStorage.theme === 'dark' || (!('theme' in localStorage) && window.matchMedia('(prefers-color-scheme: dark)').matches)) {
            html.classList.add('dark');
        } else {
            html.classList.remove('dark');
        }
        if(themeBtn) {
            themeBtn.addEventListener('click', () => {
                html.classList.toggle('dark');
                localStorage.theme = html.classList.contains('dark') ? 'dark' : 'light';
            });
        }
        const mobileToggle = document.getElementById('mobile-sidebar-toggle');
        const sidebar = document.getElementById('sidebar');
        const collapsedToolbar = document.getElementById('collapsed-toolbar');
        const desktopCollapseBtn = document.getElementById('sidebar-collapse-btn');
        const sidebarExpandBtn = document.getElementById('sidebar-expand-btn');

        if(mobileToggle && sidebar) {
            mobileToggle.addEventListener('click', () => {
                sidebar.classList.toggle('-translate-x-full');
            });
        }

        function toggleSidebar() {
            if(!sidebar) return;
            sidebar.classList.toggle('w-64');
            sidebar.classList.toggle('w-0');
            sidebar.classList.toggle('p-0'); 
            
            if(collapsedToolbar) {
                if(sidebar.classList.contains('w-0')) {
                    collapsedToolbar.classList.remove('toolbar-hidden');
                    collapsedToolbar.classList.add('toolbar-visible');
                } else {
                    collapsedToolbar.classList.add('toolbar-hidden');
                    collapsedToolbar.classList.remove('toolbar-visible');
                }
            }
        }

        if(desktopCollapseBtn) desktopCollapseBtn.addEventListener('click', toggleSidebar);
        if(sidebarExpandBtn) sidebarExpandBtn.addEventListener('click', toggleSidebar);
    </script>
</body>
</html>
