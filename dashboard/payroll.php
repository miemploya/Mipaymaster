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
                deptList: <?php echo json_encode($departments); ?>,
                catList: <?php echo json_encode($categories); ?>,
                
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
                    this.loading = true;
                    this.errorMsg = '';
                    try {
                        const formData = {
                            action: 'initiate',
                            month: this.currentPeriod.month,
                            year: this.currentPeriod.year
                        };
                        
                        const res = await fetch('ajax/payroll_operations.php', {
                            method: 'POST',
                            headers: {'Content-Type': 'application/json'},
                            body: JSON.stringify(formData)
                        });
                        const data = await res.json();
                        
                        if(data.status) {
                            // Success
                            this.changeView('sheet');
                        } else {
                            alert('Error: ' + data.message);
                        }
                    } catch(e) {
                        alert('System Error: ' + e.message);
                    } finally {
                        this.loading = false;
                    }
                },

                async fetchSheet() {
                    this.loading = true;
                    try {
                        const formData = {
                            action: 'fetch_sheet',
                            month: this.currentPeriod.month,
                            year: this.currentPeriod.year
                        };
                        const res = await fetch('ajax/payroll_operations.php', {
                            method: 'POST',
                            headers: {'Content-Type': 'application/json'},
                            body: JSON.stringify(formData)
                        });
                        const data = await res.json();
                        
                        if(data.status && data.run) {
                            this.payrollRun = data.run; // id, status, etc.
                            this.sheetData = data.entries;
                            // Update Totals
                            this.totals = {
                                gross: parseFloat(data.totals.total_gross || 0),
                                deductions: parseFloat(data.totals.total_deductions || 0),
                                net: parseFloat(data.totals.total_net || 0),
                                count: parseInt(data.totals.employee_count || 0)
                            };
                            // Update Anomalies
                            this.anomalies = data.anomalies || [];
                        } else {
                            // No run found
                            this.payrollRun = null;
                            this.sheetData = [];
                            this.totals = { gross: 0, deductions: 0, net: 0, count: 0 };
                            this.anomalies = [];
                        }
                    } catch(e) {
                        console.error(e);
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
                    return new Intl.NumberFormat('en-NG', { style: 'currency', currency: 'NGN' }).format(amount);
                }
            }
        }
    </script>
</head>
<body class="flex h-screen overflow-hidden bg-slate-50 dark:bg-slate-900 text-slate-900 dark:text-slate-100 transition-colors duration-300" x-data="payrollApp()">

    <!-- MANDATORY SIDEBAR STRUCTURE -->
    <?php include '../includes/dashboard_sidebar.php'; ?>

        <!-- NOTIFICATIONS PANEL (SLIDE-OVER) -->
        <div id="notif-panel" class="fixed inset-y-0 right-0 w-80 bg-white dark:bg-slate-950 shadow-2xl transform translate-x-full transition-transform duration-300 z-50 border-l border-slate-200 dark:border-slate-800">
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

            <!-- Main Scrollable Area -->
            <main class="flex-1 overflow-y-auto p-6 lg:p-8 bg-slate-50 dark:bg-slate-900 scroll-smooth">
                
                <!-- SUCCESS TOAST -->
                <div x-show="runSuccess" x-transition class="fixed top-20 right-6 bg-green-600 text-white px-6 py-4 rounded-lg shadow-xl z-50 flex items-center gap-3">
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
                        <button @click="changeView('preview')" class="px-4 py-2 bg-brand-600 text-white rounded-lg hover:bg-brand-700 text-sm font-bold shadow-md">Proceed to Validation</button>
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
                                    <th class="bg-green-50/50 dark:bg-green-900/10 min-w-[100px] text-green-700 dark:text-green-400">Overtime</th>
                                    <th class="bg-slate-200 dark:bg-slate-800 min-w-[120px]">Gross Pay</th>
                                    
                                    <!-- Deductions Group -->
                                    <th class="bg-red-50/50 dark:bg-red-900/10 border-l border-slate-200 dark:border-slate-800 min-w-[100px]">PAYE</th>
                                    <th class="bg-red-50/50 dark:bg-red-900/10 min-w-[100px]">Pension</th>
                                    <th class="bg-red-50/50 dark:bg-red-900/10 min-w-[100px]">NHIS</th>
                                    <th class="bg-red-50/50 dark:bg-red-900/10 min-w-[100px] text-red-700 dark:text-red-400">Loan</th>
                                    
                                    <!-- Summary -->
                                    <th class="bg-brand-50 dark:bg-brand-900/20 border-l border-slate-200 dark:border-slate-800 text-brand-700 dark:text-white min-w-[140px]">Net Pay</th>
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
                                        <td class="bg-green-50/5 dark:bg-transparent text-green-600">0.00</td>
                                        <td class="font-bold bg-slate-50 dark:bg-slate-900" x-text="formatCurrency(emp.gross_salary)"></td>
                                        
                                        <!-- Deductions -->
                                        <td class="bg-red-50/5 dark:bg-transparent" x-text="formatCurrency(emp.breakdown.paye)"></td>
                                        <td class="bg-red-50/5 dark:bg-transparent" x-text="formatCurrency(emp.breakdown.pension)"></td>
                                        <td class="bg-red-50/5 dark:bg-transparent" x-text="formatCurrency(emp.breakdown.nhis)"></td>
                                        <td class="bg-red-50/5 dark:bg-transparent text-red-500" x-text="formatCurrency(emp.breakdown.loan)"></td>
                                        
                                        <!-- Net -->
                                        <td class="font-bold bg-brand-50/30 text-brand-700 dark:text-white border-l border-slate-200 dark:border-slate-800" x-text="formatCurrency(emp.net_pay)"></td>
                                    </tr>
                                </template>
                                <tr x-show="sheetData.length === 0">
                                    <td colspan="11" class="p-8 text-center text-slate-500 italic">No payroll data generated for this period. Run payroll to see entries.</td>
                                </tr>
                            </tbody>
                        </table>
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
