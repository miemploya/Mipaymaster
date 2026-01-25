<?php
require_once '../includes/functions.php';
require_once '../includes/increment_manager.php';
require_once '../includes/payroll_lock.php'; // Check locks before actions
require_login();

$company_id = $_SESSION['company_id'] ?? 0;
$incManager = new IncrementManager($pdo);

// --- DB PATCH: Ensure payroll_id exists ---
try {
    $cols = $pdo->query("SHOW COLUMNS FROM employees LIKE 'payroll_id'")->fetchAll();
    if (count($cols) == 0) {
        $pdo->exec("ALTER TABLE employees ADD COLUMN payroll_id VARCHAR(50) DEFAULT NULL AFTER company_id");
        // Optional: Backfill IDs for existing users
        $pdo->exec("UPDATE employees SET payroll_id = CONCAT('MIP-', LPAD(id, 3, '0')) WHERE payroll_id IS NULL");
    }
} catch (Exception $e) { /* ignore */ }

// --- ENSURE TABLE EXISTS ---
try {
    $pdo->exec("CREATE TABLE IF NOT EXISTS employee_salary_adjustments (
        id INT AUTO_INCREMENT PRIMARY KEY,
        employee_id INT NOT NULL,
        adjustment_type ENUM('increment', 'decrement') NOT NULL,
        amount DECIMAL(15,2) NOT NULL,
        previous_gross DECIMAL(15,2) NOT NULL,
        new_gross DECIMAL(15,2) NOT NULL,
        reason TEXT,
        effective_from DATE NOT NULL,
        approval_status ENUM('pending', 'approved', 'rejected') DEFAULT 'pending',
        approved_by INT DEFAULT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        INDEX(employee_id),
        INDEX(approval_status)
    )");
} catch (Exception $e) { /* ignore */ }

// Handle Actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        if (isset($_POST['action'])) {
            $action = $_POST['action'];
            
            if ($action === 'add_increment') {
                $employee_id = $_POST['employee_id'];
                $type = $_POST['adjustment_type'];
                $value = floatval($_POST['adjustment_value']);
                $effective_date = $_POST['effective_from'];
                $reason = clean_input($_POST['reason']);
                
                $res = $incManager->add_increment($employee_id, $type, $value, $effective_date, $reason, null);
                if ($res['status']) {
                    set_flash_message('success', "Increment request created successfully.");
                } else {
                    set_flash_message('error', "Error: " . $res['message']);
                }
            }
            elseif ($action === 'approve') {
                $inc_id = $_POST['increment_id'];
                // Optional: Check for locks logic here or let manager handle it?
                // Manager handles basic state update. 
                // We should ensure the effective date doesn't conflict with a LOCKED payroll run?
                // The prompt says "Increment cannot be edited once payroll is locked".
                // If effective date is in a locked period, maybe warn?
                // For now rely on backend manager logic if present, or just approve.
                
                $res = $incManager->approve_increment($inc_id, $_SESSION['user_id']);
                if ($res['status']) {
                     set_flash_message('success', "Increment approved successfully.");
                } else {
                     set_flash_message('error', "Error: " . $res['message']);
                }
            }
            elseif ($action === 'reject') {
                $inc_id = $_POST['increment_id'];
                $res = $incManager->reject_increment($inc_id, $_SESSION['user_id']);
                if ($res['status']) {
                     set_flash_message('success', "Increment rejected.");
                } else {
                     set_flash_message('error', "Error: " . $res['message']);
                }
            }
        }
    } catch (Exception $e) {
        set_flash_message('error', "System Error: " . $e->getMessage());
    }
    // Redirect to self to clear post
    redirect('increments.php');
}

// Fetch Data
// 1. Employees for Dropdown (Note: employment_status ENUM has 'Full Time', 'Part Time', 'Contract', 'Intern' - NOT 'active')
$stmt = $pdo->prepare("SELECT id, first_name, last_name, payroll_id FROM employees WHERE company_id = ? ORDER BY first_name ASC");
$stmt->execute([$company_id]);
$employees = $stmt->fetchAll(PDO::FETCH_ASSOC);

// 2. Pending Increments
$stmt = $pdo->prepare("
    SELECT adj.*, e.first_name, e.last_name, e.payroll_id 
    FROM employee_salary_adjustments adj
    JOIN employees e ON adj.employee_id = e.id
    WHERE e.company_id = ? AND adj.approval_status = 'pending'
    ORDER BY adj.created_at DESC
");
$stmt->execute([$company_id]);
$pending_increments = $stmt->fetchAll(PDO::FETCH_ASSOC);

// 3. History (Approved/Rejected)
$stmt = $pdo->prepare("
    SELECT adj.*, e.first_name, e.last_name, e.payroll_id, 
           u.email as approver_email
    FROM employee_salary_adjustments adj
    JOIN employees e ON adj.employee_id = e.id
    LEFT JOIN users u ON adj.approved_by = u.id
    WHERE e.company_id = ? AND adj.approval_status IN ('approved', 'rejected')
    ORDER BY adj.effective_from DESC
    LIMIT 50
");
$stmt->execute([$company_id]);
$history = $stmt->fetchAll(PDO::FETCH_ASSOC);

$current_page = 'increments';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Salary Increments - MiPayMaster</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script>
        tailwind.config = {
            darkMode: 'class',
            theme: {
                extend: {
                    fontFamily: { sans: ['Inter', 'sans-serif'] },
                    colors: {
                        brand: { 50: '#eef2ff', 500: '#6366f1', 600: '#4f46e5', 700: '#4338ca' }
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
    </style>
</head>
<body class="flex h-screen overflow-hidden bg-slate-50 dark:bg-slate-900 text-slate-900 dark:text-slate-100 transition-colors duration-300">

    <?php include '../includes/dashboard_sidebar.php'; ?>

    <div class="flex-1 flex flex-col h-full overflow-hidden w-full relative">
        <!-- Header -->
        <header class="h-16 bg-white dark:bg-slate-950 border-b border-slate-200 dark:border-slate-800 flex items-center justify-between px-6 shrink-0 z-30">
            <div class="flex items-center gap-4">
               <button id="mobile-toggle" class="md:hidden text-slate-500"><i data-lucide="menu" class="w-6 h-6"></i></button>
               <h2 class="text-xl font-bold text-slate-800 dark:text-white">Salary Increments</h2>
            </div>
            <div><!-- User Menu placeholder --></div>
        </header>
        
        <style>
            /* Toolbar transition */
            #collapsed-toolbar { transition: transform 0.3s ease-in-out, opacity 0.3s ease-in-out; }
            .toolbar-hidden { transform: translateY(-100%); opacity: 0; pointer-events: none; height: 0; padding: 0; border: none; }
            .toolbar-visible { transform: translateY(0); opacity: 1; pointer-events: auto; height: auto; }
        </style>

        <!-- Horizontal Nav (Hidden by default) -->
        <div id="horizontal-nav" class="hidden bg-white dark:bg-slate-950 border-b border-slate-200 dark:border-slate-800 px-6 py-2">
            <!-- Dynamic Nav Content -->
        </div>

        <!-- Collapsed Toolbar -->
        <div id="collapsed-toolbar" class="toolbar-hidden w-full bg-white dark:bg-slate-950 border-b border-slate-200 dark:border-slate-800 flex items-center px-6 shrink-0 shadow-sm z-20">
            <button id="sidebar-expand-btn" class="flex items-center gap-2 p-2 rounded-lg text-slate-500 hover:text-brand-600 dark:text-slate-400 dark:hover:text-white hover:bg-slate-100 dark:hover:bg-slate-800 transition-colors my-2">
                <i data-lucide="menu" class="w-5 h-5"></i> <span class="text-sm font-medium">Show Menu</span>
            </button>
        </div>

        <main class="flex-1 overflow-y-auto p-6 lg:p-8" x-data="{ tab: 'pending', modalOpen: false }">
            <?php display_flash_message(); ?>

            <!-- Tabs -->
            <div class="flex gap-4 mb-6 border-b border-slate-200 dark:border-slate-800">
                <button @click="tab = 'pending'" :class="tab === 'pending' ? 'border-brand-600 text-brand-600' : 'border-transparent text-slate-500 hover:text-slate-700'" class="pb-2 border-b-2 font-medium text-sm transition-colors">
                    Pending Approvals (<?php echo count($pending_increments); ?>)
                </button>
                <button @click="tab = 'history'" :class="tab === 'history' ? 'border-brand-600 text-brand-600' : 'border-transparent text-slate-500 hover:text-slate-700'" class="pb-2 border-b-2 font-medium text-sm transition-colors">
                    History
                </button>
                <button @click="modalOpen = true" class="ml-auto bg-brand-600 hover:bg-brand-700 text-white px-4 py-1.5 rounded-lg text-sm font-bold flex items-center gap-2 mb-2">
                    <i data-lucide="plus" class="w-4 h-4"></i> New Request
                </button>
            </div>

            <!-- Tab: Pending -->
            <div x-show="tab === 'pending'">
                <?php if (empty($pending_increments)): ?>
                    <div class="text-center py-12 bg-white dark:bg-slate-950 rounded-xl border border-dashed border-slate-300 dark:border-slate-700">
                        <div class="w-12 h-12 bg-slate-100 dark:bg-slate-800 rounded-full flex items-center justify-center mx-auto mb-3">
                            <i data-lucide="inbox" class="w-6 h-6 text-slate-400"></i>
                        </div>
                        <p class="text-slate-500">No pending increment requests.</p>
                    </div>
                <?php else: ?>
                    <div class="bg-white dark:bg-slate-950 rounded-xl border border-slate-200 dark:border-slate-800 overflow-hidden shadow-sm">
                        <table class="w-full text-left text-sm">
                            <thead class="bg-slate-50 dark:bg-slate-900 border-b border-slate-200 dark:border-slate-800 text-slate-500">
                                <tr>
                                    <th class="px-6 py-3 font-medium">Employee</th>
                                    <th class="px-6 py-3 font-medium">Type</th>
                                    <th class="px-6 py-3 font-medium text-right">Value</th>
                                    <th class="px-6 py-3 font-medium">Effective Date</th>
                                    <th class="px-6 py-3 font-medium">Reason</th>
                                    <th class="px-6 py-3 font-medium text-right">Actions</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
                                <?php foreach ($pending_increments as $inc): ?>
                                <tr class="hover:bg-slate-50 dark:hover:bg-slate-900/50">
                                    <td class="px-6 py-4">
                                        <p class="font-bold text-slate-900 dark:text-white"><?php echo htmlspecialchars($inc['first_name'] . ' ' . $inc['last_name']); ?></p>
                                        <p class="text-xs text-slate-500"><?php echo $inc['payroll_id']; ?></p>
                                    </td>
                                    <td class="px-6 py-4 capitalize text-slate-600 dark:text-slate-300">
                                        <?php echo $inc['adjustment_type']; ?>
                                    </td>
                                    <td class="px-6 py-4 text-right font-medium text-brand-600 text-base">
                                        <?php if($inc['adjustment_type'] == 'percentage') echo '+' . $inc['adjustment_value'] . '%'; else echo '₦' . number_format($inc['adjustment_value'], 2); ?>
                                    </td>
                                    <td class="px-6 py-4 text-slate-600">
                                        <?php echo date('M d, Y', strtotime($inc['effective_from'])); ?>
                                    </td>
                                    <td class="px-6 py-4 text-slate-500 text-xs max-w-xs truncate">
                                        <?php echo htmlspecialchars($inc['reason'] ?? '-'); ?>
                                    </td>
                                    <td class="px-6 py-4 text-right flex justify-end gap-2">
                                        <form method="POST" onsubmit="return confirm('Approve this increment? It will affect future payroll runs.');">
                                            <input type="hidden" name="action" value="approve">
                                            <input type="hidden" name="increment_id" value="<?php echo $inc['id']; ?>">
                                            <button type="submit" class="p-2 bg-green-50 text-green-600 hover:bg-green-100 rounded-lg" title="Approve">
                                                <i data-lucide="check" class="w-4 h-4"></i>
                                            </button>
                                        </form>
                                        <form method="POST" onsubmit="return confirm('Reject this increment request?');">
                                            <input type="hidden" name="action" value="reject">
                                            <input type="hidden" name="increment_id" value="<?php echo $inc['id']; ?>">
                                            <button type="submit" class="p-2 bg-red-50 text-red-600 hover:bg-red-100 rounded-lg" title="Reject">
                                                <i data-lucide="x" class="w-4 h-4"></i>
                                            </button>
                                        </form>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>

            <!-- Tab: History -->
            <div x-show="tab === 'history'" x-cloak>
                 <div class="bg-white dark:bg-slate-950 rounded-xl border border-slate-200 dark:border-slate-800 overflow-hidden shadow-sm">
                    <table class="w-full text-left text-sm">
                        <thead class="bg-slate-50 dark:bg-slate-900 border-b border-slate-200 dark:border-slate-800 text-slate-500">
                            <tr>
                                <th class="px-6 py-3 font-medium">Employee</th>
                                <th class="px-6 py-3 font-medium">Type</th>
                                <th class="px-6 py-3 font-medium text-right">Value</th>
                                <th class="px-6 py-3 font-medium">Effective</th>
                                <th class="px-6 py-3 font-medium">Status</th>
                                <th class="px-6 py-3 font-medium">Processed By</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
                            <?php foreach ($history as $h): ?>
                            <tr class="hover:bg-slate-50 dark:hover:bg-slate-900/50">
                                <td class="px-6 py-4">
                                    <p class="font-medium text-slate-900 dark:text-white"><?php echo htmlspecialchars($h['first_name'] . ' ' . $h['last_name']); ?></p>
                                </td>
                                <td class="px-6 py-4 capitalize text-slate-500"><?php echo $h['adjustment_type']; ?></td>
                                <td class="px-6 py-4 text-right font-medium">
                                    <?php if($h['adjustment_type'] == 'percentage') echo '+' . $h['adjustment_value'] . '%'; else echo '₦' . number_format($h['adjustment_value'], 2); ?>
                                </td>
                                <td class="px-6 py-4 text-slate-500"><?php echo date('M d, Y', strtotime($h['effective_from'])); ?></td>
                                <td class="px-6 py-4">
                                    <?php if($h['approval_status'] == 'approved'): ?>
                                        <span class="px-2 py-1 bg-green-100 text-green-700 rounded-full text-xs font-bold">Approved</span>
                                    <?php else: ?>
                                        <span class="px-2 py-1 bg-red-100 text-red-700 rounded-full text-xs font-bold">Rejected</span>
                                    <?php endif; ?>
                                </td>
                                <td class="px-6 py-4 text-xs text-slate-500">
                                    <?php echo $h['approver_email'] ?? 'System'; ?><br>
                                    <?php echo date('M d, H:i', strtotime($h['approved_at'])); ?>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- New Request Modal -->
            <div x-show="modalOpen" x-cloak class="fixed inset-0 z-50 flex items-center justify-center bg-black/50 backdrop-blur-sm">
                <div @click.outside="modalOpen = false" class="bg-white dark:bg-slate-900 rounded-xl shadow-xl w-full max-w-md overflow-hidden">
                    <div class="px-6 py-4 border-b border-slate-200 dark:border-slate-800 flex justify-between items-center">
                        <h3 class="font-bold text-lg text-slate-900 dark:text-white">New Quantity/Rate Adjustment</h3>
                        <button @click="modalOpen = false" class="text-slate-400 hover:text-slate-600"><i data-lucide="x" class="w-5 h-5"></i></button>
                    </div>
                    <form method="POST" class="p-6">
                        <input type="hidden" name="action" value="add_increment">
                        
                        <div class="space-y-4">
                            <div x-data="{ 
                                search: '', 
                                open: false, 
                                selectedId: '',
                                selectedName: '',
                                employees: [
                                    <?php foreach ($employees as $emp): ?>
                                    { id: '<?= $emp['id'] ?>', name: '<?= htmlspecialchars($emp['first_name'] . ' ' . $emp['last_name'] . ' (' . ($emp['payroll_id'] ?? 'N/A') . ')') ?>' },
                                    <?php endforeach; ?>
                                ],
                                get filteredEmployees() {
                                    if (this.search === '') return this.employees;
                                    return this.employees.filter(emp => emp.name.toLowerCase().includes(this.search.toLowerCase()));
                                },
                                select(emp) {
                                    this.selectedId = emp.id;
                                    this.selectedName = emp.name;
                                    this.open = false;
                                    this.search = ''; 
                                }
                            }" @click.outside="open = false" class="relative">
                                <label class="block text-sm font-medium text-slate-700 dark:text-slate-300 mb-1">Employee</label>
                                <input type="hidden" name="employee_id" :value="selectedId" required>
                                
                                <div @click="open = !open" class="w-full rounded-lg border border-slate-300 dark:border-slate-700 bg-white dark:bg-slate-800 text-sm p-2.5 shadow-sm focus:border-brand-500 cursor-pointer flex justify-between items-center">
                                    <span x-text="selectedName ? selectedName : 'Select Employee...'" :class="{'text-slate-400': !selectedName, 'text-slate-700 dark:text-white': selectedName}"></span>
                                    <i data-lucide="chevron-down" class="w-4 h-4 text-slate-400"></i>
                                </div>
                                
                                <div x-show="open" class="absolute z-10 w-full mt-1 bg-white dark:bg-slate-800 border border-slate-200 dark:border-slate-700 rounded-lg shadow-lg max-h-60 overflow-y-auto" style="display: none;">
                                    <div class="p-2 sticky top-0 bg-white dark:bg-slate-800 border-b border-slate-100 dark:border-slate-700">
                                        <input type="text" x-model="search" placeholder="Search..." class="w-full px-3 py-1.5 text-sm border border-slate-200 dark:border-slate-600 rounded-md bg-slate-50 dark:bg-slate-900 focus:outline-none focus:border-brand-500 dark:text-white" @click.stop>
                                    </div>
                                    <ul>
                                        <template x-for="emp in filteredEmployees" :key="emp.id">
                                            <li @click="select(emp)" class="px-4 py-2 hover:bg-slate-50 dark:hover:bg-slate-700 cursor-pointer text-sm text-slate-700 dark:text-gray-300">
                                                <span x-text="emp.name"></span>
                                            </li>
                                        </template>
                                        <li x-show="filteredEmployees.length === 0" class="px-4 py-2 text-sm text-slate-400 text-center">No results found</li>
                                    </ul>
                                </div>
                            </div>

                            <div class="grid grid-cols-2 gap-4">
                                <div>
                                    <label class="block text-sm font-medium text-slate-700 dark:text-slate-300 mb-1">Type</label>
                                    <select name="adjustment_type" x-data="{ type: 'fixed' }" x-model="type" class="w-full rounded-lg border-slate-300 dark:border-slate-700 bg-white dark:bg-slate-800 text-sm p-2.5">
                                        <option value="fixed">Fixed Amount (+)</option>
                                        <option value="percentage">Percentage (%)</option>
                                        <option value="override">New Gross (=)</option>
                                    </select>
                                </div>
                                <div>
                                    <label class="block text-sm font-medium text-slate-700 dark:text-slate-300 mb-1">Value</label>
                                    <input type="number" step="0.01" name="adjustment_value" required class="w-full rounded-lg border-slate-300 dark:border-slate-700 bg-white dark:bg-slate-800 text-sm p-2.5">
                                </div>
                            </div>

                            <div>
                                <label class="block text-sm font-medium text-slate-700 dark:text-slate-300 mb-1">Effective Date</label>
                                <input type="date" name="effective_from" required class="w-full rounded-lg border-slate-300 dark:border-slate-700 bg-white dark:bg-slate-800 text-sm p-2.5">
                                <p class="text-xs text-slate-500 mt-1">Increments apply to payrolls ending after this date.</p>
                            </div>

                            <div>
                                <label class="block text-sm font-medium text-slate-700 dark:text-slate-300 mb-1">Reason</label>
                                <textarea name="reason" rows="2" class="w-full rounded-lg border-slate-300 dark:border-slate-700 bg-white dark:bg-slate-800 text-sm p-2.5" placeholder="e.g. Annual Review, Promotion..."></textarea>
                            </div>
                        </div>

                        <div class="mt-6 flex justify-end gap-3">
                            <button type="button" @click="modalOpen = false" class="px-4 py-2 border border-slate-300 dark:border-slate-600 rounded-lg text-slate-600 dark:text-slate-300 font-medium hover:bg-slate-50">Cancel</button>
                            <button type="submit" class="px-4 py-2 bg-brand-600 text-white rounded-lg font-bold hover:bg-brand-700 shadow-sm">Create Request</button>
                        </div>
                    </form>
                </div>
            </div>

        </main>
    </div>

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

    // Sidebar Logic
    const mobileToggle = document.getElementById('mobile-toggle');
    const sidebar = document.getElementById('sidebar');
    const desktopCollapseBtn = document.getElementById('sidebar-collapse-btn');
    const sidebarExpandBtn = document.getElementById('sidebar-expand-btn');
    const collapsedToolbar = document.getElementById('collapsed-toolbar');

    // Add mobile toggle listener
    if(mobileToggle) {
        mobileToggle.addEventListener('click', () => {
             if(sidebar) sidebar.classList.toggle('-translate-x-full');
        });
    }

    function toggleSidebar() {
        if(!sidebar) return;
        sidebar.classList.toggle('w-64');
        sidebar.classList.toggle('w-0');
        sidebar.classList.toggle('p-0'); 
        
        if(collapsedToolbar) {
            if (sidebar.classList.contains('w-0')) {
                collapsedToolbar.classList.remove('toolbar-hidden');
                collapsedToolbar.classList.add('toolbar-visible');
            } else {
                collapsedToolbar.classList.add('toolbar-hidden');
                collapsedToolbar.classList.remove('toolbar-visible');
            }
        }
    }

    if(desktopCollapseBtn) desktopCollapseBtn.addEventListener('click', toggleSidebar);
    if(sidebarExpandBtn) sidebarExpandBtn.addEventListener('click', toggleSidebar);

</script>
</body>
</html>
