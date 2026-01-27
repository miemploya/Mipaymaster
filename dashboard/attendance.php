<?php
ini_set('display_errors', 1); ini_set('display_startup_errors', 1); error_reporting(E_ALL);
require_once '../includes/functions.php';
require_login();
$company_name = $_SESSION['company_name'] ?? 'My Company';
$company_id = $_SESSION['company_id'];

// --- FETCH DATA ---

// 1. Get Policy & Method
$stmt = $pdo->prepare("SELECT attendance_method FROM companies WHERE id = ?");
$stmt->execute([$company_id]);
$attendance_method = $stmt->fetchColumn() ?: 'manual';

// 2. Get Records (Default to Today)
$filter_date = $_GET['date'] ?? date('Y-m-d');
$records_json = '[]';

try {
    // Fetch logs joined with employee data
    $stmt = $pdo->prepare("
        SELECT al.*, e.first_name, e.last_name, e.payroll_id as emp_code, d.name as dept_name 
        FROM attendance_logs al 
        JOIN employees e ON al.employee_id = e.id 
        LEFT JOIN departments d ON e.department_id = d.id 
        WHERE al.company_id = ? AND al.date = ?
    ");
    $stmt->execute([$company_id, $filter_date]);
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $records = [];
    foreach($rows as $r) {
        $in = $r['check_in_time'] ? date('h:i A', strtotime($r['check_in_time'])) : '-';
        $out = $r['check_out_time'] ? date('h:i A', strtotime($r['check_out_time'])) : '-';
        
        $hours = '0h';
        if($r['check_in_time'] && $r['check_out_time']) {
            $diff = strtotime($r['check_out_time']) - strtotime($r['check_in_time']);
            $hours = round($diff / 3600, 1) . 'h';
        }

        $impact = 'None';
        if ($r['final_deduction_amount'] > 0) $impact = 'Deduction';
        // Bonus logic placeholder
        
        $records[] = [
            'date' => $r['date'],
            'id' => $r['emp_code'],
            'name' => $r['first_name'] . ' ' . $r['last_name'],
            'dept' => $r['dept_name'] ?? '-',
            'in' => $in,
            'out' => $out,
            'hours' => $hours,
            'status' => ucfirst($r['status']), // present, late, absent, etc
            'overtime' => '0h', // Pending OT logic
            'impact' => $impact
        ];
    }
    
    // If empty and it's today, maybe we want to show all employees as "Pending"? 
    // For now, let's stick to showing logs.
    
    $records_json = json_encode($records);

} catch (Exception $e) {
    error_log("Attendance Fetch Error: " . $e->getMessage());
}

// 3. Get All Active Employees for Manual Entry Dropdown
$employees_for_dropdown = [];
try {
    $stmt_emp = $pdo->prepare("
        SELECT e.id, e.first_name, e.last_name, e.payroll_id, d.name as dept_name 
        FROM employees e 
        LEFT JOIN departments d ON e.department_id = d.id 
        WHERE e.company_id = ? 
        AND LOWER(e.employment_status) IN ('active', 'full time', 'probation', 'contract')
        ORDER BY e.first_name ASC
    ");
    $stmt_emp->execute([$company_id]);
    $employees_for_dropdown = $stmt_emp->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    error_log("Employee Fetch Error: " . $e->getMessage());
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Attendance & Records - Mipaymaster</title>
    <!-- Tailwind CSS -->
    <script src="https://cdn.tailwindcss.com"></script>
    <script>
        window.onerror = function(msg, url, line, col, error) {
            var div = document.createElement("div");
            div.style.cssText = "position: fixed; top: 0; left: 0; width: 100%; background: red; color: white; padding: 20px; z-index: 9999;";
            div.innerHTML = "JS Error: " + msg + "<br>Line: " + line;
            document.body.appendChild(div);
            return false;
        };
    </script>
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
    
    <!-- Alpine.js Logic -->
    <script>
        function attendanceApp() {
            return {
                currentTab: 'overview',
                biometricSubTab: 'devices',
                statusFilter: 'All', 
                showManualModal: false,
                showUploadModal: false,
                showDeviceModal: false,
                
                dailyRecords: <?php echo $records_json; ?>,
                policyMethod: '<?php echo $attendance_method; ?>',

                // FLAGGED RECORDS
                flaggedCount: 0,
                flaggedRecords: [],
                flaggedLoading: false,

                devices: [
                    { name: 'Main Entrance', type: 'Fingerprint', location: 'Reception', ip: '192.168.1.20', status: 'Online', lastSync: '2 mins ago' },
                    { name: 'Factory Gate', type: 'Face ID', location: 'Warehouse', ip: '192.168.1.21', status: 'Offline', lastSync: '4 hours ago' },
                ],

                mappings: [
                    { bioId: '1001', bioName: 'John D.', empName: 'John Doe', empId: 'MIP-001', status: 'Linked' },
                    { bioId: '1002', bioName: 'Jane S.', empName: 'Jane Smith', empId: 'MIP-002', status: 'Linked' },
                    { bioId: '1005', bioName: 'Unknown_User_5', empName: '-', empId: '-', status: 'Unmapped' },
                ],

                auditLogs: [
                    { date: 'Jan 15, 10:30 AM', action: 'Manual Entry', user: 'HR Manager', target: 'Sam Wilson', details: 'Added Check-in 09:00 AM' },
                    { date: 'Jan 15, 08:00 AM', action: 'Device Sync', user: 'System', target: 'Main Entrance', details: 'Synced 45 records' },
                    { date: 'Jan 14, 05:15 PM', action: 'Update Record', user: 'Admin', target: 'Jane Smith', details: 'Changed status Late -> Present' },
                ],

                init() {
                    // Safe access to lucide
                    this.$watch('currentTab', () => {
                        setTimeout(() => {
                             if(typeof lucide !== 'undefined' && lucide.createIcons) lucide.createIcons();
                        }, 50)
                    });
                    this.$watch('biometricSubTab', () => {
                        setTimeout(() => {
                            if(typeof lucide !== 'undefined' && lucide.createIcons) lucide.createIcons();
                        }, 50)
                    });
                    // Load flagged count on init
                    this.loadFlaggedCount();
                },

                get filteredRecords() {
                    if (this.statusFilter === 'All') return this.dailyRecords;
                    return this.dailyRecords.filter(r => r.status === this.statusFilter);
                },

                // FLAGGED RECORDS METHODS
                async loadFlaggedCount() {
                    try {
                        const res = await fetch('ajax/attendance_flags.php', {
                            method: 'POST',
                            headers: { 'Content-Type': 'application/json' },
                            body: JSON.stringify({ action: 'get_flagged' })
                        });
                        const data = await res.json();
                        if (data.status) {
                            this.flaggedCount = data.data.length;
                        }
                    } catch (e) { console.error('Failed to load flagged count:', e); }
                },

                async loadFlagged() {
                    this.flaggedLoading = true;
                    try {
                        const res = await fetch('ajax/attendance_flags.php', {
                            method: 'POST',
                            headers: { 'Content-Type': 'application/json' },
                            body: JSON.stringify({ action: 'get_flagged' })
                        });
                        const data = await res.json();
                        if (data.status) {
                            this.flaggedRecords = data.data;
                            this.flaggedCount = data.data.length;
                        }
                    } catch (e) { console.error('Failed to load flagged records:', e); alert('Error loading flagged records'); }
                    this.flaggedLoading = false;
                    setTimeout(() => { if(typeof lucide !== 'undefined') lucide.createIcons(); }, 50);
                },

                async clearFlag(logId) {
                    if (!confirm('Clear this flag? The record will be marked as reviewed.')) return;
                    try {
                        const res = await fetch('ajax/attendance_flags.php', {
                            method: 'POST',
                            headers: { 'Content-Type': 'application/json' },
                            body: JSON.stringify({ action: 'clear_flag', log_id: logId })
                        });
                        const data = await res.json();
                        if (data.status) {
                            this.loadFlagged(); // Refresh
                        } else {
                            alert(data.message || 'Failed to clear flag');
                        }
                    } catch (e) { console.error('Failed to clear flag:', e); alert('Error clearing flag'); }
                },

                async markAbsent(dateStr) {
                    const date = dateStr || prompt('Enter date (YYYY-MM-DD):', new Date(Date.now() - 86400000).toISOString().slice(0,10));
                    if (!date) return;
                    if (!confirm(`Mark all non-present employees as Absent for ${date}?`)) return;
                    
                    try {
                        const res = await fetch('ajax/attendance_flags.php', {
                            method: 'POST',
                            headers: { 'Content-Type': 'application/json' },
                            body: JSON.stringify({ action: 'mark_absent', date: date })
                        });
                        const data = await res.json();
                        alert(data.message || 'Done');
                        if (data.status) this.loadFlagged(); // Refresh
                    } catch (e) { console.error('Failed to mark absent:', e); alert('Error marking absences'); }
                }
            }
        }
    </script>
    <!-- Alpine.js Core -->
    <script src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js"></script>
    
    <style type="text/tailwindcss">
        @import url('https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap');
        body { font-family: 'Inter', sans-serif; }
        
        ::-webkit-scrollbar { width: 6px; height: 6px; }
        ::-webkit-scrollbar-track { background: transparent; }
        ::-webkit-scrollbar-thumb { background: #cbd5e1; border-radius: 3px; }
        .dark ::-webkit-scrollbar-thumb { background: #334155; }
        
        [x-cloak] { display: none !important; }
        
        /* Sidebar Transitions */
        .sidebar-transition { transition: margin-left 0.3s ease-in-out, width 0.3s ease-in-out, transform 0.3s ease-in-out; }
        
        /* Toolbar transition */
        #collapsed-toolbar { transition: transform 0.3s ease-in-out, opacity 0.3s ease-in-out; }
        .toolbar-hidden { transform: translateY(-100%); opacity: 0; pointer-events: none; height: 0; padding: 0; border: none; }
        .toolbar-visible { transform: translateY(0); opacity: 1; pointer-events: auto; height: auto; }

        /* Form Styles */
        .form-input {
            @apply w-full rounded-lg border-slate-300 dark:border-slate-700 bg-white dark:bg-slate-800 text-slate-900 dark:text-white focus:ring-brand-500 focus:border-brand-500 transition-colors shadow-sm text-sm;
        }
        .form-label {
            @apply block text-xs font-semibold text-slate-700 dark:text-slate-300 mb-1.5 uppercase tracking-wide;
        }
        
        /* NAVIGATION TABS - Styles now inline in HTML */

        /* FILTER PILL BUTTONS */
        .filter-btn {
            @apply px-3 py-2 text-xs font-bold rounded-lg border transition-all duration-200 flex items-center gap-2;
        }
    </style>
</head>
<body class="bg-slate-50 dark:bg-slate-900 text-slate-900 dark:text-slate-100 transition-colors duration-300 overflow-hidden flex h-screen" x-data="attendanceApp()">

    <!-- Wrapper removed -->


        <!-- A. LEFT SIDEBAR -->
        <?php $current_page = 'attendance'; include '../includes/dashboard_sidebar.php'; ?>

        <!-- MAIN CONTENT -->
        <!-- NOTIFICATIONS PANEL (SLIDE-OVER) - Starts hidden -->
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

        <div class="flex-1 flex flex-col h-full overflow-hidden w-full relative">
            
            <!-- Header -->
            <?php $page_title = 'Attendance & Records'; include '../includes/dashboard_header.php'; ?>
            <!-- Payroll Sub-Header -->
            <?php include '../includes/payroll_header.php'; ?>


            <!-- Collapsed Toolbar (Shown when sidebar is collapsed) -->
            <!-- Positioned statically within the flex column to avoid overlapping main content -->
            <!-- Collapsed Toolbar -->
            <div id="collapsed-toolbar" class="toolbar-hidden bg-white dark:bg-slate-950 border-b border-slate-200 dark:border-slate-800 flex items-center px-6 shrink-0 z-20">
                <button id="sidebar-expand-btn" class="flex items-center gap-2 p-2 rounded-lg text-slate-500 hover:text-brand-600 dark:text-slate-400 dark:hover:text-white hover:bg-slate-100 dark:hover:bg-slate-800 transition-colors my-2">
                    <i data-lucide="menu" class="w-5 h-5"></i> <span class="text-sm font-medium">Show Menu</span>
                </button>
            </div>

            <!-- Scrollable Content -->
            <main class="flex-1 overflow-y-auto p-6 lg:p-8 bg-slate-50 dark:bg-slate-900">
                
                
                <!-- NAVIGATION TABS -->
                <div class="flex items-center gap-2 w-full border-b border-slate-200 dark:border-slate-800 mb-6 overflow-x-auto min-w-full">
                    <button @click="currentTab = 'overview'" 
                        :class="currentTab === 'overview' ? 'border-brand-600 text-brand-600 dark:text-brand-400 dark:border-brand-400 bg-brand-50/10' : 'border-transparent text-slate-500 hover:text-slate-800 dark:text-slate-400 dark:hover:text-slate-200 hover:border-slate-300'" 
                        class="flex items-center gap-2 px-4 py-3 text-sm font-medium border-b-2 transition-all duration-200 whitespace-nowrap cursor-pointer">
                        <i data-lucide="layout-grid" class="w-4 h-4"></i> Overview
                    </button>
                    <button @click="currentTab = 'monthly'" 
                        :class="currentTab === 'monthly' ? 'border-brand-600 text-brand-600 dark:text-brand-400 dark:border-brand-400 bg-brand-50/10' : 'border-transparent text-slate-500 hover:text-slate-800 dark:text-slate-400 dark:hover:text-slate-200 hover:border-slate-300'" 
                        class="flex items-center gap-2 px-4 py-3 text-sm font-medium border-b-2 transition-all duration-200 whitespace-nowrap cursor-pointer">
                        <i data-lucide="calendar" class="w-4 h-4"></i> Monthly Summary
                    </button>
                    <button @click="currentTab = 'biometrics'" x-show="policyMethod === 'biometric'"
                        :class="currentTab === 'biometrics' ? 'border-brand-600 text-brand-600 dark:text-brand-400 dark:border-brand-400 bg-brand-50/10' : 'border-transparent text-slate-500 hover:text-slate-800 dark:text-slate-400 dark:hover:text-slate-200 hover:border-slate-300'" 
                        class="flex items-center gap-2 px-4 py-3 text-sm font-medium border-b-2 transition-all duration-200 whitespace-nowrap cursor-pointer">
                        <i data-lucide="fingerprint" class="w-4 h-4"></i> Biometrics
                    </button>
                    <a href="company.php?tab=attendance" 
                        class="flex items-center gap-2 px-4 py-3 text-sm font-medium border-b-2 border-transparent text-slate-500 hover:text-brand-600 dark:text-slate-400 dark:hover:text-brand-400 hover:border-brand-300 transition-all duration-200 whitespace-nowrap cursor-pointer">
                        <i data-lucide="settings" class="w-4 h-4"></i> Policy Settings
                    </a>
                    <button @click="currentTab = 'logs'" 
                        :class="currentTab === 'logs' ? 'border-brand-600 text-brand-600 dark:text-brand-400 dark:border-brand-400 bg-brand-50/10' : 'border-transparent text-slate-500 hover:text-slate-800 dark:text-slate-400 dark:hover:text-slate-200 hover:border-slate-300'" 
                        class="flex items-center gap-2 px-4 py-3 text-sm font-medium border-b-2 transition-all duration-200 whitespace-nowrap cursor-pointer">
                        <i data-lucide="file-clock" class="w-4 h-4"></i> Audit Logs
                    </button>
                    <button @click="currentTab = 'flagged'; loadFlagged()" 
                        :class="currentTab === 'flagged' ? 'border-red-600 text-red-600 dark:text-red-400 dark:border-red-400 bg-red-50/10' : 'border-transparent text-slate-500 hover:text-red-600 dark:text-slate-400 dark:hover:text-red-400 hover:border-red-300'" 
                        class="flex items-center gap-2 px-4 py-3 text-sm font-medium border-b-2 transition-all duration-200 whitespace-nowrap cursor-pointer">
                        <i data-lucide="alert-triangle" class="w-4 h-4"></i> 
                        <span>Flagged</span>
                        <span x-show="flaggedCount > 0" class="ml-1 px-2 py-0.5 text-xs font-bold bg-red-500 text-white rounded-full" x-text="flaggedCount"></span>
                    </button>
                </div>

                <!-- TAB 1: OVERVIEW -->
                <div x-show="currentTab === 'overview'" x-transition.opacity>
                    <!-- Policy Banner -->
                    <div class="mb-6 px-4 py-3 rounded-lg border flex items-center gap-3"
                         :class="{
                            'bg-blue-50 border-blue-200 text-blue-700 dark:bg-blue-900/20 dark:border-blue-800 dark:text-blue-300': policyMethod === 'manual',
                            'bg-purple-50 border-purple-200 text-purple-700 dark:bg-purple-900/20 dark:border-purple-800 dark:text-purple-300': policyMethod === 'self',
                            'bg-indigo-50 border-indigo-200 text-indigo-700 dark:bg-indigo-900/20 dark:border-indigo-800 dark:text-indigo-300': policyMethod === 'biometric'
                         }">
                        <i :data-lucide="policyMethod === 'manual' ? 'clipboard-list' : (policyMethod === 'self' ? 'smartphone' : 'fingerprint')" class="w-5 h-5"></i>
                        <span class="text-sm font-medium">
                            Current Mode: <span class="font-bold" x-text="policyMethod === 'manual' ? 'Manual Entry' : (policyMethod === 'self' ? 'Self Check-In' : 'Biometric Sync')"></span>
                        </span>
                        <a href="company.php?tab=attendance" class="text-xs underline ml-auto hover:text-brand-600">Change Policy</a>
                    </div>

                    <!-- Top Controls (Card Wrapper) -->
                    <div class="bg-white dark:bg-slate-950 p-5 rounded-2xl border border-slate-200 dark:border-slate-800 shadow-sm mb-6">
                        <div class="flex flex-col xl:flex-row justify-between items-start xl:items-center gap-6">
                            
                            <!-- Search & Date Filter -->
                            <div class="flex flex-col sm:flex-row gap-3 w-full xl:w-auto">
                                <div class="relative">
                                    <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                                        <i data-lucide="calendar" class="h-4 w-4 text-slate-400"></i>
                                    </div>
                                    <input type="date" class="form-input pl-10 py-2.5 w-full sm:w-40 font-medium" value="<?php echo $filter_date; ?>" @change="window.location.href='attendance.php?date=' + $event.target.value">
                                </div>
                                <div class="relative flex-1 min-w-[240px]">
                                    <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                                        <i data-lucide="search" class="h-4 w-4 text-slate-400"></i>
                                    </div>
                                    <input type="text" placeholder="Search employee..." class="form-input pl-10 py-2.5">
                                </div>
                            </div>
                            
                            <!-- STATUS FILTERS & ACTIONS -->
                            <div class="flex flex-col sm:flex-row gap-4 w-full xl:w-auto xl:items-center">
                                <!-- Filters -->
                                <div class="flex flex-wrap gap-2">
                                    <button @click="statusFilter = 'All'" 
                                        :class="statusFilter === 'All' ? 'bg-slate-800 text-white border-slate-800 ring-2 ring-slate-800 ring-offset-1' : 'bg-white text-slate-600 border-slate-200 hover:border-slate-300 hover:bg-slate-50'" 
                                        class="filter-btn">
                                        All
                                    </button>
                                    
                                    <button @click="statusFilter = 'Present'" 
                                        :class="statusFilter === 'Present' ? 'bg-green-600 text-white border-green-600 ring-2 ring-green-600 ring-offset-1' : 'bg-white text-slate-600 border-slate-200 hover:text-green-600 hover:border-green-200'" 
                                        class="filter-btn">
                                        <div :class="statusFilter === 'Present' ? 'bg-white' : 'bg-green-500'" class="w-1.5 h-1.5 rounded-full"></div> Present
                                    </button>
                                    
                                    <button @click="statusFilter = 'Late'" 
                                        :class="statusFilter === 'Late' ? 'bg-amber-500 text-white border-amber-500 ring-2 ring-amber-500 ring-offset-1' : 'bg-white text-slate-600 border-slate-200 hover:text-amber-600 hover:border-amber-200'" 
                                        class="filter-btn">
                                        <div :class="statusFilter === 'Late' ? 'bg-white' : 'bg-amber-500'" class="w-1.5 h-1.5 rounded-full"></div> Late
                                    </button>
                                    
                                    <button @click="statusFilter = 'Absent'" 
                                        :class="statusFilter === 'Absent' ? 'bg-red-600 text-white border-red-600 ring-2 ring-red-600 ring-offset-1' : 'bg-white text-slate-600 border-slate-200 hover:text-red-600 hover:border-red-200'" 
                                        class="filter-btn">
                                        <div :class="statusFilter === 'Absent' ? 'bg-white' : 'bg-red-500'" class="w-1.5 h-1.5 rounded-full"></div> Absent
                                    </button>
                                    
                                    <!-- Simplified Other Filters Dropdown idea or just list them if space permits. Listing them as pills for now. -->
                                     <button @click="statusFilter = 'On Leave'" 
                                        :class="statusFilter === 'On Leave' ? 'bg-blue-600 text-white border-blue-600 ring-2 ring-blue-600 ring-offset-1' : 'bg-white text-slate-600 border-slate-200 hover:text-blue-600 hover:border-blue-200'" 
                                        class="filter-btn">
                                        <div :class="statusFilter === 'On Leave' ? 'bg-white' : 'bg-blue-500'" class="w-1.5 h-1.5 rounded-full"></div> Leave
                                    </button>
                                </div>

                                <!-- Actions -->
                                <div class="flex gap-2 border-l border-slate-200 dark:border-slate-800 pl-4 ml-2">
                                    <button @click="showManualModal = true" class="p-2.5 bg-white dark:bg-slate-800 border border-slate-200 dark:border-slate-700 text-slate-600 dark:text-slate-300 rounded-lg hover:border-brand-500 hover:text-brand-600 transition-colors" title="Manual Entry">
                                        <i data-lucide="plus-square" class="w-5 h-5"></i>
                                    </button>
                                    <button @click="showUploadModal = true" class="px-4 py-2.5 bg-slate-900 dark:bg-white text-white dark:text-slate-900 rounded-lg hover:opacity-90 text-sm font-bold transition-opacity flex items-center gap-2 shadow-sm">
                                        <i data-lucide="upload" class="w-4 h-4"></i> Upload
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Table -->
                    <div class="bg-white dark:bg-slate-950 rounded-xl border border-slate-200 dark:border-slate-800 overflow-hidden shadow-sm overflow-x-auto">
                        <table class="w-full text-left text-sm min-w-[1000px]">
                            <thead class="bg-slate-50 dark:bg-slate-900 border-b border-slate-200 dark:border-slate-800 text-slate-500 dark:text-slate-400">
                                <tr>
                                    <th class="px-6 py-3 font-medium">Date</th>
                                    <th class="px-6 py-3 font-medium">Payroll ID</th>
                                    <th class="px-6 py-3 font-medium">Employee</th>
                                    <th class="px-6 py-3 font-medium">In / Out</th>
                                    <th class="px-6 py-3 font-medium text-center">Hours</th>
                                    <th class="px-6 py-3 font-medium text-center">Status</th>
                                    <th class="px-6 py-3 font-medium text-center">Payroll Impact</th>
                                    <th class="px-6 py-3 font-medium text-center">Action</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
                                <template x-for="record in filteredRecords" :key="record.id">
                                    <tr class="hover:bg-slate-50 dark:hover:bg-slate-900/50 transition-colors">
                                        <td class="px-6 py-4 text-slate-500" x-text="record.date"></td>
                                        <td class="px-6 py-4 font-mono text-slate-500" x-text="record.id"></td>
                                        <td class="px-6 py-4">
                                            <div class="font-medium text-slate-900 dark:text-white" x-text="record.name"></div>
                                            <div class="text-xs text-slate-500" x-text="record.dept"></div>
                                        </td>
                                        <td class="px-6 py-4 text-slate-600 dark:text-slate-400">
                                            <span x-text="record.in"></span> - <span x-text="record.out"></span>
                                        </td>
                                        <td class="px-6 py-4 text-center text-slate-900 dark:text-white font-medium">
                                            <span x-text="record.hours"></span>
                                            <span x-show="record.overtime !== '0h'" class="block text-xs text-green-600" x-text="'+' + record.overtime + ' OT'"></span>
                                        </td>
                                        <td class="px-6 py-4 text-center">
                                            <span :class="{
                                                'bg-green-100 text-green-700 dark:bg-green-900/30 dark:text-green-400': record.status === 'Present',
                                                'bg-amber-100 text-amber-700 dark:bg-amber-900/30 dark:text-amber-400': record.status === 'Late',
                                                'bg-red-100 text-red-700 dark:bg-red-900/30 dark:text-red-400': record.status === 'Absent',
                                                'bg-blue-100 text-blue-700 dark:bg-blue-900/30 dark:text-blue-400': record.status === 'On Leave',
                                                'bg-gray-200 text-gray-700 dark:bg-gray-700 dark:text-gray-300': record.status === 'Suspended'
                                            }" class="px-2 py-1 rounded-full text-xs font-bold" x-text="record.status"></span>
                                        </td>
                                        <td class="px-6 py-4 text-center">
                                            <div class="flex items-center justify-center gap-1 text-xs font-medium"
                                                 :class="{
                                                    'text-red-500': record.impact === 'Deduction',
                                                    'text-green-500': record.impact === 'Bonus',
                                                    'text-slate-400': record.impact === 'None'
                                                 }">
                                                <i x-show="record.impact !== 'None'" :data-lucide="record.impact === 'Deduction' ? 'trending-down' : 'trending-up'" class="w-3 h-3"></i>
                                                <span x-text="record.impact"></span>
                                            </div>
                                        </td>
                                        <td class="px-6 py-4 text-center">
                                            <button @click="showManualModal = true" class="text-brand-600 hover:underline text-xs">Edit</button>
                                        </td>
                                    </tr>
                                </template>
                            </tbody>
                        </table>
                    </div>
                </div>

                <!-- TAB 2: MONTHLY SUMMARY -->
                <div x-show="currentTab === 'monthly'" x-cloak x-transition.opacity>
                    <!-- Summary Cards -->
                    <div class="grid grid-cols-2 md:grid-cols-4 lg:grid-cols-6 gap-4 mb-6">
                        <div class="bg-white dark:bg-slate-950 p-4 rounded-xl border border-slate-200 dark:border-slate-800 shadow-sm text-center">
                            <p class="text-xs text-slate-500 uppercase font-bold">Working Days</p>
                            <p class="text-2xl font-bold text-slate-900 dark:text-white mt-1">22</p>
                        </div>
                        <div class="bg-white dark:bg-slate-950 p-4 rounded-xl border border-slate-200 dark:border-slate-800 shadow-sm text-center">
                            <p class="text-xs text-green-600 uppercase font-bold">Present Rate</p>
                            <p class="text-2xl font-bold text-green-700 dark:text-green-400 mt-1">95%</p>
                        </div>
                        <div class="bg-white dark:bg-slate-950 p-4 rounded-xl border border-slate-200 dark:border-slate-800 shadow-sm text-center">
                            <p class="text-xs text-red-500 uppercase font-bold">Absent</p>
                            <p class="text-2xl font-bold text-red-600 dark:text-red-400 mt-1">12</p>
                        </div>
                        <div class="bg-white dark:bg-slate-950 p-4 rounded-xl border border-slate-200 dark:border-slate-800 shadow-sm text-center">
                            <p class="text-xs text-amber-500 uppercase font-bold">Lates</p>
                            <p class="text-2xl font-bold text-amber-600 dark:text-amber-400 mt-1">45</p>
                        </div>
                        <div class="bg-white dark:bg-slate-950 p-4 rounded-xl border border-slate-200 dark:border-slate-800 shadow-sm text-center">
                            <p class="text-xs text-blue-500 uppercase font-bold">On Leave</p>
                            <p class="text-2xl font-bold text-blue-600 dark:text-blue-400 mt-1">3</p>
                        </div>
                        <div class="bg-white dark:bg-slate-950 p-4 rounded-xl border border-slate-200 dark:border-slate-800 shadow-sm text-center">
                            <p class="text-xs text-purple-500 uppercase font-bold">Overtime</p>
                            <p class="text-2xl font-bold text-purple-600 dark:text-purple-400 mt-1">120h</p>
                        </div>
                    </div>
                    
                    <!-- Employee Summary Table Placeholder -->
                    <div class="bg-white dark:bg-slate-950 rounded-xl border border-slate-200 dark:border-slate-800 p-8 text-center text-slate-500">
                        Detailed Monthly Breakdown Table would appear here.
                    </div>
                </div>

                <!-- TAB 3: BIOMETRIC CONFIGURATION & MAPPING -->
                <div x-show="currentTab === 'biometrics'" x-cloak x-transition.opacity>
                    <!-- Sub-tabs for Biometrics -->
                    <div class="flex gap-4 mb-6 border-b border-slate-200 dark:border-slate-800 pb-1">
                        <button @click="biometricSubTab = 'devices'" :class="biometricSubTab === 'devices' ? 'text-brand-600 border-brand-600 font-bold' : 'text-slate-500 border-transparent hover:text-slate-700'" class="pb-2 px-1 text-sm border-b-2 transition-colors">Connected Devices</button>
                        <button @click="biometricSubTab = 'mapping'" :class="biometricSubTab === 'mapping' ? 'text-brand-600 border-brand-600 font-bold' : 'text-slate-500 border-transparent hover:text-slate-700'" class="pb-2 px-1 text-sm border-b-2 transition-colors">Employee Mapping</button>
                    </div>

                    <!-- SUB-TAB: DEVICES -->
                    <div x-show="biometricSubTab === 'devices'">
                        <div class="flex justify-between items-center mb-6">
                            <h3 class="text-sm font-bold uppercase text-slate-500 tracking-wide">Devices</h3>
                            <button @click="showDeviceModal = true" class="px-4 py-2 bg-brand-600 text-white rounded-lg hover:bg-brand-700 text-sm font-bold shadow-md transition-colors flex items-center gap-2">
                                <i data-lucide="plus" class="w-4 h-4"></i> Add Device
                            </button>
                        </div>
                        
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                            <template x-for="device in devices" :key="device.ip">
                                <div class="bg-white dark:bg-slate-950 rounded-xl border border-slate-200 dark:border-slate-800 p-6 flex flex-col justify-between h-48">
                                    <div class="flex justify-between items-start">
                                        <div class="flex items-center gap-3">
                                            <div class="w-10 h-10 rounded-lg bg-slate-100 dark:bg-slate-800 flex items-center justify-center">
                                                <i :data-lucide="device.type === 'Fingerprint' ? 'fingerprint' : 'scan-face'" class="w-6 h-6 text-slate-500"></i>
                                            </div>
                                            <div>
                                                <h4 class="font-bold text-slate-900 dark:text-white" x-text="device.name"></h4>
                                                <p class="text-xs text-slate-500" x-text="device.location + ' • ' + device.ip"></p>
                                            </div>
                                        </div>
                                        <span :class="device.status === 'Online' ? 'bg-green-100 text-green-700' : 'bg-red-100 text-red-700'" class="px-2 py-1 rounded text-xs font-bold" x-text="device.status"></span>
                                    </div>
                                    <div class="mt-4">
                                        <p class="text-xs text-slate-400">Last Sync: <span x-text="device.lastSync"></span></p>
                                        <div class="w-full bg-slate-100 dark:bg-slate-800 h-1.5 rounded-full mt-2 overflow-hidden">
                                            <div class="bg-brand-500 h-full w-full animate-pulse"></div>
                                        </div>
                                    </div>
                                    <div class="flex gap-2 mt-4 pt-4 border-t border-slate-100 dark:border-slate-800">
                                        <button class="flex-1 py-2 text-sm font-medium text-slate-600 dark:text-slate-300 hover:bg-slate-50 dark:hover:bg-slate-800 rounded">Sync Now</button>
                                        <button class="flex-1 py-2 text-sm font-medium text-slate-600 dark:text-slate-300 hover:bg-slate-50 dark:hover:bg-slate-800 rounded">Configure</button>
                                    </div>
                                </div>
                            </template>
                        </div>
                    </div>

                    <!-- SUB-TAB: MAPPING (NEW) -->
                    <div x-show="biometricSubTab === 'mapping'">
                        <div class="bg-white dark:bg-slate-950 rounded-xl border border-slate-200 dark:border-slate-800 overflow-hidden shadow-sm">
                            <div class="p-4 border-b border-slate-200 dark:border-slate-800 bg-slate-50 dark:bg-slate-900 flex justify-between items-center">
                                <div class="flex items-center gap-2">
                                    <i data-lucide="link" class="w-4 h-4 text-slate-500"></i>
                                    <span class="text-sm font-bold text-slate-700 dark:text-slate-300">Biometric User ID to Employee Mapping</span>
                                </div>
                                <button class="text-xs text-brand-600 font-bold hover:underline">Auto-Map by ID</button>
                            </div>
                            <table class="w-full text-left text-sm">
                                <thead class="bg-slate-50 dark:bg-slate-900 border-b border-slate-200 dark:border-slate-800 text-slate-500 dark:text-slate-400">
                                    <tr>
                                        <th class="px-6 py-3 font-medium">Biometric ID</th>
                                        <th class="px-6 py-3 font-medium">Name on Device</th>
                                        <th class="px-6 py-3 font-medium">Mapped Employee</th>
                                        <th class="px-6 py-3 font-medium">Payroll ID</th>
                                        <th class="px-6 py-3 font-medium text-center">Status</th>
                                        <th class="px-6 py-3 font-medium text-center">Action</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
                                    <template x-for="map in mappings" :key="map.bioId">
                                        <tr class="hover:bg-slate-50 dark:hover:bg-slate-900/50 transition-colors">
                                            <td class="px-6 py-4 font-mono text-slate-500" x-text="map.bioId"></td>
                                            <td class="px-6 py-4 font-medium text-slate-900 dark:text-white" x-text="map.bioName"></td>
                                            <td class="px-6 py-4 text-slate-600 dark:text-slate-300" x-text="map.empName"></td>
                                            <td class="px-6 py-4 font-mono text-xs text-slate-500" x-text="map.empId"></td>
                                            <td class="px-6 py-4 text-center">
                                                <span :class="map.status === 'Linked' ? 'bg-green-100 text-green-700' : 'bg-amber-100 text-amber-700'" class="px-2 py-1 rounded-full text-xs font-bold" x-text="map.status"></span>
                                            </td>
                                            <td class="px-6 py-4 text-center">
                                                <button class="text-brand-600 hover:underline text-xs" x-text="map.status === 'Linked' ? 'Edit' : 'Map Now'"></button>
                                            </td>
                                        </tr>
                                    </template>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>



                <!-- TAB 5: AUDIT LOGS (NEW TABLE) -->
                <div x-show="currentTab === 'logs'" x-cloak x-transition.opacity>
                    <div class="bg-white dark:bg-slate-950 rounded-xl border border-slate-200 dark:border-slate-800 overflow-hidden shadow-sm">
                        <div class="p-4 border-b border-slate-200 dark:border-slate-800 bg-slate-50 dark:bg-slate-900 flex justify-between items-center">
                            <h3 class="text-sm font-bold text-slate-700 dark:text-slate-300 uppercase tracking-wide">System Audit Trail</h3>
                            <button class="text-xs text-brand-600 font-bold hover:underline flex items-center gap-1"><i data-lucide="download" class="w-3 h-3"></i> Export Log</button>
                        </div>
                        <table class="w-full text-left text-sm">
                            <thead class="bg-slate-50 dark:bg-slate-900 border-b border-slate-200 dark:border-slate-800 text-slate-500 dark:text-slate-400">
                                <tr>
                                    <th class="px-6 py-3 font-medium">Date & Time</th>
                                    <th class="px-6 py-3 font-medium">Action</th>
                                    <th class="px-6 py-3 font-medium">User</th>
                                    <th class="px-6 py-3 font-medium">Target</th>
                                    <th class="px-6 py-3 font-medium">Details</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
                                <template x-for="log in auditLogs" :key="log.date">
                                    <tr class="hover:bg-slate-50 dark:hover:bg-slate-900/50 transition-colors">
                                        <td class="px-6 py-4 text-slate-500 text-xs font-mono" x-text="log.date"></td>
                                        <td class="px-6 py-4 font-bold text-slate-700 dark:text-slate-300" x-text="log.action"></td>
                                        <td class="px-6 py-4 text-slate-600 dark:text-slate-400" x-text="log.user"></td>
                                        <td class="px-6 py-4 text-slate-600 dark:text-slate-400" x-text="log.target"></td>
                                        <td class="px-6 py-4 text-slate-500 italic" x-text="log.details"></td>
                                    </tr>
                                </template>
                            </tbody>
                        </table>
                    </div>
                </div>

            </main>
        </div>
    <!-- Wrapper closing div removed -->

    <!-- MODALS -->
    
    <!-- Modern Manual Entry Modal -->
    <div x-show="showManualModal" x-cloak class="fixed inset-0 z-50 flex items-center justify-center bg-black/60 backdrop-blur-sm p-4">
        <div @click.outside="showManualModal = false" class="bg-white dark:bg-slate-900 rounded-2xl shadow-2xl w-full max-w-lg border border-slate-200 dark:border-slate-800 overflow-hidden">
            <div class="flex justify-between items-center p-6 border-b border-slate-100 dark:border-slate-800 bg-slate-50 dark:bg-slate-900/50">
                <div>
                    <h3 class="text-xl font-bold text-slate-900 dark:text-white">Manual Entry</h3>
                    <p class="text-xs text-slate-500">Correct or add attendance records manually.</p>
                </div>
                <button @click="showManualModal = false" class="text-slate-400 hover:text-slate-600 dark:hover:text-white"><i data-lucide="x" class="w-6 h-6"></i></button>
            </div>
            
            <script>
                window.attendanceEmployees = <?php echo json_encode($employees_for_dropdown); ?>;
            </script>
            <div class="p-6 space-y-5" x-data="{
                form: {
                    employee_id: '',
                    date: '<?php echo date('Y-m-d'); ?>',
                    status: 'Present',
                    check_in: '',
                    check_out: '',
                    reason: '',
                    custom_deduction: ''
                },
                search: '', 
                open: false, 
                selectedName: '',
                employees: window.attendanceEmployees,
                get filteredEmployees() {
                    if (this.search === '') return this.employees;
                    return this.employees.filter(emp => {
                        const fullName = (emp.first_name + ' ' + emp.last_name).toLowerCase();
                        return fullName.includes(this.search.toLowerCase()) || 
                               (emp.payroll_id && emp.payroll_id.toLowerCase().includes(this.search.toLowerCase()));
                    });
                },
                select(emp) {
                    this.form.employee_id = emp.id;
                    this.selectedName = emp.first_name + ' ' + emp.last_name + ' (' + (emp.payroll_id || 'N/A') + ')';
                    this.open = false;
                    this.search = '';
                },
                save() {
                    if(!this.form.employee_id) { alert('Please select an employee'); return; }
                    if(!this.form.date) { alert('Please select a date'); return; }
                    if(!this.form.reason) { alert('Please enter a reason'); return; }
                    
                    fetch('ajax/save_manual_attendance.php', {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/json' },
                        body: JSON.stringify(this.form)
                    })
                    .then(r => r.json())
                    .then(data => {
                        if(data.status) {
                            alert('Record saved successfully!');
                            window.location.reload();
                        } else {
                            alert('Error: ' + data.message);
                        }
                    })
                    .catch(e => alert('Network error'));
                }
            }">
                <div class="relative" @click.outside="open = false">
                    <label class="form-label">Employee</label>
                    
                    <div @click="open = !open" class="form-input bg-slate-50 cursor-pointer flex justify-between items-center">
                        <span x-text="selectedName ? selectedName : 'Select Employee...'" :class="{'text-slate-400': !selectedName, 'text-slate-700 dark:text-white': selectedName}"></span>
                        <i data-lucide="chevron-down" class="w-4 h-4 text-slate-400"></i>
                    </div>
                    
                    <div x-show="open" x-cloak class="absolute z-10 w-full mt-1 bg-white dark:bg-slate-800 border border-slate-200 dark:border-slate-700 rounded-lg shadow-lg max-h-60 overflow-y-auto">
                        <div class="p-2 sticky top-0 bg-white dark:bg-slate-800 border-b border-slate-100 dark:border-slate-700">
                            <input type="text" x-model="search" placeholder="Search by name or ID..." class="w-full px-3 py-1.5 text-sm border border-slate-200 dark:border-slate-600 rounded-md bg-slate-50 dark:bg-slate-900 focus:outline-none focus:border-brand-500 dark:text-white" @click.stop>
                        </div>
                        <ul>
                            <template x-for="emp in filteredEmployees" :key="emp.id">
                                <li @click="select(emp)" class="px-4 py-2 hover:bg-slate-50 dark:hover:bg-slate-700 cursor-pointer text-sm text-slate-700 dark:text-gray-300">
                                    <span x-text="emp.first_name + ' ' + emp.last_name" class="font-medium"></span>
                                    <span class="text-xs text-slate-400 ml-2" x-text="'(' + (emp.payroll_id || 'N/A') + ')'"></span>
                                    <span x-show="emp.dept_name" class="block text-xs text-slate-400" x-text="emp.dept_name"></span>
                                </li>
                            </template>
                            <li x-show="filteredEmployees.length === 0" class="px-4 py-2 text-sm text-slate-400 text-center">No employees found</li>
                        </ul>
                    </div>
                </div>
                
                <div class="grid grid-cols-2 gap-5">
                    <div><label class="form-label">Date</label><input type="date" class="form-input" x-model="form.date"></div>
                    <div>
                        <label class="form-label">Attendance Status</label>
                        <select class="form-input" x-model="form.status">
                            <option value="Present">Present</option>
                            <option value="Late">Late</option>
                            <option value="Absent">Absent</option>
                            <option value="On Leave">On Leave</option>
                            <option value="Suspended">Suspended</option>
                        </select>
                    </div>
                </div>
                
                <div class="grid grid-cols-2 gap-5 p-4 bg-slate-50 dark:bg-slate-800/50 rounded-lg border border-slate-100 dark:border-slate-700/50">
                    <div>
                        <label class="form-label flex items-center gap-2"><i data-lucide="log-in" class="w-3 h-3 text-green-500"></i> Check In</label>
                        <input type="time" class="form-input" x-model="form.check_in">
                    </div>
                    <div>
                        <label class="form-label flex items-center gap-2"><i data-lucide="log-out" class="w-3 h-3 text-red-500"></i> Check Out</label>
                        <input type="time" class="form-input" x-model="form.check_out">
                    </div>
                </div>
                
                <!-- Lateness Deduction Field (shown when Late) -->
                <div x-show="form.status === 'Late'" x-cloak class="p-4 bg-amber-50 dark:bg-amber-900/20 rounded-lg border border-amber-200 dark:border-amber-800">
                    <label class="form-label flex items-center gap-2 text-amber-700 dark:text-amber-400">
                        <i data-lucide="alert-triangle" class="w-3 h-3"></i> Lateness Deduction (Optional)
                    </label>
                    <div class="flex items-center gap-3">
                        <span class="text-lg font-bold text-amber-600">₦</span>
                        <input type="number" step="0.01" min="0" class="form-input flex-1" x-model="form.custom_deduction" placeholder="Leave blank for auto-calculation">
                    </div>
                    <p class="text-xs text-amber-600 dark:text-amber-400 mt-1">If blank, deduction is calculated from policy settings.</p>
                </div>
                
                <div>
                    <label class="form-label">Reason for Adjustment <span class="text-red-500">*</span></label>
                    <textarea class="form-input" rows="2" placeholder="Required for audit trail..." x-model="form.reason"></textarea>
                </div>
                
                <div class="pt-2">
                    <button @click="save()" class="w-full py-3 bg-brand-600 text-white font-bold rounded-xl hover:bg-brand-700 transition-colors shadow-lg shadow-brand-500/30">Save Record</button>
                </div>
            </div>
        </div>
    </div>

    <!-- Upload Modal -->
    <div x-show="showUploadModal" x-cloak class="fixed inset-0 z-50 flex items-center justify-center bg-black/50 backdrop-blur-sm p-4">
        <div @click.outside="showUploadModal = false" class="bg-white dark:bg-slate-900 rounded-xl shadow-2xl w-full max-w-md border border-slate-200 dark:border-slate-800 p-6 text-center">
            <h3 class="text-lg font-bold text-slate-900 dark:text-white mb-4">Bulk Attendance Upload</h3>
            <div class="border-2 border-dashed border-slate-300 dark:border-slate-700 rounded-xl p-8 mb-4 hover:bg-slate-50 dark:hover:bg-slate-800 transition-colors cursor-pointer">
                <i data-lucide="upload-cloud" class="w-10 h-10 text-slate-400 mx-auto mb-2"></i>
                <p class="text-sm text-slate-600 dark:text-slate-400">Click to upload CSV or Excel</p>
            </div>
            <a href="#" class="text-xs text-brand-600 hover:underline mb-6 block">Download Template</a>
            <button class="w-full py-2 bg-brand-600 text-white font-bold rounded-lg hover:bg-brand-700 transition-colors">Process File</button>
        </div>
    </div>



    <script>
        lucide.createIcons();

    </script>
    <?php include '../includes/dashboard_scripts.php'; ?>
    </script>
</body>
</html>
