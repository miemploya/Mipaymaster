<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

require_once '../includes/functions.php';
require_login();

$company_id = $_SESSION['company_id'];
$success_msg = '';
$error_msg = '';
$current_page = 'users';

// --- HANDLE FORM SUBMISSIONS ---
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    // 1. SAVE USER
    if ($action === 'save_user') {
        $u_id = $_POST['user_id'] ?? null;
        $fname = clean_input($_POST['first_name']);
        $lname = clean_input($_POST['last_name']);
        $email = clean_input($_POST['email']);
        $role = clean_input($_POST['role']);
        $pwd = $_POST['password'];
        
        if (empty($fname) || empty($email) || empty($role)) {
            $error_msg = "All fields are required.";
        } else {
            try {
                if ($u_id) {
                    // Update
                    // SECURITY CHECK: Get current role before update
                    $stmt = $pdo->prepare("SELECT role FROM users WHERE id = ?");
                    $stmt->execute([$u_id]);
                    $current_user_data = $stmt->fetch();
                    
                    if ($current_user_data && $current_user_data['role'] === 'super_admin') {
                        throw new Exception("Super Admin accounts cannot be modified.");
                    }

                    $sql = "UPDATE users SET first_name=?, last_name=?, email=?, role=?"; 
                    $params = [$fname, $lname, $email, $role];
                    
                    if (!empty($pwd)) {
                        $sql .= ", password_hash=?";
                        $params[] = password_hash($pwd, PASSWORD_DEFAULT);
                    }
                    $sql .= " WHERE id=? AND company_id=?";
                    $params[] = $u_id;
                    $params[] = $company_id;
                    
                    $stmt = $pdo->prepare($sql);
                    $stmt->execute($params);
                    $success_msg = "User updated successfully.";
                    log_audit($company_id, $_SESSION['user_id'], 'UPDATE_USER', "Updated user: $email");
                } else {
                    // Create
                    if (empty($pwd)) throw new Exception("Password is required for new users.");
                    
                    // Check email uniqueness
                    $chk = $pdo->prepare("SELECT id FROM users WHERE email = ?");
                    $chk->execute([$email]);
                    if ($chk->rowCount() > 0) throw new Exception("Email already exists.");

                    $stmt = $pdo->prepare("INSERT INTO users (company_id, first_name, last_name, email, password_hash, role, status) VALUES (?, ?, ?, ?, ?, ?, 'active')");
                    $stmt->execute([$company_id, $fname, $lname, $email, password_hash($pwd, PASSWORD_DEFAULT), $role]);
                    $success_msg = "User created successfully.";
                    log_audit($company_id, $_SESSION['user_id'], 'CREATE_USER', "Created user: $email ($role)");
                }
            } catch (Exception $e) { $error_msg = "Error: " . $e->getMessage(); }
        }
    }
    // 2. TOGGLE STATUS
    elseif ($action === 'toggle_status') {
        $u_id = $_POST['user_id'];
        $new_status = $_POST['status']; // 'active' or 'suspended'
        try {
            // SECURITY CHECK
            $stmt = $pdo->prepare("SELECT role FROM users WHERE id = ?");
            $stmt->execute([$u_id]);
            $tgt = $stmt->fetch();
            if ($tgt && $tgt['role'] === 'super_admin') throw new Exception("Super Admin accounts cannot be suspended.");

            $stmt = $pdo->prepare("UPDATE users SET status=? WHERE id=? AND company_id=?");
            $stmt->execute([$new_status, $u_id, $company_id]);
            $success_msg = "User status updated to $new_status.";
             log_audit($company_id, $_SESSION['user_id'], 'UPDATE_USER_STATUS', "User ID $u_id status set to $new_status");
        } catch (Exception $e) { $error_msg = "Error: " . $e->getMessage(); }
    }
    // 3. DELETE USER
    elseif ($action === 'delete_user') {
        $u_id = $_POST['user_id'];
         try {
            // Prevent self-delete
            if ($u_id == $_SESSION['user_id']) throw new Exception("You cannot delete your own account.");
            
            // SECURITY CHECK
            $stmt = $pdo->prepare("SELECT role FROM users WHERE id = ?");
            $stmt->execute([$u_id]);
            $tgt = $stmt->fetch();
            if ($tgt && $tgt['role'] === 'super_admin') throw new Exception("Super Admin accounts cannot be deleted.");
            
            $stmt = $pdo->prepare("DELETE FROM users WHERE id=? AND company_id=?");
            $stmt->execute([$u_id, $company_id]);
            $success_msg = "User deleted.";
            log_audit($company_id, $_SESSION['user_id'], 'DELETE_USER', "Deleted user ID: $u_id");
        } catch (Exception $e) { $error_msg = "Error: " . $e->getMessage(); }
    }
}

// --- FETCH DATA ---
$company_users = [];
try {
    $stmt = $pdo->prepare("SELECT * FROM users WHERE company_id = ? ORDER BY created_at DESC");
    $stmt->execute([$company_id]);
    $company_users = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) { /* ignore */ }
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>User Management - Mipaymaster</title>
    <!-- Tailwind & Logic -->
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
    
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap');
        body { font-family: 'Inter', sans-serif; }
        
        ::-webkit-scrollbar { width: 6px; height: 6px; }
        ::-webkit-scrollbar-track { background: transparent; }
        ::-webkit-scrollbar-thumb { background: #cbd5e1; border-radius: 3px; }
        .dark ::-webkit-scrollbar-thumb { background: #334155; }
        
        [x-cloak] { display: none !important; }
    </style>
</head>
<body class="flex h-screen overflow-hidden bg-slate-50 dark:bg-slate-900 text-slate-900 dark:text-slate-100 transition-colors duration-300" x-data="{ sidebarOpen: false }">

    <!-- Sidebar -->
    <?php include '../includes/dashboard_sidebar.php'; ?>

    <!-- Main Content -->
    <div class="flex-1 flex flex-col h-full overflow-hidden w-full relative">
        <!-- Header -->
        <?php $page_title = 'User Management'; include '../includes/dashboard_header.php'; ?>
        <!-- Admin Sub-Header -->
        <?php include '../includes/admin_header.php'; ?>

        <main class="flex-1 overflow-y-auto p-6 lg:p-8 relative scroll-smooth bg-slate-50 dark:bg-slate-900">
            
            <?php if ($success_msg): ?>
                <div class="mb-6 p-4 rounded-lg bg-green-50 dark:bg-green-900/30 text-green-700 dark:text-green-300 border border-green-200 dark:border-green-800"><?php echo $success_msg; ?></div>
            <?php endif; ?>
            <?php if ($error_msg): ?>
                <div class="mb-6 p-4 rounded-lg bg-red-50 dark:bg-red-900/30 text-red-700 dark:text-red-300 border border-red-200 dark:border-red-800"><?php echo $error_msg; ?></div>
            <?php endif; ?>

            <div class="max-w-6xl space-y-6">
                <div class="flex justify-between items-center mb-4">
                    <div><h3 class="text-lg font-bold text-slate-900 dark:text-white">User Management</h3><p class="text-xs text-slate-500">Manage system access and permissions.</p></div>
                    <button @click="$dispatch('open-user-modal')" class="px-4 py-2 bg-purple-600 text-white text-sm font-bold rounded-lg hover:bg-purple-700 shadow-md flex items-center gap-2"><i data-lucide="plus" class="w-4 h-4"></i> Add User</button>
                </div>

                <div class="bg-white dark:bg-slate-950 rounded-xl border border-slate-200 dark:border-slate-800 overflow-hidden shadow-sm">
                    <table class="w-full text-left text-sm">
                        <thead class="bg-slate-50 dark:bg-slate-900 border-b border-slate-200 dark:border-slate-800 text-slate-500">
                            <tr>
                                <th class="px-6 py-4 font-bold">User</th>
                                <th class="px-6 py-4 font-bold">Role</th>
                                <th class="px-6 py-4 font-bold">Status</th>
                                <th class="px-6 py-4 font-bold text-right">Actions</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
                            <?php foreach ($company_users as $usr): ?>
                            <tr class="hover:bg-slate-50 dark:hover:bg-slate-900/50">
                                <td class="px-6 py-4">
                                    <div class="flex items-center gap-3">
                                        <div class="w-8 h-8 rounded-full bg-slate-200 dark:bg-slate-700 flex items-center justify-center font-bold text-slate-600 dark:text-slate-300 text-xs">
                                            <?php echo strtoupper(substr($usr['first_name'], 0, 1) . substr($usr['last_name'], 0, 1)); ?>
                                        </div>
                                        <div>
                                            <p class="font-bold text-slate-900 dark:text-white"><?php echo htmlspecialchars($usr['first_name'] . ' ' . $usr['last_name']); ?></p>
                                            <p class="text-xs text-slate-500"><?php echo htmlspecialchars($usr['email']); ?></p>
                                        </div>
                                    </div>
                                </td>
                                <td class="px-6 py-4"><span class="px-2 py-1 rounded text-xs font-bold bg-slate-100 dark:bg-slate-900 text-slate-600 dark:text-slate-400 capitalize"><?php echo htmlspecialchars($usr['role']); ?></span></td>
                                <td class="px-6 py-4">
                                    <?php if (($usr['status'] ?? 'active') === 'active'): ?>
                                        <span class="inline-flex items-center gap-1.5 px-2 py-1 rounded-full text-xs font-bold bg-green-50 text-green-700 border border-green-200"><span class="w-1.5 h-1.5 rounded-full bg-green-500"></span> Active</span>
                                    <?php else: ?>
                                        <span class="inline-flex items-center gap-1.5 px-2 py-1 rounded-full text-xs font-bold bg-red-50 text-red-700 border border-red-200"><span class="w-1.5 h-1.5 rounded-full bg-red-500"></span> Suspended</span>
                                    <?php endif; ?>
                                </td>
                                <td class="px-6 py-4 text-right">
                                    <div class="flex items-center justify-end gap-2" x-data="{ open: false }">
                                        <button @click="$dispatch('edit-user', <?php echo htmlspecialchars(json_encode($usr)); ?>)" class="p-2 text-slate-500 hover:text-brand-600 dark:hover:text-white transition-colors" title="Edit"><i data-lucide="edit-2" class="w-4 h-4"></i></button>
                                        
                                        <?php if ($usr['id'] != $_SESSION['user_id']): ?>
                                            <!-- Suspend/Activate -->
                                            <form method="POST" class="inline">
                                                <input type="hidden" name="action" value="toggle_status">
                                                <input type="hidden" name="user_id" value="<?php echo $usr['id']; ?>">
                                                <input type="hidden" name="status" value="<?php echo ($usr['status'] ?? 'active') === 'active' ? 'suspended' : 'active'; ?>">
                                                <button type="submit" class="p-2 text-slate-500 hover:text-amber-600 transition-colors" title="<?php echo ($usr['status'] ?? 'active') === 'active' ? 'Suspend' : 'Activate'; ?>">
                                                    <i data-lucide="<?php echo ($usr['status'] ?? 'active') === 'active' ? 'ban' : 'check-circle'; ?>" class="w-4 h-4"></i>
                                                </button>
                                            </form>
                                            
                                            <!-- Delete -->
                                            <form method="POST" class="inline" onsubmit="return confirm('Delete this user? This action cannot be undone.');">
                                                <input type="hidden" name="action" value="delete_user">
                                                <input type="hidden" name="user_id" value="<?php echo $usr['id']; ?>">
                                                <button type="submit" class="p-2 text-slate-500 hover:text-red-600 transition-colors" title="Delete"><i data-lucide="trash-2" class="w-4 h-4"></i></button>
                                            </form>
                                        <?php endif; ?>
                                    </div>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- User Modal (Alpine) -->
            <div x-data="{ open: false, isEdit: false, user: {} }" 
                 @open-user-modal.window="open = true; isEdit = false; user = {}"
                 @edit-user.window="open = true; isEdit = true; user = $event.detail"
                 x-show="open" style="display: none;" 
                 class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/50 backdrop-blur-sm">
                
                <div @click.outside="open = false" class="bg-white dark:bg-slate-900 rounded-xl shadow-2xl w-full max-w-md overflow-hidden">
                    <div class="px-6 py-4 border-b border-slate-100 dark:border-slate-800 flex justify-between items-center">
                        <h3 class="text-lg font-bold text-slate-900 dark:text-white" x-text="isEdit ? 'Edit User' : 'Add New User'"></h3>
                        <button @click="open = false" class="text-slate-500 hover:text-slate-700"><i data-lucide="x" class="w-5 h-5"></i></button>
                    </div>
                    <form method="POST" class="p-6 space-y-4">
                        <input type="hidden" name="action" value="save_user">
                        <input type="hidden" name="user_id" :value="user.id">
                        
                        <div class="grid grid-cols-2 gap-4">
                            <div>
                                <label class="block text-xs font-bold text-slate-500 mb-1">First Name</label>
                                <input type="text" name="first_name" :value="user.first_name" required class="w-full rounded-lg border-slate-300 dark:border-slate-700 bg-slate-50 dark:bg-slate-800 p-2 text-sm">
                            </div>
                            <div>
                                <label class="block text-xs font-bold text-slate-500 mb-1">Last Name</label>
                                <input type="text" name="last_name" :value="user.last_name" required class="w-full rounded-lg border-slate-300 dark:border-slate-700 bg-slate-50 dark:bg-slate-800 p-2 text-sm">
                            </div>
                        </div>
                        <div>
                            <label class="block text-xs font-bold text-slate-500 mb-1">Email (Login)</label>
                            <input type="email" name="email" :value="user.email" required class="w-full rounded-lg border-slate-300 dark:border-slate-700 bg-slate-50 dark:bg-slate-800 p-2 text-sm">
                        </div>
                        <div>
                            <label class="block text-xs font-bold text-slate-500 mb-1">Role</label>
                            <select name="role" required class="w-full rounded-lg border-slate-300 dark:border-slate-700 bg-slate-50 dark:bg-slate-800 p-2 text-sm">
                                <option value="company_admin" :selected="user.role === 'company_admin'">Admin</option>
                                <option value="hr_manager" :selected="user.role === 'hr_manager'">HR Manager</option>
                                <option value="employee" :selected="user.role === 'employee'">Employee</option>
                                <!-- Super Admin is hidden from selection but handled if existing -->
                                <template x-if="user.role === 'super_admin'">
                                    <option value="super_admin" selected>Super Admin</option>
                                </template>
                            </select>
                        </div>
                        <div>
                            <label class="block text-xs font-bold text-slate-500 mb-1">Password <span x-show="isEdit" class="text-slate-400 font-normal">(Leave blank to keep current)</span></label>
                            <input type="password" name="password" :required="!isEdit" class="w-full rounded-lg border-slate-300 dark:border-slate-700 bg-slate-50 dark:bg-slate-800 p-2 text-sm" placeholder="••••••••">
                        </div>
                        
                        <div class="pt-4 flex justify-end gap-2">
                            <button type="button" @click="open = false" class="px-4 py-2 text-slate-600 hover:bg-slate-100 rounded-lg text-sm font-medium">Cancel</button>
                            <button type="submit" class="px-6 py-2 bg-purple-600 text-white rounded-lg text-sm font-bold hover:bg-purple-700 shadow-md">Save User</button>
                        </div>
                    </form>
                </div>
            </div>
        </main>
    </div>
    <?php include '../includes/dashboard_scripts.php'; ?>
</body>
</html>
