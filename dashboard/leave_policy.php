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
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap');
        body { font-family: 'Inter', sans-serif; }
        .form-input { width: 100%; border-radius: 0.5rem; border: 1px solid #cbd5e1; background: white; padding: 0.5rem 0.75rem; font-size: 0.875rem; transition: all 0.2s; }
        .form-input:focus { outline: none; border-color: #0ea5e9; box-shadow: 0 0 0 2px rgba(14, 165, 233, 0.2); }
        .form-label { display: block; font-size: 0.75rem; font-weight: 600; color: #475569; margin-bottom: 0.375rem; text-transform: uppercase; letter-spacing: 0.05em; }
        .modal { display: none; position: fixed; inset: 0; z-index: 50; align-items: center; justify-content: center; background: rgba(0,0,0,0.5); backdrop-filter: blur(4px); padding: 1rem; }
        .modal.active { display: flex; }
        .dark .form-input { background: #1e293b; border-color: #334155; color: white; }
    </style>
</head>
<body class="flex h-screen overflow-hidden bg-slate-50 dark:bg-slate-900 text-slate-900 dark:text-slate-100">
    
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
                    <button onclick="initBalances()" class="px-4 py-2 text-sm font-bold bg-amber-500 text-white rounded-lg hover:bg-amber-600 transition-colors flex items-center gap-2">
                        <i data-lucide="refresh-cw" class="w-4 h-4"></i> Apply to Employees
                    </button>
                    <button onclick="openAddTypeModal()" class="px-4 py-2 text-sm font-bold bg-brand-600 text-white rounded-lg hover:bg-brand-700 transition-colors flex items-center gap-2">
                        <i data-lucide="plus" class="w-4 h-4"></i> Add Leave Type
                    </button>
                </div>
            </div>
            
            <!-- Leave Types Summary -->
            <div class="bg-white dark:bg-slate-950 rounded-xl border border-slate-200 dark:border-slate-800 p-5 mb-6">
                <h2 class="text-sm font-bold text-slate-700 dark:text-slate-300 mb-3 uppercase tracking-wide">Leave Types</h2>
                <div id="leave-types-container" class="flex flex-wrap gap-2">
                    <?php foreach ($leave_types as $lt): ?>
                    <span class="leave-type-badge px-3 py-1.5 rounded-full text-xs font-bold inline-flex items-center gap-1.5 <?= $lt['is_system'] ? 'bg-brand-100 text-brand-700 dark:bg-brand-900/50 dark:text-brand-300' : 'bg-slate-100 text-slate-600 dark:bg-slate-800 dark:text-slate-400' ?>" data-id="<?= $lt['id'] ?>" data-system="<?= $lt['is_system'] ?>">
                        <span><?= htmlspecialchars($lt['name']) ?></span>
                        <?php if (!$lt['is_system']): ?>
                        <button onclick="deleteLeaveType(<?= $lt['id'] ?>, '<?= htmlspecialchars($lt['name']) ?>')" class="ml-1 text-red-500 hover:text-red-700" title="Delete">
                            <i data-lucide="x" class="w-3 h-3"></i>
                        </button>
                        <?php endif; ?>
                    </span>
                    <?php endforeach; ?>
                </div>
            </div>
            
            <!-- Policy Entry Form -->
            <div class="bg-white dark:bg-slate-950 rounded-xl border border-slate-200 dark:border-slate-800 p-5 mb-6">
                <h2 class="text-sm font-bold text-slate-700 dark:text-slate-300 mb-4 uppercase tracking-wide">Add/Update Policy</h2>
                <form id="policy-form" class="grid grid-cols-1 md:grid-cols-5 gap-4">
                    <div>
                        <label class="form-label">Category</label>
                        <select id="policy-category" class="form-input">
                            <option value="">All Categories</option>
                            <?php foreach ($categories as $cat): ?>
                            <option value="<?= $cat['id'] ?>"><?= htmlspecialchars($cat['name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div>
                        <label class="form-label">Leave Type</label>
                        <select id="policy-leave-type" class="form-input" required>
                            <option value="">Select...</option>
                            <?php foreach ($leave_types as $lt): ?>
                            <option value="<?= $lt['id'] ?>"><?= htmlspecialchars($lt['name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div>
                        <label class="form-label">Days/Year</label>
                        <input type="number" id="policy-days" class="form-input" min="0" max="365" value="0">
                    </div>
                    <div>
                        <label class="form-label">Carry Over Days</label>
                        <input type="number" id="policy-carry" class="form-input" min="0" value="0">
                    </div>
                    <div class="flex items-end">
                        <button type="submit" class="w-full py-2.5 bg-brand-600 text-white font-bold rounded-lg hover:bg-brand-700 transition-colors">
                            Save Policy
                        </button>
                    </div>
                </form>
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
                            <th class="px-6 py-3 font-medium text-center">Actions</th>
                        </tr>
                    </thead>
                    <tbody id="policies-tbody" class="divide-y divide-slate-100 dark:divide-slate-800">
                        <?php if (empty($policies)): ?>
                        <tr><td colspan="5" class="px-6 py-8 text-center text-slate-500">No policies configured yet.</td></tr>
                        <?php else: foreach ($policies as $pol): ?>
                        <tr class="hover:bg-slate-50 dark:hover:bg-slate-900/50" data-policy-id="<?= $pol['id'] ?>">
                            <td class="px-6 py-4 font-medium text-slate-900 dark:text-white"><?= $pol['category_name'] ?: 'All Categories' ?></td>
                            <td class="px-6 py-4 text-slate-600 dark:text-slate-300"><?= htmlspecialchars($pol['leave_type_name']) ?></td>
                            <td class="px-6 py-4 text-center font-bold"><?= $pol['days_per_year'] ?></td>
                            <td class="px-6 py-4 text-center"><?= $pol['max_carry_over_days'] ?: '-' ?></td>
                            <td class="px-6 py-4 text-center">
                                <div class="flex justify-center gap-2">
                                    <button onclick="editPolicy(<?= $pol['id'] ?>, '<?= htmlspecialchars($pol['category_name'] ?: 'All Categories') ?>', '<?= htmlspecialchars($pol['leave_type_name']) ?>', <?= $pol['days_per_year'] ?>, <?= $pol['max_carry_over_days'] ?>)" class="p-1.5 text-brand-600 hover:bg-brand-50 dark:hover:bg-brand-900/20 rounded-lg" title="Edit">
                                        <i data-lucide="pencil" class="w-4 h-4"></i>
                                    </button>
                                    <button onclick="deletePolicy(<?= $pol['id'] ?>)" class="p-1.5 text-red-600 hover:bg-red-50 dark:hover:bg-red-900/20 rounded-lg" title="Delete">
                                        <i data-lucide="trash-2" class="w-4 h-4"></i>
                                    </button>
                                </div>
                            </td>
                        </tr>
                        <?php endforeach; endif; ?>
                    </tbody>
                </table>
            </div>
            
        </main>
    </div>
    
    <!-- Add Leave Type Modal -->
    <div id="add-type-modal" class="modal">
        <div class="bg-white dark:bg-slate-900 rounded-xl shadow-2xl w-full max-w-sm border border-slate-200 dark:border-slate-800 overflow-hidden">
            <div class="p-5 border-b border-slate-200 dark:border-slate-800 bg-slate-50 dark:bg-slate-900/50">
                <h3 class="text-lg font-bold text-slate-900 dark:text-white">Add Custom Leave Type</h3>
            </div>
            <div class="p-5 space-y-4">
                <div>
                    <label class="form-label">Leave Type Name</label>
                    <input type="text" id="new-type-name" class="form-input" placeholder="e.g. Study leave">
                    <p class="text-xs text-slate-400 mt-1">Will be saved in sentence case.</p>
                </div>
                <div class="flex gap-2">
                    <button onclick="closeModal('add-type-modal')" class="flex-1 py-2.5 border border-slate-300 dark:border-slate-700 text-slate-700 dark:text-slate-300 font-bold rounded-lg hover:bg-slate-50 dark:hover:bg-slate-800 transition-colors">Cancel</button>
                    <button onclick="addLeaveType()" class="flex-1 py-2.5 bg-brand-600 text-white font-bold rounded-lg hover:bg-brand-700 transition-colors">Add Type</button>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Edit Policy Modal -->
    <div id="edit-policy-modal" class="modal">
        <div class="bg-white dark:bg-slate-900 rounded-xl shadow-2xl w-full max-w-md border border-slate-200 dark:border-slate-800 overflow-hidden">
            <div class="p-5 border-b border-slate-200 dark:border-slate-800 bg-slate-50 dark:bg-slate-900/50">
                <h3 class="text-lg font-bold text-slate-900 dark:text-white">Edit Leave Policy</h3>
            </div>
            <div class="p-5 space-y-4">
                <input type="hidden" id="edit-policy-id">
                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <label class="form-label">Category</label>
                        <input type="text" id="edit-category-name" class="form-input bg-slate-100" disabled>
                    </div>
                    <div>
                        <label class="form-label">Leave Type</label>
                        <input type="text" id="edit-leave-type-name" class="form-input bg-slate-100" disabled>
                    </div>
                </div>
                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <label class="form-label">Days/Year</label>
                        <input type="number" id="edit-days" class="form-input" min="0" max="365">
                    </div>
                    <div>
                        <label class="form-label">Carry Over Days</label>
                        <input type="number" id="edit-carry" class="form-input" min="0">
                    </div>
                </div>
                <!-- Guide Note -->
                <div class="bg-blue-50 dark:bg-blue-900/20 border border-blue-200 dark:border-blue-800 rounded-lg p-3 text-xs text-blue-700 dark:text-blue-400">
                    <i data-lucide="info" class="w-4 h-4 inline-block mr-1"></i>
                    <strong>Note:</strong> When you save changes, click the button <i data-lucide="refresh-cw" class="w-3 h-3 inline-block mx-1"></i><strong>Apply to Employees</strong> to effect final change.
                </div>
                <div class="flex gap-2 pt-2">
                    <button onclick="closeModal('edit-policy-modal')" class="flex-1 py-2.5 border border-slate-300 dark:border-slate-700 text-slate-700 dark:text-slate-300 font-bold rounded-lg hover:bg-slate-50 dark:hover:bg-slate-800 transition-colors">Cancel</button>
                    <button onclick="updatePolicy()" class="flex-1 py-2.5 bg-brand-600 text-white font-bold rounded-lg hover:bg-brand-700 transition-colors">Save Changes</button>
                </div>
            </div>
        </div>
    </div>
    
    <script>
        // Initialize Lucide icons
        lucide.createIcons();
        
        // Modal functions
        function openModal(id) {
            document.getElementById(id).classList.add('active');
        }
        
        function closeModal(id) {
            document.getElementById(id).classList.remove('active');
        }
        
        function openAddTypeModal() {
            document.getElementById('new-type-name').value = '';
            openModal('add-type-modal');
        }
        
        // Add Leave Type
        async function addLeaveType() {
            var name = document.getElementById('new-type-name').value.trim();
            if (!name) {
                alert('Name is required');
                return;
            }
            
            // Sentence case
            name = name.charAt(0).toUpperCase() + name.slice(1).toLowerCase();
            
            var fd = new FormData();
            fd.append('action', 'add_leave_type');
            fd.append('name', name);
            
            try {
                var res = await fetch('../ajax/leave_operations.php', { method: 'POST', body: fd });
                var data = await res.json();
                
                if (data.status) {
                    alert(data.message);
                    location.reload();
                } else {
                    alert(data.message);
                }
            } catch (e) {
                alert('Error: ' + e.message);
            }
        }
        
        // Delete Leave Type
        async function deleteLeaveType(typeId, typeName) {
            if (!confirm('Delete leave type "' + typeName + '"? This cannot be undone.')) return;
            
            var fd = new FormData();
            fd.append('action', 'delete_leave_type');
            fd.append('type_id', typeId);
            
            try {
                var res = await fetch('../ajax/leave_operations.php', { method: 'POST', body: fd });
                var data = await res.json();
                
                if (data.status) {
                    alert(data.message);
                    location.reload();
                } else {
                    alert(data.message);
                }
            } catch (e) {
                alert('Error: ' + e.message);
            }
        }
        
        // Save Policy (form submit)
        document.getElementById('policy-form').addEventListener('submit', async function(e) {
            e.preventDefault();
            
            var leaveTypeId = document.getElementById('policy-leave-type').value;
            if (!leaveTypeId) {
                alert('Select a leave type');
                return;
            }
            
            var fd = new FormData();
            fd.append('action', 'save_policy');
            fd.append('category_id', document.getElementById('policy-category').value);
            fd.append('leave_type_id', leaveTypeId);
            fd.append('days_per_year', document.getElementById('policy-days').value);
            fd.append('max_carry_over_days', document.getElementById('policy-carry').value);
            
            try {
                var res = await fetch('../ajax/leave_operations.php', { method: 'POST', body: fd });
                var data = await res.json();
                
                if (data.status) {
                    alert(data.message);
                    location.reload();
                } else {
                    alert(data.message);
                }
            } catch (e) {
                alert('Error: ' + e.message);
            }
        });
        
        // Edit Policy
        function editPolicy(id, categoryName, leaveTypeName, daysPerYear, maxCarry) {
            document.getElementById('edit-policy-id').value = id;
            document.getElementById('edit-category-name').value = categoryName;
            document.getElementById('edit-leave-type-name').value = leaveTypeName;
            document.getElementById('edit-days').value = daysPerYear;
            document.getElementById('edit-carry').value = maxCarry;
            openModal('edit-policy-modal');
        }
        
        // Update Policy
        async function updatePolicy() {
            var fd = new FormData();
            fd.append('action', 'update_policy');
            fd.append('policy_id', document.getElementById('edit-policy-id').value);
            fd.append('days_per_year', document.getElementById('edit-days').value);
            fd.append('max_carry_over_days', document.getElementById('edit-carry').value);
            
            try {
                var res = await fetch('../ajax/leave_operations.php', { method: 'POST', body: fd });
                var data = await res.json();
                
                if (data.status) {
                    alert(data.message);
                    location.reload();
                } else {
                    alert(data.message);
                }
            } catch (e) {
                alert('Error: ' + e.message);
            }
        }
        
        // Delete Policy
        async function deletePolicy(policyId) {
            if (!confirm('Delete this policy? This cannot be undone.')) return;
            
            var fd = new FormData();
            fd.append('action', 'delete_policy');
            fd.append('policy_id', policyId);
            
            try {
                var res = await fetch('../ajax/leave_operations.php', { method: 'POST', body: fd });
                var data = await res.json();
                
                if (data.status) {
                    alert(data.message);
                    location.reload();
                } else {
                    alert(data.message);
                }
            } catch (e) {
                alert('Error: ' + e.message);
            }
        }
        
        // Initialize Balances
        async function initBalances() {
            if (!confirm('This will assign leave balances to all active employees based on their category policies. Continue?')) return;
            
            var fd = new FormData();
            fd.append('action', 'init_balances');
            
            try {
                var res = await fetch('../ajax/leave_operations.php', { method: 'POST', body: fd });
                var data = await res.json();
                alert(data.message);
            } catch (e) {
                alert('Error: ' + e.message);
            }
        }
        
        // Close modal on outside click
        document.querySelectorAll('.modal').forEach(function(modal) {
            modal.addEventListener('click', function(e) {
                if (e.target === modal) {
                    modal.classList.remove('active');
                }
            });
        });
    </script>
    <?php include '../includes/dashboard_scripts.php'; ?>
</body>
</html>
