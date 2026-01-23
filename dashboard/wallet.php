<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

require_once '../includes/functions.php';
require_login();

$current_page = 'wallet';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Disbursement & Wallet - Mipaymaster</title>
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
        
        ::-webkit-scrollbar { width: 6px; height: 6px; }
        ::-webkit-scrollbar-track { background: transparent; }
        ::-webkit-scrollbar-thumb { background: #cbd5e1; border-radius: 3px; }
        .dark ::-webkit-scrollbar-thumb { background: #334155; }
        
        [x-cloak] { display: none !important; }
        
        .sidebar-transition { transition: width 0.3s ease-in-out, transform 0.3s ease-in-out, padding 0.3s; }
        #sidebar.w-0 { overflow: hidden; }

        /* Toggle Switch */
        .toggle-checkbox:checked { right: 0; border-color: #68D391; }
        .toggle-checkbox:checked + .toggle-label { background-color: #68D391; }
        
        .toolbar-hidden { transform: translateY(-100%); opacity: 0; pointer-events: none; height: 0; padding: 0; border: none; }
        .toolbar-visible { transform: translateY(0); opacity: 1; pointer-events: auto; height: auto; }
    </style>
    
    <script>
        function disbursementApp() {
            return {
                sidebarOpen: false,
                disbursementEnabled: false,
                walletBalance: 2500000.00,
                
                // Mock History Data
                history: [
                    { date: 'Jan 25, 2026', period: 'Jan 2026', amount: 1250000, method: 'Bank Transfer', status: 'Pending', ref: 'TRX-8839' },
                    { date: 'Dec 24, 2025', period: 'Dec 2025', amount: 1180000, method: 'Bank Transfer', status: 'Successful', ref: 'TRX-7742' },
                    { date: 'Nov 25, 2025', period: 'Nov 2025', amount: 1180000, method: 'Manual Upload', status: 'Successful', ref: 'TRX-6621' },
                ],

                toggleDisbursement() {
                    // In a real app, this would trigger a confirmation modal or API call
                    console.log('Disbursement state toggled:', this.disbursementEnabled);
                }
            }
        }
    </script>
</head>
<body class="bg-slate-50 dark:bg-slate-900 text-slate-900 dark:text-slate-100 transition-colors duration-300 overflow-hidden flex h-screen" x-data="disbursementApp()">

    <!-- Wrapper removed -->


        <!-- Sidebar Include -->
        <?php include '../includes/dashboard_sidebar.php'; ?>
        
        <!-- NOTIFICATIONS PANEL -->
        <div id="notif-panel" class="fixed inset-y-0 right-0 w-80 bg-white dark:bg-slate-950 shadow-2xl transform translate-x-full transition-transform duration-300 z-50 border-l border-slate-200 dark:border-slate-800">
             <div class="h-16 flex items-center justify-between px-6 border-b border-slate-100 dark:border-slate-800">
                <h3 class="text-lg font-bold text-slate-900 dark:text-white">Notifications</h3>
                <button id="notif-close" class="text-slate-500 hover:text-slate-900 dark:hover:text-white"><i data-lucide="x" class="w-5 h-5"></i></button>
            </div>
            <div class="p-4 space-y-4 overflow-y-auto h-[calc(100vh-64px)]">
                <p class="text-sm text-slate-500 p-4 text-center">No new notifications.</p>
            </div>
        </div>
        
        <div id="overlay" class="fixed inset-0 bg-black/50 z-30 hidden"></div>

        <!-- MAIN CONTENT -->
        <div class="flex-1 flex flex-col h-full overflow-hidden w-full relative">
            
            <!-- Header -->
            <header class="h-16 bg-white dark:bg-slate-950 border-b border-slate-200 dark:border-slate-800 flex items-center justify-between px-6 shrink-0 z-30">
                <div class="flex items-center gap-4">
                    <button id="mobile-sidebar-toggle" class="md:hidden text-slate-500 hover:text-slate-700 dark:hover:text-slate-300">
                        <i data-lucide="menu" class="w-6 h-6"></i>
                    </button>
                    <div>
                        <h2 class="text-xl font-bold text-slate-800 dark:text-white">Disbursement & Wallet</h2>
                    </div>
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

            <!-- Horizontal Navigation -->
            <div id="horizontal-nav" class="hidden bg-white dark:bg-slate-950 border-b border-slate-200 dark:border-slate-800 px-6 py-2">
                <!-- Dynamic Nav Content -->
            </div>


            <!-- Collapsed Toolbar -->
            <div id="collapsed-toolbar" class="toolbar-hidden w-full bg-white dark:bg-slate-950 border-b border-slate-200 dark:border-slate-800 flex items-center px-6 shrink-0 shadow-sm z-20">
                <button id="sidebar-expand-btn" class="flex items-center gap-2 p-2 rounded-lg text-slate-500 hover:text-brand-600 dark:text-slate-400 dark:hover:text-white hover:bg-slate-100 dark:hover:bg-slate-800 transition-colors my-2">
                    <i data-lucide="menu" class="w-5 h-5"></i> <span class="text-sm font-medium">Show Menu</span>
                </button>
            </div>

            <!-- Main Layout -->
            <main class="flex-1 overflow-y-auto p-6 lg:p-8 relative scroll-smooth bg-slate-50 dark:bg-slate-900">
                
                <div class="max-w-6xl mx-auto space-y-8">

                    <!-- SECTION A: WALLET OVERVIEW (TOP PRIORITY) -->
                    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
                        <!-- Balance Card -->
                        <div class="lg:col-span-2 bg-gradient-to-br from-slate-900 to-slate-800 dark:from-black dark:to-slate-900 rounded-xl p-8 text-white relative overflow-hidden shadow-xl">
                            <div class="absolute top-0 right-0 w-64 h-64 bg-white/5 rounded-full blur-3xl -translate-y-1/2 translate-x-1/4"></div>
                            
                            <div class="relative z-10 flex flex-col justify-between h-full">
                                <div class="flex justify-between items-start mb-6">
                                    <div>
                                        <p class="text-slate-400 text-xs font-bold uppercase tracking-wider mb-1 flex items-center gap-1">
                                            Wallet Balance <i data-lucide="info" class="w-3 h-3 cursor-help" title="Used only when direct disbursement is enabled"></i>
                                        </p>
                                        <h2 class="text-4xl font-bold font-mono">₦ <span x-text="walletBalance.toLocaleString(undefined, {minimumFractionDigits: 2})"></span></h2>
                                    </div>
                                    <span class="px-2.5 py-0.5 rounded-full text-xs font-bold uppercase tracking-wider inline-flex items-center gap-1 bg-white/10 text-white border border-white/20">Active</span>
                                </div>
                                
                                <div class="flex flex-col sm:flex-row gap-4 pt-6 border-t border-white/10">
                                    <p class="text-xs text-slate-400 flex items-center gap-2 mr-auto">
                                        <i data-lucide="clock" class="w-3 h-3"></i> Last Activity: Today, 09:42 AM
                                    </p>
                                    
                                    <!-- SECTION E: WALLET ACTIONS (Secondary Buttons) -->
                                    <div class="flex gap-3">
                                        <button class="px-4 py-2 bg-white/10 hover:bg-white/20 text-white rounded-lg text-xs font-bold transition-colors flex items-center gap-2">
                                            <i data-lucide="plus" class="w-3 h-3"></i> Fund Wallet
                                        </button>
                                        <button class="px-4 py-2 bg-white/10 hover:bg-white/20 text-white rounded-lg text-xs font-bold transition-colors flex items-center gap-2">
                                            <i data-lucide="list" class="w-3 h-3"></i> Transactions
                                        </button>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- SECTION B: DISBURSEMENT CONTROL (CRITICAL) -->
                        <div class="bg-white dark:bg-slate-950 rounded-xl border border-slate-200 dark:border-slate-800 shadow-sm p-6 border-l-4 border-l-brand-600 flex flex-col justify-center">
                            <h3 class="text-sm font-bold text-slate-900 dark:text-white mb-2">Disbursement Permission</h3>
                            <p class="text-xs text-slate-500 mb-6">Authorize MiPayMaster to initiate salary payments directly from your wallet.</p>
                            
                            <div class="flex items-center justify-between p-4 bg-slate-50 dark:bg-slate-900 rounded-lg border border-slate-200 dark:border-slate-700 mb-4">
                                <span class="text-sm font-bold" :class="disbursementEnabled ? 'text-green-600' : 'text-slate-600 dark:text-slate-400'" x-text="disbursementEnabled ? 'Enabled' : 'Disabled'">Disabled</span>
                                
                                <div class="relative inline-block w-12 align-middle select-none">
                                    <input type="checkbox" x-model="disbursementEnabled" @change="toggleDisbursement" class="toggle-checkbox absolute block w-6 h-6 rounded-full bg-white border-4 appearance-none cursor-pointer"/>
                                    <label class="toggle-label block overflow-hidden h-6 rounded-full bg-slate-300 cursor-pointer"></label>
                                </div>
                            </div>
                            
                            <div x-show="disbursementEnabled" x-transition class="p-3 bg-amber-50 dark:bg-amber-900/10 border border-amber-100 dark:border-amber-900/20 rounded-lg flex items-start gap-2">
                                <i data-lucide="alert-triangle" class="w-4 h-4 text-amber-600 shrink-0 mt-0.5"></i>
                                <p class="text-[10px] text-amber-800 dark:text-amber-300 leading-tight">
                                    Warning: Automatic transfers authorized based on approved payroll.
                                </p>
                            </div>
                            <div x-show="!disbursementEnabled" class="p-3 bg-slate-50 dark:bg-slate-900/50 rounded-lg flex items-start gap-2">
                                <i data-lucide="info" class="w-4 h-4 text-slate-400 shrink-0 mt-0.5"></i>
                                <p class="text-[10px] text-slate-500 leading-tight">
                                    Payroll will operate in calculation-only mode. No funds will move.
                                </p>
                            </div>
                        </div>
                    </div>

                    <!-- SECTION C: DISBURSEMENT METHODS -->
                    <div>
                        <h3 class="text-lg font-bold text-slate-900 dark:text-white mb-4">Disbursement Channels</h3>
                        <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                            <!-- Method 1 -->
                            <div class="bg-white dark:bg-slate-950 rounded-xl border border-slate-200 dark:border-slate-800 shadow-sm p-6 group hover:border-brand-500 transition-colors">
                                <div class="flex justify-between items-start mb-4">
                                    <div class="w-10 h-10 rounded-lg bg-blue-100 dark:bg-blue-900/20 text-blue-600 flex items-center justify-center"><i data-lucide="landmark" class="w-5 h-5"></i></div>
                                    <span class="px-2 py-1 bg-slate-100 text-slate-500 text-[10px] font-bold rounded">Not Configured</span>
                                </div>
                                <h4 class="font-bold text-slate-900 dark:text-white">Bank Transfer</h4>
                                <p class="text-xs text-slate-500 mt-1 mb-4">Direct bank-to-bank settlement.</p>
                                <button class="w-full py-2 border border-slate-200 dark:border-slate-700 rounded-lg text-xs font-bold text-slate-400 cursor-not-allowed">Configure</button>
                            </div>

                            <!-- Method 2 -->
                            <div class="bg-white dark:bg-slate-950 rounded-xl border border-slate-200 dark:border-slate-800 shadow-sm p-6 group hover:border-brand-500 transition-colors">
                                <div class="flex justify-between items-start mb-4">
                                    <div class="w-10 h-10 rounded-lg bg-green-100 dark:bg-green-900/20 text-green-600 flex items-center justify-center"><i data-lucide="credit-card" class="w-5 h-5"></i></div>
                                    <span class="px-2 py-1 bg-slate-100 text-slate-500 text-[10px] font-bold rounded">Not Configured</span>
                                </div>
                                <h4 class="font-bold text-slate-900 dark:text-white">Payment Gateway</h4>
                                <p class="text-xs text-slate-500 mt-1">Paystack / Flutterwave integration.</p>
                                <button class="w-full py-2 border border-slate-200 dark:border-slate-700 rounded-lg text-xs font-bold text-slate-400 cursor-not-allowed">Configure</button>
                            </div>

                            <!-- Method 3 -->
                            <div class="bg-white dark:bg-slate-950 rounded-xl border border-slate-200 dark:border-slate-800 shadow-sm p-6 group hover:border-brand-500 transition-colors">
                                <div class="flex justify-between items-start mb-4">
                                    <div class="w-10 h-10 rounded-lg bg-slate-100 dark:bg-slate-800 text-slate-600 flex items-center justify-center"><i data-lucide="file-spreadsheet" class="w-5 h-5"></i></div>
                                    <span class="px-2 py-1 bg-green-100 text-green-700 text-[10px] font-bold rounded">Active</span>
                                </div>
                                <h4 class="font-bold text-slate-900 dark:text-white">Manual Schedule</h4>
                                <p class="text-xs text-slate-500 mt-1">Download CSV for manual processing.</p>
                                <button class="w-full py-2 border border-slate-200 dark:border-slate-700 rounded-lg text-xs font-bold text-slate-600 dark:text-slate-300 hover:bg-slate-50 dark:hover:bg-slate-800">Settings</button>
                            </div>
                        </div>
                    </div>

                    <!-- SECTION D: DISBURSEMENT HISTORY -->
                    <div class="bg-white dark:bg-slate-950 rounded-xl border border-slate-200 dark:border-slate-800 shadow-sm p-6 overflow-hidden p-0">
                        <div class="p-6 border-b border-slate-200 dark:border-slate-800 flex justify-between items-center">
                            <h3 class="text-lg font-bold text-slate-900 dark:text-white">Disbursement History</h3>
                            <button class="text-xs font-bold text-brand-600 hover:underline">View All</button>
                        </div>
                        <div class="overflow-x-auto">
                            <table class="w-full text-left text-sm">
                                <thead class="bg-slate-50 dark:bg-slate-900 text-slate-500 border-b border-slate-200 dark:border-slate-800">
                                    <tr>
                                        <th class="px-6 py-3 font-medium">Date</th>
                                        <th class="px-6 py-3 font-medium">Payroll Period</th>
                                        <th class="px-6 py-3 font-medium">Amount</th>
                                        <th class="px-6 py-3 font-medium">Method</th>
                                        <th class="px-6 py-3 font-medium text-center">Status</th>
                                        <th class="px-6 py-3 font-medium text-right">Reference</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
                                    <template x-for="item in history" :key="item.ref">
                                        <tr class="hover:bg-slate-50 dark:hover:bg-slate-900/50">
                                            <td class="px-6 py-4 text-slate-600 dark:text-slate-400" x-text="item.date"></td>
                                            <td class="px-6 py-4 font-medium text-slate-900 dark:text-white" x-text="item.period"></td>
                                            <td class="px-6 py-4 font-bold text-slate-900 dark:text-white" x-text="'₦ ' + item.amount.toLocaleString()"></td>
                                            <td class="px-6 py-4 text-slate-600 dark:text-slate-400" x-text="item.method"></td>
                                            <td class="px-6 py-4 text-center">
                                                <span :class="{
                                                    'bg-green-100 text-green-700 dark:bg-green-900/30 dark:text-green-400': item.status === 'Successful',
                                                    'bg-amber-100 text-amber-700 dark:bg-amber-900/30 dark:text-amber-400': item.status === 'Pending',
                                                    'bg-red-100 text-red-700 dark:bg-red-900/30 dark:text-red-400': item.status === 'Failed'
                                                }" class="px-2 py-1 rounded-full text-xs font-bold" x-text="item.status"></span>
                                            </td>
                                            <td class="px-6 py-4 text-right font-mono text-xs text-slate-400" x-text="item.ref"></td>
                                        </tr>
                                    </template>
                                </tbody>
                            </table>
                        </div>
                    </div>

                    <!-- SECTION F: INFORMATION & RISK NOTICE -->
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div class="p-4 bg-slate-50 dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-lg flex gap-3">
                            <i data-lucide="shield-check" class="w-5 h-5 text-slate-400 shrink-0 mt-0.5"></i>
                            <p class="text-xs text-slate-600 dark:text-slate-400 leading-relaxed">
                                Direct salary disbursement is optional. Companies may continue to process payroll without using wallet disbursement.
                            </p>
                        </div>
                        <div class="p-4 bg-slate-50 dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-lg flex gap-3">
                            <i data-lucide="star" class="w-5 h-5 text-brand-400 shrink-0 mt-0.5"></i>
                            <p class="text-xs text-slate-600 dark:text-slate-400 leading-relaxed">
                                Disbursement availability may depend on your active subscription status. <a href="billing.php" class="text-brand-600 hover:underline">Check Plan</a>
                            </p>
                        </div>
                    </div>

                    <!-- FOOTER -->
                    <div class="pt-8 pb-4 text-center">
                        <p class="text-xs text-black font-medium">Powered by Miemploya Platform</p>
                    </div>

                </div>

            </main>
        </div>
    <!-- Wrapper closing div removed -->


    <!-- Script Logic -->
    <script>
        lucide.createIcons();
        // Standard Sidebar/Theme JS Block
        const themeBtn = document.getElementById('theme-toggle');
        const html = document.documentElement;
        if (localStorage.theme === 'dark' || (!('theme' in localStorage) && window.matchMedia('(prefers-color-scheme: dark)').matches)) { html.classList.add('dark'); } else { html.classList.remove('dark'); }
        if(themeBtn) { themeBtn.addEventListener('click', () => { html.classList.toggle('dark'); localStorage.theme = html.classList.contains('dark') ? 'dark' : 'light'; }); }
        
        const mobileToggle = document.getElementById('mobile-sidebar-toggle');
        const sidebar = document.getElementById('sidebar');
        const collapsedToolbar = document.getElementById('collapsed-toolbar');
        const desktopCollapseBtn = document.getElementById('sidebar-collapse-btn');
        const sidebarExpandBtn = document.getElementById('sidebar-expand-btn');
        if(mobileToggle) { mobileToggle.addEventListener('click', () => { sidebar.classList.toggle('-translate-x-full'); }); }
        function toggleSidebar() { sidebar.classList.toggle('w-64'); sidebar.classList.toggle('w-0'); sidebar.classList.toggle('p-0'); 
            if (sidebar.classList.contains('w-0')) { if(collapsedToolbar) { collapsedToolbar.classList.remove('toolbar-hidden'); collapsedToolbar.classList.add('toolbar-visible'); } } else { if(collapsedToolbar) { collapsedToolbar.classList.add('toolbar-hidden'); collapsedToolbar.classList.remove('toolbar-visible'); } } }
        if(desktopCollapseBtn) desktopCollapseBtn.addEventListener('click', toggleSidebar);
        if(sidebarExpandBtn) sidebarExpandBtn.addEventListener('click', toggleSidebar);

        const notifToggle = document.getElementById('notif-toggle'); const notifClose = document.getElementById('notif-close'); const notifPanel = document.getElementById('notif-panel'); const overlay = document.getElementById('overlay');
        function toggleOverlay(show) { if (show) overlay.classList.remove('hidden'); else overlay.classList.add('hidden'); }
        if(notifToggle) { notifToggle.addEventListener('click', () => { notifPanel.classList.remove('translate-x-full'); toggleOverlay(true); }); }
        if(notifClose) { notifClose.addEventListener('click', () => { notifPanel.classList.add('translate-x-full'); toggleOverlay(false); }); }
        if(overlay) { overlay.addEventListener('click', () => { notifPanel.classList.add('translate-x-full'); toggleOverlay(false); }); }
    </script>
</body>
</html>
