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
    'report' => 'reports',
    'audit' => 'audit',
    'users' => 'users',
    'wallet' => 'wallet',
    'billing' => 'billing',
    'tax_calculator' => 'tax_calculator',
    'support' => 'support',
    // HR Sub-pages active state logic
    'hr_recruitment' => 'recruitment',
    'hr_onboarding' => 'onboarding', 
    // ... add others if they exist or map to generic 'hr'
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
        'text-amber-700 dark:text-amber-400 bg-amber-50 dark:bg-amber-900/20 hover:bg-amber-100 dark:hover:bg-amber-900/30 border-amber-200 dark:border-amber-800/50' : 
        'text-slate-600 dark:text-slate-400 hover:bg-slate-50 dark:hover:bg-slate-800 border-transparent';
}
?>
<aside id="sidebar" class="sidebar-transition fixed md:relative z-40 w-64 bg-white dark:bg-slate-950 border-r border-slate-200 dark:border-slate-800 flex flex-col h-[calc(100vh-64px)] md:h-full top-16 md:top-0 -translate-x-full md:translate-x-0 shadow-xl md:shadow-none whitespace-nowrap overflow-x-hidden group">
    
    <!-- Top: Logo (Desktop Only) -->
    <div class="hidden md:flex h-16 items-center px-6 border-b border-slate-100 dark:border-slate-800 min-w-[16rem]">
        <a href="index.php" class="flex items-center gap-2">
            <!-- Light Theme Logo -->
            <img src="../assets/images/logo-light.png" alt="Mipaymaster" class="h-10 w-auto object-contain block dark:hidden">
            <!-- Dark Theme Logo -->
            <img src="../assets/images/logo-dark.png" alt="Mipaymaster" class="h-10 w-auto object-contain hidden dark:block">
        </a>
    </div>
    
    <!-- Company Switcher -->
    <!-- Company Switcher -->
    <!-- Company Display (No Switcher) -->
    <div class="p-4 border-b border-slate-100 dark:border-slate-800 min-w-[16rem]">
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
    <nav class="flex-1 overflow-y-auto py-4 px-3 space-y-1 min-w-[16rem]">
        
        <!-- 1. Dashboard -->
         <a href="index.php" class="flex items-center gap-3 px-3 py-2 text-sm font-medium rounded-lg transition-colors border mb-4 <?php echo isActive('dashboard', $current); ?>">
            <i data-lucide="layout-dashboard" class="w-5 h-5"></i> Dashboard
        </a>

        <!-- 2. Company & Employer Setup -->
        <a href="company.php" class="flex items-center justify-between px-3 py-2 text-sm font-medium rounded-lg transition-colors border <?php echo isActive('company', $current); ?>">
            <div class="flex items-center gap-3">
                <i data-lucide="building-2" class="w-5 h-5"></i> Company Setup
            </div>
            <?php if($current === 'company'): ?>
            <i data-lucide="alert-triangle" class="w-4 h-4 text-amber-500"></i>
            <?php endif; ?>
        </a>

        <!-- 3. Employees Management -->
        <a href="employees.php" class="flex items-center gap-3 px-3 py-2 text-sm font-medium rounded-lg transition-colors border <?php echo isActive('employees', $current); ?>">
            <i data-lucide="users" class="w-5 h-5"></i> Employees Management
        </a>

        <!-- 4. Attendance & Records -->
        <a href="attendance.php" class="flex items-center gap-3 px-3 py-2 text-sm font-medium rounded-lg transition-colors border <?php echo isActive('attendance', $current); ?>">
            <i data-lucide="calendar-check" class="w-5 h-5"></i> Attendance & Records
        </a>

        <!-- 5. Payroll -->
        <a href="payroll.php" class="flex items-center gap-3 px-3 py-2 text-sm font-medium rounded-lg transition-colors border <?php echo isActive('payroll', $current); ?>">
            <i data-lucide="banknote" class="w-5 h-5"></i> Payroll
        </a>

        <!-- 5b. Loans -->
        <a href="loans.php" class="flex items-center gap-3 px-3 py-2 text-sm font-medium rounded-lg transition-colors border <?php echo isActive('loans', $current); ?>">
            <i data-lucide="hand-coins" class="w-5 h-5"></i> Loans & Advances
        </a>

        <!-- 5c. Increments -->
        <a href="increments.php" class="flex items-center gap-3 px-3 py-2 text-sm font-medium rounded-lg transition-colors border <?php echo isActive('increments', $current); ?>">
            <i data-lucide="trending-up" class="w-5 h-5"></i> Increments
        </a>

        <!-- 6. HR Management (Expandable) -->
        <!-- 6. HR Management -->
        <a href="hr_recruitment.php" class="flex items-center gap-3 px-3 py-2 text-sm font-medium rounded-lg transition-colors border <?php echo in_array($current, ['recruitment', 'onboarding', 'relations', 'performance', 'templates']) ? 'text-amber-700 dark:text-amber-400 bg-amber-50 dark:bg-amber-900/20 hover:bg-amber-100 dark:hover:bg-amber-900/30 border-amber-200 dark:border-amber-800/50' : 'text-slate-600 dark:text-slate-400 hover:bg-slate-50 dark:hover:bg-slate-800 border-transparent'; ?>">
            <i data-lucide="briefcase" class="w-5 h-5"></i> HR Management
        </a>

        <!-- 7. Reports -->
        <a href="report.php" class="flex items-center gap-3 px-3 py-2 text-sm font-medium rounded-lg transition-colors border <?php echo isActive('reports', $current); ?>">
            <i data-lucide="file-bar-chart" class="w-5 h-5"></i> Reports
        </a>



        <!-- 8. Disbursement & Wallet -->
        <a href="wallet.php" class="flex items-center gap-3 px-3 py-2 text-sm font-medium rounded-lg transition-colors border <?php echo isActive('wallet', $current); ?>">
            <i data-lucide="wallet" class="w-5 h-5"></i> Disbursement & Wallet
        </a>

        <!-- 9. Subscription & Billing -->
        <a href="billing.php" class="flex items-center gap-3 px-3 py-2 text-sm font-medium rounded-lg transition-colors border <?php echo isActive('billing', $current); ?>">
             <i data-lucide="credit-card" class="w-5 h-5"></i> Subscription & Billing
        </a>

        <!-- 8. Tax Calculator -->
        <a href="tax_calculator.php" class="flex items-center gap-3 px-3 py-2 text-sm font-medium rounded-lg transition-colors border <?php echo isActive('tax_calculator', $current); ?>">
            <i data-lucide="calculator" class="w-5 h-5"></i> Tax Calculator
        </a>
        
        <!-- 9. Miemploya Support -->
        <a href="support.php" class="flex items-center gap-3 px-3 py-2 text-sm font-medium rounded-lg transition-colors border <?php echo isActive('support', $current); ?>">
            <i data-lucide="headset" class="w-5 h-5"></i> Miemploya Support
        </a>

        <!-- 10. System Administration (Expandable) -->
        <div x-data="{ open: <?php echo in_array($current, ['users', 'settings', 'audit']) ? 'true' : 'false'; ?> }">
            <button @click="open = !open" class="w-full flex items-center justify-between px-3 py-2 text-sm font-medium rounded-lg transition-colors border <?php echo in_array($current, ['users', 'settings', 'audit']) ? 'text-amber-700 dark:text-amber-400 bg-amber-50 dark:bg-amber-900/20 border-amber-200 dark:border-amber-800/50' : 'text-slate-600 dark:text-slate-400 hover:bg-slate-50 dark:hover:bg-slate-800 border-transparent'; ?>">
                <div class="flex items-center gap-3">
                    <i data-lucide="shield-check" class="w-5 h-5"></i>
                    <span>System Admin</span>
                </div>
                <i data-lucide="chevron-down" class="w-4 h-4 transition-transform duration-200" :class="{'rotate-180': open}"></i>
            </button>
            <div x-show="open" x-collapse class="pl-4 mt-1 space-y-1">
                <a href="users.php" class="flex items-center gap-3 px-3 py-2 text-sm font-medium rounded-lg transition-colors border <?php echo isActive('users', $current); ?>">
                    <i data-lucide="users" class="w-4 h-4"></i> Users
                </a>
                <a href="settings.php" class="flex items-center gap-3 px-3 py-2 text-sm font-medium rounded-lg transition-colors border <?php echo isActive('settings', $current); ?>">
                    <i data-lucide="settings" class="w-4 h-4"></i> Settings
                </a>
                <a href="audit.php" class="flex items-center gap-3 px-3 py-2 text-sm font-medium rounded-lg transition-colors border <?php echo isActive('audit', $current); ?>">
                    <i data-lucide="shield-alert" class="w-4 h-4"></i> Audit Trail
                </a>
            </div>
        </div>

        <!-- User Profile (Bottom) with Dynamic Photo -->
        <div class="p-4 border-t border-slate-200 dark:border-slate-800 mt-auto">
            <div class="flex items-center gap-3">
                <div class="w-10 h-10 rounded-full bg-slate-200 dark:bg-slate-700 overflow-hidden flex items-center justify-center border border-slate-300 dark:border-slate-600">
                    <?php if (!empty($_SESSION['user_photo'])): ?>
                        <img src="../uploads/avatars/<?php echo htmlspecialchars($_SESSION['user_photo']); ?>" alt="User" class="w-full h-full object-cover">
                    <?php else: ?>
                        <i data-lucide="user" class="w-5 h-5 text-slate-500 dark:text-slate-400"></i>
                    <?php endif; ?>
                </div>
                <div class="flex-1 min-w-0 hidden md:block">
                    <p class="text-sm font-bold text-slate-900 dark:text-white truncate"><?php echo htmlspecialchars($_SESSION['user_name'] ?? 'User'); ?></p>
                    <p class="text-xs text-slate-500 dark:text-slate-400 truncate"><?php echo htmlspecialchars($_SESSION['role'] ?? 'Role'); ?></p>
                </div>
            </div>
        </div>

    </nav>

    <!-- Collapse Button (Inside Sidebar - Visible when Expanded) -->
    <div class="hidden md:flex p-4 border-t border-slate-200 dark:border-slate-800 justify-end min-w-[16rem]">
        <button id="sidebar-collapse-btn" class="p-2 text-slate-400 hover:text-slate-600 dark:hover:text-slate-200 rounded-lg hover:bg-slate-100 dark:hover:bg-slate-800 transition-colors" title="Collapse Sidebar">
            <i data-lucide="chevrons-left" class="w-5 h-5"></i>
        </button>
    </div>
</aside>
