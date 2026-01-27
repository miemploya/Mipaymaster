<?php
// Active State Helper Logic
$filename = basename($_SERVER['PHP_SELF'], '.php');

// Mapping URL filenames to Sidebar IDs
$page_map = [
    'index' => 'dashboard',
    'company' => 'company',
    'employees' => 'employees',
    'attendance' => 'attendance',
    'payroll' => 'payroll',
    'increments' => 'increments',
    'loans' => 'loans',
    'report' => 'reports',
    'audit' => 'audit',
    'users' => 'users',
    'wallet' => 'wallet',
    'billing' => 'billing',
    'tax_calculator' => 'tax_calculator',
    'support' => 'support',
    'settings' => 'settings',
    // HR Sub-pages active state logic
    'hr_recruitment' => 'recruitment',
    'hr_onboarding' => 'onboarding',
    'leaves' => 'leaves',
    'hr_performance' => 'performance',
    'hr_templates' => 'templates',
];


// Fallback to manual $current_page if set, otherwise use map, otherwise default
$current = $current_page ?? ($page_map[$filename] ?? $filename);

// Ensure Logo Data is Available
if ((!isset($company) || !isset($company['logo_url'])) && isset($_SESSION['company_id'])) {
    if (!isset($pdo)) {
         try {
             require_once __DIR__ . '/../config/db.php';
         } catch(Exception $e) {}
    }
    if (isset($pdo)) {
        $stmt_logo = $pdo->prepare("SELECT name, logo_url FROM companies WHERE id = ?");
        $stmt_logo->execute([$_SESSION['company_id']]);
        $c_data = $stmt_logo->fetch(PDO::FETCH_ASSOC);
        if ($c_data) {
            $company['logo_url'] = $c_data['logo_url'];
            if (!isset($company_name)) $company_name = $c_data['name'];
        }
    }
}
$company_logo_url = $company['logo_url'] ?? null;

// Function to check if a page is active
function isActive($page_name, $current_page) {
    return $current_page === $page_name ? 
        'text-brand-600 dark:text-brand-400 bg-brand-50 dark:bg-brand-900/20 border-brand-200 dark:border-brand-800/50 shadow-sm' : 
        'text-slate-600 dark:text-slate-400 hover:bg-slate-50 dark:hover:bg-slate-800 border-transparent hover:border-slate-200 dark:hover:border-slate-700';
}
?>
<style>
    /* Modern Sidebar Scrolling */
    #sidebar nav {
        scroll-behavior: smooth;
        scrollbar-width: thin;
        scrollbar-color: rgba(148, 163, 184, 0.5) transparent;
    }
    #sidebar nav::-webkit-scrollbar {
        width: 4px;
    }
    #sidebar nav::-webkit-scrollbar-track {
        background: transparent;
    }
    #sidebar nav::-webkit-scrollbar-thumb {
        background: rgba(148, 163, 184, 0.3);
        border-radius: 4px;
    }
    #sidebar nav::-webkit-scrollbar-thumb:hover {
        background: rgba(148, 163, 184, 0.6);
    }
    .dark #sidebar nav::-webkit-scrollbar-thumb {
        background: rgba(71, 85, 105, 0.5);
    }
    .dark #sidebar nav::-webkit-scrollbar-thumb:hover {
        background: rgba(71, 85, 105, 0.8);
    }
    /* Hide scrollbar when not hovering */
    #sidebar nav:not(:hover)::-webkit-scrollbar-thumb {
        background: transparent;
    }
    
    /* Smooth Link Transitions */
    #sidebar a, #sidebar button {
        transition: all 0.2s cubic-bezier(0.4, 0, 0.2, 1);
    }
    #sidebar a:active, #sidebar button:active {
        transform: scale(0.98);
    }
    
    /* Section Collapse Animation */
    [x-collapse] {
        overflow: hidden;
    }
</style>
<aside id="sidebar" x-data="{ ready: false }" x-init="setTimeout(() => ready = true, 100)" 
       class="sidebar-transition fixed md:relative z-40 w-64 bg-white dark:bg-slate-950 border-r border-slate-200 dark:border-slate-800 flex flex-col h-[calc(100vh-64px)] md:h-full top-16 md:top-0 -translate-x-full md:translate-x-0 shadow-xl md:shadow-none whitespace-nowrap overflow-x-hidden"
       :class="{ 'opacity-0': !ready, 'opacity-100': ready }">
    
    <!-- Top: Logo (Desktop Only) -->
    <div class="hidden md:flex h-16 items-center px-6 border-b border-slate-100 dark:border-slate-800 min-w-[16rem] shrink-0">
        <a href="index.php" class="flex items-center gap-2">
            <!-- Light Theme Logo -->
            <img src="../assets/images/logo-light.png" alt="Mipaymaster" class="h-10 w-auto object-contain block dark:hidden">
            <!-- Dark Theme Logo -->
            <img src="../assets/images/logo-dark.png" alt="Mipaymaster" class="h-10 w-auto object-contain hidden dark:block">
        </a>
    </div>
    
    <!-- Company Switcher -->
    <!-- Company Display (No Switcher) -->
    <div class="p-4 border-b border-slate-100 dark:border-slate-800 min-w-[16rem] shrink-0">
        <div class="w-full flex items-center gap-2 p-2 rounded-lg bg-slate-50 dark:bg-slate-900 border border-slate-200 dark:border-slate-800">
            <?php if (!empty($company_logo_url) && file_exists(__DIR__ . '/../uploads/logos/' . $company_logo_url)): ?>
                <div class="w-8 h-8 rounded bg-white flex items-center justify-center overflow-hidden border border-slate-200">
                    <img src="../uploads/logos/<?php echo htmlspecialchars($company_logo_url); ?>" alt="Logo" class="w-full h-full object-contain">
                </div>
            <?php else: ?>
                <div class="w-8 h-8 rounded bg-brand-600 flex items-center justify-center text-white font-bold"><?php echo strtoupper(substr($company_name ?? 'C', 0, 1)); ?></div>
            <?php endif; ?>
            
            <div class="text-left">
                <p class="text-xs font-medium text-slate-500 dark:text-slate-400">Company</p>
                <p class="text-sm font-bold text-slate-900 dark:text-white truncate w-32"><?php echo htmlspecialchars($company_name ?? 'Company'); ?></p>
            </div>
        </div>
    </div>

    <!-- Navigation Menu -->
    <nav class="flex-1 overflow-y-auto py-4 px-3 space-y-1 min-w-[16rem] overscroll-contain"
         x-data="{
            hrOpen: false,
            payrollOpen: false,
            financeOpen: false,
            adminOpen: false,
            
            init() {
                // Restore State
                this.hrOpen = this.restore('hrOpen', <?php echo in_array($current, ['recruitment', 'onboarding', 'leaves', 'relations', 'performance', 'templates']) ? 'true' : 'false'; ?>);
                this.payrollOpen = this.restore('payrollOpen', <?php echo in_array($current, ['payroll', 'increments', 'loans', 'attendance']) ? 'true' : 'false'; ?>);
                this.financeOpen = this.restore('financeOpen', <?php echo in_array($current, ['wallet', 'tax_calculator', 'billing']) ? 'true' : 'false'; ?>);
                this.adminOpen = this.restore('adminOpen', <?php echo in_array($current, ['users', 'settings', 'audit', 'support', 'reports']) ? 'true' : 'false'; ?>);

                // Scroll active item into view after a short delay
                this.$nextTick(() => {
                    const activeLink = this.$el.querySelector('a.text-brand-600, a.text-brand-400');
                    if (activeLink) {
                        setTimeout(() => {
                            activeLink.scrollIntoView({ behavior: 'smooth', block: 'center' });
                        }, 200);
                    }
                });

                // Watchers for sections
                this.$watch('hrOpen', val => this.save('hrOpen', val));
                this.$watch('payrollOpen', val => this.save('payrollOpen', val));
                this.$watch('financeOpen', val => this.save('financeOpen', val));
                this.$watch('adminOpen', val => this.save('adminOpen', val));
            },
            restore(key, def) {
                const val = localStorage.getItem('sidebar_' + key);
                return val !== null ? val === 'true' : def;
            },
            save(key, val) {
                localStorage.setItem('sidebar_' + key, val);
            }
         }">

        <!-- 1. CORE -->
         <a href="index.php" class="group flex items-center gap-3 px-3 py-2 text-sm font-medium rounded-lg transition-all duration-200 hover:translate-x-1 border <?php echo isActive('dashboard', $current); ?>">
            <i data-lucide="layout-dashboard" class="w-5 h-5 text-blue-600 dark:text-blue-400 group-hover:scale-110 transition-transform"></i> 
            <span>Dashboard</span>
        </a>
        <a href="company.php" class="group flex items-center justify-between px-3 py-2 text-sm font-medium rounded-lg transition-all duration-200 hover:translate-x-1 border <?php echo isActive('company', $current); ?>">
            <div class="flex items-center gap-3">
                <i data-lucide="building-2" class="w-5 h-5 text-indigo-600 dark:text-indigo-400 group-hover:scale-110 transition-transform"></i> 
                <span>Company Setup</span>
            </div>
            <?php if($current === 'company'): ?><i data-lucide="alert-triangle" class="w-4 h-4 text-amber-500"></i><?php endif; ?>
        </a>
        <a href="employees.php" class="group flex items-center gap-3 px-3 py-2 text-sm font-medium rounded-lg transition-all duration-200 hover:translate-x-1 border <?php echo isActive('employees', $current); ?>">
            <i data-lucide="users" class="w-5 h-5 text-emerald-600 dark:text-emerald-400 group-hover:scale-110 transition-transform"></i> 
            <span>Employees</span>
        </a>

        <!-- 2. PAYROLL & COMPENSATION (Group) -->
        <button @click="payrollOpen = !payrollOpen" class="w-full flex items-center justify-between px-3 py-2 text-sm font-medium rounded-lg transition-colors group border <?php echo in_array($current, ['payroll', 'increments', 'loans', 'attendance']) ? 'text-amber-700 dark:text-amber-400 bg-amber-50 dark:bg-amber-900/20 border-amber-200 dark:border-amber-800/50' : 'text-slate-600 dark:text-slate-400 hover:bg-slate-50 dark:hover:bg-slate-800 border-transparent'; ?>">
            <div class="flex items-center gap-3">
                <i data-lucide="banknote" class="w-5 h-5 text-violet-600 dark:text-violet-400 group-hover:scale-110 transition-transform"></i>
                <span>Payroll Suite</span>
            </div>
            <i data-lucide="chevron-down" class="w-4 h-4 transition-transform duration-200" :class="{'rotate-180': payrollOpen}"></i>
        </button>
        <div x-show="payrollOpen" x-collapse class="pl-4 mt-1 space-y-1">
            <a href="payroll.php" class="group flex items-center gap-3 px-3 py-2 text-sm font-medium rounded-lg transition-all duration-200 hover:translate-x-1 hover:bg-violet-50 dark:hover:bg-violet-900/10 border <?php echo isActive('payroll', $current); ?>">
                <i data-lucide="play-circle" class="w-4 h-4 text-violet-500/70 dark:text-violet-400/70"></i> Run Payroll
            </a>
            <a href="increments.php" class="group flex items-center gap-3 px-3 py-2 text-sm font-medium rounded-lg transition-all duration-200 hover:translate-x-1 hover:bg-violet-50 dark:hover:bg-violet-900/10 border <?php echo isActive('increments', $current); ?>">
                <i data-lucide="trending-up" class="w-4 h-4 text-violet-500/70 dark:text-violet-400/70"></i> Increments
            </a>
            <a href="loans.php" class="group flex items-center gap-3 px-3 py-2 text-sm font-medium rounded-lg transition-all duration-200 hover:translate-x-1 hover:bg-violet-50 dark:hover:bg-violet-900/10 border <?php echo isActive('loans', $current); ?>">
                <i data-lucide="hand-coins" class="w-4 h-4 text-violet-500/70 dark:text-violet-400/70"></i> Loans & Advances
            </a>
            <a href="attendance.php" class="group flex items-center gap-3 px-3 py-2 text-sm font-medium rounded-lg transition-all duration-200 hover:translate-x-1 hover:bg-violet-50 dark:hover:bg-violet-900/10 border <?php echo isActive('attendance', $current); ?>">
                <i data-lucide="clock" class="w-4 h-4 text-violet-500/70 dark:text-violet-400/70"></i> Attendance & Lateness
            </a>
        </div>

        <!-- 3. HR MANAGEMENT (Group) -->
        <button @click="hrOpen = !hrOpen" class="w-full flex items-center justify-between px-3 py-2 text-sm font-medium rounded-lg transition-colors group border <?php echo in_array($current, ['recruitment', 'onboarding', 'leaves', 'relations', 'performance', 'templates']) ? 'text-amber-700 dark:text-amber-400 bg-amber-50 dark:bg-amber-900/20 border-amber-200 dark:border-amber-800/50' : 'text-slate-600 dark:text-slate-400 hover:bg-slate-50 dark:hover:bg-slate-800 border-transparent'; ?>">
            <div class="flex items-center gap-3">
                <i data-lucide="briefcase" class="w-5 h-5 text-rose-600 dark:text-rose-400 group-hover:scale-110 transition-transform"></i>
                <span>Human Resources</span>
            </div>
            <i data-lucide="chevron-down" class="w-4 h-4 transition-transform duration-200" :class="{'rotate-180': hrOpen}"></i>
        </button>
        <div x-show="hrOpen" x-collapse class="pl-4 mt-1 space-y-1">
            <a href="hr_recruitment.php" class="group flex items-center gap-3 px-3 py-2 text-sm font-medium rounded-lg transition-all duration-200 hover:translate-x-1 hover:bg-rose-50 dark:hover:bg-rose-900/10 border <?php echo isActive('recruitment', $current); ?>">
                <i data-lucide="user-plus" class="w-4 h-4 text-rose-500/70 dark:text-rose-400/70"></i> Recruitment
            </a>
            <a href="hr_onboarding.php" class="group flex items-center gap-3 px-3 py-2 text-sm font-medium rounded-lg transition-all duration-200 hover:translate-x-1 hover:bg-rose-50 dark:hover:bg-rose-900/10 border <?php echo isActive('onboarding', $current); ?>">
                <i data-lucide="clipboard-check" class="w-4 h-4 text-rose-500/70 dark:text-rose-400/70"></i> Onboarding
            </a>
            <a href="leaves.php" class="group flex items-center gap-3 px-3 py-2 text-sm font-medium rounded-lg transition-all duration-200 hover:translate-x-1 hover:bg-rose-50 dark:hover:bg-rose-900/10 border <?php echo isActive('leaves', $current); ?>">
                <i data-lucide="calendar-off" class="w-4 h-4 text-rose-500/70 dark:text-rose-400/70"></i> Leave Management
            </a>
             <a href="hr_performance.php" class="group flex items-center gap-3 px-3 py-2 text-sm font-medium rounded-lg transition-all duration-200 hover:translate-x-1 hover:bg-rose-50 dark:hover:bg-rose-900/10 border <?php echo isActive('performance', $current); ?>">
                <i data-lucide="bar-chart-2" class="w-4 h-4 text-rose-500/70 dark:text-rose-400/70"></i> Performance
            </a>
        </div>

        <!-- 4. FINANCE & TOOLS (Group) -->

        <button @click="financeOpen = !financeOpen" class="w-full flex items-center justify-between px-3 py-2 text-sm font-medium rounded-lg transition-colors group border <?php echo in_array($current, ['wallet', 'tax_calculator', 'billing']) ? 'text-amber-700 dark:text-amber-400 bg-amber-50 dark:bg-amber-900/20 border-amber-200 dark:border-amber-800/50' : 'text-slate-600 dark:text-slate-400 hover:bg-slate-50 dark:hover:bg-slate-800 border-transparent'; ?>">
            <div class="flex items-center gap-3">
                <i data-lucide="pie-chart" class="w-5 h-5 text-amber-600 dark:text-amber-400 group-hover:scale-110 transition-transform"></i>
                <span>Finance Tools</span>
            </div>
            <i data-lucide="chevron-down" class="w-4 h-4 transition-transform duration-200" :class="{'rotate-180': financeOpen}"></i>
        </button>
        <div x-show="financeOpen" x-collapse class="pl-4 mt-1 space-y-1">
            <a href="wallet.php" class="group flex items-center gap-3 px-3 py-2 text-sm font-medium rounded-lg transition-all duration-200 hover:translate-x-1 hover:bg-amber-50 dark:hover:bg-amber-900/10 border <?php echo isActive('wallet', $current); ?>">
                <i data-lucide="wallet" class="w-4 h-4 text-amber-500/70 dark:text-amber-400/70"></i> Disbursement
            </a>
            <a href="tax_calculator.php" class="group flex items-center gap-3 px-3 py-2 text-sm font-medium rounded-lg transition-all duration-200 hover:translate-x-1 hover:bg-amber-50 dark:hover:bg-amber-900/10 border <?php echo isActive('tax_calculator', $current); ?>">
                <i data-lucide="calculator" class="w-4 h-4 text-amber-500/70 dark:text-amber-400/70"></i> Tax Calculator
            </a>
            <a href="billing.php" class="group flex items-center gap-3 px-3 py-2 text-sm font-medium rounded-lg transition-all duration-200 hover:translate-x-1 hover:bg-amber-50 dark:hover:bg-amber-900/10 border <?php echo isActive('billing', $current); ?>">
                 <i data-lucide="credit-card" class="w-4 h-4 text-amber-500/70 dark:text-amber-400/70"></i> Subscription
            </a>
        </div>

        <!-- 5. SYSTEM (Group) -->

        <button @click="adminOpen = !adminOpen" class="w-full flex items-center justify-between px-3 py-2 text-sm font-medium rounded-lg transition-colors group border <?php echo in_array($current, ['users', 'settings', 'audit', 'reports', 'support']) ? 'text-amber-700 dark:text-amber-400 bg-amber-50 dark:bg-amber-900/20 border-amber-200 dark:border-amber-800/50' : 'text-slate-600 dark:text-slate-400 hover:bg-slate-50 dark:hover:bg-slate-800 border-transparent'; ?>">
            <div class="flex items-center gap-3">
                <i data-lucide="settings-2" class="w-5 h-5 text-slate-500 dark:text-slate-400 group-hover:scale-110 transition-transform"></i>
                <span>Admin & Support</span>
            </div>
            <i data-lucide="chevron-down" class="w-4 h-4 transition-transform duration-200" :class="{'rotate-180': adminOpen}"></i>
        </button>
         <div x-show="adminOpen" x-collapse class="pl-4 mt-1 space-y-1">
            <a href="users.php" class="group flex items-center gap-3 px-3 py-2 text-sm font-medium rounded-lg transition-all duration-200 hover:translate-x-1 hover:bg-slate-50 dark:hover:bg-slate-800 border <?php echo isActive('users', $current); ?>">
                <i data-lucide="users" class="w-4 h-4 text-slate-400"></i> Users
            </a>
            <a href="settings.php" class="group flex items-center gap-3 px-3 py-2 text-sm font-medium rounded-lg transition-all duration-200 hover:translate-x-1 hover:bg-slate-50 dark:hover:bg-slate-800 border <?php echo isActive('settings', $current); ?>">
                <i data-lucide="settings" class="w-4 h-4 text-slate-400"></i> Configuration
            </a>
            <a href="audit.php" class="group flex items-center gap-3 px-3 py-2 text-sm font-medium rounded-lg transition-all duration-200 hover:translate-x-1 hover:bg-slate-50 dark:hover:bg-slate-800 border <?php echo isActive('audit', $current); ?>">
                <i data-lucide="shield-alert" class="w-4 h-4 text-slate-400"></i> Audit Trail
            </a>
            <a href="report.php" class="group flex items-center gap-3 px-3 py-2 text-sm font-medium rounded-lg transition-all duration-200 hover:translate-x-1 hover:bg-slate-50 dark:hover:bg-slate-800 border <?php echo isActive('reports', $current); ?>">
                <i data-lucide="file-bar-chart" class="w-4 h-4 text-slate-400"></i> Reports
            </a>
            <a href="support.php" class="group flex items-center gap-3 px-3 py-2 text-sm font-medium rounded-lg transition-all duration-200 hover:translate-x-1 hover:bg-slate-50 dark:hover:bg-slate-800 border <?php echo isActive('support', $current); ?>">
                <i data-lucide="headset" class="w-4 h-4 text-slate-400"></i> Support
            </a>
        </div>
    </nav>

    <!-- Collapse Button (Inside Sidebar - Visible when Expanded) -->
    <div class="hidden md:flex py-2 px-4 border-t border-slate-200 dark:border-slate-800 justify-end min-w-[16rem]">
        <button id="sidebar-collapse-btn" class="p-1.5 text-slate-400 hover:text-slate-600 dark:hover:text-slate-200 rounded-lg hover:bg-slate-100 dark:hover:bg-slate-800 transition-colors" title="Collapse Sidebar">
            <i data-lucide="chevrons-left" class="w-5 h-5"></i>
        </button>
    </div>
</aside>
