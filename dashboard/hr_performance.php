<?php
require_once '../includes/functions.php';
require_login();
$current_page = 'hr'; 
$current_tab = 'performance'; 
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Performance Evaluation - HR Management</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script>
        tailwind.config = {
            darkMode: 'class',
            theme: {
                extend: {
                    fontFamily: { sans: ['Inter', 'sans-serif'] },
                    colors: {
                        brand: { 50: '#eef2ff', 500: '#6366f1', 600: '#4f46e5', 700: '#4338ca', 900: '#312e81' }
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
        ::-webkit-scrollbar-thumb { background: #cbd5e1; border-radius: 3px; }
        .dark ::-webkit-scrollbar-thumb { background: #334155; }
        .sidebar-transition { transition: margin-left 0.3s ease-in-out, width 0.3s ease-in-out, transform 0.3s ease-in-out; }
        .toolbar-hidden { transform: translateY(-100%); opacity: 0; pointer-events: none; height: 0; padding: 0; border: none; }
        .toolbar-visible { transform: translateY(0); opacity: 1; pointer-events: auto; height: auto; }
    </style>
</head>
<body class="bg-slate-50 dark:bg-slate-900 text-slate-900 dark:text-slate-100 transition-colors duration-300 overflow-hidden flex h-screen" x-data="{}">
    <!-- Wrapper removed -->

        <!-- Sidebar -->
        <?php include '../includes/dashboard_sidebar.php'; ?>

        <!-- NOTIFICATIONS PANEL -->
        <div id="notif-panel" class="fixed inset-y-0 right-0 w-80 bg-white dark:bg-slate-950 shadow-2xl transform translate-x-full transition-transform duration-300 z-50 border-l border-slate-200 dark:border-slate-800">
            <div class="h-16 flex items-center justify-between px-6 border-b border-slate-100 dark:border-slate-800">
                <h3 class="text-lg font-bold text-slate-900 dark:text-white">Notifications</h3>
                <button id="notif-close" class="text-slate-500 hover:text-slate-900 dark:hover:text-white"><i data-lucide="x" class="w-5 h-5"></i></button>
            </div>
            <div class="p-4 space-y-4 overflow-y-auto h-[calc(100vh-64px)]">
                <div class="p-3 bg-brand-50 dark:bg-brand-900/10 rounded-lg border-l-4 border-brand-500">
                    <p class="text-sm font-bold text-slate-900 dark:text-white mb-1">Evaluation Due</p>
                    <p class="text-xs text-slate-600 dark:text-slate-400">Q4 evaluations for Engineering due in 3 days.</p>
                </div>
            </div>
        </div>
        
        <!-- Overlay -->
        <div id="overlay" class="fixed inset-0 bg-black/50 z-30 hidden"></div>

        <!-- Main Content -->
        <div class="flex-1 flex flex-col h-full overflow-hidden w-full relative">
            
            <!-- Global Header -->
            <?php $page_title = 'HR Management'; include '../includes/dashboard_header.php'; ?>

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

            <!-- Page Content -->
            <main class="flex-1 overflow-y-auto bg-slate-50 dark:bg-slate-900 flex flex-col">
                <?php include '../includes/hr_header.php'; ?>

                <!-- Detailed Content -->
                <div class="p-6 lg:p-8 flex-1">
                    
                    <!-- ACTION BAR -->
                    <div class="flex flex-col md:flex-row justify-between items-start md:items-center gap-4 mb-6">
                        <div class="flex items-center gap-2 w-full md:w-auto">
                            <!-- Period Selector -->
                            <div class="flex items-center text-sm font-medium text-slate-600 dark:text-slate-400">
                                <span>Period:</span>
                                <select class="ml-2 bg-transparent font-bold text-slate-900 dark:text-white border-none focus:ring-0 cursor-pointer">
                                    <option>Q4 2025</option>
                                    <option>Q3 2025</option>
                                    <option>Q4 2024</option>
                                </select>
                            </div>
                        </div>
                        <div class="flex items-center gap-2 w-full md:w-auto">
                            <button class="bg-brand-600 hover:bg-brand-700 text-white px-4 py-2 rounded-lg text-sm font-bold flex items-center gap-2 shadow-sm shadow-brand-500/30">
                                <i data-lucide="play-circle" class="w-4 h-4"></i> Start New Evaluation
                            </button>
                        </div>
                    </div>

                    <!-- PERFORMANCE TABLE -->
                    <div class="bg-white dark:bg-slate-950 rounded-xl border border-slate-200 dark:border-slate-800 overflow-hidden shadow-sm">
                        <table class="w-full text-left text-sm">
                            <thead class="bg-slate-50 dark:bg-slate-900 border-b border-slate-200 dark:border-slate-800 text-slate-500 dark:text-slate-400">
                                <tr>
                                    <th class="px-6 py-4 font-medium">Employee Name</th>
                                    <th class="px-6 py-4 font-medium">Department</th>
                                    <th class="px-6 py-4 font-medium">Period</th>
                                    <th class="px-6 py-4 font-medium text-center">Score</th>
                                    <th class="px-6 py-4 font-medium text-center">Status</th>
                                    <th class="px-6 py-4 font-medium text-right">Actions</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
                                <tr class="hover:bg-slate-50 dark:hover:bg-slate-900/50 transition-colors">
                                    <td class="px-6 py-4 font-bold text-slate-900 dark:text-white">Alice Williams</td>
                                    <td class="px-6 py-4 text-slate-600 dark:text-slate-400">Sales</td>
                                    <td class="px-6 py-4 text-slate-600 dark:text-slate-400">Q4 2025</td>
                                    <td class="px-6 py-4 text-center font-bold text-slate-900 dark:text-white">4.2/5.0</td>
                                    <td class="px-6 py-4 text-center"><span class="bg-green-100 text-green-700 px-2 py-1 rounded-full text-xs font-bold">Completed</span></td>
                                    <td class="px-6 py-4 text-right">
                                        <button class="text-brand-600 hover:underline text-xs font-bold">View Report</button>
                                    </td>
                                </tr>
                                <tr class="hover:bg-slate-50 dark:hover:bg-slate-900/50 transition-colors">
                                    <td class="px-6 py-4 font-bold text-slate-900 dark:text-white">Bob Brown</td>
                                    <td class="px-6 py-4 text-slate-600 dark:text-slate-400">Engineering</td>
                                    <td class="px-6 py-4 text-slate-600 dark:text-slate-400">Q4 2025</td>
                                    <td class="px-6 py-4 text-center text-slate-400">-</td>
                                    <td class="px-6 py-4 text-center"><span class="bg-amber-100 text-amber-700 px-2 py-1 rounded-full text-xs font-bold">Pending Self-Eval</span></td>
                                    <td class="px-6 py-4 text-right">
                                        <button class="text-brand-600 hover:underline text-xs font-bold">Nudge</button>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>

                </div>
            </main>
        </div>
    <!-- Wrapper closing div removed -->


    <!-- Script Block -->
    <?php include '../includes/dashboard_scripts.php'; ?>
</body>
</html>
