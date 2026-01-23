<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

require_once '../includes/functions.php';
require_login();

$current_page = 'billing';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Subscription & Billing - Mipaymaster</title>
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
        
        .toolbar-hidden { transform: translateY(-100%); opacity: 0; pointer-events: none; height: 0; padding: 0; border: none; }
        .toolbar-visible { transform: translateY(0); opacity: 1; pointer-events: auto; height: auto; }
    </style>
</head>
<body class="bg-slate-50 dark:bg-slate-900 text-slate-900 dark:text-slate-100 transition-colors duration-300 overflow-hidden flex h-screen" x-data="{ sidebarOpen: false }">

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
                        <h2 class="text-xl font-bold text-slate-800 dark:text-white">Subscription & Billing</h2>
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
                
                <div class="max-w-5xl mx-auto space-y-6">

                    <!-- SECTION 1: CURRENT PLAN -->
                    <div class="bg-gradient-to-br from-white to-slate-50 dark:from-slate-950 dark:to-slate-900/50 bg-white dark:bg-slate-950 rounded-xl border border-slate-200 dark:border-slate-800 shadow-sm p-6">
                        <div class="flex flex-col md:flex-row justify-between items-start md:items-center gap-4 mb-6 border-b border-slate-100 dark:border-slate-800 pb-6">
                            <div>
                                <div class="flex items-center gap-3 mb-1">
                                    <h2 class="text-2xl font-bold text-slate-900 dark:text-white">Professional Payroll Plan</h2>
                                    <span class="px-3 py-1 rounded-full text-xs font-bold uppercase tracking-wider inline-flex items-center gap-1 bg-green-100 text-green-700 dark:bg-green-900/30 dark:text-green-400 border border-green-200 dark:border-green-800"><i data-lucide="check-circle" class="w-3 h-3"></i> Active</span>
                                </div>
                                <p class="text-sm text-slate-500">Billed Annually</p>
                            </div>
                            <div class="text-right">
                                <p class="text-2xl font-bold text-slate-900 dark:text-white">₦ 450,000<span class="text-sm text-slate-500 font-normal"> / year</span></p>
                            </div>
                        </div>
                        
                        <div class="grid grid-cols-1 md:grid-cols-3 gap-6 text-sm">
                            <div>
                                <p class="text-xs font-bold text-slate-400 uppercase tracking-wide mb-1">Start Date</p>
                                <p class="font-medium text-slate-700 dark:text-slate-200">Jan 10, 2026</p>
                            </div>
                            <div>
                                <p class="text-xs font-bold text-slate-400 uppercase tracking-wide mb-1">Next Renewal</p>
                                <p class="font-bold text-brand-600 dark:text-brand-400">Jan 10, 2027</p>
                            </div>
                            <div>
                                <p class="text-xs font-bold text-slate-400 uppercase tracking-wide mb-1">Expiry Date</p>
                                <p class="font-medium text-slate-700 dark:text-slate-200">Jan 10, 2027</p>
                            </div>
                        </div>
                    </div>

                    <!-- SECTION 2: SUBSCRIPTION HEALTH -->
                    <div class="bg-white dark:bg-slate-950 rounded-xl border border-slate-200 dark:border-slate-800 shadow-sm p-6">
                        <h3 class="text-sm font-bold text-slate-900 dark:text-white mb-6 uppercase tracking-wide flex items-center gap-2">
                            <i data-lucide="activity" class="w-4 h-4 text-brand-500"></i> Subscription Health
                        </h3>
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-8 items-center">
                            <!-- Employee Limit Usage -->
                            <div>
                                <div class="flex justify-between items-end mb-2">
                                    <span class="text-sm font-medium text-slate-600 dark:text-slate-300">Employee Limit Usage</span>
                                    <span class="text-sm font-bold text-slate-900 dark:text-white">142 / 200</span>
                                </div>
                                <div class="w-full bg-slate-100 dark:bg-slate-800 rounded-full h-3 overflow-hidden">
                                    <div class="bg-brand-600 h-3 rounded-full" style="width: 71%"></div>
                                </div>
                                <p class="text-xs text-slate-500 mt-2">You are using 71% of your plan allowance.</p>
                            </div>

                            <!-- Key Metrics -->
                            <div class="grid grid-cols-2 gap-4">
                                <div class="p-3 bg-slate-50 dark:bg-slate-900 rounded-lg border border-slate-100 dark:border-slate-800">
                                    <p class="text-xs text-slate-500 mb-1">Days Remaining</p>
                                    <p class="text-lg font-bold text-slate-900 dark:text-white">358 Days</p>
                                </div>
                                <div class="p-3 bg-slate-50 dark:bg-slate-900 rounded-lg border border-slate-100 dark:border-slate-800">
                                    <p class="text-xs text-slate-500 mb-1">Auto-Renewal</p>
                                    <div class="flex items-center gap-1.5 text-green-600 font-bold text-sm">
                                        <div class="w-2 h-2 rounded-full bg-green-500"></div> ON
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Feature Badges -->
                        <div class="mt-6 pt-6 border-t border-slate-100 dark:border-slate-800">
                            <div class="flex flex-wrap gap-2">
                                <span class="px-2.5 py-1 bg-slate-100 dark:bg-slate-800 text-slate-600 dark:text-slate-400 text-xs rounded border border-slate-200 dark:border-slate-700">Payroll Engine</span>
                                <span class="px-2.5 py-1 bg-slate-100 dark:bg-slate-800 text-slate-600 dark:text-slate-400 text-xs rounded border border-slate-200 dark:border-slate-700">HR Tools</span>
                                <span class="px-2.5 py-1 bg-slate-100 dark:bg-slate-800 text-slate-600 dark:text-slate-400 text-xs rounded border border-slate-200 dark:border-slate-700">Biometric Sync</span>
                                <span class="px-2.5 py-1 bg-slate-100 dark:bg-slate-800 text-slate-600 dark:text-slate-400 text-xs rounded border border-slate-200 dark:border-slate-700">Premium Support</span>
                            </div>
                        </div>
                    </div>

                    <!-- SECTION 3: PAYMENT & INVOICES -->
                    <div class="bg-white dark:bg-slate-950 rounded-xl border border-slate-200 dark:border-slate-800 shadow-sm p-6">
                        <div class="flex justify-between items-center mb-6">
                            <h3 class="text-sm font-bold text-slate-900 dark:text-white uppercase tracking-wide flex items-center gap-2">
                                <i data-lucide="receipt" class="w-4 h-4 text-brand-500"></i> Latest Payment
                            </h3>
                            <div class="flex gap-2">
                                <button class="text-xs font-medium text-brand-600 hover:underline">View All Invoices</button>
                            </div>
                        </div>
                        
                        <div class="flex flex-col md:flex-row justify-between items-center gap-4 p-4 bg-slate-50 dark:bg-slate-900 rounded-lg border border-slate-100 dark:border-slate-800">
                            <div class="flex items-center gap-4 w-full md:w-auto">
                                <div class="w-10 h-10 rounded-full bg-green-100 dark:bg-green-900/20 text-green-600 flex items-center justify-center shrink-0">
                                    <i data-lucide="check" class="w-5 h-5"></i>
                                </div>
                                <div>
                                    <p class="font-bold text-sm text-slate-900 dark:text-white">Payment Successful</p>
                                    <p class="text-xs text-slate-500">Jan 10, 2026 • Visa ending in 4242</p>
                                </div>
                            </div>
                            <div class="flex items-center gap-4 w-full md:w-auto justify-between md:justify-end">
                                <div class="text-right mr-4">
                                    <p class="font-mono text-sm font-bold text-slate-900 dark:text-white">₦ 450,000.00</p>
                                    <p class="text-xs text-slate-400">Ref: PAY-883920</p>
                                </div>
                                <button class="p-2 text-slate-500 hover:text-brand-600 hover:bg-white dark:hover:bg-slate-800 rounded-lg border border-transparent hover:border-slate-200 dark:hover:border-slate-700 transition-colors" title="Download Receipt">
                                    <i data-lucide="download" class="w-4 h-4"></i>
                                </button>
                            </div>
                        </div>
                    </div>

                    <!-- SECTION 4: ACTIONS -->
                    <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                        <button class="py-3 px-4 bg-brand-600 hover:bg-brand-700 text-white rounded-xl font-bold shadow-md transition-colors flex items-center justify-center gap-2">
                            <i data-lucide="refresh-cw" class="w-4 h-4"></i> Renew Early
                        </button>
                        <a href="../pricing.php" class="py-3 px-4 bg-white dark:bg-slate-800 border border-slate-200 dark:border-slate-700 hover:border-brand-500 text-slate-700 dark:text-slate-200 rounded-xl font-bold shadow-sm transition-colors flex items-center justify-center gap-2">
                            <i data-lucide="zap" class="w-4 h-4 text-amber-500"></i> Upgrade Plan
                        </a>
                        <button class="py-3 px-4 bg-white dark:bg-slate-800 border border-slate-200 dark:border-slate-700 hover:border-slate-400 text-slate-700 dark:text-slate-200 rounded-xl font-medium shadow-sm transition-colors flex items-center justify-center gap-2">
                            <i data-lucide="calendar" class="w-4 h-4"></i> Change Billing Cycle
                        </button>
                    </div>

                    <!-- SECTION 5: INFO NOTICE -->
                    <div class="p-4 bg-blue-50 dark:bg-blue-900/10 border border-blue-100 dark:border-blue-900/30 rounded-xl flex items-start gap-3">
                        <i data-lucide="info" class="w-5 h-5 text-blue-600 dark:text-blue-400 shrink-0 mt-0.5"></i>
                        <p class="text-sm text-blue-800 dark:text-blue-300">
                            <strong>Note:</strong> Subscription status directly affects access to payroll execution, disbursement, and advanced HR features. Ensure your plan remains active to avoid service interruption.
                        </p>
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

        if(mobileToggle) { mobileToggle.addEventListener('click', () => { sidebar.classList.toggle('-translate-x-full'); }); }
        function toggleSidebar() { sidebar.classList.toggle('w-64'); sidebar.classList.toggle('w-0'); sidebar.classList.toggle('p-0'); 
            if (sidebar.classList.contains('w-0')) { if(collapsedToolbar) { collapsedToolbar.classList.remove('toolbar-hidden'); collapsedToolbar.classList.add('toolbar-visible'); } } else { if(collapsedToolbar) { collapsedToolbar.classList.add('toolbar-hidden'); collapsedToolbar.classList.remove('toolbar-visible'); } } }
        if(desktopCollapseBtn) desktopCollapseBtn.addEventListener('click', toggleSidebar);
        const sidebarExpandBtn = document.getElementById('sidebar-expand-btn');
        if(sidebarExpandBtn) sidebarExpandBtn.addEventListener('click', toggleSidebar);

        const notifToggle = document.getElementById('notif-toggle'); const notifClose = document.getElementById('notif-close'); const notifPanel = document.getElementById('notif-panel'); const overlay = document.getElementById('overlay');
        function toggleOverlay(show) { if (show) overlay.classList.remove('hidden'); else overlay.classList.add('hidden'); }
        if(notifToggle) { notifToggle.addEventListener('click', () => { notifPanel.classList.remove('translate-x-full'); toggleOverlay(true); }); }
        if(notifClose) { notifClose.addEventListener('click', () => { notifPanel.classList.add('translate-x-full'); toggleOverlay(false); }); }
        if(overlay) { overlay.addEventListener('click', () => { notifPanel.classList.add('translate-x-full'); toggleOverlay(false); }); }
    </script>
</body>
</html>
