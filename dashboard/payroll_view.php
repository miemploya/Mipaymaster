<?php
require_once '../includes/functions.php';
require_login();

$company_id = $_SESSION['company_id'];
$run_id = $_GET['id'] ?? 0;

// Fetch Run
$stmt = $pdo->prepare("SELECT * FROM payroll_runs WHERE id = ? AND company_id = ?");
$stmt->execute([$run_id, $company_id]);
$run = $stmt->fetch();

if (!$run) redirect('payroll.php');

// Approve Logic
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['approve_payroll'])) {
    if ($run['status'] === 'draft') {
        $stmt = $pdo->prepare("UPDATE payroll_runs SET status='approved', approved_by=? WHERE id=?");
        $stmt->execute([$_SESSION['user_id'], $run_id]);
        $run['status'] = 'approved';
        set_flash_message('success', 'Payroll run approved successfully.');
    }
}

// Fetch Payslips (Details)
$stmt = $pdo->prepare("SELECT p.*, e.first_name, e.last_name, e.payroll_id 
                       FROM payroll_entries p 
                       JOIN employees e ON p.employee_id = e.id 
                       WHERE p.payroll_run_id = ?");
$stmt->execute([$run_id]);
$items = $stmt->fetchAll();

$current_page = 'payroll';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Payroll Details - MiPayMaster</title>
    <!-- Tailwind CSS -->
    <script src="https://cdn.tailwindcss.com"></script>
    <script>
        tailwind.config = {
            darkMode: 'class',
            theme: {
                extend: {
                    fontFamily: { sans: ['Inter', 'sans-serif'] },
                    colors: {
                        brand: {
                            50: '#eef2ff', 100: '#e0e7ff', 500: '#6366f1', 600: '#4f46e5', 700: '#4338ca', 900: '#312e81',
                        }
                    }
                }
            }
        }
    </script>
    <script src="https://unpkg.com/lucide@latest"></script>
    <script src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js" defer></script>
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap');
        body { font-family: 'Inter', sans-serif; }
        [x-cloak] { display: none !important; }
        ::-webkit-scrollbar { width: 6px; height: 6px; }
        ::-webkit-scrollbar-track { background: transparent; }
        ::-webkit-scrollbar-thumb { background: #cbd5e1; border-radius: 3px; }
        .dark ::-webkit-scrollbar-thumb { background: #334155; }
        .sidebar-transition { transition: margin-left 0.3s ease-in-out, width 0.3s ease-in-out, transform 0.3s ease-in-out; }
        .toolbar-hidden { transform: translateY(-100%); opacity: 0; pointer-events: none; height: 0; padding: 0; border: none; }
        .toolbar-visible { transform: translateY(0); opacity: 1; pointer-events: auto; height: auto; }
    </style>
</head>
<body class="bg-slate-50 dark:bg-slate-900 text-slate-900 dark:text-slate-100 transition-colors duration-300 overflow-hidden">

<div class="flex h-screen w-full">
    <!-- SIDEBAR -->
    <?php include '../includes/dashboard_sidebar.php'; ?>

    <!-- MAIN CONTENT -->
    <div class="flex-1 flex flex-col h-full overflow-hidden w-full relative">
        
        <!-- HEADER -->
        <header class="h-16 bg-white dark:bg-slate-950 border-b border-slate-200 dark:border-slate-800 flex items-center justify-between px-6 shrink-0 z-30">
            <div class="flex items-center gap-4">
                <button id="mobile-sidebar-toggle" class="md:hidden text-slate-500 hover:text-slate-700 dark:hover:text-slate-300">
                    <i data-lucide="menu" class="w-6 h-6"></i>
                </button>
                <div class="flex flex-col">
                    <h2 class="text-xl font-bold text-slate-800 dark:text-white">Payroll: <?php echo date('F Y', mktime(0, 0, 0, $run['period_month'], 10, $run['period_year'])); ?></h2>
                </div>
            </div>
            
            <div class="flex items-center gap-4">
                <!-- Approve Button Logic in Header -->
                <?php if ($run['status'] === 'draft'): ?>
                    <form method="POST" class="inline-block">
                        <button type="submit" name="approve_payroll" class="px-4 py-1.5 bg-brand-600 hover:bg-brand-700 text-white font-bold rounded-lg text-sm shadow-sm transition-colors">
                            Approve
                        </button>
                    </form>
                <?php else: ?>
                    <span class="px-3 py-1 bg-green-100 text-green-700 dark:bg-green-900/30 dark:text-green-400 text-xs font-bold rounded-full border border-green-200 dark:border-green-800">Approved</span>
                <?php endif; ?>

                 <button id="header-expand-btn" class="hidden p-2 text-slate-500 hover:text-brand-600 transition-colors" title="Expand Menu">
                    <i data-lucide="panel-left-open" class="w-5 h-5"></i>
                </button>
                <button id="theme-toggle" class="p-2 rounded-full text-slate-500 hover:bg-slate-100 dark:text-slate-400 dark:hover:bg-slate-800 transition-colors">
                    <i data-lucide="moon" class="w-5 h-5 block dark:hidden"></i>
                    <i data-lucide="sun" class="w-5 h-5 hidden dark:block"></i>
                </button>
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

        <main class="flex-1 overflow-y-auto p-6 lg:p-8">
            <div class="max-w-7xl mx-auto">
                
                <?php display_flash_message(); ?>

                <a href="payroll.php" class="inline-flex items-center gap-2 text-sm text-slate-500 hover:text-brand-600 mb-6 transition-colors font-medium">
                    <i data-lucide="arrow-left" class="w-4 h-4"></i> Back to Payrolls
                </a>

                <?php
                // Calculate Totals on the fly (V2)
                $calc_gross = 0;
                $calc_net = 0;
                $calc_deductions = 0;
                
                foreach($items as $i) {
                    $calc_gross += $i['gross_salary'];
                    $calc_net += $i['net_pay'];
                    $calc_deductions += $i['total_deductions'];
                }
                ?>

                <!-- Summary Cards -->
                <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-8">
                    <!-- Total Gross -->
                    <div class="bg-white dark:bg-slate-950 rounded-xl p-6 border border-slate-200 dark:border-slate-800 shadow-sm">
                        <div class="flex items-center gap-4">
                            <div class="w-12 h-12 rounded-full bg-blue-50 dark:bg-blue-900/20 flex items-center justify-center text-blue-600 dark:text-blue-400">
                                <i data-lucide="wallet" class="w-6 h-6"></i>
                            </div>
                            <div>
                                <p class="text-sm font-medium text-slate-500 dark:text-slate-400">Total Gross Salary</p>
                                <h3 class="text-2xl font-bold text-slate-900 dark:text-white">₦<?php echo number_format($calc_gross, 2); ?></h3>
                            </div>
                        </div>
                    </div>
                    <!-- Total Deductions -->
                    <div class="bg-white dark:bg-slate-900 rounded-xl p-6 border border-slate-200 dark:border-slate-800 shadow-sm">
                         <div class="flex items-center gap-4">
                            <div class="w-12 h-12 rounded-full bg-red-50 dark:bg-red-900/20 flex items-center justify-center text-red-600 dark:text-red-400">
                                <i data-lucide="building" class="w-6 h-6"></i>
                            </div>
                            <div>
                                <p class="text-sm font-medium text-slate-500 dark:text-slate-400">Total Deductions</p>
                                <h3 class="text-2xl font-bold text-slate-900 dark:text-white">₦<?php echo number_format($calc_deductions, 2); ?></h3>
                            </div>
                        </div>
                    </div>
                    <!-- Total Net -->
                    <div class="bg-white dark:bg-slate-950 rounded-xl p-6 border border-slate-200 dark:border-slate-800 shadow-sm relative overflow-hidden">
                        <div class="absolute right-0 top-0 h-full w-1 bg-green-500"></div>
                        <div class="flex items-center gap-4">
                            <div class="w-12 h-12 rounded-full bg-green-50 dark:bg-green-900/20 flex items-center justify-center text-green-600 dark:text-green-400">
                                <i data-lucide="check-circle" class="w-6 h-6"></i>
                            </div>
                            <div>
                                <p class="text-sm font-medium text-slate-500 dark:text-slate-400">Total Net Payout</p>
                                <h3 class="text-2xl font-bold text-slate-900 dark:text-white">₦<?php echo number_format($calc_net, 2); ?></h3>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Detailed Table -->
                <div class="bg-white dark:bg-slate-950 rounded-xl border border-slate-200 dark:border-slate-800 overflow-hidden shadow-sm">
                    <div class="overflow-x-auto">
                        <table class="w-full text-left text-sm whitespace-nowrap">
                            <thead class="bg-slate-50 dark:bg-slate-900 border-b border-slate-200 dark:border-slate-800 text-slate-500 dark:text-slate-400">
                                <tr>
                                    <th class="px-6 py-4 font-medium">Employee</th>
                                    <th class="px-6 py-4 font-medium text-right">Gross Salary</th>
                                    <th class="px-6 py-4 font-medium text-right">Allowances</th>
                                    <th class="px-6 py-4 font-medium text-right">Deductions</th>
                                    <th class="px-6 py-4 font-medium text-right">Net Pay</th>
                                    <th class="px-6 py-4 font-medium text-right">Action</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
                                <?php foreach ($items as $item): ?>
                                <tr class="hover:bg-slate-50 dark:hover:bg-slate-900/50 transition-colors">
                                    <td class="px-6 py-4 font-semibold text-slate-900 dark:text-white">
                                        <?php echo htmlspecialchars($item['first_name'] . ' ' . $item['last_name']); ?>
                                        <span class="block text-xs font-normal text-slate-500"><?php echo $item['payroll_id']; ?></span>
                                    </td>
                                    <td class="px-6 py-4 text-right font-medium text-slate-700 dark:text-slate-200">
                                        <?php echo number_format($item['gross_salary'], 2); ?>
                                    </td>
                                    <td class="px-6 py-4 text-right text-slate-600 dark:text-slate-300">
                                        <?php echo number_format($item['total_allowances'], 2); ?>
                                    </td>
                                    <td class="px-6 py-4 text-right text-red-600 dark:text-red-400">
                                        <?php echo number_format($item['total_deductions'], 2); ?>
                                    </td>
                                    <td class="px-6 py-4 text-right font-bold text-green-700 dark:text-green-400">
                                        <?php echo number_format($item['net_pay'], 2); ?>
                                    </td>
                                    <td class="px-6 py-4 text-right">
                                        <a href="payslip.php?id=<?php echo $item['id']; ?>" target="_blank" class="px-3 py-1.5 text-xs font-medium text-brand-600 bg-brand-50 hover:bg-brand-100 dark:bg-brand-900/20 dark:hover:bg-brand-900/30 dark:text-brand-400 rounded-lg transition-colors">
                                            Payslip
                                        </a>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>

            </div>
        </main>
    </div>
</div>

<script>
    lucide.createIcons();

    // Theme Toggle
    const themeBtn = document.getElementById('theme-toggle');
    const html = document.documentElement;
    if (localStorage.theme === 'dark' || (!('theme' in localStorage) && window.matchMedia('(prefers-color-scheme: dark)').matches)) {
        html.classList.add('dark');
    } else {
        html.classList.remove('dark');
    }
    themeBtn.addEventListener('click', () => {
        html.classList.toggle('dark');
        localStorage.theme = html.classList.contains('dark') ? 'dark' : 'light';
    });

    // Sidebar Logic
    const mobileToggle = document.getElementById('mobile-sidebar-toggle');
    const sidebar = document.getElementById('sidebar');
    const desktopCollapseBtn = document.getElementById('sidebar-collapse-btn');
    const sidebarExpandBtn = document.getElementById('sidebar-expand-btn');
    const collapsedToolbar = document.getElementById('collapsed-toolbar');
    const headerExpandBtn = document.getElementById('header-expand-btn');

    mobileToggle.addEventListener('click', () => {
        sidebar.classList.toggle('-translate-x-full');
    });

    function toggleSidebar() {
        sidebar.classList.toggle('w-64');
        sidebar.classList.toggle('w-0');
        sidebar.classList.toggle('p-0'); 
        
        if (sidebar.classList.contains('w-0')) {
            collapsedToolbar.classList.remove('toolbar-hidden');
            collapsedToolbar.classList.add('toolbar-visible');
            if(headerExpandBtn) headerExpandBtn.classList.remove('hidden');
        } else {
            collapsedToolbar.classList.add('toolbar-hidden');
            collapsedToolbar.classList.remove('toolbar-visible');
            if(headerExpandBtn) headerExpandBtn.classList.add('hidden');
        }
    }

    if(desktopCollapseBtn) desktopCollapseBtn.addEventListener('click', toggleSidebar);
    if(sidebarExpandBtn) sidebarExpandBtn.addEventListener('click', toggleSidebar);
    if(headerExpandBtn) headerExpandBtn.addEventListener('click', toggleSidebar);
</script>

</body>
</html>
