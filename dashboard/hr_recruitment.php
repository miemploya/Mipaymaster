<?php
require_once '../includes/functions.php';
require_login();
$current_page = 'hr'; // For sidebar active state
$current_tab = 'recruitment'; // For tab active state
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Recruitment - HR Management</title>
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
<body class="bg-slate-50 dark:bg-slate-900 text-slate-900 dark:text-slate-100 transition-colors duration-300 overflow-hidden flex h-screen" x-data="{ 
    view: 'jobs', // 'jobs' or 'applicants'
    showCreateModal: false
}">
    <!-- Wrapper removed -->

        <!-- Sidebar -->
        <?php include '../includes/dashboard_sidebar.php'; ?>

        <!-- NOTIFICATIONS PANEL (SLIDE-OVER) -->
        <div id="notif-panel" class="fixed inset-y-0 right-0 w-80 bg-white dark:bg-slate-950 shadow-2xl transform translate-x-full transition-transform duration-300 z-50 border-l border-slate-200 dark:border-slate-800">
            <div class="h-16 flex items-center justify-between px-6 border-b border-slate-100 dark:border-slate-800">
                <h3 class="text-lg font-bold text-slate-900 dark:text-white">Notifications</h3>
                <button id="notif-close" class="text-slate-500 hover:text-slate-900 dark:hover:text-white"><i data-lucide="x" class="w-5 h-5"></i></button>
            </div>
            <div class="p-4 space-y-4 overflow-y-auto h-[calc(100vh-64px)]">
                <!-- Mock Notifications -->
                <div class="p-3 bg-brand-50 dark:bg-brand-900/10 rounded-lg border-l-4 border-brand-500">
                    <p class="text-sm font-bold text-slate-900 dark:text-white mb-1">New Applicant</p>
                    <p class="text-xs text-slate-600 dark:text-slate-400">John Doe applied for Senior Developer.</p>
                    <div class="mt-2 flex gap-2"><button class="text-xs text-brand-600 font-medium hover:underline">View</button></div>
                </div>
            </div>
        </div>
        
        <!-- Overlay -->
        <div id="overlay" class="fixed inset-0 bg-black/50 z-30 hidden"></div>

        <!-- Main Content -->
        <div class="flex-1 flex flex-col h-full overflow-hidden w-full relative">
            
            <!-- Global Header -->
            <header class="h-16 bg-white dark:bg-slate-950 border-b border-slate-200 dark:border-slate-800 flex items-center justify-between px-6 shrink-0 z-30">
                <div class="flex items-center gap-4">
                    <button id="mobile-sidebar-toggle" class="md:hidden text-slate-500 hover:text-slate-700 dark:hover:text-slate-300">
                        <i data-lucide="menu" class="w-6 h-6"></i>
                    </button>
                    <h2 class="text-xl font-bold text-slate-800 dark:text-white">HR Management</h2>
                </div>
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
                        </button>
                        <div x-show="open" @click.outside="open = false" class="absolute right-0 mt-2 w-48 bg-white dark:bg-slate-800 rounded-lg shadow-lg border border-slate-100 dark:border-slate-700 py-1 z-50 mr-4" style="display: none;">
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

            <!-- Page Content -->
            <main class="flex-1 overflow-y-auto bg-slate-50 dark:bg-slate-900 flex flex-col">
                <!-- Shared HR Header with Tabs -->
                <?php include '../includes/hr_header.php'; ?>

                <!-- Detailed Content -->
                <div class="p-6 lg:p-8 flex-1">
                    
                    <!-- ACTION BAR -->
                    <div class="flex flex-col md:flex-row justify-between items-start md:items-center gap-4 mb-6">
                        <div class="flex items-center gap-2 w-full md:w-auto">
                            <!-- Filter: Status -->
                            <select class="bg-white dark:bg-slate-950 border border-slate-200 dark:border-slate-800 rounded-lg text-sm px-3 py-2 focus:ring-brand-500 focus:border-brand-500">
                                <option>All Status</option>
                                <option>Open</option>
                                <option>Closed</option>
                            </select>
                            <!-- Filter: Department -->
                            <select class="bg-white dark:bg-slate-950 border border-slate-200 dark:border-slate-800 rounded-lg text-sm px-3 py-2 focus:ring-brand-500 focus:border-brand-500">
                                <option>All Departments</option>
                                <option>Engineering</option>
                                <option>Sales</option>
                            </select>
                        </div>
                        <div class="flex items-center gap-2 w-full md:w-auto">
                            <div class="relative w-full md:w-64">
                                <i data-lucide="search" class="absolute left-3 top-1/2 -translate-y-1/2 w-4 h-4 text-slate-400"></i>
                                <input type="text" placeholder="Search jobs..." class="w-full pl-9 pr-4 py-2 bg-white dark:bg-slate-950 border border-slate-200 dark:border-slate-800 rounded-lg text-sm focus:ring-brand-500 focus:border-brand-500">
                            </div>
                            <button @click="showCreateModal = true" class="bg-slate-900 dark:bg-white text-white dark:text-slate-900 px-4 py-2 rounded-lg text-sm font-bold flex items-center gap-2 hover:opacity-90">
                                <i data-lucide="plus" class="w-4 h-4"></i> Create Job
                            </button>
                        </div>
                    </div>

                    <!-- JOB LISTING TABLE -->
                    <div class="bg-white dark:bg-slate-950 rounded-xl border border-slate-200 dark:border-slate-800 overflow-hidden shadow-sm">
                        <table class="w-full text-left text-sm">
                            <thead class="bg-slate-50 dark:bg-slate-900 border-b border-slate-200 dark:border-slate-800 text-slate-500 dark:text-slate-400">
                                <tr>
                                    <th class="px-6 py-4 font-medium">Job Title</th>
                                    <th class="px-6 py-4 font-medium">Department</th>
                                    <th class="px-6 py-4 font-medium">Type</th>
                                    <th class="px-6 py-4 font-medium text-center">Applicants</th>
                                    <th class="px-6 py-4 font-medium text-center">Status</th>
                                    <th class="px-6 py-4 font-medium text-right">Actions</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
                                <!-- Row 1 -->
                                <tr class="hover:bg-slate-50 dark:hover:bg-slate-900/50 transition-colors">
                                    <td class="px-6 py-4 font-bold text-slate-900 dark:text-white">Senior Frontend Developer</td>
                                    <td class="px-6 py-4 text-slate-600 dark:text-slate-400">Engineering</td>
                                    <td class="px-6 py-4 text-slate-600 dark:text-slate-400">Full Time</td>
                                    <td class="px-6 py-4 text-center"><span class="bg-slate-100 dark:bg-slate-800 px-2 py-1 rounded text-xs font-bold">12</span></td>
                                    <td class="px-6 py-4 text-center"><span class="bg-green-100 text-green-700 px-2 py-1 rounded-full text-xs font-bold">Open</span></td>
                                    <td class="px-6 py-4 text-right flex justify-end gap-2">
                                        <button class="text-slate-400 hover:text-brand-600"><i data-lucide="eye" class="w-4 h-4"></i></button>
                                        <button class="text-slate-400 hover:text-brand-600"><i data-lucide="edit-2" class="w-4 h-4"></i></button>
                                        <button class="text-slate-400 hover:text-red-600"><i data-lucide="archive" class="w-4 h-4"></i></button>
                                    </td>
                                </tr>
                                <!-- Row 2 -->
                                <tr class="hover:bg-slate-50 dark:hover:bg-slate-900/50 transition-colors">
                                    <td class="px-6 py-4 font-bold text-slate-900 dark:text-white">Marketing Specialist</td>
                                    <td class="px-6 py-4 text-slate-600 dark:text-slate-400">Marketing</td>
                                    <td class="px-6 py-4 text-slate-600 dark:text-slate-400">Contract</td>
                                    <td class="px-6 py-4 text-center"><span class="bg-slate-100 dark:bg-slate-800 px-2 py-1 rounded text-xs font-bold">5</span></td>
                                    <td class="px-6 py-4 text-center"><span class="bg-green-100 text-green-700 px-2 py-1 rounded-full text-xs font-bold">Open</span></td>
                                    <td class="px-6 py-4 text-right flex justify-end gap-2">
                                        <button class="text-slate-400 hover:text-brand-600"><i data-lucide="eye" class="w-4 h-4"></i></button>
                                        <button class="text-slate-400 hover:text-brand-600"><i data-lucide="edit-2" class="w-4 h-4"></i></button>
                                        <button class="text-slate-400 hover:text-red-600"><i data-lucide="archive" class="w-4 h-4"></i></button>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>

                </div>
            </main>
        </div>
    <!-- Wrapper closing div removed -->


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
        if(themeBtn) {
            themeBtn.addEventListener('click', () => {
                html.classList.toggle('dark');
                localStorage.theme = html.classList.contains('dark') ? 'dark' : 'light';
            });
        }

        // Notification Logic
        const notifToggle = document.getElementById('notif-toggle');
        const notifClose = document.getElementById('notif-close');
        const notifPanel = document.getElementById('notif-panel');
        const overlay = document.getElementById('overlay');
        const mobileToggle = document.getElementById('mobile-sidebar-toggle');
        const sidebar = document.getElementById('sidebar');
        const collapsedToolbar = document.getElementById('collapsed-toolbar');
        const desktopCollapseBtn = document.getElementById('sidebar-collapse-btn');
        const sidebarExpandBtn = document.getElementById('sidebar-expand-btn');
        const headerExpandBtn = document.getElementById('header-expand-btn');

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
        if(sidebarExpandBtn) sidebarExpandBtn.addEventListener('click', toggleSidebar);
        if(headerExpandBtn) headerExpandBtn.addEventListener('click', toggleSidebar);
    </script>
</body>
</html>
