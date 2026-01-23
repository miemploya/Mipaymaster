<?php
require_once '../includes/functions.php';
require_login();
$current_page = 'support';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Support - Mipaymaster</title>
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
    
    <style type="text/tailwindcss">
        @import url('https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap');
        body { font-family: 'Inter', sans-serif; }
        
        ::-webkit-scrollbar { width: 6px; height: 6px; }
        ::-webkit-scrollbar-track { background: transparent; }
        ::-webkit-scrollbar-thumb { background: #cbd5e1; border-radius: 3px; }
        .dark ::-webkit-scrollbar-thumb { background: #334155; }
        
        [x-cloak] { display: none !important; }
        
        .sidebar-transition { transition: width 0.3s ease-in-out, transform 0.3s ease-in-out, padding 0.3s; }
        #sidebar.w-0 { overflow: hidden; }

        /* Nav Pills */
        .nav-pill { @apply px-4 py-2 text-sm font-semibold rounded-lg transition-all duration-200 flex items-center gap-2 whitespace-nowrap; }
        .nav-active { @apply bg-brand-600 text-white shadow-md shadow-brand-500/20; }
        .nav-inactive { @apply bg-white dark:bg-slate-800 text-slate-600 dark:text-slate-400 hover:bg-slate-100 dark:hover:bg-slate-700 border border-slate-200 dark:border-slate-700; }
        
        /* Form Inputs */
        .form-input { @apply w-full rounded-lg border-slate-300 dark:border-slate-700 bg-white dark:bg-slate-800 text-slate-900 dark:text-white focus:ring-brand-500 focus:border-brand-500 transition-colors shadow-sm text-sm; }
        .form-label { @apply block text-xs font-bold text-slate-500 dark:text-slate-400 uppercase tracking-wide mb-1; }

        /* Support Cards */
        .support-card { @apply bg-white dark:bg-slate-950 p-6 rounded-xl border border-slate-200 dark:border-slate-800 hover:border-brand-500 dark:hover:border-brand-500 transition-all shadow-sm hover:shadow-md; }
    </style>
    
    <script>
        function supportApp() {
            return {
                view: 'dashboard', // dashboard, create, tickets, payroll_support, kb
                sidebarOpen: false,

                // Mock Tickets
                tickets: [
                    { id: '#TK-2045', subject: 'Payroll Calculation Error', category: 'Payroll Issue', priority: 'High', status: 'In Progress', updated: '2 hours ago' },
                    { id: '#TK-2044', subject: 'Add New Statutory Deduction', category: 'Statutory Compliance', priority: 'Medium', status: 'Open', updated: '1 day ago' },
                    { id: '#TK-2040', subject: 'Biometric Device Sync Failed', category: 'Technical Support', priority: 'High', status: 'Resolved', updated: '3 days ago' },
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
<body class="bg-slate-50 dark:bg-slate-900 text-slate-900 dark:text-slate-100 transition-colors duration-300 overflow-hidden flex h-screen" x-data="supportApp()">

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
                <p class="text-sm text-slate-500 p-4 text-center">No new notifications.</p>
            </div>
        </div>
        
        <!-- Overlay -->
        <div id="overlay" class="fixed inset-0 bg-black/50 z-30 hidden"></div>

        <!-- MAIN CONTENT -->
        <div class="flex-1 flex flex-col h-full overflow-hidden w-full relative">
            
            <!-- Header -->
            <header class="h-16 bg-white dark:bg-slate-950 border-b border-slate-200 dark:border-slate-800 flex items-center justify-between px-6 shrink-0 z-30">
                <div class="flex items-center gap-4">
                    <button id="mobile-sidebar-toggle" class="md:hidden text-slate-500"><i data-lucide="menu" class="w-6 h-6"></i></button>
                    <h2 class="text-xl font-bold text-slate-800 dark:text-white">Miemploya Support</h2>
                </div>
                <!-- Header Actions -->
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


            <!-- Collapsed Toolbar -->
            <div id="collapsed-toolbar" class="toolbar-hidden w-full bg-white dark:bg-slate-950 border-b border-slate-200 dark:border-slate-800 flex items-center px-6 shrink-0 shadow-sm z-20">
                <button id="sidebar-expand-btn" class="flex items-center gap-2 p-2 rounded-lg text-slate-500 hover:text-brand-600 dark:text-slate-400 dark:hover:text-white hover:bg-slate-100 dark:hover:bg-slate-800 transition-colors my-2">
                    <i data-lucide="menu" class="w-5 h-5"></i> <span class="text-sm font-medium">Show Menu</span>
                </button>
            </div>

            <!-- Main Scrollable Area -->
            <main class="flex-1 overflow-y-auto p-6 lg:p-8 bg-slate-50 dark:bg-slate-900 scroll-smooth">
                
                <!-- Internal Support Navigation -->
                <div class="mb-8 overflow-x-auto pb-2">
                    <div class="flex gap-3 min-w-max">
                        <button @click="changeView('dashboard')" :class="view === 'dashboard' ? 'nav-active' : 'nav-inactive'" class="nav-pill"><i data-lucide="layout-dashboard" class="w-4 h-4"></i> Support Dashboard</button>
                        <button @click="changeView('create')" :class="view === 'create' ? 'nav-active' : 'nav-inactive'" class="nav-pill"><i data-lucide="plus-circle" class="w-4 h-4"></i> Create Ticket</button>
                        <button @click="changeView('tickets')" :class="view === 'tickets' ? 'nav-active' : 'nav-inactive'" class="nav-pill"><i data-lucide="ticket" class="w-4 h-4"></i> My Tickets</button>
                        <button @click="changeView('payroll_support')" :class="view === 'payroll_support' ? 'nav-active' : 'nav-inactive'" class="nav-pill"><i data-lucide="star" class="w-4 h-4"></i> Payroll Support</button>
                        <button @click="changeView('kb')" :class="view === 'kb' ? 'nav-active' : 'nav-inactive'" class="nav-pill"><i data-lucide="book-open" class="w-4 h-4"></i> Knowledge Base</button>
                    </div>
                </div>

                <!-- VIEW 1: SUPPORT DASHBOARD -->
                <div x-show="view === 'dashboard'" x-cloak x-transition.opacity>
                    <!-- Metrics -->
                    <div class="grid grid-cols-1 md:grid-cols-4 gap-6 mb-8">
                        <div class="support-card border-l-4 border-l-blue-500">
                            <p class="text-xs text-slate-500 uppercase font-bold">Open Tickets</p>
                            <h3 class="text-2xl font-bold text-slate-900 dark:text-white mt-2">2</h3>
                        </div>
                        <div class="support-card border-l-4 border-l-amber-500">
                            <p class="text-xs text-slate-500 uppercase font-bold">In Progress</p>
                            <h3 class="text-2xl font-bold text-slate-900 dark:text-white mt-2">1</h3>
                        </div>
                        <div class="support-card border-l-4 border-l-green-500">
                            <p class="text-xs text-slate-500 uppercase font-bold">Resolved</p>
                            <h3 class="text-2xl font-bold text-slate-900 dark:text-white mt-2">18</h3>
                        </div>
                         <div class="support-card border-l-4 border-l-purple-500">
                            <p class="text-xs text-slate-500 uppercase font-bold">SLA Status</p>
                            <h3 class="text-xl font-bold text-purple-600 dark:text-purple-400 mt-2 flex items-center gap-2"><i data-lucide="check-circle-2" class="w-5 h-5"></i> On Track</h3>
                        </div>
                    </div>

                    <div class="grid grid-cols-1 lg:grid-cols-3 gap-8">
                        <!-- Quick Actions -->
                        <div class="lg:col-span-2 grid grid-cols-1 sm:grid-cols-3 gap-4">
                            <button @click="changeView('create')" class="p-6 bg-brand-600 text-white rounded-xl shadow-lg hover:bg-brand-700 transition-all flex flex-col items-center justify-center gap-3">
                                <i data-lucide="message-square-plus" class="w-8 h-8"></i>
                                <span class="font-bold">Create New Ticket</span>
                            </button>
                            <button @click="changeView('payroll_support')" class="p-6 bg-white dark:bg-slate-950 border border-slate-200 dark:border-slate-800 rounded-xl hover:border-brand-500 transition-colors flex flex-col items-center justify-center gap-3 group">
                                <i data-lucide="headset" class="w-8 h-8 text-slate-400 group-hover:text-brand-600"></i>
                                <span class="font-bold text-slate-700 dark:text-slate-300 group-hover:text-brand-600">Contact Payroll Expert</span>
                            </button>
                            <button @click="changeView('kb')" class="p-6 bg-white dark:bg-slate-950 border border-slate-200 dark:border-slate-800 rounded-xl hover:border-brand-500 transition-colors flex flex-col items-center justify-center gap-3 group">
                                <i data-lucide="book" class="w-8 h-8 text-slate-400 group-hover:text-brand-600"></i>
                                <span class="font-bold text-slate-700 dark:text-slate-300 group-hover:text-brand-600">Knowledge Base</span>
                            </button>
                        </div>

                        <!-- Recent Activity -->
                        <div class="bg-white dark:bg-slate-950 rounded-xl border border-slate-200 dark:border-slate-800 p-6">
                            <h3 class="font-bold text-slate-900 dark:text-white mb-4">Recent Activity</h3>
                            <div class="space-y-4">
                                <div class="flex gap-3">
                                    <div class="w-2 h-2 mt-2 rounded-full bg-blue-500 flex-shrink-0"></div>
                                    <div>
                                        <p class="text-sm font-medium text-slate-900 dark:text-white">Ticket #TK-2045 updated</p>
                                        <p class="text-xs text-slate-500">Support Agent replied • 2 hours ago</p>
                                    </div>
                                </div>
                                <div class="flex gap-3">
                                    <div class="w-2 h-2 mt-2 rounded-full bg-green-500 flex-shrink-0"></div>
                                    <div>
                                        <p class="text-sm font-medium text-slate-900 dark:text-white">Ticket #TK-2040 resolved</p>
                                        <p class="text-xs text-slate-500">Closed by System • 3 days ago</p>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- VIEW 2: CREATE TICKET -->
                <div x-show="view === 'create'" x-cloak x-transition.opacity class="max-w-3xl mx-auto">
                    <div class="bg-white dark:bg-slate-950 rounded-xl border border-slate-200 dark:border-slate-800 p-8 shadow-sm">
                        <h2 class="text-xl font-bold text-slate-900 dark:text-white mb-6">Submit a Support Ticket</h2>
                        
                        <div class="space-y-6">
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                                <div>
                                    <label class="form-label">Category</label>
                                    <select class="form-input">
                                        <option>Payroll Issue</option>
                                        <option>Attendance Issue</option>
                                        <option>Statutory Compliance</option>
                                        <option>Technical Support</option>
                                    </select>
                                </div>
                                <div>
                                    <label class="form-label">Priority</label>
                                    <select class="form-input">
                                        <option>Low</option>
                                        <option>Medium</option>
                                        <option>High</option>
                                    </select>
                                </div>
                            </div>

                            <div>
                                <label class="form-label">Subject</label>
                                <input type="text" class="form-input" placeholder="Brief summary of the issue...">
                            </div>

                            <div>
                                <label class="form-label">Description</label>
                                <textarea rows="5" class="form-input" placeholder="Detailed explanation..."></textarea>
                            </div>

                            <div>
                                <label class="form-label">Attachments</label>
                                <div class="border-2 border-dashed border-slate-300 dark:border-slate-700 rounded-lg p-6 text-center hover:bg-slate-50 dark:hover:bg-slate-900 cursor-pointer transition-colors">
                                    <i data-lucide="upload-cloud" class="w-8 h-8 text-slate-400 mx-auto mb-2"></i>
                                    <p class="text-sm text-slate-500">Click to upload screenshots or documents</p>
                                </div>
                            </div>

                            <div class="flex justify-end gap-4 pt-4 border-t border-slate-100 dark:border-slate-800">
                                <button @click="changeView('dashboard')" class="px-6 py-2 border border-slate-300 dark:border-slate-600 rounded-lg text-slate-600 dark:text-slate-300 font-medium hover:bg-slate-50 dark:hover:bg-slate-800">Cancel</button>
                                <button class="px-6 py-2 bg-brand-600 text-white rounded-lg font-bold shadow-md hover:bg-brand-700">Submit Ticket</button>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- VIEW 3: MY TICKETS -->
                <div x-show="view === 'tickets'" x-cloak x-transition.opacity>
                    <div class="bg-white dark:bg-slate-950 rounded-xl border border-slate-200 dark:border-slate-800 overflow-hidden shadow-sm">
                        <table class="w-full text-left text-sm">
                            <thead class="bg-slate-100 dark:bg-slate-900 border-b border-slate-200 dark:border-slate-800 text-slate-500">
                                <tr>
                                    <th class="p-4 font-medium">Ticket ID</th>
                                    <th class="p-4 font-medium">Subject</th>
                                    <th class="p-4 font-medium">Category</th>
                                    <th class="p-4 font-medium">Priority</th>
                                    <th class="p-4 font-medium">Status</th>
                                    <th class="p-4 font-medium">Updated</th>
                                    <th class="p-4 text-center font-medium">Action</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
                                <template x-for="ticket in tickets" :key="ticket.id">
                                    <tr class="hover:bg-slate-50 dark:hover:bg-slate-900/50 transition-colors">
                                        <td class="p-4 font-mono text-slate-500" x-text="ticket.id"></td>
                                        <td class="p-4 font-medium text-slate-900 dark:text-white" x-text="ticket.subject"></td>
                                        <td class="p-4 text-slate-600 dark:text-slate-400" x-text="ticket.category"></td>
                                        <td class="p-4">
                                            <span :class="{'text-red-600 bg-red-50 dark:bg-red-900/20': ticket.priority === 'High', 'text-amber-600 bg-amber-50 dark:bg-amber-900/20': ticket.priority === 'Medium'}" class="px-2 py-1 rounded text-xs font-bold" x-text="ticket.priority"></span>
                                        </td>
                                        <td class="p-4">
                                            <span :class="{'text-blue-600 bg-blue-50 dark:bg-blue-900/20': ticket.status === 'In Progress', 'text-green-600 bg-green-50 dark:bg-green-900/20': ticket.status === 'Resolved'}" class="px-2 py-1 rounded text-xs font-bold" x-text="ticket.status"></span>
                                        </td>
                                        <td class="p-4 text-slate-500" x-text="ticket.updated"></td>
                                        <td class="p-4 text-center">
                                            <button class="text-brand-600 hover:underline font-medium text-xs">View</button>
                                        </td>
                                    </tr>
                                </template>
                            </tbody>
                        </table>
                    </div>
                </div>

                <!-- VIEW 4: PAYROLL SUPPORT (PREMIUM) -->
                <div x-show="view === 'payroll_support'" x-cloak x-transition.opacity>
                    <div class="bg-gradient-to-r from-brand-900 to-brand-700 rounded-xl p-8 text-white mb-8 shadow-xl relative overflow-hidden">
                        <div class="absolute top-0 right-0 w-64 h-64 bg-white/10 rounded-full blur-3xl -translate-y-1/2 translate-x-1/4"></div>
                        <div class="relative z-10">
                            <div class="flex items-center gap-3 mb-4">
                                <div class="p-2 bg-white/20 rounded-lg backdrop-blur-sm"><i data-lucide="star" class="w-6 h-6 text-yellow-300"></i></div>
                                <h2 class="text-2xl font-bold">Premium Payroll Services</h2>
                            </div>
                            <p class="max-w-2xl text-brand-100 mb-6">Access our team of expert payroll accountants for compliance reviews, tax filing assistance, and full-service payroll processing.</p>
                            <div class="flex flex-wrap gap-3">
                                <span class="px-3 py-1 bg-white/10 rounded-full text-xs font-medium border border-white/20">PAYE Filing</span>
                                <span class="px-3 py-1 bg-white/10 rounded-full text-xs font-medium border border-white/20">Audit Support</span>
                                <span class="px-3 py-1 bg-white/10 rounded-full text-xs font-medium border border-white/20">Advisory</span>
                            </div>
                        </div>
                    </div>

                    <div class="grid grid-cols-1 md:grid-cols-2 gap-8">
                        <div class="bg-white dark:bg-slate-950 rounded-xl border border-slate-200 dark:border-slate-800 p-6">
                            <h3 class="font-bold text-slate-900 dark:text-white mb-4">Request Service</h3>
                            <div class="space-y-4">
                                <div>
                                    <label class="form-label">Service Type</label>
                                    <select class="form-input">
                                        <option>Request Payroll Processing</option>
                                        <option>Compliance Review</option>
                                        <option>Consultation Call</option>
                                    </select>
                                </div>
                                <div>
                                    <label class="form-label">Payroll Period</label>
                                    <input type="month" class="form-input">
                                </div>
                                <div>
                                    <label class="form-label">Notes / Instructions</label>
                                    <textarea class="form-input" rows="3"></textarea>
                                </div>
                                <button class="w-full py-3 bg-brand-600 text-white rounded-lg font-bold hover:bg-brand-700 shadow-md">Submit Request</button>
                            </div>
                        </div>

                        <div class="bg-white dark:bg-slate-950 rounded-xl border border-slate-200 dark:border-slate-800 p-6">
                            <h3 class="font-bold text-slate-900 dark:text-white mb-4">Request History</h3>
                            <div class="space-y-4">
                                <div class="p-3 bg-slate-50 dark:bg-slate-900 rounded-lg border border-slate-100 dark:border-slate-800 flex justify-between items-center">
                                    <div>
                                        <p class="text-sm font-bold text-slate-800 dark:text-white">Compliance Review</p>
                                        <p class="text-xs text-slate-500">Dec 2025 Audit</p>
                                    </div>
                                    <span class="px-2 py-1 bg-green-100 text-green-700 text-xs rounded font-bold">Completed</span>
                                </div>
                                <div class="p-3 bg-slate-50 dark:bg-slate-900 rounded-lg border border-slate-100 dark:border-slate-800 flex justify-between items-center">
                                    <div>
                                        <p class="text-sm font-bold text-slate-800 dark:text-white">Payroll Processing</p>
                                        <p class="text-xs text-slate-500">Nov 2025 Run</p>
                                    </div>
                                    <span class="px-2 py-1 bg-green-100 text-green-700 text-xs rounded font-bold">Completed</span>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- VIEW 5: KNOWLEDGE BASE -->
                <div x-show="view === 'kb'" x-cloak x-transition.opacity>
                    <div class="max-w-2xl mx-auto text-center mb-10">
                        <h2 class="text-2xl font-bold text-slate-900 dark:text-white mb-4">How can we help you?</h2>
                        <div class="relative">
                            <i data-lucide="search" class="absolute left-4 top-3.5 w-5 h-5 text-slate-400"></i>
                            <input type="text" placeholder="Search for articles, guides, and troubleshooting..." class="w-full pl-12 pr-4 py-3 rounded-xl border border-slate-300 dark:border-slate-700 bg-white dark:bg-slate-800 text-slate-900 dark:text-white focus:ring-2 focus:ring-brand-500 shadow-sm">
                        </div>
                    </div>

                    <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                        <div class="support-card hover:translate-y-[-2px] transition-transform">
                            <div class="w-10 h-10 rounded-lg bg-blue-100 dark:bg-blue-900/20 text-blue-600 flex items-center justify-center mb-4"><i data-lucide="rocket" class="w-5 h-5"></i></div>
                            <h4 class="font-bold text-slate-900 dark:text-white mb-2">Getting Started</h4>
                            <p class="text-sm text-slate-500">Setup your account, company profile, and first employee.</p>
                        </div>
                        <div class="support-card hover:translate-y-[-2px] transition-transform">
                            <div class="w-10 h-10 rounded-lg bg-green-100 dark:bg-green-900/20 text-green-600 flex items-center justify-center mb-4"><i data-lucide="banknote" class="w-5 h-5"></i></div>
                            <h4 class="font-bold text-slate-900 dark:text-white mb-2">Payroll & Tax</h4>
                            <p class="text-sm text-slate-500">Understanding tax laws, running payroll, and generating reports.</p>
                        </div>
                        <div class="support-card hover:translate-y-[-2px] transition-transform">
                            <div class="w-10 h-10 rounded-lg bg-purple-100 dark:bg-purple-900/20 text-purple-600 flex items-center justify-center mb-4"><i data-lucide="fingerprint" class="w-5 h-5"></i></div>
                            <h4 class="font-bold text-slate-900 dark:text-white mb-2">Attendance</h4>
                            <p class="text-sm text-slate-500">Connecting biometric devices and managing shifts.</p>
                        </div>
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

        // Sidebar Logic
        const mobileToggle = document.getElementById('mobile-sidebar-toggle');
        const sidebar = document.getElementById('sidebar');
        const collapsedToolbar = document.getElementById('collapsed-toolbar');
        const desktopCollapseBtn = document.getElementById('sidebar-collapse-btn');
        const sidebarExpandBarBtn = document.getElementById('sidebar-expand-bar-btn');

        // Combined Toggle Logic for System Sidebar
        if(mobileToggle && sidebar) {
            mobileToggle.addEventListener('click', () => {
                sidebar.classList.toggle('-translate-x-full');
            });
        }
        
        // Notifications Logic (System Standard)
        const notifToggle = document.getElementById('notif-toggle');
        const notifClose = document.getElementById('notif-close');
        const notifPanel = document.getElementById('notif-panel');
        const overlay = document.getElementById('overlay');

        function toggleOverlay(show) {
            if(overlay) {
                if (show) overlay.classList.remove('hidden');
                else overlay.classList.add('hidden');
            }
        }

        if(notifToggle && notifPanel) {
            notifToggle.addEventListener('click', () => {
                notifPanel.classList.remove('translate-x-full');
                toggleOverlay(true);
            });
        }

        if(notifClose && notifPanel) {
            notifClose.addEventListener('click', () => {
                notifPanel.classList.add('translate-x-full');
                toggleOverlay(false);
            });
        }

         if(overlay && notifPanel) {
            overlay.addEventListener('click', () => {
                notifPanel.classList.add('translate-x-full');
                if(sidebar) sidebar.classList.add('-translate-x-full'); 
                toggleOverlay(false);
            });
        }

        // Collapse Logic (System Standard)
        function toggleSidebar() {
            if(!sidebar) return;
            sidebar.classList.toggle('w-64');
            sidebar.classList.toggle('w-0');
            sidebar.classList.toggle('p-0'); 
            if (sidebar.classList.contains('w-0')) {
                if(collapsedToolbar) { collapsedToolbar.style.display = 'flex'; collapsedToolbar.classList.remove('toolbar-hidden'); }
            } else {
                if(collapsedToolbar) { collapsedToolbar.style.display = 'none'; collapsedToolbar.classList.add('toolbar-hidden'); }
            }
        }
        if(desktopCollapseBtn) desktopCollapseBtn.addEventListener('click', toggleSidebar);
        const sidebarExpandBtn = document.getElementById('sidebar-expand-btn');
        if(sidebarExpandBtn) sidebarExpandBtn.addEventListener('click', toggleSidebar);

    </script>
</body>
</html>
