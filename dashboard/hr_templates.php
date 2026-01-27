<?php
require_once '../includes/functions.php';
require_login();
$current_page = 'hr'; 
$current_tab = 'templates'; 
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Templates - HR Management</title>
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
<body class="bg-slate-50 dark:bg-slate-900 text-slate-900 dark:text-slate-100 transition-colors duration-300 overflow-hidden" x-data="{}">
    <div class="flex h-screen w-full">
        <!-- Sidebar -->
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
        
        <!-- Overlay -->
        <div id="overlay" class="fixed inset-0 bg-black/50 z-30 hidden"></div>

        <!-- Main Content -->
        <div class="flex-1 flex flex-col h-full overflow-hidden w-full relative">
            
            <!-- Global Header -->
            <?php $page_title = 'HR Management'; include '../includes/dashboard_header.php'; ?>

            <!-- Collapsed Toolbar -->
            <div id="collapsed-toolbar" class="toolbar-hidden bg-white dark:bg-slate-950 border-b border-slate-200 dark:border-slate-800 flex items-center px-6 shrink-0 z-20">
                <button id="sidebar-expand-btn" class="flex items-center gap-2 p-2 rounded-lg text-slate-500 hover:text-brand-600 dark:text-slate-400 dark:hover:text-white hover:bg-slate-100 dark:hover:bg-slate-800 transition-colors my-1">
                    <i data-lucide="menu" class="w-5 h-5"></i> <span class="text-sm font-medium">Show Menu</span>
                </button>
            </div>

            <!-- Page Content -->
            <main class="flex-1 overflow-y-auto bg-slate-50 dark:bg-slate-900 flex flex-col">
                <?php include '../includes/hr_header.php'; ?>

                <!-- Detailed Content -->
                <div class="p-6 lg:p-8 flex-1">
                    
                    <!-- ACTION BAR -->
                    <div class="flex justify-between items-center mb-6">
                        <h3 class="text-lg font-bold text-slate-900 dark:text-white">Document Templates</h3>
                        <button class="bg-indigo-600 hover:bg-indigo-700 text-white px-4 py-2 rounded-lg text-sm font-bold flex items-center gap-2">
                            <i data-lucide="plus" class="w-4 h-4"></i> Create Template
                        </button>
                    </div>

                    <!-- TEMPLATES GRID/LIST -->
                    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
                        
                        <!-- Template Card -->
                        <div class="bg-white dark:bg-slate-950 p-5 rounded-xl border border-slate-200 dark:border-slate-800 hover:border-brand-500 dark:hover:border-brand-500 transition-all group">
                            <div class="flex items-start justify-between mb-4">
                                <div class="w-10 h-10 rounded-lg bg-pink-100 dark:bg-pink-900/20 flex items-center justify-center text-pink-600 dark:text-pink-400">
                                    <i data-lucide="file-check" class="w-5 h-5"></i>
                                </div>
                                <div class="flex gap-2 opacity-0 group-hover:opacity-100 transition-opacity">
                                    <button class="p-1 hover:text-brand-600 text-slate-400"><i data-lucide="edit-2" class="w-4 h-4"></i></button>
                                </div>
                            </div>
                            <h4 class="font-bold text-slate-900 dark:text-white mb-1">Standard Offer Letter</h4>
                            <p class="text-xs text-slate-500 mb-4">Category: Recruitment</p>
                            <div class="flex items-center justify-between text-xs text-slate-400 border-t border-slate-100 dark:border-slate-800 pt-4">
                                <span>Updated: 2h ago</span>
                                <span class="bg-green-100 text-green-700 px-2 py-0.5 rounded-full font-bold">Active</span>
                            </div>
                        </div>

                        <!-- Template Card -->
                        <div class="bg-white dark:bg-slate-950 p-5 rounded-xl border border-slate-200 dark:border-slate-800 hover:border-brand-500 dark:hover:border-brand-500 transition-all group">
                            <div class="flex items-start justify-between mb-4">
                                <div class="w-10 h-10 rounded-lg bg-purple-100 dark:bg-purple-900/20 flex items-center justify-center text-purple-600 dark:text-purple-400">
                                    <i data-lucide="file-warning" class="w-5 h-5"></i>
                                </div>
                                <div class="flex gap-2 opacity-0 group-hover:opacity-100 transition-opacity">
                                    <button class="p-1 hover:text-brand-600 text-slate-400"><i data-lucide="edit-2" class="w-4 h-4"></i></button>
                                </div>
                            </div>
                            <h4 class="font-bold text-slate-900 dark:text-white mb-1">First Warning Notice</h4>
                            <p class="text-xs text-slate-500 mb-4">Category: Employee Relations</p>
                            <div class="flex items-center justify-between text-xs text-slate-400 border-t border-slate-100 dark:border-slate-800 pt-4">
                                <span>Updated: 1d ago</span>
                                <span class="bg-green-100 text-green-700 px-2 py-0.5 rounded-full font-bold">Active</span>
                            </div>
                        </div>

                         <!-- Template Card -->
                        <div class="bg-white dark:bg-slate-950 p-5 rounded-xl border border-slate-200 dark:border-slate-800 hover:border-brand-500 dark:hover:border-brand-500 transition-all group">
                            <div class="flex items-start justify-between mb-4">
                                <div class="w-10 h-10 rounded-lg bg-blue-100 dark:bg-blue-900/20 flex items-center justify-center text-blue-600 dark:text-blue-400">
                                    <i data-lucide="clipboard-list" class="w-5 h-5"></i>
                                </div>
                                <div class="flex gap-2 opacity-0 group-hover:opacity-100 transition-opacity">
                                    <button class="p-1 hover:text-brand-600 text-slate-400"><i data-lucide="edit-2" class="w-4 h-4"></i></button>
                                </div>
                            </div>
                            <h4 class="font-bold text-slate-900 dark:text-white mb-1">Performance Review Form</h4>
                            <p class="text-xs text-slate-500 mb-4">Category: Evaluation</p>
                            <div class="flex items-center justify-between text-xs text-slate-400 border-t border-slate-100 dark:border-slate-800 pt-4">
                                <span>Updated: 5d ago</span>
                                <span class="bg-slate-100 text-slate-700 px-2 py-0.5 rounded-full font-bold">Draft</span>
                            </div>
                        </div>

                    </div>

                </div>
            </main>
        </div>
    </div>

    <!-- Script Block -->
    <?php include '../includes/dashboard_scripts.php'; ?>
</body>
</html>
