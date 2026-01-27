<?php
require_once '../includes/functions.php';
require_login();

if (!in_array($_SESSION['role'], ['super_admin', 'company_admin', 'hr_manager'])) {
    header('Location: index.php');
    exit;
}

$company_id = $_SESSION['company_id'];

// Fetch categories
$stmt = $pdo->prepare("SELECT id, name FROM salary_categories WHERE company_id = ? ORDER BY name");
$stmt->execute([$company_id]);
$categories = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Fetch leave types
$stmt = $pdo->prepare("SELECT id, name, is_system FROM leave_types WHERE company_id = ? AND is_active = 1 ORDER BY is_system DESC, name");
$stmt->execute([$company_id]);
$leave_types = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Fetch existing policies
$stmt = $pdo->prepare("
    SELECT lp.*, lt.name as leave_type_name, sc.name as category_name
    FROM leave_policies lp
    JOIN leave_types lt ON lp.leave_type_id = lt.id
    LEFT JOIN salary_categories sc ON lp.category_id = sc.id
    WHERE lp.company_id = ?
    ORDER BY COALESCE(sc.name, 'All Categories'), lt.name
");
$stmt->execute([$company_id]);
$policies = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Leave Policy | MiPayMaster</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script>
        tailwind.config = {
            darkMode: 'class',
            theme: { extend: { colors: { brand: { 50:'#f0f9ff',100:'#e0f2fe',200:'#bae6fd',300:'#7dd3fc',400:'#38bdf8',500:'#0ea5e9',600:'#0284c7',700:'#0369a1',800:'#075985',900:'#0c4a6e' }}}}
        }
    </script>
    <script src="https://unpkg.com/lucide@latest"></script>
    <script src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js" defer></script>
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap');
        body { font-family: 'Inter', sans-serif; }
        [x-cloak] { display: none !important; }
        .form-input { @apply w-full rounded-lg border-slate-300 dark:border-slate-700 bg-white dark:bg-slate-800 text-slate-900 dark:text-white focus:ring-brand-500 focus:border-brand-500 transition-colors shadow-sm text-sm px-3 py-2; }
        .form-label { @apply block text-xs font-semibold text-slate-700 dark:text-slate-300 mb-1.5 uppercase tracking-wide; }
        .sentence-case { text-transform: capitalize; }
    </style>
</head>
<body class="flex h-screen overflow-hidden bg-slate-50 dark:bg-slate-900 text-slate-900 dark:text-slate-100" x-data="leavePolicyApp()">
    
    <?php include '../includes/dashboard_sidebar.php'; ?>
    
    <div class="flex-1 flex flex-col h-full overflow-hidden">
        <?php $page_title = 'Leave Policy'; include '../includes/dashboard_header.php'; ?>
        <?php $current_tab = 'leave_policy'; include '../includes/hr_header.php'; ?>
        
        <main class="flex-1 overflow-y-auto p-6 lg:p-8">
            
            <!-- Page Header -->
            <div class="flex flex-col md:flex-row justify-between items-start md:items-center gap-4 mb-6">
                <div>
                    <h1 class="text-2xl font-bold text-slate-900 dark:text-white">Leave Policy Configuration</h1>
                    <p class="text-sm text-slate-500 mt-1">Define leave entitlements per employee category.</p>
                </div>
                <div class="flex gap-2">
                    <button @click="initBalances()" class="px-4 py-2 text-sm font-bold bg-amber-500 text-white rounded-lg hover:bg-amber-600 transition-colors flex items-center gap-2">
                        <i data-lucide="refresh-cw" class="w-4 h-4"></i> Apply to Employees
                    </button>
                    <button @click="showAddType = true" class="px-4 py-2 text-sm font-bold bg-brand-600 text-white rounded-lg hover:bg-brand-700 transition-colors flex items-center gap-2">
                        <i data-lucide="plus" class="w-4 h-4"></i> Add Leave Type
                    </button>
                </div>
            </div>
            
            <!-- Leave Types Summary -->
            <div class="bg-white dark:bg-slate-950 rounded-xl border border-slate-200 dark:border-slate-800 p-5 mb-6">
                <h2 class="text-sm font-bold text-slate-700 dark:text-slate-300 mb-3 uppercase tracking-wide">Leave Types</h2>
                <div class="flex flex-wrap gap-2">
                    <template x-for="lt in leaveTypes" :key="lt.id">
                        <span class="px-3 py-1.5 rounded-full text-xs font-bold"
                              :class="lt.is_system == 1 ? 'bg-brand-100 text-brand-700 dark:bg-brand-900/50 dark:text-brand-300' : 'bg-slate-100 text-slate-600 dark:bg-slate-800 dark:text-slate-400'"
                              x-text="lt.name"></span>
                    </template>
                </div>
            </div>
            
            <!-- Policy Entry Form -->
            <div class="bg-white dark:bg-slate-950 rounded-xl border border-slate-200 dark:border-slate-800 p-5 mb-6">
                <h2 class="text-sm font-bold text-slate-700 dark:text-slate-300 mb-4 uppercase tracking-wide">Add/Update Policy</h2>
                <div class="grid grid-cols-1 md:grid-cols-5 gap-4">
                    <div>
                        <label class="form-label">Category</label>
                        <select class="form-input" x-model="newPolicy.category_id">
                            <option value="">All Categories</option>
                            <?php foreach ($categories as $cat): ?>
                            <option value="<?= $cat['id'] ?>"><?= htmlspecialchars($cat['name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div>
                        <label class="form-label">Leave Type</label>
                        <select class="form-input" x-model="newPolicy.leave_type_id">
                            <option value="">Select...</option>
                            <template x-for="lt in leaveTypes" :key="lt.id">
                                <option :value="lt.id" x-text="lt.name"></option>
                            </template>
                        </select>
                    </div>
                    <div>
                        <label class="form-label">Days/Year</label>
                        <input type="number" class="form-input" x-model="newPolicy.days_per_year" min="0" max="365">
                    </div>
                    <div>
                        <label class="form-label">Carry Over Days</label>
                        <input type="number" class="form-input" x-model="newPolicy.max_carry_over_days" min="0">
                    </div>
                    <div class="flex items-end">
                        <button @click="savePolicy()" class="w-full py-2.5 bg-brand-600 text-white font-bold rounded-lg hover:bg-brand-700 transition-colors">
                            Save Policy
                        </button>
                    </div>
                </div>
            </div>
            
            <!-- Policies Table -->
            <div class="bg-white dark:bg-slate-950 rounded-xl border border-slate-200 dark:border-slate-800 overflow-hidden">
                <div class="p-4 border-b border-slate-200 dark:border-slate-800 bg-slate-50 dark:bg-slate-900">
                    <h2 class="text-sm font-bold text-slate-700 dark:text-slate-300 uppercase tracking-wide">Configured Policies</h2>
                </div>
                <table class="w-full text-left text-sm">
                    <thead class="bg-slate-50 dark:bg-slate-900 border-b border-slate-200 dark:border-slate-800 text-slate-500">
                        <tr>
                            <th class="px-6 py-3 font-medium">Category</th>
                            <th class="px-6 py-3 font-medium">Leave Type</th>
                            <th class="px-6 py-3 font-medium text-center">Days/Year</th>
                            <th class="px-6 py-3 font-medium text-center">Carry Over</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
                        <?php if (empty($policies)): ?>
                        <tr><td colspan="4" class="px-6 py-8 text-center text-slate-500">No policies configured yet.</td></tr>
                        <?php else: foreach ($policies as $pol): ?>
                        <tr class="hover:bg-slate-50 dark:hover:bg-slate-900/50">
                            <td class="px-6 py-4 font-medium text-slate-900 dark:text-white"><?= $pol['category_name'] ?: 'All Categories' ?></td>
                            <td class="px-6 py-4 text-slate-600 dark:text-slate-300"><?= htmlspecialchars($pol['leave_type_name']) ?></td>
                            <td class="px-6 py-4 text-center font-bold"><?= $pol['days_per_year'] ?></td>
                            <td class="px-6 py-4 text-center"><?= $pol['max_carry_over_days'] ?: '-' ?></td>
                        </tr>
                        <?php endforeach; endif; ?>
                    </tbody>
                </table>
            </div>
            
        </main>
    </div>
    
    <!-- Add Leave Type Modal -->
    <div x-show="showAddType" x-cloak class="fixed inset-0 z-50 flex items-center justify-center bg-black/50 backdrop-blur-sm p-4">
        <div @click.outside="showAddType = false" class="bg-white dark:bg-slate-900 rounded-xl shadow-2xl w-full max-w-sm border border-slate-200 dark:border-slate-800 overflow-hidden">
            <div class="p-5 border-b border-slate-200 dark:border-slate-800 bg-slate-50 dark:bg-slate-900/50">
                <h3 class="text-lg font-bold text-slate-900 dark:text-white">Add Custom Leave Type</h3>
            </div>
            <div class="p-5 space-y-4">
                <div>
                    <label class="form-label">Leave Type Name</label>
                    <input type="text" class="form-input sentence-case" x-model="newTypeName" placeholder="e.g. Study leave" @input="newTypeName = $event.target.value.charAt(0).toUpperCase() + $event.target.value.slice(1).toLowerCase()">
                    <p class="text-xs text-slate-400 mt-1">Will be saved in sentence case.</p>
                </div>
                <button @click="addLeaveType()" class="w-full py-2.5 bg-brand-600 text-white font-bold rounded-lg hover:bg-brand-700 transition-colors">
                    Add Type
                </button>
            </div>
        </div>
    </div>
    
    <script>
        function leavePolicyApp() {
            return {
                leaveTypes: <?= json_encode($leave_types) ?>,
                showAddType: false,
                newTypeName: '',
                newPolicy: {
                    category_id: '',
                    leave_type_id: '',
                    days_per_year: 0,
                    max_carry_over_days: 0
                },
                
                async addLeaveType() {
                    if (!this.newTypeName.trim()) return alert('Name is required');
                    const fd = new FormData();
                    fd.append('action', 'add_leave_type');
                    fd.append('name', this.newTypeName.trim());
                    
                    const res = await fetch('../ajax/leave_operations.php', { method: 'POST', body: fd });
                    const data = await res.json();
                    
                    if (data.status) {
                        this.leaveTypes.push({ id: data.id, name: this.newTypeName.charAt(0).toUpperCase() + this.newTypeName.slice(1).toLowerCase(), is_system: 0 });
                        this.newTypeName = '';
                        this.showAddType = false;
                        alert(data.message);
                    } else {
                        alert(data.message);
                    }
                },
                
                async savePolicy() {
                    if (!this.newPolicy.leave_type_id) return alert('Select a leave type');
                    
                    const fd = new FormData();
                    fd.append('action', 'save_policy');
                    fd.append('category_id', this.newPolicy.category_id);
                    fd.append('leave_type_id', this.newPolicy.leave_type_id);
                    fd.append('days_per_year', this.newPolicy.days_per_year);
                    fd.append('max_carry_over_days', this.newPolicy.max_carry_over_days);
                    
                    const res = await fetch('../ajax/leave_operations.php', { method: 'POST', body: fd });
                    const data = await res.json();
                    
                    if (data.status) {
                        alert(data.message);
                        location.reload();
                    } else {
                        alert(data.message);
                    }
                },
                
                async initBalances() {
                    if (!confirm('This will assign leave balances to all active employees based on their category policies. Continue?')) return;
                    
                    const fd = new FormData();
                    fd.append('action', 'init_balances');
                    
                    const res = await fetch('../ajax/leave_operations.php', { method: 'POST', body: fd });
                    const data = await res.json();
                    alert(data.message);
                }
            }
        }
        lucide.createIcons();
    </script>
    <?php include '../includes/dashboard_scripts.php'; ?>
</body>
</html>
