<?php
require_once '../includes/functions.php';
require_once '../includes/payroll_lock.php';
require_login();

// 1. Verify Role: Admin OR HR
if (!in_array($_SESSION['role'], ['super_admin', 'company_admin', 'hr_manager'])) {
    redirect('../dashboard/index.php');
}

$company_id = $_SESSION['company_id'];

?>
<!DOCTYPE html>
<html lang="en" class="h-full">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Leave Management - MiPayMaster</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://unpkg.com/lucide@latest"></script>
    <script src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js" defer></script>
    <script>
        tailwind.config = {
            darkMode: 'class',
            theme: {
                extend: {
                    fontFamily: { sans: ['Inter', 'sans-serif'] },
                    colors: { brand: { 50: '#eef2ff', 500: '#6366f1', 600: '#4f46e5', 700: '#4338ca' } }
                }
            }
        }
    </script>
    <style>@import url('https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap'); body { font-family: 'Inter', sans-serif; } [x-cloak] { display: none !important; }</style>
</head>
<body class="flex h-screen overflow-hidden bg-slate-50 dark:bg-slate-900 text-slate-900 dark:text-slate-100 transition-colors duration-300" 
      x-data="leavesApp()">

    <?php include '../includes/dashboard_sidebar.php'; ?>

    <div class="flex-1 flex flex-col h-full overflow-hidden w-full relative">
        <!-- Header -->
        <!-- Header -->
        <?php $page_title = 'Leave Management'; include '../includes/dashboard_header.php'; ?>
        
        <?php $current_tab = 'leaves'; include '../includes/hr_header.php'; ?>

        <main class="flex-1 overflow-y-auto p-6 lg:p-8">
            <!-- Tabs -->
            <div class="flex justify-between items-center border-b border-slate-200 dark:border-slate-800 mb-6">
                <div class="flex gap-4">
                    <button @click="tab = 'pending'; fetchLeaves()" :class="tab === 'pending' ? 'border-brand-600 text-brand-600 dark:text-brand-400' : 'border-transparent text-slate-500 hover:text-slate-700 dark:hover:text-slate-300'" class="px-4 py-2 text-sm font-bold border-b-2 transition-colors">Pending Requests</button>
                    <button @click="tab = 'approved'; fetchLeaves()" :class="tab === 'approved' ? 'border-brand-600 text-brand-600 dark:text-brand-400' : 'border-transparent text-slate-500 hover:text-slate-700 dark:hover:text-slate-300'" class="px-4 py-2 text-sm font-bold border-b-2 transition-colors">Approved</button>
                    <button @click="tab = 'rejected'; fetchLeaves()" :class="tab === 'rejected' ? 'border-brand-600 text-brand-600 dark:text-brand-400' : 'border-transparent text-slate-500 hover:text-slate-700 dark:hover:text-slate-300'" class="px-4 py-2 text-sm font-bold border-b-2 transition-colors">Rejected</button>
                    <button @click="tab = 'history'; fetchLeaves()" :class="tab === 'history' ? 'border-brand-600 text-brand-600 dark:text-brand-400' : 'border-transparent text-slate-500 hover:text-slate-700 dark:hover:text-slate-300'" class="px-4 py-2 text-sm font-bold border-b-2 transition-colors">All History</button>
                </div>
                <div class="flex gap-2 mb-2">
                    <button @click="exportCSV()" class="px-3 py-1.5 bg-green-600 hover:bg-green-700 text-white rounded-lg text-xs font-bold flex items-center gap-1">
                        <i data-lucide="download" class="w-3 h-3"></i> Export CSV
                    </button>
                    <button @click="printLeaves()" class="px-3 py-1.5 bg-blue-600 hover:bg-blue-700 text-white rounded-lg text-xs font-bold flex items-center gap-1">
                        <i data-lucide="printer" class="w-3 h-3"></i> Print
                    </button>
                </div>
            </div>

            <!-- Table -->
            <div class="bg-white dark:bg-slate-950 rounded-xl border border-slate-200 dark:border-slate-800 shadow-sm overflow-hidden">
                <div class="overflow-x-auto">
                    <table class="w-full text-left text-sm">
                        <thead class="bg-slate-50 dark:bg-slate-900 border-b border-slate-200 dark:border-slate-800 text-slate-500">
                            <tr>
                                <th class="px-6 py-4 font-bold">Employee</th>
                                <th class="px-6 py-4 font-bold">Type</th>
                                <th class="px-6 py-4 font-bold">Dates</th>
                                <th class="px-6 py-4 font-bold">Duration</th>
                                <th class="px-6 py-4 font-bold">Reason</th>
                                <th class="px-6 py-4 font-bold text-center">Status</th>
                                <th class="px-6 py-4 font-bold text-right">Actions</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
                            <template x-for="leave in leaves" :key="leave.id">
                                <tr class="hover:bg-slate-50 dark:hover:bg-slate-900/50">
                                    <td class="px-6 py-4">
                                        <div class="font-bold text-slate-900 dark:text-white" x-text="leave.first_name + ' ' + leave.last_name"></div>
                                        <div class="text-xs text-slate-400" x-text="leave.email"></div>
                                    </td>
                                    <td class="px-6 py-4">
                                        <span class="px-2 py-1 rounded-full bg-slate-100 dark:bg-slate-800 text-xs font-bold" x-text="leave.leave_type"></span>
                                    </td>
                                    <td class="px-6 py-4 text-slate-600 dark:text-slate-300">
                                        <div x-text="formatDate(leave.start_date)"></div>
                                        <div class="text-xs text-slate-400">to <span x-text="formatDate(leave.end_date)"></span></div>
                                    </td>
                                    <td class="px-6 py-4 text-slate-600 dark:text-slate-300">
                                        <span x-text="calcDuration(leave.start_date, leave.end_date) + ' days'"></span>
                                    </td>
                                    <td class="px-6 py-4">
                                        <p class="text-xs text-slate-500 truncate max-w-[200px]" x-text="leave.reason" :title="leave.reason"></p>
                                    </td>
                                    <td class="px-6 py-4 text-center">
                                        <span class="px-2 py-1 rounded text-xs font-bold capitalize"
                                              :class="{
                                                  'bg-yellow-50 text-yellow-700 border border-yellow-200': leave.status === 'pending',
                                                  'bg-green-50 text-green-700 border border-green-200': leave.status === 'approved',
                                                  'bg-red-50 text-red-700 border border-red-200': leave.status === 'rejected'
                                              }" x-text="leave.status"></span>
                                    </td>
                                    <td class="px-6 py-4 text-right">
                                        <div class="flex items-center justify-end gap-2" x-show="leave.status === 'pending'">
                                            <button @click="approveLeave(leave.id)" class="px-3 py-1.5 bg-green-600 hover:bg-green-700 text-white rounded-lg text-xs font-bold shadow-sm transition-colors flex items-center gap-1">
                                                <i data-lucide="check" class="w-3 h-3"></i> Approve
                                            </button>
                                            <button @click="rejectLeave(leave.id)" class="px-3 py-1.5 bg-red-600 hover:bg-red-700 text-white rounded-lg text-xs font-bold shadow-sm transition-colors flex items-center gap-1">
                                                <i data-lucide="x" class="w-3 h-3"></i> Reject
                                            </button>
                                        </div>
                                        <div x-show="leave.status !== 'pending'" class="text-xs text-slate-400 italic">
                                            Processed
                                        </div>
                                    </td>
                                </tr>
                            </template>
                            <tr x-show="leaves.length === 0">
                                <td colspan="7" class="px-6 py-8 text-center text-slate-500">No leave requests found.</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </main>
    </div>

    <script>
        function leavesApp() {
            return {
                tab: 'pending',
                leaves: [],
                
                init() {
                    this.fetchLeaves();
                    lucide.createIcons();
                },

                formatDate(d) {
                    if(!d) return '';
                    return new Date(d).toLocaleDateString();
                },
                
                calcDuration(start, end) {
                   const d1 = new Date(start);
                   const d2 = new Date(end);
                   const diffTime = Math.abs(d2 - d1);
                   return Math.ceil(diffTime / (1000 * 60 * 60 * 24)) + 1; 
                },

                async fetchLeaves() {
                    const fd = new FormData();
                    fd.append('action', 'fetch_leaves');
                    fd.append('filter', this.tab);
                    
                    try {
                        const res = await fetch('../ajax/leave_operations.php', { method: 'POST', body: fd });
                        const data = await res.json();
                        if (data.status) {
                            this.leaves = data.leaves;
                            this.$nextTick(() => lucide.createIcons());
                        }
                    } catch(e) { console.error(e); }
                },

                async approveLeave(id) {
                    if (!confirm('Approve this leave request?')) return;
                    this.processLeave(id, 'approve_leave');
                },

                async rejectLeave(id) {
                    if (!confirm('Reject this leave request?')) return;
                    this.processLeave(id, 'reject_leave');
                },

                async processLeave(id, action) {
                    const fd = new FormData();
                    fd.append('action', action);
                    fd.append('leave_id', id);
                    
                    try {
                        const res = await fetch('../ajax/leave_operations.php', { method: 'POST', body: fd });
                        const data = await res.json();
                        alert(data.message);
                        if(data.status) this.fetchLeaves();
                    } catch(e) { alert('Error processing request.'); }
                },

                exportCSV() {
                    if (this.leaves.length === 0) {
                        alert('No data to export.');
                        return;
                    }
                    
                    const headers = ['Employee', 'Email', 'Leave Type', 'Start Date', 'End Date', 'Duration (Days)', 'Reason', 'Status'];
                    const rows = this.leaves.map(l => [
                        `${l.first_name} ${l.last_name}`,
                        l.email,
                        l.leave_type,
                        l.start_date,
                        l.end_date,
                        this.calcDuration(l.start_date, l.end_date),
                        `"${(l.reason || '').replace(/"/g, '""')}"`,
                        l.status
                    ]);
                    
                    let csv = headers.join(',') + '\n';
                    rows.forEach(r => csv += r.join(',') + '\n');
                    
                    const blob = new Blob([csv], { type: 'text/csv' });
                    const url = URL.createObjectURL(blob);
                    const a = document.createElement('a');
                    a.href = url;
                    a.download = `leave_report_${this.tab}_${new Date().toISOString().split('T')[0]}.csv`;
                    a.click();
                    URL.revokeObjectURL(url);
                },

                printLeaves() {
                    if (this.leaves.length === 0) {
                        alert('No data to print.');
                        return;
                    }
                    
                    const tabTitle = this.tab.charAt(0).toUpperCase() + this.tab.slice(1);
                    let html = `
                        <html>
                        <head>
                            <title>Leave Report - ${tabTitle}</title>
                            <style>
                                body { font-family: Arial, sans-serif; padding: 20px; }
                                h1 { color: #333; font-size: 24px; margin-bottom: 5px; }
                                h3 { color: #666; font-size: 14px; margin-bottom: 20px; }
                                table { width: 100%; border-collapse: collapse; font-size: 12px; }
                                th, td { border: 1px solid #ddd; padding: 8px; text-align: left; }
                                th { background: #f5f5f5; font-weight: bold; }
                                .status-pending { color: #b45309; }
                                .status-approved { color: #15803d; }
                                .status-rejected { color: #dc2626; }
                            </style>
                        </head>
                        <body>
                            <h1>Leave Report - ${tabTitle}</h1>
                            <h3>Generated: ${new Date().toLocaleString()}</h3>
                            <table>
                                <thead>
                                    <tr>
                                        <th>Employee</th>
                                        <th>Leave Type</th>
                                        <th>Start Date</th>
                                        <th>End Date</th>
                                        <th>Duration</th>
                                        <th>Reason</th>
                                        <th>Status</th>
                                    </tr>
                                </thead>
                                <tbody>
                    `;
                    
                    this.leaves.forEach(l => {
                        html += `
                            <tr>
                                <td>${l.first_name} ${l.last_name}</td>
                                <td>${l.leave_type}</td>
                                <td>${this.formatDate(l.start_date)}</td>
                                <td>${this.formatDate(l.end_date)}</td>
                                <td>${this.calcDuration(l.start_date, l.end_date)} days</td>
                                <td>${l.reason || '-'}</td>
                                <td class="status-${l.status}">${l.status}</td>
                            </tr>
                        `;
                    });
                    
                    html += `</tbody></table></body></html>`;
                    
                    const printWindow = window.open('', '_blank');
                    printWindow.document.write(html);
                    printWindow.document.close();
                    printWindow.print();
                }
            }
        }

    </script>
    <?php include '../includes/dashboard_scripts.php'; ?>
</body>
</html>
