<?php
require_once '../includes/functions.php';
require_login();

$company_id = $_SESSION['company_id'] ?? 0;
$current_page = 'dashboard';

// Fetch quick stats
$stmt = $pdo->prepare("SELECT COUNT(*) FROM employees WHERE company_id = ?");
$stmt->execute([$company_id]);
$employee_count = $stmt->fetchColumn();

// Fetch Company Data for Header
$stmt = $pdo->prepare("SELECT * FROM companies WHERE id = ?");
$stmt->execute([$company_id]);
$company = $stmt->fetch();
$company_name = $company['name'] ?? 'Company';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Employer Dashboard - Mipaymaster</title>
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
    <!-- Chart.js -->
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <!-- Alpine.js -->
    <script src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js"></script>
    
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap');
        body { font-family: 'Inter', sans-serif; }
        
        /* Custom Scrollbar */
        ::-webkit-scrollbar { width: 6px; height: 6px; }
        ::-webkit-scrollbar-track { background: transparent; }
        ::-webkit-scrollbar-thumb { background: #cbd5e1; border-radius: 3px; }
        .dark ::-webkit-scrollbar-thumb { background: #334155; }
        
        .sidebar-transition { transition: width 0.3s ease-in-out, transform 0.3s ease-in-out, padding 0.3s; }
        
        /* Hide scrollbar in sidebar when collapsing to prevent ugly scrollbars during transition */
        #sidebar.w-0 { overflow: hidden; }
        
        /* Toolbar transition */
        #collapsed-toolbar { transition: max-height 0.3s ease-in-out, opacity 0.3s ease-in-out; }
        .toolbar-hidden { max-height: 0; opacity: 0; pointer-events: none; overflow: hidden; }
        .toolbar-visible { max-height: 64px; opacity: 1; pointer-events: auto; }
    </style>
</head>
<body class="flex h-screen overflow-hidden bg-slate-50 dark:bg-slate-900 text-slate-900 dark:text-slate-100 transition-colors duration-300">

    <!-- A. LEFT SIDEBAR NAVIGATION -->
    <!-- Fixed on mobile, Relative on Desktop. Collapses to w-0 on Desktop. -->
    <?php $current_page = 'dashboard'; include '../includes/dashboard_sidebar.php'; ?>

    <!-- NOTIFICATIONS PANEL (SLIDE-OVER) - Starts off-screen -->
    <div id="notif-panel" class="fixed inset-y-0 right-0 w-80 bg-white dark:bg-slate-950 shadow-2xl transform translate-x-full transition-transform duration-300 z-50 border-l border-slate-200 dark:border-slate-800" style="visibility: hidden;">
        <div class="h-16 flex items-center justify-between px-6 border-b border-slate-100 dark:border-slate-800">
            <h3 class="text-lg font-bold text-slate-900 dark:text-white">Notifications</h3>
            <button id="notif-close" class="text-slate-500 hover:text-slate-900 dark:hover:text-white"><i data-lucide="x" class="w-5 h-5"></i></button>
        </div>
        <div class="p-4 space-y-4 overflow-y-auto h-[calc(100vh-64px)]">
            <div class="p-3 bg-brand-50 dark:bg-brand-900/10 rounded-lg border-l-4 border-brand-500">
                <p class="text-sm font-bold text-slate-900 dark:text-white mb-1">Payroll Completed</p>
                <p class="text-xs text-slate-600 dark:text-slate-400">January 2026 payroll has been processed successfully.</p>
                <div class="mt-2 flex gap-2">
                    <button class="text-xs text-brand-600 font-medium hover:underline">View</button>
                </div>
            </div>
            <div class="p-3 bg-white dark:bg-slate-900 rounded-lg border border-slate-100 dark:border-slate-800">
                <p class="text-sm font-bold text-slate-900 dark:text-white mb-1">Approval Required</p>
                <p class="text-xs text-slate-600 dark:text-slate-400">2 leave requests are pending your approval.</p>
                <div class="mt-2 flex gap-2">
                    <button class="text-xs text-brand-600 font-medium hover:underline">Review</button>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Overlay -->
    <div id="overlay" class="fixed inset-0 bg-black/50 z-30 hidden"></div>

    <!-- MAIN CONTENT WRAPPER -->
    <div class="flex-1 flex flex-col h-full overflow-hidden w-full relative">
        
    <!-- B. TOP HEADER BAR -->
    <?php $page_title = 'Employer Dashboard'; include '../includes/dashboard_header.php'; ?>

            <!-- MAIN CONTENT AREA -->
            <main class="flex-1 overflow-y-auto p-6 lg:p-8 relative scroll-smooth">
                
                <!-- 1. SUMMARY METRIC CARDS -->
                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6 mb-8">
                    <!-- Card 1 -->
                    <div class="bg-white dark:bg-slate-950 rounded-xl p-6 border border-slate-200 dark:border-slate-800 shadow-sm hover:shadow-md transition-shadow">
                        <div class="flex justify-between items-start mb-4">
                            <div class="p-2 bg-blue-50 dark:bg-blue-900/20 rounded-lg text-blue-600 dark:text-blue-400"><i data-lucide="users" class="w-5 h-5"></i></div>
                            <span class="flex items-center text-xs font-medium text-green-600 bg-green-50 dark:bg-green-900/20 px-2 py-1 rounded-full">+12%</span>
                        </div>
                        <p class="text-sm font-medium text-slate-500 dark:text-slate-400">Total Employees</p>
                        <h3 class="text-2xl font-bold text-slate-900 dark:text-white mt-1"><?php echo $employee_count; ?></h3>
                    </div>

                    <!-- Card 2 -->
                    <div class="bg-white dark:bg-slate-950 rounded-xl p-6 border border-slate-200 dark:border-slate-800 shadow-sm hover:shadow-md transition-shadow">
                        <div class="flex justify-between items-start mb-4">
                            <div class="p-2 bg-brand-50 dark:bg-brand-900/20 rounded-lg text-brand-600 dark:text-brand-400"><i data-lucide="banknote" class="w-5 h-5"></i></div>
                            <span class="text-xs font-medium text-slate-500 bg-slate-100 dark:bg-slate-800 px-2 py-1 rounded-full">Jan 2026</span>
                        </div>
                        <p class="text-sm font-medium text-slate-500 dark:text-slate-400">Monthly Payroll Cost</p>
                        <h3 class="text-2xl font-bold text-slate-900 dark:text-white mt-1">₦ 0.00</h3>
                    </div>

                    <!-- Card 3 -->
                    <div class="bg-white dark:bg-slate-950 rounded-xl p-6 border border-slate-200 dark:border-slate-800 shadow-sm hover:shadow-md transition-shadow">
                        <div class="flex justify-between items-start mb-4">
                            <div class="p-2 bg-purple-50 dark:bg-purple-900/20 rounded-lg text-purple-600 dark:text-purple-400"><i data-lucide="file-check" class="w-5 h-5"></i></div>
                            <span class="text-xs font-medium text-amber-600 bg-amber-50 dark:bg-amber-900/20 px-2 py-1 rounded-full">Due in 5 days</span>
                        </div>
                        <p class="text-sm font-medium text-slate-500 dark:text-slate-400">PAYE Due</p>
                        <h3 class="text-2xl font-bold text-slate-900 dark:text-white mt-1">₦ 0.00</h3>
                    </div>

                    <!-- Card 4 -->
                    <div class="bg-white dark:bg-slate-950 rounded-xl p-6 border border-slate-200 dark:border-slate-800 shadow-sm hover:shadow-md transition-shadow">
                        <div class="flex justify-between items-start mb-4">
                            <div class="p-2 bg-orange-50 dark:bg-orange-900/20 rounded-lg text-orange-600 dark:text-orange-400"><i data-lucide="clock" class="w-5 h-5"></i></div>
                            <a href="#" class="text-xs font-medium text-brand-600 dark:text-brand-400 hover:underline">View All</a>
                        </div>
                        <p class="text-sm font-medium text-slate-500 dark:text-slate-400">Pending Approvals</p>
                        <h3 class="text-2xl font-bold text-slate-900 dark:text-white mt-1">0</h3>
                    </div>
                </div>

                <!-- 2. PAYROLL ANALYTICS SECTION -->
                <div class="grid grid-cols-1 lg:grid-cols-3 gap-6 mb-8">
                    <!-- Main Chart -->
                    <div class="lg:col-span-2 bg-white dark:bg-slate-950 rounded-xl p-6 border border-slate-200 dark:border-slate-800 shadow-sm">
                        <div class="flex items-center justify-between mb-6">
                            <h3 class="text-lg font-bold text-slate-900 dark:text-white">Payroll Trend</h3>
                            <select class="text-xs border-slate-200 dark:border-slate-700 bg-transparent text-slate-500 rounded focus:ring-0">
                                <option>Last 6 Months</option>
                                <option>Last 12 Months</option>
                            </select>
                        </div>
                        <div class="h-64 w-full">
                            <canvas id="payrollChart"></canvas>
                        </div>
                    </div>

                    <!-- Breakdown -->
                    <div class="bg-white dark:bg-slate-950 rounded-xl p-6 border border-slate-200 dark:border-slate-800 shadow-sm flex flex-col justify-between">
                        <h3 class="text-lg font-bold text-slate-900 dark:text-white mb-4">Payroll Breakdown</h3>
                        <div class="space-y-6">
                            <div>
                                <div class="flex justify-between text-sm mb-1">
                                    <span class="text-slate-500 dark:text-slate-400">Basic Salary</span>
                                    <span class="font-medium text-slate-900 dark:text-white">65%</span>
                                </div>
                                <div class="w-full bg-slate-100 dark:bg-slate-800 rounded-full h-2">
                                    <div class="bg-brand-600 h-2 rounded-full" style="width: 65%"></div>
                                </div>
                            </div>
                            <div>
                                <div class="flex justify-between text-sm mb-1">
                                    <span class="text-slate-500 dark:text-slate-400">Allowances</span>
                                    <span class="font-medium text-slate-900 dark:text-white">25%</span>
                                </div>
                                <div class="w-full bg-slate-100 dark:bg-slate-800 rounded-full h-2">
                                    <div class="bg-purple-500 h-2 rounded-full" style="width: 25%"></div>
                                </div>
                            </div>
                            <div>
                                <div class="flex justify-between text-sm mb-1">
                                    <span class="text-slate-500 dark:text-slate-400">Deductions</span>
                                    <span class="font-medium text-slate-900 dark:text-white">10%</span>
                                </div>
                                <div class="w-full bg-slate-100 dark:bg-slate-800 rounded-full h-2">
                                    <div class="bg-red-500 h-2 rounded-full" style="width: 10%"></div>
                                </div>
                            </div>
                        </div>
                        <div class="mt-6 pt-6 border-t border-slate-100 dark:border-slate-800 text-center">
                            <p class="text-xs text-slate-400">Based on January 2026 Run</p>
                        </div>
                    </div>
                </div>

                <!-- 3. BOTTOM SECTION: ACTIONS, ACTIVITY, COMPLIANCE -->
                <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
                    <!-- Quick Actions -->
                    <div class="bg-white dark:bg-slate-950 rounded-xl p-6 border border-slate-200 dark:border-slate-800 shadow-sm">
                        <h3 class="text-lg font-bold text-slate-900 dark:text-white mb-4">Quick Actions</h3>
                        <div class="grid grid-cols-2 gap-3">
                            <button onclick="window.location.href='employees_add.php'" class="flex flex-col items-center justify-center p-4 rounded-lg bg-slate-50 dark:bg-slate-900 border border-slate-100 dark:border-slate-800 hover:border-brand-500 dark:hover:border-brand-500 transition-colors group">
                                <div class="w-10 h-10 rounded-full bg-blue-100 dark:bg-blue-900/30 text-blue-600 dark:text-blue-400 flex items-center justify-center mb-2 group-hover:scale-110 transition-transform">
                                    <i data-lucide="user-plus" class="w-5 h-5"></i>
                                </div>
                                <span class="text-xs font-medium text-slate-600 dark:text-slate-300">Add Employee</span>
                            </button>
                            <button onclick="window.location.href='payroll.php'" class="flex flex-col items-center justify-center p-4 rounded-lg bg-slate-50 dark:bg-slate-900 border border-slate-100 dark:border-slate-800 hover:border-brand-500 dark:hover:border-brand-500 transition-colors group">
                                <div class="w-10 h-10 rounded-full bg-brand-100 dark:bg-brand-900/30 text-brand-600 dark:text-brand-400 flex items-center justify-center mb-2 group-hover:scale-110 transition-transform">
                                    <i data-lucide="play" class="w-5 h-5"></i>
                                </div>
                                <span class="text-xs font-medium text-slate-600 dark:text-slate-300">Run Payroll</span>
                            </button>
                            <button class="flex flex-col items-center justify-center p-4 rounded-lg bg-slate-50 dark:bg-slate-900 border border-slate-100 dark:border-slate-800 hover:border-brand-500 dark:hover:border-brand-500 transition-colors group">
                                <div class="w-10 h-10 rounded-full bg-green-100 dark:bg-green-900/30 text-green-600 dark:text-green-400 flex items-center justify-center mb-2 group-hover:scale-110 transition-transform">
                                    <i data-lucide="file-text" class="w-5 h-5"></i>
                                </div>
                                <span class="text-xs font-medium text-slate-600 dark:text-slate-300">View Payslips</span>
                            </button>
                            <button class="flex flex-col items-center justify-center p-4 rounded-lg bg-slate-50 dark:bg-slate-900 border border-slate-100 dark:border-slate-800 hover:border-brand-500 dark:hover:border-brand-500 transition-colors group">
                                <div class="w-10 h-10 rounded-full bg-orange-100 dark:bg-orange-900/30 text-orange-600 dark:text-orange-400 flex items-center justify-center mb-2 group-hover:scale-110 transition-transform">
                                    <i data-lucide="upload-cloud" class="w-5 h-5"></i>
                                </div>
                                <span class="text-xs font-medium text-slate-600 dark:text-slate-300">Upload CSV</span>
                            </button>
                        </div>
                    </div>

                    <!-- Activity Feed -->
                    <div class="bg-white dark:bg-slate-950 rounded-xl p-6 border border-slate-200 dark:border-slate-800 shadow-sm">
                        <div class="flex items-center justify-between mb-4">
                            <h3 class="text-lg font-bold text-slate-900 dark:text-white">Recent Activity</h3>
                            <a href="#" class="text-xs text-brand-600 hover:underline">View All</a>
                        </div>
                        <div class="space-y-4">
                            <div class="flex items-start gap-3">
                                <div class="w-2 h-2 mt-2 rounded-full bg-green-500 flex-shrink-0"></div>
                                <div>
                                    <p class="text-sm font-medium text-slate-900 dark:text-white">Payroll Approved</p>
                                    <p class="text-xs text-slate-500">Jan 2026 Salary • 2 hours ago</p>
                                </div>
                            </div>
                            <div class="flex items-start gap-3">
                                <div class="w-2 h-2 mt-2 rounded-full bg-blue-500 flex-shrink-0"></div>
                                <div>
                                    <p class="text-sm font-medium text-slate-900 dark:text-white">New Employee Added</p>
                                    <p class="text-xs text-slate-500">Sarah O. • 5 hours ago</p>
                                </div>
                            </div>
                            <div class="flex items-start gap-3">
                                <div class="w-2 h-2 mt-2 rounded-full bg-purple-500 flex-shrink-0"></div>
                                <div>
                                    <p class="text-sm font-medium text-slate-900 dark:text-white">Statutory Updated</p>
                                    <p class="text-xs text-slate-500">FIRS Rates • Yesterday</p>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Compliance Widget -->
                    <div class="bg-slate-900 dark:bg-black rounded-xl p-6 border border-slate-800 shadow-sm text-white">
                        <h3 class="text-lg font-bold mb-4 flex items-center gap-2">
                            <i data-lucide="shield-check" class="w-5 h-5 text-green-400"></i> Compliance Status
                        </h3>
                        <div class="space-y-4 mb-6">
                            <div class="flex items-center justify-between p-3 rounded-lg bg-white/5 border border-white/10">
                                <span class="text-sm font-medium">PAYE Tax</span>
                                <span class="text-xs font-bold text-green-400 bg-green-400/10 px-2 py-1 rounded">READY</span>
                            </div>
                            <div class="flex items-center justify-between p-3 rounded-lg bg-white/5 border border-white/10">
                                <span class="text-sm font-medium">Pension</span>
                                <span class="text-xs font-bold text-blue-400 bg-blue-400/10 px-2 py-1 rounded">ENABLED</span>
                            </div>
                            <div class="flex items-center justify-between p-3 rounded-lg bg-white/5 border border-white/10">
                                <span class="text-sm font-medium">NHIS</span>
                                <span class="text-xs font-bold text-slate-400 bg-slate-400/10 px-2 py-1 rounded">DISABLED</span>
                            </div>
                        </div>
                        <button class="w-full py-2.5 bg-brand-600 hover:bg-brand-500 text-white text-sm font-bold rounded-lg transition-colors">
                            Review Settings
                        </button>
                    </div>
                </div>
            </main>
        </div>


    <!-- Logic -->
    <?php include '../includes/dashboard_scripts.php'; ?>
    <script>
        // Chart Config
        const ctx = document.getElementById('payrollChart').getContext('2d');
        let payrollChart;

        function initChart() {
            const isDark = html.classList.contains('dark');
            const gridColor = isDark ? '#1e293b' : '#f1f5f9';
            const textColor = isDark ? '#94a3b8' : '#64748b';

            if (payrollChart) payrollChart.destroy();

            payrollChart = new Chart(ctx, {
                type: 'line',
                data: {
                    labels: ['Aug', 'Sep', 'Oct', 'Nov', 'Dec', 'Jan'],
                    datasets: [{
                        label: 'Total Cost',
                        data: [11.2, 11.5, 11.8, 11.8, 12.1, 12.5],
                        borderColor: '#4f46e5',
                        backgroundColor: 'rgba(79, 70, 229, 0.1)',
                        borderWidth: 2,
                        fill: true,
                        tension: 0.4,
                        pointBackgroundColor: '#4f46e5'
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: { display: false }
                    },
                    scales: {
                        y: {
                            beginAtZero: false,
                            grid: { color: gridColor },
                            ticks: { color: textColor, callback: function(value) { return '₦' + value + 'M'; } }
                        },
                        x: {
                            grid: { display: false },
                            ticks: { color: textColor }
                        }
                    }
                }
            });
        }

        initChart();
        function updateChartTheme() { initChart(); }

    </script>
</body>
</html>
