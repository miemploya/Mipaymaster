<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

require_once '../includes/functions.php';
require_login();

$company_id = $_SESSION['company_id'] ?? 0;
$success_msg = '';
$error_msg = '';
$current_page = 'settings'; // Matches key in dashboard_sidebar.php

// --- DB MIGRATION CHECK (AUTO) ---
try {
    $cols = $pdo->query("SHOW COLUMNS FROM users LIKE 'photo_url'")->fetchAll();
    if (count($cols) == 0) $pdo->exec("ALTER TABLE users ADD COLUMN photo_url VARCHAR(255) DEFAULT NULL");
} catch (Exception $e) { /* silent fail */ }

// --- HANDLE FORM SUBMISSIONS ---
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $tab = $_POST['tab'] ?? '';

    // 0. UPDATE PROFILE (NEW)
    if ($tab === 'profile') {
        try {
            // Handle Photo Upload
            if (isset($_FILES['avatar']) && $_FILES['avatar']['error'] === UPLOAD_ERR_OK) {
                $allowTypes = ['jpg', 'png', 'jpeg', 'gif'];
                $fileName = basename($_FILES['avatar']['name']);
                $targetDir = "../uploads/avatars/";
                if (!is_dir($targetDir)) mkdir($targetDir, 0777, true);
                
                $fileType = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));
                if (in_array($fileType, $allowTypes)) {
                    $newFileName = 'user_' . $_SESSION['user_id'] . '_' . time() . '.' . $fileType;
                    $targetFilePath = $targetDir . $newFileName;
                    
                    if (move_uploaded_file($_FILES['avatar']['tmp_name'], $targetFilePath)) {
                        // Update DB
                        $stmt = $pdo->prepare("UPDATE users SET photo_url = ? WHERE id = ?");
                        $stmt->execute([$newFileName, $_SESSION['user_id']]);
                        
                        // Update Session
                        $_SESSION['user_photo'] = $newFileName;
                        $success_msg = "Profile photo updated.";
                    } else {
                        $error_msg = "Failed to upload file.";
                    }
                } else {
                    $error_msg = "Invalid file type. Only JPG, PNG, GIF allowed.";
                }
            }
            
            // Handle Profile Details (Name/Password) if fields exist
            if (isset($_POST['full_name'])) {
                $name = clean_input($_POST['full_name']);
                $stmt = $pdo->prepare("UPDATE users SET name = ? WHERE id = ?");
                $stmt->execute([$name, $_SESSION['user_id']]);
                $_SESSION['user_name'] = $name;
                $success_msg = "Profile updated.";
            }

        } catch (PDOException $e) { $error_msg = "Error updating profile: " . $e->getMessage(); }
    }

    // 1. UPDATE STATUTORY
    if ($tab === 'statutory') {
        $enable_paye = isset($_POST['enable_paye']) ? 1 : 0;
        $enable_pension = isset($_POST['enable_pension']) ? 1 : 0;
        $enable_nhis = isset($_POST['enable_nhis']) ? 1 : 0;
        $enable_nhf = isset($_POST['enable_nhf']) ? 1 : 0;
        $pension_employer = floatval($_POST['pension_employer_perc']);
        $pension_employee = floatval($_POST['pension_employee_perc']);

        try {
            // Check if settings exist first
            $check = $pdo->prepare("SELECT company_id FROM statutory_settings WHERE company_id = ?");
            $check->execute([$company_id]);
            if ($check->rowCount() == 0) {
                 $pdo->prepare("INSERT INTO statutory_settings (company_id) VALUES (?)")->execute([$company_id]);
            }
            
            $stmt = $pdo->prepare("UPDATE statutory_settings SET enable_paye=?, enable_pension=?, enable_nhis=?, enable_nhf=?, pension_employer_perc=?, pension_employee_perc=? WHERE company_id=?");
            $stmt->execute([$enable_paye, $enable_pension, $enable_nhis, $enable_nhf, $pension_employer, $pension_employee, $company_id]);
            $success_msg = "Statutory settings updated.";
        } catch (PDOException $e) { $error_msg = "Error updating settings: " . $e->getMessage(); }
    }

    // 2. UPDATE BEHAVIOUR
    elseif ($tab === 'behaviour') {
        $prorate = isset($_POST['prorate_new_hires']) ? 1 : 0;
        $email_payslips = isset($_POST['email_payslips']) ? 1 : 0;
        $password_protect = isset($_POST['password_protect_payslips']) ? 1 : 0;
        $overtime = isset($_POST['enable_overtime']) ? 1 : 0;

        try {
            $check = $pdo->prepare("SELECT company_id FROM payroll_behaviours WHERE company_id = ?");
            $check->execute([$company_id]);
            if ($check->rowCount() == 0) {
                 $pdo->prepare("INSERT INTO payroll_behaviours (company_id) VALUES (?)")->execute([$company_id]);
            }
            $stmt = $pdo->prepare("UPDATE payroll_behaviours SET prorate_new_hires=?, email_payslips=?, password_protect_payslips=?, enable_overtime=? WHERE company_id=?");
            $stmt->execute([$prorate, $email_payslips, $password_protect, $overtime, $company_id]);
            $success_msg = "Behaviour settings updated.";
        } catch (PDOException $e) { $error_msg = "Error updating behaviours: " . $e->getMessage(); }
    }
    // 3. USER MANAGEMENT (NEW)
    // 3. (REMOVED) User Management moved to users.php
}
// --- FETCH DATA ---

// 1. Statutory
$statutory = ['enable_paye'=>1, 'enable_pension'=>1, 'pension_employer_perc'=>10, 'pension_employee_perc'=>8, 'enable_nhis'=>0, 'enable_nhf'=>0];
try {
    $stmt = $pdo->prepare("SELECT * FROM statutory_settings WHERE company_id = ?");
    $stmt->execute([$company_id]);
    $fetched = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($fetched) {
        $statutory = $fetched;
    } else {
        $pdo->prepare("INSERT INTO statutory_settings (company_id) VALUES (?)")->execute([$company_id]);
    }
} catch (Exception $e) { /* ignore */ }

// 2. Behaviours
$behaviour = ['prorate_new_hires'=>1, 'email_payslips'=>0, 'password_protect_payslips'=>0, 'enable_overtime'=>0];
try {
    $stmt = $pdo->prepare("SELECT * FROM payroll_behaviours WHERE company_id = ?");
    $stmt->execute([$company_id]);
    $fetched = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($fetched) {
        $behaviour = $fetched;
    } else {
        $pdo->prepare("INSERT INTO payroll_behaviours (company_id) VALUES (?)")->execute([$company_id]);
    }
} catch (Exception $e) { /* ignore */ }

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Settings - Mipaymaster</title>
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
        
        .sidebar-transition { transition: margin-left 0.3s ease-in-out, width 0.3s ease-in-out, transform 0.3s ease-in-out; }
        .toolbar-hidden { transform: translateY(-100%); opacity: 0; pointer-events: none; height: 0; padding: 0; border: none; }
        .toolbar-visible { transform: translateY(0); opacity: 1; pointer-events: auto; height: auto; }
        
        /* Toggle Switch */
        .toggle-checkbox:checked { right: 0; border-color: #68D391; }
        .toggle-checkbox:checked + .toggle-label { background-color: #68D391; }
    </style>
    
    <script>
        function settingsApp() {
            return {
                currentTab: '<?php echo isset($_POST['tab']) ? $_POST['tab'] : 'general'; ?>', // default to general
                sidebarOpen: false,

                // Mock Audit Logs
                auditLogs: [
                    { date: 'Jan 15, 10:30 AM', user: 'Admin', action: 'Modified Tax Settings', module: 'Statutory', details: 'Enabled NHF' },
                    { date: 'Jan 14, 02:15 PM', user: 'HR', action: 'Updated Salary Component', module: 'Components', details: 'Changed Housing % to 30' },
                ],

                saveSettings() {
                    // This is just a UI feedback for tabs without backend logic yet
                     // For tabs with backend logic (Statutory, Behaviour), the form submission handles it.
                },

                init() {
                    this.$watch('currentTab', () => setTimeout(() => lucide.createIcons(), 50));
                }
            }
        }
    </script>
</head>
<body class="flex h-screen overflow-hidden bg-slate-50 dark:bg-slate-900 text-slate-900 dark:text-slate-100 transition-colors duration-300" x-data="settingsApp()">

    <!-- Sidebar Include -->
    <?php include '../includes/dashboard_sidebar.php'; ?>

        <!-- NOTIFICATIONS PANEL (Restored System Standard) -->
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
            <!-- Header -->
            <?php $page_title = 'Settings'; include '../includes/dashboard_header.php'; ?>
            <!-- Admin Sub-Header -->
            <?php include '../includes/admin_header.php'; ?>

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

            <!-- Main Layout: Content Area Only -->
            <main class="flex-1 overflow-y-auto p-6 lg:p-8 relative scroll-smooth bg-slate-50 dark:bg-slate-900">
                
                 <?php if ($success_msg): ?>
                    <div class="mb-6 p-4 rounded-lg bg-green-50 dark:bg-green-900/30 text-green-700 dark:text-green-300 border border-green-200 dark:border-green-800"><?php echo $success_msg; ?></div>
                <?php endif; ?>
                <?php if ($error_msg): ?>
                    <div class="mb-6 p-4 rounded-lg bg-red-50 dark:bg-red-900/30 text-red-700 dark:text-red-300 border border-red-200 dark:border-red-800"><?php echo $error_msg; ?></div>
                <?php endif; ?>

                <!-- Horizontal Tabs (Color Coded & Hover Effects) -->
                <div class="mb-8 overflow-x-auto pb-2">
                    <div class="flex gap-3 min-w-max">
                        <button @click="currentTab = 'profile'" :class="currentTab === 'profile' ? 'bg-indigo-600 text-white shadow-indigo-500/30 hover:bg-indigo-700' : 'bg-white dark:bg-slate-800 text-slate-600 dark:text-slate-400 hover:text-indigo-600 dark:hover:text-indigo-400 border border-slate-200 dark:border-slate-700 hover:border-indigo-300'" class="px-5 py-2.5 text-sm font-bold rounded-xl transition-all shadow-sm flex items-center justify-center gap-2 whitespace-nowrap"><i data-lucide="user" class="w-4 h-4"></i> Profile</button>

                        <button @click="currentTab = 'general'" :class="currentTab === 'general' ? 'bg-blue-600 text-white shadow-blue-500/30 hover:bg-blue-700' : 'bg-white dark:bg-slate-800 text-slate-600 dark:text-slate-400 hover:text-blue-600 dark:hover:text-blue-400 border border-slate-200 dark:border-slate-700 hover:border-blue-300'" class="px-5 py-2.5 text-sm font-bold rounded-xl transition-all shadow-sm flex items-center justify-center gap-2 whitespace-nowrap"><i data-lucide="globe" class="w-4 h-4"></i> General</button>
                        <button @click="currentTab = 'statutory'" :class="currentTab === 'statutory' ? 'bg-emerald-600 text-white shadow-emerald-500/30 hover:bg-emerald-700' : 'bg-white dark:bg-slate-800 text-slate-600 dark:text-slate-400 hover:text-emerald-600 dark:hover:text-emerald-400 border border-slate-200 dark:border-slate-700 hover:border-emerald-300'" class="px-5 py-2.5 text-sm font-bold rounded-xl transition-all shadow-sm flex items-center justify-center gap-2 whitespace-nowrap"><i data-lucide="scale" class="w-4 h-4"></i> Statutory</button>
                        <button @click="currentTab = 'behaviour'" :class="currentTab === 'behaviour' ? 'bg-amber-600 text-white shadow-amber-500/30 hover:bg-amber-700' : 'bg-white dark:bg-slate-800 text-slate-600 dark:text-slate-400 hover:text-amber-600 dark:hover:text-amber-400 border border-slate-200 dark:border-slate-700 hover:border-amber-300'" class="px-5 py-2.5 text-sm font-bold rounded-xl transition-all shadow-sm flex items-center justify-center gap-2 whitespace-nowrap"><i data-lucide="sliders" class="w-4 h-4"></i> Behaviour</button>
                        <button @click="currentTab = 'security'" :class="currentTab === 'security' ? 'bg-red-600 text-white shadow-red-500/30 hover:bg-red-700' : 'bg-white dark:bg-slate-800 text-slate-600 dark:text-slate-400 hover:text-red-600 dark:hover:text-red-400 border border-slate-200 dark:border-slate-700 hover:border-red-300'" class="px-5 py-2.5 text-sm font-bold rounded-xl transition-all shadow-sm flex items-center justify-center gap-2 whitespace-nowrap"><i data-lucide="shield" class="w-4 h-4"></i> Security</button>
                        <button @click="currentTab = 'notifications'" :class="currentTab === 'notifications' ? 'bg-orange-500 text-white shadow-orange-500/30 hover:bg-orange-600' : 'bg-white dark:bg-slate-800 text-slate-600 dark:text-slate-400 hover:text-orange-500 dark:hover:text-orange-400 border border-slate-200 dark:border-slate-700 hover:border-orange-300'" class="px-5 py-2.5 text-sm font-bold rounded-xl transition-all shadow-sm flex items-center justify-center gap-2 whitespace-nowrap"><i data-lucide="bell" class="w-4 h-4"></i> Notifications</button>
                        <button @click="currentTab = 'integrations'" :class="currentTab === 'integrations' ? 'bg-cyan-600 text-white shadow-cyan-500/30 hover:bg-cyan-700' : 'bg-white dark:bg-slate-800 text-slate-600 dark:text-slate-400 hover:text-cyan-600 dark:hover:text-cyan-400 border border-slate-200 dark:border-slate-700 hover:border-cyan-300'" class="px-5 py-2.5 text-sm font-bold rounded-xl transition-all shadow-sm flex items-center justify-center gap-2 whitespace-nowrap"><i data-lucide="link" class="w-4 h-4"></i> Integrations</button>
                        <button @click="currentTab = 'system'" :class="currentTab === 'system' ? 'bg-slate-700 text-white shadow-slate-500/30 hover:bg-slate-800' : 'bg-white dark:bg-slate-800 text-slate-600 dark:text-slate-400 hover:text-slate-800 dark:hover:text-slate-300 border border-slate-200 dark:border-slate-700 hover:border-slate-400'" class="px-5 py-2.5 text-sm font-bold rounded-xl transition-all shadow-sm flex items-center justify-center gap-2 whitespace-nowrap"><i data-lucide="cpu" class="w-4 h-4"></i> System</button>
                        <button @click="currentTab = 'audit'" :class="currentTab === 'audit' ? 'bg-gray-800 text-white shadow-gray-500/30 hover:bg-black' : 'bg-white dark:bg-slate-800 text-slate-600 dark:text-slate-400 hover:text-gray-800 dark:hover:text-gray-300 border border-slate-200 dark:border-slate-700 hover:border-gray-400'" class="px-5 py-2.5 text-sm font-bold rounded-xl transition-all shadow-sm flex items-center justify-center gap-2 whitespace-nowrap"><i data-lucide="file-clock" class="w-4 h-4"></i> Logs</button>
                    </div>
                </div>

                <!-- TAB 0: USER PROFILE -->
                <div x-show="currentTab === 'profile'" x-cloak x-transition.opacity>
                    <div class="max-w-4xl bg-white dark:bg-slate-950 rounded-xl border border-slate-200 dark:border-slate-800 p-6 shadow-sm mb-6">
                        <form method="POST" enctype="multipart/form-data">
                            <input type="hidden" name="tab" value="profile">
                            <div class="flex items-start gap-8">
                                <div class="shrink-0 group relative">
                                    <div class="w-32 h-32 rounded-full bg-slate-100 dark:bg-slate-900 border-4 border-white dark:border-slate-800 shadow-lg overflow-hidden flex items-center justify-center">
                                        <?php if (!empty($_SESSION['user_photo'])): ?>
                                            <img src="../uploads/avatars/<?php echo htmlspecialchars($_SESSION['user_photo']); ?>" class="w-full h-full object-cover">
                                        <?php else: ?>
                                            <i data-lucide="user" class="w-16 h-16 text-slate-300 dark:text-slate-700"></i>
                                        <?php endif; ?>
                                    </div>
                                    <label class="absolute bottom-0 right-0 p-2 bg-indigo-600 text-white rounded-full shadow-lg hover:bg-indigo-700 cursor-pointer transition-transform hover:scale-105 border-2 border-white dark:border-slate-800">
                                        <i data-lucide="camera" class="w-4 h-4"></i>
                                        <input type="file" name="avatar" class="hidden" accept="image/*" onchange="this.form.submit()">
                                    </label>
                                </div>
                                <div class="flex-1 space-y-4">
                                    <div>
                                        <label class="block text-sm font-bold text-slate-700 dark:text-slate-300 mb-1">Full Name</label>
                                        <input type="text" name="full_name" value="<?php echo htmlspecialchars($_SESSION['user_name']); ?>" class="w-full rounded-lg border-slate-300 dark:border-slate-700 bg-slate-50 dark:bg-slate-900 px-4 py-2.5 font-bold text-slate-900 dark:text-white focus:ring-indigo-500 focus:border-indigo-500">
                                    </div>
                                    <div>
                                        <label class="block text-sm font-bold text-slate-700 dark:text-slate-300 mb-1">Email Address</label>
                                        <input type="email" value="<?php echo htmlspecialchars($_SESSION['email'] ?? 'user@example.com'); ?>" disabled class="w-full rounded-lg border-slate-300 dark:border-slate-700 bg-slate-100 dark:bg-slate-800 px-4 py-2.5 text-slate-500 cursor-not-allowed">
                                    </div>
                                    <div class="pt-2">
                                        <button type="submit" class="px-6 py-2.5 bg-indigo-600 text-white font-bold rounded-lg hover:bg-indigo-700 shadow-md transition-colors">Update Profile</button>
                                    </div>
                                </div>
                            </div>
                        </form>
                    </div>
                </div>



                <!-- TAB 1: GENERAL SETTINGS -->
                <div x-show="currentTab === 'general'" x-cloak x-transition.opacity>
                    <div class="max-w-4xl">
                        <div class="bg-white dark:bg-slate-950 rounded-xl border border-slate-200 dark:border-slate-800 p-6 space-y-8 shadow-sm">
                            <div>
                                <h3 class="text-sm font-bold text-slate-900 dark:text-white mb-4 border-b border-slate-100 dark:border-slate-800 pb-2 uppercase tracking-wide">Company Defaults</h3>
                                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                                    <div><label class="block text-xs font-bold text-slate-500 dark:text-slate-400 uppercase tracking-wide mb-1">Default Currency</label><select class="w-full rounded-lg border-slate-300 dark:border-slate-700 bg-white dark:bg-slate-800 text-slate-900 dark:text-white focus:ring-brand-500 focus:border-brand-500 transition-colors shadow-sm text-sm"><option>NGN (₦)</option><option>USD ($)</option></select></div>
                                    <div><label class="block text-xs font-bold text-slate-500 dark:text-slate-400 uppercase tracking-wide mb-1">Payroll Cycle</label><select class="w-full rounded-lg border-slate-300 dark:border-slate-700 bg-white dark:bg-slate-800 text-slate-900 dark:text-white focus:ring-brand-500 focus:border-brand-500 transition-colors shadow-sm text-sm"><option>Monthly</option><option>Weekly</option></select></div>
                                    <div><label class="block text-xs font-bold text-slate-500 dark:text-slate-400 uppercase tracking-wide mb-1">Working Days</label><input type="number" value="22" class="w-full rounded-lg border-slate-300 dark:border-slate-700 bg-white dark:bg-slate-800 text-slate-900 dark:text-white focus:ring-brand-500 focus:border-brand-500 transition-colors shadow-sm text-sm"></div>
                                    <div><label class="block text-xs font-bold text-slate-500 dark:text-slate-400 uppercase tracking-wide mb-1">Daily Hours</label><input type="number" value="8" class="w-full rounded-lg border-slate-300 dark:border-slate-700 bg-white dark:bg-slate-800 text-slate-900 dark:text-white focus:ring-brand-500 focus:border-brand-500 transition-colors shadow-sm text-sm"></div>
                                </div>
                            </div>
                            <div>
                                <h3 class="text-sm font-bold text-slate-900 dark:text-white mb-4 border-b border-slate-100 dark:border-slate-800 pb-2 uppercase tracking-wide">Localization</h3>
                                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                                    <div><label class="block text-xs font-bold text-slate-500 dark:text-slate-400 uppercase tracking-wide mb-1">Country</label><input type="text" value="Nigeria" disabled class="w-full rounded-lg border-slate-300 dark:border-slate-700 bg-slate-50 dark:bg-slate-900 text-slate-900 dark:text-white focus:ring-brand-500 focus:border-brand-500 transition-colors shadow-sm text-sm opacity-70"></div>
                                    <div><label class="block text-xs font-bold text-slate-500 dark:text-slate-400 uppercase tracking-wide mb-1">Tax Jurisdiction</label><input type="text" value="Nigeria Tax Act 2025" disabled class="w-full rounded-lg border-slate-300 dark:border-slate-700 bg-slate-50 dark:bg-slate-900 text-slate-900 dark:text-white focus:ring-brand-500 focus:border-brand-500 transition-colors shadow-sm text-sm opacity-70"></div>
                                </div>
                            </div>
                            <div class="flex justify-end"><button class="px-6 py-2.5 bg-brand-600 text-white font-bold rounded-lg hover:bg-brand-700 shadow-md">Save Changes</button></div>
                        </div>
                    </div>
                </div>

                <!-- TAB 3: STATUTORY & COMPLIANCE -->
                <div x-show="currentTab === 'statutory'" x-cloak x-transition.opacity>
                    <div class="max-w-4xl space-y-6">
                        <form method="POST">
                        <input type="hidden" name="tab" value="statutory">
                        <div class="bg-white dark:bg-slate-950 rounded-xl border border-slate-200 dark:border-slate-800 p-6 shadow-sm mb-6">
                            <div class="flex justify-between items-start mb-4">
                                <div><h3 class="text-lg font-bold text-slate-900 dark:text-white">P.A.Y.E (Nigeria Tax Act 2025)</h3><p class="text-xs text-slate-500 mt-1">Automatic tax calculation based on graduated tax bands.</p></div>
                                <div class="relative inline-block w-12 align-middle select-none"><input type="checkbox" name="enable_paye" <?php echo ($statutory['enable_paye']) ? 'checked' : ''; ?> class="toggle-checkbox absolute block w-6 h-6 rounded-full bg-white border-4 appearance-none cursor-pointer"/><label class="toggle-label block overflow-hidden h-6 rounded-full bg-slate-300 cursor-pointer"></label></div>
                            </div>
                        </div>
                        <div class="bg-white dark:bg-slate-950 rounded-xl border border-slate-200 dark:border-slate-800 p-6 shadow-sm">
                            <h3 class="text-sm font-bold text-slate-900 dark:text-white mb-4 border-b border-slate-100 dark:border-slate-800 pb-2 uppercase tracking-wide">Pension Scheme</h3>
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                                <div><label class="block text-xs font-bold text-slate-500 dark:text-slate-400 uppercase tracking-wide mb-1">Employee Contribution %</label><input type="number" name="pension_employee_perc" value="<?php echo $statutory['pension_employee_perc']; ?>" class="w-full rounded-lg border-slate-300 dark:border-slate-700 bg-white dark:bg-slate-800 text-slate-900 dark:text-white focus:ring-brand-500 focus:border-brand-500 transition-colors shadow-sm text-sm"></div>
                                <div><label class="block text-xs font-bold text-slate-500 dark:text-slate-400 uppercase tracking-wide mb-1">Employer Contribution %</label><input type="number" name="pension_employer_perc" value="<?php echo $statutory['pension_employer_perc']; ?>" class="w-full rounded-lg border-slate-300 dark:border-slate-700 bg-white dark:bg-slate-800 text-slate-900 dark:text-white focus:ring-brand-500 focus:border-brand-500 transition-colors shadow-sm text-sm"></div>
                            </div>
                        </div>
                        <div class="flex justify-end mt-6"><button type="submit" class="px-6 py-2.5 bg-brand-600 text-white font-bold rounded-lg hover:bg-brand-700 shadow-md">Save Changes</button></div>
                        </form>
                    </div>
                </div>
                
                <!-- TAB 4: PAYROLL BEHAVIOUR -->
                <div x-show="currentTab === 'behaviour'" x-cloak x-transition.opacity>
                     <div class="max-w-4xl bg-white dark:bg-slate-950 rounded-xl border border-slate-200 dark:border-slate-800 p-6 shadow-sm">
                         <form method="POST">
                         <input type="hidden" name="tab" value="behaviour">
                         <div class="space-y-6">
                             <div>
                                 <h3 class="text-sm font-bold text-slate-900 dark:text-white mb-4 border-b border-slate-100 dark:border-slate-800 pb-2 uppercase tracking-wide">Processing</h3>
                                 <div class="space-y-3">
                                     <label class="flex items-center gap-3 p-3 border rounded-lg border-slate-200 dark:border-slate-700"><input type="checkbox" name="prorate_new_hires" <?php echo ($behaviour['prorate_new_hires']) ? 'checked' : ''; ?> class="w-5 h-5 text-brand-600 rounded"> <span class="text-sm text-slate-700 dark:text-slate-300">Prorate salary for new hires</span></label>
                                     <label class="flex items-center gap-3 p-3 border rounded-lg border-slate-200 dark:border-slate-700"><input type="checkbox" name="email_payslips" <?php echo ($behaviour['email_payslips']) ? 'checked' : ''; ?> class="w-5 h-5 text-brand-600 rounded"> <span class="text-sm text-slate-700 dark:text-slate-300">Email payslips automatically</span></label>
                                     <label class="flex items-center gap-3 p-3 border rounded-lg border-slate-200 dark:border-slate-700"><input type="checkbox" name="password_protect_payslips" <?php echo ($behaviour['password_protect_payslips']) ? 'checked' : ''; ?> class="w-5 h-5 text-brand-600 rounded"> <span class="text-sm text-slate-700 dark:text-slate-300">Password protect payslips (Use Employee ID)</span></label>
                                 </div>
                             </div>
                             <div class="flex justify-end"><button type="submit" class="px-6 py-2.5 bg-brand-600 text-white font-bold rounded-lg hover:bg-brand-700 shadow-md">Save Behaviour</button></div>
                         </div>
                         </form>
                     </div>
                </div>

                <!-- TAB 5: SECURITY -->
                <div x-show="currentTab === 'security'" x-cloak x-transition.opacity>
                    <div class="max-w-4xl bg-white dark:bg-slate-950 rounded-xl border border-slate-200 dark:border-slate-800 p-6 shadow-sm">
                        <div class="space-y-8">
                             <div>
                                <h3 class="text-sm font-bold text-slate-900 dark:text-white mb-4 border-b border-slate-100 dark:border-slate-800 pb-2 uppercase tracking-wide">Session Security</h3>
                                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                                    <div>
                                        <label class="block text-xs font-bold text-slate-500 dark:text-slate-400 uppercase tracking-wide mb-1">Auto-Logout Timer</label>
                                        <select class="w-full rounded-lg border-slate-300 dark:border-slate-700 bg-white dark:bg-slate-800 text-slate-900 dark:text-white focus:ring-brand-500 focus:border-brand-500 transition-colors shadow-sm text-sm">
                                            <option>15 Minutes</option>
                                            <option>30 Minutes</option>
                                            <option>1 Hour</option>
                                        </select>
                                    </div>
                                    <div class="flex items-center justify-between p-3 border rounded-lg border-slate-200 dark:border-slate-700 h-[42px] mt-auto">
                                        <span class="text-sm text-slate-700 dark:text-slate-300">Require 2FA for Admin Actions</span>
                                        <div class="relative inline-block w-10 align-middle select-none"><input type="checkbox" checked class="toggle-checkbox absolute block w-5 h-5 rounded-full bg-white border-4 appearance-none cursor-pointer"/><label class="toggle-label block overflow-hidden h-5 rounded-full bg-gray-300 cursor-pointer"></label></div>
                                    </div>
                                </div>
                             </div>
                             <div>
                                <h3 class="text-sm font-bold text-slate-900 dark:text-white mb-4 border-b border-slate-100 dark:border-slate-800 pb-2 uppercase tracking-wide">Access Control</h3>
                                <div>
                                    <label class="block text-xs font-bold text-slate-500 dark:text-slate-400 uppercase tracking-wide mb-1">IP Whitelist (Optional)</label>
                                    <textarea rows="3" class="w-full rounded-lg border-slate-300 dark:border-slate-700 bg-white dark:bg-slate-800 text-slate-900 dark:text-white focus:ring-brand-500 focus:border-brand-500 transition-colors shadow-sm text-sm" placeholder="Enter allowed IP addresses separated by commas..."></textarea>
                                </div>
                             </div>
                             <div class="flex justify-end"><button class="px-6 py-2.5 bg-brand-600 text-white font-bold rounded-lg hover:bg-brand-700 shadow-md">Save Security</button></div>
                        </div>
                    </div>
                </div>

                <!-- TAB 6: NOTIFICATIONS -->
                <div x-show="currentTab === 'notifications'" x-cloak x-transition.opacity>
                    <div class="max-w-4xl bg-white dark:bg-slate-950 rounded-xl border border-slate-200 dark:border-slate-800 p-6 shadow-sm">
                        <h2 class="text-xl font-bold text-slate-900 dark:text-white mb-6">Notification Preferences</h2>
                        <div class="space-y-4">
                            <!-- Header Row -->
                            <div class="grid grid-cols-3 gap-4 border-b border-slate-100 dark:border-slate-800 pb-2 mb-2 text-xs font-bold text-slate-500 uppercase">
                                <div class="col-span-1">Event Type</div>
                                <div class="text-center">Email Alert</div>
                                <div class="text-center">In-App Alert</div>
                            </div>
                            
                            <!-- Rows -->
                            <div class="grid grid-cols-3 gap-4 items-center py-2">
                                <span class="text-sm font-medium text-slate-700 dark:text-slate-300">Payroll Completed</span>
                                <div class="text-center"><input type="checkbox" checked class="w-5 h-5 text-orange-500 rounded"></div>
                                <div class="text-center"><input type="checkbox" checked class="w-5 h-5 text-orange-500 rounded"></div>
                            </div>
                            <div class="grid grid-cols-3 gap-4 items-center py-2">
                                <span class="text-sm font-medium text-slate-700 dark:text-slate-300">Statutory Remittance Due</span>
                                <div class="text-center"><input type="checkbox" checked class="w-5 h-5 text-orange-500 rounded"></div>
                                <div class="text-center"><input type="checkbox" checked class="w-5 h-5 text-orange-500 rounded"></div>
                            </div>
                            <div class="grid grid-cols-3 gap-4 items-center py-2">
                                <span class="text-sm font-medium text-slate-700 dark:text-slate-300">Attendance Anomalies</span>
                                <div class="text-center"><input type="checkbox" class="w-5 h-5 text-orange-500 rounded"></div>
                                <div class="text-center"><input type="checkbox" checked class="w-5 h-5 text-orange-500 rounded"></div>
                            </div>
                            
                            <div class="flex justify-end mt-6 pt-4 border-t border-slate-100 dark:border-slate-800">
                                <button class="px-6 py-2.5 bg-orange-500 text-white font-bold rounded-lg hover:bg-orange-600 shadow-md">Save Preferences</button>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- TAB 7: INTEGRATIONS -->
                <div x-show="currentTab === 'integrations'" x-cloak x-transition.opacity>
                    <div class="max-w-5xl">
                         <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                             <!-- Paystack -->
                             <div class="bg-white dark:bg-slate-950 p-6 rounded-xl border border-slate-200 dark:border-slate-800 shadow-sm flex flex-col justify-between">
                                 <div class="flex justify-between items-start mb-4">
                                     <div class="w-12 h-12 bg-blue-50 dark:bg-blue-900/20 rounded-lg flex items-center justify-center text-blue-600"><i data-lucide="credit-card" class="w-6 h-6"></i></div>
                                     <span class="px-2 py-1 bg-green-100 text-green-700 text-xs font-bold rounded">Connected</span>
                                 </div>
                                 <div>
                                     <h3 class="font-bold text-slate-900 dark:text-white">Paystack</h3>
                                     <p class="text-xs text-slate-500 mt-1">Direct salary disbursement.</p>
                                 </div>
                                 <button class="mt-4 w-full py-2 border border-slate-200 dark:border-slate-700 rounded-lg text-sm font-bold text-slate-600 dark:text-slate-300 hover:bg-slate-50 dark:hover:bg-slate-800">Configure</button>
                             </div>
                             
                             <!-- Biometrics -->
                             <div class="bg-white dark:bg-slate-950 p-6 rounded-xl border border-slate-200 dark:border-slate-800 shadow-sm flex flex-col justify-between">
                                 <div class="flex justify-between items-start mb-4">
                                     <div class="w-12 h-12 bg-cyan-50 dark:bg-cyan-900/20 rounded-lg flex items-center justify-center text-cyan-600"><i data-lucide="fingerprint" class="w-6 h-6"></i></div>
                                     <span class="px-2 py-1 bg-slate-100 text-slate-600 text-xs font-bold rounded">Not Configured</span>
                                 </div>
                                 <div>
                                     <h3 class="font-bold text-slate-900 dark:text-white">Biometric Devices</h3>
                                     <p class="text-xs text-slate-500 mt-1">ZKTeco & Hikvision sync.</p>
                                 </div>
                                 <button class="mt-4 w-full py-2 bg-cyan-600 text-white rounded-lg text-sm font-bold hover:bg-cyan-700">Connect</button>
                             </div>
                             
                             <!-- Accounting -->
                             <div class="bg-white dark:bg-slate-950 p-6 rounded-xl border border-slate-200 dark:border-slate-800 shadow-sm flex flex-col justify-between opacity-75">
                                 <div class="flex justify-between items-start mb-4">
                                     <div class="w-12 h-12 bg-purple-50 dark:bg-purple-900/20 rounded-lg flex items-center justify-center text-purple-600"><i data-lucide="book-open" class="w-6 h-6"></i></div>
                                     <span class="px-2 py-1 bg-slate-100 text-slate-500 text-xs font-bold rounded">Coming Soon</span>
                                 </div>
                                 <div>
                                     <h3 class="font-bold text-slate-900 dark:text-white">QuickBooks / Xero</h3>
                                     <p class="text-xs text-slate-500 mt-1">Automated ledger posting.</p>
                                 </div>
                                 <button class="mt-4 w-full py-2 border border-slate-200 dark:border-slate-700 rounded-lg text-sm font-bold text-slate-400 cursor-not-allowed">Unavailable</button>
                             </div>
                         </div>
                    </div>
                </div>

                <!-- TAB 8: SYSTEM PREFERENCES -->
                <div x-show="currentTab === 'system'" x-cloak x-transition.opacity>
                    <div class="max-w-4xl bg-white dark:bg-slate-950 rounded-xl border border-slate-200 dark:border-slate-800 p-6 shadow-sm">
                        <div class="space-y-6">
                            <div>
                                <h3 class="text-sm font-bold text-slate-900 dark:text-white mb-4 border-b border-slate-100 dark:border-slate-800 pb-2 uppercase tracking-wide">Data & Formatting</h3>
                                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                                    <div><label class="block text-xs font-bold text-slate-500 dark:text-slate-400 uppercase tracking-wide mb-1">Date Format</label><select class="w-full rounded-lg border-slate-300 dark:border-slate-700 bg-white dark:bg-slate-800 text-slate-900 dark:text-white focus:ring-brand-500 focus:border-brand-500 transition-colors shadow-sm text-sm"><option>DD/MM/YYYY</option><option>MM/DD/YYYY</option></select></div>
                                    <div><label class="block text-xs font-bold text-slate-500 dark:text-slate-400 uppercase tracking-wide mb-1">Currency Decimals</label><select class="w-full rounded-lg border-slate-300 dark:border-slate-700 bg-white dark:bg-slate-800 text-slate-900 dark:text-white focus:ring-brand-500 focus:border-brand-500 transition-colors shadow-sm text-sm"><option>2 (e.g. 1,000.00)</option><option>0 (e.g. 1,000)</option></select></div>
                                </div>
                            </div>
                            <div>
                                <h3 class="text-sm font-bold text-slate-900 dark:text-white mb-4 border-b border-slate-100 dark:border-slate-800 pb-2 uppercase tracking-wide">Maintenance</h3>
                                <div class="flex items-center justify-between p-4 bg-slate-50 dark:bg-slate-900 rounded-lg border border-slate-200 dark:border-slate-700">
                                    <div>
                                        <p class="text-sm font-bold text-slate-900 dark:text-white">Data Retention Policy</p>
                                        <p class="text-xs text-slate-500">Auto-archive records older than selected period.</p>
                                    </div>
                                    <select class="w-40 rounded-lg border-slate-300 dark:border-slate-700 bg-white dark:bg-slate-800 text-slate-900 dark:text-white focus:ring-brand-500 focus:border-brand-500 transition-colors shadow-sm text-sm"><option>5 Years</option><option>7 Years</option><option>Forever</option></select>
                                </div>
                            </div>
                            <div class="flex justify-end"><button class="px-6 py-2.5 bg-slate-700 text-white font-bold rounded-lg hover:bg-slate-800 shadow-md">Save System Settings</button></div>
                        </div>
                    </div>
                </div>


                
            </main>
        </div>


    <!-- Script Logic -->
    <?php include '../includes/dashboard_scripts.php'; ?>
</body>
</html>
