<?php
require_once '../includes/functions.php';
require_login();
$current_page = 'hr'; 
$current_tab = 'onboarding'; 
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Onboarding - HR Management</title>
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
                <!-- Mock Notifications -->
                <div class="p-3 bg-brand-50 dark:bg-brand-900/10 rounded-lg border-l-4 border-brand-500">
                    <p class="text-sm font-bold text-slate-900 dark:text-white mb-1">Onboarding Update</p>
                    <p class="text-xs text-slate-600 dark:text-slate-400">Jane Smith has submitted her documents.</p>
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
                    
                    <!-- PIPELINE VISUAL -->
                    <div class="grid grid-cols-2 md:grid-cols-5 gap-4 mb-8">
                        <div class="bg-white dark:bg-slate-950 p-4 rounded-xl border border-l-4 border-slate-200 dark:border-slate-800 border-l-brand-600 shadow-sm">
                            <h3 class="text-xs font-bold uppercase text-slate-500 mb-1">Stage 1</h3>
                            <p class="font-bold text-slate-900 dark:text-white">Offer Issued</p>
                            <div class="mt-2 text-2xl font-bold">4</div>
                        </div>
                        <div class="bg-white dark:bg-slate-950 p-4 rounded-xl border border-l-4 border-slate-200 dark:border-slate-800 border-l-amber-500 shadow-sm">
                            <h3 class="text-xs font-bold uppercase text-slate-500 mb-1">Stage 2</h3>
                            <p class="font-bold text-slate-900 dark:text-white">Docs Submitted</p>
                             <div class="mt-2 text-2xl font-bold">2</div>
                        </div>
                        <div class="bg-white dark:bg-slate-950 p-4 rounded-xl border border-slate-200 dark:border-slate-800 opacity-70">
                            <h3 class="text-xs font-bold uppercase text-slate-500 mb-1">Stage 3</h3>
                            <p class="font-bold text-slate-900 dark:text-white">Verification</p>
                             <div class="mt-2 text-2xl font-bold">1</div>
                        </div>
                         <div class="bg-white dark:bg-slate-950 p-4 rounded-xl border border-slate-200 dark:border-slate-800 opacity-70">
                            <h3 class="text-xs font-bold uppercase text-slate-500 mb-1">Stage 4</h3>
                            <p class="font-bold text-slate-900 dark:text-white">Emp. Created</p>
                             <div class="mt-2 text-2xl font-bold">0</div>
                        </div>
                         <div class="bg-white dark:bg-slate-950 p-4 rounded-xl border border-slate-200 dark:border-slate-800 opacity-70">
                            <h3 class="text-xs font-bold uppercase text-slate-500 mb-1">Stage 5</h3>
                            <p class="font-bold text-slate-900 dark:text-white">Completed</p>
                             <div class="mt-2 text-2xl font-bold">12</div>
                        </div>
                    </div>

                    <!-- ONBOARDING TABLE -->
                    <div class="bg-white dark:bg-slate-950 rounded-xl border border-slate-200 dark:border-slate-800 overflow-hidden shadow-sm">
                        <table class="w-full text-left text-sm">
                            <thead class="bg-slate-50 dark:bg-slate-900 border-b border-slate-200 dark:border-slate-800 text-slate-500 dark:text-slate-400">
                                <tr>
                                    <th class="px-6 py-4 font-medium">Candidate Name</th>
                                    <th class="px-6 py-4 font-medium">Position</th>
                                    <th class="px-6 py-4 font-medium">Stage</th>
                                    <th class="px-6 py-4 font-medium">Assigned HR</th>
                                    <th class="px-6 py-4 font-medium text-right">Action</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
                                <tr class="hover:bg-slate-50 dark:hover:bg-slate-900/50 transition-colors">
                                    <td class="px-6 py-4 flex items-center gap-3">
                                        <div class="w-8 h-8 rounded-full bg-brand-100 text-brand-600 flex items-center justify-center font-bold">JS</div>
                                        <span class="font-bold text-slate-900 dark:text-white">Jane Smith</span>
                                    </td>
                                    <td class="px-6 py-4 text-slate-600 dark:text-slate-400">Product Designer</td>
                                    <td class="px-6 py-4"><span class="bg-amber-100 text-amber-700 px-2 py-1 rounded-full text-xs font-bold">Docs Submitted</span></td>
                                    <td class="px-6 py-4 text-slate-600 dark:text-slate-400">Admin</td>
                                    <td class="px-6 py-4 text-right">
                                        <button class="bg-slate-900 dark:bg-white text-white dark:text-slate-900 px-3 py-1.5 rounded-lg text-xs font-bold hover:opacity-90">Continue</button>
                                    </td>
                                </tr>
                                <tr class="hover:bg-slate-50 dark:hover:bg-slate-900/50 transition-colors">
                                    <td class="px-6 py-4 flex items-center gap-3">
                                        <div class="w-8 h-8 rounded-full bg-blue-100 text-blue-600 flex items-center justify-center font-bold">MK</div>
                                        <span class="font-bold text-slate-900 dark:text-white">Mike Kels</span>
                                    </td>
                                    <td class="px-6 py-4 text-slate-600 dark:text-slate-400">Backend Dev</td>
                                    <td class="px-6 py-4"><span class="bg-brand-100 text-brand-700 px-2 py-1 rounded-full text-xs font-bold">Offer Issued</span></td>
                                    <td class="px-6 py-4 text-slate-600 dark:text-slate-400">Admin</td>
                                    <td class="px-6 py-4 text-right">
                                        <button class="text-slate-500 hover:text-brand-600 font-medium text-xs">Remind</button>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>

                </div>
            </main>
        </div>
    <!-- Wrapper closing div removed -->


    <!-- Re-use the same script as other pages -->
    <?php include '../includes/dashboard_scripts.php'; ?>
</body>
</html>
