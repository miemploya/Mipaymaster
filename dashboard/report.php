<?php
require_once '../includes/functions.php';
require_login();
$current_page = 'reports'; // Matches key in dashboard_sidebar.php
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reports - Mipaymaster</title>
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
        
        .sidebar-transition { transition: margin-left 0.3s ease-in-out, width 0.3s ease-in-out, transform 0.3s ease-in-out; }
        .toolbar-hidden { transform: translateY(-100%); opacity: 0; pointer-events: none; height: 0; padding: 0; border: none; }
        .toolbar-visible { transform: translateY(0); opacity: 1; pointer-events: auto; height: auto; }

        /* Custom Scrollbar */
        ::-webkit-scrollbar { width: 6px; height: 6px; }
        ::-webkit-scrollbar-track { background: transparent; }
        ::-webkit-scrollbar-thumb { background: #cbd5e1; border-radius: 3px; }
        .dark ::-webkit-scrollbar-thumb { background: #334155; }
        
        [x-cloak] { display: none !important; }
        
        .sidebar-transition { transition: margin-left 0.3s ease-in-out, width 0.3s ease-in-out, transform 0.3s ease-in-out; }
        .toolbar-hidden { transform: translateY(-100%); opacity: 0; pointer-events: none; height: 0; padding: 0; border: none; }
        .toolbar-visible { transform: translateY(0); opacity: 1; pointer-events: auto; height: auto; }
    </style>
    
    <script>
        function reportsApp() {
            return {
                view: 'payroll', // payroll, statutory, attendance, hr, audit
                sidebarOpen: false,

                // Mock Audit Logs
                auditLogs: [
                    { date: 'Jan 15, 10:30 AM', user: 'Admin User', action: 'Approved Payroll', module: 'Payroll', details: 'Jan 2026 Batch A' },
                    { date: 'Jan 15, 09:15 AM', user: 'HR Manager', action: 'Added Employee', module: 'HR', details: 'Sarah Connor (MIP-045)' },
                    { date: 'Jan 14, 04:45 PM', user: 'System', action: 'Auto-Sync', module: 'Attendance', details: 'Biometric Device #2' },
                    { date: 'Jan 14, 02:00 PM', user: 'Admin User', action: 'Updated Settings', module: 'Settings', details: 'Changed Tax Config' },
                ],
                
                init() {
                    this.$watch('view', () => setTimeout(() => lucide.createIcons(), 50));
                },
                
                changeView(newView) {
                    this.view = newView;
                    window.scrollTo({top: 0, behavior: 'smooth'});
                }
            }
        }
    </script>
</head>
<body class="bg-slate-50 dark:bg-slate-900 text-slate-900 dark:text-slate-100 transition-colors duration-300 overflow-hidden flex h-screen" x-data="reportsApp()">

    <!-- Wrapper removed -->


        <!-- Sidebar Include -->
        <?php include '../includes/dashboard_sidebar.php'; ?>
        
        <!-- NOTIFICATIONS PANEL (Restored System Standard) -->
        <div id="notif-panel" class="fixed inset-y-0 right-0 w-80 bg-white dark:bg-slate-950 shadow-2xl transform translate-x-full transition-transform duration-300 z-50 border-l border-slate-200 dark:border-slate-800">
            <div class="h-16 flex items-center justify-between px-6 border-b border-slate-100 dark:border-slate-800">
                <h3 class="text-lg font-bold text-slate-900 dark:text-white">Notifications</h3>
                <button id="notif-close" class="text-slate-500 hover:text-slate-900 dark:hover:text-white"><i data-lucide="x" class="w-5 h-5"></i></button>
            </div>
            <div class="p-4 space-y-4 overflow-y-auto h-[calc(100vh-64px)]">
                <p class="text-sm text-slate-500 p-4 text-center">No new notifications.</p>
            </div>
        </div>
        
        <!-- Overlay -->
        <div id="overlay" class="fixed inset-0 bg-black/50 z-30 hidden"></div>

        <!-- MAIN CONTENT -->
        <div class="flex-1 flex flex-col h-full overflow-hidden w-full relative">
            
            <header class="h-16 bg-white dark:bg-slate-950 border-b border-slate-200 dark:border-slate-800 flex items-center justify-between px-6 shrink-0 z-30">
                <div class="flex items-center gap-4">
                    <button id="mobile-sidebar-toggle" class="md:hidden text-slate-500 hover:text-slate-700 dark:hover:text-slate-300">
                        <i data-lucide="menu" class="w-6 h-6"></i>
                    </button>
                    <h2 class="text-xl font-bold text-slate-800 dark:text-white">Reports</h2>
                </div>
                <!-- Standard Header Actions (Integrated) -->
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

            <!-- Horizontal Navigation -->
            <div id="horizontal-nav" class="hidden bg-white dark:bg-slate-950 border-b border-slate-200 dark:border-slate-800 px-6 py-2">
                <!-- Dynamic Nav Content -->
            </div>


            <!-- Collapsed Toolbar (Restored) -->
            <div id="collapsed-toolbar" class="toolbar-hidden w-full bg-white dark:bg-slate-950 border-b border-slate-200 dark:border-slate-800 flex items-center px-6 shrink-0 shadow-sm z-20">
                <button id="sidebar-expand-btn" class="flex items-center gap-2 p-2 rounded-lg text-slate-500 hover:text-brand-600 dark:text-slate-400 dark:hover:text-white hover:bg-slate-100 dark:hover:bg-slate-800 transition-colors my-2">
                    <i data-lucide="menu" class="w-5 h-5"></i> <span class="text-sm font-medium">Show Menu</span>
                </button>
            </div>

            <!-- Main Scrollable Area -->
            <main class="flex-1 overflow-y-auto p-6 lg:p-8 bg-slate-50 dark:bg-slate-900 scroll-smooth">
                
                <!-- Internal Navigation Tabs (Segmented Control) -->
                <div class="mb-8 overflow-x-auto pb-2">
                    <div class="flex gap-2 min-w-max p-1 bg-slate-100 dark:bg-slate-950/50 border border-slate-200 dark:border-slate-800 rounded-xl w-fit">
                        
                        <button @click="changeView('payroll')" 
                            :class="view === 'payroll' ? 'bg-white dark:bg-slate-800 text-brand-600 dark:text-brand-400 shadow-sm ring-1 ring-slate-950/5' : 'text-slate-500 dark:text-slate-400 hover:text-slate-900 dark:hover:text-white hover:bg-white/50 dark:hover:bg-slate-800/50'" 
                            class="px-4 py-2 text-sm font-bold rounded-lg transition-all flex items-center gap-2 whitespace-nowrap">
                            <i data-lucide="banknote" class="w-4 h-4"></i> Payroll Reports
                        </button>

                        <button @click="changeView('statutory')" 
                            :class="view === 'statutory' ? 'bg-white dark:bg-slate-800 text-brand-600 dark:text-brand-400 shadow-sm ring-1 ring-slate-950/5' : 'text-slate-500 dark:text-slate-400 hover:text-slate-900 dark:hover:text-white hover:bg-white/50 dark:hover:bg-slate-800/50'" 
                            class="px-4 py-2 text-sm font-bold rounded-lg transition-all flex items-center gap-2 whitespace-nowrap">
                            <i data-lucide="scale" class="w-4 h-4"></i> Statutory Reports
                        </button>
                        
                        <button @click="changeView('attendance')" 
                            :class="view === 'attendance' ? 'bg-white dark:bg-slate-800 text-brand-600 dark:text-brand-400 shadow-sm ring-1 ring-slate-950/5' : 'text-slate-500 dark:text-slate-400 hover:text-slate-900 dark:hover:text-white hover:bg-white/50 dark:hover:bg-slate-800/50'" 
                            class="px-4 py-2 text-sm font-bold rounded-lg transition-all flex items-center gap-2 whitespace-nowrap">
                            <i data-lucide="calendar" class="w-4 h-4"></i> Attendance Reports
                        </button>
                        
                        <button @click="changeView('hr')" 
                            :class="view === 'hr' ? 'bg-white dark:bg-slate-800 text-brand-600 dark:text-brand-400 shadow-sm ring-1 ring-slate-950/5' : 'text-slate-500 dark:text-slate-400 hover:text-slate-900 dark:hover:text-white hover:bg-white/50 dark:hover:bg-slate-800/50'" 
                            class="px-4 py-2 text-sm font-bold rounded-lg transition-all flex items-center gap-2 whitespace-nowrap">
                            <i data-lucide="users" class="w-4 h-4"></i> HR Reports
                        </button>
                        
                        <button @click="changeView('audit')" 
                            :class="view === 'audit' ? 'bg-white dark:bg-slate-800 text-brand-600 dark:text-brand-400 shadow-sm ring-1 ring-slate-950/5' : 'text-slate-500 dark:text-slate-400 hover:text-slate-900 dark:hover:text-white hover:bg-white/50 dark:hover:bg-slate-800/50'" 
                            class="px-4 py-2 text-sm font-bold rounded-lg transition-all flex items-center gap-2 whitespace-nowrap">
                            <i data-lucide="shield-alert" class="w-4 h-4"></i> Audit & Logs
                        </button>
                    </div>
                </div>

                <!-- VIEW 1: PAYROLL REPORTS -->
                <div x-show="view === 'payroll'" x-cloak x-transition.opacity>
                    <!-- Filters -->
                    <div class="bg-white dark:bg-slate-950 p-6 rounded-xl border border-slate-200 dark:border-slate-800 shadow-sm mb-6">
                        <div class="grid grid-cols-1 md:grid-cols-4 gap-4 items-end">
                            <div><label class="block text-xs font-bold text-slate-500 dark:text-slate-400 uppercase tracking-wide mb-1">Payroll Period</label><input type="month" value="2026-01" class="w-full rounded-lg border-slate-300 dark:border-slate-700 bg-white dark:bg-slate-800 text-slate-900 dark:text-white focus:ring-brand-500 focus:border-brand-500 transition-colors shadow-sm text-sm p-2.5"></div>
                            <div><label class="block text-xs font-bold text-slate-500 dark:text-slate-400 uppercase tracking-wide mb-1">Department</label><select class="w-full rounded-lg border-slate-300 dark:border-slate-700 bg-white dark:bg-slate-800 text-slate-900 dark:text-white focus:ring-brand-500 focus:border-brand-500 transition-colors shadow-sm text-sm p-2.5"><option>All Departments</option></select></div>
                            <div><label class="block text-xs font-bold text-slate-500 dark:text-slate-400 uppercase tracking-wide mb-1">Category</label><select class="w-full rounded-lg border-slate-300 dark:border-slate-700 bg-white dark:bg-slate-800 text-slate-900 dark:text-white focus:ring-brand-500 focus:border-brand-500 transition-colors shadow-sm text-sm p-2.5"><option>All Categories</option></select></div>
                            <button class="px-4 py-2.5 bg-slate-900 dark:bg-white text-white dark:text-slate-900 rounded-lg font-bold text-sm hover:opacity-90 transition-opacity">Apply Filters</button>
                        </div>
                    </div>

                    <!-- Available Reports -->
                    <h3 class="text-lg font-bold text-slate-900 dark:text-white mb-4">Available Reports</h3>
                    <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                        <!-- Report Card -->
                        <div class="bg-white dark:bg-slate-950 p-6 rounded-xl border border-slate-200 dark:border-slate-800 hover:border-brand-500 dark:hover:border-brand-500 transition-all cursor-pointer shadow-sm hover:shadow-md group flex flex-col h-full group">
                            <div class="flex justify-between items-start mb-4">
                                <div class="w-10 h-10 rounded-lg bg-green-100 dark:bg-green-900/20 text-green-600 flex items-center justify-center"><i data-lucide="table-2" class="w-5 h-5"></i></div>
                                <i data-lucide="download" class="w-4 h-4 text-slate-400 group-hover:text-brand-600"></i>
                            </div>
                            <h4 class="font-bold text-slate-900 dark:text-white group-hover:text-brand-600 mb-1">Payroll Summary</h4>
                            <p class="text-xs text-slate-500 mb-4 flex-1">High-level view of total gross, deductions, and net pay per department.</p>
                            <div class="flex gap-2">
                                <button class="flex-1 py-2 text-xs font-bold border border-slate-200 dark:border-slate-700 rounded hover:bg-slate-50 dark:hover:bg-slate-800">PDF</button>
                                <button class="flex-1 py-2 text-xs font-bold border border-slate-200 dark:border-slate-700 rounded hover:bg-slate-50 dark:hover:bg-slate-800">Excel</button>
                            </div>
                        </div>

                        <!-- Report Card -->
                        <div class="bg-white dark:bg-slate-950 p-6 rounded-xl border border-slate-200 dark:border-slate-800 hover:border-brand-500 dark:hover:border-brand-500 transition-all cursor-pointer shadow-sm hover:shadow-md group flex flex-col h-full group">
                            <div class="flex justify-between items-start mb-4">
                                <div class="w-10 h-10 rounded-lg bg-blue-100 dark:bg-blue-900/20 text-blue-600 flex items-center justify-center"><i data-lucide="list" class="w-5 h-5"></i></div>
                                <i data-lucide="download" class="w-4 h-4 text-slate-400 group-hover:text-brand-600"></i>
                            </div>
                            <h4 class="font-bold text-slate-900 dark:text-white group-hover:text-brand-600 mb-1">Payroll Ledger</h4>
                            <p class="text-xs text-slate-500 mb-4 flex-1">Detailed breakdown of earnings and deductions for accounting.</p>
                            <div class="flex gap-2">
                                <button class="flex-1 py-2 text-xs font-bold border border-slate-200 dark:border-slate-700 rounded hover:bg-slate-50 dark:hover:bg-slate-800">PDF</button>
                                <button class="flex-1 py-2 text-xs font-bold border border-slate-200 dark:border-slate-700 rounded hover:bg-slate-50 dark:hover:bg-slate-800">Excel</button>
                            </div>
                        </div>

                        <!-- Report Card -->
                        <div class="bg-white dark:bg-slate-950 p-6 rounded-xl border border-slate-200 dark:border-slate-800 hover:border-brand-500 dark:hover:border-brand-500 transition-all cursor-pointer shadow-sm hover:shadow-md group flex flex-col h-full group">
                            <div class="flex justify-between items-start mb-4">
                                <div class="w-10 h-10 rounded-lg bg-purple-100 dark:bg-purple-900/20 text-purple-600 flex items-center justify-center"><i data-lucide="wallet" class="w-5 h-5"></i></div>
                                <i data-lucide="download" class="w-4 h-4 text-slate-400 group-hover:text-brand-600"></i>
                            </div>
                            <h4 class="font-bold text-slate-900 dark:text-white group-hover:text-brand-600 mb-1">Net Pay Analysis</h4>
                            <p class="text-xs text-slate-500 mb-4 flex-1">Analysis of net payments to banks for processing.</p>
                            <div class="flex gap-2">
                                <button class="flex-1 py-2 text-xs font-bold border border-slate-200 dark:border-slate-700 rounded hover:bg-slate-50 dark:hover:bg-slate-800">PDF</button>
                                <button class="flex-1 py-2 text-xs font-bold border border-slate-200 dark:border-slate-700 rounded hover:bg-slate-50 dark:hover:bg-slate-800">Excel</button>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- VIEW 2: STATUTORY REPORTS -->
                <div x-show="view === 'statutory'" x-cloak x-transition.opacity>
                    <div class="bg-white dark:bg-slate-950 p-6 rounded-xl border border-slate-200 dark:border-slate-800 shadow-sm mb-6">
                        <div class="grid grid-cols-1 md:grid-cols-4 gap-4 items-end">
                            <div><label class="block text-xs font-bold text-slate-500 dark:text-slate-400 uppercase tracking-wide mb-1">Period</label><input type="month" value="2026-01" class="w-full rounded-lg border-slate-300 dark:border-slate-700 bg-white dark:bg-slate-800 text-slate-900 dark:text-white focus:ring-brand-500 focus:border-brand-500 transition-colors shadow-sm text-sm p-2.5"></div>
                            <div><label class="block text-xs font-bold text-slate-500 dark:text-slate-400 uppercase tracking-wide mb-1">State</label><select class="w-full rounded-lg border-slate-300 dark:border-slate-700 bg-white dark:bg-slate-800 text-slate-900 dark:text-white focus:ring-brand-500 focus:border-brand-500 transition-colors shadow-sm text-sm p-2.5"><option>All States</option><option>Lagos</option></select></div>
                            <div></div>
                            <button class="px-4 py-2.5 bg-slate-900 dark:bg-white text-white dark:text-slate-900 rounded-lg font-bold text-sm hover:opacity-90 transition-opacity">Generate All</button>
                        </div>
                    </div>

                    <h3 class="text-lg font-bold text-slate-900 dark:text-white mb-4">Regulatory Compliance</h3>
                    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6">
                        <div class="bg-white dark:bg-slate-950 p-6 rounded-xl border border-slate-200 dark:border-slate-800 hover:border-brand-500 dark:hover:border-brand-500 transition-all cursor-pointer shadow-sm hover:shadow-md group flex flex-col h-full group">
                            <div class="w-10 h-10 rounded-lg bg-green-100 dark:bg-green-900/20 text-green-600 flex items-center justify-center mb-4"><i data-lucide="file-check" class="w-5 h-5"></i></div>
                            <h4 class="font-bold text-slate-900 dark:text-white">PAYE Schedule</h4>
                            <p class="text-xs text-slate-500 mb-4">Tax remittance for Internal Revenue Service.</p>
                            <a href="print_tax_report.php" target="_blank" class="w-full py-2 text-xs font-bold bg-slate-50 dark:bg-slate-800 rounded hover:bg-slate-100 dark:hover:bg-slate-700 text-center block">Download</a>
                        </div>
                        <div class="bg-white dark:bg-slate-950 p-6 rounded-xl border border-slate-200 dark:border-slate-800 hover:border-brand-500 dark:hover:border-brand-500 transition-all cursor-pointer shadow-sm hover:shadow-md group flex flex-col h-full group">
                            <div class="w-10 h-10 rounded-lg bg-blue-100 dark:bg-blue-900/20 text-blue-600 flex items-center justify-center mb-4"><i data-lucide="piggy-bank" class="w-5 h-5"></i></div>
                            <h4 class="font-bold text-slate-900 dark:text-white">Pension Report</h4>
                            <p class="text-xs text-slate-500 mb-4">Contribution schedule for PFAs.</p>
                            <button onclick="alert('Pension report module is coming soon.')" class="w-full py-2 text-xs font-bold bg-slate-50 dark:bg-slate-800 rounded hover:bg-slate-100 dark:hover:bg-slate-700">Download</button>
                        </div>
                        <div class="bg-white dark:bg-slate-950 p-6 rounded-xl border border-slate-200 dark:border-slate-800 hover:border-brand-500 dark:hover:border-brand-500 transition-all cursor-pointer shadow-sm hover:shadow-md group flex flex-col h-full group">
                            <div class="w-10 h-10 rounded-lg bg-orange-100 dark:bg-orange-900/20 text-orange-600 flex items-center justify-center mb-4"><i data-lucide="heart-pulse" class="w-5 h-5"></i></div>
                            <h4 class="font-bold text-slate-900 dark:text-white">NHIS Report</h4>
                            <p class="text-xs text-slate-500 mb-4">Health insurance contribution schedule.</p>
                            <button onclick="alert('NHIS report module is coming soon.')" class="w-full py-2 text-xs font-bold bg-slate-50 dark:bg-slate-800 rounded hover:bg-slate-100 dark:hover:bg-slate-700">Download</button>
                        </div>
                        <div class="bg-white dark:bg-slate-950 p-6 rounded-xl border border-slate-200 dark:border-slate-800 hover:border-brand-500 dark:hover:border-brand-500 transition-all cursor-pointer shadow-sm hover:shadow-md group flex flex-col h-full group">
                            <div class="w-10 h-10 rounded-lg bg-purple-100 dark:bg-purple-900/20 text-purple-600 flex items-center justify-center mb-4"><i data-lucide="home" class="w-5 h-5"></i></div>
                            <h4 class="font-bold text-slate-900 dark:text-white">NHF Report</h4>
                            <p class="text-xs text-slate-500 mb-4">National Housing Fund remittance.</p>
                            <button onclick="alert('NHF report module is coming soon.')" class="w-full py-2 text-xs font-bold bg-slate-50 dark:bg-slate-800 rounded hover:bg-slate-100 dark:hover:bg-slate-700">Download</button>
                        </div>
                    </div>
                </div>

                <!-- VIEW 3: ATTENDANCE REPORTS -->
                <div x-show="view === 'attendance'" x-cloak x-transition.opacity>
                    <div class="bg-white dark:bg-slate-950 p-6 rounded-xl border border-slate-200 dark:border-slate-800 shadow-sm mb-6">
                        <div class="grid grid-cols-1 md:grid-cols-3 gap-4 items-end">
                            <div><label class="block text-xs font-bold text-slate-500 dark:text-slate-400 uppercase tracking-wide mb-1">Date Range</label><input type="date" class="w-full rounded-lg border-slate-300 dark:border-slate-700 bg-white dark:bg-slate-800 text-slate-900 dark:text-white focus:ring-brand-500 focus:border-brand-500 transition-colors shadow-sm text-sm p-2.5"></div>
                            <div><label class="block text-xs font-bold text-slate-500 dark:text-slate-400 uppercase tracking-wide mb-1">Department</label><select class="w-full rounded-lg border-slate-300 dark:border-slate-700 bg-white dark:bg-slate-800 text-slate-900 dark:text-white focus:ring-brand-500 focus:border-brand-500 transition-colors shadow-sm text-sm p-2.5"><option>All</option></select></div>
                            <button class="px-4 py-2.5 bg-slate-900 dark:bg-white text-white dark:text-slate-900 rounded-lg font-bold text-sm">Update View</button>
                        </div>
                    </div>

                    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6">
                        <div class="bg-white dark:bg-slate-950 p-6 rounded-xl border border-slate-200 dark:border-slate-800 hover:border-brand-500 dark:hover:border-brand-500 transition-all cursor-pointer shadow-sm hover:shadow-md group flex flex-col h-full">
                            <div class="w-10 h-10 rounded-lg bg-slate-100 dark:bg-slate-800 text-slate-600 flex items-center justify-center mb-4"><i data-lucide="calendar" class="w-5 h-5"></i></div>
                            <h4 class="font-bold text-slate-900 dark:text-white">Daily Attendance</h4>
                            <p class="text-xs text-slate-500 mb-4">Detailed in/out logs per day.</p>
                            <button class="w-full py-2 text-xs font-bold border rounded">View</button>
                        </div>
                        <div class="bg-white dark:bg-slate-950 p-6 rounded-xl border border-slate-200 dark:border-slate-800 hover:border-brand-500 dark:hover:border-brand-500 transition-all cursor-pointer shadow-sm hover:shadow-md group flex flex-col h-full">
                            <div class="w-10 h-10 rounded-lg bg-slate-100 dark:bg-slate-800 text-slate-600 flex items-center justify-center mb-4"><i data-lucide="clock" class="w-5 h-5"></i></div>
                            <h4 class="font-bold text-slate-900 dark:text-white">Lateness Report</h4>
                            <p class="text-xs text-slate-500 mb-4">Employees with late arrivals & penalties.</p>
                            <button class="w-full py-2 text-xs font-bold border rounded">View</button>
                        </div>
                        <div class="bg-white dark:bg-slate-950 p-6 rounded-xl border border-slate-200 dark:border-slate-800 hover:border-brand-500 dark:hover:border-brand-500 transition-all cursor-pointer shadow-sm hover:shadow-md group flex flex-col h-full">
                            <div class="w-10 h-10 rounded-lg bg-slate-100 dark:bg-slate-800 text-slate-600 flex items-center justify-center mb-4"><i data-lucide="user-x" class="w-5 h-5"></i></div>
                            <h4 class="font-bold text-slate-900 dark:text-white">Absence Report</h4>
                            <p class="text-xs text-slate-500 mb-4">Unexcused absences & leave days.</p>
                            <button class="w-full py-2 text-xs font-bold border rounded">View</button>
                        </div>
                         <div class="bg-white dark:bg-slate-950 p-6 rounded-xl border border-slate-200 dark:border-slate-800 hover:border-brand-500 dark:hover:border-brand-500 transition-all cursor-pointer shadow-sm hover:shadow-md group flex flex-col h-full">
                            <div class="w-10 h-10 rounded-lg bg-slate-100 dark:bg-slate-800 text-slate-600 flex items-center justify-center mb-4"><i data-lucide="hourglass" class="w-5 h-5"></i></div>
                            <h4 class="font-bold text-slate-900 dark:text-white">Overtime Report</h4>
                            <p class="text-xs text-slate-500 mb-4">Approved overtime hours & costs.</p>
                            <button class="w-full py-2 text-xs font-bold border rounded">View</button>
                        </div>
                    </div>
                </div>

                <!-- VIEW 4: HR REPORTS -->
                <div x-show="view === 'hr'" x-cloak x-transition.opacity>
                    <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                        <div class="bg-white dark:bg-slate-950 p-6 rounded-xl border border-slate-200 dark:border-slate-800 hover:border-brand-500 dark:hover:border-brand-500 transition-all cursor-pointer shadow-sm hover:shadow-md group flex flex-col h-full">
                            <h4 class="font-bold text-slate-900 dark:text-white mb-2">Employee Master List</h4>
                            <p class="text-xs text-slate-500 mb-4">Full database of all active employees.</p>
                            <button class="mt-auto w-full py-2 text-xs font-bold bg-brand-50 text-brand-700 rounded">Export Excel</button>
                        </div>
                        <div class="bg-white dark:bg-slate-950 p-6 rounded-xl border border-slate-200 dark:border-slate-800 hover:border-brand-500 dark:hover:border-brand-500 transition-all cursor-pointer shadow-sm hover:shadow-md group flex flex-col h-full">
                            <h4 class="font-bold text-slate-900 dark:text-white mb-2">New Hires Report</h4>
                            <p class="text-xs text-slate-500 mb-4">Employees joined within date range.</p>
                            <button class="mt-auto w-full py-2 text-xs font-bold bg-brand-50 text-brand-700 rounded">Export Excel</button>
                        </div>
                        <div class="bg-white dark:bg-slate-950 p-6 rounded-xl border border-slate-200 dark:border-slate-800 hover:border-brand-500 dark:hover:border-brand-500 transition-all cursor-pointer shadow-sm hover:shadow-md group flex flex-col h-full">
                            <h4 class="font-bold text-slate-900 dark:text-white mb-2">Attrition / Exits</h4>
                            <p class="text-xs text-slate-500 mb-4">Employees who left the company.</p>
                            <button class="mt-auto w-full py-2 text-xs font-bold bg-brand-50 text-brand-700 rounded">Export Excel</button>
                        </div>
                    </div>
                </div>

                <!-- VIEW 5: AUDIT LOGS -->
                <div x-show="view === 'audit'" x-cloak x-transition.opacity>
                    <div class="bg-white dark:bg-slate-950 rounded-xl border border-slate-200 dark:border-slate-800 overflow-hidden shadow-sm">
                        <div class="p-4 border-b border-slate-200 dark:border-slate-800 bg-slate-50 dark:bg-slate-900 flex justify-between items-center">
                            <h3 class="text-sm font-bold text-slate-700 dark:text-slate-300 uppercase tracking-wide">System Activity Log</h3>
                            <button class="text-xs text-brand-600 font-bold hover:underline flex items-center gap-1"><i data-lucide="download" class="w-3 h-3"></i> Export Full Log</button>
                        </div>
                        <table class="w-full text-left text-sm">
                            <thead class="bg-slate-100 dark:bg-slate-900 border-b border-slate-200 dark:border-slate-800 text-slate-500">
                                <tr>
                                    <th class="p-4 font-medium">Date & Time</th>
                                    <th class="p-4 font-medium">User</th>
                                    <th class="p-4 font-medium">Action</th>
                                    <th class="p-4 font-medium">Module</th>
                                    <th class="p-4 font-medium">Details</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
                                <template x-for="log in auditLogs" :key="log.date">
                                    <tr class="hover:bg-slate-50 dark:hover:bg-slate-900/50 transition-colors">
                                        <td class="p-4 font-mono text-slate-500 text-xs" x-text="log.date"></td>
                                        <td class="p-4 font-medium text-slate-900 dark:text-white" x-text="log.user"></td>
                                        <td class="p-4 font-bold text-slate-700 dark:text-slate-300" x-text="log.action"></td>
                                        <td class="p-4 text-slate-500" x-text="log.module"></td>
                                        <td class="p-4 text-slate-500 italic" x-text="log.details"></td>
                                    </tr>
                                </template>
                            </tbody>
                        </table>
                    </div>
                </div>

            </main>
        </div>
    <!-- Wrapper closing div removed -->


    <!-- Script Logic -->
    <script>
        lucide.createIcons();

        // Theme Logic
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

        // Standard System Logic (Notification & Sidebar)
        const notifToggle = document.getElementById('notif-toggle');
        const notifClose = document.getElementById('notif-close');
        const notifPanel = document.getElementById('notif-panel');
        const overlay = document.getElementById('overlay');
        const mobileToggle = document.getElementById('mobile-sidebar-toggle');
        const sidebar = document.getElementById('sidebar');
        const collapsedToolbar = document.getElementById('collapsed-toolbar');
        const desktopCollapseBtn = document.getElementById('sidebar-collapse-btn');
        const sidebarExpandBarBtn = document.getElementById('sidebar-expand-bar-btn');
        
        function toggleOverlay(show) {
            if (show) overlay.classList.remove('hidden');
            else overlay.classList.add('hidden');
        }

        if(notifToggle) {
            notifToggle.addEventListener('click', () => {
                notifPanel.classList.remove('translate-x-full');
                toggleOverlay(true);
            });
        }

        if(notifClose) {
            notifClose.addEventListener('click', () => {
                notifPanel.classList.add('translate-x-full');
                toggleOverlay(false);
            });
        }

        if(overlay) {
            overlay.addEventListener('click', () => {
                notifPanel.classList.add('translate-x-full');
                if(sidebar) sidebar.classList.add('-translate-x-full'); 
                toggleOverlay(false);
            });
        }

        if(mobileToggle) {
            mobileToggle.addEventListener('click', () => {
                sidebar.classList.toggle('-translate-x-full');
                if (!sidebar.classList.contains('-translate-x-full')) {
                    toggleOverlay(true);
                } else {
                    toggleOverlay(false);
                }
            });
        }

        function toggleSidebar() {
            sidebar.classList.toggle('w-64');
            sidebar.classList.toggle('w-0');
            sidebar.classList.toggle('p-0'); 
            if (sidebar.classList.contains('w-0')) {
                if(collapsedToolbar) { collapsedToolbar.classList.remove('toolbar-hidden'); collapsedToolbar.classList.add('toolbar-visible'); }
            } else {
                if(collapsedToolbar) { collapsedToolbar.classList.add('toolbar-hidden'); collapsedToolbar.classList.remove('toolbar-visible'); }
            }
        }
        if(desktopCollapseBtn) desktopCollapseBtn.addEventListener('click', toggleSidebar);
        const sidebarExpandBtn = document.getElementById('sidebar-expand-btn');
        if(sidebarExpandBtn) sidebarExpandBtn.addEventListener('click', toggleSidebar);
    </script>
</body>
</html>
